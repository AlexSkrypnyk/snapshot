<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Index;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\Snapshot;

/**
 * Collects and indexes the files in the directory, respecting the rules.
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
    if ($this->files === NULL) {
      $this->scan();
    }

    $this->files ??= [];

    if (!is_callable($cb)) {
      return $this->files;
    }

    // Transform a copy so the callback never mutates the cached index.
    $files = [];
    foreach ($this->files as $path => $file) {
      $files[$path] = $cb($file);
    }

    return $files;
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

    // Pre-cache pattern arrays for faster matching. A shared Rules object
    // is re-seeded with the default skip rules by each constructor, so
    // dedupe to avoid matching a pattern twice per file.
    $global_patterns = array_unique($this->rules->getGlobal());
    $include_patterns = array_unique($this->rules->getInclude());
    $skip_patterns = array_unique($this->rules->getSkip());
    $ignore_content_patterns = array_unique($this->rules->getIgnoreContent());
    $include_content_patterns = array_unique($this->rules->getIncludeContent());

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

      $basename = $file->getBasename();
      $relative_path = $file->getPathnameFromBasepath();

      // $is_included must be known before the global check, so an include pattern can override it too.
      $is_included = FALSE;
      if (!empty($include_patterns)) {
        $is_included = $this->matchesAnyPattern($relative_path, $include_patterns) || $this->matchesAnyPattern($basename, $include_patterns);
      }

      if (!$is_included && $this->matchesAnyPattern($basename, $global_patterns)) {
        continue;
      }

      if (!$is_included && $this->matchesAnyPattern($relative_path, $skip_patterns)) {
        continue;
      }

      $is_ignore_content = $this->matchesAnyPattern($relative_path, $ignore_content_patterns);
      if ($is_ignore_content && !empty($include_content_patterns)) {
        $is_ignore_content = !$this->matchesAnyPattern($relative_path, $include_content_patterns);
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
      if (static::pathMatchesPattern($path, $pattern)) {
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
  protected static function pathMatchesPattern(string $path, string $pattern): bool {
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
