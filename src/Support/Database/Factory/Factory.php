<?php

namespace Atom\Framework\Database\Factories;

use Eyika\Atom\Framework\Support\Database\DB;

abstract class Factory
{
    protected int $count = 1;
    protected array $states = [];
    protected array $afterMakingCallbacks = [];
    protected array $afterCreatingCallbacks = [];
    protected array $sequence = [];
    protected int $sequenceIndex = 0;
    protected array $relations = [];

    /**
     * Define the model's default attributes.
     */
    abstract public function definition(): array;

    /**
     * Set the number of records to generate.
     */
    public function times(int $count): static
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Apply state modifications.
     */
    public function state(array|callable $state): static
    {
        $this->states[] = $state;
        return $this;
    }

    /**
     * Define an after-make callback.
     */
    public function afterMaking(callable $callback): static
    {
        $this->afterMakingCallbacks[] = $callback;
        return $this;
    }

    /**
     * Define an after-create callback.
     */
    public function afterCreating(callable $callback): static
    {
        $this->afterCreatingCallbacks[] = $callback;
        return $this;
    }

    /**
     * Define a sequence of values.
     */
    public function sequence(array $sequence): static
    {
        $this->sequence = $sequence;
        return $this;
    }

    /**
     * Associate a relationship.
     */
    public function for(Factory $factory, ?string $foreignKey = null): static
    {
        $this->relations[] = ['factory' => $factory, 'foreignKey' => $foreignKey];
        return $this;
    }

    /**
     * Define a has-many relationship.
     */
    public function has(Factory $factory, int $count, ?string $foreignKey = null): static
    {
        $this->relations[] = ['factory' => $factory, 'foreignKey' => $foreignKey, 'count' => $count];
        return $this;
    }

    /**
     * Generate an array of data without saving.
     */
    public function make(): array
    {
        return array_map(fn() => $this->applyState($this->definition()), range(1, $this->count));
    }

    /**
     * Generate and insert into the database.
     */
    public function create(): array
    {
        $records = $this->make();

        foreach ($records as &$record) {
            DB::table($this->getTable())->insert($record);

            // Apply after-creating hooks
            foreach ($this->afterCreatingCallbacks as $callback) {
                $callback($record);
            }

            // Handle relationships
            foreach ($this->relations as $relation) {
                if (isset($relation['count'])) {
                    (clone $relation['factory'])->times($relation['count'])->state([$relation['foreignKey'] => $record['id']])->create();
                } else {
                    (clone $relation['factory'])->state([$relation['foreignKey'] => $record['id']])->create();
                }
            }
        }

        return $records;
    }

    /**
     * Apply state modifications.
     */
    protected function applyState(array $data): array
    {
        foreach ($this->states as $state) {
            $data = is_callable($state) ? $state($data) : array_merge($data, $state);
        }

        if (!empty($this->sequence)) {
            $data = array_merge($data, $this->sequence[$this->sequenceIndex]);
            $this->sequenceIndex = ($this->sequenceIndex + 1) % count($this->sequence);
        }

        foreach ($this->afterMakingCallbacks as $callback) {
            $callback($data);
        }

        return $data;
    }

    /**
     * Get the table name (should be overridden in child classes).
     */
    abstract protected function getTable(): string;
}
