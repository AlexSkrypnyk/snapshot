# functional_update Test Fixtures

Fixtures for testing the `bin/snapshot-update` CLI script.

Each scenario contains a **complete** project that is copied to `$sut/test_project/`.

## Directory Structure

```
functional_update/
├── README.md
│
├── no_change/                    # Scenario: everything matches
│   ├── .gitignore                # Ignores .expected directory
│   ├── phpunit.xml
│   ├── composer.json
│   ├── tests/
│   │   ├── SnapshotTest.php
│   │   └── snapshots/
│   │       ├── _baseline/
│   │       │   ├── file1.txt     # "original content"
│   │       │   └── file2.txt     # "content 2"
│   │       └── scenario1/
│   │           ├── .gitkeep
│   │           └── .ignorecontent # Skips .gitkeep in comparison
│   └── actual/
│       ├── file1.txt             # "original content" ← MATCHES
│       └── file2.txt             # "content 2" ← MATCHES
│
├── baseline_change/              # Scenario: baseline needs update
│   ├── .gitignore
│   ├── phpunit.xml
│   ├── composer.json
│   ├── tests/
│   │   ├── SnapshotTest.php
│   │   └── snapshots/
│   │       ├── _baseline/
│   │       │   ├── file1.txt     # "original content"
│   │       │   └── file2.txt     # "content 2"
│   │       └── scenario1/
│   │           ├── .gitkeep
│   │           └── .ignorecontent
│   ├── actual/
│   │   ├── file1.txt             # "modified content for baseline" ← DIFFERS
│   │   └── file2.txt             # "content 2"
│   └── expected/
│       └── _baseline/
│           ├── file1.txt         # "modified content for baseline"
│           └── file2.txt         # "content 2"
│
├── scenario_change/              # Scenario: scenario needs update (single dataset)
│   ├── .gitignore
│   ├── phpunit.xml
│   ├── composer.json
│   ├── tests/
│   │   ├── SnapshotTest.php
│   │   └── snapshots/
│   │       ├── _baseline/
│   │       │   ├── file1.txt     # "original content"
│   │       │   └── file2.txt     # "content 2"
│   │       └── scenario1/
│   │           ├── .gitkeep
│   │           └── .ignorecontent
│   ├── actual/
│   │   ├── file1.txt             # "original content"
│   │   ├── file2.txt             # "content 2"
│   │   └── scenario_file.txt     # "new scenario content" ← NEW FILE
│   └── expected/
│       └── scenario1/
│           └── scenario_file.txt # "new scenario content"
│
└── both_change/                  # Scenario: baseline AND scenario need update
    ├── .gitignore
    ├── phpunit.xml
    ├── composer.json
    ├── tests/
    │   ├── SnapshotTest.php
    │   └── snapshots/
    │       ├── _baseline/
    │       │   ├── file1.txt     # "original content"
    │       │   └── file2.txt     # "content 2"
    │       └── scenario1/
    │           ├── .gitkeep
    │           └── .ignorecontent
    ├── actual/
    │   ├── file1.txt             # "modified content for baseline" ← DIFFERS
    │   ├── file2.txt             # "content 2"
    │   └── scenario_file.txt     # "new scenario content" ← NEW FILE
    └── expected/
        ├── _baseline/
        │   ├── file1.txt         # "modified content for baseline"
        │   ├── file2.txt         # "content 2"
        │   └── scenario_file.txt # "new scenario content" (merged into baseline)
        └── scenario1/
            └── .gitkeep          # Empty (no diffs after baseline update)
```

## Test Scenarios

### no_change

**Test**: `testNoChangeNoCommits`

**Purpose**: Verify no commits created when all tests pass.

**Flow**:
1. Copy `no_change/` to `$sut/test_project/`
2. Run `snapshot-update testSnapshot tests/snapshots` (all datasets)
3. All tests pass → no updates needed
4. Assert: only 1 commit (initial commit)

### baseline_change

**Test**: `testBaselineChangeCreatesCommit`

**Purpose**: Verify baseline is updated when actual differs from baseline.

**Flow**:
1. Copy `baseline_change/` to `$sut/test_project/`
2. Run `snapshot-update testSnapshot tests/snapshots` (all datasets)
3. Baseline test fails → baseline is updated
4. Git commit created with "Updated baseline" message
5. Assert: 2 commits (initial + update)
6. Compare `_baseline/` with `expected/_baseline/`

### scenario_change

**Test**: `testScenarioChangeUpdatesFiles`

**Purpose**: Verify scenario diff files are created in single dataset mode.

**Flow**:
1. Copy `scenario_change/` to `$sut/test_project/`
2. Run `snapshot-update testSnapshot tests/snapshots scenario1` (single dataset)
3. Scenario test fails → diff file created
4. Assert: only 1 commit (single dataset mode doesn't commit)
5. Compare `scenario1/` with `expected/scenario1/`

**Note**: Single dataset mode does NOT create commits.

### both_change

**Test**: `testBothChangeCreatesCommit`

**Purpose**: Verify correct behavior when both baseline and scenario need updates.

**Flow**:
1. Copy `both_change/` to `$sut/test_project/`
2. Run `snapshot-update testSnapshot tests/snapshots` (all datasets)
3. Baseline test fails → baseline updated (includes scenario_file.txt)
4. Scenario test fails → scenario diff recalculated (now empty)
5. Git commit created/amended
6. Assert: 2 commits (initial + update)
7. Compare `_baseline/` with `expected/_baseline/`
8. Assert: `scenario1/` has no diff files (only .gitkeep, .ignorecontent)

## File Contents

| File                                       | Content                         |
|--------------------------------------------|---------------------------------|
| `_baseline/file1.txt`                      | `original content`              |
| `_baseline/file2.txt`                      | `content 2`                     |
| `baseline_change/actual/file1.txt`         | `modified content for baseline` |
| `both_change/actual/file1.txt`             | `modified content for baseline` |
| `both_change/actual/scenario_file.txt`     | `new scenario content`          |
| `scenario_change/actual/scenario_file.txt` | `new scenario content`          |

## Special Files

- `.gitignore` - Prevents `.expected` temp directory from being committed
- `.ignorecontent` - Skips `.gitkeep` during snapshot comparison
