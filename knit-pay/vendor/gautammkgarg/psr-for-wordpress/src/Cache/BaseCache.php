<?php

declare(strict_types=1);

namespace GautamMKGarg\PsrForWordPress\Cache;

use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use GautamMKGarg\PsrForWordPress\Cache\Exception\InvalidArgumentException;

/**
 * Base class providing shared logic for WordPress-backed PSR-16 cache adapters.
 *
 * Key features:
 * - PSR-16 key validation (A-Z, a-z, 0-9, _, . — 1-64 chars minimum)
 * - TTL conversion (null → permanent, DateInterval → seconds, int → passthrough)
 * - Lazy companion key pattern for storing boolean false (only when needed)
 *
 * @internal Not intended for direct use. See WpTransientsCache and WpObjectCache.
 */
abstract class BaseCache implements CacheInterface
{
    /**
     * Companion key suffix used only when the stored value is boolean false.
     *
     * The companion key is set to `true` whenever the main key stores `false`.
     * On retrieval, if the main key returns `false`, checking the companion key
     * tells us whether that `false` was a real stored value or a cache miss.
     */
    protected const FOUND_SUFFIX = '__found_psr16__';

    /** @var array<string, true> Tracks which keys were stored with a companion. */
    protected array $companionKeys = [];

    /**
     * Validates a single PSR-16 cache key.
     *
     * @throws InvalidArgumentException If the key is empty, too long, or contains reserved characters.
     */
    protected function validateKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Cache key must be a non-empty string.');
        }

        if (strpbrk($key, '{}()/\\@:') !== false) {
            throw new InvalidArgumentException(
                sprintf('Cache key "%s" contains reserved characters: {}()/\\@:', $key)
            );
        }

        $max = $this->maxKeyLength();
        if ($max !== null && strlen($key) > $max) {
            throw new InvalidArgumentException(
                sprintf('Cache key "%s" exceeds the maximum length of %d characters.', $key, $max)
            );
        }
    }

    /**
     * Returns the maximum key length supported by the backend, or null for unlimited.
     */
    abstract protected function maxKeyLength(): ?int;

    /**
     * Validates an iterable of cache keys.
     *
     * @param iterable<string> $keys
     * @return list<string> Normalized list of keys.
     * @throws InvalidArgumentException If the iterable is invalid or any key is invalid.
     */
    protected function validateKeys(iterable $keys): array
    {
        if (!is_array($keys) && !$keys instanceof \Traversable) {
            throw new InvalidArgumentException('Keys must be an array or a Traversable.');
        }

        $result = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('All cache keys must be strings.');
            }
            $this->validateKey($key);
            $result[] = $key;
        }

        return $result;
    }

    /**
     * Returns the companion key for a given cache key.
     */
    protected function companionKey(string $key): string
    {
        return $key . self::FOUND_SUFFIX;
    }

    /**
     * Converts a PSR-16 TTL to seconds suitable for WordPress APIs.
     *
     * @return int Seconds until expiration. 0 means "as long as possible".
     */
    protected function ttlToSeconds(null|int|\DateInterval $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        // DateInterval: compute seconds from now
        $now  = new \DateTimeImmutable();
        $then = $now->add($ttl);

        return max(0, (int) ($then->getTimestamp() - $now->getTimestamp()));
    }

    /**
     * Checks whether the given TTL resolves to zero or negative seconds.
     *
     * PSR-16: a negative or zero TTL means the item is already expired
     * and MUST be deleted from the cache.
     */
    protected function isExpired(null|int|\DateInterval $ttl): bool
    {
        if ($ttl === null) {
            return false;
        }

        $seconds = $this->ttlToSeconds($ttl);

        return $seconds <= 0;
    }

    /**
     * Checks whether a given value is the literal boolean false.
     */
    protected function isFalse(mixed $value): bool
    {
        return $value === false;
    }
}
