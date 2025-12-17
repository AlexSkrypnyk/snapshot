<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\Snapshot\Exception\PatchException;
use AlexSkrypnyk\Snapshot\Index\IndexedFile;
use AlexSkrypnyk\Snapshot\Patch\Patcher;
use AlexSkrypnyk\Snapshot\Snapshot;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Patcher::class)]
final class PatcherTest extends UnitTestCase {

  #[DataProvider('dataProviderIsPatchFile')]
  public function testIsPatchFile(string $file_path, bool $expected): void {
    $file_path = self::$sut . DIRECTORY_SEPARATOR . $file_path;
    $dir = dirname($file_path);
    if (!file_exists($dir)) {
      mkdir($dir, 0777, TRUE);
    }

    if (str_contains($file_path, 'directory')) {
      mkdir($file_path, 0777, TRUE);
    }
    elseif (str_contains($file_path, 'symlink')) {
      touch($file_path . '_target');
      symlink($file_path . '_target', $file_path);
    }
    elseif (str_contains($file_path, 'with_content')) {
      file_put_contents($file_path, "Some content\n@@ -1,1 +1,1 @@\n");
    }
    else {
      file_put_contents($file_path, "Some content without patch markers");
    }

    $result = Patcher::isPatchFile($file_path);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderIsPatchFile(): \Iterator {
    yield 'non_existent_file' => ['non_existent.patch', FALSE];
    yield 'directory' => ['directory', FALSE];
    yield 'symlink' => ['symlink.patch', FALSE];
    yield 'file_with_content' => ['with_content.patch', TRUE];
    yield 'file_without_patch_content' => ['without_patch_content.txt', FALSE];
  }

  public function testAddPatchFile(): void {
    $patch_content = "@@ -1,1 +1,1 @@\n-old line\n+new line\n";
    $patch_file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.patch';
    file_put_contents($patch_file_path, $patch_content);

    $file_info = new IndexedFile(
      $patch_file_path,
      '',
      ''
    );

    $patcher = new Patcher(self::$sut, self::$sut);
    $result = $patcher->addPatchFile($file_info);

    $this->assertInstanceOf(Patcher::class, $result);
  }

  public function testAddPatchFileInvalid(): void {
    $content = "Not a patch file";
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'not_a_patch.txt';
    file_put_contents($file_path, $content);

    $file_info = new IndexedFile(
      $file_path,
      '',
      ''
    );

    $patcher = new Patcher(self::$sut, self::$sut);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage(sprintf('Invalid patch file in file "%s"', $file_path));

    $patcher->addPatchFile($file_info);
  }

  public function testAddDiff(): void {
    $patcher = new Patcher(self::$sut, self::$sut);

    $diff_string = "@@ -1,1 +1,1 @@\n-old line\n+new line\n";
    $result1 = $patcher->addDiff($diff_string, 'test.txt');
    $this->assertInstanceOf(Patcher::class, $result1);

    $diff_array = ["@@ -1,1 +1,1 @@", "-old line", "+new line"];
    $result2 = $patcher->addDiff($diff_array, 'test2.txt');
    $this->assertInstanceOf(Patcher::class, $result2);
  }

  public function testSplitLines(): void {
    $content = "line1\nline2\r\nline3\rline4";

    $result = self::callProtectedMethod(Patcher::class, 'splitLines', [$content]);

    $expected = ['line1', 'line2', 'line3', 'line4'];
    $this->assertEquals($expected, $result);
  }

  public function testSplitLinesEdgeCases(): void {
    $result1 = self::callProtectedMethod(Patcher::class, 'splitLines', ['']);
    $this->assertEquals([''], $result1);

    $result2 = self::callProtectedMethod(Patcher::class, 'splitLines', ['single line']);
    $this->assertEquals(['single line'], $result2);

    $result3 = self::callProtectedMethod(Patcher::class, 'splitLines', ["\n\n\n"]);
    $this->assertEquals(['', '', '', ''], $result3);
  }

  public function testFilePatch(): void {
    $baseline_dir = self::$sut . DIRECTORY_SEPARATOR . 'baseline';
    $diff_dir = self::$sut . DIRECTORY_SEPARATOR . 'diff';
    $dest_dir = self::$sut . DIRECTORY_SEPARATOR . 'dest';

    mkdir($baseline_dir, 0777, TRUE);
    mkdir($diff_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $baseline_file = $baseline_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($baseline_file, "line1\nline2\nline3\n");

    $diff_content = "@@ -1,3 +1,3 @@\n line1\n-line2\n+new line 2\n line3\n";
    $diff_file = $diff_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($diff_file, $diff_content);

    Snapshot::patch($baseline_dir, $diff_dir, $dest_dir);

    $this->assertFileExists($dest_dir . DIRECTORY_SEPARATOR . 'test.txt');
    $this->assertSame("line1\nnew line 2\nline3\n", file_get_contents($dest_dir . DIRECTORY_SEPARATOR . 'test.txt'));
  }

  public function testFindHunk(): void {
    $lines = [
      "@@ -1,3 +1,3 @@",
      " line1",
      "-line2",
      "+new line 2",
      " line3",
    ];
    reset($lines);

    $patcher = new Patcher(self::$sut, self::$sut);
    $result = self::callProtectedMethod($patcher, 'findHunk', [&$lines]);

    $expected = [
      'src_idx' => 1,
      'src_size' => 3,
      'dst_idx' => 1,
      'dst_size' => 3,
    ];
    $this->assertEquals($expected, $result);
    $this->assertSame(" line1", current($lines));
  }

  public function testFindHunkNull(): void {
    $lines = ["Not a hunk header"];
    reset($lines);

    $patcher = new Patcher(self::$sut, self::$sut);
    $result = self::callProtectedMethod($patcher, 'findHunk', [&$lines]);

    $this->assertNull($result);
  }

  public function testFindHunkUnexpectedEof(): void {
    $lines = ["@@ -1,3 +1,3 @@"];
    reset($lines);

    $patcher = new Patcher(self::$sut, self::$sut);
    $this->expectException(PatchException::class);
    $this->expectExceptionMessage('Unexpected EOF');

    self::callProtectedMethod($patcher, 'findHunk', [&$lines]);
  }

  public function testApplyHunk(): void {
    $source_dir = self::$sut . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = self::$sut . DIRECTORY_SEPARATOR . 'dest';
    mkdir($source_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $source_file = $source_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($source_file, "line1\nline2\nline3\n");

    $diff = [
      " line1",
      "-line2",
      "+new line 2",
      " line3",
    ];
    reset($diff);

    $info = [
      'src_idx' => 1,
      'src_size' => 3,
      'dst_idx' => 1,
      'dst_size' => 3,
    ];

    $patcher = new Patcher($source_dir, $dest_dir);
    $dst_file = $dest_dir . DIRECTORY_SEPARATOR . 'test.txt';

    self::callProtectedMethod($patcher, 'applyHunk', [
      &$diff,
      $source_file,
      $dst_file,
      $info,
    ]);

    self::callProtectedMethod($patcher, 'updateDestinations', []);

    $this->assertFileExists($dst_file);
    $this->assertSame("line1\nnew line 2\nline3\n", file_get_contents($dst_file));
  }

  public function testApplyHunkNoNewline(): void {
    $source_dir = self::$sut . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = self::$sut . DIRECTORY_SEPARATOR . 'dest';
    mkdir($source_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $source_file = $source_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($source_file, "line1\nline2\nline3");

    $diff = [
      " line1",
      "-line2",
      "+new line 2",
      " line3",
      "\\ No newline at end of file",
    ];
    reset($diff);

    $info = [
      'src_idx' => 1,
      'src_size' => 3,
      'dst_idx' => 1,
      'dst_size' => 3,
    ];

    $patcher = new Patcher($source_dir, $dest_dir);
    $dst_file = $dest_dir . DIRECTORY_SEPARATOR . 'test.txt';

    self::callProtectedMethod($patcher, 'applyHunk', [
      &$diff,
      $source_file,
      $dst_file,
      $info,
    ]);

    self::callProtectedMethod($patcher, 'updateDestinations', []);

    $this->assertFileExists($dst_file);
    $this->assertSame("line1\nnew line 2\nline3", file_get_contents($dst_file));
  }

  public function testApplyHunkSourceMismatch(): void {
    $source_dir = self::$sut . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = self::$sut . DIRECTORY_SEPARATOR . 'dest';
    mkdir($source_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $source_file = $source_dir . DIRECTORY_SEPARATOR . 'test.txt';
    $content = "different line1\ndifferent line2\ndifferent line3\n";
    file_put_contents($source_file, $content);

    $diff = [
      " line1",
      "-line2",
      "+new line 2",
      " line3",
    ];
    reset($diff);

    $info = [
      'src_idx' => 1,
      'src_size' => 3,
      'dst_idx' => 1,
      'dst_size' => 3,
    ];

    $patcher = new Patcher($source_dir, $dest_dir);
    $dst_file = $dest_dir . DIRECTORY_SEPARATOR . 'test.txt';

    $this->expectException(PatchException::class);
    $this->expectExceptionMessageMatches('/Source file verification failed/');

    self::callProtectedMethod($patcher, 'applyHunk', [
      &$diff,
      $source_file,
      $dst_file,
      $info,
    ]);
  }

  public function testApplyHunkMismatch(): void {
    $source_dir = self::$sut . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = self::$sut . DIRECTORY_SEPARATOR . 'dest';
    mkdir($source_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $source_file = $source_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($source_file, "line1\nline2\nline3\n");

    $diff = [
      " line1",
      "-line2",
    ];
    reset($diff);

    $info = [
      'src_idx' => 1,
      'src_size' => 3,
      'dst_idx' => 1,
      'dst_size' => 3,
    ];

    $patcher = new Patcher($source_dir, $dest_dir);
    $dst_file = $dest_dir . DIRECTORY_SEPARATOR . 'test.txt';

    $this->expectException(PatchException::class);
    $this->expectExceptionMessageMatches('/Hunk mismatch/');

    self::callProtectedMethod($patcher, 'applyHunk', [
      &$diff,
      $source_file,
      $dst_file,
      $info,
    ]);
  }

  public function testUpdateDestinations(): void {
    $patcher = new Patcher(self::$sut, self::$sut);

    $dest_file1 = self::$sut . DIRECTORY_SEPARATOR . 'file1.txt';
    $dest_file2 = self::$sut . DIRECTORY_SEPARATOR . 'file2.txt';

    self::setProtectedValue($patcher, 'dstLines', [
      $dest_file1 => ['line1', 'line2'],
      $dest_file2 => ['line3', 'line4'],
    ]);

    $result = self::callProtectedMethod($patcher, 'updateDestinations', []);

    $this->assertEquals(2, $result);
    $this->assertFileExists($dest_file1);
    $this->assertFileExists($dest_file2);
    $this->assertSame("line1\nline2", file_get_contents($dest_file1));
    $this->assertSame("line3\nline4", file_get_contents($dest_file2));
  }

  /**
   * Tests the unexpected removal line exception.
   */
  public function testUnexpectedRemovalLine(): void {
    $source_dir = self::$sut . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = self::$sut . DIRECTORY_SEPARATOR . 'dest';
    mkdir($source_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $source_file = $source_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($source_file, "line1\nline2\nline3\n");

    // Create a diff with too many removal lines.
    $diff = [
      " line1",
      "-line2",
      "-extra removal line",
      " line3",
    ];
    reset($diff);

    $info = [
      'src_idx' => 1,
      // Source size is only 2, but we're trying to remove 3 lines.
      'src_size' => 2,
      'dst_idx' => 1,
      'dst_size' => 2,
    ];

    $patcher = new Patcher($source_dir, $dest_dir);
    $dst_file = $dest_dir . DIRECTORY_SEPARATOR . 'test.txt';

    try {
      self::callProtectedMethod($patcher, 'applyHunk', [
        &$diff,
        $source_file,
        $dst_file,
        $info,
      ]);
      $this->fail('Expected PatchException was not thrown');
    }
    catch (PatchException $patch_exception) {
      $this->assertStringContainsString('Unexpected removal line', $patch_exception->getMessage());
      $this->assertSame($source_file, $patch_exception->getFilePath());
      $this->assertNotNull($patch_exception->getLineNumber());
      $this->assertNotNull($patch_exception->getLineContent());
    }
  }

  /**
   * Tests the unexpected addition line exception.
   */
  public function testUnexpectedAdditionLine(): void {
    $source_dir = self::$sut . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = self::$sut . DIRECTORY_SEPARATOR . 'dest';
    mkdir($source_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $source_file = $source_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($source_file, "line1\nline2\nline3\n");

    // Create a diff with too many addition lines.
    $diff = [
      " line1",
      "+new line",
      "+extra addition line",
      " line3",
    ];
    reset($diff);

    $info = [
      'src_idx' => 1,
      'src_size' => 2,
      'dst_idx' => 1,
      // Destination size is only 2, but we're trying to add 3 lines.
      'dst_size' => 2,
    ];

    $patcher = new Patcher($source_dir, $dest_dir);
    $dst_file = $dest_dir . DIRECTORY_SEPARATOR . 'test.txt';

    try {
      self::callProtectedMethod($patcher, 'applyHunk', [
        &$diff,
        $source_file,
        $dst_file,
        $info,
      ]);
      $this->fail('Expected PatchException was not thrown');
    }
    catch (PatchException $patch_exception) {
      $this->assertStringContainsString('Unexpected addition line', $patch_exception->getMessage());
      $this->assertSame($source_file, $patch_exception->getFilePath());
      $this->assertNotNull($patch_exception->getLineNumber());
      $this->assertNotNull($patch_exception->getLineContent());
    }
  }

  /**
   * Tests PatchException constructor and properties.
   */
  public function testPatchExceptionProperties(): void {
    $message = 'Test message';
    $file_path = '/path/to/file.txt';
    $line_number = 42;
    $line_content = 'Test line content';

    $exception = new PatchException(
      $message,
      $file_path,
      $line_number,
      $line_content
    );

    // Test getters.
    $this->assertSame($file_path, $exception->getFilePath());
    $this->assertSame($line_number, $exception->getLineNumber());
    $this->assertSame($line_content, $exception->getLineContent());

    // Test message formatting.
    $this->assertStringContainsString($message, $exception->getMessage());
    $this->assertStringContainsString($file_path, $exception->getMessage());
    $this->assertStringContainsString((string) $line_number, $exception->getMessage());
    $this->assertStringContainsString($line_content, $exception->getMessage());
  }

  /**
   * Test applying hunk with "No newline at end of file" line in the middle.
   */
  public function testApplyHunkWithNoNewlineInMiddle(): void {
    $source_dir = self::$sut . DIRECTORY_SEPARATOR . 'source';
    $dest_dir = self::$sut . DIRECTORY_SEPARATOR . 'dest';
    mkdir($source_dir, 0777, TRUE);
    mkdir($dest_dir, 0777, TRUE);

    $source_file = $source_dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($source_file, "line1\nline2\nline3\n");

    // Create a diff where "No newline at end of file" appears during
    // processing.
    $diff = [
      " line1",
      "\\ No newline at end of file",
      "-line2",
      "+new line 2",
      " line3",
    ];
    reset($diff);

    $info = [
      'src_idx' => 1,
      'src_size' => 3,
      'dst_idx' => 1,
      'dst_size' => 3,
    ];

    $patcher = new Patcher($source_dir, $dest_dir);
    $dst_file = $dest_dir . DIRECTORY_SEPARATOR . 'test.txt';

    self::callProtectedMethod($patcher, 'applyHunk', [
      &$diff,
      $source_file,
      $dst_file,
      $info,
    ]);

    self::callProtectedMethod($patcher, 'updateDestinations', []);

    $this->assertFileExists($dst_file);
    $this->assertSame("line1\nnew line 2\nline3\n", file_get_contents($dst_file));
  }

}
