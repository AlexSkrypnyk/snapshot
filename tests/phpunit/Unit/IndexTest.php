<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Index\Index;
use AlexSkrypnyk\Snapshot\Index\IndexedFile;
use AlexSkrypnyk\Snapshot\Index\IndexedFileInterface;
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\Snapshot;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Index::class)]
#[CoversClass(IndexedFile::class)]
final class IndexTest extends UnitTestCase {

  #[DataProvider('dataProviderIndexScan')]
  public function testIndexScan(?callable $rules, ?callable $before_match_content, array $expected): void {
    $dir = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal_advanced' . DIRECTORY_SEPARATOR . 'directory2');

    $rules = is_callable($rules) ? $rules() : $rules;

    $index = new Index($dir, $rules, $before_match_content);
    $this->callProtectedMethod($index, 'scan');

    $this->assertSame($expected, array_keys($index->getFiles()));
  }

  public static function dataProviderIndexScan(): \Iterator {
    $defaults = [
      'd32f2_symlink_deep.txt',
      'dir1_flat/d1f1.txt',
      'dir1_flat/d1f1_symlink.txt',
      'dir1_flat/d1f2.txt',
      'dir2_flat/d2f1.txt',
      'dir2_flat/d2f2.txt',
      'dir3_subdirs/d3f1-ignored.txt',
      'dir3_subdirs/d3f2-ignored.txt',
      'dir3_subdirs/dir31/d31f1-ignored.txt',
      'dir3_subdirs/dir31/d31f2-ignored.txt',
      'dir3_subdirs/dir31/f3-new-file-ignore-everywhere.txt',
      'dir3_subdirs/dir32-unignored/d32f1.txt',
      'dir3_subdirs/dir32-unignored/d32f1_symlink.txt',
      'dir3_subdirs/dir32-unignored/d32f2-ignore-ext-only-dst.log',
      'dir3_subdirs/dir32-unignored/d32f2.txt',
      'dir3_subdirs/f3-new-file-ignore-everywhere.txt',
      'dir3_subdirs_symlink',
      'dir3_subdirs_symlink_ignored',
      'dir4_full_ignore/d4f1.txt',
      'dir5_content_ignore/d5f1-ignored-changed-content.txt',
      'dir5_content_ignore/d5f2-unignored-content.txt',
      'dir5_content_ignore/dir51/d51f1-changed-content.txt',
      'f1.txt',
      'f2.txt',
      'f2_symlink.txt',
      'f3-new-file-ignore-everywhere.txt',
      'f4-ignore-ext.log',
      'f5-new-file-ignore-ext.log',
    ];
    yield [NULL, NULL, $defaults];
    yield [NULL, fn(IndexedFile $file): null => NULL, $defaults];
    yield [NULL, fn(IndexedFile $file): true => TRUE, $defaults];
    yield [NULL, fn(IndexedFile $file): string => $file->getContent(), $defaults];
    yield [NULL, fn(IndexedFile $file): false => FALSE, []];
    yield [
      NULL,
      fn(IndexedFile $file): bool => str_contains($file->getContent(), 'f2l1'),
      [
        'd32f2_symlink_deep.txt',
        'dir1_flat/d1f2.txt',
        'dir2_flat/d2f2.txt',
        'dir3_subdirs/d3f2-ignored.txt',
        'dir3_subdirs/dir31/d31f2-ignored.txt',
        'dir3_subdirs/dir32-unignored/d32f2-ignore-ext-only-dst.log',
        'dir3_subdirs/dir32-unignored/d32f2.txt',
        'dir5_content_ignore/d5f2-unignored-content.txt',
        'f2.txt',
      ],
    ];
    yield [
      fn(): Rules => (new Rules())
        ->addGlobal('*.log')
        ->addGlobal('f3-new-file-ignore-everywhere.txt')
        ->addGlobal('dir3_subdirs_symlink_ignored')
        ->addSkip('dir2_flat/*')
        ->addSkip('dir3_subdirs/*')
        ->addSkip('dir4_full_ignore/')
        ->addInclude('dir3_subdirs/dir32-unignored/')
        ->addInclude('dir3_subdirs_symlink/dir32-unignored/')
        ->addInclude('dir5_content_ignore/d5f2-unignored-content.txt')
        ->addIgnoreContent('dir5_content_ignore/'),
      NULL,
      [
        'd32f2_symlink_deep.txt',
        'dir1_flat/d1f1.txt',
        'dir1_flat/d1f1_symlink.txt',
        'dir1_flat/d1f2.txt',
        'dir3_subdirs/dir31/d31f1-ignored.txt',
        'dir3_subdirs/dir31/d31f2-ignored.txt',
        'dir3_subdirs/dir32-unignored/d32f1.txt',
        'dir3_subdirs/dir32-unignored/d32f1_symlink.txt',
        'dir3_subdirs/dir32-unignored/d32f2-ignore-ext-only-dst.log',
        'dir3_subdirs/dir32-unignored/d32f2.txt',
        'dir3_subdirs_symlink',
        'dir5_content_ignore/d5f1-ignored-changed-content.txt',
        'dir5_content_ignore/d5f2-unignored-content.txt',
        'dir5_content_ignore/dir51/d51f1-changed-content.txt',
        'f1.txt',
        'f2.txt',
        'f2_symlink.txt',
      ],
    ];
  }

  public function testGetDirectoryAndRules(): void {
    $dir = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal_advanced' . DIRECTORY_SEPARATOR . 'directory2');
    $rules = new Rules();

    $index = new Index($dir, $rules);

    $this->assertSame($dir, $index->getDirectory());
    $this->assertSame($rules, $index->getRules());
  }

  public function testGetFilesWithCallback(): void {
    $dir = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal_advanced' . DIRECTORY_SEPARATOR . 'directory2');

    $index = new Index($dir);

    $result = $index->getFiles(fn(IndexedFile $file): string => 'Modified content for ' . $file->getPathname());

    foreach ($result as $modified_content) {
      // @phpstan-ignore cast.string
      $this->assertStringStartsWith('Modified content for ', (string) $modified_content);
    }

    $index = new Index($dir);
    $result = $index->getFiles();

    $this->assertNotEmpty($result);
  }

  public function testGetFilesCalledTwice(): void {
    $dir = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal_advanced' . DIRECTORY_SEPARATOR . 'directory2');

    $index = new Index($dir);

    $result1 = $index->getFiles();
    $result2 = $index->getFiles();

    $this->assertSame($result1, $result2);
    $this->assertNotEmpty($result1);
  }

  public function testGetFilesWithCallbackDoesNotMutateIndex(): void {
    $dir = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal_advanced' . DIRECTORY_SEPARATOR . 'directory2');

    $index = new Index($dir);

    $index->getFiles(fn(IndexedFile $file): string => 'transformed:' . $file->getFilename());

    $this->assertContainsOnlyInstancesOf(IndexedFile::class, $index->getFiles());
  }

  public function testConstructorWithIgnoreContentFile(): void {
    $test_dir = $this->locationsTmp() . DIRECTORY_SEPARATOR . 'test_dir_with_ignorecontent';
    File::mkdir($test_dir);

    $ignorecontent_file = $test_dir . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT;
    file_put_contents($ignorecontent_file, "*.txt\n!important.txt\n^content-only.txt");

    $index = new Index($test_dir);

    $rules = $index->getRules();
    $this->assertInstanceOf(Rules::class, $rules);

    $skip_rules = $rules->getSkip();
    $this->assertContains(Snapshot::IGNORECONTENT, $skip_rules);
    $this->assertContains('.git/', $skip_rules);

    unlink($ignorecontent_file);
    rmdir($test_dir);
  }

  public function testBeforeMatchContentCallback(): void {
    $dir = File::dir($this->locationsFixtureDir('compare') . DIRECTORY_SEPARATOR . 'files_equal_advanced' . DIRECTORY_SEPARATOR . 'directory2');

    $before_match_content =
        (fn(IndexedFile $file): bool => str_contains($file->getContent(), 'specific content'));

    $test_file = $dir . DIRECTORY_SEPARATOR . 'test_before_match.txt';
    file_put_contents($test_file, 'This file contains specific content that should be included');

    try {
      $index = new Index($dir, NULL, $before_match_content);

      $files = $index->getFiles();

      $this->assertArrayHasKey('test_before_match.txt', $files);

      $control_index = new Index($dir);
      $control_files = $control_index->getFiles();

      $this->assertGreaterThan(count($files), count($control_files));
    }
    finally {
      if (file_exists($test_file)) {
        unlink($test_file);
      }
    }
  }

  public function testIterator(): void {
    $test_dir = $this->locationsTmp() . DIRECTORY_SEPARATOR . 'test_iterator_dir';
    File::mkdir($test_dir);

    $test_files = [
      $test_dir . DIRECTORY_SEPARATOR . 'file1.txt',
      $test_dir . DIRECTORY_SEPARATOR . 'file2.log',
      $test_dir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'file3.txt',
    ];

    File::mkdir($test_dir . DIRECTORY_SEPARATOR . 'subdir');
    file_put_contents($test_files[0], 'Test content 1');
    file_put_contents($test_files[1], 'Test content 2');
    file_put_contents($test_files[2], 'Test content 3');

    try {
      $index = new Index($test_dir);

      $iterator = $this->callProtectedMethod($index, 'iterator', [$test_dir]);

      $this->assertInstanceOf(\RecursiveIteratorIterator::class, $iterator);

      $found_files = [];
      foreach ($iterator as $file) {
        if ($file instanceof \SplFileInfo) {
          $found_files[] = $file->getPathname();
        }
      }

      foreach ($test_files as $test_file) {
        $this->assertContains($test_file, $found_files);
      }
    }
    finally {
      foreach ($test_files as $test_file) {
        if (file_exists($test_file)) {
          unlink($test_file);
        }
      }

      if (is_dir($test_dir . DIRECTORY_SEPARATOR . 'subdir')) {
        rmdir($test_dir . DIRECTORY_SEPARATOR . 'subdir');
      }

      if (is_dir($test_dir)) {
        rmdir($test_dir);
      }
    }
  }

  public function testScanWithSymlinks(): void {
    if (!function_exists('symlink')) {
      $this->markTestSkipped('Symlinks are not supported on this system.');
    }

    $test_dir = $this->locationsTmp() . DIRECTORY_SEPARATOR . 'test_symlinks_dir';
    File::mkdir($test_dir);
    File::mkdir($test_dir . DIRECTORY_SEPARATOR . 'dir1');

    $file1 = $test_dir . DIRECTORY_SEPARATOR . 'dir1' . DIRECTORY_SEPARATOR . 'file1.txt';
    file_put_contents($file1, 'Original file content');

    $symlink_file = $test_dir . DIRECTORY_SEPARATOR . 'symlink_file.txt';
    symlink($file1, $symlink_file);

    $symlink_dir = $test_dir . DIRECTORY_SEPARATOR . 'symlink_dir';
    symlink($test_dir . DIRECTORY_SEPARATOR . 'dir1', $symlink_dir);

    // A broken symlink covers handling of non-existent targets.
    $broken_symlink = $test_dir . DIRECTORY_SEPARATOR . 'broken_symlink';
    symlink($test_dir . DIRECTORY_SEPARATOR . 'nonexistent_file.txt', $broken_symlink);

    try {
      $index = new Index($test_dir);

      $files = $index->getFiles();

      $symlink_file_entry = $this->assertIndexedFile($files, 'symlink_file.txt');

      $symlink_dir_entry = $this->assertIndexedFile($files, 'symlink_dir');

      // Whether a broken symlink is included depends on implementation
      // details, so neither its presence nor its absence is asserted here.
      // The original file is indexed through both the direct path and the
      // symlink.
      $dir1_file1_entry = $this->assertIndexedFile($files, 'dir1/file1.txt');

      $this->assertSame('Original file content', $dir1_file1_entry->getContent());

      $this->assertTrue($symlink_file_entry->isLink());
      $this->assertTrue($symlink_dir_entry->isLink());
    }
    finally {
      if (file_exists($symlink_file)) {
        unlink($symlink_file);
      }
      if (file_exists($symlink_dir)) {
        unlink($symlink_dir);
      }
      if (file_exists($broken_symlink)) {
        unlink($broken_symlink);
      }
      if (file_exists($file1)) {
        unlink($file1);
      }
      if (is_dir($test_dir . DIRECTORY_SEPARATOR . 'dir1')) {
        rmdir($test_dir . DIRECTORY_SEPARATOR . 'dir1');
      }
      if (is_dir($test_dir)) {
        rmdir($test_dir);
      }
    }
  }

  #[DataProvider('dataProviderIsPathMatchesPattern')]
  public function testIsPathMatchesPattern(string $path, string $pattern, bool $expected): void {
    $result = self::callProtectedMethod(Index::class, 'pathMatchesPattern', [$path, $pattern]);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderIsPathMatchesPattern(): \Iterator {
    // Exact match.
    yield ['dir/file.txt', 'dir/file.txt', TRUE];
    // Directory match.
    yield ['dir/subdir/file.txt', 'dir/', TRUE];
    yield ['otherdir/file.txt', 'dir/', FALSE];
    // Direct child match.
    yield ['dir/file.txt', 'dir/*', TRUE];
    yield ['dir/subdir/file.txt', 'dir/*', FALSE];
    yield ['dir/another.txt', 'dir/*', TRUE];
    // Wildcard match.
    yield ['dir/file.txt', '*.txt', TRUE];
    yield ['dir/file.md', '*.txt', FALSE];
    // Nested paths do not match.
    yield ['dir/nested/file.txt', 'dir/*.txt', FALSE];
    // Pattern with a wildcard in the middle.
    yield ['dir/abc_file.txt', 'dir/abc_*.txt', TRUE];
    yield ['dir/xyz_file.txt', 'dir/abc_*.txt', FALSE];
    // Matching subdirectories.
    yield ['dir/subdir/file.txt', 'dir/subdir/*', TRUE];
    yield ['dir/anotherdir/file.txt', 'dir/subdir/*', FALSE];
    // Complex fnmatch pattern.
    yield ['dir/file.txt', 'dir/f*.txt', TRUE];
    yield ['dir/afile.txt', 'dir/f*.txt', FALSE];
  }

  public function testScanDirectorySkipLogic(): void {
    $test_class = new class($this->locationsTmp()) extends Index {

      protected function iterator(string $directory): \RecursiveIteratorIterator {
        // SELF_FIRST makes the iterator return directories during iteration.
        return new \RecursiveIteratorIterator(
          new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
          \RecursiveIteratorIterator::SELF_FIRST
        );
      }

    };

    $test_dir = $this->locationsTmp() . DIRECTORY_SEPARATOR . 'test_dir_skip';
    File::mkdir($test_dir);
    File::mkdir($test_dir . DIRECTORY_SEPARATOR . 'sub_directory');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'test_file.txt', 'content');

    try {
      $reflection = new \ReflectionClass($test_class);
      $property = $reflection->getProperty('directory');
      $property->setValue($test_class, $test_dir);

      $files = $test_class->getFiles();

      $this->assertArrayHasKey('test_file.txt', $files);

      $this->assertArrayNotHasKey('sub_directory', $files);
    }
    finally {
      File::rmdir($test_dir);
    }
  }

  public function testIncludePatternsOverrideGlobalAndSkipPatterns(): void {
    $test_dir = $this->locationsTmp() . DIRECTORY_SEPARATOR . 'test_include_overrides';
    File::mkdir($test_dir);
    File::mkdir($test_dir . DIRECTORY_SEPARATOR . 'sub');
    File::mkdir($test_dir . DIRECTORY_SEPARATOR . 'cache');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'important.txt', 'content');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'important.log', 'content');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'other.log', 'content');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'important.log', 'content');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'other.log', 'content');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'keep.txt', 'content');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'other.txt', 'content');

    try {
      $rules = (new Rules())
        ->addGlobal('important.txt')
        ->addGlobal('*.log')
        ->addSkip('cache/')
        ->addInclude('important.txt')
        ->addInclude('important.log')
        ->addInclude('cache/keep.txt');

      $files = (new Index($test_dir, $rules))->getFiles();

      $this->assertArrayHasKey('important.txt', $files);
      $this->assertArrayHasKey('important.log', $files);
      $this->assertArrayHasKey('sub/important.log', $files);
      $this->assertArrayNotHasKey('other.log', $files);
      $this->assertArrayNotHasKey('sub/other.log', $files);
      $this->assertArrayHasKey('cache/keep.txt', $files);
      $this->assertArrayNotHasKey('cache/other.txt', $files);
    }
    finally {
      File::rmdir($test_dir);
    }
  }

  public function testIncludeContentPatternsOverrideIgnoreContentPatterns(): void {
    $test_dir = $this->locationsTmp() . DIRECTORY_SEPARATOR . 'test_include_content_overrides';
    File::mkdir($test_dir);
    File::mkdir($test_dir . DIRECTORY_SEPARATOR . 'dir');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'file.txt', 'content');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'skipped.txt', 'content');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'dir' . DIRECTORY_SEPARATOR . 'keep.txt', 'content');
    file_put_contents($test_dir . DIRECTORY_SEPARATOR . 'dir' . DIRECTORY_SEPARATOR . 'sibling.txt', 'content');

    try {
      $rules = (new Rules())
        ->addIgnoreContent('file.txt')
        ->addIgnoreContent('dir/')
        ->addIgnoreContent('skipped.txt')
        ->addSkip('skipped.txt')
        ->addIncludeContent('file.txt')
        ->addIncludeContent('dir/keep.txt')
        ->addInclude('skipped.txt');

      $files = (new Index($test_dir, $rules))->getFiles();

      // '^file.txt' + '!^file.txt': content is compared, not ignored.
      $this->assertArrayHasKey('file.txt', $files);
      $this->assertFalse($files['file.txt']->isIgnoreContent());

      // '^dir/' + '!^dir/keep.txt': the named file's content is compared,
      // the sibling's is not.
      $this->assertArrayHasKey('dir/keep.txt', $files);
      $this->assertFalse($files['dir/keep.txt']->isIgnoreContent());
      $this->assertArrayHasKey('dir/sibling.txt', $files);
      $this->assertTrue($files['dir/sibling.txt']->isIgnoreContent());

      // Skip + plain '!skipped.txt': the file is indexed, but its content
      // is still ignored.
      $this->assertArrayHasKey('skipped.txt', $files);
      $this->assertTrue($files['skipped.txt']->isIgnoreContent());
    }
    finally {
      File::rmdir($test_dir);
    }
  }

  protected function assertIndexedFile(array $files, string $key): IndexedFileInterface {
    $this->assertArrayHasKey($key, $files);

    $file = $files[$key];
    if (!$file instanceof IndexedFileInterface) {
      $this->fail(sprintf('Expected %s to be indexed as a file.', $key));
    }

    return $file;
  }

  public static function locationsTmp(): string {
    return self::$tmp;
  }

}
