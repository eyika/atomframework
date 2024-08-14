<?php

namespace Basttyy\FxDataServer\libs\Traits;

use Basttyy\FxDataServer\libs\Interfaces\ModelInterface;
use Basttyy\FxDataServer\libs\Interfaces\UserModelInterface;
use Basttyy\FxDataServer\libs\mysqly;

trait UserAwareQueryBuilder
{
    public static function boot(ModelInterface | UserModelInterface | null $user)
    {
    }
    public function findByUsername($name, $is_protected = true)
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $query_arr['username'] = $name;
        if ($this->child->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }

        $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        if (!$user = mysqly::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
        if (count( $user ) < 1) {
            $this->resetInstance();
            return false;
        }

        $this->resetInstance();
        return $this->fill($user[0]);
    }

    public function findByEmail(string $email, $is_protected = true)
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $query_arr['email'] = $email;
        if ($this->child->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }

        $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        if (!$user = mysqly::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
        if (count( $user ) < 1) {
            $this->resetInstance();
            return false;
        }

        $this->resetInstance();
        return $this->fill($user[0]);
    }
}