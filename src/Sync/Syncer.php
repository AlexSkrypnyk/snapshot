<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Sync;

use AlexSkrypnyk\File\File;
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

    $paths = [];

    foreach ($this->srcIndex->getFiles() as $file) {
      // getFiles() allows a transform callback to return non-file values;
      // skip anything that is not a file since none is passed here.
      if (!$file instanceof IndexedFileInterface) {
        // @codeCoverageIgnoreStart
        continue;
        // @codeCoverageIgnoreEnd
      }

      if ($placeholder_ignored_content && $file->isIgnoreContent()) {
        File::dump($dst . DIRECTORY_SEPARATOR . $file->getPathnameFromBasepath(), $file->getContent());
        continue;
      }

      $paths[] = $file->getPathnameFromBasepath();
    }

    static::copyPaths($this->srcIndex->getDirectory(), $dst, $paths, $permissions, $copy_empty_dirs);

    return $this;
  }

  /**
   * Copies paths of a directory with links kept inside the destination.
   *
   * File::copyPaths() keeps a link inside the copied tree relative only in
   * File::SYMLINK_MODE_RELATIVE. The mode is global, so it is set for this
   * copy and the caller's mode is restored.
   *
   * @param string $src
   *   Source directory path.
   * @param string $dst
   *   Destination directory path.
   * @param array<int, string> $paths
   *   Paths relative to $src.
   * @param int $permissions
   *   Permissions to set on created directories.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When a symlink cannot be read or created.
   */
  public static function copyPaths(string $src, string $dst, array $paths, int $permissions = 0755, bool $copy_empty_dirs = FALSE): void {
    $mode = File::getSymlinkMode();
    File::setSymlinkMode(File::SYMLINK_MODE_RELATIVE);

    try {
      File::copyPaths($src, $dst, $paths, $permissions, $copy_empty_dirs);
    }
    finally {
      File::setSymlinkMode($mode);
    }
  }

}
