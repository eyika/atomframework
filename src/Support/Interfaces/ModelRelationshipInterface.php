<?php

namespace Basttyy\FxDataServer\libs\Interfaces;

interface ModelRelationshipInterface extends ModelInterface
{
    /**
     * @param ModelInterface|string $class_name
     * @param string $foreign_key
     * @param string $local_key
     * 
     * @return $class_name
     */
    public function hasOne($class_name, $foreign_key = null, $local_key = null);

    /**
     * @param string $class_name
     * @param string $foreign_key
     * @param string $local_key
     * 
     * @return $class_name
     */
    public function hasMany($class_name, $foreign_key, $local_key);

    /**
     * @param string $class_name
     * @param string $foreign_key
     * @param string $local_key
     * 
     * @return $class_name
     */
    public function belongsTo($class_name, $foreign_key, $local_key);

    /**
     * @param string $class_name
     * @param string $foreign_key
     * @param string $local_key
     * 
     * @return $class_name
     * 
     */
    public function belongsToMany($class_name, $pivot_table, $local_primary_key, $foreign_primary_key);
}