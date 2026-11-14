<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\Snapshot;
use AlexSkrypnyk\Snapshot\Sync\Syncer;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Copies symlinks through every snapshot operation and every link layout.
 *
 * Each case builds a source tree, copies it, removes the source and checks
 * that every copied link still resolves to the expected content.
 */
#[CoversClass(Snapshot::class)]
#[CoversClass(Syncer::class)]
final class SymlinkPermutationsTest extends UnitTestCase {

  /**
   * Link and target paths for each target location, relative to the tree.
   *
   * A NULL target is outside the tree.
   *
   * @var array<string, array{string, string|null}>
   */
  protected const array LOCATIONS = [
    'same directory' => ['a/b/link', 'a/b/target'],
    'child directory' => ['a/link', 'a/b/target'],
    'parent directory' => ['a/b/link', 'a/target'],
    'sibling directory' => ['a/b/link', 'a/c/target'],
    'tree root' => ['a/b/link', 'target'],
    'deep descendant' => ['a/link', 'a/b/c/d/target'],
    'distant cousin' => ['a/b/c/link', 'x/y/target'],
    'link at tree root' => ['link', 'a/b/target'],
    'outside the tree' => ['a/b/link', NULL],
  ];

  #[DataProvider('dataProviderPermutations')]
  public function testPermutations(string $operation, string $kind, bool $absolute, string $location, bool $chain): void {
    [$link, $target] = self::LOCATIONS[$location];
    $tree = self::$sut . '/tree';
    $outside = self::$sut . '/outside/target';

    $target_path = $target === NULL ? $outside : $tree . '/' . $target;
    $this->createTarget($target_path, $kind);

    File::mkdir(dirname($tree . '/' . $link));
    File::dump($tree . '/marker.txt', 'marker');

    if ($absolute || $target === NULL) {
      symlink($target_path, $tree . '/' . $link);
    }
    else {
      $this->createRelativeSymlink($tree . '/' . $link, self::relative(dirname($link), $target));
    }

    $links = [$link];

    if ($chain) {
      // A link to the link, so the copy resolves 2 hops.
      $this->createRelativeSymlink($tree . '/chain', $link);
      $links[] = 'chain';
    }

    $dst = $this->runOperation($operation, $tree, $links);

    File::remove($tree);

    foreach ($links as $copied) {
      $this->assertTrue(is_link($dst . '/' . $copied), sprintf('%s is a symlink.', $copied));

      $expected = $target === NULL ? $outside : $dst . '/' . $target;
      $this->assertSame(realpath($expected), realpath($dst . '/' . $copied), sprintf('%s resolves to %s.', $copied, $target ?? 'the outside target'));
      $this->assertSame('content', $this->readTarget($dst . '/' . $copied, $kind), sprintf('%s reads the target content.', $copied));
    }
  }

  public static function dataProviderPermutations(): \Iterator {
    foreach (['sync', 'patch', 'diff', 'roundtrip'] as $operation) {
      foreach (['file', 'directory'] as $kind) {
        foreach ([FALSE, TRUE] as $absolute) {
          foreach (array_keys(self::LOCATIONS) as $location) {
            // An outside target is always absolute.
            if (!$absolute && self::LOCATIONS[$location][1] === NULL) {
              continue;
            }

            foreach ([FALSE, TRUE] as $chain) {
              $name = sprintf('%s, %s, %s, %s%s', $operation, $kind, $absolute ? 'absolute' : 'relative', $location, $chain ? ', chained' : '');
              yield $name => [$operation, $kind, $absolute, $location, $chain];
            }
          }
        }
      }
    }
  }

  #[DataProvider('dataProviderNames')]
  public function testNames(string $link, string $target): void {
    $tree = self::$sut . '/tree';
    File::dump($tree . '/' . $target, 'content');
    File::mkdir(dirname($tree . '/' . $link));
    $this->createRelativeSymlink($tree . '/' . $link, self::relative(dirname($link), $target));

    $dst = $this->runOperation('sync', $tree);
    File::remove($tree);

    $this->assertTrue(is_link($dst . '/' . $link));
    $this->assertSame('content', file_get_contents($dst . '/' . $link));
  }

  public static function dataProviderNames(): \Iterator {
    yield 'spaces' => ['dir with space/link name.txt', 'other dir/target name.txt'];
    yield 'non-ASCII' => ['dïr/lïnk.txt', 'tärget/fïle.txt'];
    yield 'target named 0' => ['a/link', 'a/0'];
    yield 'dot in directory name' => ['a.b/link', 'c.d/target.e'];
    yield 'leading dash' => ['-a/-link', '-b/-target'];
  }

  public function testLinkThroughDirectoryLink(): void {
    $tree = self::$sut . '/tree';
    File::dump($tree . '/real/file.txt', 'content');
    $this->createRelativeSymlink($tree . '/alias', 'real');
    File::mkdir($tree . '/a');
    $this->createRelativeSymlink($tree . '/a/link.txt', '../alias/file.txt');

    $dst = $this->runOperation('sync', $tree);
    File::remove($tree);

    $this->assertTrue(is_link($dst . '/a/link.txt'));
    $this->assertSame('content', file_get_contents($dst . '/a/link.txt'));
  }

  public function testLinkToTreeRoot(): void {
    $tree = self::$sut . '/tree';
    File::dump($tree . '/file.txt', 'content');
    File::mkdir($tree . '/a/b');
    $this->createRelativeSymlink($tree . '/a/b/root', '../..');

    $dst = $this->runOperation('sync', $tree);
    File::remove($tree);

    $this->assertTrue(is_link($dst . '/a/b/root'));
    $this->assertSame(realpath($dst), realpath($dst . '/a/b/root'));
  }

  public function testBrokenLinkIsNotCopied(): void {
    $tree = self::$sut . '/tree';
    File::dump($tree . '/file.txt', 'content');
    File::dump($tree . '/gone.txt', 'gone');
    $this->createRelativeSymlink($tree . '/link.txt', 'gone.txt');
    unlink($tree . '/gone.txt');

    $dst = $this->runOperation('sync', $tree);

    $this->assertFileExists($dst . '/file.txt');
    $this->assertFalse(is_link($dst . '/link.txt'));
  }

  public function testLinkToSkippedTarget(): void {
    $tree = self::$sut . '/tree';
    File::dump($tree . '/kept.txt', 'kept');
    File::dump($tree . '/skipped.txt', 'skipped');
    $this->createRelativeSymlink($tree . '/link.txt', 'skipped.txt');

    $dst = self::$sut . '/dst';
    Snapshot::sync($tree, $dst, rules: Rules::create()->skip('skipped.txt'));

    $this->assertFileDoesNotExist($dst . '/skipped.txt');
    $this->assertTrue(is_link($dst . '/link.txt'), 'A link to a skipped target is still copied.');

    // readlink() fails for a dangling link on Windows, so the target is
    // checked by creating it in the destination.
    File::dump($dst . '/skipped.txt', 'created later');
    $this->assertSame(realpath($dst . '/skipped.txt'), realpath($dst . '/link.txt'));
    $this->assertSame('created later', file_get_contents($dst . '/link.txt'));
  }

  /**
   * Runs a snapshot operation that copies $tree and returns the copy.
   *
   * The roundtrip operation diffs $tree against a baseline holding the same
   * tree without $links, then patches the baseline with that diff. Each new
   * link reaches the diff without its target, which stays in the baseline.
   *
   * @param string $operation
   *   One of 'sync', 'patch', 'diff' or 'roundtrip'.
   * @param string $tree
   *   Source tree.
   * @param array<int, string> $links
   *   Links in $tree, relative to it.
   *
   * @return string
   *   Directory holding the copy.
   */
  protected function runOperation(string $operation, string $tree, array $links = []): string {
    $dst = self::$sut . '/dst';
    $empty = self::$sut . '/empty';
    File::mkdir($empty);

    if ($operation === 'roundtrip') {
      $baseline = self::$sut . '/baseline';
      $diffs = self::$sut . '/diffs';
      Snapshot::sync($tree, $baseline);
      File::remove(array_map(fn(string $link): string => $baseline . '/' . $link, $links));
      Snapshot::diff($baseline, $tree, $diffs);
      Snapshot::patch($baseline, $diffs, $dst);

      return $dst;
    }

    match ($operation) {
      'sync' => Snapshot::sync($tree, $dst),
      'patch' => Snapshot::patch($empty, $tree, $dst),
      'diff' => Snapshot::diff($empty, $tree, $dst),
      default => throw new \InvalidArgumentException('Unknown operation ' . $operation),
    };

    return $dst;
  }

  /**
   * Creates a file target, or a directory target holding a file.
   */
  protected function createTarget(string $path, string $kind): void {
    if ($kind === 'file') {
      File::dump($path, 'content');

      return;
    }

    File::dump($path . '/file.txt', 'content');
  }

  /**
   * Reads the content a copied link leads to.
   */
  protected function readTarget(string $link, string $kind): string {
    $path = $kind === 'file' ? $link : $link . '/file.txt';

    return (string) @file_get_contents($path);
  }

  /**
   * Builds the relative path from a directory to a path, both in the tree.
   */
  protected static function relative(string $from_dir, string $to): string {
    $from = $from_dir === '.' ? [] : explode('/', $from_dir);
    $to_parts = explode('/', $to);

    while ($from !== [] && $to_parts !== [] && $from[0] === $to_parts[0]) {
      array_shift($from);
      array_shift($to_parts);
    }

    return implode('/', array_merge(array_fill(0, count($from), '..'), $to_parts));
  }

}
