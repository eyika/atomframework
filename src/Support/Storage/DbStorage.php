<?php
namespace Eyika\Atom\Framework\Support\Storage;

use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use Eyika\Atom\Framework\Support\Storage\Contracts\StorageInterface;
use Hybridauth\Exception\RuntimeException;

/**
 * Hybridauth storage manager
 */
class DbStorage implements StorageInterface
{
    /**
     * Namespace
     *
     * @var string
     */
    protected $storeNamespace = '';

    /**
     * Key prefix
     *
     * @var string
     */
    protected $keyPrefix = '';

    /**
     * Initiate a new session
     *
     * @throws RuntimeException
     */
    public function __construct(string $store_namespace = 'default')
    {
        $this->storeNamespace = $store_namespace;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        $key = $this->keyPrefix . strtolower($key);

        $value = DatabaseConnection::get($key, $this->storeNamespace);

        if (isset($value)) {
            if (is_array($value) && array_key_exists('lateObject', $value)) {
                $value = unserialize($value['lateObject']);
            }

            return $value;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        $key = $this->keyPrefix . strtolower($key);

        if (is_object($value)) {
            // We encapsulate as our classes may be defined after session is initialized.
            $value = ['lateObject' => serialize($value)];
        }

        DatabaseConnection::set($key, $value, $this->storeNamespace);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        DatabaseConnection::clear($this->storeNamespace);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $key = $this->keyPrefix . strtolower($key);

        DatabaseConnection::unset($key, $this->storeNamespace);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMatch($key)
    {
        $key = $this->keyPrefix . strtolower($key);

        DatabaseConnection::unset($key, $this->storeNamespace);
    }
}
