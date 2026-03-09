<?php

declare(strict_types=1);

namespace Core\Plug\Storage\Contract;

use Core\Plug\Response;

/**
 * File download operations.
 */
interface Downloadable
{
    /**
     * Get file contents as string.
     */
    public function contents(string $remotePath): Response;

    /**
     * Download file to local path.
     */
    public function toFile(string $remotePath, string $localPath): Response;
}
