<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Benchmarks;

use AlexSkrypnyk\File\File;

/**
 * Trait for common benchmark directory operations.
 *
 * Provides helper methods for creating test directory structures
 * used across multiple benchmark classes.
 */
trait BenchmarkDirectoryTrait {

  /**
   * Temporary directory for test data.
   */
  protected string $tmpDir = '';

  /**
   * Baseline directory path.
   */
  protected string $baselineDir = '';

  /**
   * Actual directory path.
   */
  protected string $actualDir = '';

  /**
   * Output directory path (for diff/patch files).
   */
  protected string $outputDir = '';

  /**
   * Destination directory path (for patch results).
   */
  protected string $destinationDir = '';

  /**
   * Initialize directory structure.
   *
   * Creates temporary baseline, actual, output, and destination directories.
   */
  protected function directoryInitialize(): void {
    $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('snapshot_bench_', TRUE);
    mkdir($this->tmpDir, 0777, TRUE);

    $this->baselineDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'baseline';
    $this->actualDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'actual';
    $this->outputDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'output';
    $this->destinationDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'destination';

    mkdir($this->baselineDir, 0777, TRUE);
    mkdir($this->actualDir, 0777, TRUE);
    mkdir($this->outputDir, 0777, TRUE);
  }

  /**
   * Clean up test directories.
   */
  protected function directoryCleanup(): void {
    if (is_dir($this->tmpDir)) {
      File::rmdir($this->tmpDir);
    }
  }

  /**
   * Create directory structure with files.
   *
   * @param string $target_dir
   *   Target directory to create files in.
   * @param int $file_count
   *   Number of files to create. Default: 100.
   * @param int $directory_depth
   *   Depth of nested directory structure. Default: 3.
   * @param int $file_size
   *   Approximate size of each file in bytes. Default: 1024.
   */
  protected function directoryCreateStructure(string $target_dir, int $file_count = 100, int $directory_depth = 3, int $file_size = 1024): void {
    $files_per_level = (int) ceil($file_count / $directory_depth);
    $file_counter = 1;

    for ($level = 1; $level <= $directory_depth; $level++) {
      // Build nested path: level_1/level_2/level_3/etc.
      $nested_path = $target_dir;
      for ($i = 1; $i <= $level; $i++) {
        $nested_path .= DIRECTORY_SEPARATOR . ('level_' . $i);
      }
      mkdir($nested_path, 0777, TRUE);

      for ($file_in_level = 1; $file_in_level <= $files_per_level && $file_counter <= $file_count; $file_in_level++) {
        $content = sprintf("File %d content\n", $file_counter);
        $content .= sprintf("Level: %d\n", $level);
        $content .= "Some common text that appears in all files.\n";

        // Pad to reach target size.
        if (strlen($content) < $file_size) {
          $padding = str_repeat("Line of text to fill the file.\n", (int) ceil(($file_size - strlen($content)) / 30));
          $content .= $padding;
          $content = substr($content, 0, $file_size);
        }

        $filename = sprintf('file_%d.txt', $file_counter);
        file_put_contents($nested_path . DIRECTORY_SEPARATOR . $filename, $content);
        $file_counter++;
      }
    }
  }

  /**
   * Create identical directories for baseline comparison.
   *
   * @param int $file_count
   *   Number of files to create. Default: 100.
   * @param int $directory_depth
   *   Depth of nested directory structure. Default: 3.
   * @param int $file_size
   *   Approximate size of each file in bytes. Default: 1024.
   */
  protected function directoryCreateIdentical(int $file_count = 100, int $directory_depth = 3, int $file_size = 1024): void {
    $this->directoryCreateStructure($this->baselineDir, $file_count, $directory_depth, $file_size);
    $this->directoryCreateStructure($this->actualDir, $file_count, $directory_depth, $file_size);
  }

  /**
   * Create directories with content differences.
   *
   * @param int $percent_changed
   *   Percentage of files to modify (0-100).
   */
  protected function directoryCreateWithContentDiffs(int $percent_changed): void {
    $files = File::scandirRecursive($this->actualDir);
    $files_to_change = (int) ceil(count($files) * ($percent_changed / 100));

    shuffle($files);
    $files_to_change_list = array_slice($files, 0, $files_to_change);

    foreach ($files_to_change_list as $file) {
      $content = file_get_contents($file);
      if ($content !== FALSE) {
        $modified_content = $content . "\nMODIFIED CONTENT\n";
        file_put_contents($file, $modified_content);
      }
    }
  }

  /**
   * Create directories with structural differences (missing/extra files).
   *
   * @param int $percent_removed
   *   Percentage of files to remove from actual. Default: 10.
   * @param int $percent_added
   *   Percentage of extra files to add to actual. Default: 10.
   */
  protected function directoryCreateWithStructuralDiffs(int $percent_removed = 10, int $percent_added = 10): void {
    $files = File::scandirRecursive($this->actualDir);
    $files_count = count($files);

    // Remove some files.
    $files_to_remove = (int) ceil($files_count * ($percent_removed / 100));
    shuffle($files);
    $files_to_remove_list = array_slice($files, 0, $files_to_remove);

    foreach ($files_to_remove_list as $file) {
      unlink($file);
    }

    // Add extra files.
    $files_to_add = (int) ceil($files_count * ($percent_added / 100));
    $dirs = glob($this->actualDir . '/level_*', GLOB_ONLYDIR);

    for ($i = 1; $i <= $files_to_add; $i++) {
      $target_dir = empty($dirs) ? $this->actualDir : $dirs[array_rand($dirs)];
      $extra_file = $target_dir . DIRECTORY_SEPARATOR . sprintf('extra_file_%d.txt', $i);
      file_put_contents($extra_file, "Extra file {$i} content\n");
    }
  }

}
