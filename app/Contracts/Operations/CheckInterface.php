<?php

namespace App\Contracts\Operations;

interface CheckInterface
{
    /**
     * Get the human-readable name of the diagnostic check.
     */
    public function getName(): string;

    /**
     * Get the category of the check (e.g. Core, Infrastructure, Services).
     */
    public function getCategory(): string;

    /**
     * Run the check and return a result array.
     * Expected array format:
     * [
     *     'status' => 'success'|'warning'|'error',
     *     'message' => 'Status description',
     *     'meta' => [] // Optional debug metadata (e.g. version numbers, size in MB)
     * ]
     */
    public function run(): array;
}
