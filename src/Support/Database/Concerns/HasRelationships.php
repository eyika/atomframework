<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

use Eyika\Atom\Framework\Support\Str;
use Exception;
use Eyika\Atom\Framework\Support\Database\DB;

trait HasRelationships
{
    public function hasOne(string $class_name, $foreign_key = null, $local_key = null, callable|string|null $with = null)
    {
        //TODO: is_protected feature should be dynamic for relationships
        try {
            $foreign_model = new $class_name;
            $classname = get_called_class();
            $classname = basename(str_replace('\\', '/', $classname));

            $foreign_key = $foreign_key ?? Str::snake($classname) . '_id';
            $local_key = $local_key ?? 'id';

            $foreign_model = $foreign_model->where($foreign_key, $this->{$local_key})->first(true);

            if (!$foreign_model) {
                return null;
            }
            return $foreign_model;
        } catch (Exception $e) {
            logger()->error("got the following error: ".$e->getMessage(), $e->getTrace());
            return null;
        }
    }

    public function belongsTo(string $class_name, $foreign_key = null, $local_key = null)
    {
        //TODO: is_protected feature should be dynamic for relationships
        try {
            $parent_model = new $class_name;
            $class_name = basename(str_replace('\\', '/', $class_name));

            $foreign_key = $foreign_key ?? Str::snake($class_name) . '_id';
            $local_key = $local_key ?? 'id';

            $parent_model = $parent_model->where($local_key, $this->{$foreign_key})->first(false);

            if (!$parent_model) {
                return null;
            }
            return $parent_model;
        } catch (Exception $e) {
            logger()->error("got the following error: ".$e->getMessage(), $e->getTrace());
            return null;
        }
    }

    public function hasMany(string $class_name, $foreign_key = null, $local_key = null)
    {
        //TODO: is_protected feature should be dynamic for relationships
        try {
            $foreign_model = new $class_name;
            $classname = get_called_class();
            $classname = basename(str_replace('\\', '/', $classname));

            $foreign_key = $foreign_key ?? Str::snake($classname) . '_id';
            $local_key = $local_key ?? 'id';

            $foreign_models = $foreign_model->where($foreign_key, $this->{$local_key})->all(true);

            if (!$foreign_models) {
                return null;
            }

            // foreach ($foreign_models as &$model) {
            //     $model = $class_name::getBuilder()->fill($model, true);
            // }
            return $foreign_models;
        } catch (Exception $e) {
            logger()->error("got the following error: ".$e->getMessage(), $e->getTrace());
            return null;
        }
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
