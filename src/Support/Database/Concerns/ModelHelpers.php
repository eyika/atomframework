<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

use Eyika\Atom\Framework\Support\Arr;

trait ModelHelpers
{
    use ModelProperties;

    public function isSaved()
    {
        if ($this->{$this->primaryKey} == null)
            return false;

        return true;
    }

    public function isNotSaved()
    {
        return !$this->isSaved();
    }

    public function incrementing()
    {
        return $this->incrementing;
    }

    public function exists()
    {
        return $this->exists;
    }

    public function wasRecentlyCreated()
    {
        return $this->wasRecentlyCreated;
    }

    public function __get($name) {
        return $this->dynamicProperties[$name] ?? null;
    }

    public function __set($name, $value) {
        $this->dynamicProperties[$name] = $value;
    }

    private function encryptValues(array &$values)
    {
        $keys = array_intersect($this::encrypted, array_keys($values));

        foreach ($keys as $key) {
            if (!empty($values[$key])) { // Avoid decrypting null values
                $values[$key] = encrypt($values[$key]);
            }
        }
    }

    private function decryptValues(array &$values)
    {
        $keys = array_intersect($this::encrypted, array_keys($values));

        foreach ($keys as $key) {
            if (!empty($values[$key])) { // Avoid decrypting null values
                $values[$key] = decrypt($values[$key]);
            }
        }
    }

    public function useHashForEncryptedColumnComparisonQueries(array &$query_arr)
    {
        $keys = array_intersect(array_diff($this::encrypted, $this::ignore_hash_replica), array_keys($query_arr));

        foreach ($keys as $key) {
            $query_arr[$key] = getHash($query_arr[$key], 'sha256', env('APP_KEY'));
            Arr::replaceKey($query_arr, $key, $key.$this::hashed_col_suffix);
        }
    }

    public function createHashDuplicatesForCreateAndUpdateQueries(array &$values)
    {
        $keys = array_intersect(array_diff($this::encrypted, $this::ignore_hash_replica), array_keys($values));

        foreach ($keys as $key) {
            $values[$key.$this::hashed_col_suffix] = getHash($values[$key], 'sha256', env('APP_KEY'));
        }
    }

    /**
     * Re-serialize values for columns whose cast is 'array' / 'json' / 'object'
     * before they hit the DB writer. fill() runs castAttribute() on writes too,
     * so a json_encoded payload gets decoded back into a PHP value; without
     * this step, Connection::values() binds it as-is and exec() then tries to
     * expand an array as an IN-list (SQLSTATE[HY093]), or fails outright on an
     * object ("Object of class stdClass could not be converted to string").
     *
     * Both shapes must be handled: the 'array'/'json' casts decode to an array,
     * but 'object' decodes to a stdClass.
     */
    private function serializeCastedValues(array &$values)
    {
        if (!defined('static::casts')) {
            return;
        }

        foreach ($values as $key => $value) {
            if (!array_key_exists($key, static::casts)) {
                continue;
            }
            $cast = static::casts[$key];
            if ($cast !== 'array' && $cast !== 'json' && $cast !== 'object') {
                continue;
            }
            if (is_array($value) || is_object($value)) {
                $values[$key] = json_encode($value);
            }
        }
    }
}
