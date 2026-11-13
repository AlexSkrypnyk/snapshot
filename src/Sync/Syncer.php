<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Sync;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Index\IndexedFile;
use AlexSkrypnyk\Snapshot\Index\IndexedFileInterface;
use AlexSkrypnyk\Snapshot\Index\IndexInterface;

/**
 * Handles file synchronization between directories.
 */
class Syncer implements SyncerInterface {

  /**
   * Constructs a Syncer instance.
   *
   * @param \AlexSkrypnyk\Snapshot\Index\IndexInterface $srcIndex
   *   The source index to sync from.
   */
  public function __construct(
    protected IndexInterface $srcIndex,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function sync(string $dst, int $permissions = 0755, bool $copy_empty_dirs = FALSE, bool $placeholder_ignored_content = FALSE): static {
    File::mkdir($dst, $permissions);

    foreach ($this->srcIndex->getFiles() as $file) {
      // getFiles() allows a transform callback to return non-file values;
      // skip anything that is not a file since none is passed here.
      if (!$file instanceof IndexedFileInterface) {
        // @codeCoverageIgnoreStart
        continue;
        // @codeCoverageIgnoreEnd
      }

      $absolute_dst_path = $dst . DIRECTORY_SEPARATOR . $file->getPathnameFromBasepath();

      if ($placeholder_ignored_content && $file->isIgnoreContent()) {
        File::dump($absolute_dst_path, $file->getContent());
        continue;
      }

      static::copy($file, $absolute_dst_path, $permissions, $copy_empty_dirs);
    }

    return $this;
  }

  /**
   * Copies an indexed file to a destination path.
   *
   * A symlink with a target inside the source tree is recreated to point at
   * the same place inside the destination, because readlink() on Windows
   * returns an absolute path into the source tree.
   *
   * @param \AlexSkrypnyk\Snapshot\Index\IndexedFileInterface $file
   *   The file to copy.
   * @param string $dst
   *   The destination path.
   * @param int $permissions
   *   Permissions to set on created directories.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   */
  public static function copy(IndexedFileInterface $file, string $dst, int $permissions = 0755, bool $copy_empty_dirs = FALSE): void {
    $target = NULL;

    if ($file->isLink()) {
      // The ignore-content marker replaces the link target, so a fresh file
      // reads the target from disk.
      $link = $file->isIgnoreContent() ? new IndexedFile($file->getPathname(), $file->getBasepath()) : $file;
      $target = $link->getContent();
    }

    if ($target === NULL || IndexedFile::isAbsolutePath($target)) {
      File::copy($file->getPathname(), $dst, $permissions, $copy_empty_dirs);

      return;
    }

    $parent = File::mkdir(dirname($dst), $permissions);

    if (is_link($dst) || file_exists($dst)) {
      return;
    }

    // PHP on Windows fails to create a link to a relative target, so the
    // target is anchored at the link's directory inside the destination.
    if (PHP_OS_FAMILY === 'Windows') {
      $target = $parent . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
    }

    symlink($target, $dst);
  }

}
