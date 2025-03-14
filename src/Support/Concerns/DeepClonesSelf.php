<?php

namespace Eyika\Atom\Framework\Support\Concerns;

trait DeepClonesSelf
{
    public function __clone()
    {
        foreach ($this as $key => $value) {
            if (is_object($value)) {
                $this->$key = clone $value;
            }
        }
    }
    // private static array $clonedObjects = []; // Track already cloned objects

    // public function __clone()
    // {
    //     self::$clonedObjects = []; // Reset tracking for each new clone operation
    //     $this->deepClone($this);
    // }

    // private function deepClone(&$object)
    // {
    //     $objectId = spl_object_id($object);

    //     // Prevent infinite recursion: If object is already cloned, return the cloned instance
    //     if (isset(self::$clonedObjects[$objectId])) {
    //         return self::$clonedObjects[$objectId];
    //     }

    //     // Clone the object and store it in the map
    //     $clonedObject = clone $object;
    //     self::$clonedObjects[$objectId] = $clonedObject;

    //     foreach ($clonedObject as $key => $value) {
    //         if (is_object($value)) {
    //             // Recursively clone only if the object hasn't been cloned already
    //             $clonedObject->$key = $this->deepClone($value);
    //         }
    //     }

    //     return $clonedObject;
    // }
}
