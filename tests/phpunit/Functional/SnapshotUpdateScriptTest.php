<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Functional;

use AlexSkrypnyk\File\File;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Functional tests for the snapshot-update CLI script.
 *
 * Tests the following scenarios:
 * 1. no_change      - Baseline passes, scenario passes → 0 commits
 * 2. baseline_change - Baseline fails, scenario passes → 1 commit
 * 3. scenario_change - Single dataset mode (no commit, but files updated)
 * 4. both_change    - Baseline fails, scenario fails → 1 commit (amended)
 */
#[CoversNothing]
final class SnapshotUpdateScriptTest extends FunctionalTestCase {

  /**
   * Path to the snapshot-update script.
   */
  protected string $scriptPath;

  /**
   * Path to the test project directory in $sut.
   */
  protected string $projectDir;

  /**
   * Path to the functional_update fixtures.
   */
  protected string $fixturesDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->scriptPath = self::$root . '/bin/snapshot-update';
    $this->projectDir = self::$sut . DIRECTORY_SEPARATOR . 'test_project';
    $this->fixturesDir = self::$fixtures . DIRECTORY_SEPARATOR . 'functional_update';
  }

  /**
   * Test that help is displayed with --help flag.
   */
  public function testHelpFlag(): void {
    $this->processRun('php', [$this->scriptPath, '--help']);

    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('Update test snapshots');
    $this->assertProcessOutputContains('Usage:');
    $this->assertProcessOutputContains('Arguments:');
    $this->assertProcessOutputContains('Options:');
    $this->assertProcessOutputContains('--root=');
    $this->assertProcessOutputContains('--timeout=');
    $this->assertProcessOutputContains('--retries=');
    $this->assertProcessOutputContains('--debug');
    $this->assertProcessOutputContains('Examples:');
  }

  /**
   * Test that help is displayed with -h flag.
   */
  public function testHelpShortFlag(): void {
    $this->processRun('php', [$this->scriptPath, '-h']);

    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('Update test snapshots');
  }

  /**
   * Test that error is shown when test-name argument is missing.
   */
  public function testMissingTestNameArgument(): void {
    $this->processRun('php', [$this->scriptPath]);

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Missing required argument: <test-name>');
  }

  /**
   * Test that error is shown when snapshots-path argument is missing.
   */
  public function testMissingSnapshotsPathArgument(): void {
    $this->processRun('php', [$this->scriptPath, 'testSomeMethod']);

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Missing required argument: <snapshots-path>');
  }

  /**
   * Test that error is shown when root directory does not exist.
   */
  public function testInvalidRootDirectory(): void {
    $this->processRun('php', [
      $this->scriptPath,
      '--root=/nonexistent/directory',
      'testSomeMethod',
      'snapshots',
    ]);

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Root directory does not exist');
  }

  /**
   * Test that error is shown when PHPUnit is not found.
   */
  public function testPhpunitNotFound(): void {
    $temp_dir = self::$sut . DIRECTORY_SEPARATOR . 'no_phpunit';
    File::mkdir($temp_dir);

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $temp_dir,
      'testSomeMethod',
      'snapshots',
    ]);

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('PHPUnit not found');
  }

  /**
   * Test that SCRIPT_QUIET environment variable suppresses output.
   */
  public function testQuietMode(): void {
    $this->processRun(
      'php',
      [$this->scriptPath, '--help'],
      [],
      ['SCRIPT_QUIET' => '1']
    );

    $this->assertProcessSuccessful();
    $this->assertEmpty($this->processGet()->getOutput());
  }

  /**
   * Test that script can be skipped with SCRIPT_RUN_SKIP environment variable.
   */
  public function testSkipScript(): void {
    $this->processRun(
      'php',
      [$this->scriptPath],
      [],
      ['SCRIPT_RUN_SKIP' => '1']
    );

    $this->assertProcessSuccessful();
    $this->assertEmpty($this->processGet()->getOutput());
  }

  /**
   * Test no_change scenario: all tests pass, no commits created.
   *
   * Scenario: no_change
   * - Actual matches baseline
   * - Run all datasets
   * - Expected: 0 new commits, files unchanged.
   */
  public function testNoChangeNoCommits(): void {
    $this->setupTestProject('no_change');

    // Run snapshot-update for all datasets.
    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
    ]);

    // Assert: datasets discovered, all pass.
    $this->assertProcessOutputContains('Discovering datasets');
    $this->assertProcessOutputContains('Found');

    // Assert: only 1 commit (initial commit).
    $commit_count = $this->getCommitCount();
    $this->assertSame(1, $commit_count, 'Expected only initial commit');
  }

  /**
   * Test baseline_change scenario: baseline fails, creates commit.
   *
   * Scenario: baseline_change
   * - Actual file1.txt differs from baseline
   * - Run all datasets
   * - Expected: 1 commit with "Updated" message, baseline files updated.
   */
  public function testBaselineChangeCreatesCommit(): void {
    $this->setupTestProject('baseline_change');

    // Run snapshot-update for all datasets.
    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
    ]);

    // Assert: datasets discovered.
    $this->assertProcessOutputContains('Discovering datasets');
    $this->assertProcessOutputContains('Found');

    // Assert: 2 commits (initial + update).
    $commit_count = $this->getCommitCount();
    $this->assertSame(2, $commit_count, 'Expected initial + update commit');

    // Assert: commit message contains "Updated".
    $last_commit_msg = $this->getLastCommitMessage();
    $this->assertTrue(
      str_contains($last_commit_msg, 'Updated baseline') || str_contains($last_commit_msg, 'Updated snapshots'),
      'Expected commit message containing "Updated"'
    );

    // Assert: baseline files match expected.
    $this->assertDirectoriesIdentical(
      $this->fixturesDir . '/baseline_change/expected/_baseline',
      $this->projectDir . '/tests/snapshots/_baseline'
    );
  }

  /**
   * Test scenario_change: single dataset mode updates files (no commit).
   *
   * Scenario: scenario_change
   * - Actual has extra scenario_file.txt
   * - Run ONLY scenario1 dataset (single dataset mode)
   * - Expected: Files updated, no commit (single dataset mode doesn't commit)
   */
  public function testScenarioChangeUpdatesFiles(): void {
    $this->setupTestProject('scenario_change');

    // Run snapshot-update for ONLY scenario1 dataset.
    // Single dataset mode doesn't create commits.
    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
      'scenario1',
    ]);

    // Assert: single dataset mode ran.
    $this->assertProcessOutputContains('Scanning for dataset: scenario1');

    // Assert: scenario diff file was created.
    $scenario_file = $this->projectDir . '/tests/snapshots/scenario1/scenario_file.txt';
    $this->assertFileExists($scenario_file);

    // Assert: content matches expected.
    $expected_file = $this->fixturesDir . '/scenario_change/expected/scenario1/scenario_file.txt';
    $this->assertFileEquals($expected_file, $scenario_file);

    // Assert: only 1 commit (initial - single dataset mode doesn't commit).
    $commit_count = $this->getCommitCount();
    $this->assertSame(1, $commit_count, 'Single dataset mode should not create commits');
  }

  /**
   * Test both_change scenario: baseline and scenario both fail.
   *
   * Scenario: both_change
   * - Actual file1.txt differs from baseline
   * - Actual has extra scenario_file.txt
   * - Run all datasets
   * - Expected: 1 commit (amended), baseline updated with ALL actual files.
   */
  public function testBothChangeCreatesCommit(): void {
    $this->setupTestProject('both_change');

    // Run snapshot-update for all datasets.
    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
    ]);

    // Assert: datasets discovered.
    $this->assertProcessOutputContains('Discovering datasets');
    $this->assertProcessOutputContains('Found');

    // Assert: 2 commits (initial + update, possibly amended).
    $commit_count = $this->getCommitCount();
    $this->assertSame(2, $commit_count, 'Expected initial + update commit');

    // Assert: commit message contains "Updated".
    $last_commit_msg = $this->getLastCommitMessage();
    $this->assertTrue(
      str_contains($last_commit_msg, 'Updated baseline') || str_contains($last_commit_msg, 'Updated snapshots'),
      'Expected commit message containing "Updated"'
    );

    // Assert: baseline files match expected (includes scenario_file.txt).
    $this->assertDirectoriesIdentical(
      $this->fixturesDir . '/both_change/expected/_baseline',
      $this->projectDir . '/tests/snapshots/_baseline'
    );

    // Assert: scenario1 should remain empty (just metadata files like .gitkeep
    // and .ignorecontent - no actual diff files).
    $scenario_path = $this->projectDir . '/tests/snapshots/scenario1';
    $scenario_files = array_diff(scandir($scenario_path), ['.', '..', '.gitkeep', '.ignorecontent']);
    $this->assertCount(0, $scenario_files, 'scenario1 should not contain any diff files');
  }

  /**
   * Set up a test project by copying a complete scenario fixture.
   *
   * @param string $scenario
   *   Scenario name (no_change, baseline_change, scenario_change, both_change).
   */
  protected function setupTestProject(string $scenario): void {
    // Copy complete scenario fixture to $sut.
    $scenario_dir = $this->fixturesDir . DIRECTORY_SEPARATOR . $scenario;
    File::copy($scenario_dir, $this->projectDir);

    // Create vendor directory with symlinks to root's vendor.
    $vendor_dir = $this->projectDir . '/vendor';
    File::mkdir($vendor_dir . '/bin');
    symlink(
      self::$root . '/vendor/autoload.php',
      $vendor_dir . '/autoload.php'
    );
    symlink(
      self::$root . '/vendor/bin/phpunit',
      $vendor_dir . '/bin/phpunit'
    );
    symlink(
      self::$root . '/vendor/composer',
      $vendor_dir . '/composer'
    );
    symlink(
      self::$root . '/vendor/phpunit',
      $vendor_dir . '/phpunit'
    );
    symlink(
      self::$root . '/vendor/alexskrypnyk',
      $vendor_dir . '/alexskrypnyk'
    );
    symlink(
      self::$root . '/vendor/sebastian',
      $vendor_dir . '/sebastian'
    );

    // Initialize git repository.
    $this->processCwd = $this->projectDir;
    $this->processRun('git', ['init']);
    $this->processRun('git', ['config', 'user.email', 'test@test.com']);
    $this->processRun('git', ['config', 'user.name', 'Test']);
    $this->processRun('git', ['add', '.']);
    $this->processRun('git', ['commit', '-m', 'Initial commit']);
  }

  /**
   * Get the number of commits in the test project.
   */
  protected function getCommitCount(): int {
    $this->processCwd = $this->projectDir;
    $this->processRun('git', ['rev-list', '--count', 'HEAD']);
    return (int) trim($this->processGet()->getOutput());
  }

  /**
   * Get the last commit message in the test project.
   */
  protected function getLastCommitMessage(): string {
    $this->processCwd = $this->projectDir;
    $this->processRun('git', ['log', '-1', '--format=%s']);
    return trim($this->processGet()->getOutput());
  }

}
