<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

/**
 * Data-returning relation ergonomics.
 *
 * {@see HasRelationships} makes hasOne/hasMany/belongsTo RETURN a {@see \Eyika\Atom\Framework\Support\Database\Relation}
 * descriptor so `with()` can batch-load every parent with a single whereIn (no N+1).
 * A model that uses THIS trait instead gets the ergonomic form where a direct call —
 * `$model->relation()` — resolves the relation and returns the related DATA:
 * a single model (or null) for hasOne/belongsTo, an array for hasMany.
 *
 * `with()` and `getRelation()` continue to work: both tolerate a resolved
 * (non-Relation) return via their legacy/per-parent branch.
 *
 * Requires the using class to inherit the base relation builders — i.e. extend a
 * Model that uses {@see HasRelationships} — since each override delegates to
 * `parent::`. belongsToMany is unchanged (already returns data) and is inherited.
 */
trait ResolvesRelations
{
    public function hasOne(string $class_name, $foreign_key = null, $local_key = null)
    {
        return parent::hasOne($class_name, $foreign_key, $local_key)->getResults($this);
    }

    public function belongsTo(string $class_name, $foreign_key = null, $local_key = null)
    {
        return parent::belongsTo($class_name, $foreign_key, $local_key)->getResults($this);
    }

    public function hasMany(string $class_name, $foreign_key = null, $local_key = null)
    {
        return parent::hasMany($class_name, $foreign_key, $local_key)->getResults($this);
    }
}
