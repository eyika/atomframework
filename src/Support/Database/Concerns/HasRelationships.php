<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

use Eyika\Atom\Framework\Support\Str;
use Exception;
use Eyika\Atom\Framework\Support\Database\Contracts\ModelRelationshipInterface;

trait HasRelationships
{
    public function hasOne(string $class_name, $foreign_key = null, $local_key = null, callable|string|null $with = null)
    {
        try {
            $foreign_model = new $class_name;
            $classname = get_called_class();
            $classname = basename(str_replace('\\', '/', $classname));

            $foreign_key = $foreign_key ?? Str::lower($classname) . '_id';
            $local_key = $local_key ?? 'id';

            $foreign_model = $foreign_model->where($foreign_key, $this->{$local_key})->first();

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
        try {
            $parent_model = new $class_name;
            $class_name = basename(str_replace('\\', '/', $class_name));

            $foreign_key = $foreign_key ?? Str::lower($class_name) . '_id';
            $local_key = $local_key ?? 'id';

            $parent_model = $parent_model->where($local_key, $this->{$foreign_key})->first(false);

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
        try {
            $foreign_model = new $class_name;
            $classname = get_called_class();
            $classname = basename(str_replace('\\', '/', $classname));

            $foreign_key = $foreign_key ?? Str::lower($classname) . '_id';
            $local_key = $local_key ?? 'id';

            $foreign_models = $foreign_model->where($foreign_key, $this->{$local_key})->all(false);

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
        try {
            $parent_model = new $class_name;
            $class_name = basename(str_replace('\\', '/', $class_name));

            $foreign_key = $foreign_key ?? Str::lower($class_name) . '_id';
            $local_key = $local_key ?? 'id';

            $parent_model = $parent_model->where($local_key, $this->{$foreign_key})->all(false);

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