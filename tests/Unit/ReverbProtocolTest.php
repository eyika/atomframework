<?php

namespace Eyika\Atom\Framework\Tests\Unit;

use Eyika\Atom\Reverb\ChannelManager;
use Eyika\Atom\Reverb\Connection;
use Eyika\Atom\Reverb\Protocol\Frame;
use Eyika\Atom\Reverb\Protocol\Handshake;
use Eyika\Atom\Reverb\Server;
use PHPUnit\Framework\TestCase;

// atom-reverb is a sibling repo; pull in the classes under test (no framework deps).
// Path: atomframework/tests/Unit -> eyika/ -> atom-reverb/src/*
$reverb = dirname(__DIR__, 3) . '/atom-reverb/src';
require_once $reverb . '/Protocol/Handshake.php';
require_once $reverb . '/Protocol/Frame.php';
require_once $reverb . '/ChannelManager.php';
require_once $reverb . '/Connection.php';
require_once $reverb . '/Auth/Signature.php';
require_once $reverb . '/Backplane/Backplane.php';
require_once $reverb . '/Backplane/LocalBackplane.php';
require_once $reverb . '/Backplane/RedisBackplane.php';
require_once $reverb . '/Presence/PresenceStore.php';
require_once $reverb . '/Presence/LocalPresenceStore.php';
require_once $reverb . '/Redis/RedisClient.php';
require_once $reverb . '/Presence/RedisPresenceStore.php';
require_once $reverb . '/Server.php';

/**
 * Covers the atom-reverb WebSocket protocol primitives + pub/sub bookkeeping without any
 * sockets: RFC 6455 handshake + framing (with the spec's own test vectors), channel
 * subscription targeting, and the broadcast-ingest / client-message seams the Server
 * exposes for exactly this reason.
 */
class ReverbProtocolTest extends TestCase
{
    public function test_handshake_accept_key_matches_the_rfc6455_vector(): void
    {
        // RFC 6455 §1.3 worked example.
        $this->assertSame(
            's3pPLMBiTxaQ9kYGzzhZRbK+xOo=',
            Handshake::acceptKey('dGhlIHNhbXBsZSBub25jZQ==')
        );
    }

    public function test_handshake_detects_upgrade_and_extracts_key(): void
    {
        $req = "GET /app HTTP/1.1\r\nHost: x\r\nUpgrade: websocket\r\n"
            . "Connection: Upgrade\r\nSec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n\r\n";

        $this->assertTrue(Handshake::isUpgrade($req));
        $this->assertSame('dGhlIHNhbXBsZSBub25jZQ==', Handshake::keyFrom($req));
        $this->assertStringContainsString('Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=', Handshake::response('dGhlIHNhbXBsZSBub25jZQ=='));
    }

    public function test_decodes_a_masked_client_text_frame(): void
    {
        $payload = json_encode(['event' => 'subscribe', 'data' => ['channel' => 'orders']]);
        $mask = 'abcd';
        $frame = chr(0x81) . chr(0x80 | strlen($payload)) . $mask . Frame::applyMask($payload, $mask);

        $decoded = Frame::decode($frame);

        $this->assertNotNull($decoded);
        $this->assertSame(Frame::OP_TEXT, $decoded['opcode']);
        $this->assertSame($payload, $decoded['payload']);
        $this->assertSame(strlen($frame), $decoded['consumed']);
    }

    public function test_encodes_an_unmasked_server_frame(): void
    {
        $this->assertSame(chr(0x81) . chr(2) . 'hi', Frame::encode('hi'));

        // 16-bit extended length path.
        $payload = str_repeat('x', 200);
        $frame = Frame::encode($payload);
        $this->assertSame(0x81, ord($frame[0]));
        $this->assertSame(126, ord($frame[1]));
        $this->assertSame(200, unpack('n', substr($frame, 2, 2))[1]);
        $this->assertSame($payload, substr($frame, 4));
    }

    public function test_decode_returns_null_on_an_incomplete_buffer(): void
    {
        $this->assertNull(Frame::decode(chr(0x81)));                          // header truncated
        $this->assertNull(Frame::decode(chr(0x81) . chr(0x8A) . 'abcd' . 'x')); // says 10 bytes, has 1
    }

    public function test_channel_manager_targets_and_forgets(): void
    {
        $cm = new ChannelManager();
        $cm->subscribe(1, 'orders');
        $cm->subscribe(2, 'orders');
        $cm->subscribe(2, 'news');

        $this->assertEqualsCanonicalizing([1, 2], $cm->subscribers('orders'));
        $this->assertSame([2], $cm->subscribers('news'));
        $this->assertEqualsCanonicalizing(['orders', 'news'], $cm->channelsFor(2));

        $cm->unsubscribe(1, 'orders');
        $this->assertSame([2], $cm->subscribers('orders'));

        $cm->forget(2); // disconnect
        $this->assertSame([], $cm->subscribers('orders'));
        $this->assertSame([], $cm->channels());
    }

    public function test_server_parses_a_broadcast_ingest_body(): void
    {
        $body = json_encode(['channel' => 'orders', 'event' => 'OrderShipped', 'data' => ['id' => 7]]);
        $raw = "POST /broadcast HTTP/1.1\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body;

        $parsed = Server::parseIngest($raw);
        $this->assertSame('orders', $parsed['channel']);
        $this->assertSame('OrderShipped', $parsed['event']);
        $this->assertSame(['id' => 7], $parsed['data']);

        $this->assertNull(Server::parseIngest("POST / HTTP/1.1\r\n\r\nnot-json"));
    }

    public function test_server_client_subscribe_message_updates_channels(): void
    {
        $server = new Server();
        $conn = $this->connection(5);

        // A public channel needs no auth.
        $server->handleClientMessage($conn, json_encode(['event' => 'pusher:subscribe', 'data' => ['channel' => 'orders']]));
        $this->assertSame([5], $server->channels()->subscribers('orders'));

        $server->handleClientMessage($conn, json_encode(['event' => 'pusher:unsubscribe', 'data' => ['channel' => 'orders']]));
        $this->assertSame([], $server->channels()->subscribers('orders'));
    }

    /** A handshook Connection over an in-memory socket (no real network). */
    private function connection(int $id): Connection
    {
        $conn = new Connection($id, fopen('php://memory', 'r+'), 0.0);
        $conn->handshook = true;
        $conn->socketId = $id . '.1';
        return $conn;
    }
}
