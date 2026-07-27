<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Psr\Cache\CacheItemInterface;

class CacheItem implements CacheItemInterface
{
    private string $key;
    private mixed $value;
    private bool $isHit;
    private ?int $expiration = null; // Expiration timestamp (Unix time)

    public function __construct(string $key, mixed $value = null, bool $isHit = false)
    {
        $this->key = $key;
        $this->value = $value;
        $this->isHit = $isHit;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->isHit ? $this->value : null;
    }

    public function isHit(): bool
    {
        if ($this->expiration !== null && $this->expiration < time()) {
            $this->isHit = false; // Expired items are not considered hits
        }
        return $this->isHit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->isHit = true;
        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->expiration = $expiration ? $expiration->getTimestamp() : null;
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        if ($time === null) {
            $this->expiration = null; // No expiration
        } elseif ($time instanceof \DateInterval) {
            $now = new \DateTime();
            $now->add($time);
            $this->expiration = $now->getTimestamp();
        } else {
            $this->expiration = time() + $time; // $time is in seconds
        }
        return $this;
    }

    public function getExpiration(): ?int
    {
        return $this->expiration;
    }
}
