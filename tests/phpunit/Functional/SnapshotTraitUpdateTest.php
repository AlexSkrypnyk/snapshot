<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Functional;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Snapshot;
use AlexSkrypnyk\Snapshot\Testing\SnapshotTrait;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SnapshotTrait::class)]
final class SnapshotTraitUpdateTest extends FunctionalTestCase {

  public function testUpdateBaselineOnFailure(): void {
    $test_dir = self::$sut . DIRECTORY_SEPARATOR . 'test_project';
    File::mkdir($test_dir);

    $snapshots_dir = $test_dir . DIRECTORY_SEPARATOR . 'snapshots';
    $baseline_dir = $snapshots_dir . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR;
    File::mkdir($baseline_dir);
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "original content\n");
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file2.txt', "content 2\n");

    $actual_dir = $test_dir . DIRECTORY_SEPARATOR . 'actual';
    File::mkdir($actual_dir);
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file1.txt', "modified content\n");
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file2.txt', "content 2\n");
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file3.txt', "new file\n");

    $test_class_content = $this->createTestClass($baseline_dir, $actual_dir);
    $test_class_file = $test_dir . DIRECTORY_SEPARATOR . 'BaselineUpdateTest.php';
    file_put_contents($test_class_file, $test_class_content);

    $this->processCwd = $test_dir;
    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
    );

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Differences between directories');

    $this->assertFileExists($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt');
    $this->assertStringEqualsFile($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "original content\n");
    $this->assertFileDoesNotExist($baseline_dir . DIRECTORY_SEPARATOR . 'file3.txt');

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

    $this->assertStringEqualsFile($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "modified content\n");
    $this->assertFileExists($baseline_dir . DIRECTORY_SEPARATOR . 'file3.txt');
    $this->assertStringEqualsFile($baseline_dir . DIRECTORY_SEPARATOR . 'file3.txt', "new file\n");

    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
    );

    $this->assertProcessSuccessful();
  }

  public function testUpdateDiffsOnFailure(): void {
    $test_dir = self::$sut . DIRECTORY_SEPARATOR . 'test_project_diffs';
    File::mkdir($test_dir);

    $snapshots_dir = $test_dir . DIRECTORY_SEPARATOR . 'snapshots';
    $baseline_dir = $snapshots_dir . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR;
    File::mkdir($baseline_dir);
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "original line 1\noriginal line 2\n");
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file2.txt', "content 2\n");

    // The scenario diff directory starts empty: no changes from the baseline.
    $scenario_dir = $snapshots_dir . DIRECTORY_SEPARATOR . 'scenario1';
    File::mkdir($scenario_dir);

    $actual_dir = $test_dir . DIRECTORY_SEPARATOR . 'actual';
    File::mkdir($actual_dir);
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file1.txt', "modified line 1\noriginal line 2\n");
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file2.txt', "content 2\n");

    $test_class_content = $this->createScenarioTestClass($scenario_dir, $baseline_dir, $actual_dir);
    $test_class_file = $test_dir . DIRECTORY_SEPARATOR . 'ScenarioUpdateTest.php';
    file_put_contents($test_class_file, $test_class_content);

    $this->processCwd = $test_dir;
    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
    );

    $this->assertProcessFailed();
    $this->assertProcessOutputContains('Differences between directories');

    $this->assertDirectoryExists($scenario_dir);
    $this->assertEmpty(glob($scenario_dir . DIRECTORY_SEPARATOR . '*'));

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

    $diff_file = $scenario_dir . DIRECTORY_SEPARATOR . 'file1.txt';
    $this->assertFileExists($diff_file);
    $diff_content = file_get_contents($diff_file);
    $this->assertIsString($diff_content);
    $this->assertStringContainsString('-original line 1', $diff_content);
    $this->assertStringContainsString('+modified line 1', $diff_content);

    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
    );

    $this->assertProcessSuccessful();
  }

  public function testNoUpdateWithoutEnvVariable(): void {
    $test_dir = self::$sut . DIRECTORY_SEPARATOR . 'test_project_no_update';
    File::mkdir($test_dir);

    $snapshots_dir = $test_dir . DIRECTORY_SEPARATOR . 'snapshots';
    $baseline_dir = $snapshots_dir . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR;
    File::mkdir($baseline_dir);
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "original content\n");

    $actual_dir = $test_dir . DIRECTORY_SEPARATOR . 'actual';
    File::mkdir($actual_dir);
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file1.txt', "modified content\n");

    $test_class_content = $this->createTestClass($baseline_dir, $actual_dir);
    $test_class_file = $test_dir . DIRECTORY_SEPARATOR . 'NoUpdateTest.php';
    file_put_contents($test_class_file, $test_class_content);

    $this->processCwd = $test_dir;

    for ($i = 0; $i < 3; $i++) {
      $this->processRun(
        self::$root . '/vendor/bin/phpunit',
        ['--no-configuration', $test_class_file],
      );

      $this->assertProcessFailed();
      $this->assertProcessErrorOutputNotContains('[SNAPSHOT]');
    }

    $this->assertStringEqualsFile($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "original content\n");
  }

  public function testNoUpdateForNonSnapshotFailures(): void {
    $test_dir = self::$sut . DIRECTORY_SEPARATOR . 'test_project_non_snapshot';
    File::mkdir($test_dir);

    $snapshots_dir = $test_dir . DIRECTORY_SEPARATOR . 'snapshots';
    $baseline_dir = $snapshots_dir . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR;
    File::mkdir($baseline_dir);
    file_put_contents($baseline_dir . DIRECTORY_SEPARATOR . 'file1.txt', "content\n");

    $actual_dir = $test_dir . DIRECTORY_SEPARATOR . 'actual';
    File::mkdir($actual_dir);
    file_put_contents($actual_dir . DIRECTORY_SEPARATOR . 'file1.txt', "content\n");

    $test_class_content = $this->createNonSnapshotFailingTestClass($baseline_dir, $actual_dir);
    $test_class_file = $test_dir . DIRECTORY_SEPARATOR . 'NonSnapshotFailTest.php';
    file_put_contents($test_class_file, $test_class_content);

    $this->processCwd = $test_dir;
    $this->processRun(
      self::$root . '/vendor/bin/phpunit',
      ['--no-configuration', $test_class_file],
      [],
      ['UPDATE_SNAPSHOTS' => '1'],
    );

    $this->assertProcessFailed();
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

use AlexSkrypnyk\\Snapshot\\Testing\\SnapshotTrait;
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

use AlexSkrypnyk\\Snapshot\\Testing\\SnapshotTrait;
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
    \$this->assertSnapshotMatchesBaseline(\$this->baseline, \$this->snapshots, \$this->actual);
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

use AlexSkrypnyk\\Snapshot\\Testing\\SnapshotTrait;
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
