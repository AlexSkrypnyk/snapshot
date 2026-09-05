<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Compare\Comparer;
use AlexSkrypnyk\Snapshot\Index\Index;
use AlexSkrypnyk\Snapshot\Index\IndexedFile;
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\Snapshot;
use AlexSkrypnyk\Snapshot\SnapshotBuilder;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SnapshotBuilder::class)]
final class SnapshotBuilderTest extends UnitTestCase {

  public function testCreate(): void {
    $builder = SnapshotBuilder::create();
    $this->assertInstanceOf(SnapshotBuilder::class, $builder);
    $this->assertNotInstanceOf(Rules::class, $builder->getRules());
    $this->assertNull($builder->getContentProcessor());
    $this->assertNull($builder->getFileFilter());
  }

  public function testWithRules(): void {
    $rules = Rules::create()->skip('vendor/');
    $builder = SnapshotBuilder::create()->withRules($rules);

    $this->assertSame($rules, $builder->getRules());
  }

  public function testWithContentProcessor(): void {
    $processor = fn(string $content): string => strtoupper($content);
    $builder = SnapshotBuilder::create()->withContentProcessor($processor);

    $this->assertSame($processor, $builder->getContentProcessor());
  }

  public function testWithFileFilter(): void {
    $file_filter = fn(IndexedFile $file): bool => TRUE;
    $builder = SnapshotBuilder::create()->withFileFilter($file_filter);

    $this->assertSame($file_filter, $builder->getFileFilter());
    $this->assertNull($builder->getContentProcessor());
  }

  public function testAddSkip(): void {
    $builder = SnapshotBuilder::create()->addSkip('vendor/', 'node_modules/');

    $rules = $builder->getRules();
    $this->assertInstanceOf(Rules::class, $rules);
    $this->assertSame(['vendor/', 'node_modules/'], $rules->getSkip());
  }

  public function testAddIgnoreContent(): void {
    $builder = SnapshotBuilder::create()->addIgnoreContent('composer.lock', 'package-lock.json');

    $rules = $builder->getRules();
    $this->assertInstanceOf(Rules::class, $rules);
    $this->assertSame(['composer.lock', 'package-lock.json'], $rules->getIgnoreContent());
  }

  public function testAddInclude(): void {
    $builder = SnapshotBuilder::create()->addInclude('important.log');

    $rules = $builder->getRules();
    $this->assertInstanceOf(Rules::class, $rules);
    $this->assertSame(['important.log'], $rules->getInclude());
  }

  public function testAddIncludeContent(): void {
    $builder = SnapshotBuilder::create()->addIncludeContent('important.log');

    $rules = $builder->getRules();
    $this->assertInstanceOf(Rules::class, $rules);
    $this->assertSame(['important.log'], $rules->getIncludeContent());
  }

  public function testFluentMethodChaining(): void {
    $builder = SnapshotBuilder::create()
      ->withRules(Rules::phpProject())
      ->addSkip('custom/')
      ->addIgnoreContent('custom.lock')
      ->withContentProcessor(fn(string $content): string => $content);

    $rules = $builder->getRules();
    $this->assertInstanceOf(Rules::class, $rules);
    $this->assertContains('vendor/', $rules->getSkip());
    $this->assertContains('custom/', $rules->getSkip());
    $this->assertContains('composer.lock', $rules->getIgnoreContent());
    $this->assertContains('custom.lock', $rules->getIgnoreContent());
  }

  public function testScan(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');

    $builder = SnapshotBuilder::create();
    $index = $builder->scan($src);

    $this->assertInstanceOf(Index::class, $index);
    $this->assertGreaterThan(0, count($index->getFiles()));
  }

  public function testScanWithRules(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');

    $builder = SnapshotBuilder::create()->withRules(Rules::create());
    $index = $builder->scan($src);

    $this->assertInstanceOf(Index::class, $index);
  }

  public function testCompare(): void {
    $dir1 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');
    $dir2 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');

    $builder = SnapshotBuilder::create();
    $comparer = $builder->compare($dir1, $dir2);

    $this->assertInstanceOf(Comparer::class, $comparer);
    $this->assertNull($comparer->render());
  }

  public function testCompareWithRules(): void {
    $dir1 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');
    $dir2 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');

    $builder = SnapshotBuilder::create()->withRules(Rules::create());
    $comparer = $builder->compare($dir1, $dir2);

    $this->assertInstanceOf(Comparer::class, $comparer);
  }

  public function testDiff(): void {
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'baseline');
    $dst = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'result');

    $builder = SnapshotBuilder::create();
    $result = $builder->diff($baseline, $dst, self::$sut);

    $this->assertSame($builder, $result);

    // Equal directories generate no diff.
    $files = glob(self::$sut . '/*');
    $this->assertEmpty($files);
  }

  public function testPatch(): void {
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'baseline');
    $diff = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'diff');
    $expected = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'result');

    $builder = SnapshotBuilder::create();
    $result = $builder->patch($baseline, $diff, self::$sut);

    $this->assertSame($builder, $result);

    $this->assertDirectoriesIdentical($expected, self::$sut);
  }

  public function testSync(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');
    $expected = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');

    copy($expected . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT, self::$sut . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT);

    $builder = SnapshotBuilder::create();
    $result = $builder->sync($src, self::$sut);

    $this->assertSame($builder, $result);

    $this->assertDirectoriesIdentical($expected, self::$sut);
  }

  public function testSyncWithRules(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');
    $expected = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');

    copy($expected . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT, self::$sut . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT);

    $builder = SnapshotBuilder::create()->withRules(Rules::create());
    $builder->sync($src, self::$sut);

    $this->assertDirectoriesIdentical($expected, self::$sut);
  }

  public function testPatchWithContentProcessor(): void {
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'baseline');
    $diff = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'diff');

    $processor_called = FALSE;
    $processor = function (string $content) use (&$processor_called): string {
      $processor_called = TRUE;
      return $content;
    };

    $builder = SnapshotBuilder::create()->withContentProcessor($processor);
    $builder->patch($baseline, $diff, self::$sut);

    $this->assertTrue($processor_called, 'Content processor should be called');
  }

  public function testPatchWithRulesHonoursSkip(): void {
    $baseline = self::$sut . DIRECTORY_SEPARATOR . 'baseline';
    $diffs = self::$sut . DIRECTORY_SEPARATOR . 'diffs';
    $destination = self::$sut . DIRECTORY_SEPARATOR . 'destination';
    mkdir($baseline, 0777, TRUE);
    mkdir($diffs, 0777, TRUE);

    file_put_contents($baseline . DIRECTORY_SEPARATOR . 'keep.txt', 'keep content');
    file_put_contents($baseline . DIRECTORY_SEPARATOR . 'skip.txt', 'secret content');

    $builder = SnapshotBuilder::create()->withRules(Rules::create()->skip('skip.txt'));
    $result = $builder->patch($baseline, $diffs, $destination);

    $this->assertSame($builder, $result);
    $this->assertFileExists($destination . DIRECTORY_SEPARATOR . 'keep.txt');
    $this->assertFileDoesNotExist($destination . DIRECTORY_SEPARATOR . 'skip.txt');
  }

  public function testSyncWithFileFilterExcludesFiles(): void {
    $src = self::$sut . DIRECTORY_SEPARATOR . 'src';
    $dst = self::$sut . DIRECTORY_SEPARATOR . 'dst';
    mkdir($src, 0777, TRUE);

    file_put_contents($src . DIRECTORY_SEPARATOR . 'keep.txt', 'keep content');
    file_put_contents($src . DIRECTORY_SEPARATOR . 'drop.txt', 'drop content');

    $builder = SnapshotBuilder::create()->withFileFilter(fn(IndexedFile $file): bool => $file->getBasename() !== 'drop.txt');
    $builder->sync($src, $dst);

    $this->assertFileExists($dst . DIRECTORY_SEPARATOR . 'keep.txt');
    $this->assertFileDoesNotExist($dst . DIRECTORY_SEPARATOR . 'drop.txt');
  }

  public function testFileFilterAndContentProcessorReceiveOwnValues(): void {
    $baseline = self::$sut . DIRECTORY_SEPARATOR . 'baseline';
    $diffs = self::$sut . DIRECTORY_SEPARATOR . 'diffs';
    $destination = self::$sut . DIRECTORY_SEPARATOR . 'destination';
    $synced = self::$sut . DIRECTORY_SEPARATOR . 'synced';
    mkdir($baseline, 0777, TRUE);
    mkdir($diffs, 0777, TRUE);

    file_put_contents($baseline . DIRECTORY_SEPARATOR . 'keep.txt', 'keep content');
    file_put_contents($baseline . DIRECTORY_SEPARATOR . 'drop.txt', 'drop content');

    $filtered = [];
    $processed = [];

    $builder = SnapshotBuilder::create()
      ->withFileFilter(function (IndexedFile $file) use (&$filtered): bool {
        $filtered[] = $file->getPathnameFromBasepath();
        return $file->getBasename() !== 'drop.txt';
      })
      ->withContentProcessor(function (string $content) use (&$processed): string {
        $processed[] = $content;
        return strtoupper($content);
      });

    $builder->patch($baseline, $diffs, $destination);

    $this->assertSame([], $filtered, 'File filter is not used by the patch operation');
    sort($processed);
    $this->assertSame(['drop content', 'keep content'], $processed);
    $this->assertStringEqualsFile($destination . DIRECTORY_SEPARATOR . 'keep.txt', 'KEEP CONTENT');

    $processed = [];

    $builder->sync($baseline, $synced);

    $this->assertSame([], $processed, 'Content processor is not used by the sync operation');
    sort($filtered);
    $this->assertSame(['drop.txt', 'keep.txt'], $filtered);
    $this->assertFileExists($synced . DIRECTORY_SEPARATOR . 'keep.txt');
    $this->assertFileDoesNotExist($synced . DIRECTORY_SEPARATOR . 'drop.txt');
  }

  public function testSyncWithRulesHonoursSkip(): void {
    $src = self::$sut . DIRECTORY_SEPARATOR . 'src';
    $dst = self::$sut . DIRECTORY_SEPARATOR . 'dst';
    mkdir($src, 0777, TRUE);

    file_put_contents($src . DIRECTORY_SEPARATOR . 'keep.txt', 'keep content');
    file_put_contents($src . DIRECTORY_SEPARATOR . 'skip.txt', 'secret content');

    $builder = SnapshotBuilder::create()->withRules(Rules::create()->skip('skip.txt'));
    $result = $builder->sync($src, $dst);

    $this->assertSame($builder, $result);
    $this->assertFileExists($dst . DIRECTORY_SEPARATOR . 'keep.txt');
    $this->assertFileDoesNotExist($dst . DIRECTORY_SEPARATOR . 'skip.txt');
  }

  public function testFluentOperationChaining(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');
    $expected = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');

    copy($expected . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT, self::$sut . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT);

    $builder = SnapshotBuilder::create()
      ->addSkip('custom/')
      ->sync($src, self::$sut);

    $this->assertInstanceOf(SnapshotBuilder::class, $builder);
  }

  public function testReusableBuilder(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');
    $dir1 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');
    $dir2 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');

    $builder = SnapshotBuilder::create()->withRules(Rules::phpProject());

    $index = $builder->scan($src);
    $this->assertInstanceOf(Index::class, $index);

    $comparer = $builder->compare($dir1, $dir2);
    $this->assertInstanceOf(Comparer::class, $comparer);
  }

}
