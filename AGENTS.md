# AGENTS.md — php-plug-storage

CorePHP package shape:

- `src/` — PSR-4 `Core\Plug\Storage\\\`
- `tests/` — Pest test suite
- `composer.json` — `    "name": "lthn/php-plug-storage",`, EUPL-1.2, PHP 8.4+

## CI

- `.github/workflows/ci.yml` — Pest + Pint + PHPStan
- `.woodpecker.yml` — mirrored for forge.lthn.sh

## Coding standards

- `declare(strict_types=1);` in every PHP file
- Type hints on every parameter + return type
- Pest test syntax
- PSR-12 via Laravel Pint
