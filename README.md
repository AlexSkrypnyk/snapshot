<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="logo.png" alt="Snapshot logo"/>
</p>

<h1 align="center">Directory snapshot, diff, and patch system useful for test fixtures</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/alexskrypnyk/snapshot.svg)](https://github.com/alexskrypnyk/snapshot/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/alexskrypnyk/snapshot.svg)](https://github.com/alexskrypnyk/snapshot/pulls)
[![Test PHP](https://github.com/alexskrypnyk/snapshot/actions/workflows/test-php.yml/badge.svg)](https://github.com/alexskrypnyk/snapshot/actions/workflows/test-php.yml)
[![codecov](https://codecov.io/gh/alexskrypnyk/snapshot/graph/badge.svg?token=7WEB1IXBYT)](https://codecov.io/gh/alexskrypnyk/snapshot)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/alexskrypnyk/snapshot)
![LICENSE](https://img.shields.io/github/license/alexskrypnyk/snapshot)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

</div>

---

## Features

- **Directory comparison** - Compare two directories for identical structure and content
- **Baseline + diff architecture** - Store a baseline once, then only diffs per test scenario
- **Unified diff format** - Human-readable patch files that can be reviewed in PRs
- **Auto-update snapshots** - Automatically update snapshots when tests fail
- **Flexible ignore rules** - Skip files, directories, or ignore content differences
- **PHPUnit integration** - Simple trait with intuitive assertions

## Use Cases

This library is designed for testing systems that generate file output:

- **Template repositories** - Test scaffolds, skeletons, and boilerplate generators
  to ensure customization options produce the expected file structure
- **Code generators** - Verify that generated code matches expected output across
  different configuration scenarios
- **Build tools** - Assert that compilation or transformation processes produce
  correct artifacts
- **Migration scripts** - Validate that file transformations work correctly

For example, if you maintain a project template with customizable options (like
choosing a database driver or enabling optional features), you can use this
library to test each combination of options produces the correct files.

## Concepts

### Baseline

A **baseline** is a reference directory containing the expected file structure
and content. It represents the "golden master" that your test output is compared
against.

```
fixtures/
└── _baseline/           # The baseline directory
    ├── composer.json
    ├── src/
    │   └── App.php
    └── README.md
```

### Snapshot (Scenario)

A **snapshot** (or scenario) represents differences from the baseline for a
specific test case. Instead of duplicating the entire expected output, you only
store the files that differ.

```
fixtures/
├── _baseline/           # Shared baseline
│   └── ...
├── scenario_mysql/      # Only files that differ for MySQL option
│   └── config/
│       └── database.php
└── scenario_postgres/   # Only files that differ for PostgreSQL option
    └── config/
        └── database.php
```

### Diff Files

Snapshot directories contain **diff files** in unified diff format. These
describe how a file should differ from its baseline version:

```diff
@@ -1,8 +1,8 @@
 <?php

 return [
-    'driver' => 'sqlite',
-    'database' => ':memory:',
+    'driver' => 'mysql',
+    'host' => 'localhost',
+    'database' => 'app',
 ];
```

Snapshot directories can also contain:
- **New files** - Full file content for files not in baseline (copied as-is)
- **Deletion markers** - Files prefixed with `-` (e.g., `-README.md`) indicate
  the file should not exist in this scenario

## Installation

    composer require --dev alexskrypnyk/snapshot

## Usage

### Basic Directory Comparison

Use `assertDirectoriesIdentical()` to compare two directories:

```php
use AlexSkrypnyk\Snapshot\Testing\SnapshotTrait;
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase {
    use SnapshotTrait;

    public function testGeneratorOutput(): void {
        // Run your code generator
        $generator->generate($output_dir);

        // Compare against expected output
        $this->assertDirectoriesIdentical($expected_dir, $output_dir);
    }
}
```

### Baseline + Diff Testing

For multiple test scenarios sharing common files, use a baseline directory with
scenario-specific diffs:

```php
public function testScenarioA(): void {
    $generator->generate($output_dir, ['option' => 'A']);

    $this->assertSnapshotMatchesBaseline(
        $output_dir,            // Actual output
        $baseline_dir,          // Common baseline
        $scenario_a_diffs_dir   // Diffs specific to scenario A
    );
}
```

This approach:
- Reduces duplication across test fixtures
- Makes differences between scenarios explicit
- Produces reviewable diff files in pull requests

### Auto-Update Snapshots

Enable automatic snapshot updates when tests fail:

```php
protected function tearDown(): void {
    // Updates snapshots when UPDATE_SNAPSHOTS=1 is set
    $this->snapshotUpdateOnFailure($snapshots_dir, $actual_dir);
    parent::tearDown();
}
```

Run tests with the environment variable:

```bash
UPDATE_SNAPSHOTS=1 ./vendor/bin/phpunit
```

### Batch Snapshot Updates

For tests with many datasets, use the `update-snapshots` CLI tool to update
snapshots one dataset at a time with timeout handling and automatic retries:

```bash
# Update all datasets for a test
vendor/bin/update-snapshots testMySnapshot tests/snapshots

# Update a specific dataset
vendor/bin/update-snapshots testMySnapshot tests/snapshots baseline

# Specify project root (useful when running from subdirectory)
vendor/bin/update-snapshots --root=/path/to/project testMySnapshot tests/snapshots
```

The tool:
- Discovers all datasets from PHPUnit test list
- Runs baseline dataset first, then remaining datasets
- Handles timeouts with configurable retries
- Auto-commits baseline and snapshot changes
- Shows progress with visual feedback

Options:
- `--root=<path>` - Project root directory (default: current directory)
- `--test-dir=<path>` - Directory containing tests (default: `tests`)
- `--timeout=<seconds>` - Timeout per test run (default: 30)
- `--retries=<count>` - Max retries for timed out tests (default: 3)
- `--debug` - Show PHPUnit output for failed tests

Example output:

```
Discovering datasets...
Found 13 unique datasets
[1/13] baseline .......... ✓
[2/13] name ........... ✓
[3/13] no docs ..... ✓
[4/13] no funding ..... ✓
[5/13] no languages .......... ✓
[6/13] no pr autoassign .... ✓
[7/13] no pr template ..... ✓
[8/13] no release drafter ..... ✓
[9/13] no renovate ..... ✓
[10/13] nodejs ........... ✓
[11/13] php command ......... ✓
[12/13] php script ......... ✓
[13/13] shell ..... ✓
Total: 13 | Succeeded: 13 | Failed: 0 | Timed out: 0
Total execution time: 1 minutes 37 seconds
```

### Ignore Rules

Create a `.ignorecontent` file in your baseline directory to control which files
are compared and how.

```
# Skip files entirely - they won't be compared at all
*.log
cache/
node_modules/

# Include specific files (override a previous skip rule)
!important.log

# Ignore content differences - verify file exists, but allow any content
^composer.lock
^package-lock.json
```

#### Why Ignore Content?

Some files should exist but have unpredictable or environment-specific content:

- **`composer.lock`** - You want to verify it was generated, but the exact
  content depends on dependency resolution timing and isn't meaningful to test
- **`package-lock.json`** - Same as above for npm dependencies
- **Generated timestamps** - Files containing build dates or version hashes
- **Environment configs** - Files that vary between CI and local environments

Using `^filename` ensures the file exists without failing on content differences.

#### Pattern Reference

| Pattern | Effect |
|---------|--------|
| `*.log` | Skip all files matching the glob pattern |
| `cache/` | Skip the entire directory and its contents |
| `!important.log` | Include this file even if a previous rule would skip it |
| `^composer.lock` | Check that file exists, but don't compare its content |

### Programmatic API

Use the `Snapshot` class directly for custom workflows:

```php
use AlexSkrypnyk\Snapshot\Snapshot;

// Scan a directory
$index = Snapshot::scan($directory);

// Compare directories
$comparer = Snapshot::compare($baseline, $actual);
echo $comparer->render();

// Create diff files
Snapshot::diff($baseline, $actual, $output_dir);

// Apply patches
Snapshot::patch($baseline, $patches, $destination);

// Sync directories
Snapshot::sync($source, $destination);
```

### Fluent Builder API

For configured operations with rules and content processors, use `SnapshotBuilder`:

```php
use AlexSkrypnyk\Snapshot\SnapshotBuilder;
use AlexSkrypnyk\Snapshot\Rules\Rules;

// Create a reusable builder with configuration
$builder = SnapshotBuilder::create()
    ->withRules(Rules::phpProject())
    ->addSkip('custom/')
    ->addIgnoreContent('custom.lock')
    ->withContentProcessor(fn($content) => trim($content));

// Use the builder for multiple operations
$index = $builder->scan($directory);
$comparer = $builder->compare($dir1, $dir2);
$builder->sync($source, $destination);
$builder->diff($baseline, $actual, $output);
$builder->patch($baseline, $patches, $destination);
```

### Programmatic Rules

Configure comparison rules programmatically using the `Rules` class:

```php
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\Snapshot;

// Use preset rules for common project types
$rules = Rules::phpProject();  // Skips vendor/, ignores composer.lock
$rules = Rules::nodeProject(); // Skips node_modules/, ignores lock files

// Or create custom rules with fluent API
$rules = Rules::create()
    ->skip('vendor/', 'node_modules/', '.git/')
    ->ignoreContent('composer.lock', 'package-lock.json')
    ->include('important.log');

// Use rules with Snapshot operations
$comparer = Snapshot::compare($baseline, $actual, $rules);
```

### Version Normalization

When updating snapshots, volatile content like version numbers, hashes, and
timestamps can cause unnecessary churn. The `Replacer` class automatically
normalizes this content during snapshot updates.

#### Default Behavior

The `snapshotUpdateBefore()` hook automatically applies version normalization
using `File::getReplacer()->addVersionReplacements()`:

```php
// This happens automatically in snapshotUpdateOnFailure()
File::getReplacer()->addVersionReplacements()->replaceInDir($actual);
```

The default patterns replace:
- Semver versions (`1.2.3`, `v1.2.3-beta.1`) → `__VERSION__`
- Git hashes (`@abc123...`) → `@__HASH__`
- SRI integrity hashes (`sha512-...`) → `__INTEGRITY__`
- Docker image tags (`nginx:1.21.0`) → `nginx:__VERSION__`
- GitHub Actions versions (`actions/checkout@v4`) → `actions/checkout@__VERSION__`
- Package versions in JSON (`"^1.2.3"`) → `"__VERSION__"`

#### Customizing Version Replacement

Override `snapshotUpdateBefore()` to customize the replacement behavior:

```php
protected function snapshotUpdateBefore(string $actual): void {
    // Use default patterns but add custom ones
    File::getReplacer()
        ->addVersionReplacements()
        ->setMaxReplacements(0)
        ->addReplacement(Replacement::create('custom', '/BUILD-\d+/', '__BUILD__'))
        ->replaceInDir($actual);
}
```

Or disable version replacement entirely:

```php
protected function snapshotUpdateBefore(string $actual): void {
    // Do nothing - keep versions as-is
}
```

#### Standalone Usage

Use `Replacer` independently for custom workflows:

```php
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\File\Replacer\Replacement;

// Use preset version patterns
$replacer = File::getReplacer()->addVersionReplacements();
$replacer->replaceInDir($directory);

// Or create custom replacer
$replacer = File::getReplacer()
    ->addReplacement(Replacement::create('version', '/v\d+\.\d+\.\d+/', '__VERSION__'))
    ->addReplacement(Replacement::create('date', '/\d{4}-\d{2}-\d{2}/', '__DATE__'));

// Apply to string content
$content = 'Version: v1.2.3';
$replacer->replace($content);  // $content is now 'Version: __VERSION__'

// Apply to directory
$replacer->replaceInDir($directory);
```

## Maintenance


    composer install
    composer lint
    composer test

### Performance Benchmarks

Run benchmarks to measure performance of core operations:

    # Run benchmarks with baseline comparison
    composer benchmark

    # Create or update baseline
    composer benchmark-baseline

    # Quick test (verify benchmarks work)
    ./vendor/bin/phpbench run benchmarks/SnapshotBench.php --iterations=1 --revs=1

---
_This repository was created using the [Scaffold](https://getscaffold.dev/) project template_
