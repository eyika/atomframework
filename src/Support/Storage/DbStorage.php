<?php
/*!
* Hybridauth
* https://hybridauth.github.io | https://github.com/hybridauth/hybridauth
*  (c) 2017 Hybridauth authors | https://hybridauth.github.io/license.html
*/

namespace Basttyy\FxDataServer\libs\Storage;

// use Basttyy\FxDataServer\libs\Interfaces\StorageInterface;

use Basttyy\FxDataServer\libs\mysqly;
use Hybridauth\Exception\RuntimeException;
use Hybridauth\Storage\StorageInterface;

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

        $value = mysqly::get($key, $this->storeNamespace);

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

        mysqly::set($key, $value, $this->storeNamespace);
        // $_SESSION[$this->storeNamespace][$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        mysqly::clear($this->storeNamespace);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $key = $this->keyPrefix . strtolower($key);

        mysqly::unset($key, $this->storeNamespace);
        // if (isset($_SESSION[$this->storeNamespace], $_SESSION[$this->storeNamespace][$key])) {
        //     $tmp = $_SESSION[$this->storeNamespace];

        //     unset($tmp[$key]);

        //     $_SESSION[$this->storeNamespace] = $tmp;
        // }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMatch($key)
    {
        $key = $this->keyPrefix . strtolower($key);

        mysqly::unset($key, $this->storeNamespace);
        // if (isset($_SESSION[$this->storeNamespace]) && count($_SESSION[$this->storeNamespace])) {
        //     $tmp = $_SESSION[$this->storeNamespace];

        //     foreach ($tmp as $k => $v) {
        //         if (strstr($k, $key)) {
        //             unset($tmp[$k]);
        //         }
        //     }

        //     $_SESSION[$this->storeNamespace] = $tmp;
        // }
    }
}
