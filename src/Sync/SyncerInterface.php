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
   * @param bool $placeholder_ignored_content
   *   Whether to write a fixed placeholder in place of the content of files
   *   matched by an ignore-content rule. Keeps volatile files stable in a
   *   committed baseline. When FALSE, every file is copied verbatim.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function sync(string $dst, int $permissions = 0755, bool $copy_empty_dirs = FALSE, bool $placeholder_ignored_content = FALSE): static;

}
