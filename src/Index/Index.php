<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Index;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\Rules\RulesInterface;
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
  protected RulesInterface $rules;

  /**
   * Constructs an Index instance.
   *
   * @param string $directory
   *   The directory to index.
   * @param \AlexSkrypnyk\Snapshot\Rules\RulesInterface|null $rules
   *   Optional rules to apply when indexing. Falls back to the directory's
   *   rules file, then to empty rules.
   * @param mixed $fileFilter
   *   Optional callback receiving the IndexedFile of each file that survives
   *   the skip and global rules, including files marked by an ignore-content
   *   rule. A symlink to a directory reaches it; a broken symlink does not.
   *   Returning FALSE excludes the file from the index; any other return value
   *   is discarded, and any change the callback made to the file is kept.
   */
  public function __construct(
    protected string $directory,
    ?RulesInterface $rules = NULL,
    protected mixed $fileFilter = NULL,
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
  public function getFiles(?callable $transformer = NULL): array {
    if ($this->files === NULL) {
      $this->scan();
    }

    $this->files ??= [];

    if (!is_callable($transformer)) {
      return $this->files;
    }

    // Transform a copy so the callback never mutates the cached index.
    $files = [];
    foreach ($this->files as $path => $file) {
      $files[$path] = $transformer($file);
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
  public function getRules(): RulesInterface {
    return $this->rules;
  }

  /**
   * Scans files in the directory, applying the rules and the file filter.
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

      // Neither the rules file nor the VCS tree is user-overridable, so both
      // are hard-skipped before include patterns are checked.
      if ($relative_path === Snapshot::IGNORECONTENT || str_starts_with($relative_path, '.git/')) {
        continue;
      }

      // $is_included must be known before the global check, so an include
      // pattern can override the global check as well as the skip check.
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

      // The filter runs after the content marking so that it can exclude a
      // file whose content is ignored.
      if (is_callable($this->fileFilter) && call_user_func($this->fileFilter, $file) === FALSE) {
        continue;
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
