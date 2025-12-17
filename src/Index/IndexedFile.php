<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Index;

use AlexSkrypnyk\Snapshot\Exception\SnapshotException;

/**
 * Extended SplFileInfo class with additional file handling capabilities.
 */
class IndexedFile extends \SplFileInfo implements IndexedFileInterface {

  /**
   * Marker used to flag when content should be ignored in comparison.
   */
  public const CONTENT_IGNORED_MARKER = 'content_ignored';

  /**
   * Base path used for relative path calculations.
   */
  protected string $basepath;

  /**
   * Content hash value.
   */
  protected ?string $hash = NULL;

  /**
   * File content.
   */
  protected ?string $content = NULL;

  /**
   * Whether content has been loaded.
   */
  protected bool $contentLoaded = FALSE;

  /**
   * Constructs a new IndexedFile object.
   *
   * @param string $filename
   *   The file name.
   * @param string $base
   *   The base path.
   * @param string|null $content
   *   Optional content to set.
   */
  public function __construct(string $filename, string $base, ?string $content = NULL) {
    parent::__construct($filename);

    $this->setBasepath($base);
    $this->setContent($content);
  }

  /**
   * {@inheritdoc}
   */
  public function setBasepath(string $basepath): void {
    $this->basepath = rtrim($basepath, DIRECTORY_SEPARATOR);
  }

  /**
   * {@inheritdoc}
   */
  public function getBasepath(): string {
    return $this->basepath;
  }

  /**
   * {@inheritdoc}
   */
  public function getHash(): ?string {
    if (!$this->contentLoaded) {
      $this->loadContent();
    }
    return $this->hash;
  }

  /**
   * {@inheritdoc}
   */
  public function getContent(): string {
    if (!$this->contentLoaded) {
      $this->loadContent();
    }

    // Lazy load actual content for files if not already loaded.
    if ($this->content === NULL && !$this->isLink()) {
      $this->content = (string) file_get_contents($this->getRealPath());
    }

    return $this->content ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getPathFromBasepath(): string {
    return static::stripBasepath($this->getBasepath(), $this->getPath());
  }

  /**
   * {@inheritdoc}
   */
  public function getPathnameFromBasepath(): string {
    return static::stripBasepath($this->getBasepath(), $this->getPathname());
  }

  /**
   * {@inheritdoc}
   */
  public function isIgnoreContent(): bool {
    // If content is explicitly set, check it.
    // If not loaded yet, it can't be the ignore marker.
    return $this->contentLoaded && $this->content === static::CONTENT_IGNORED_MARKER;
  }

  /**
   * {@inheritdoc}
   */
  public function setIgnoreContent(bool $ignore = TRUE): void {
    $this->setContent($ignore ? static::CONTENT_IGNORED_MARKER : NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function setContent(?string $content): void {
    if (!is_null($content)) {
      $this->content = $content;
      $this->hash = $this->hash($this->content);
      $this->contentLoaded = TRUE;
    }
    else {
      // Defer content loading until needed.
      $this->contentLoaded = FALSE;
      $this->content = NULL;
      $this->hash = NULL;
    }
  }

  /**
   * Loads the file content.
   */
  protected function loadContent(): void {
    if ($this->contentLoaded) {
      return;
    }

    if ($this->isLink()) {
      $link_target = $this->getLinkTarget();
      // If the link target is absolute and within basepath, make it relative.
      if (str_starts_with($link_target, $this->basepath)) {
        $this->content = static::stripBasepath($this->basepath, $link_target);
      }
      else {
        $this->content = $link_target;
      }
      $this->hash = $this->hash($this->content);
    }
    else {
      // For files, compute hash incrementally without loading entire content.
      $this->hash = $this->hashFile($this->getRealPath());
      // Content remains NULL until explicitly requested via getContent().
    }

    $this->contentLoaded = TRUE;
  }

  /**
   * Creates a hash for the given content.
   *
   * Uses SHA-1 instead of MD5 for better performance.
   *
   * @param string $content
   *   The content to hash.
   *
   * @return string
   *   A hash of the content.
   */
  protected function hash(string $content): string {
    return sha1($content);
  }

  /**
   * Computes a hash for a file using incremental processing.
   *
   * This method reads the file in chunks to avoid loading the entire
   * content into memory, which is more efficient for large files.
   *
   * @param string $filepath
   *   The path to the file.
   *
   * @return string
   *   A hash of the file content.
   *
   * @throws \AlexSkrypnyk\Snapshot\Exception\SnapshotException
   *   If the file cannot be opened.
   */
  protected function hashFile(string $filepath): string {
    $context = hash_init('sha1');
    $handle = fopen($filepath, 'rb');

    if ($handle === FALSE) {
      // @codeCoverageIgnoreStart
      throw new SnapshotException('Cannot open file: ' . $filepath);
      // @codeCoverageIgnoreEnd
    }

    while (!feof($handle)) {
      $data = fread($handle, 8192);
      if ($data !== FALSE) {
        hash_update($context, $data);
      }
    }

    fclose($handle);
    return hash_final($context);
  }

  /**
   * Removes the base path from a path.
   *
   * @param string $basepath
   *   The base path to remove.
   * @param string $path
   *   The full path.
   *
   * @return string
   *   The path with base path removed.
   *
   * @throws \AlexSkrypnyk\Snapshot\Exception\SnapshotException
   *   If the path does not start with the base path.
   */
  protected static function stripBasepath(string $basepath, string $path): string {
    if (!str_starts_with($path, $basepath)) {
      throw new SnapshotException(sprintf('Path %s does not start with basepath %s', $path, $basepath));
    }

    return ltrim(str_replace($basepath, '', $path), DIRECTORY_SEPARATOR);
  }

}
