<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Compare;

use AlexSkrypnyk\Snapshot\Index\IndexedFileInterface;

/**
 * Interface for file difference.
 */
interface DiffInterface extends RenderableInterface {

  /**
   * Sets the left (source) file.
   *
   * @param \AlexSkrypnyk\Snapshot\Index\IndexedFileInterface $file
   *   The file to set.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function setLeft(IndexedFileInterface $file): static;

  /**
   * Sets the right (destination) file.
   *
   * @param \AlexSkrypnyk\Snapshot\Index\IndexedFileInterface $file
   *   The file to set.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function setRight(IndexedFileInterface $file): static;

  /**
   * Gets the left (source) file.
   *
   * @return \AlexSkrypnyk\Snapshot\Index\IndexedFileInterface
   *   The left file.
   */
  public function getLeft(): IndexedFileInterface;

  /**
   * Gets the right (destination) file.
   *
   * @return \AlexSkrypnyk\Snapshot\Index\IndexedFileInterface
   *   The right file.
   */
  public function getRight(): IndexedFileInterface;

  /**
   * Checks if the left file exists.
   *
   * @return bool
   *   TRUE if the left file exists, FALSE otherwise.
   */
  public function existsLeft(): bool;

  /**
   * Checks if the right file exists.
   *
   * @return bool
   *   TRUE if the right file exists, FALSE otherwise.
   */
  public function existsRight(): bool;

  /**
   * Checks if the left and right files have the same content.
   *
   * @return bool
   *   TRUE if the content is the same, FALSE otherwise.
   */
  public function isSameContent(): bool;

}
