<?php

declare(strict_types=1);

namespace Core\Plug\Storage\Contract;

use Core\Plug\Response;

/**
 * File deletion operations.
 */
interface Deletable
{
    /**
     * Delete a single file.
     */
    public function path(string $remotePath): Response;

    /**
     * Delete multiple files.
     *
     * @param  array<string>  $remotePaths
     */
    public function paths(array $remotePaths): Response;
}
