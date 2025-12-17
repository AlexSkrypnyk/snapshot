<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Compare;

use AlexSkrypnyk\Snapshot\Index\IndexedFileInterface;

/**
 * Interface for directory comparer.
 */
interface ComparerInterface extends RenderableInterface {

  /**
   * Compares the left and right directories.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function compare(): static;

  /**
   * Adds a file from the left (source) directory to the diff collection.
   *
   * @param \AlexSkrypnyk\Snapshot\Index\IndexedFileInterface $file
   *   The file to add.
   */
  public function addLeftFile(IndexedFileInterface $file): void;

  /**
   * Adds a file from the right (destination) directory to the diff collection.
   *
   * @param \AlexSkrypnyk\Snapshot\Index\IndexedFileInterface $file
   *   The file to add.
   */
  public function addRightFile(IndexedFileInterface $file): void;

  /**
   * Get an array of absent left diffs.
   *
   * @param callable|null $cb
   *   Optional transformation callback.
   *
   * @return array<string, \AlexSkrypnyk\Snapshot\Compare\Diff|mixed>
   *   An array of diffs that are present in the right directory but not in
   *   the left.
   */
  public function getAbsentLeftDiffs(?callable $cb = NULL): array;

  /**
   * Get an array of absent right diffs.
   *
   * @param callable|null $cb
   *   Optional transformation callback.
   *
   * @return array<string, \AlexSkrypnyk\Snapshot\Compare\Diff|mixed>
   *   An array of diffs that are present in the left directory but not in
   *   the right.
   */
  public function getAbsentRightDiffs(?callable $cb = NULL): array;

  /**
   * Get an array of content diffs.
   *
   * @param callable|null $cb
   *   Optional transformation callback.
   *
   * @return array<string, \AlexSkrypnyk\Snapshot\Compare\Diff|mixed>
   *   An array of diffs that are present in both directories but have different
   *   content.
   */
  public function getContentDiffs(?callable $cb = NULL): array;

}
