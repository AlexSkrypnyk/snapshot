<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Testing;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Exception\PatchException;
use AlexSkrypnyk\Snapshot\Replacer\Replacer;
use AlexSkrypnyk\Snapshot\Snapshot;
use PHPUnit\Framework\TestStatus\Error;
use PHPUnit\Framework\TestStatus\Failure;

/**
 * PHPUnit trait for directory snapshot testing.
 *
 * Provides assertions for comparing directories and automatic snapshot updates
 * when tests fail due to directory comparison mismatches.
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait SnapshotTrait {

  /**
   * Environment variable to trigger snapshot updates.
   */
  protected static string $snapshotUpdateEnvVar = 'UPDATE_SNAPSHOTS';

  /**
   * Error messages that trigger snapshot updates.
   *
   * @var array<int, string>
   */
  protected static array $snapshotUpdateTriggers = [
    'Differences between directories',
    'Failed to apply patch',
  ];

  /**
   * Assert that two directories have identical structure and content.
   *
   * @param string $dir1
   *   First directory path to compare.
   * @param string $dir2
   *   Second directory path to compare.
   * @param string|null $message
   *   Optional custom failure message.
   * @param callable|null $match_content
   *   Optional callback to process file content before comparison.
   * @param bool $show_diff
   *   Whether to include diff output in failure messages.
   */
  public function assertDirectoriesIdentical(string $dir1, string $dir2, ?string $message = NULL, ?callable $match_content = NULL, bool $show_diff = TRUE): void {
    $text = Snapshot::compare($dir1, $dir2, NULL, $match_content)->render(['show_diff' => $show_diff]);
    if (!empty($text)) {
      $this->fail($message ? $message . PHP_EOL . $text : $text);
    }
    $this->addToAssertionCount(1);
  }

  /**
   * Assert that a directory is equal to the patched baseline (baseline + diff).
   *
   * This method applies patch files to a baseline directory and then compares
   * the resulting directory with an actual directory to verify they match.
   *
   * @param string $actual
   *   Actual directory path to compare.
   * @param string $baseline
   *   Baseline directory path.
   * @param string $diffs
   *   Directory containing diff/patch files to apply to the baseline.
   * @param string|null $expected
   *   Optional path where to create the expected directory. If not provided,
   *   a '.expected' directory will be created next to the baseline.
   * @param string|null $message
   *   Optional custom failure message.
   */
  public function assertSnapshotMatchesBaseline(string $actual, string $baseline, string $diffs, ?string $expected = NULL, ?string $message = NULL): void {
    if (!is_dir($baseline)) {
      $this->fail($message ?: sprintf('The baseline directory does not exist: %s', $baseline));
    }

    // We use the .expected dir to easily assess the combined expected snapshot.
    $expected = $expected ?: File::realpath($baseline . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.expected');
    File::rmdir($expected);

    try {
      Snapshot::patch($baseline, $diffs, $expected);
    }
    catch (PatchException $patch_exception) {
      $this->fail($message ?: sprintf('Failed to apply patch: %s', $patch_exception->getMessage()));
    }

    // Do not override .ignorecontent file from the baseline directory.
    if (file_exists($baseline . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT)) {
      File::copy($baseline . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT, $expected . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT);
    }

    $this->assertDirectoriesIdentical($expected, $actual, $message);
  }

  /**
   * Update snapshots if test failed due to directory comparison.
   *
   * Call in tearDown() before parent::tearDown().
   *
   * @param string $snapshots
   *   Current test's snapshot directory.
   * @param string $actual
   *   Actual output directory (SUT).
   * @param string|null $tmp
   *   Optional temp directory.
   *
   * @codeCoverageIgnoreStart
   */
  protected function snapshotUpdateOnFailure(string $snapshots, string $actual, ?string $tmp = NULL): void {
    if (!$this->snapshotShouldUpdate($snapshots)) {
      return;
    }

    $tmp ??= sys_get_temp_dir();
    $baseline = Snapshot::getBaselinePath($snapshots);

    // Hook for preprocessing.
    $this->snapshotUpdateBefore($actual);

    if (Snapshot::isBaseline($snapshots)) {
      $this->snapshotUpdateBaseline($baseline, $actual, $tmp);
    }
    else {
      $this->snapshotUpdateDiffs($baseline, $snapshots, $actual, $tmp);
    }
  }

  /**
   * Check if snapshot update should run.
   *
   * @param string $snapshots
   *   Path to the snapshots directory.
   *
   * @return bool
   *   TRUE if snapshot update should run.
   *
   * @codeCoverageIgnoreStart
   */
  protected function snapshotShouldUpdate(string $snapshots): bool {
    if (!getenv(static::$snapshotUpdateEnvVar)) {
      return FALSE;
    }

    $status = $this->status();
    if (!($status instanceof Failure) && !($status instanceof Error)) {
      return FALSE;
    }

    foreach (static::$snapshotUpdateTriggers as $snapshot_update_trigger) {
      if (str_contains($status->message(), $snapshot_update_trigger)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Update baseline snapshot.
   *
   * @param string $baseline
   *   Path to the baseline directory.
   * @param string $actual
   *   Path to the actual output directory.
   * @param string $tmp
   *   Path to the temp directory.
   *
   * @codeCoverageIgnoreStart
   */
  protected function snapshotUpdateBaseline(string $baseline, string $actual, string $tmp): void {
    fwrite(STDERR, PHP_EOL . '[SNAPSHOT] Updating baseline' . PHP_EOL);

    $ic = Snapshot::IGNORECONTENT;
    File::copyIfExists($baseline . '/' . $ic, $actual . '/' . $ic);
    File::copyIfExists($baseline . '/' . $ic, $tmp . '/' . $ic);

    File::rmdir($baseline);
    Snapshot::sync($actual, $baseline);

    File::copyIfExists($tmp . '/' . $ic, $baseline . '/' . $ic);

    fwrite(STDERR, '[SNAPSHOT] Baseline updated' . PHP_EOL);
  }

  /**
   * Update scenario snapshot (diff from baseline).
   *
   * @param string $baseline
   *   Path to the baseline directory.
   * @param string $snapshots
   *   Path to the snapshots directory.
   * @param string $actual
   *   Path to the actual output directory.
   * @param string $tmp
   *   Path to the temp directory.
   *
   * @codeCoverageIgnoreEnd
   */
  protected function snapshotUpdateDiffs(string $baseline, string $snapshots, string $actual, string $tmp): void {
    fwrite(STDERR, PHP_EOL . '[SNAPSHOT] Updating diffs' . PHP_EOL);

    $ic = Snapshot::IGNORECONTENT;
    File::copyIfExists($snapshots . '/' . $ic, $tmp . '/' . $ic);

    File::rmdir($snapshots);
    Snapshot::diff($baseline, $actual, $snapshots);

    File::copyIfExists($tmp . '/' . $ic, $snapshots . '/' . $ic);

    fwrite(STDERR, '[SNAPSHOT] Diffs updated' . PHP_EOL);
  }

  /**
   * Hook: Called before snapshot update.
   *
   * By default, applies version normalization patterns to replace volatile
   * content (version numbers, hashes, etc.) with placeholders.
   *
   * Override to customize preprocessing. Call parent::snapshotUpdateBefore()
   * to keep default behavior, or replace entirely with custom logic.
   *
   * @param string $actual
   *   Path to the actual output directory.
   *
   * @codeCoverageIgnoreStart
   */
  protected function snapshotUpdateBefore(string $actual): void {
    Replacer::versions()->replaceInDir($actual);
  }

}
