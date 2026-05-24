<?php

declare(strict_types=1);

namespace GautamMKGarg\PsrForWordPress\Cache;

/**
 * PSR-16 Simple Cache adapter backed by the WordPress Object Cache API.
 *
 * The Object Cache is an in-memory key-value store. By default it is NOT
 * persistent across requests unless a persistent cache plugin (Redis,
 * Memcached) has replaced the default WP_Object_Cache implementation.
 *
 * Uses the lazy companion key pattern to store boolean false reliably,
 * avoiding the unreliable \$found reference parameter which some cache
 * backends do not consistently set.
 */
final class WpObjectCache extends BaseCache
{
    /** @var string The cache group used for all operations. */
    private readonly string $group;

    /**
     * Object Cache backed by wp_cache_* has no meaningful key length limit
     * when running against the default PHP-array implementation or Redis.
     */
    protected function maxKeyLength(): ?int
    {
        return null;
    }

    /**
     * Tracks keys written by this instance for best-effort clear() support.
     * @var array<string, true>
     */
    private array $writtenKeys = [];

    /**
     * @param string $group Optional group name passed to wp_cache_* functions.
     *                      Defaults to 'psr16' to avoid collisions with other
     *                      object cache consumers.
     */
    public function __construct(string $group = 'psr16')
    {
        $this->group = $group;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        $value = wp_cache_get($key, $this->group);

        if ($value !== false) {
            return $value;
        }

        // Value is false — distinguish stored false from cache miss.
        if (wp_cache_get($this->companionKey($key), $this->group) !== false) {
            return false;
        }

        return $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->validateKey($key);
        $this->writtenKeys[$key] = true;

        // PSR-16: negative or zero TTL means "already expired" → delete.
        if ($this->isExpired($ttl)) {
            return $this->delete($key);
        }

        $seconds = $this->ttlToSeconds($ttl);

        if ($this->isFalse($value)) {
            $companionSet = wp_cache_set($this->companionKey($key), true, $this->group, $seconds);
            $mainSet      = wp_cache_set($key, false, $this->group, $seconds);
            return $mainSet && $companionSet;
        }

        // Non-false value: remove any lingering companion key from a prior write.
        wp_cache_delete($this->companionKey($key), $this->group);

        return wp_cache_set($key, $value, $this->group, $seconds);
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        unset($this->writtenKeys[$key]);

        wp_cache_delete($key, $this->group);
        wp_cache_delete($this->companionKey($key), $this->group);

        // PSR-16: deleting a non-existent key is NOT an error.
        return true;
    }

    public function clear(): bool
    {
        if ($this->writtenKeys === []) {
            return true;
        }

        $success = true;
        foreach (array_keys($this->writtenKeys) as $key) {
            if (!$this->delete((string) $key)) {
                $success = false;
            }
        }

        $this->writtenKeys = [];
        return $success;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keys   = $this->validateKeys($keys);
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        if (!is_array($values) && !$values instanceof \Traversable) {
            throw new Exception\InvalidArgumentException('Values must be an array or a Traversable.');
        }

        $success = true;
        foreach ($values as $key => $value) {
            /** @var int|string $key */
            $key = is_string($key) ? $key : (string) $key;
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $keys    = $this->validateKeys($keys);
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);

        $value = wp_cache_get($key, $this->group);
        if ($value !== false) {
            return true;
        }

        return wp_cache_get($this->companionKey($key), $this->group) !== false;
    }
}
