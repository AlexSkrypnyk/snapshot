<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Functional;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Snapshot;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Exception;
use Symfony\Component\Process\Process;

/**
 * Functional tests for the update-snapshots CLI script.
 *
 * Tests the following scenarios:
 * 1. no_change - Baseline passes, scenario passes → 0 commits.
 * 2. baseline_change - Baseline fails, scenario passes → 1 commit.
 * 3. scenario_change - Single dataset mode (no commit, but files updated).
 * 4. both_change - Baseline fails, scenario fails → 1 commit (amended).
 * 5. genuine_failure - Non-snapshot failure → non-zero exit, no commit.
 * 6. scenario_only_change - Stale scenario diff updates in parallel run.
 */
#[CoversNothing]
final class SnapshotUpdateScriptTest extends FunctionalTestCase {

  /**
   * Seconds to wait for a file written by a spawned dataset to appear.
   */
  protected const int WAIT_FOR_FILE = 30;

  /**
   * Seconds to wait for the signalled script to exit.
   */
  protected const int WAIT_FOR_EXIT = 30;

  /**
   * Seconds to wait for spawned processes to disappear.
   *
   * Far shorter than the fixture's sleep, so a process that only ends when its
   * own test finishes still counts as a survivor.
   */
  protected const int WAIT_FOR_PROCESSES_GONE = 10;

  protected string $scriptPath;

  protected string $projectDir;

  protected string $fixturesDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->scriptPath = self::$root . '/bin/update-snapshots';
    $this->projectDir = self::$sut . DIRECTORY_SEPARATOR . 'test_project';
    $this->fixturesDir = self::$fixtures . DIRECTORY_SEPARATOR . 'functional_update';
  }

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
    $this->assertProcessOutputContains('--jobs=');
    $this->assertProcessOutputContains('--debug');
    $this->assertProcessOutputContains('dataset ...');
    $this->assertProcessOutputContains('Examples:');
  }

  public function testHelpShortFlag(): void {
    $this->processRun('php', [$this->scriptPath, '-h']);

    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('Update test snapshots');
  }

  public function testMissingTestNameArgument(): void {
    $this->processRun('php', [$this->scriptPath]);

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Missing required argument: <test-name>');
  }

  public function testMissingSnapshotsPathArgument(): void {
    $this->processRun('php', [$this->scriptPath, 'testSomeMethod']);

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Missing required argument: <snapshots-path>');
  }

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

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
    ]);

    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('Discovering datasets');
    $this->assertProcessOutputContains('Found');

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

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
    ]);

    // A successful update exits 0. Only genuine failures exit non-zero.
    $this->assertProcessSuccessful();

    $this->assertProcessOutputContains('Discovering datasets');
    $this->assertProcessOutputContains('Found');

    $commit_count = $this->getCommitCount();
    $this->assertSame(2, $commit_count, 'Expected initial + update commit');

    $last_commit_message = $this->getLastCommitMessage();
    $this->assertTrue(
      str_contains($last_commit_message, 'Updated baseline') || str_contains($last_commit_message, 'Updated snapshots'),
      'Expected commit message containing "Updated"'
    );

    $this->assertDirectoriesIdentical(
      $this->fixturesDir . '/baseline_change/expected/' . Snapshot::BASELINE_DIR,
      $this->projectDir . '/tests/snapshots/' . Snapshot::BASELINE_DIR
    );
  }

  /**
   * Test scenario_change: specified dataset mode updates files (no commit).
   *
   * Scenario: scenario_change
   * - Actual has extra scenario_file.txt
   * - Run ONLY scenario1 dataset (specified dataset mode)
   * - Expected: Files updated, no commit (specified dataset mode doesn't
   *   commit)
   */
  public function testScenarioChangeUpdatesFiles(): void {
    $this->setupTestProject('scenario_change');

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
      'scenario1',
    ]);

    // A successful update exits 0 even in specified dataset mode.
    $this->assertProcessSuccessful();

    $this->assertProcessOutputContains('Running 1 specified dataset(s)');
    $this->assertProcessOutputContains('scenario1');

    $scenario_file = $this->projectDir . '/tests/snapshots/scenario1/scenario_file.txt';
    $this->assertFileExists($scenario_file);

    $expected_file = $this->fixturesDir . '/scenario_change/expected/scenario1/scenario_file.txt';
    $this->assertFileEquals($expected_file, $scenario_file);

    $commit_count = $this->getCommitCount();
    $this->assertSame(1, $commit_count, 'Specified dataset mode should not create commits');
  }

  /**
   * Test multiple datasets: specified datasets are all processed.
   *
   * Scenario: both_change
   * - Run baseline AND scenario1 datasets
   * - Expected: Both datasets processed, files updated, no commit.
   */
  public function testMultipleDatasetsUpdatesFiles(): void {
    $this->setupTestProject('both_change');

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
      'baseline',
      'scenario1',
    ]);

    // A successful update exits 0 with multiple specified datasets.
    $this->assertProcessSuccessful();

    $this->assertProcessOutputContains('Running 2 specified dataset(s)');
    $this->assertProcessOutputContains('baseline');
    $this->assertProcessOutputContains('scenario1');

    $this->assertDirectoriesIdentical(
      $this->fixturesDir . '/both_change/expected/' . Snapshot::BASELINE_DIR,
      $this->projectDir . '/tests/snapshots/' . Snapshot::BASELINE_DIR
    );

    $commit_count = $this->getCommitCount();
    $this->assertSame(1, $commit_count, 'Specified dataset mode should not create commits');
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

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
    ]);

    // A successful update exits 0 even when baseline and scenario both change.
    $this->assertProcessSuccessful();

    $this->assertProcessOutputContains('Discovering datasets');
    $this->assertProcessOutputContains('Found');

    $commit_count = $this->getCommitCount();
    $this->assertSame(2, $commit_count, 'Expected initial + update commit');

    $last_commit_message = $this->getLastCommitMessage();
    $this->assertTrue(
      str_contains($last_commit_message, 'Updated baseline') || str_contains($last_commit_message, 'Updated snapshots'),
      'Expected commit message containing "Updated"'
    );

    $this->assertDirectoriesIdentical(
      $this->fixturesDir . '/both_change/expected/' . Snapshot::BASELINE_DIR,
      $this->projectDir . '/tests/snapshots/' . Snapshot::BASELINE_DIR
    );

    // The scenario1 directory holds only metadata files (.gitkeep,
    // .ignorecontent), no diff files.
    $scenario_path = $this->projectDir . '/tests/snapshots/scenario1';
    $scenario_files = array_diff(scandir($scenario_path), ['.', '..', '.gitkeep', '.ignorecontent']);
    $this->assertCount(0, $scenario_files, 'scenario1 should not contain any diff files');
  }

  /**
   * Test that stderr from snapshotUpdateOnFailure() is captured separately.
   *
   * The script spawns PHPUnit with separate pipes for stdout and stderr. The
   * [SNAPSHOT] messages written to stderr by snapshotUpdateOnFailure() must
   * not be merged into stdout (no 2>&1), as this can cause false PHPUnit
   * errors.
   *
   * Scenario: scenario_change with --debug
   * - Run scenario1 dataset (triggers snapshot update with stderr output)
   * - Expected: [SNAPSHOT] messages captured in debug output, no
   *   PHPUnit\Framework\Exception, files updated correctly.
   */
  public function testStderrNotMergedIntoStdout(): void {
    $this->setupTestProject('scenario_change');

    // Run with --debug to expose captured PHPUnit output.
    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      '--debug',
      'testSnapshot',
      'tests/snapshots',
      'scenario1',
    ]);

    $output = $this->processGet()->getOutput();

    $this->assertStringContainsString('[SNAPSHOT]', $output, 'stderr messages should be captured in debug output');

    // Stderr messages must not cause PHPUnit to wrap them as exceptions.
    $this->assertStringNotContainsString(
      Exception::class,
      $output,
      'stderr messages should not cause PHPUnit framework exceptions'
    );

    $scenario_file = $this->projectDir . '/tests/snapshots/scenario1/scenario_file.txt';
    $this->assertFileExists($scenario_file);

    $expected_file = $this->fixturesDir . '/scenario_change/expected/scenario1/scenario_file.txt';
    $this->assertFileEquals($expected_file, $scenario_file);
  }

  public function testNoChangeParallelJobs(): void {
    $this->setupTestProject('no_change');

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      '--jobs=2',
      'testSnapshot',
      'tests/snapshots',
    ]);

    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('Discovering datasets');
    $this->assertProcessOutputContains('Found');
    $this->assertProcessOutputContains('parallel: 2');

    $commit_count = $this->getCommitCount();
    $this->assertSame(1, $commit_count, 'Expected only initial commit');
  }

  public function testBaselineChangeParallelJobs(): void {
    $this->setupTestProject('baseline_change');

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      '--jobs=2',
      'testSnapshot',
      'tests/snapshots',
    ]);

    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('Discovering datasets');
    $this->assertProcessOutputContains('parallel: 2');

    $commit_count = $this->getCommitCount();
    $this->assertSame(2, $commit_count, 'Expected initial + update commit');

    $this->assertDirectoriesIdentical(
      $this->fixturesDir . '/baseline_change/expected/' . Snapshot::BASELINE_DIR,
      $this->projectDir . '/tests/snapshots/' . Snapshot::BASELINE_DIR
    );
  }

  public function testBothChangeParallelJobs(): void {
    $this->setupTestProject('both_change');

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      '--jobs=2',
      'testSnapshot',
      'tests/snapshots',
    ]);

    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('Discovering datasets');
    $this->assertProcessOutputContains('parallel: 2');

    $commit_count = $this->getCommitCount();
    $this->assertSame(2, $commit_count, 'Expected initial + update commit');

    $this->assertDirectoriesIdentical(
      $this->fixturesDir . '/both_change/expected/' . Snapshot::BASELINE_DIR,
      $this->projectDir . '/tests/snapshots/' . Snapshot::BASELINE_DIR
    );

    $scenario_path = $this->projectDir . '/tests/snapshots/scenario1';
    $scenario_files = array_diff(scandir($scenario_path), ['.', '..', '.gitkeep', '.ignorecontent']);
    $this->assertCount(0, $scenario_files, 'scenario1 should not contain any diff files');
  }

  public function testJobsOneSequential(): void {
    $this->setupTestProject('no_change');

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      '--jobs=1',
      'testSnapshot',
      'tests/snapshots',
    ]);

    $this->assertProcessSuccessful();
    $this->assertProcessOutputContains('parallel: 1');

    $commit_count = $this->getCommitCount();
    $this->assertSame(1, $commit_count, 'Expected only initial commit');
  }

  /**
   * Test that a genuine (non-updatable) failure exits non-zero.
   *
   * Scenario: genuine_failure
   * - Baseline matches actual (the baseline dataset passes).
   * - The 'failing' dataset fails for a non-snapshot reason, so no snapshot is
   *   updated and no completion marker is emitted.
   * - Expected: the script exits non-zero because the failure cannot be
   *   resolved by an update.
   */
  public function testGenuineFailureExitsNonZero(): void {
    $this->setupTestProject('genuine_failure');

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
    ]);

    // A genuine failure (no snapshot update) must exit non-zero.
    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Some tests failed');

    $commit_count = $this->getCommitCount();
    $this->assertSame(1, $commit_count, 'Genuine failure should not create a commit');
  }

  public function testGenuineFailureSpecifiedDatasetExitsNonZero(): void {
    $this->setupTestProject('genuine_failure');

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
      'failing',
    ]);

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Failed datasets: failing');
  }

  /**
   * Test a scenario-only update in all-datasets (parallel) mode exits 0.
   *
   * Scenario: scenario_only_change
   * - Baseline matches actual (the baseline dataset passes).
   * - scenario1 holds a stale diff, so the scenario dataset updates while
   *   running in the parallel phase.
   * - Expected: the script exits 0, the stale diff is reset, and the update is
   *   committed.
   */
  public function testScenarioOnlyChangeUpdatesInParallel(): void {
    $this->setupTestProject('scenario_only_change');

    // Sanity check: the stale diff file exists before the run.
    $stale_file = $this->projectDir . '/tests/snapshots/scenario1/extra.txt';
    $this->assertFileExists($stale_file);

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=60',
      'testSnapshot',
      'tests/snapshots',
    ]);

    // A scenario updated in parallel is a success, not a failure.
    $this->assertProcessSuccessful();

    $this->assertFileDoesNotExist($stale_file);

    $commit_count = $this->getCommitCount();
    $this->assertSame(2, $commit_count, 'Expected initial + update commit');

    $last_commit_message = $this->getLastCommitMessage();
    $this->assertStringContainsString('Updated', $last_commit_message);
  }

  /**
   * Test that a dataset retried after a timeout reports its attempt count.
   *
   * Scenario: slow
   * - The dataset sleeps far longer than the timeout, so every attempt is
   *   killed and the dataset is retried until the retry budget is spent.
   * - Expected: the result line names the attempt the dataset ended on, and
   *   the exhausted dataset is reported as failed.
   */
  public function testTimeoutRetriesReportAttemptCount(): void {
    $this->setupTestProject('slow');

    $this->processRun('php', [
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=1',
      '--retries=2',
      'testSnapshot',
      'tests/snapshots',
      'slow',
    ]);

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Running 1 specified dataset(s)');
    $this->assertProcessOutputContains('(attempt 2/2)');
    $this->assertProcessOutputContains('Timed out: 1');
    $this->assertProcessOutputContains('Failed datasets: slow');
  }

  /**
   * Test that a signal stops the script and every process it spawned.
   *
   * Scenario: slow
   * - The only dataset sleeps, so its PHPUnit process is still running when
   *   the script is signalled.
   * - Expected: the script reports the interruption, exits with the code for
   *   the signal, and leaves no spawned PHPUnit process behind.
   */
  #[DataProvider('dataProviderSignalStopsSpawnedProcesses')]
  public function testSignalStopsSpawnedProcesses(int $signal, int $expected_code): void {
    $this->setupTestProject('slow');

    // The project directory is unique per test, so a command line containing
    // it belongs to this run and to no other, and only dataset runs are passed
    // '--no-coverage', which keeps dataset discovery out of the match. The
    // bracket stops the pattern from matching the shell that runs pgrep.
    $marker = $this->projectDir . '/vendor/bin/[p]hpunit --no-coverage';

    $process = new Process([
      PHP_BINARY,
      $this->scriptPath,
      '--root=' . $this->projectDir,
      '--test-dir=tests',
      '--timeout=120',
      'testSnapshot',
      'tests/snapshots',
    ]);
    $process->setTimeout(120);
    $process->start();

    try {
      // The dataset writes this file just before it sleeps. Waiting for it
      // puts the signal after PHPUnit has written its startup output, so the
      // pipes the script closes on exit cannot be what ends the spawned run.
      $this->assertTrue(
        $this->waitForFile($this->projectDir . '/running.marker'),
        'Dataset did not start running before the signal was sent'
      );
      $this->assertNotEmpty($this->findProcesses($marker), 'No PHPUnit process was running when the signal was sent');

      $process->signal($signal);

      $deadline = microtime(TRUE) + self::WAIT_FOR_EXIT;
      while ($process->isRunning() && microtime(TRUE) < $deadline) {
        usleep(50000);
      }

      $this->assertFalse($process->isRunning(), 'Script did not exit after the signal');
      $this->assertSame($expected_code, $process->getExitCode(), 'Script did not report the exit code of the signal');
      $this->assertStringContainsString('Interrupted', $process->getOutput(), 'Script did not report the interruption');
      $this->assertSame([], $this->waitForProcessesGone($marker), 'Spawned PHPUnit processes outlived the script');
    }
    finally {
      $process->stop(0);
      exec(sprintf('pkill -f %s 2>/dev/null', escapeshellarg($marker)));
    }
  }

  /**
   * Data provider for signal handling.
   *
   * @return \Iterator<string, array{int, int}>
   *   Signal number and the exit code the script must report for it.
   */
  public static function dataProviderSignalStopsSpawnedProcesses(): \Iterator {
    yield 'SIGINT' => [2, 130];
    yield 'SIGTERM' => [15, 143];
  }

  /**
   * Wait for a file to appear.
   *
   * @param string $path
   *   Path to the file.
   *
   * @return bool
   *   TRUE if the file appeared before the wait ended.
   */
  protected function waitForFile(string $path): bool {
    $deadline = microtime(TRUE) + self::WAIT_FOR_FILE;

    do {
      clearstatcache(TRUE, $path);

      if (file_exists($path)) {
        return TRUE;
      }

      usleep(50000);
    } while (microtime(TRUE) < $deadline);

    return FALSE;
  }

  /**
   * Wait until no process matches a command line fragment.
   *
   * @param string $marker
   *   Command line fragment identifying the processes.
   *
   * @return array<string>
   *   Process IDs matching the fragment when the wait ended.
   */
  protected function waitForProcessesGone(string $marker): array {
    $deadline = microtime(TRUE) + self::WAIT_FOR_PROCESSES_GONE;

    do {
      $pids = $this->findProcesses($marker);

      if ($pids === []) {
        return $pids;
      }

      usleep(50000);
    } while (microtime(TRUE) < $deadline);

    return $pids;
  }

  /**
   * Find processes whose command line contains a fragment.
   *
   * @param string $marker
   *   Command line fragment identifying the processes.
   *
   * @return array<string>
   *   Process IDs matching the fragment.
   */
  protected function findProcesses(string $marker): array {
    $output = [];
    exec(sprintf('pgrep -f %s 2>/dev/null', escapeshellarg($marker)), $output);

    return array_values(array_filter(array_map(trim(...), $output), fn(string $pid): bool => $pid !== ''));
  }

  /**
   * Set up a test project by copying a complete scenario fixture.
   *
   * @param string $scenario
   *   Name of a fixture scenario directory under
   *   tests/phpunit/Fixtures/functional_update.
   */
  protected function setupTestProject(string $scenario): void {
    $scenario_dir = $this->fixturesDir . DIRECTORY_SEPARATOR . $scenario;
    File::copy($scenario_dir, $this->projectDir);

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

    $this->processCwd = $this->projectDir;
    $this->processRun('git', ['init']);
    $this->processRun('git', ['config', 'user.email', 'test@test.com']);
    $this->processRun('git', ['config', 'user.name', 'Test']);
    $this->processRun('git', ['add', '.']);
    $this->processRun('git', ['commit', '-m', 'Initial commit']);
  }

  protected function getCommitCount(): int {
    $this->processCwd = $this->projectDir;
    $this->processRun('git', ['rev-list', '--count', 'HEAD']);
    return (int) trim($this->processGet()->getOutput());
  }

  protected function getLastCommitMessage(): string {
    $this->processCwd = $this->projectDir;
    $this->processRun('git', ['log', '-1', '--format=%s']);
    return trim($this->processGet()->getOutput());
  }

}
