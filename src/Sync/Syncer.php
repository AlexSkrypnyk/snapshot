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

    // File::copy() copies a symlink as a link, so a source directory that is
    // a symlink is resolved to copy its contents.
    File::copy(File::realpath($this->srcIndex->getDirectory()), $dst, $permissions, $copy_empty_dirs, $paths, File::SYMLINK_MODE_RELATIVE);

    return $this;
  }

}
