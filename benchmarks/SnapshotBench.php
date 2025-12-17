<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Benchmarks;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Snapshot;

/**
 * Benchmarks for Snapshot class operations.
 *
 * Tests performance of compare, diff, patch, and sync operations
 * across various directory sizes and content differences.
 */
class SnapshotBench {

  use BenchmarkDirectoryTrait;

  /**
   * Directory path for pre-created diff files.
   */
  protected string $diffDir = '';

  /**
   * Setup for identical directory comparison benchmark.
   */
  public function setUpIdentical(): void {
    $this->directoryInitialize();
    $this->directoryCreateIdentical(100, 3, 1024);
  }

  /**
   * Setup for content differences benchmark.
   */
  public function setUpContentDiffs(): void {
    $this->directoryInitialize();
    $this->directoryCreateIdentical(100, 3, 1024);
    $this->directoryCreateWithContentDiffs(20);
  }

  /**
   * Setup for structural differences benchmark.
   */
  public function setUpStructuralDiffs(): void {
    $this->directoryInitialize();
    $this->directoryCreateIdentical(100, 3, 1024);
    $this->directoryCreateWithStructuralDiffs(10, 10);
  }

  /**
   * Setup for patch benchmark.
   */
  public function setUpPatch(): void {
    $this->directoryInitialize();
    $this->directoryCreateIdentical(100, 3, 1024);
    $this->directoryCreateWithContentDiffs(20);

    // Create diff files first.
    $this->diffDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'diff';
    mkdir($this->diffDir, 0777, TRUE);
    Snapshot::diff($this->baselineDir, $this->actualDir, $this->diffDir);
  }

  /**
   * Setup for sync benchmark.
   */
  public function setUpSync(): void {
    $this->directoryInitialize();
    $this->directoryCreateStructure($this->baselineDir, 100, 3, 1024);
  }

  /**
   * Setup for large directory benchmark.
   */
  public function setUpLargeDirectory(): void {
    $this->directoryInitialize();
    $this->directoryCreateIdentical(500, 5, 4096);
    $this->directoryCreateWithContentDiffs(10);
  }

  /**
   * Teardown method - cleans up test files.
   */
  public function tearDown(): void {
    $this->directoryCleanup();
  }

  /**
   * Benchmark comparing identical directories.
   *
   * Tests baseline performance when directories are identical (no differences).
   *
   * @BeforeMethods("setUpIdentical")
   * @AfterMethods("tearDown")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(10)
   */
  public function benchCompareIdentical(): void {
    Snapshot::compare($this->baselineDir, $this->actualDir);
  }

  /**
   * Benchmark comparing directories with content differences.
   *
   * Tests performance with 20% of files having modified content.
   *
   * @BeforeMethods("setUpContentDiffs")
   * @AfterMethods("tearDown")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(10)
   */
  public function benchCompareContentDiffs(): void {
    Snapshot::compare($this->baselineDir, $this->actualDir);
  }

  /**
   * Benchmark comparing directories with structural differences.
   *
   * Tests performance with 10% files missing and 10% extra files.
   *
   * @BeforeMethods("setUpStructuralDiffs")
   * @AfterMethods("tearDown")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(10)
   */
  public function benchCompareStructuralDiffs(): void {
    Snapshot::compare($this->baselineDir, $this->actualDir);
  }

  /**
   * Benchmark creating diff files.
   *
   * Tests performance of generating diff files from content differences.
   *
   * @BeforeMethods("setUpContentDiffs")
   * @AfterMethods("tearDown")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(10)
   */
  public function benchDiff(): void {
    // Clean output dir for each run.
    if (is_dir($this->outputDir)) {
      File::rmdir($this->outputDir);
      mkdir($this->outputDir, 0777, TRUE);
    }
    Snapshot::diff($this->baselineDir, $this->actualDir, $this->outputDir);
  }

  /**
   * Benchmark applying patches.
   *
   * Tests performance of applying diff files to baseline.
   *
   * @BeforeMethods("setUpPatch")
   * @AfterMethods("tearDown")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(10)
   */
  public function benchPatch(): void {
    // Clean destination dir for each run.
    if (is_dir($this->destinationDir)) {
      File::rmdir($this->destinationDir);
    }
    Snapshot::patch($this->baselineDir, $this->diffDir, $this->destinationDir);
  }

  /**
   * Benchmark syncing directories.
   *
   * Tests performance of directory sync operation.
   *
   * @BeforeMethods("setUpSync")
   * @AfterMethods("tearDown")
   * @Revs(10)
   * @Warmup(2)
   * @Iterations(10)
   */
  public function benchSync(): void {
    // Clean destination dir for each run.
    if (is_dir($this->destinationDir)) {
      File::rmdir($this->destinationDir);
    }
    Snapshot::sync($this->baselineDir, $this->destinationDir);
  }

  /**
   * Benchmark comparing large directories.
   *
   * Tests performance with 500 files and 10% content differences.
   *
   * @BeforeMethods("setUpLargeDirectory")
   * @AfterMethods("tearDown")
   * @Revs(5)
   * @Warmup(1)
   * @Iterations(5)
   */
  public function benchCompareLargeDirectory(): void {
    Snapshot::compare($this->baselineDir, $this->actualDir);
  }

}
