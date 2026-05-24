<?php

declare(strict_types=1);

namespace GautamMKGarg\PsrForWordPress\Cache;

/**
 * PSR-16 Simple Cache adapter backed by the WordPress Transients API.
 *
 * Transients are always persistent: stored in the database by default,
 * and automatically delegated to the Object Cache when a persistent cache
 * plugin (Redis, Memcached) is active.
 *
 * Uses the lazy companion key pattern to store boolean false without
 * breaking native WordPress interop for all other data types.
 */
final class WpTransientsCache extends BaseCache
{
    /**
     * WordPress transient keys are stored in the `wp_options` table.
     * The option_name column is VARCHAR(191). WordPress prepends `_transient_`
     * (10 chars). With our companion suffix (`__found_psr16__`, 15 chars), the
     * effective limit for the caller's key is 172 characters.
     */
    private const MAX_KEY_LENGTH = 172;

    /**
     * Tracks keys written by this instance for best-effort clear() support.
     * @var array<string, true>
     */
    private array $writtenKeys = [];

    protected function maxKeyLength(): ?int
    {
        return self::MAX_KEY_LENGTH;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        $value = get_transient($key);

        if ($value !== false) {
            return $value;
        }

        // Value is false — distinguish stored false from cache miss.
        if (get_transient($this->companionKey($key)) !== false) {
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
            $companionSet = set_transient($this->companionKey($key), true, $seconds);
            $mainSet      = set_transient($key, false, $seconds);
            return $mainSet && $companionSet;
        }

        // Non-false value: remove any lingering companion key from a prior write.
        delete_transient($this->companionKey($key));

        return set_transient($key, $value, $seconds);
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        unset($this->writtenKeys[$key]);

        delete_transient($key);
        delete_transient($this->companionKey($key));

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

        $value = get_transient($key);
        if ($value !== false) {
            return true;
        }

        return get_transient($this->companionKey($key)) !== false;
    }
}
