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
   * File::copy() keeps a link inside the copied tree relative only in
   * File::SYMLINK_MODE_RELATIVE. The mode is global, so it is set for this
   * copy and the caller's mode is restored.
   *
   * @param string $src
   *   Source directory path.
   * @param string $dst
   *   Destination directory path.
   * @param array<int, string> $paths
   *   Paths of files and symlinks relative to $src, with "/" separators.
   * @param int $permissions
   *   Permissions to set on created directories.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   *
   * @throws \AlexSkrypnyk\File\Exception\FileException
   *   When a symlink cannot be read or created.
   */
  public static function copyPaths(string $src, string $dst, array $paths, int $permissions = 0755, bool $copy_empty_dirs = FALSE): void {
    if ($paths === []) {
      return;
    }

    // The filter is asked about directories before the paths inside them, so
    // every ancestor of a path is let through.
    $selected = array_flip($paths);
    $dirs = [];

    foreach ($paths as $path) {
      for ($dir = dirname($path); $dir !== '.' && !isset($dirs[$dir]); $dir = dirname($dir)) {
        $dirs[$dir] = TRUE;
      }
    }

    // File::copy() copies a symlink as a link, so a source directory that is
    // a symlink is resolved to copy its contents.
    $src = is_link($src) ? (realpath($src) ?: $src) : $src;

    $mode = File::getSymlinkMode();
    File::setSymlinkMode(File::SYMLINK_MODE_RELATIVE);

    try {
      File::copy($src, $dst, $permissions, $copy_empty_dirs, fn(string $path, bool $is_dir): bool => $is_dir ? isset($dirs[$path]) : isset($selected[$path]));
    }
    finally {
      File::setSymlinkMode($mode);
    }
  }

}
