<?php

declare(strict_types=1);

namespace Core\Plug\Storage\Contract;

use Core\Plug\Response;

/**
 * File upload operations.
 */
interface Uploadable
{
    /**
     * Upload a file from local path to remote path.
     */
    public function file(string $localPath, string $remotePath): Response;

    /**
     * Upload string contents directly to remote path.
     */
    public function contents(string $remotePath, string $contents): Response;
}
