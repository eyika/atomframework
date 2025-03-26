<?php
namespace Eyika\Atom\Framework\Support;

use Eyika\Atom\Framework\Support\Contracts\Arrayable;
use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;

abstract class Data
{
    public function __construct(...$params)
    {
        if (empty($params)) {
            return;
        }
        
        $this->fill($params); // Pass arguments as array
    }

    public static function fromArray(array $payload): static
    {
        $obj = new static();

        $obj->fill($payload);
        return $obj;
    }

    public static function from(array|Arrayable $payload): static
    {
        return static::fromArray($payload);
    }

    protected function fill(array $data): void
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $key = $property->getName();

            $type = $property->getType();
            $value = array_key_exists($key, $data) ? $data[$key] : $this->getDefaultValueForType($type instanceof ReflectionNamedType ? $type->getName() : '');

            // Check if the property has a type and handle casting
            if ($type) {
                if (!$type instanceof ReflectionNamedType) {
                    if ($type->allowsNull()) {
                        $value = null;
                    } else {
                        // foreach ($type->getTypes() as $namedType) {
                        //     if ($namedType instanceof \ReflectionNamedType) {
                        //         $name = $namedType->getName();

                        //         if (!$namedType->isBuiltin() && is_array($value)) {

                        //         }
                        //         $dtoClass = $namedType->getName();
                        //         if ($dtoClass === gettype($value))
                        //             break; // Use the first valid non-built-in type
                        //     } elseif ($namedType instanceof ReflectionIntersectionType) {
                        //         Arr::first($namedType->getTypes(), function (ReflectionType $_type) {
                        //             return $_type->
                        //         })
                        //     }
                        // }

                        // // Handle DTO class instantiation
                        // if (isset($dtoClass)) {
                        //     if (is_array($value)) {
                        //         if (class_exists($dtoClass) && is_subclass_of($dtoClass, self::class)) {
                        //             $value = $dtoClass::fromArray($value);
                        //         } elseif ($this->isListOfDataObjects($value, $dtoClass)) {
                        //             $value = array_map(fn ($item) => $dtoClass::fromArray($item), $value);
                        //         }
                        //     } elseif (is_null($value)) {
                        //         if (class_exists($dtoClass) && is_subclass_of($dtoClass, self::class)) {
                        //             $value = new $dtoClass;
                        //         }
                        //     }
                        // }
                    }
                } elseif (!$type->isBuiltin()) {
                    $dtoClass = $type->getName();

                    if (is_array($value)) {
                        if (class_exists($dtoClass) && is_subclass_of($dtoClass, self::class)) {
                            $value = $dtoClass::fromArray($value);
                        } elseif ($this->isListOfDataObjects($value, $dtoClass)) {
                            $value = array_map(fn ($item) => $dtoClass::fromArray($item), $value);
                        }
                    } else if (is_null($value)) {
                        if (class_exists($dtoClass) && is_subclass_of($dtoClass, self::class)) {
                            $value = new $dtoClass;
                        }
                    }
                }
            }

            $this->$key = $value;
        }
    }

    /**
     * Check if an array is a list of valid Data objects
     */
    protected function isListOfDataObjects(array $data, string $dtoClass): bool
    {
        return is_array($data) && isset($data[0]) && is_array($data[0]) && class_exists($dtoClass) && is_subclass_of($dtoClass, self::class);
    }

    /**
     * Convert data object to array.
     */
    public function toArray(bool $filter = false): array
    {
        $data = [];

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $key = $property->getName();
            $value = $this->$key;

            if ($value instanceof self) {
                $value = $value->toArray($filter);
            } elseif (is_array($value)) {
                $value = array_map(fn($item) => $item instanceof self ? $item->toArray($filter) : $item, $value);
            }

            if (!$filter || !empty($value)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Convert to JSON.
     */
    public function toJson(int $options = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $options);
    }

    private function getDefaultValueForType(string $type): mixed
    {
        return match ($type) {
            'string' => '',
            'int' => 0,
            'float' => 0.0,
            'bool' => false,
            'array' => [],
            default => null, // For unknown types or objects, set to null
        };
    }
}
