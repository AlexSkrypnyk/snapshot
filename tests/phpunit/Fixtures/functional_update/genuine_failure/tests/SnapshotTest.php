<?php

declare(strict_types=1);

namespace Test;

use AlexSkrypnyk\Snapshot\Testing\SnapshotTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test class with a genuine, non-snapshot failure.
 *
 * The 'failing' dataset fails for a reason unrelated to directory comparison,
 * so SnapshotTrait emits no completion marker and the update-snapshots script
 * must treat it as a real failure (non-zero exit).
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
    $this->currentDataset = $dataset;

    if ($dataset === 'failing') {
      // Genuine, non-snapshot failure: the message is not a snapshot update
      // trigger, so no snapshot is updated and no completion marker is emitted.
      $this->fail('Intentional non-snapshot failure for exit-code testing.');
    }

    $this->assertDirectoriesIdentical($this->getBaselineDir(), $this->getActualDir());
  }

  /**
   * Data provider for snapshot tests.
   *
   * @return \Iterator<string, array{string}>
   *   Array of dataset names.
   */
  public static function dataProviderSnapshot(): \Iterator {
    yield 'baseline' => ['baseline'];
    yield 'failing' => ['failing'];
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
