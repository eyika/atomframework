<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

use Eyika\Atom\Framework\Support\Str;
use Exception;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Relation;

trait HasRelationships
{
    // NOTE: hasOne/belongsTo/hasMany now RETURN a Relation descriptor instead of
    // eagerly executing + returning data. This lets with() batch-load with a single
    // whereIn (no N+1). Access results via with('rel') + $model->rel, or lazily via
    // $model->getRelation('rel'). (belongsToMany is still the legacy eager form.)
    public function hasOne(string $class_name, $foreign_key = null, $local_key = null)
    {
        $parent = basename(str_replace('\\', '/', get_called_class()));
        return new Relation(
            Relation::HAS_ONE,
            $class_name,
            $foreign_key ?? Str::snake($parent) . '_id',
            $local_key ?? 'id'
        );
    }

    public function belongsTo(string $class_name, $foreign_key = null, $local_key = null)
    {
        $related = basename(str_replace('\\', '/', $class_name));
        return new Relation(
            Relation::BELONGS_TO,
            $class_name,
            $foreign_key ?? Str::snake($related) . '_id',
            $local_key ?? 'id'
        );
    }

    public function hasMany(string $class_name, $foreign_key = null, $local_key = null)
    {
        $parent = basename(str_replace('\\', '/', get_called_class()));
        return new Relation(
            Relation::HAS_MANY,
            $class_name,
            $foreign_key ?? Str::snake($parent) . '_id',
            $local_key ?? 'id'
        );
    }

    /**
     * Lazily resolve a relation defined by a method on this model (single parent).
     */
    public function getRelation(string $name): mixed
    {
        $relation = $this->{$name}();

        return $relation instanceof Relation ? $relation->getResults($this) : $relation;
    }
    /**
     * Basic many-to-many without joins.
     *
     * @param class-string $class_name        Related model class (e.g., User::class)
     * @param string       $pivotTable        Pivot table name (e.g., 'post_likes')
     * @param string|null  $foreignPivotKey   FK on pivot pointing to *this* model (default: snake(ThisClass).'_id')
     * @param string|null  $relatedPivotKey   FK on pivot pointing to the related model (default: snake(RelatedClass).'_id')
     * @param string|null  $parentKey         Local PK on this model (default: 'id')
     * @param string|null  $relatedKey        PK on related model (default: 'id')
     * @param callable|null $pivotFilter      Optional: function(array $pivotRow): bool to filter pivot rows in PHP
     *
     * @return array|null  Array of related model instances, or null if nothing found
     */
    public function belongsToMany(
        string $class_name,
        string $pivotTable,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        ?callable $pivotFilter = null,
        ?bool $pivotSoftDeletes = null
    ) {
        try {
            // Infer sensible defaults
            $thisClass = get_called_class();
            $thisShort = basename(str_replace('\\', '/', $thisClass));
            $relatedShort = basename(str_replace('\\', '/', $class_name));

            $parentKey       = $parentKey       ?: 'id';
            $relatedKey      = $relatedKey      ?: 'id';
            $foreignPivotKey = $foreignPivotKey ?: Str::snake($thisShort) . '_id';
            $relatedPivotKey = $relatedPivotKey ?: Str::snake($relatedShort) . '_id';

            // 1) Pull pivot rows for this parent
            $pivots = DB::table($pivotTable)->where($foreignPivotKey, $this->{$parentKey});
            if ($pivotSoftDeletes)
                $pivots->whereNull('deleted_at');
            $pivots = $pivots->get();

            if (!$pivots || !is_array($pivots)) {
                return null;
            }

            // 2) Optional in-PHP filter over pivot rows (to emulate whereNotNull etc.)
            if ($pivotFilter) {
                $pivots = array_values(array_filter($pivots, $pivotFilter));
                if (empty($pivots)) {
                    return null;
                }
            }

            // 3) Extract related IDs from pivot
            $ids = array_values(array_unique(array_map(
                fn ($row) => $row[$relatedPivotKey] ?? null,
                $pivots
            )));
            $ids = array_values(array_filter($ids, fn ($v) => !is_null($v)));

            if (empty($ids)) {
                return null;
            }

            $results = (new $class_name)->whereIn($relatedKey, $ids)->get();

            return empty($results) ? null : $results;
        } catch (Exception $e) {
            logger()->error("belongsToMany error: " . $e->getMessage(), $e->getTrace());
            return null;
        }
    }
}
