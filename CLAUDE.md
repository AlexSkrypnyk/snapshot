# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with
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
- Autoloading: PSR-4 via Composer

### Component Structure

```
src/
├── Snapshot.php              # Main facade class with static methods
├── SnapshotTrait.php         # PHPUnit trait for snapshot testing
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
│   ├── IndexedFileInterface.php
│   ├── Rules.php             # Skip/include/ignore rules for indexing
│   └── RulesInterface.php
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


## Code Quality Standards

### Three-Layer Quality Stack

1. **PHP_CodeSniffer** - Drupal coding standards + strict types
   - Config: `phpcs.xml`
   - Rules: Drupal standard, Generic.PHP.RequireStrictTypes
   - Relaxed rules in test files

2. **PHPStan** - Level 9 static analysis
   - Config: `phpstan.neon`

3. **Rector** - PHP 8.2/8.3 modernization
   - Config: `rector.php`
   - Sets: PHP_82, PHP_83, CODE_QUALITY, CODING_STYLE, DEAD_CODE, TYPE_DECLARATION

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
│   ├── ComparerTest.php
│   ├── DiffTest.php
│   ├── IndexedFileTest.php
│   ├── IndexTest.php
│   ├── PatcherTest.php
│   ├── RulesTest.php
│   ├── SnapshotAssertionsTraitTest.php
│   ├── SnapshotTest.php
│   └── SyncerTest.php
├── Functional/              # Integration tests - subprocess testing
│   ├── FunctionalTestCase.php
│   └── SnapshotTraitUpdateTest.php
├── Fixtures/                # Test fixture directories
│   ├── compare/            # Comparison test fixtures
│   └── diff/               # Diff/patch test fixtures
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
use AlexSkrypnyk\Snapshot\SnapshotTrait;

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


## CI/CD

GitHub Actions workflows test across:
- PHP versions: 8.2, 8.3
- Separate jobs: lint, test, coverage upload (Codecov)

Key workflow: `.github/workflows/test-php.yml`
