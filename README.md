<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="logo.png" alt="Snapshot logo"></a>
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

## ✨ Features

- **Directory comparison** - Compare two directories for identical structure and content
- **Baseline + diff architecture** - Store a baseline once, then only diffs per test scenario
- **Unified diff format** - Human-readable patch files that can be reviewed in PRs
- **Auto-update snapshots** - Automatically update snapshots when tests fail
- **Batch update CLI** - Regenerate many snapshots at once, in parallel, with timeouts and retries
- **Version normalization** - Replace volatile versions, hashes and timestamps before snapshots are written
- **Flexible ignore rules** - Skip files, directories, or ignore content differences
- **PHPUnit integration** - Simple trait with intuitive assertions

## 🎯 Use Cases

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

## 🧩 Concepts

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

## 📦 Installation

    composer require --dev alexskrypnyk/snapshot

## 🚀 Usage

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
        $baseline_dir,          // Common baseline
        $scenario_a_diffs_dir,  // Diffs specific to scenario A
        $output_dir             // Actual output
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
snapshots with timeout handling, automatic retries, and parallel execution:

```bash
# Update all datasets for a test
vendor/bin/update-snapshots testMySnapshot tests/snapshots

# Update specific datasets
vendor/bin/update-snapshots testMySnapshot tests/snapshots baseline scenario1

# Run with 8 parallel jobs
vendor/bin/update-snapshots --jobs=8 testMySnapshot tests/snapshots

# Specify project root (useful when running from subdirectory)
vendor/bin/update-snapshots --root=../.. testMySnapshot tests/snapshots
```

The tool:
- Discovers all datasets from PHPUnit test list
- Runs baseline dataset first (sequentially), then remaining scenarios in parallel
- Handles timeouts with configurable retries
- Auto-commits baseline and snapshot changes
- Shows a live TUI progress display with scrolling when running in a terminal
- Terminates every spawned dataset process and exits `130` (`SIGINT`) or `143` (`SIGTERM`) when signalled; requires the `pcntl` extension

Options:
- `--root=<path>` - Project root directory (default: current directory)
- `--test-dir=<path>` - Directory containing tests (default: `tests`)
- `--timeout=<seconds>` - Timeout per test run (default: 30)
- `--retries=<count>` - Max retries for timed out tests (default: 12)
- `--jobs=<count>` - Number of parallel jobs for scenarios (default: 4)
- `--debug` - Show PHPUnit output for failed tests

#### Exit Codes

Updating a snapshot is the expected outcome, so the tool exits `0` when it updates one. It exits non-zero only when a dataset genuinely cannot be updated - a failure that is not a snapshot mismatch, or a run that keeps timing out. A single PHPUnit run still exits non-zero after updating a snapshot, because the assertion fails before `tearDown()` rewrites the files; the tool reclassifies those runs as updated.

#### Parallel Execution

When updating all datasets, the baseline is always run first (since other
scenarios may depend on it). Once the baseline completes, all remaining
scenarios run in parallel using the number of jobs specified by `--jobs`.

In a TTY terminal, a live progress display shows the status of all tasks with
keyboard scrolling (arrow keys and Page Up/Down). In non-TTY environments
(e.g., CI), results are printed after all tasks complete.

### Ignore Rules

Create a `.ignorecontent` file in your baseline directory to control which files
are compared and how.

```
# Skip by file name, anywhere in the tree
*.log
.DS_Store

# Skip by path, relative to this directory
node_modules/
build/cache/

# Include a file that a path rule would otherwise skip
!build/cache/manifest.json

# Ignore content differences - verify the file exists, but allow any content
^composer.lock
^package-lock.json
```

A pattern is matched in one of two ways, depending on whether it contains a `/`:

- **Without a `/`** the pattern is matched against the file name alone, so `*.log` skips every `.log` file at any depth.
- **With a `/`** the pattern is matched against the path relative to the directory being indexed, so `build/cache/` only skips that one directory.

A `!` rule overrides either kind: it is matched against both the file name and the relative path, so `!important.log` keeps that file even though `*.log` would otherwise skip it.

A `!^` rule overrides content ignoring instead: `!^composer.lock` keeps comparing that file's content even though `^composer.lock` would otherwise leave it unchecked. Unlike `!`, a `!^` rule is matched only against the relative path, the same way a `^` rule is.

The `.ignorecontent` file itself and the `.git/` directory are always skipped and cannot be re-included by any `!` rule.

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
| `*.log` | Skip every file whose name matches the glob, at any depth |
| `cache/` | Skip the directory and everything under it |
| `cache/*` | Skip the files directly in the directory, but not its subdirectories |
| `!cache/keep.txt` | Include this file even though another rule would otherwise skip it |
| `^composer.lock` | Check that the file exists, but do not compare its content |
| `^cache/` | Check that files under the directory exist, but do not compare their content |
| `!^composer.lock` | Compare this file's content even though a `^` rule would otherwise ignore it |

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

// Apply diffs to the baseline
Snapshot::patch($baseline, $diffs, $destination);

// Sync directories
Snapshot::sync($source, $destination);
```

### Fluent Builder API

For configured operations with rules, a file filter or a content processor, use
`SnapshotBuilder`:

```php
use AlexSkrypnyk\Snapshot\Index\IndexedFile;
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\SnapshotBuilder;

// Create a reusable builder with configuration
$builder = SnapshotBuilder::create()
    ->withRules(Rules::phpProject())
    ->addSkip('custom/')
    ->addIgnoreContent('custom.lock')
    ->addInclude('custom/keep.txt')
    ->addIncludeContent('custom/keep.log')
    // Drop every generated file from the index
    ->withFileFilter(fn(IndexedFile $file) => !str_contains($file->getPathnameFromBasepath(), 'generated/'))
    // Normalise the content of every file written by patch()
    ->withContentProcessor(fn(string $content) => trim($content));

// Use the builder for multiple operations
$index = $builder->scan($directory);
$comparer = $builder->compare($dir1, $dir2);
$builder->sync($source, $destination);
$builder->diff($baseline, $actual, $output);
$builder->patch($baseline, $diffs, $destination);
```

The two callbacks serve different operations and receive different values:

| Callback | Used by | Receives | Effect |
|----------|---------|----------|--------|
| `withFileFilter()` | `scan()`, `compare()`, `diff()`, `sync()` | An `IndexedFile` | Returning `FALSE` excludes the file from the index |
| `withContentProcessor()` | `patch()` | The patched file content as a string | The returned string is written back to the file |

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
    ->ignoreContent('composer.lock', 'package-lock.json', 'reports/')
    // Keeps this one file even though vendor/ is skipped
    ->include('vendor/autoload.php')
    // Compares this one file's content even though reports/ is content-ignored
    ->includeContent('reports/summary.json');

// Or load them from an existing .ignorecontent file
$rules = Rules::fromFile($baseline . '/.ignorecontent');

// Use rules with Snapshot operations
$comparer = Snapshot::compare($baseline, $actual, $rules);
```

The presets are built from rule sets. Extend `AbstractRuleSet` to define your
own reusable set and turn it into rules with `Rules::fromRuleSet()`:

```php
use AlexSkrypnyk\Snapshot\Rules\AbstractRuleSet;
use AlexSkrypnyk\Snapshot\Rules\Rules;

class MyProjectRuleSet extends AbstractRuleSet {

    protected const SKIP_PATTERNS = ['dist/', '.cache/'];

    protected const IGNORE_CONTENT_PATTERNS = ['reports/'];

    protected const GLOBAL_PATTERNS = ['*.log'];

    protected const INCLUDE_PATTERNS = ['dist/keep.txt'];

    protected const INCLUDE_CONTENT_PATTERNS = ['reports/summary.json'];

}

$rules = Rules::fromRuleSet(new MyProjectRuleSet());
```

Each constant maps to the matching `Rules` pattern list, and a set can define
only the constants it needs - the rest default to empty.

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
    $build = Replacement::create('build', '/BUILD-\d+/', '__BUILD__');

    File::getReplacer()
        ->addVersionReplacements()
        ->setMaxReplacements(0)
        ->addReplacement($build)
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
$version = Replacement::create('version', '/v\d+\.\d+\.\d+/', '__VERSION__');
$date = Replacement::create('date', '/\d{4}-\d{2}-\d{2}/', '__DATE__');

$replacer = File::getReplacer()
    ->addReplacement($version)
    ->addReplacement($date);

// Apply to string content
$content = 'Version: v1.2.3';
$replacer->replace($content);  // $content is now 'Version: __VERSION__'

// Apply to directory
$replacer->replaceInDir($directory);
```

## 🤝 Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for local development setup, the
linting and testing commands, and how to run the performance benchmarks.

## 🔄 Updating

To pull the latest infrastructure from the template into this project, ask
Claude Code to "update scaffold" - see [`AGENTS.md`](AGENTS.md) for details.

---
_This repository was created using the [Scaffold](https://getscaffold.dev/) project template_
