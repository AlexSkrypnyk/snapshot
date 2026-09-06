<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that no script in 'bin/' drops out of static analysis unnoticed.
 *
 * PHPCS, PHPStan and Rector all filter a directory scan by file extension, so
 * an extensionless script listed only through its parent directory is skipped
 * while the linters still report success.
 */
#[CoversNothing]
final class BinLintCoverageTest extends UnitTestCase {

  /**
   * Extensions that a linter's directory scan discovers on its own.
   */
  protected const array SCANNED_EXTENSIONS = ['php', 'inc'];

  /**
   * Linter configuration files that declare the analysed paths.
   */
  protected const array LINTER_CONFIGS = ['phpcs.xml', 'phpstan.neon', 'rector.php'];

  #[DataProvider('dataProviderBinScriptsAreAnalysed')]
  public function testBinScriptsAreAnalysed(string $config_file): void {
    $config = file_get_contents(self::$root . DIRECTORY_SEPARATOR . $config_file);
    $this->assertIsString($config, sprintf('Linter configuration %s is not readable.', $config_file));

    $missing = array_values(array_filter(
      self::unscannableBinScripts(),
      static fn(string $path): bool => !str_contains($config, $path)
    ));

    $this->assertSame([], $missing, sprintf('%s does not name every extensionless script in bin/, so those scripts are skipped while the run still reports success.', $config_file));
  }

  public static function dataProviderBinScriptsAreAnalysed(): \Iterator {
    foreach (self::LINTER_CONFIGS as $config_file) {
      yield $config_file => [$config_file];
    }
  }

  /**
   * Get the scripts in 'bin/' that a linter's directory scan cannot discover.
   *
   * @return array<int, string>
   *   Repository-relative paths, empty when every script carries a scanned
   *   extension.
   */
  protected static function unscannableBinScripts(): array {
    $paths = glob(self::$root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . '*');

    if ($paths === FALSE) {
      return [];
    }

    $unscannable = [];

    foreach ($paths as $path) {
      if (!is_file($path) || in_array(pathinfo($path, PATHINFO_EXTENSION), self::SCANNED_EXTENSIONS, TRUE)) {
        continue;
      }

      $unscannable[] = 'bin/' . basename($path);
    }

    return $unscannable;
  }

}
