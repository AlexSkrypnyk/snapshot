<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Functional;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Snapshot;
use AlexSkrypnyk\Snapshot\SnapshotTrait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Functional tests for SnapshotTrait auto-update functionality.
 *
 * These tests run PHPUnit in a subprocess to verify that the trait correctly
 * updates snapshots when tests fail due to directory comparison mismatches.
 */
#[CoversClass(SnapshotTrait::class)]
final class SnapshotTraitUpdateTest extends FunctionalTestCase {

  /**
   * Test that snapshotUpdateOnFailure() updates baseline when test fails.
   */
  public function testUpdateBaselineOnFailure(): void {
    // Create the test structure.
    $test_dir = self::$sut . DIRECTORY_SEPARATOR . 'test_project';
    File::mkdir($test_dir);

    // Create snapshots directory with baseline.
    $snapshots_dir = $test_dir . DIRECTORY_SEPARATOR . 'snapshots';
    $baseline_dir = $snapshots_dir . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR;
    File::mkdir($baseline_dir);
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "original content\n");
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file2.txt', "content 2\n");

    // Create actual output directory (different from baseline).
    $actual_dir = $test_dir . DIRECTORY_SEPARATOR . 'actual';
    File::mkdir($actual_dir);
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file1.txt', "modified content\n");
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file2.txt', "content 2\n");
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file3.txt', "new file\n");

    // Create a temporary test class that uses the trait.
    $test_class_content = $this->createTestClass($baseline_dir, $actual_dir);
    $test_class_file = $test_dir . DIRECTORY_SEPARATOR . 'BaselineUpdateTest.php';
    file_put_contents($test_class_file, $test_class_content);

    // Run PHPUnit without UPDATE_SNAPSHOTS - should fail.
    $this->processCwd = $test_dir;
    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
    );

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Differences between directories');

    // Verify baseline is unchanged.
    $this->assertFileExists($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt');
    $this->assertStringEqualsFile($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "original content\n");
    $this->assertFileDoesNotExist($baseline_dir . DIRECTORY_SEPARATOR . 'file3.txt');

    // Run PHPUnit with UPDATE_SNAPSHOTS=1 - should update baseline.
    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
      [],
      ['UPDATE_SNAPSHOTS' => '1'],
    );

    // The test still reports failure (because assertion failed), but baseline
    // should be updated.
    $this->assertProcessFailed();
    $this->assertProcessErrorOutputContains('[SNAPSHOT] Updating baseline');
    $this->assertProcessErrorOutputContains('[SNAPSHOT] Baseline updated');

    // Verify baseline was updated.
    $this->assertStringEqualsFile($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "modified content\n");
    $this->assertFileExists($baseline_dir . DIRECTORY_SEPARATOR . 'file3.txt');
    $this->assertStringEqualsFile($baseline_dir . DIRECTORY_SEPARATOR . 'file3.txt', "new file\n");

    // Run PHPUnit again without UPDATE_SNAPSHOTS - should now pass.
    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
    );

    $this->assertProcessSuccessful();
  }

  /**
   * Test that snapshotUpdateOnFailure() updates diffs when scenario test fails.
   */
  public function testUpdateDiffsOnFailure(): void {
    // Create the test structure.
    $test_dir = self::$sut . DIRECTORY_SEPARATOR . 'test_project_diffs';
    File::mkdir($test_dir);

    // Create snapshots directory with baseline.
    $snapshots_dir = $test_dir . DIRECTORY_SEPARATOR . 'snapshots';
    $baseline_dir = $snapshots_dir . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR;
    File::mkdir($baseline_dir);
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "original line 1\noriginal line 2\n");
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file2.txt', "content 2\n");

    // Create scenario diff directory (initially empty - no changes from
    // baseline).
    $scenario_dir = $snapshots_dir . DIRECTORY_SEPARATOR . 'scenario1';
    File::mkdir($scenario_dir);

    // Create actual output directory (different from baseline).
    $actual_dir = $test_dir . DIRECTORY_SEPARATOR . 'actual';
    File::mkdir($actual_dir);
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file1.txt', "modified line 1\noriginal line 2\n");
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file2.txt', "content 2\n");

    // Create a temporary test class that uses the trait for scenario testing.
    $test_class_content = $this->createScenarioTestClass($scenario_dir, $baseline_dir, $actual_dir);
    $test_class_file = $test_dir . DIRECTORY_SEPARATOR . 'ScenarioUpdateTest.php';
    file_put_contents($test_class_file, $test_class_content);

    // Run PHPUnit without UPDATE_SNAPSHOTS - should fail.
    $this->processCwd = $test_dir;
    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
    );

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Differences between directories');

    // Verify diffs directory is empty.
    $this->assertDirectoryExists($scenario_dir);
    $this->assertEmpty(glob($scenario_dir . DIRECTORY_SEPARATOR . '*'));

    // Run PHPUnit with UPDATE_SNAPSHOTS=1 - should update diffs.
    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
      [],
      ['UPDATE_SNAPSHOTS' => '1'],
    );

    // The test still reports failure, but diffs should be updated.
    $this->assertProcessFailed();
    $this->assertProcessErrorOutputContains('[SNAPSHOT] Updating diffs');
    $this->assertProcessErrorOutputContains('[SNAPSHOT] Diffs updated');

    // Verify diff file was created.
    $diff_file = $scenario_dir . DIRECTORY_SEPARATOR . 'file1.txt';
    $this->assertFileExists($diff_file);
    $diff_content = file_get_contents($diff_file);
    $this->assertIsString($diff_content);
    $this->assertStringContainsString('-original line 1', $diff_content);
    $this->assertStringContainsString('+modified line 1', $diff_content);

    // Run PHPUnit again without UPDATE_SNAPSHOTS - should now pass.
    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
    );

    $this->assertProcessSuccessful();
  }

  /**
   * Test that snapshotUpdateOnFailure() does nothing without env variable.
   */
  public function testNoUpdateWithoutEnvVariable(): void {
    // Create the test structure.
    $test_dir = self::$sut . DIRECTORY_SEPARATOR . 'test_project_no_update';
    File::mkdir($test_dir);

    // Create snapshots directory with baseline.
    $snapshots_dir = $test_dir . DIRECTORY_SEPARATOR . 'snapshots';
    $baseline_dir = $snapshots_dir . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR;
    File::mkdir($baseline_dir);
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "original content\n");

    // Create actual output directory (different from baseline).
    $actual_dir = $test_dir . DIRECTORY_SEPARATOR . 'actual';
    File::mkdir($actual_dir);
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file1.txt', "modified content\n");

    // Create a temporary test class that uses the trait.
    $test_class_content = $this->createTestClass($baseline_dir, $actual_dir);
    $test_class_file = $test_dir . DIRECTORY_SEPARATOR . 'NoUpdateTest.php';
    file_put_contents($test_class_file, $test_class_content);

    // Run PHPUnit multiple times without UPDATE_SNAPSHOTS.
    $this->processCwd = $test_dir;

    for ($i = 0; $i < 3; $i++) {
      $this->processRun(
        self::$root . '/vendor/bin/phpunit',
        ['--no-configuration', $test_class_file],
      );

      $this->assertProcessFailed();
      $this->assertProcessErrorOutputNotContains('[SNAPSHOT]');
    }

    // Verify baseline is unchanged.
    $this->assertStringEqualsFile($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "original content\n");
  }

  /**
   * Test that non-snapshot failures don't trigger updates.
   */
  public function testNoUpdateForNonSnapshotFailures(): void {
    // Create the test structure.
    $test_dir = self::$sut . DIRECTORY_SEPARATOR . 'test_project_non_snapshot';
    File::mkdir($test_dir);

    // Create snapshots directory with baseline.
    $snapshots_dir = $test_dir . DIRECTORY_SEPARATOR . 'snapshots';
    $baseline_dir = $snapshots_dir . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR;
    File::mkdir($baseline_dir);
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "content\n");

    // Create actual output directory (same as baseline).
    $actual_dir = $test_dir . DIRECTORY_SEPARATOR . 'actual';
    File::mkdir($actual_dir);
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file1.txt', "content\n");

    // Create a test class that fails for a non-snapshot reason.
    $test_class_content = $this->createNonSnapshotFailingTestClass($baseline_dir, $actual_dir);
    $test_class_file = $test_dir . DIRECTORY_SEPARATOR . 'NonSnapshotFailTest.php';
    file_put_contents($test_class_file, $test_class_content);

    // Run PHPUnit with UPDATE_SNAPSHOTS=1.
    $this->processCwd = $test_dir;
    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
      [],
      ['UPDATE_SNAPSHOTS' => '1'],
    );

    $this->assertProcessFailed();
    // Should not trigger snapshot update.
    $this->assertProcessErrorOutputNotContains('[SNAPSHOT]');
  }

  /**
   * Create test class content for baseline testing.
   *
   * @param string $baseline_dir
   *   Path to baseline directory.
   * @param string $actual_dir
   *   Path to actual directory.
   *
   * @return string
   *   PHP code for the test class.
   */
  protected function createTestClass(string $baseline_dir, string $actual_dir): string {
    $baseline_dir = addslashes($baseline_dir);
    $actual_dir = addslashes($actual_dir);

    return <<<PHP
<?php

declare(strict_types=1);

use AlexSkrypnyk\\Snapshot\\SnapshotTrait;
use PHPUnit\\Framework\\TestCase;

final class BaselineUpdateTest extends TestCase {

  use SnapshotTrait;

  protected string \$snapshots = '{$baseline_dir}';
  protected string \$actual = '{$actual_dir}';

  protected function tearDown(): void {
    \$this->snapshotUpdateOnFailure(\$this->snapshots, \$this->actual);
    parent::tearDown();
  }

  public function testDirectoriesMatch(): void {
    \$this->assertDirectoriesIdentical(\$this->snapshots, \$this->actual);
  }

}
PHP;
  }

  /**
   * Create test class content for scenario (diff) testing.
   *
   * @param string $scenario_dir
   *   Path to scenario diff directory.
   * @param string $baseline_dir
   *   Path to baseline directory.
   * @param string $actual_dir
   *   Path to actual directory.
   *
   * @return string
   *   PHP code for the test class.
   */
  protected function createScenarioTestClass(string $scenario_dir, string $baseline_dir, string $actual_dir): string {
    $scenario_dir = addslashes($scenario_dir);
    $baseline_dir = addslashes($baseline_dir);
    $actual_dir = addslashes($actual_dir);

    return <<<PHP
<?php

declare(strict_types=1);

use AlexSkrypnyk\\Snapshot\\SnapshotTrait;
use PHPUnit\\Framework\\TestCase;

final class ScenarioUpdateTest extends TestCase {

  use SnapshotTrait;

  protected string \$snapshots = '{$scenario_dir}';
  protected string \$baseline = '{$baseline_dir}';
  protected string \$actual = '{$actual_dir}';

  protected function tearDown(): void {
    \$this->snapshotUpdateOnFailure(\$this->snapshots, \$this->actual);
    parent::tearDown();
  }

  public function testScenarioMatch(): void {
    \$this->assertSnapshotMatchesBaseline(\$this->actual, \$this->baseline, \$this->snapshots);
  }

}
PHP;
  }

  /**
   * Create test class that fails for non-snapshot reasons.
   *
   * @param string $baseline_dir
   *   Path to baseline directory.
   * @param string $actual_dir
   *   Path to actual directory.
   *
   * @return string
   *   PHP code for the test class.
   */
  protected function createNonSnapshotFailingTestClass(string $baseline_dir, string $actual_dir): string {
    $baseline_dir = addslashes($baseline_dir);
    $actual_dir = addslashes($actual_dir);

    return <<<PHP
<?php

declare(strict_types=1);

use AlexSkrypnyk\\Snapshot\\SnapshotTrait;
use PHPUnit\\Framework\\TestCase;

final class NonSnapshotFailTest extends TestCase {

  use SnapshotTrait;

  protected string \$snapshots = '{$baseline_dir}';
  protected string \$actual = '{$actual_dir}';

  protected function tearDown(): void {
    \$this->snapshotUpdateOnFailure(\$this->snapshots, \$this->actual);
    parent::tearDown();
  }

  public function testNonSnapshotFailure(): void {
    // This fails, but not due to directory comparison.
    \$this->assertTrue(false, 'This is a non-snapshot failure');
  }

}
PHP;
  }

}
