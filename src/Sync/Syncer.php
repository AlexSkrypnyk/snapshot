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
  public function sync(string $dst, int $permissions = 0755, bool $copy_empty_dirs = FALSE): static {
    File::mkdir($dst, $permissions);

    foreach ($this->srcIndex->getFiles() as $file) {
      // getFiles() allows a transform callback to return non-file values;
      // skip anything that is not a file since none is passed here.
      if (!$file instanceof IndexedFileInterface) {
        continue;
      }

      $absolute_src_path = $file->getPathname();
      $absolute_dst_path = $dst . DIRECTORY_SEPARATOR . $file->getPathnameFromBasepath();
      if ($file->isIgnoreContent()) {
        File::dump($absolute_dst_path, $file->getContent());
      }
      else {
        File::copy($absolute_src_path, $absolute_dst_path, $permissions, $copy_empty_dirs);
      }
    }

    return $this;
  }

}
