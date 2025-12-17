<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Sync;

/**
 * Interface for directory syncer.
 */
interface SyncerInterface {

  /**
   * Sync files from one directory to another, respecting the .ignorecontent.
   *
   * @param string $dst
   *   Destination directory path.
   * @param int $permissions
   *   Directory permissions to use when creating directories.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function sync(string $dst, int $permissions = 0755, bool $copy_empty_dirs = FALSE): static;

}
