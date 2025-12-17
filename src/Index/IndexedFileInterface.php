<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Index;

/**
 * Interface for indexed file objects.
 */
interface IndexedFileInterface {

  /**
   * Sets the base path.
   *
   * @param string $basepath
   *   The base path to set.
   */
  public function setBasepath(string $basepath): void;

  /**
   * Gets the base path.
   *
   * @return string
   *   The base path.
   */
  public function getBasepath(): string;

  /**
   * Gets the content hash.
   *
   * @return string|null
   *   The content hash.
   */
  public function getHash(): ?string;

  /**
   * Gets the file content.
   *
   * @return string
   *   The file content.
   */
  public function getContent(): string;

  /**
   * Gets the path relative to the base path.
   *
   * @return string
   *   The path relative to the base path.
   */
  public function getPathFromBasepath(): string;

  /**
   * Gets the pathname relative to the base path.
   *
   * @return string
   *   The pathname relative to the base path.
   */
  public function getPathnameFromBasepath(): string;

  /**
   * Checks if content should be ignored in comparison.
   *
   * @return bool
   *   TRUE if content should be ignored, FALSE otherwise.
   */
  public function isIgnoreContent(): bool;

  /**
   * Sets whether content should be ignored in comparison.
   *
   * @param bool $ignore
   *   Whether to ignore the content.
   */
  public function setIgnoreContent(bool $ignore = TRUE): void;

  /**
   * Sets the file content.
   *
   * @param string|null $content
   *   The content to set, or NULL to load lazily.
   */
  public function setContent(?string $content): void;

  /**
   * Gets the full pathname.
   *
   * @return string
   *   The full pathname.
   */
  public function getPathname(): string;

  /**
   * Gets the basename.
   *
   * @return string
   *   The file basename.
   */
  public function getBasename(): string;

  /**
   * Checks if this is a directory.
   *
   * @return bool
   *   TRUE if directory, FALSE otherwise.
   */
  public function isDir(): bool;

  /**
   * Checks if this is a symbolic link.
   *
   * @return bool
   *   TRUE if symbolic link, FALSE otherwise.
   */
  public function isLink(): bool;

  /**
   * Gets the file size.
   *
   * @return int|false
   *   The file size or FALSE on failure.
   */
  public function getSize(): int|false;

  /**
   * Gets the inode number.
   *
   * @return int|false
   *   The inode number or FALSE on failure.
   */
  public function getInode(): int|false;

}
