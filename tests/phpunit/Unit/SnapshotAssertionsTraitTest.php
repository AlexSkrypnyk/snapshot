<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Snapshot;
use AlexSkrypnyk\Snapshot\SnapshotTrait;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SnapshotTrait::class)]
final class SnapshotAssertionsTraitTest extends TestCase {

  use SnapshotTrait;

  protected string $tmpDir;
  protected string $baselineDir;
  protected string $diffDir;
  protected string $expectedDir;
  protected string $actualDir;

  protected function setUp(): void {
    $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('directory_assertions_test_', TRUE);
    mkdir($this->tmpDir, 0777, TRUE);

    $this->baselineDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'baseline';
    $this->diffDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'diff';
    $this->expectedDir = $this->tmpDir . DIRECTORY_SEPARATOR . '.expected';
    $this->actualDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'actual';

    mkdir($this->baselineDir, 0777, TRUE);
    mkdir($this->diffDir, 0777, TRUE);
    mkdir($this->actualDir, 0777, TRUE);
  }

  protected function tearDown(): void {
    if (is_dir($this->tmpDir)) {
      File::remove($this->tmpDir);
    }
  }

  public function testAssertDirectoriesIdenticalPositive(): void {
    $dir1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'dir1';
    $dir2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'dir2';

    mkdir($dir1, 0777, TRUE);
    mkdir($dir2, 0777, TRUE);

    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'file1.txt', 'Content 1');
    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'file2.txt', 'Content 2');

    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'file1.txt', 'Content 1');
    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'file2.txt', 'Content 2');

    $this->assertDirectoriesIdentical($dir1, $dir2);
    $this->addToAssertionCount(1);

    mkdir($dir1 . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    mkdir($dir2 . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);

    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file3.txt', 'Content 3');
    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file3.txt', 'Content 3');

    $this->assertDirectoriesIdentical($dir1, $dir2);
    $this->addToAssertionCount(1);
  }

  public function testAssertDirectoriesIdenticalNegative(): void {
    $dir1 = $this->tmpDir . DIRECTORY_SEPARATOR . 'dir1';
    $dir2 = $this->tmpDir . DIRECTORY_SEPARATOR . 'dir2';

    mkdir($dir1, 0777, TRUE);
    mkdir($dir2, 0777, TRUE);

    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'file1.txt', 'Content 1');
    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'file2.txt', 'Content 2');

    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'file1.txt', 'Different content');
    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'file3.txt', 'Content 3');

    try {
      $this->assertDirectoriesIdentical($dir1, $dir2);
      $this->fail('Assertion should have failed for different file content');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Files that differ in content', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file1.txt', $assertion_failed_error->getMessage());
    }

    try {
      $this->assertDirectoriesIdentical($dir1, $dir2, 'Custom message for missing files');
      $this->fail('Assertion should have failed for missing files');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom message for missing files', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('Files absent in', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file2.txt', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file3.txt', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertSnapshotMatchesBaselinePositive(): void {
    mkdir($this->baselineDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file2.txt', 'Subdir content');

    mkdir($this->diffDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file3.txt', 'New file content');

    mkdir($this->actualDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file3.txt', 'New file content');
    $subdir_path = $this->actualDir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR;
    file_put_contents($subdir_path . 'file2.txt', 'Subdir content');

    $this->assertSnapshotMatchesBaseline($this->actualDir, $this->baselineDir, $this->diffDir);
    $this->addToAssertionCount(1);
  }

  public function testAssertSnapshotMatchesBaselineNegative(): void {
    mkdir($this->baselineDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file2.txt', 'Subdir content');

    mkdir($this->diffDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Modified content');
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file3.txt', 'New file content');

    mkdir($this->actualDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Wrong content');
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file3.txt', 'New file content');
    $subdir_path = $this->actualDir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR;
    file_put_contents($subdir_path . 'file2.txt', 'Subdir content');

    $this->expectedDir = $this->tmpDir . DIRECTORY_SEPARATOR . '.expected';
    mkdir($this->expectedDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->expectedDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Modified content');
    file_put_contents($this->expectedDir . DIRECTORY_SEPARATOR . 'file3.txt', 'New file content');
    $subdir_path = $this->expectedDir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR;
    file_put_contents($subdir_path . 'file2.txt', 'Subdir content');

    try {
      $this->assertSnapshotMatchesBaseline($this->actualDir, $this->baselineDir, $this->diffDir, $this->expectedDir);
      $this->fail('Assertion should have failed for different content');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Files that differ in content', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('file1.txt', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertSnapshotMatchesBaselineWithNonexistentBaseline(): void {
    $nonexistent_dir = $this->tmpDir . DIRECTORY_SEPARATOR . 'nonexistent';

    try {
      $this->assertSnapshotMatchesBaseline($this->actualDir, $nonexistent_dir, $this->diffDir);
      $this->fail('Assertion should have failed for nonexistent baseline directory');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('The baseline directory does not exist', $assertion_failed_error->getMessage());
      $this->assertStringContainsString($nonexistent_dir, $assertion_failed_error->getMessage());
    }
  }

  public function testAssertSnapshotMatchesBaselineWithInvalidPatch(): void {
    mkdir($this->baselineDir . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', "line1\nline2\nline3\n");

    // Create an invalid patch file with incorrect line indices.
    mkdir($this->diffDir, 0777, TRUE);
    $diff_content = "@@ -1,3 +1,3 @@\n line1\n-wrong line\n+new line 2\n line3\n";
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', $diff_content);

    mkdir($this->actualDir, 0777, TRUE);

    try {
      $this->assertSnapshotMatchesBaseline($this->actualDir, $this->baselineDir, $this->diffDir);
      $this->fail('Assertion should have failed for invalid patch');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Failed to apply patch', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertSnapshotMatchesBaselineWithHunkMismatch(): void {
    mkdir($this->baselineDir, 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', "line1\nline2\nline3\n");

    // Create a patch file with a hunk mismatch (incomplete hunk)
    mkdir($this->diffDir, 0777, TRUE);
    // Missing the rest of the hunk.
    $diff_content = "@@ -1,3 +1,3 @@\n line1\n-line2\n";
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', $diff_content);

    mkdir($this->actualDir, 0777, TRUE);

    try {
      $this->assertSnapshotMatchesBaseline($this->actualDir, $this->baselineDir, $this->diffDir);
      $this->fail('Assertion should have failed for hunk mismatch');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Failed to apply patch', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('Hunk mismatch', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertSnapshotMatchesBaselineWithUnexpectedEof(): void {
    mkdir($this->baselineDir, 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', "line1\nline2\nline3\n");

    // Create a patch file with unexpected EOF.
    mkdir($this->diffDir, 0777, TRUE);
    // Missing the content completely.
    $diff_content = "@@ -1,3 +1,3 @@";
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', $diff_content);

    mkdir($this->actualDir, 0777, TRUE);

    try {
      $this->assertSnapshotMatchesBaseline($this->actualDir, $this->baselineDir, $this->diffDir);
      $this->fail('Assertion should have failed for unexpected EOF');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Failed to apply patch', $assertion_failed_error->getMessage());
      $this->assertStringContainsString('Unexpected EOF', $assertion_failed_error->getMessage());
    }
  }

  public function testAssertSnapshotMatchesBaselineWithIgnoreContent(): void {
    mkdir($this->baselineDir, 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT, "*.ignored\n*.log");

    mkdir($this->diffDir, 0777, TRUE);
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');

    mkdir($this->actualDir, 0777, TRUE);
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');

    $this->expectedDir = $this->tmpDir . DIRECTORY_SEPARATOR . '.expected';
    mkdir($this->expectedDir, 0777, TRUE);
    file_put_contents($this->expectedDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');
    file_put_contents($this->expectedDir . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT, "*.ignored\n*.log");

    $this->assertSnapshotMatchesBaseline($this->actualDir, $this->baselineDir, $this->diffDir, $this->expectedDir);
    $this->addToAssertionCount(1);

    $expected_ignore_content_path = $this->expectedDir . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT;
    $this->assertFileExists($expected_ignore_content_path);
    $content = file_get_contents($expected_ignore_content_path);
    $this->assertIsString($content);
    $this->assertSame("*.ignored\n*.log", $content);
  }

  public function testAssertSnapshotMatchesBaselineWithCustomMessage(): void {
    // Set up baseline directory.
    mkdir($this->baselineDir, 0777, TRUE);
    file_put_contents($this->baselineDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');

    // Set up diff directory.
    mkdir($this->diffDir, 0777, TRUE);
    file_put_contents($this->diffDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');

    // Set up actual directory.
    mkdir($this->actualDir, 0777, TRUE);
    file_put_contents($this->actualDir . DIRECTORY_SEPARATOR . 'file1.txt', 'Original content');

    // Test successful assertion with custom message.
    $this->assertSnapshotMatchesBaseline($this->actualDir, $this->baselineDir, $this->diffDir, NULL, 'Custom success message');
    $this->addToAssertionCount(1);

    // Test failed assertion with custom message (nonexistent baseline).
    $nonexistent_dir = $this->tmpDir . DIRECTORY_SEPARATOR . 'nonexistent';
    try {
      $this->assertSnapshotMatchesBaseline($this->actualDir, $nonexistent_dir, $this->diffDir, NULL, 'Custom failure message');
      $this->fail('Assertion should have failed');
    }
    catch (AssertionFailedError $assertion_failed_error) {
      $this->assertStringContainsString('Custom failure message', $assertion_failed_error->getMessage());
    }
  }

}
