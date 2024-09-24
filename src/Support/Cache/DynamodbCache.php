<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;

class DynamoDbCache implements CacheInterface
{
    /**
     * The DynamoDb client instance.
     *
     * @var \Aws\DynamoDb\DynamoDbClient
     */
    protected $dynamoDb;

    /**
     * The DynamoDb table name.
     *
     * @var string
     */
    protected $table;

    /**
     * Constructor to initialize the DynamoDb connection.
     *
     * @param DynamoDbClient $dynamoDb
     * @param string $table
     */
    public function __construct()
    {
        $config = config('cache.stores.dynamodb');

        $this->dynamoDb = new DynamoDbClient([
            'version' => 'latest',
            'region'  => $config['region'],
            'credentials' => [
                'key'    => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);
        
        $this->table = $config['table'];
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        try {
            $result = $this->dynamoDb->getItem([
                'TableName' => $this->table,
                'Key' => [
                    'id' => ['S' => $key],
                ],
            ]);

            if (isset($result['Item'])) {
                // Check if the item has expired
                $expiresAt = (int) $result['Item']['expires_at']['N'];
                if ($expiresAt === 0 || $expiresAt > time()) {
                    return $result['Item']['value']['S'] ?? null;
                } else {
                    // Item has expired, delete it
                    $this->delete($key);
                    return null;
                }
            }

            return null;
        } catch (DynamoDbException $e) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $expiresAt = ($ttl > 0) ? time() + $ttl : 0; // 0 means no expiration

        try {
            $this->dynamoDb->putItem([
                'TableName' => $this->table,
                'Item' => [
                    'id' => ['S' => $key],
                    'value' => ['S' => (string)$value],
                    'expires_at' => ['N' => (string)$expiresAt],
                ],
            ]);

            return true;
        } catch (DynamoDbException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        try {
            $this->dynamoDb->deleteItem([
                'TableName' => $this->table,
                'Key' => [
                    'id' => ['S' => $key],
                ],
            ]);

            return true;
        } catch (DynamoDbException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        // DynamoDB doesn't support a "truncate" operation directly.
        // You need to scan the table and delete items individually.
        try {
            $result = $this->dynamoDb->scan([
                'TableName' => $this->table,
            ]);

            foreach ($result['Items'] as $item) {
                $this->delete($item['id']['S']);
            }

            return true;
        } catch (DynamoDbException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
