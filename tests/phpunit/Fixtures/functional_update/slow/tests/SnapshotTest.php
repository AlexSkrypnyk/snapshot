<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test class whose only dataset runs longer than the script lets it.
 *
 * The sleep outlasts both the signal sent by the interruption test and the
 * short timeout used by the retry test, so the run is always ended from the
 * outside. It writes a marker file first: the file name is shared with the
 * functional test, which waits for it before signalling.
 */
final class SnapshotTest extends TestCase {

  /**
   * Test snapshot matching.
   */
  #[DataProvider('dataProviderSnapshot')]
  public function testSnapshot(string $dataset): void {
    file_put_contents(dirname(__DIR__) . '/running.marker', $dataset);

    sleep(60);

    $this->assertSame('slow', $dataset);
  }

  /**
   * Data provider for snapshot tests.
   *
   * @return \Iterator<string, array{string}>
   *   Array of dataset names.
   */
  public static function dataProviderSnapshot(): \Iterator {
    yield 'slow' => ['slow'];
  }

}
