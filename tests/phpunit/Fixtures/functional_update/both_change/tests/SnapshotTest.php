<?php

declare(strict_types=1);

namespace Test;

use AlexSkrypnyk\Snapshot\SnapshotTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test class with data provider for update-snapshots script testing.
 */
final class SnapshotTest extends TestCase {

  use SnapshotTrait;

  /**
   * Current dataset being tested.
   */
  protected string $currentDataset = '';

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // For baseline, pass the _baseline directory.
    // For scenarios, pass the scenario directory.
    if ($this->currentDataset === 'baseline') {
      $snapshot_path = $this->getBaselineDir();
    }
    else {
      $snapshot_path = $this->getSnapshotsDir() . '/' . $this->currentDataset;
    }

    $this->snapshotUpdateOnFailure($snapshot_path, $this->getActualDir());
    parent::tearDown();
  }

  /**
   * Test snapshot matching.
   */
  #[DataProvider('dataProviderSnapshot')]
  public function testSnapshot(string $dataset): void {
    // Store current dataset for tearDown.
    $this->currentDataset = $dataset;

    $baseline_dir = $this->getBaselineDir();

    if ($dataset === 'baseline') {
      $this->assertDirectoriesIdentical($baseline_dir, $this->getActualDir());
    }
    else {
      $scenario_dir = $this->getSnapshotsDir() . '/' . $dataset;
      $this->assertSnapshotMatchesBaseline($this->getActualDir(), $baseline_dir, $scenario_dir);
    }
  }

  /**
   * Data provider for snapshot tests.
   *
   * @return \Iterator<string, array{string}>
   *   Array of dataset names.
   */
  public static function dataProviderSnapshot(): \Iterator {
    yield 'baseline' => ['baseline'];
    yield 'scenario1' => ['scenario1'];
  }

  /**
   * Get snapshots directory path.
   */
  protected function getSnapshotsDir(): string {
    return dirname(__DIR__) . '/tests/snapshots';
  }

  /**
   * Get baseline directory path.
   */
  protected function getBaselineDir(): string {
    return $this->getSnapshotsDir() . '/_baseline';
  }

  /**
   * Get actual output directory path.
   */
  protected function getActualDir(): string {
    return dirname(__DIR__) . '/actual';
  }

}
