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

    protected array $deferredItems = [];

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
    public function getItem(string $key): CacheItem
    {
        try {
            $result = $this->dynamoDb->getItem([
                'TableName' => $this->table,
                'Key' => [
                    'id' => ['S' => $key],
                ],
            ]);
            $value = null;

            if (isset($result['Item'])) {
                // Check if the item has expired
                $expiresAt = (int) $result['Item']['expires_at']['N'];
                if ($expiresAt === 0 || $expiresAt > time()) {
                    $value = $result['Item']['value']['S'] ?? null;
                } else {
                    // Item has expired, delete it
                    $this->deleteItem($key);
                    $value =  null;
                }
            }

            return new CacheItem($key, $value, $value !== null);
        } catch (DynamoDbException $e) {
            return new CacheItem($key, null, false);
        }
    }

    /**
     * @param string[] $keys
     * 
     * @return CacheItemInterface[]
     * 
     * @throws InvalidArgumentException
     */
    public function getItems($keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }
        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        return $this->getItem($key)->isHit();
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
                $this->deleteItem($item['id']['S']);
            }

            return true;
        } catch (DynamoDbException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key): bool
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
     * @throws InvalidArgumentException
     */
    public function deleteItems($keys = []): bool
    {
        foreach ($keys as $key) {
            if (!$this->deleteItem($key))
                return false;
        }
        return true;
    }

    /**
     * @param CacheItem $item
     */
    public function save($item): bool
    {
        $expiresAt = ($item->getExpiration() > 0) ? time() + $item->getExpiration() : 0; // 0 means no expiration

        try {
            $this->dynamoDb->putItem([
                'TableName' => $this->table,
                'Item' => [
                    'id' => ['S' => $item->getKey()],
                    'value' => ['S' => (string)$item->get()],
                    'expires_at' => ['N' => (string)$expiresAt],
                ],
            ]);

            return true;
        } catch (DynamoDbException $e) {
            return false;
        }
    }

    /**
     * @param CacheItem $item
     */
    public function saveDeferred($item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }

        $this->deferredItems[$item->getKey()] = $item;
        return true;
    }

    public function commit(): bool
    {
        $allSaved = true;

        foreach ($this->deferredItems as $key => $item) {
            if (!$this->save($item)) {
                $allSaved = false;
            }
        }

        // Clear deferred items after attempting to save
        $this->deferredItems = [];

        return $allSaved;
    }
}
