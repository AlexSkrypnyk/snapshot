<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Index;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Snapshot;

/**
 * Collect and index of the files in the directory respecting the rules.
 *
 * @see Rules::parse()
 */
class Index implements IndexInterface {

  /**
   * Files indexed by the path from the base directory.
   *
   * @var array<string, \AlexSkrypnyk\Snapshot\Index\IndexedFile>|null
   */
  protected ?array $files = NULL;

  /**
   * The rules to apply when indexing files.
   */
  protected Rules $rules;

  public function __construct(
    protected string $directory,
    ?Rules $rules = NULL,
    protected mixed $beforeMatchContent = NULL,
  ) {
    $this->rules = $rules ??
      (
      File::exists($directory . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT)
        ? Rules::fromFile($directory . DIRECTORY_SEPARATOR . Snapshot::IGNORECONTENT)
        : new Rules()
      );
    $this->rules->addSkip(Snapshot::IGNORECONTENT)->addSkip('.git/');
  }

  /**
   * {@inheritdoc}
   */
  public function getFiles(?callable $cb = NULL): array {
    if (is_null($this->files)) {
      $this->scan();
    }

    $this->files ??= [];

    if (is_callable($cb)) {
      foreach ($this->files as $path => $file) {
        $this->files[$path] = $cb($file);
      }
    }

    return $this->files;
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectory(): string {
    return $this->directory;
  }

  /**
   * {@inheritdoc}
   */
  public function getRules(): Rules {
    return $this->rules;
  }

  /**
   * Scan files in directory respecting rules and optionally using a callback.
   */
  protected function scan(): static {
    $this->files = [];

    // Pre-cache pattern arrays for faster matching.
    $global_patterns = $this->rules->getGlobal();
    $include_patterns = $this->rules->getInclude();
    $skip_patterns = $this->rules->getSkip();
    $ignore_content_patterns = $this->rules->getIgnoreContent();

    foreach ($this->iterator($this->directory) as $resource) {
      if (!$resource instanceof \SplFileInfo) {
        // @codeCoverageIgnoreStart
        continue;
        // @codeCoverageIgnoreEnd
      }

      // Skip directories, but not links to directories.
      if ($resource->isDir() && !$resource->isLink()) {
        continue;
      }

      // Skip links that point to non-existing files (broken links).
      if ($resource->isLink() && !$resource->getRealPath()) {
        continue;
      }

      $file = new IndexedFile($resource->getPathname(), $this->directory);

      // Fast path: check basename against global patterns first.
      $basename = $file->getBasename();
      if ($this->matchesAnyPattern($basename, $global_patterns)) {
        continue;
      }

      $relative_path = $file->getPathnameFromBasepath();

      // Check include patterns (if any exist).
      $is_included = FALSE;
      if (!empty($include_patterns)) {
        $is_included = $this->matchesAnyPattern($relative_path, $include_patterns);
      }

      // Only check skip if not explicitly included.
      if (!$is_included && $this->matchesAnyPattern($relative_path, $skip_patterns)) {
        continue;
      }

      // Check ignore content patterns.
      $is_ignore_content = FALSE;
      if (!$is_included && $this->matchesAnyPattern($relative_path, $ignore_content_patterns)) {
        $is_ignore_content = TRUE;
      }

      if ($is_ignore_content) {
        $file->setIgnoreContent();
      }
      elseif ($file->isDir() && !$file->isLink()) {
        // @codeCoverageIgnoreStart
        $file->setIgnoreContent();
        // @codeCoverageIgnoreEnd
      }
      elseif (is_callable($this->beforeMatchContent)) {
        // Allow to skip files that do not match the content by returning FALSE
        // from the callback.
        $ret = call_user_func($this->beforeMatchContent, $file);
        if ($ret === FALSE) {
          continue;
        }
      }

      $this->files[$relative_path] = $file;
    }

    ksort($this->files);

    return $this;
  }

  /**
   * Checks if a path matches any of the given patterns.
   *
   * This is a helper method to reduce code duplication and improve
   * readability in the scan() method.
   *
   * @param string $path
   *   The path to check.
   * @param array<int, string> $patterns
   *   The patterns to match against.
   *
   * @return bool
   *   TRUE if the path matches any pattern, FALSE otherwise.
   */
  protected function matchesAnyPattern(string $path, array $patterns): bool {
    foreach ($patterns as $pattern) {
      if (static::isPathMatchesPattern($path, $pattern)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks if a path matches a pattern.
   *
   * Handles several types of patterns:
   * - Directory patterns ending with / (match all files inside directory)
   * - Direct child patterns with /* (match only files directly in directory)
   * - Standard file glob patterns (using fnmatch)
   *
   * @param string $path
   *   The path to check.
   * @param string $pattern
   *   The pattern to match against.
   *
   * @return bool
   *   TRUE if the path matches the pattern, FALSE otherwise.
   */
  protected static function isPathMatchesPattern(string $path, string $pattern): bool {
    // Match directory pattern (e.g., "dir/").
    if (str_ends_with($pattern, DIRECTORY_SEPARATOR)) {
      return str_starts_with($path, $pattern);
    }

    // Match direct children (e.g., "dir/*").
    if (str_contains($pattern, '/*')) {
      $parent_dir = rtrim($pattern, '/*') . DIRECTORY_SEPARATOR;

      return str_starts_with($path, $parent_dir) && substr_count($path, DIRECTORY_SEPARATOR) === substr_count($parent_dir, DIRECTORY_SEPARATOR);
    }

    // @phpcs:ignore Drupal.Functions.DiscouragedFunctions.Discouraged
    return fnmatch($pattern, $path);
  }

  /**
   * Get the iterator for the directory.
   *
   * @return \RecursiveIteratorIterator<\RecursiveDirectoryIterator>
   *   The iterator.
   */
  protected function iterator(string $directory): \RecursiveIteratorIterator {
    return new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
  }

}
