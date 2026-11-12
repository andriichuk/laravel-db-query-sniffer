# Contributing

Use PHP 8.3 or later and install development dependencies with `composer install`.

Before submitting a pull request, run:

```bash
composer format
composer check
```

Add behavioral tests for fixes and new features. Use real database queries for integration coverage and test failure paths as well as successful capture. Keep source methods typed, preserve strict types, and document configuration and public behavior changes in the README and changelog.

Changes must pass the compatibility matrix, maximum-level PHPStan without suppressions, Pint, and the coverage threshold. Avoid adding runtime dependencies or new public configuration without a concrete use case.

Do not include application credentials, query dumps, customer data, or generated files in a pull request.
