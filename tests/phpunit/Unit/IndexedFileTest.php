<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\Snapshot\Exception\SnapshotException;
use AlexSkrypnyk\Snapshot\Index\IndexedFile;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(IndexedFile::class)]
final class IndexedFileTest extends UnitTestCase {

  #[DataProvider('dataProviderConstructor')]
  public function testConstructor(?string $content, string $expected_content, bool $content_from_file): void {
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    $file_content = 'file content on disk';
    file_put_contents($file_path, $file_content);

    $indexed_file = new IndexedFile($file_path, self::$sut, $content);

    $this->assertSame(self::$sut, $indexed_file->getBasepath());
    $this->assertSame('test.txt', $indexed_file->getPathnameFromBasepath());

    if ($content_from_file) {
      $this->assertSame($file_content, $indexed_file->getContent());
    }
    else {
      $this->assertSame($expected_content, $indexed_file->getContent());
    }
  }

  public static function dataProviderConstructor(): \Iterator {
    yield 'no content provided' => [NULL, '', TRUE];
    yield 'custom content provided' => ['custom content', 'custom content', FALSE];
    yield 'empty string content' => ['', '', FALSE];
  }

  #[DataProvider('dataProviderSetBasepath')]
  public function testSetBasepath(bool $add_trailing_slash): void {
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $basepath = $add_trailing_slash ? self::$sut . DIRECTORY_SEPARATOR : self::$sut;
    $indexed_file = new IndexedFile($file_path, $basepath);

    $this->assertSame(self::$sut, $indexed_file->getBasepath());
  }

  public static function dataProviderSetBasepath(): \Iterator {
    yield 'no trailing slash' => [FALSE];
    yield 'with trailing slash' => [TRUE];
  }

  #[DataProvider('dataProviderGetHash')]
  public function testGetHash(?string $preset_content, string $file_content, string $expected_hash_source): void {
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, $file_content);

    $indexed_file = new IndexedFile($file_path, self::$sut, $preset_content);

    $this->assertSame(sha1($expected_hash_source), $indexed_file->getHash());
  }

  public static function dataProviderGetHash(): \Iterator {
    yield 'hash from file' => [NULL, 'file content', 'file content'];
    yield 'hash from preset content' => ['preset', 'file content', 'preset'];
    yield 'hash from empty preset' => ['', 'file content', ''];
    yield 'hash from empty file' => [NULL, '', ''];
  }

  #[DataProvider('dataProviderGetContent')]
  public function testGetContent(?string $preset_content, string $file_content, string $expected): void {
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, $file_content);

    $indexed_file = new IndexedFile($file_path, self::$sut, $preset_content);

    $this->assertSame($expected, $indexed_file->getContent());
    // Call twice to test caching.
    $this->assertSame($expected, $indexed_file->getContent());
  }

  public static function dataProviderGetContent(): \Iterator {
    yield 'content from file' => [NULL, 'file content', 'file content'];
    yield 'content from preset' => ['preset content', 'file content', 'preset content'];
    yield 'empty file' => [NULL, '', ''];
    yield 'empty preset' => ['', 'file content', ''];
  }

  #[DataProvider('dataProviderPathFromBasepath')]
  public function testPathFromBasepath(string $subpath, string $expected_path, string $expected_pathname): void {
    $dir = self::$sut;
    if (!empty($subpath)) {
      $dir = self::$sut . DIRECTORY_SEPARATOR . $subpath;
      mkdir($dir, 0777, TRUE);
    }
    $file_path = $dir . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $indexed_file = new IndexedFile($file_path, self::$sut);

    $this->assertSame($expected_path, $indexed_file->getPathFromBasepath());
    $this->assertSame($expected_pathname, $indexed_file->getPathnameFromBasepath());
  }

  public static function dataProviderPathFromBasepath(): \Iterator {
    yield 'root level' => ['', '', 'test.txt'];
    yield 'one level deep' => ['subdir', 'subdir', 'subdir' . DIRECTORY_SEPARATOR . 'test.txt'];
    yield 'two levels deep' => ['sub1' . DIRECTORY_SEPARATOR . 'sub2', 'sub1' . DIRECTORY_SEPARATOR . 'sub2', 'sub1' . DIRECTORY_SEPARATOR . 'sub2' . DIRECTORY_SEPARATOR . 'test.txt'];
  }

  #[DataProvider('dataProviderIgnoreContent')]
  public function testIgnoreContent(bool $set_ignore, bool $expected_is_ignore, string $expected_content): void {
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $indexed_file = new IndexedFile($file_path, self::$sut);

    if ($set_ignore) {
      $indexed_file->setIgnoreContent(TRUE);
    }

    $this->assertSame($expected_is_ignore, $indexed_file->isIgnoreContent());

    if ($set_ignore) {
      $this->assertSame($expected_content, $indexed_file->getContent());
    }
  }

  public static function dataProviderIgnoreContent(): \Iterator {
    yield 'not ignored' => [FALSE, FALSE, 'test content'];
    yield 'ignored' => [TRUE, TRUE, IndexedFile::CONTENT_IGNORED_MARKER];
  }

  #[DataProvider('dataProviderSetContent')]
  public function testSetContent(?string $initial, ?string $new_content, string $expected, string $expected_hash_source): void {
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    $file_content = 'file on disk';
    file_put_contents($file_path, $file_content);

    $indexed_file = new IndexedFile($file_path, self::$sut, $initial);
    $indexed_file->setContent($new_content);

    if ($new_content === NULL) {
      // When NULL, content loads from file.
      $this->assertSame($file_content, $indexed_file->getContent());
      $this->assertSame(sha1($file_content), $indexed_file->getHash());
    }
    else {
      $this->assertSame($expected, $indexed_file->getContent());
      $this->assertSame(sha1($expected_hash_source), $indexed_file->getHash());
    }
  }

  public static function dataProviderSetContent(): \Iterator {
    yield 'set new content' => [NULL, 'new content', 'new content', 'new content'];
    yield 'replace content' => ['old', 'new', 'new', 'new'];
    yield 'set to null reloads from file' => ['preset', NULL, '', ''];
    yield 'set empty string' => [NULL, '', '', ''];
  }

  #[DataProvider('dataProviderSymlink')]
  public function testSymlink(bool $external_target, string $expected_content_pattern): void {
    if (!function_exists('symlink')) {
      $this->markTestSkipped('Symlinks are not supported on this system.');
    }

    if ($external_target) {
      $external_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('external_', TRUE);
      mkdir($external_dir, 0777, TRUE);
      $target_path = $external_dir . DIRECTORY_SEPARATOR . 'target.txt';
    }
    else {
      $target_path = self::$sut . DIRECTORY_SEPARATOR . 'target.txt';
      $external_dir = NULL;
    }

    file_put_contents($target_path, 'target content');

    $link_path = self::$sut . DIRECTORY_SEPARATOR . 'link.txt';
    symlink($target_path, $link_path);

    try {
      $indexed_file = new IndexedFile($link_path, self::$sut);

      $this->assertTrue($indexed_file->isLink());

      if ($external_target) {
        $this->assertSame($target_path, $indexed_file->getContent());
      }
      else {
        $this->assertSame('target.txt', $indexed_file->getContent());
        $this->assertSame(sha1('target.txt'), $indexed_file->getHash());
      }
    }
    finally {
      unlink($link_path);
      if ($external_dir !== NULL) {
        unlink($target_path);
        rmdir($external_dir);
      }
    }
  }

  public static function dataProviderSymlink(): \Iterator {
    yield 'internal target' => [FALSE, 'target.txt'];
    yield 'external target' => [TRUE, ''];
  }

  #[DataProvider('dataProviderSplFileInfoMethods')]
  public function testSplFileInfoMethods(string $method): void {
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $indexed_file = new IndexedFile($file_path, self::$sut);

    $this->assertNotNull($indexed_file->$method());
  }

  public static function dataProviderSplFileInfoMethods(): \Iterator {
    yield 'getPathname' => ['getPathname'];
    yield 'getBasename' => ['getBasename'];
    yield 'getPath' => ['getPath'];
    yield 'getFilename' => ['getFilename'];
    yield 'getExtension' => ['getExtension'];
    yield 'getRealPath' => ['getRealPath'];
    yield 'getSize' => ['getSize'];
    yield 'getInode' => ['getInode'];
  }

  #[DataProvider('dataProviderFileTypes')]
  public function testFileTypes(string $type, bool $expected_is_dir, bool $expected_is_link): void {
    if ($type === 'link' && !function_exists('symlink')) {
      $this->markTestSkipped('Symlinks are not supported on this system.');
    }

    $path = self::$sut . DIRECTORY_SEPARATOR . 'test_' . $type;

    switch ($type) {
      case 'file':
        file_put_contents($path, 'content');
        break;

      case 'dir':
        mkdir($path, 0777, TRUE);
        break;

      case 'link':
        $target = self::$sut . DIRECTORY_SEPARATOR . 'target.txt';
        file_put_contents($target, 'target');
        symlink($target, $path);
        break;
    }

    $indexed_file = new IndexedFile($path, self::$sut);

    $this->assertSame($expected_is_dir, $indexed_file->isDir());
    $this->assertSame($expected_is_link, $indexed_file->isLink());
  }

  public static function dataProviderFileTypes(): \Iterator {
    yield 'regular file' => ['file', FALSE, FALSE];
    yield 'directory' => ['dir', TRUE, FALSE];
    yield 'symlink' => ['link', FALSE, TRUE];
  }

  public function testStripBasepathThrowsOnInvalidPath(): void {
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $indexed_file = new IndexedFile($file_path, self::$sut);
    $indexed_file->setBasepath('/different/path');

    $this->expectException(SnapshotException::class);
    $this->expectExceptionMessage('does not start with basepath');

    $indexed_file->getPathnameFromBasepath();
  }

  public function testContentIgnoredMarkerConstant(): void {
    $this->assertSame('content_ignored', IndexedFile::CONTENT_IGNORED_MARKER);
  }

  public function testLargeFileHashing(): void {
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'large.txt';
    // Create a file larger than 8192 bytes to test chunked hashing.
    $content = str_repeat('a', 10000);
    file_put_contents($file_path, $content);

    $indexed_file = new IndexedFile($file_path, self::$sut);

    $this->assertSame(sha1($content), $indexed_file->getHash());
  }

  public function testSetIgnoreContentToggle(): void {
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $indexed_file = new IndexedFile($file_path, self::$sut);

    $this->assertFalse($indexed_file->isIgnoreContent());

    $indexed_file->setIgnoreContent(TRUE);
    $this->assertTrue($indexed_file->isIgnoreContent());

    $indexed_file->setIgnoreContent(FALSE);
    $this->assertFalse($indexed_file->isIgnoreContent());
  }

}
