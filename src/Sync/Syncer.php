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
   * A symlink with a relative target is recreated from that target, because
   * readlink() on Windows returns an absolute path into the source tree.
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
    if (!$file->isLink() || $file->isIgnoreContent() || IndexedFile::isAbsolutePath($file->getContent())) {
      File::copy($file->getPathname(), $dst, $permissions, $copy_empty_dirs);

      return;
    }

    $parent = File::mkdir(dirname($dst), $permissions);

    if (is_link($dst) || file_exists($dst)) {
      return;
    }

    // Windows resolves a relative target against the current directory.
    $current_dir = File::cwd();
    chdir($parent);
    symlink($file->getContent(), basename($dst));
    chdir($current_dir);
  }

}
