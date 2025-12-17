<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Index;

use AlexSkrypnyk\Snapshot\Rules\RulesInterface;

/**
 * Interface for directory index.
 */
interface IndexInterface {

  /**
   * Gets the indexed files.
   *
   * @param callable|null $cb
   *   Optional callback to transform each file.
   *
   * @return array<string, \AlexSkrypnyk\Snapshot\Index\IndexedFileInterface>
   *   Array of files indexed by path relative to base directory.
   */
  public function getFiles(?callable $cb = NULL): array;

  /**
   * Gets the directory being indexed.
   *
   * @return string
   *   The directory path.
   */
  public function getDirectory(): string;

  /**
   * Gets the rules used by this index.
   *
   * @return \AlexSkrypnyk\Snapshot\Rules\RulesInterface
   *   The rules instance.
   */
  public function getRules(): RulesInterface;

}
