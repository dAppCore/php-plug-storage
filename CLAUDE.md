# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is `lthn/php-plug-storage`, a Composer library providing storage provider integrations for the Plug framework. It depends on `lthn/php` (the core framework) and wraps Bunny CDN's storage API behind a driver-based abstraction.

## Commands

```bash
composer install          # Install dependencies
```

There are no tests, linting, or build steps in this package.

## Architecture

**Namespace:** `Core\Plug\Storage\` (PSR-4 autoloaded from `src/`)

### Contract Layer (`src/Contract/`)

Four interfaces define storage operations. All return `Core\Plug\Response` (from the core framework):

- `Uploadable` — file and content uploads
- `Downloadable` — content retrieval and file downloads
- `Deletable` — single and bulk file deletion
- `Browseable` — directory listing, existence checks, file size

### Bunny Implementation (`src/Bunny/`)

Each contract has a corresponding Bunny CDN implementation (`Upload`, `Download`, `Delete`, `Browse`). They share a common pattern:
- Constructor accepts optional credentials or reads from config (`cdn.bunny.{zone}.api_key`, `cdn.bunny.{zone}.storage_zone`, `cdn.bunny.{zone}.region`)
- Lazy-initialized `Bunny\Storage\Client`
- `isConfigured()` guard before operations
- `BuildsResponse` trait for consistent `ok()`/`error()` responses
- Static `public()`/`private()` factory methods for zone selection

**VBucket** (`src/Bunny/VBucket.php`) provides workspace-isolated storage. It hashes a domain via `Core\Crypt\LthnHash::vBucketId()` and prefixes all paths with the resulting ID. It combines upload, download, delete, list, and exists operations in one class rather than implementing the individual contracts.

### StorageManager (`src/StorageManager.php`)

Factory that resolves operations by driver name. Configured via `cdn.storage_driver` config key (defaults to `bunny`). Supports zone switching and custom driver registration via `extend()`.

## Key Patterns

- All storage operations return `Core\Plug\Response`, never throw — errors are wrapped via `BuildsResponse::error()`.
- Zone (`public`/`private`) is threaded through constructors, not set after instantiation.
- Adding a new storage provider means: implement the four contracts, then register via `StorageManager::extend()`.
