<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;

trait UserAwareQueryBuilder
{
    public function _findByUsername($name, $is_protected = true)
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $query_arr['username'] = $name;
        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }
        $this->useHashForEncryptedColumnComparisonQueries($query_arr);

        $fields = $this->readableFields();
        info('query array is', $query_arr);
        if (!$user = DatabaseConnection::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
        if (count( $user ) < 1) {
            $this->resetInstance();
            return false;
        }

        $this->resetInstance();
        return $this->fill($user[0], true);
    }

    public function _findByEmail(string $email, $is_protected = true)
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $query_arr['email'] = $email;
        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }
        $this->useHashForEncryptedColumnComparisonQueries($query_arr);

        $fields = $this->readableFields();
        if (!$user = DatabaseConnection::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
        if (count( $user ) < 1) {
            $this->resetInstance();
            return false;
        }

        $this->resetInstance();
        return $this->fill($user[0], true);
    }
}
