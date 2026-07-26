<?php

namespace Eyika\Atom\Framework\Support\Database;

/**
 * Lightweight relation descriptor. Relationship methods (hasOne/belongsTo/hasMany)
 * now RETURN one of these instead of executing immediately, so `with()` can inspect
 * the keys and batch-load all parents with a single whereIn (no N+1).
 */
class Relation
{
    public const HAS_ONE = 'hasOne';
    public const BELONGS_TO = 'belongsTo';
    public const HAS_MANY = 'hasMany';

    public function __construct(
        public string $type,
        public string $relatedClass,
        public string $foreignKey,
        public string $localKey
    ) {
    }

    public function isMany(): bool
    {
        return $this->type === self::HAS_MANY;
    }

    /** The attribute on the PARENT whose value we match against the related side. */
    public function parentKey(): string
    {
        // belongsTo: the parent holds the FK; hasOne/hasMany: the parent holds the local PK.
        return $this->type === self::BELONGS_TO ? $this->foreignKey : $this->localKey;
    }

    /** The column on the RELATED model matched against the parent key. */
    public function relatedKey(): string
    {
        return $this->type === self::BELONGS_TO ? $this->localKey : $this->foreignKey;
    }

    /**
     * Lazily resolve the relation for a single parent (one query).
     */
    public function getResults($parent): mixed
    {
        $value = $parent->{$this->parentKey()} ?? null;
        if ($value === null) {
            return $this->isMany() ? [] : null;
        }

        $query = (new $this->relatedClass)->where($this->relatedKey(), $value);

        return $this->isMany() ? ($query->all(true) ?: []) : ($query->first(true) ?: null);
    }

    /**
     * Eager-load for many parents with ONE whereIn; returns a map keyed by the
     * related-side match value.
     */
    public function eagerLoad(array $parents): array
    {
        $keys = [];
        foreach ($parents as $parent) {
            $value = $parent->{$this->parentKey()} ?? null;
            if ($value !== null) {
                $keys[] = $value;
            }
        }

        $keys = array_values(array_unique($keys));
        if (empty($keys)) {
            return [];
        }

        $related = (new $this->relatedClass)->whereIn($this->relatedKey(), $keys)->all(true) ?: [];

        $map = [];
        foreach ($related as $item) {
            $matchValue = $item->{$this->relatedKey()};
            if ($this->isMany()) {
                $map[$matchValue][] = $item;
            } else {
                $map[$matchValue] = $item;
            }
        }

        return $map;
    }
}
