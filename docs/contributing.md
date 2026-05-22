# Contributing

Thank you for your interest in contributing to NexusAI!

## Development Setup

```bash
git clone https://github.com/token27/nexus-ai.git
cd nexus-ai
composer install
```

## Code Standards

All code must pass:

```bash
# Static analysis — Level 8, 0 errors
vendor/bin/phpstan analyse src/ --memory-limit=512M

# Code style — PSR-12
vendor/bin/php-cs-fixer fix src/

# Tests — all must pass
vendor/bin/phpunit tests/Unit/
```

## Adding a New Driver

1. Create `src/Driver/MyProvider/` with:
   - `MyProviderDriver.php` — implements `DriverInterface`
   - `MyProviderPayloadBuilder.php` — builds the HTTP request payload
   - `MyProviderResponseParser.php` — parses text/structured responses
   - `MyProviderStreamParser.php` — parses SSE stream
2. Add to the `Provider` enum in `src/Enum/Provider.php`
3. Register in `NexusAI::registerDefaultDrivers()`
4. Add tests in `tests/Unit/Driver/`

## Adding a New Middleware

1. Create `src/Pipeline/Middleware/MyMiddleware.php` — implements `MiddlewareInterface`
2. Add tests in `tests/Unit/Middleware/MyMiddlewareTest.php`
3. Document in `docs/middleware.md`

## Pull Request Guidelines

- One feature or fix per PR
- Include tests for new code
- Update relevant documentation in `docs/`
- Add entry to `CHANGELOG.md` under `[Unreleased]`
- Follow PSR-12 code style

## Reporting Bugs

Please include:
- PHP version
- NexusAI version
- Provider and model used
- Minimal reproduction case
- Full exception trace

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

> **← Back:** [Troubleshooting](troubleshooting.md)
