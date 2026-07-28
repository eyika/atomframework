<?php

namespace Eyika\Atom\Framework\Tests\Unit;

use Eyika\Atom\Reverb\Auth\Signature;
use Eyika\Atom\Reverb\Backplane\RedisBackplane;
use Eyika\Atom\Reverb\Connection;
use Eyika\Atom\Reverb\Presence\LocalPresenceStore;
use Eyika\Atom\Reverb\Presence\RedisPresenceStore;
use Eyika\Atom\Reverb\Protocol\Frame;
use Eyika\Atom\Reverb\Server;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

$reverb = dirname(__DIR__, 3) . '/atom-reverb/src';
foreach ([
    '/Protocol/Handshake.php', '/Protocol/Frame.php', '/ChannelManager.php', '/Connection.php',
    '/Auth/Signature.php', '/Backplane/Backplane.php', '/Backplane/LocalBackplane.php',
    '/Backplane/RedisBackplane.php', '/Presence/PresenceStore.php', '/Presence/LocalPresenceStore.php',
    '/Redis/RedisClient.php', '/Presence/RedisPresenceStore.php', '/Server.php',
] as $file) {
    require_once $reverb . $file;
}

/**
 * Covers the production hardening of atom-reverb: HMAC channel/ingest auth, presence
 * membership, the RESP backplane parser, connection write-buffering, and frame
 * fragmentation — all without real sockets.
 */
class ReverbProductionTest extends TestCase
{
    private const KEY = 'atom';
    private const SECRET = 'super-secret';

    private function connection(int $id): Connection
    {
        $conn = new Connection($id, fopen('php://memory', 'r+'), 0.0);
        $conn->handshook = true;
        $conn->socketId = $id . '.1';
        return $conn;
    }

    private function server(): Server
    {
        return new Server(null, ['app_key' => self::KEY, 'app_secret' => self::SECRET]);
    }

    // --- Channel auth ------------------------------------------------------------------

    public function test_private_channel_requires_valid_auth(): void
    {
        $server = $this->server();
        $conn = $this->connection(9);

        // Bad signature → rejected.
        $server->handleClientMessage($conn, json_encode([
            'event' => 'pusher:subscribe',
            'data'  => ['channel' => 'private-room', 'auth' => 'atom:deadbeef'],
        ]));
        $this->assertSame([], $server->channels()->subscribers('private-room'));

        // Correct signature (as the app's auth endpoint would produce) → accepted.
        $auth = Signature::channelAuth(self::KEY, self::SECRET, '9.1', 'private-room');
        $server->handleClientMessage($conn, json_encode([
            'event' => 'pusher:subscribe',
            'data'  => ['channel' => 'private-room', 'auth' => $auth],
        ]));
        $this->assertSame([9], $server->channels()->subscribers('private-room'));
    }

    public function test_presence_channel_tracks_members(): void
    {
        $server = $this->server();
        $conn = $this->connection(9);

        $channelData = json_encode(['user_id' => 'u1', 'user_info' => ['name' => 'Ada']]);
        $auth = Signature::channelAuth(self::KEY, self::SECRET, '9.1', 'presence-room', $channelData);

        $server->handleClientMessage($conn, json_encode([
            'event' => 'pusher:subscribe',
            'data'  => ['channel' => 'presence-room', 'auth' => $auth, 'channel_data' => $channelData],
        ]));

        // Membership lives in the presence store, deduped by user_id.
        $members = $server->presence()->members('presence-room');
        $this->assertArrayHasKey('u1', $members);
        $this->assertSame(['name' => 'Ada'], $members['u1']);
        $this->assertSame(1, $server->presence()->count('presence-room'));
    }

    public function test_presence_store_reference_counts_users(): void
    {
        $store = new LocalPresenceStore();

        // Two connections for the SAME user → one member; member_added fires only once.
        $this->assertTrue($store->join('presence-x', 'n:1', 'u1', ['name' => 'Ada']));
        $this->assertFalse($store->join('presence-x', 'n:2', 'u1', ['name' => 'Ada']));
        $this->assertSame(1, $store->count('presence-x'));

        // A second user → member_added.
        $this->assertTrue($store->join('presence-x', 'n:3', 'u2', ['name' => 'Bo']));
        $this->assertSame(2, $store->count('presence-x'));
        $this->assertEqualsCanonicalizing(['u1', 'u2'], array_keys($store->members('presence-x')));

        // First user's first connection leaves → NOT the last, no member_removed.
        $this->assertFalse($store->leave('presence-x', 'n:1', 'u1'));
        // Second connection leaves → last, member_removed.
        $this->assertTrue($store->leave('presence-x', 'n:2', 'u1'));
        $this->assertSame(1, $store->count('presence-x'));
        $this->assertArrayNotHasKey('u1', $store->members('presence-x'));
    }

    public function test_ingest_signature_roundtrip(): void
    {
        $body = json_encode(['channel' => 'c', 'event' => 'e', 'data' => []]);
        $sig = Signature::ingest(self::SECRET, $body);

        $this->assertTrue(Signature::verifyIngest(self::SECRET, $body, $sig));
        $this->assertFalse(Signature::verifyIngest(self::SECRET, $body, 'wrong'));
    }

    public function test_channel_classification(): void
    {
        $this->assertTrue(Signature::isPrivate('private-x'));
        $this->assertTrue(Signature::isPrivate('presence-x'));
        $this->assertTrue(Signature::isPresence('presence-x'));
        $this->assertFalse(Signature::isPresence('private-x'));
        $this->assertFalse(Signature::isPrivate('public-x'));
    }

    public function test_redis_presence_keys_use_a_cluster_hash_tag(): void
    {
        $store = (new ReflectionClass(RedisPresenceStore::class))->newInstanceWithoutConstructor();
        $keys = (new ReflectionClass($store))->getMethod('keys');
        $keys->setAccessible(true);

        // All three keys must share the {channel} hash tag → same Redis Cluster slot.
        $this->assertSame(
            ['presence:{presence-room}', 'presence:{presence-room}:u', 'presence:{presence-room}:i'],
            $keys->invoke($store, 'presence-room')
        );
    }

    // --- Backplane RESP parser ---------------------------------------------------------

    public function test_resp_parser_extracts_a_pubsub_message(): void
    {
        $raw = "*3\r\n\$7\r\nmessage\r\n\$6\r\nmychan\r\n\$5\r\nhello\r\n";
        [$value, $consumed] = RedisBackplane::parseReply($raw, 0);

        $this->assertSame(['message', 'mychan', 'hello'], $value);
        $this->assertSame(strlen($raw), $consumed);

        // An incomplete buffer yields null (wait for more).
        $this->assertNull(RedisBackplane::parseReply("*3\r\n\$7\r\nmess", 0));
    }

    // --- Connection write buffer -------------------------------------------------------

    public function test_connection_buffers_and_flushes_writes(): void
    {
        $conn = new Connection(1, fopen('php://memory', 'r+'), 0.0);
        $conn->queue('abc');

        $this->assertTrue($conn->hasPendingWrites());
        $this->assertTrue($conn->flush());
        $this->assertFalse($conn->hasPendingWrites());

        rewind($conn->socket);
        $this->assertSame('abc', stream_get_contents($conn->socket));
    }

    // --- Frame fragmentation -----------------------------------------------------------

    public function test_frame_exposes_the_fin_bit_for_fragments(): void
    {
        // Fragment start: opcode TEXT, FIN off.
        $start = chr(0x01) . chr(0x80 | 4) . 'abcd' . Frame::applyMask('part', 'abcd');
        $d1 = Frame::decode($start);
        $this->assertFalse($d1['fin']);
        $this->assertSame(Frame::OP_TEXT, $d1['opcode']);

        // Final continuation: opcode CONTINUATION (0x0), FIN on.
        $end = chr(0x80) . chr(0x80 | 2) . 'abcd' . Frame::applyMask('ed', 'abcd');
        $d2 = Frame::decode($end);
        $this->assertTrue($d2['fin']);
        $this->assertSame(Frame::OP_CONTINUATION, $d2['opcode']);
    }
}
