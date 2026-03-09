<?php

declare(strict_types=1);

namespace Core\Plug\Storage;

use Core\Plug\Storage\Contract\Browseable;
use Core\Plug\Storage\Contract\Deletable;
use Core\Plug\Storage\Contract\Downloadable;
use Core\Plug\Storage\Contract\Uploadable;
use InvalidArgumentException;

/**
 * Storage Manager - Factory for storage provider operations.
 *
 * Resolves storage providers from config and provides type-safe access to operations.
 * Supports zone switching for providers with multiple storage zones (public/private).
 *
 * @example
 * $storage = app(StorageManager::class);
 *
 * // Get operations
 * $uploader = $storage->upload();    // Returns Uploadable for default driver
 * $downloader = $storage->download(); // Returns Downloadable
 *
 * // Specific driver
 * $uploader = $storage->driver('bunny')->upload();
 *
 * // Zone switching (for Bunny)
 * $uploader = $storage->zone('private')->upload();
 *
 * // vBucket for workspace isolation
 * $vbucket = $storage->vbucket('example.com');
 */
class StorageManager
{
    protected string $defaultDriver;

    protected string $zone = 'public';

    /**
     * @var array<string, array{upload: class-string<Uploadable>, download: class-string<Downloadable>, delete: class-string<Deletable>, browse: class-string<Browseable>, vbucket?: class-string}>
     */
    protected array $drivers = [
        'bunny' => [
            'upload' => Bunny\Upload::class,
            'download' => Bunny\Download::class,
            'delete' => Bunny\Delete::class,
            'browse' => Bunny\Browse::class,
            'vbucket' => Bunny\VBucket::class,
        ],
    ];

    public function __construct()
    {
        $this->defaultDriver = config('cdn.storage_driver', 'bunny');
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Set the default driver.
     */
    public function setDefaultDriver(string $driver): self
    {
        $this->defaultDriver = $driver;

        return $this;
    }

    /**
     * Get a driver instance.
     */
    public function driver(?string $driver = null): self
    {
        if ($driver !== null) {
            $this->defaultDriver = $driver;
        }

        return $this;
    }

    /**
     * Set the storage zone (public/private).
     */
    public function zone(string $zone): self
    {
        $this->zone = $zone;

        return $this;
    }

    /**
     * Get the upload operation for the current driver.
     */
    public function upload(): Uploadable
    {
        return $this->resolve('upload');
    }

    /**
     * Get the download operation for the current driver.
     */
    public function download(): Downloadable
    {
        return $this->resolve('download');
    }

    /**
     * Get the delete operation for the current driver.
     */
    public function delete(): Deletable
    {
        return $this->resolve('delete');
    }

    /**
     * Get the browse operation for the current driver.
     */
    public function browse(): Browseable
    {
        return $this->resolve('browse');
    }

    /**
     * Get a vBucket for workspace-isolated storage.
     */
    public function vbucket(string $domain): Bunny\VBucket
    {
        if (! isset($this->drivers[$this->defaultDriver]['vbucket'])) {
            throw new InvalidArgumentException(
                "vBucket not available for storage driver [{$this->defaultDriver}]."
            );
        }

        $class = $this->drivers[$this->defaultDriver]['vbucket'];

        return new $class($domain, zone: $this->zone);
    }

    /**
     * Check if a driver is registered.
     */
    public function hasDriver(string $driver): bool
    {
        return isset($this->drivers[$driver]);
    }

    /**
     * Get all registered driver names.
     *
     * @return array<string>
     */
    public function drivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Register a custom driver.
     *
     * @param  array{upload?: class-string<Uploadable>, download?: class-string<Downloadable>, delete?: class-string<Deletable>, browse?: class-string<Browseable>, vbucket?: class-string}  $operations
     */
    public function extend(string $driver, array $operations): self
    {
        $this->drivers[$driver] = array_merge($this->drivers[$driver] ?? [], $operations);

        return $this;
    }

    /**
     * Resolve an operation class for the current driver.
     */
    protected function resolve(string $operation): object
    {
        if (! isset($this->drivers[$this->defaultDriver])) {
            throw new InvalidArgumentException("Storage driver [{$this->defaultDriver}] not registered.");
        }

        if (! isset($this->drivers[$this->defaultDriver][$operation])) {
            throw new InvalidArgumentException(
                "Operation [{$operation}] not available for storage driver [{$this->defaultDriver}]."
            );
        }

        $class = $this->drivers[$this->defaultDriver][$operation];

        return new $class(zone: $this->zone);
    }
}
