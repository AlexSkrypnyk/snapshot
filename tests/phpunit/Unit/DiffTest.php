<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\Snapshot\Compare\Diff;
use AlexSkrypnyk\Snapshot\Index\IndexedFile;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Diff::class)]
final class DiffTest extends UnitTestCase {

  public function testSetGetLeft(): void {
    $diff = new Diff();
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $file_info = new IndexedFile($file_path, self::$sut);

    $result = $diff->setLeft($file_info);

    $this->assertInstanceOf(Diff::class, $result);
    $this->assertSame($file_info, $diff->getLeft());
  }

  public function testSetGetRight(): void {
    $diff = new Diff();
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');

    $file_info = new IndexedFile($file_path, self::$sut);

    $result = $diff->setRight($file_info);

    $this->assertInstanceOf(Diff::class, $result);
    $this->assertSame($file_info, $diff->getRight());
  }

  public function testExistsLeft(): void {
    $diff = new Diff();

    $this->assertFalse($diff->existsLeft());

    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');
    $file_info = new IndexedFile($file_path, self::$sut);

    $diff->setLeft($file_info);
    $this->assertTrue($diff->existsLeft());
  }

  public function testExistsRight(): void {
    $diff = new Diff();

    $this->assertFalse($diff->existsRight());

    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');
    $file_info = new IndexedFile($file_path, self::$sut);

    $diff->setRight($file_info);
    $this->assertTrue($diff->existsRight());
  }

  public function testIsSameContentWhenMissingFiles(): void {
    $diff = new Diff();

    // No files set.
    $this->assertFalse($diff->isSameContent());

    // Only left file set.
    $file_path = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    file_put_contents($file_path, 'test content');
    $file_info = new IndexedFile($file_path, self::$sut);

    $diff->setLeft($file_info);
    $this->assertFalse($diff->isSameContent());

    // Reset and set only right file.
    $diff = new Diff();
    $diff->setRight($file_info);
    $this->assertFalse($diff->isSameContent());
  }

  public function testIsSameContentWithSameContent(): void {
    $diff = new Diff();

    $file_path1 = self::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = self::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    file_put_contents($file_path1, 'test content');
    file_put_contents($file_path2, 'test content');

    $file_info1 = new IndexedFile($file_path1, self::$sut);
    $file_info2 = new IndexedFile($file_path2, self::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $this->assertTrue($diff->isSameContent());
  }

  public function testIsSameContentWithDifferentContent(): void {
    $diff = new Diff();

    $file_path1 = self::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = self::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    file_put_contents($file_path1, 'test content 1');
    file_put_contents($file_path2, 'test content 2');

    $file_info1 = new IndexedFile($file_path1, self::$sut);
    $file_info2 = new IndexedFile($file_path2, self::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $this->assertFalse($diff->isSameContent());
  }

  public function testIsSameContentWithIgnoreContent(): void {
    $diff = new Diff();

    $file_path1 = self::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = self::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    file_put_contents($file_path1, 'test content 1');
    file_put_contents($file_path2, 'test content 2');

    $file_info1 = new IndexedFile($file_path1, self::$sut);
    $file_info2 = new IndexedFile($file_path2, self::$sut);

    // Set left file to ignore content.
    $file_info1->setIgnoreContent(TRUE);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $this->assertTrue($diff->isSameContent());

    // Reset and set right file to ignore content.
    $diff = new Diff();
    $file_info1 = new IndexedFile($file_path1, self::$sut);
    $file_info2 = new IndexedFile($file_path2, self::$sut);
    $file_info2->setIgnoreContent(TRUE);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $this->assertTrue($diff->isSameContent());
  }

  public function testRender(): void {
    $diff = new Diff();

    $file_path1 = self::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = self::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    file_put_contents($file_path1, 'test content');
    file_put_contents($file_path2, 'test content');

    $file_info1 = new IndexedFile($file_path1, self::$sut);
    $file_info2 = new IndexedFile($file_path2, self::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    // Test with default renderer.
    $rendered = $diff->render();
    $this->assertSame('test content', $rendered);

    // Test with custom renderer.
    $custom_renderer = (fn(Diff $diff, array $options = []): string => 'Custom rendered content');

    $rendered = $diff->render([], $custom_renderer);
    $this->assertSame('Custom rendered content', $rendered);
  }

  public function testDoRender(): void {
    $diff = new Diff();

    $file_path1 = self::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = self::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    // Test with same content.
    file_put_contents($file_path1, 'test content');
    file_put_contents($file_path2, 'test content');

    $file_info1 = new IndexedFile($file_path1, self::$sut);
    $file_info2 = new IndexedFile($file_path2, self::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $result = self::callProtectedMethod(Diff::class, 'doRender', [$diff]);
    $this->assertEquals('test content', $result);

    // Test with different content.
    file_put_contents($file_path1, "line1\n");
    file_put_contents($file_path2, "line2\n");

    $file_info1 = new IndexedFile($file_path1, self::$sut);
    $file_info2 = new IndexedFile($file_path2, self::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    $result = self::callProtectedMethod(Diff::class, 'doRender', [$diff]);
    assert(is_string($result));
    $this->assertStringContainsString('@@ -1 +1 @@', $result);
    $this->assertStringContainsString('-line1', $result);
    $this->assertStringContainsString('+line2', $result);
  }

  public function testIsSameContentWithDifferentSizes(): void {
    $diff = new Diff();

    $file_path1 = self::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = self::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    // Create files with different sizes.
    file_put_contents($file_path1, 'short');
    file_put_contents($file_path2, 'much longer content');

    $file_info1 = new IndexedFile($file_path1, self::$sut);
    $file_info2 = new IndexedFile($file_path2, self::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    // Should return FALSE without reading content (optimization).
    $this->assertFalse($diff->isSameContent(), 'Files with different sizes should not be same');
  }

  public function testIsSameContentWithSameSizeDifferentContent(): void {
    $diff = new Diff();

    $file_path1 = self::$sut . DIRECTORY_SEPARATOR . 'test1.txt';
    $file_path2 = self::$sut . DIRECTORY_SEPARATOR . 'test2.txt';

    // Create files with same size but different content.
    file_put_contents($file_path1, 'abcde');
    file_put_contents($file_path2, 'fghij');

    $file_info1 = new IndexedFile($file_path1, self::$sut);
    $file_info2 = new IndexedFile($file_path2, self::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    // Should return FALSE after checking hash.
    $this->assertFalse($diff->isSameContent(), 'Files with same size but different content should not be same');
  }

  public function testIsSameContentWithSymlinks(): void {
    $diff = new Diff();

    $target_path = self::$sut . DIRECTORY_SEPARATOR . 'target.txt';
    file_put_contents($target_path, 'target content');

    // Create two symlinks pointing to the same target.
    $link_path1 = self::$sut . DIRECTORY_SEPARATOR . 'link1.txt';
    $link_path2 = self::$sut . DIRECTORY_SEPARATOR . 'link2.txt';
    symlink($target_path, $link_path1);
    symlink($target_path, $link_path2);

    $file_info1 = new IndexedFile($link_path1, self::$sut);
    $file_info2 = new IndexedFile($link_path2, self::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    // Should compare symlink targets correctly.
    $this->assertTrue($diff->isSameContent(), 'Symlinks with same targets should be same');
  }

  public function testIsSameContentSkipsSizeCheckForSymlinks(): void {
    $diff = new Diff();

    // Create symlinks to directories (getSize() would fail).
    $dir1 = self::$sut . DIRECTORY_SEPARATOR . 'dir1';
    $dir2 = self::$sut . DIRECTORY_SEPARATOR . 'dir2';
    mkdir($dir1);
    mkdir($dir2);

    $link_path1 = self::$sut . DIRECTORY_SEPARATOR . 'link1';
    $link_path2 = self::$sut . DIRECTORY_SEPARATOR . 'link2';
    symlink($dir1, $link_path1);
    symlink($dir2, $link_path2);

    $file_info1 = new IndexedFile($link_path1, self::$sut);
    $file_info2 = new IndexedFile($link_path2, self::$sut);

    $diff->setLeft($file_info1);
    $diff->setRight($file_info2);

    // Should not throw exception from getSize() and compare by hash.
    $this->assertFalse($diff->isSameContent(), 'Symlinks to different directories should not be same');
  }

}
