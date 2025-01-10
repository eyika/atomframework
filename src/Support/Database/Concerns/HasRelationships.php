<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

use Eyika\Atom\Framework\Support\Str;
use Exception;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Database\Contracts\ModelRelationshipInterface;

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

            $parent_model = $parent_model->where($local_key, $this->child->{$foreign_key})->first(false);

            if (!$parent_model) {
                return null;
            }
            return $parent_model;
        } catch (Exception $e) {
            logger()->error("got the following error: ".$e->getMessage(), $e->getTrace());
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
            $models = [];

            foreach ($foreign_models as $model) {
                $models[] = $class_name::getBuilder()->fill($model);
            }
            return $models;
        } catch (Exception $e) {
            logger()->error("got the following error: ".$e->getMessage(), $e->getTrace());
        }
    }

    public function belongsToMany(string $class_name, $foreign_key = null, $local_key = null)
    {
        //TODO: is_protected feature should be dynamic for relationships
        try {
            $parent_model = new $class_name;
            $class_name = basename(str_replace('\\', '/', $class_name));

            $foreign_key = $foreign_key ?? Str::snake($class_name) . '_id';
            $local_key = $local_key ?? 'id';

            $parent_model = $parent_model->where($local_key, $this->{$foreign_key})->all(true);

            if (!$parent_model) {
                return null;
            }
            return $parent_model;
        } catch (Exception $e) {
            logger()->error("got the following error: ".$e->getMessage(), $e->getTrace());
        }
    }

    // public function attach(ModelRelationshipInterface $object)
    // {
    //     $object->table;
    // }
}
