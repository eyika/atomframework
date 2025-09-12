<?php

namespace Eyika\Atom\Framework\Support\Database\Contracts;

interface ModelRelationshipInterface extends ModelInterface
{
    /**
     * @param string $class_name
     * @param string $foreign_key
     * @param string $local_key
     * 
     * @return null|$class_name
     */
    public function hasOne($class_name, $foreign_key = null, $local_key = null);

    /**
     * @param string $class_name
     * @param string $foreign_key
     * @param string $local_key
     * 
     * @return null|$class_name[]
     */
    public function hasMany($class_name, $foreign_key, $local_key);

    /**
     * @param string $class_name
     * @param string $foreign_key
     * @param string $local_key
     * 
     * @return null|$class_name
     */
    public function belongsTo($class_name, $foreign_key, $local_key);

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
     * @return null|$class_name[]  Array of related model instances, or null if nothing found
     */
    public function belongsToMany(
        string $class_name,
        string $pivotTable,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        ?callable $pivotFilter = null
    );
}
