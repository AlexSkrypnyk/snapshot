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
 *
 * For snapshot update functionality to work, tests should call
 * snapshotUpdateOnFailure() in tearDown() with the fixture and actual
 * output directories. Set the UPDATE_SNAPSHOTS environment variable to enable
 * auto-updates.
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
