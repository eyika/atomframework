<?php

namespace Eyika\Atom\Framework\Support;

use Carbon\Carbon as BaseCarbon;
use Carbon\CarbonImmutable as BaseCarbonImmutable;
use Eyika\Atom\Framework\Support\Concerns\Conditionable;
use Eyika\Atom\Framework\Support\Traits\Dumpable;
use Ramsey\Uuid\Uuid;
// use Symfony\Component\Uid\Ulid;

class Carbon extends BaseCarbon
{
    use Conditionable, Dumpable;

    /**
     * {@inheritdoc}
     */
    public static function setTestNow(mixed $testNow = null): void
    {
        BaseCarbon::setTestNow($testNow);
        BaseCarbonImmutable::setTestNow($testNow);
    }

    /**
     * Create a Carbon instance from a given ordered UUID or ULID.
     */
    public static function createFromId(Uuid|string $id): static
    {
        if (is_string($id)) {
            // $id = Ulid::isValid($id) ? Ulid::fromString($id) : Uuid::fromString($id);
            $id = Uuid::fromString($id);
        }

        return static::createFromInterface($id->getDateTime());
    }
}
