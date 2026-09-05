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

}
