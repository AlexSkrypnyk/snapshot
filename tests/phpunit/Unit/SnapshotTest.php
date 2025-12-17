<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\File\Exception\FileException;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Compare\Comparer;
use AlexSkrypnyk\Snapshot\Compare\Diff;
use AlexSkrypnyk\Snapshot\Patch\Patcher;
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\Snapshot;
use AlexSkrypnyk\Snapshot\Sync\Syncer;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

// @phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
#[CoversClass(Snapshot::class)]
#[CoversClass(Syncer::class)]
#[CoversClass(Patcher::class)]
#[CoversClass(Comparer::class)]
#[CoversClass(Diff::class)]
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

    $this->assertEquals($expected_diffs['absent_dir1'] ?? [], array_keys($absent_dir1));
    $this->assertEquals($expected_diffs['absent_dir2'] ?? [], array_keys($absent_dir2));
    $this->assertEquals($expected_diffs['content'] ?? [], $content);
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
            'dir5_content_ignore/dir51/d51f2-new-file.txt',
            'f4-new-file-notignore-everywhere.txt',
          ],
          'absent_dir2' => [
            'd32f2_symlink_deep.txt',
            'dir1_flat/d1f1_symlink.txt',
            'dir1_flat/d1f3-only-src.txt',
            'dir3_subdirs/dir32-unignored/d32f1_symlink.txt',
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

  #[DataProvider('dataProviderCompareRender')]
  public function testCompareRender(array $expected): void {
    $dir1 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'directory1');
    $dir2 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'directory2');

    $content = Snapshot::compare($dir1, $dir2)->render();

    if ($expected === []) {
      $this->assertNull($content);
      return;
    }

    if (is_null($content)) {
      $this->fail('Expected content, but got NULL.');
    }

    foreach ($expected as $expected_line) {
      $this->assertStringContainsString($expected_line, $content);
    }
  }

  public static function dataProviderCompareRender(): \Iterator {
    yield 'files_equal' => [
        [],
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
  dir5_content_ignore/dir51/d51f2-new-file.txt
  f4-new-file-notignore-everywhere.txt
ABSENT,
          "Files absent in [right]:\n",
          <<<ABSENT
  d32f2_symlink_deep.txt
  dir1_flat/d1f1_symlink.txt
  dir1_flat/d1f3-only-src.txt
  dir3_subdirs/dir32-unignored/d32f1_symlink.txt
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
    $baseline = File::dir($this->locationsFixtureDir() . DIRECTORY_SEPARATOR . '/../baseline');
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
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . '/../baseline');
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

    Snapshot::patch($baseline, $diff, self::$sut, $processor);

    $this->assertTrue($processor_called, 'Content processor should be called');
  }

  public function testGetBaselinePath(): void {
    // Create the baseline directory structure.
    $parent = self::$sut;
    $baseline_dir = $parent . DIRECTORY_SEPARATOR . Snapshot::BASELINE_DIR;
    $snapshot_dir = $parent . DIRECTORY_SEPARATOR . 'snapshot';
    mkdir($baseline_dir, 0755, TRUE);
    mkdir($snapshot_dir, 0755, TRUE);

    $result = Snapshot::getBaselinePath($snapshot_dir);

    $this->assertSame($baseline_dir, $result);
  }

  public function testIsBaseline(): void {
    $this->assertTrue(Snapshot::isBaseline('/path/to/' . Snapshot::BASELINE_DIR . '/file.txt'));
    $this->assertTrue(Snapshot::isBaseline('/path/' . Snapshot::BASELINE_DIR));
    $this->assertFalse(Snapshot::isBaseline('/path/to/regular/directory'));
    $this->assertFalse(Snapshot::isBaseline('/path/to/snapshot'));
  }

  public function testCompareWithRules(): void {
    $dir1 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');
    $dir2 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');

    $rules = Rules::create();
    $comparer = Snapshot::compare($dir1, $dir2, $rules);

    $this->assertInstanceOf(Comparer::class, $comparer);
  }

  public function testDiffWithRules(): void {
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . '/../baseline');
    $dst = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'result');

    $rules = Rules::create();
    Snapshot::diff($baseline, $dst, self::$sut, $rules);

    // No diff should be generated for equal directories.
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

}
