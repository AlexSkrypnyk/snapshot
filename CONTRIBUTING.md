# Contributing

Thank you for considering a contribution to this project. This guide covers
setting up a local environment and running the linting, tests and benchmarks.

## Local setup

```bash
composer install
```

## Linting

`composer lint` runs PHP_CodeSniffer, PHPStan and Rector in check mode.
`composer lint-fix` applies the fixes that Rector and PHP Code Beautifier can
make automatically.

```bash
composer lint
composer lint-fix
```

## Tests

```bash
# Run the whole suite.
composer test

# Run the suite with coverage reports in .logs/.
composer test-coverage

# Run a single file or a single test.
vendor/bin/phpunit tests/phpunit/Unit/SnapshotTest.php
vendor/bin/phpunit --filter testMethodName
```

## Performance benchmarks

PHPBench measures the core operations against a stored baseline. CI compares
each run to that baseline and fails when a benchmark moves by more than 5%.

```bash
# Run benchmarks against the stored baseline.
composer benchmark

# Create or update the stored baseline.
composer benchmark-baseline

# Verify a benchmark runs, without the full suite.
vendor/bin/phpbench run benchmarks/SnapshotBench.php --iterations=1 --revs=1
```
