<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test class whose only dataset outlives the signal sent to the script.
 *
 * The update-snapshots script must terminate this run when it is signalled,
 * so the test sleeps long enough to still be alive at that point. It writes a
 * marker file first: the file name is shared with the functional test, which
 * waits for it before signalling.
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
