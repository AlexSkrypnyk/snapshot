<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Compare\Comparer;
use AlexSkrypnyk\Snapshot\Index\Index;
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

  public function testFluentChaining(): void {
    $builder = SnapshotBuilder::create()
      ->withRules(Rules::phpProject())
      ->addSkip('custom/')
      ->addIgnoreContent('custom.lock')
      ->withContentProcessor(fn($c) => $c);

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
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . '/../baseline');
    $dst = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'result');

    $builder = SnapshotBuilder::create();
    $result = $builder->diff($baseline, $dst, self::$sut);

    // Verify fluent return.
    $this->assertSame($builder, $result);

    // No diff should be generated for equal directories.
    $files = glob(self::$sut . '/*');
    $this->assertEmpty($files);
  }

  public function testPatch(): void {
    $baseline = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'baseline');
    $diff = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'diff');
    $expected = File::dir($this->locationsFixtureDir('diff') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'result');

    $builder = SnapshotBuilder::create();
    $result = $builder->patch($baseline, $diff, self::$sut);

    // Verify fluent return.
    $this->assertSame($builder, $result);

    $this->assertDirectoriesIdentical($expected, self::$sut);
  }

  public function testSync(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');
    $expected = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');

    copy($expected . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT, self::$sut . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT);

    $builder = SnapshotBuilder::create();
    $result = $builder->sync($src, self::$sut);

    // Verify fluent return.
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

  public function testFluentOperationChaining(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');
    $expected = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');

    copy($expected . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT, self::$sut . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT);

    // Test that void operations can be chained.
    $builder = SnapshotBuilder::create()
      ->addSkip('custom/')
      ->sync($src, self::$sut);

    $this->assertInstanceOf(SnapshotBuilder::class, $builder);
  }

  public function testReusableBuilder(): void {
    $src = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');
    $dir1 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory1');
    $dir2 = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal' . DIRECTORY_SEPARATOR . 'directory2');

    // Create builder once, use multiple times.
    $builder = SnapshotBuilder::create()->withRules(Rules::phpProject());

    $index = $builder->scan($src);
    $this->assertInstanceOf(Index::class, $index);

    $comparer = $builder->compare($dir1, $dir2);
    $this->assertInstanceOf(Comparer::class, $comparer);
  }

}
