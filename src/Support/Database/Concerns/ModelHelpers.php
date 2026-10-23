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

    /**
     * Rewrite equality filters on encrypted columns onto their `<col>_hash` HMAC replica, so an
     * encrypted column stays queryable by value (the ciphertext itself can never be compared).
     *
     * The filter keys are NOT always bare column names. where() runs its column through
     * QueryBuilder::_parseColumn(), which qualifies an own-table column to `table.column`, and
     * __where() appends a `__dupN` marker when the same column is filtered twice. The `encrypted`
     * / `ignore_hash_replica` lists, by contrast, hold BARE names. So the key is reduced to its
     * bare column for the membership test, and the replica suffix is spliced back in while
     * PRESERVING the qualifier and the dup marker — `users.email` → `users.email_hash`,
     * `users.email__dup1` → `users.email_hash__dup1` — so the emitted SQL column reference and the
     * unique bind-param name both stay correct.
     *
     * Matching only the bare name was the bug behind "every login fails for a user who exists":
     * findBy() assigns its key directly and stayed bare, so it worked, but where()->first() (what
     * the Eloquent auth driver uses) qualified the key, the intersection came up empty, and the
     * query compared the ENCRYPTED column against a plaintext value — matching nothing, ever.
     */
    public function useHashForEncryptedColumnComparisonQueries(array &$query_arr)
    {
        $hashable = array_diff($this::encrypted, $this::ignore_hash_replica);
        if (!$hashable) {
            return;
        }

        foreach (array_keys($query_arr) as $key) {
            $bare = $key;

            $dupMarker = '';
            if (($pos = strpos($bare, '__dup')) !== false) {
                $dupMarker = substr($bare, $pos);   // "__dup1"
                $bare = substr($bare, 0, $pos);
            }

            $qualifier = '';
            if (($dot = strrpos($bare, '.')) !== false) {
                $qualifier = substr($bare, 0, $dot + 1);   // "users."
                $bare = substr($bare, $dot + 1);           // "email"
            }

            if (!in_array($bare, $hashable, true)) {
                continue;
            }

            $query_arr[$key] = getHash($query_arr[$key], 'sha256', env('APP_KEY'));
            Arr::replaceKey($query_arr, $key, $qualifier . $bare . $this::hashed_col_suffix . $dupMarker);
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
