<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Compare\Comparer;
use AlexSkrypnyk\Snapshot\Compare\Diff;
use AlexSkrypnyk\Snapshot\Index\Index;
use AlexSkrypnyk\Snapshot\Index\Rules;
use AlexSkrypnyk\Snapshot\Patch\Patcher;
use AlexSkrypnyk\Snapshot\Sync\Syncer;

/**
 * Directory snapshot operations.
 *
 * Provides functionality for creating, comparing, and applying
 * directory snapshots using a baseline + diff architecture.
 */
class Snapshot {

  /**
   * Default directory name for baseline snapshots.
   */
  public const BASELINE_DIR = '_baseline';

  /**
   * Filename for ignore content rules.
   */
  public const IGNORECONTENT = '.ignorecontent';

  /**
   * Create a new snapshot index of a directory.
   *
   * @param string $directory
   *   Directory to snapshot.
   * @param \AlexSkrypnyk\Snapshot\Index\Rules|null $rules
   *   Optional comparison rules.
   * @param callable|null $content_processor
   *   Optional callback to process file content before indexing.
   *
   * @return \AlexSkrypnyk\Snapshot\Index\Index
   *   The directory index.
   */
  public static function of(string $directory, ?Rules $rules = NULL, ?callable $content_processor = NULL): Index {
    return new Index($directory, $rules, $content_processor);
  }

  /**
   * Compare two directories and return comparison result.
   *
   * @param string $baseline
   *   Baseline directory path (expected).
   * @param string $actual
   *   Actual directory path.
   * @param \AlexSkrypnyk\Snapshot\Index\Rules|null $rules
   *   Optional comparison rules.
   * @param callable|null $content_processor
   *   Optional callback to process content before comparison.
   *
   * @return \AlexSkrypnyk\Snapshot\Compare\Comparer
   *   Comparison result object.
   */
  public static function compare(string $baseline, string $actual, ?Rules $rules = NULL, ?callable $content_processor = NULL): Comparer {
    $baseline_index = new Index($baseline, $rules, $content_processor);

    File::mkdir($actual);
    $actual_index = new Index($actual, $rules ?: $baseline_index->getRules(), $content_processor);

    return (new Comparer($baseline_index, $actual_index))->compare();
  }

  /**
   * Create diff files between baseline and actual directories.
   *
   * @param string $baseline
   *   Baseline directory path.
   * @param string $actual
   *   Actual directory path.
   * @param string $output
   *   Directory to write diff files to.
   * @param callable|null $content_processor
   *   Optional callback to process content before diffing.
   */
  public static function diff(string $baseline, string $actual, string $output, ?callable $content_processor = NULL): void {
    File::mkdir($output);

    $comparer = self::compare($baseline, $actual, NULL, $content_processor);

    $absent_left = $comparer->getAbsentLeftDiffs();
    $absent_right = $comparer->getAbsentRightDiffs();
    $content_diffs = $comparer->getContentDiffs();

    if (empty($absent_left) && empty($absent_right) && empty($content_diffs)) {
      return;
    }

    // Files in actual but not in baseline - copy to output.
    foreach (array_keys($absent_left) as $file) {
      $src = $actual . DIRECTORY_SEPARATOR . $file;
      $dst = $output . DIRECTORY_SEPARATOR . $file;
      File::mkdir(dirname($dst));
      File::copy($src, $dst);
    }

    // Files in baseline but not in actual - create deletion marker.
    foreach (array_keys($absent_right) as $file) {
      $dst = $output . DIRECTORY_SEPARATOR . dirname((string) $file);
      File::mkdir($dst);
      File::dump($dst . DIRECTORY_SEPARATOR . '-' . basename((string) $file), '');
    }

    // Files with content differences - save diff.
    foreach ($content_diffs as $file => $diff) {
      if ($diff instanceof Diff) {
        $rendered = $diff->render();
        if ($rendered !== NULL) {
          File::dump($output . DIRECTORY_SEPARATOR . $file, $rendered);
        }
      }
    }
  }

  /**
   * Apply patches to baseline and write to destination.
   *
   * @param string $baseline
   *   Baseline directory path.
   * @param string $patches
   *   Directory containing patch/diff files.
   * @param string $destination
   *   Destination directory for patched output.
   * @param callable|null $content_processor
   *   Optional callback to process content before patching.
   */
  public static function patch(string $baseline, string $patches, string $destination, ?callable $content_processor = NULL): void {
    File::mkdir($destination);

    self::sync($baseline, $destination);

    $patcher = new Patcher($baseline, $destination);

    $patch_index = self::of($patches);
    foreach ($patch_index->getFiles() as $file) {
      $basename = $file->getBasename();
      $relative_path = $file->getPathnameFromBasepath();

      if (str_starts_with($basename, '-')) {
        // Deletion marker - remove file from destination.
        $target = $destination . DIRECTORY_SEPARATOR . $file->getPathFromBasepath() . DIRECTORY_SEPARATOR . substr($basename, 1);
        File::remove($target);
      }
      elseif (!Patcher::isPatchFile($file->getPathname())) {
        // New file - copy to destination.
        File::copy($file->getPathname(), $destination . DIRECTORY_SEPARATOR . $relative_path);
      }
      else {
        // Patch file - queue for patching.
        $patcher->addPatchFile($file);
      }
    }

    $patcher->patch();
  }

  /**
   * Sync source directory to destination.
   *
   * @param string $source
   *   Source directory path.
   * @param string $destination
   *   Destination directory path.
   * @param int $permissions
   *   Directory permissions.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   */
  public static function sync(string $source, string $destination, int $permissions = 0755, bool $copy_empty_dirs = FALSE): void {
    $index = self::of($source);
    (new Syncer($index))->sync($destination, $permissions, $copy_empty_dirs);
  }

  /**
   * Get the baseline directory path from a snapshot path.
   *
   * @param string $snapshot_path
   *   Path to a snapshot directory.
   *
   * @return string
   *   Path to the baseline directory.
   */
  public static function getBaselinePath(string $snapshot_path): string {
    return File::dir($snapshot_path . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . self::BASELINE_DIR);
  }

  /**
   * Check if a path is a baseline directory.
   *
   * @param string $path
   *   Path to check.
   *
   * @return bool
   *   TRUE if path is a baseline directory.
   */
  public static function isBaseline(string $path): bool {
    return str_contains($path, DIRECTORY_SEPARATOR . self::BASELINE_DIR);
  }

}
