<?php

declare(strict_types=1);

namespace Core\Plug\Storage\Contract;

use Core\Plug\Response;

/**
 * File browsing and inspection operations.
 */
interface Browseable
{
    /**
     * List files in a path.
     */
    public function list(string $path = '/'): Response;

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Get file size in bytes.
     */
    public function size(string $path): ?int;
}
