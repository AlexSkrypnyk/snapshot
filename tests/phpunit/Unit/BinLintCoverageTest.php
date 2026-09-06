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
   * Patterns matching a path where each linter reads its analysis targets.
   *
   * A plain substring search would also be satisfied by a path that appears in
   * an exclusion, so each pattern is anchored to the syntax that declares a
   * target. The '%s' placeholder receives the quoted path.
   */
  protected const array LINTER_CONFIGS = [
    'phpcs.xml' => '#<file>%s</file>#',
    'phpstan.neon' => '#(?<!\w)paths:[ \t]*\n(?:[ \t]*-.*\n)*[ \t]*-[ \t]*%s[ \t]*\n#',
    'rector.php' => '#withPaths\(\[[^\]]*\'/%s\'#s',
  ];

  #[DataProvider('dataProviderBinScriptsAreAnalysed')]
  public function testBinScriptsAreAnalysed(string $config_file, string $pattern): void {
    $config = file_get_contents(self::$root . DIRECTORY_SEPARATOR . $config_file);
    $this->assertIsString($config, sprintf('Linter configuration %s is not readable.', $config_file));

    $declarations = self::stripComments($config_file, $config);

    $missing = array_values(array_filter(
      self::unscannableBinScripts(),
      static fn(string $path): bool => preg_match(sprintf($pattern, preg_quote($path, '#')), $declarations) !== 1
    ));

    $this->assertSame([], $missing, sprintf('%s does not declare every extensionless script in bin/ as an analysis target, so those scripts are skipped while the run still reports success.', $config_file));
  }

  public static function dataProviderBinScriptsAreAnalysed(): \Iterator {
    foreach (self::LINTER_CONFIGS as $config_file => $pattern) {
      yield $config_file => [$config_file, $pattern];
    }
  }

  /**
   * Remove every comment from a linter configuration.
   *
   * A commented-out path declares nothing, so it must not satisfy the patterns
   * the analysis targets are matched against.
   *
   * @param string $config_file
   *   Configuration file name, which selects the comment syntax.
   * @param string $config
   *   Configuration file contents.
   *
   * @return string
   *   Contents carrying only the active declarations.
   */
  protected static function stripComments(string $config_file, string $config): string {
    if (str_ends_with($config_file, '.xml')) {
      return (string) preg_replace('#<!--.*?-->#s', '', $config);
    }

    if (str_ends_with($config_file, '.neon')) {
      return (string) preg_replace('#^[ \t]*\#.*\n#m', '', $config);
    }

    $stripped = '';

    foreach (token_get_all($config) as $token) {
      if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], TRUE)) {
        continue;
      }

      $stripped .= is_array($token) ? $token[1] : $token;
    }

    return $stripped;
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
