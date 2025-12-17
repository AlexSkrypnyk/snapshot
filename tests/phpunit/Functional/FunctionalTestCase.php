<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Functional;

use AlexSkrypnyk\PhpunitHelpers\Traits\EnvTrait;
use AlexSkrypnyk\PhpunitHelpers\Traits\ProcessTrait;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;

/**
 * Base functional test case for snapshot package tests.
 *
 * Extends UnitTestCase and adds ProcessTrait for subprocess testing.
 */
abstract class FunctionalTestCase extends UnitTestCase {

  use EnvTrait;
  use ProcessTrait;

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->processTearDown();
    parent::tearDown();
  }

}
