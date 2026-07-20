<?php

namespace App\Services\Imports;

class ImportLookupCache
{
    private array $cache = [];

    /**
     * Get a cached lookup value, or run the callback to fetch and store it.
     */
    public function get(string $category, string $key, callable $fallback)
    {
        $normalizedKey = strtolower(trim($key));
        if (!isset($this->cache[$category][$normalizedKey])) {
            $this->cache[$category][$normalizedKey] = $fallback();
        }
        return $this->cache[$category][$normalizedKey];
    }

    /**
     * Prime the cache for a category in one query.
     */
    public function prime(string $category, array $items)
    {
        $this->cache[$category] = [];
        foreach ($items as $key => $value) {
            $normalizedKey = strtolower(trim($key));
            $this->cache[$category][$normalizedKey] = $value;
        }
    }

    /**
     * Clear all cached lookups.
     */
    public function clear(): void
    {
        $this->cache = [];
    }
}
