<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Patch;

use AlexSkrypnyk\Snapshot\Index\IndexedFileInterface;

/**
 * Interface for unified diff patcher.
 */
interface PatcherInterface {

  /**
   * Check if a file is a patch file.
   *
   * @param string $filepath
   *   The file path.
   *
   * @return bool
   *   TRUE if the file is a patch file, FALSE otherwise.
   */
  public static function isPatchFile(string $filepath): bool;

  /**
   * Add a patch file.
   *
   * @param \AlexSkrypnyk\Snapshot\Index\IndexedFileInterface $file
   *   The patch file.
   *
   * @return $this
   */
  public function addPatchFile(IndexedFileInterface $file): static;

  /**
   * Add a diff.
   *
   * @param string|array<int, string> $diff
   *   The diff content.
   * @param string $pathname
   *   The source file path.
   *
   * @return $this
   */
  public function addDiff(string|array $diff, string $pathname): static;

  /**
   * Apply the patch.
   *
   * @return int
   *   The number of files patched.
   */
  public function patch(): int;

}
