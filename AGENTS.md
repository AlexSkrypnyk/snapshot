# AGENTS.md

This file provides guidance to AI agents when working with
code in this repository.


## Project Overview

**Snapshot** is a PHP library for directory snapshot testing. It provides
functionality for creating, comparing, and applying directory snapshots using
a baseline + diff architecture. This is particularly useful for testing code
generators, scaffolding tools, or any system that produces file output.

### Core Concepts

- **Baseline**: A reference directory representing the expected state
- **Snapshot/Scenario**: A set of diff files representing changes from baseline
- **Diff**: Unified diff format patches for file content changes
- **Index**: A scanned representation of a directory's files and content


## Architecture

### Namespace Structure

- Source code: `AlexSkrypnyk\Snapshot\`
- Tests: `AlexSkrypnyk\Snapshot\Tests\`
- Benchmarks: `AlexSkrypnyk\Snapshot\Benchmarks\`
- Autoloading: PSR-4 via Composer

### Component Structure

```
src/
├── Snapshot.php              # Main facade class with static methods
├── SnapshotBuilder.php       # Fluent builder for snapshot operations
├── Testing/
│   └── SnapshotTrait.php     # PHPUnit trait for snapshot testing
├── Compare/
│   ├── Comparer.php          # Compares two directory indexes
│   ├── ComparerInterface.php
│   ├── Diff.php              # Represents content differences
│   ├── DiffInterface.php
│   └── RenderableInterface.php
├── Index/
│   ├── Index.php             # Scans and indexes directory contents
│   ├── IndexInterface.php
│   ├── IndexedFile.php       # Represents a file in an index
│   └── IndexedFileInterface.php
├── Rules/
│   ├── Rules.php             # Skip/include/ignore rules for indexing
│   ├── RulesInterface.php
│   ├── AbstractRuleSet.php   # Shared rule set behaviour
│   ├── RuleSetInterface.php
│   ├── PhpProjectRuleSet.php  # Preset rules for PHP projects
│   └── NodeProjectRuleSet.php # Preset rules for NodeJS projects
├── Patch/
│   ├── Patcher.php           # Applies unified diff patches
│   └── PatcherInterface.php
├── Sync/
│   ├── Syncer.php            # Copies files from index to destination
│   └── SyncerInterface.php
└── Exception/
    ├── PatchException.php
    ├── RulesException.php
    └── SnapshotException.php
```

### Key Classes

| Class | Purpose |
|-------|---------|
| `Snapshot` | Static facade for all operations (compare, diff, patch, sync) |
| `SnapshotBuilder` | Fluent builder for composing snapshot operations |
| `SnapshotTrait` | PHPUnit trait with `assertDirectoriesIdentical()` and `assertSnapshotMatchesBaseline()` |
| `Index` | Scans directories, respects `.ignorecontent` rules |
| `IndexedFile` | File representation with content, hash, path info |
| `Rules` | Configures skip/include/ignore patterns for comparison |
| `Comparer` | Finds differences between two indexes |
| `Diff` | Generates unified diff output |
| `Patcher` | Applies patch files to recreate expected state |
| `Syncer` | Copies indexed files to destination |


## Commands

### Code Quality

```bash
# Run all linters (PHPCS, PHPStan, Rector)
composer lint

# Auto-fix code style issues
composer lint-fix

# Individual tools
./vendor/bin/phpcs      # Check coding standards
./vendor/bin/phpcbf     # Fix coding standards
./vendor/bin/phpstan    # Static analysis (level 9)
./vendor/bin/rector --dry-run  # Check Rector suggestions
```

### Testing

```bash
# Run all PHPUnit tests
composer test

# Run with coverage reports
composer test-coverage
# Coverage reports: .logs/.coverage-html/index.html, .logs/cobertura.xml

# Run unit tests only
./vendor/bin/phpunit tests/phpunit/Unit

# Run functional tests only
./vendor/bin/phpunit tests/phpunit/Functional

# Run specific test file
./vendor/bin/phpunit tests/phpunit/Unit/SnapshotTest.php

# Run specific test method
./vendor/bin/phpunit --filter testMethodName
```

### Building

```bash
# Clean and reinstall dependencies
composer reset   # removes vendor/, composer.lock
composer install
```


## Code Quality Standards

### Three-Layer Quality Stack

1. **PHP_CodeSniffer** - Drupal coding standards + strict types
   - Config: `phpcs.xml`
   - Rules: Drupal standard, Generic.PHP.RequireStrictTypes
   - Relaxed rules in test files

2. **PHPStan** - Level 9 static analysis
   - Config: `phpstan.neon`

3. **Rector** - PHP 8.3 modernization
   - Config: `rector.php`
   - Sets: PHP_83, CODE_QUALITY, CODING_STYLE, DEAD_CODE, TYPE_DECLARATION

### Coding Conventions

- All PHP files must declare `strict_types=1`
- Use single quotes for strings (double quotes if containing single quote)
- All files must end with a newline character
- Local variables/method arguments: `snake_case`
- Method names/class properties: `camelCase`


## Testing Patterns

### Test Structure

```
tests/phpunit/
├── Unit/                    # Unit tests - isolated, fast
│   ├── DiffTest.php
│   ├── IndexedFileTest.php
│   ├── IndexTest.php
│   ├── PatcherTest.php
│   ├── PatchExceptionTest.php
│   ├── RulesTest.php
│   ├── SnapshotBuilderTest.php
│   ├── SnapshotTest.php
│   └── SnapshotTraitTest.php
├── Functional/              # Integration tests - subprocess testing
│   ├── FunctionalTestCase.php
│   ├── SnapshotTraitUpdateTest.php
│   └── SnapshotUpdateScriptTest.php
├── Fixtures/                # Test fixture directories
│   ├── compare/            # Comparison test fixtures
│   ├── copy/               # Sync/copy test fixtures
│   ├── diff/               # Diff/patch test fixtures
│   └── functional_update/  # Bulk update script fixtures
└── UnitTestCase.php        # Base test case
```

### Writing Tests

- Use PHPUnit 11 attributes: `#[CoversClass()]`, `#[DataProvider()]`
- Data provider method names start with `dataProvider`
- Use `UnitTestCase` as base class (includes `SnapshotTrait` and `LocationsTrait`)
- Functional tests use `FunctionalTestCase` which adds `ProcessTrait`

### Fixture Directory Structure

For comparison tests (`tests/phpunit/Fixtures/compare/`):
```
scenario_name/
├── directory1/          # Left side (baseline/expected)
│   ├── .ignorecontent   # Optional ignore rules
│   └── ...files...
└── directory2/          # Right side (actual)
    └── ...files...
```

For diff/patch tests (`tests/phpunit/Fixtures/diff/`):
```
scenario_name/
├── baseline/            # Original state
├── diff/                # Patch files to apply
└── result/              # Expected result after patching
```

### The `.ignorecontent` File

Controls which files are compared. Supports patterns:
- `*.log` - Skip files matching glob pattern
- `dir/` - Skip entire directory
- `!important.txt` - Include file (override skip)
- `^content.txt` - Ignore content differences (compare existence only)


## SnapshotTrait Usage

The trait provides two main assertions for PHPUnit tests:

```php
use AlexSkrypnyk\Snapshot\Testing\SnapshotTrait;

class MyTest extends TestCase {
    use SnapshotTrait;

    // Compare two directories directly
    public function testOutput(): void {
        $this->assertDirectoriesIdentical($expected, $actual);
    }

    // Compare actual against baseline + diffs
    public function testScenario(): void {
        $this->assertSnapshotMatchesBaseline($actual, $baseline, $diffs);
    }

    // Enable auto-update on failure (call in tearDown)
    protected function tearDown(): void {
        $this->snapshotUpdateOnFailure($snapshots, $actual);
        parent::tearDown();
    }
}
```

### Auto-Update Feature

Set `UPDATE_SNAPSHOTS=1` environment variable to automatically update snapshots
when tests fail due to directory comparison mismatches:

```bash
UPDATE_SNAPSHOTS=1 ./vendor/bin/phpunit
```

### Bulk Snapshot Updates (`bin/update-snapshots`)

CLI that runs PHPUnit per dataset with `UPDATE_SNAPSHOTS=1` (in parallel, with
timeouts and retries) to regenerate many snapshots at once:

```bash
vendor/bin/update-snapshots testMySnapshot tests/snapshots
```

**Exit-code contract**: successfully updating snapshots is the expected outcome
and exits `0`. The script exits non-zero **only** when a dataset genuinely
cannot be updated - a non-snapshot failure or a timeout.

A per-dataset PHPUnit run still exits non-zero when it updates a snapshot (the
assertion fails before `tearDown()` rewrites it). The script reclassifies such
runs as "updated" by detecting `SnapshotTrait`'s `[SNAPSHOT] Baseline updated` /
`[SNAPSHOT] Diffs updated` completion markers in the captured output - so those
marker strings are a contract shared with `src/Testing/SnapshotTrait.php`; keep
them in sync.

Functional tests run the script as a subprocess against fixtures in
`tests/phpunit/Fixtures/functional_update/`. Coverage measures `src/` only, so
`bin/` is not coverage-gated.


## Performance Benchmarks

PHPBench benchmarks measure performance of core Snapshot operations.

### Commands

```bash
# Run benchmarks with baseline comparison (used by CI)
composer benchmark

# Create or update baseline for performance comparison
composer benchmark-baseline

# Run specific benchmark class
./vendor/bin/phpbench run benchmarks/SnapshotBench.php --ref=baseline

# Quick testing: verify benchmark works without full suite
./vendor/bin/phpbench run benchmarks/SnapshotBench.php --iterations=1 --revs=1
```

### Benchmark Coverage

The `SnapshotBench` class measures:
- **benchCompareIdentical**: Comparing identical directories (baseline)
- **benchCompareContentDiffs**: Comparing with 20% modified content
- **benchCompareStructuralDiffs**: Comparing with missing/extra files
- **benchDiff**: Creating diff files from differences
- **benchPatch**: Applying patches to baseline
- **benchSync**: Syncing directories
- **benchCompareLargeDirectory**: Large directory (500 files) comparison

### Baseline Management

- Baseline benchmarks stored in `.phpbench/storage/` directory
- CI compares new benchmarks against baseline with ±5% threshold
- Performance regressions exceeding ±5% will fail CI checks
- Update baseline manually: `composer benchmark-baseline`


## CI/CD

GitHub Actions workflows test across:
- PHP versions: 8.3, 8.4, 8.5
- Separate jobs: lint, test, coverage upload (Codecov)

Lint, coverage threshold and Codecov upload run on the PHP 8.4 `normal`
dependency leg only.

Key workflows:
- `.github/workflows/test-php.yml` - PHP testing
- `.github/workflows/benchmark-php.yml` - Performance benchmarks


## Updating from the template

This project was generated from a template and can pull the template's latest
infrastructure (CI workflows, linting and test configuration, and docs)
at any time. When you are asked to "update scaffold", fetch the updater skill
from the template repository into the local `.claude/skills/` directory, then
invoke it:

1. Create the target directory if it does not exist:

   ```bash
   mkdir -p .claude/skills/update-consumer-scaffold
   ```

2. Download the skill:

   ```bash
   curl -sSL https://raw.githubusercontent.com/AlexSkrypnyk/scaffold/main/.scaffold/skills/update-consumer-scaffold/SKILL.md -o .claude/skills/update-consumer-scaffold/SKILL.md
   ```

3. Invoke the `update-consumer-scaffold` skill and follow its steps.

The skill directory is fetched on demand and is git-ignored - it is not
committed to the project.
