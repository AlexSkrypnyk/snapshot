<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Compare\Comparer;
use AlexSkrypnyk\Snapshot\Compare\Diff;
use AlexSkrypnyk\Snapshot\Index\IndexedFile;
use AlexSkrypnyk\Snapshot\Patch\Patcher;
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\Snapshot;
use AlexSkrypnyk\Snapshot\Sync\Syncer;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

// @phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
#[CoversClass(Comparer::class)]
#[CoversClass(Diff::class)]
#[CoversClass(Patcher::class)]
#[CoversClass(Snapshot::class)]
#[CoversClass(Syncer::class)]
final class SnapshotTest extends UnitTestCase {

  #[DataProvider('dataProviderCompare')]
  public function testCompare(array $expected_diffs = []): void {
    $dir1 = File::dir($this->locationsFixtureDir() . DIRECTORY_SEPARATOR . 'directory1');
    $dir2 = File::dir($this->locationsFixtureDir() . DIRECTORY_SEPARATOR . 'directory2');

    $comparer = Snapshot::compare($dir1, $dir2);

    $absent_dir1 = $comparer->getAbsentLeftDiffs();
    $absent_dir2 = $comparer->getAbsentRightDiffs();
    $content = $comparer->getContentDiffs(fn(Diff $diff): array => [
      'dir1' => $diff->getLeft()->getContent(),
      'dir2' => $diff->getRight()->getContent(),
    ]);

    $this->assertSame($expected_diffs['absent_dir1'] ?? [], array_keys($absent_dir1));
    $this->assertSame($expected_diffs['absent_dir2'] ?? [], array_keys($absent_dir2));
    $this->assertSame($expected_diffs['content'] ?? [], $content);
  }

  public static function dataProviderCompare(): \Iterator {
    yield 'files_equal' => [];
    yield 'files_not_equal' => [
      [
        'absent_dir1' => [
          'f4.txt',
        ],
        'absent_dir2' => [
          'f3.txt',
        ],
        'content' => [
          'f2.txt' => [
            'dir1' => "f2l1\n",
            'dir2' => "f2l1-changed\n",
          ],
        ],
      ],
    ];
    yield 'files_equal_ignorecontent' => [];
    yield 'files_not_equal_ignorecontent' => [
      [
        'absent_dir1' => [
          'f4.txt',
        ],
        'absent_dir2' => [
          'f3.txt',
        ],
        'content' => [
          'f2.txt' => [
            'dir1' => "f2l1\n",
            'dir2' => "f2l1-changed\n",
          ],
        ],
      ],
    ];
    yield 'files_equal_advanced' => [];
    yield 'files_not_equal_advanced' => [
      [
        'absent_dir1' => [
          'dir2_flat-present-dst/d2f1.txt',
          'dir2_flat-present-dst/d2f2.txt',
          'dir3_subdirs/dir31/f4-new-file-notignore-everywhere.txt',
          'dir3_subdirs/dir32-unignored/d32f2-ignore-ext-only-dst.log',
          'dir5_content_ignore/dir51/d51f2-new-file.txt',
          'f4-new-file-notignore-everywhere.txt',
        ],
        'absent_dir2' => [
          'd32f2_symlink_deep.txt',
          'dir1_flat/d1f1_symlink.txt',
          'dir1_flat/d1f3-only-src.txt',
          'dir3_subdirs/dir32-unignored/d32f1_symlink.txt',
          'dir3_subdirs/dir32-unignored/d32f2-only-src.log',
          'dir3_subdirs_symlink',
          'f2_symlink.txt',
        ],
        'content' => [
          'dir3_subdirs/dir32-unignored/d32f2.txt' => [
            'dir1' => "d32f2l1\n",
            'dir2' => "d32f2l1-changed\n",
          ],
        ],
      ],
    ];
  }

  /**
   * Test the rendered comparison of a fixture directory pair.
   *
   * @param array $expected
   *   Strings that must appear in the render. An empty array expects no
   *   render at all.
   * @param array $unexpected
   *   Strings that must not appear in the render, pinning the branch that
   *   renders nothing for an empty absent-file list.
   */
  #[DataProvider('dataProviderCompareRender')]
  public function testCompareRender(array $expected, array $unexpected = []): void {
    $dir1 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'directory1');
    $dir2 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'directory2');

    $content = Snapshot::compare($dir1, $dir2)->render();

    if ($expected === []) {
      $this->assertNull($content);
      return;
    }

    if ($content === NULL) {
      $this->fail('Expected content, but got NULL.');
    }

    foreach ($expected as $expected_line) {
      $this->assertStringContainsString($expected_line, $content);
    }

    foreach ($unexpected as $unexpected_line) {
      $this->assertStringNotContainsString($unexpected_line, $content);
    }
  }

  public static function dataProviderCompareRender(): \Iterator {
    yield 'files_equal' => [
      [],
    ];
    yield 'files_content_only' => [
      [
        'Differences between directories',
        'Files that differ in content:',
        'f2.txt' => <<<DIFF_WRAP
          --- DIFF START ---
          @@ -1 +1 @@
          -f2l1
          +f2l1-changed
          --- DIFF END ---
          DIFF_WRAP,
      ],
      [
        'Files absent in [left]:',
        'Files absent in [right]:',
      ],
    ];
    yield 'files_not_equal' => [
      [
        'Differences between directories',
        <<<ABSENT
          Files absent in [left]:
            f4.txt
          ABSENT,
        <<<ABSENT
          Files absent in [right]:
            f3.txt
          ABSENT,
        'Files that differ in content:',
        'f2.txt' => <<<DIFF_WRAP
          --- DIFF START ---
          @@ -1 +1 @@
          -f2l1
          +f2l1-changed
          --- DIFF END ---
          DIFF_WRAP,
      ],
    ];
    yield 'files_equal_ignorecontent' => [
      [],
    ];
    yield 'files_not_equal_ignorecontent' => [
      [
        'Differences between directories',
        <<<ABSENT
          Files absent in [left]:
            f4.txt
          ABSENT,
        <<<ABSENT
          Files absent in [right]:
            f3.txt
          ABSENT,
        'Files that differ in content:',
        'f2.txt' => <<<DIFF_WRAP
          --- DIFF START ---
          @@ -1 +1 @@
          -f2l1
          +f2l1-changed
          --- DIFF END ---
          DIFF_WRAP,
      ],
    ];
    yield 'files_equal_advanced' => [
      [],
    ];
    yield 'files_not_equal_advanced' => [
      [
        'Differences between directories',
        "Files absent in [left]:\n",
        <<<ABSENT
            dir2_flat-present-dst/d2f1.txt
            dir2_flat-present-dst/d2f2.txt
            dir3_subdirs/dir31/f4-new-file-notignore-everywhere.txt
            dir3_subdirs/dir32-unignored/d32f2-ignore-ext-only-dst.log
            dir5_content_ignore/dir51/d51f2-new-file.txt
            f4-new-file-notignore-everywhere.txt
          ABSENT,
        "Files absent in [right]:\n",
        <<<ABSENT
            d32f2_symlink_deep.txt
            dir1_flat/d1f1_symlink.txt
            dir1_flat/d1f3-only-src.txt
            dir3_subdirs/dir32-unignored/d32f1_symlink.txt
            dir3_subdirs/dir32-unignored/d32f2-only-src.log
            dir3_subdirs_symlink
            f2_symlink.txt
          ABSENT,
        'Files that differ in content:',
        'dir3_subdirs/dir32-unignored/d32f2.txt' => <<<DIFF_WRAP
          --- DIFF START ---
          @@ -1 +1 @@
          -d32f2l1
          +d32f2l1-changed
          --- DIFF END ---
          DIFF_WRAP,
      ],
    ];
  }

  #[DataProvider('dataProviderDiff')]
  public function testDiff(): void {
    $baseline = File::dir($this->locationsFixtureDir() . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'baseline');
    $dst = File::dir($this->locationsFixtureDir() . DIRECTORY_SEPARATOR . 'result');

    Snapshot::diff($baseline, $dst, self::$sut);

    $expected = File::dir($this->locationsFixtureDir() . DIRECTORY_SEPARATOR . 'diff');

    $this->assertDirectoriesIdentical($expected, self::$sut);
  }

  public static function dataProviderDiff(): \Iterator {
    yield 'files_equal' => [];
    yield 'files_not_equal' => [];
  }

  public function testSync(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');
    $expected = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');

    copy($expected . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT, self::$sut . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT);

    Snapshot::sync($src, self::$sut);

    $this->assertDirectoriesIdentical($expected, self::$sut);
  }

  public function testSyncFile(): void {
    $this->expectException(FileException::class);
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');

    $dst = self::$sut . DIRECTORY_SEPARATOR . 'file.txt';
    touch($dst);

    Snapshot::sync($src, $dst);
  }

  #[DataProvider('dataProviderPatch')]
  public function testPatch(): void {
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'baseline');
    $diff = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'diff');

    Snapshot::patch($baseline, $diff, self::$sut);

    $expected = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'result');

    $this->assertDirectoriesIdentical($expected, self::$sut);
  }

  public static function dataProviderPatch(): \Iterator {
    yield 'files_equal' => [];
    yield 'files_not_equal' => [];
  }

  public function testScan(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');

    $index = Snapshot::scan($src);

    $this->assertGreaterThan(0, count($index->getFiles()));
  }

  public function testPatchWithContentProcessor(): void {
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'baseline');
    $diff = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'diff');

    $processor_called = FALSE;
    $processor = function (string $content) use (&$processor_called): string {
      $processor_called = TRUE;
      return $content;
    };

    Snapshot::patch($baseline, $diff, self::$sut, NULL, $processor);

    $this->assertTrue($processor_called, 'Content processor should be called');
  }

  public function testPatchWithContentProcessorThatModifies(): void {
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'baseline');
    $diff = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'diff');

    $processor = fn(string $content): string => str_replace('f1l1', 'REPLACED', $content);

    Snapshot::patch($baseline, $diff, self::$sut, NULL, $processor);

    $files = File::scandir(self::$sut);
    $modified = FALSE;
    foreach ($files as $file_path) {
      if (is_file($file_path) && str_contains((string) file_get_contents($file_path), 'REPLACED')) {
        $modified = TRUE;
        break;
      }
    }
    $this->assertTrue($modified, 'Content processor should have modified file content on disk');
  }

  public function testGetBaselinePath(): void {
    $parent = self::$sut;
    $baseline_dir = $parent . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR;
    $snapshot_dir = $parent . DIRECTORY_SEPARATOR . 'snapshot';
    mkdir($baseline_dir, 0777, TRUE);
    mkdir($snapshot_dir, 0777, TRUE);

    $result = Snapshot::getBaselinePath($snapshot_dir);

    $this->assertSame($baseline_dir, $result);
  }

  #[DataProvider('dataProviderIsBaseline')]
  public function testIsBaseline(string $path, bool $expected): void {
    $this->assertSame($expected, Snapshot::isBaseline($path));
  }

  public static function dataProviderIsBaseline(): \Iterator {
    yield 'baseline directory' => ['snapshots' . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR, TRUE];
    yield 'baseline directory with trailing separator' => ['snapshots' . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR . DIRECTORY_SEPARATOR, TRUE];
    yield 'bare baseline directory' => [Snapshot::BASELINE_DIR, TRUE];
    yield 'file inside baseline directory' => ['snapshots' . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'App.php', FALSE];
    yield 'sibling directory with baseline prefix' => ['snapshots' . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR . '_backup', FALSE];
    yield 'file inside sibling directory with baseline prefix' => ['snapshots' . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR . '_old' . DIRECTORY_SEPARATOR . 'x', FALSE];
    yield 'regular scenario directory' => ['snapshots' . DIRECTORY_SEPARATOR . 'scenario_one', FALSE];
  }

  public function testCompareWithRules(): void {
    $dir1 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');
    $dir2 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');

    $rules = Rules::create();
    $comparer = Snapshot::compare($dir1, $dir2, $rules);

    $this->assertInstanceOf(Comparer::class, $comparer);
  }

  public function testDiffWithRules(): void {
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'baseline');
    $dst = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'result');

    $rules = Rules::create();
    Snapshot::diff($baseline, $dst, self::$sut, $rules);

    // Equal directories generate no diff.
    $files = glob(self::$sut . '/*');
    $this->assertEmpty($files);
  }

  public function testScanWithRulesAndProcessor(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');

    $rules = Rules::create();
    $processor = fn(string $content): string => $content;

    $index = Snapshot::scan($src, $rules, $processor);

    $this->assertGreaterThan(0, count($index->getFiles()));
  }

  public function testComparerCacheInvalidation(): void {
    $dir1 = self::$sut . DIRECTORY_SEPARATOR . 'dir1';
    $dir2 = self::$sut . DIRECTORY_SEPARATOR . 'dir2';
    mkdir($dir1, 0777, TRUE);
    mkdir($dir2, 0777, TRUE);

    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'f1.txt', 'content1');
    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'f1.txt', 'content1');

    $comparer = Snapshot::compare($dir1, $dir2);

    // The directories are identical, so the first call returns empty results.
    $this->assertEmpty($comparer->getAbsentLeftDiffs());
    $this->assertEmpty($comparer->getAbsentRightDiffs());
    $this->assertEmpty($comparer->getContentDiffs());

    // The second call returns the same cached results.
    $this->assertEmpty($comparer->getAbsentLeftDiffs());
    $this->assertEmpty($comparer->getContentDiffs());
  }

  public function testComparerCacheInvalidatedOnAddFile(): void {
    $dir1 = self::$sut . DIRECTORY_SEPARATOR . 'dir1';
    $dir2 = self::$sut . DIRECTORY_SEPARATOR . 'dir2';
    mkdir($dir1, 0777, TRUE);
    mkdir($dir2, 0777, TRUE);

    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'f1.txt', 'content1');
    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'f1.txt', 'content1');

    $comparer = Snapshot::compare($dir1, $dir2);

    // The first read populates the cache.
    $this->assertEmpty($comparer->getAbsentLeftDiffs());

    // Adding a file to the left side invalidates the cache.
    $new_file_path = $dir1 . DIRECTORY_SEPARATOR . 'f2.txt';
    file_put_contents($new_file_path, 'new content');
    $new_file = new IndexedFile($new_file_path, $dir1);
    $comparer->addLeftFile($new_file);

    $absent_right = $comparer->getAbsentRightDiffs();
    $this->assertArrayHasKey('f2.txt', $absent_right);
  }

  public function testComparerAddFileMutatorsReturnSelf(): void {
    $dir1 = self::$sut . DIRECTORY_SEPARATOR . 'dir1';
    $dir2 = self::$sut . DIRECTORY_SEPARATOR . 'dir2';
    mkdir($dir1, 0777, TRUE);
    mkdir($dir2, 0777, TRUE);

    file_put_contents($dir1 . DIRECTORY_SEPARATOR . 'f1.txt', 'content1');
    file_put_contents($dir2 . DIRECTORY_SEPARATOR . 'f1.txt', 'content1');

    $comparer = Snapshot::compare($dir1, $dir2);
    $left_file = new IndexedFile($dir1 . DIRECTORY_SEPARATOR . 'f1.txt', $dir1);
    $right_file = new IndexedFile($dir2 . DIRECTORY_SEPARATOR . 'f1.txt', $dir2);

    $this->assertSame($comparer, $comparer->addLeftFile($left_file));
    $this->assertSame($comparer, $comparer->addRightFile($right_file));
  }

  public function testSyncRespectsContentIgnored(): void {
    $src = self::$sut . DIRECTORY_SEPARATOR . 'src';
    $dst = self::$sut . DIRECTORY_SEPARATOR . 'dst';
    mkdir($src, 0777, TRUE);

    file_put_contents($src . DIRECTORY_SEPARATOR . 'regular.txt', 'regular content');
    file_put_contents($src . DIRECTORY_SEPARATOR . 'ignored.txt', 'actual secret content');
    file_put_contents($src . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT, "^ignored.txt\n");

    Snapshot::sync($src, $dst);

    $this->assertFileExists($dst . DIRECTORY_SEPARATOR . 'regular.txt');
    $this->assertSame('regular content', file_get_contents($dst . DIRECTORY_SEPARATOR . 'regular.txt'));
    $this->assertFileExists($dst . DIRECTORY_SEPARATOR . 'ignored.txt');
    $this->assertSame(IndexedFile::CONTENT_IGNORED_MARKER, file_get_contents($dst . DIRECTORY_SEPARATOR . 'ignored.txt'));
  }

  public function testSyncUsesFileCopy(): void {
    if (!function_exists('symlink')) {
      $this->markTestSkipped('Symlinks are not supported on this system.');
    }

    $src = self::$sut . DIRECTORY_SEPARATOR . 'src';
    $dst = self::$sut . DIRECTORY_SEPARATOR . 'dst';
    mkdir($src, 0777, TRUE);

    file_put_contents($src . DIRECTORY_SEPARATOR . 'regular.txt', 'regular content');
    mkdir($src . DIRECTORY_SEPARATOR . 'subdir', 0777, TRUE);
    file_put_contents($src . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'nested.txt', 'nested content');
    symlink($src . DIRECTORY_SEPARATOR . 'regular.txt', $src . DIRECTORY_SEPARATOR . 'link.txt');

    Snapshot::sync($src, $dst);

    $this->assertFileExists($dst . DIRECTORY_SEPARATOR . 'regular.txt');
    $this->assertSame('regular content', file_get_contents($dst . DIRECTORY_SEPARATOR . 'regular.txt'));
    $this->assertFileExists($dst . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'nested.txt');
    $this->assertSame('nested content', file_get_contents($dst . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'nested.txt'));
    $this->assertTrue(is_link($dst . DIRECTORY_SEPARATOR . 'link.txt'));
  }

  public function testPatchWithRules(): void {
    $baseline = self::$sut . DIRECTORY_SEPARATOR . 'baseline';
    $diffs = self::$sut . DIRECTORY_SEPARATOR . 'diffs';
    $destination = self::$sut . DIRECTORY_SEPARATOR . 'destination';
    mkdir($baseline, 0777, TRUE);
    mkdir($diffs, 0777, TRUE);

    file_put_contents($baseline . DIRECTORY_SEPARATOR . 'keep.txt', 'keep content');
    file_put_contents($baseline . DIRECTORY_SEPARATOR . 'skip.txt', 'secret baseline content');
    file_put_contents($diffs . DIRECTORY_SEPARATOR . 'newfile.txt', 'new content');
    file_put_contents($diffs . DIRECTORY_SEPARATOR . 'skip.txt', 'secret diff content');

    $rules = Rules::create()->skip('skip.txt');

    Snapshot::patch($baseline, $diffs, $destination, $rules);

    $this->assertFileExists($destination . DIRECTORY_SEPARATOR . 'keep.txt');
    $this->assertFileExists($destination . DIRECTORY_SEPARATOR . 'newfile.txt');
    $this->assertFileDoesNotExist($destination . DIRECTORY_SEPARATOR . 'skip.txt');
  }

  public function testSyncWithRulesAndProcessor(): void {
    $src = self::$sut . DIRECTORY_SEPARATOR . 'src';
    $dst = self::$sut . DIRECTORY_SEPARATOR . 'dst';
    mkdir($src, 0777, TRUE);

    file_put_contents($src . DIRECTORY_SEPARATOR . 'keep.txt', 'keep content');
    file_put_contents($src . DIRECTORY_SEPARATOR . 'skip.txt', 'secret content');
    file_put_contents($src . DIRECTORY_SEPARATOR . 'excluded.txt', 'exclude-me content');

    $rules = Rules::create()->skip('skip.txt');
    $processor = fn(IndexedFile $file): bool => !str_contains($file->getContent(), 'exclude-me');

    Snapshot::sync($src, $dst, 0755, FALSE, $rules, $processor);

    $this->assertFileExists($dst . DIRECTORY_SEPARATOR . 'keep.txt');
    $this->assertFileDoesNotExist($dst . DIRECTORY_SEPARATOR . 'skip.txt');
    $this->assertFileDoesNotExist($dst . DIRECTORY_SEPARATOR . 'excluded.txt');
  }

}
