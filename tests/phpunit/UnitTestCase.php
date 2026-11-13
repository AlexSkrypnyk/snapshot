<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests;

use AlexSkrypnyk\PhpunitHelpers\UnitTestCase as BaseUnitTestCase;
use AlexSkrypnyk\Snapshot\Testing\SnapshotTrait;

/**
 * Base unit test case for snapshot package tests.
 *
 * This base class uses SnapshotTrait to demonstrate usage of this package's
 * functionality in testing.
 */
abstract class UnitTestCase extends BaseUnitTestCase {

  use SnapshotTrait;

  /**
   * {@inheritdoc}
   */
  public static function locationsFixturesDir(): string {
    return 'tests/phpunit/Fixtures';
  }

  /**
   * Creates a symlink with a relative target.
   *
   * PHP on Windows resolves a relative target against the current directory
   * and does not resolve one written with forward slashes, so the link is
   * created from its own directory with native separators.
   *
   * @param string $link
   *   Symlink path.
   * @param string $target
   *   Target relative to the symlink's directory, with forward slashes.
   */
  protected function createRelativeSymlink(string $link, string $target): void {
    $current_dir = (string) getcwd();

    chdir(dirname($link));
    $created = symlink(str_replace('/', DIRECTORY_SEPARATOR, $target), basename($link));
    chdir($current_dir);

    $this->assertTrue($created, sprintf('Created symlink %s -> %s', $link, $target));
  }

}
