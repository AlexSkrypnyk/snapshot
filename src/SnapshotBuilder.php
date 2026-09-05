<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot;

use AlexSkrypnyk\Snapshot\Compare\Comparer;
use AlexSkrypnyk\Snapshot\Index\Index;
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\Rules\RulesInterface;

/**
 * Configurable snapshot builder for repeated operations.
 *
 * Use this class to configure rules, a file filter or a content processor and
 * perform multiple operations with the same settings.
 *
 * @code
 * $builder = SnapshotBuilder::create()
 *     ->withRules(Rules::phpProject())
 *     ->addSkip('custom/')
 *     ->withFileFilter(fn(IndexedFile $file) => !$file->isLink());
 *
 * $builder->sync($src, $dest);
 * $comparer = $builder->compare($dir1, $dir2);
 * @endcode
 *
 * @phpstan-consistent-constructor
 */
class SnapshotBuilder {

  /**
   * Configured rules for operations.
   */
  protected ?RulesInterface $rules = NULL;

  /**
   * Configured content processor callback for the patch operation.
   *
   * @var callable|null
   */
  protected $contentProcessor;

  /**
   * Configured file filter callback for the indexing operations.
   *
   * @var callable|null
   */
  protected $fileFilter;

  /**
   * Creates a new configurable SnapshotBuilder instance.
   *
   * @return static
   *   A new SnapshotBuilder instance.
   */
  public static function create(): static {
    return new static();
  }

  /**
   * Set the rules for snapshot operations.
   *
   * @param \AlexSkrypnyk\Snapshot\Rules\RulesInterface $rules
   *   The rules to use.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function withRules(RulesInterface $rules): static {
    $this->rules = $rules;
    return $this;
  }

  /**
   * Set the content processor callback used by the patch operation.
   *
   * @param callable $content_processor
   *   Callback receiving the content of each patched file as a string and
   *   returning the content to write.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function withContentProcessor(callable $content_processor): static {
    $this->contentProcessor = $content_processor;
    return $this;
  }

  /**
   * Set the file filter callback used by the indexing operations.
   *
   * @param callable $file_filter
   *   Callback receiving each candidate file as an IndexedFile; returning
   *   FALSE excludes the file from the index.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function withFileFilter(callable $file_filter): static {
    $this->fileFilter = $file_filter;
    return $this;
  }

  /**
   * Add skip patterns to the rules.
   *
   * Creates rules if not set.
   *
   * @param string ...$patterns
   *   Patterns to skip.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addSkip(string ...$patterns): static {
    $this->rules ??= new Rules();
    $this->rules->skip(...$patterns);
    return $this;
  }

  /**
   * Add ignore content patterns to the rules.
   *
   * Creates rules if not set.
   *
   * @param string ...$patterns
   *   Patterns to ignore content.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addIgnoreContent(string ...$patterns): static {
    $this->rules ??= new Rules();
    $this->rules->ignoreContent(...$patterns);
    return $this;
  }

  /**
   * Add global patterns to the rules.
   *
   * Creates rules if not set.
   *
   * @param string ...$patterns
   *   Patterns that apply everywhere.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addGlobal(string ...$patterns): static {
    $this->rules ??= new Rules();
    $this->rules->global(...$patterns);
    return $this;
  }

  /**
   * Add include patterns to the rules.
   *
   * Creates rules if not set.
   *
   * @param string ...$patterns
   *   Patterns to include.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addInclude(string ...$patterns): static {
    $this->rules ??= new Rules();
    $this->rules->include(...$patterns);
    return $this;
  }

  /**
   * Add include content patterns to the rules.
   *
   * Creates rules if not set.
   *
   * @param string ...$patterns
   *   Patterns to compare content for.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addIncludeContent(string ...$patterns): static {
    $this->rules ??= new Rules();
    $this->rules->includeContent(...$patterns);
    return $this;
  }

  /**
   * Get the configured rules.
   *
   * @return \AlexSkrypnyk\Snapshot\Rules\RulesInterface|null
   *   The configured rules or NULL.
   */
  public function getRules(): ?RulesInterface {
    return $this->rules;
  }

  /**
   * Get the configured content processor.
   *
   * @return callable|null
   *   The configured content processor or NULL.
   */
  public function getContentProcessor(): ?callable {
    return $this->contentProcessor;
  }

  /**
   * Get the configured file filter.
   *
   * @return callable|null
   *   The configured file filter or NULL.
   */
  public function getFileFilter(): ?callable {
    return $this->fileFilter;
  }

  /**
   * Scan a directory and create an index.
   *
   * @param string $directory
   *   Directory to scan.
   *
   * @return \AlexSkrypnyk\Snapshot\Index\Index
   *   The directory index.
   */
  public function scan(string $directory): Index {
    return Snapshot::scan($directory, $this->rules, $this->fileFilter);
  }

  /**
   * Compare two directories using configured settings.
   *
   * @param string $baseline
   *   Baseline directory path (expected).
   * @param string $actual
   *   Actual directory path.
   *
   * @return \AlexSkrypnyk\Snapshot\Compare\Comparer
   *   Comparison result object.
   */
  public function compare(string $baseline, string $actual): Comparer {
    return Snapshot::compare($baseline, $actual, $this->rules, $this->fileFilter);
  }

  /**
   * Create diff files using configured settings.
   *
   * @param string $baseline
   *   Baseline directory path.
   * @param string $actual
   *   Actual directory path.
   * @param string $output
   *   Directory to write diff files to.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function diff(string $baseline, string $actual, string $output): static {
    Snapshot::diff($baseline, $actual, $output, $this->rules, $this->fileFilter);
    return $this;
  }

  /**
   * Apply patches using configured settings.
   *
   * @param string $baseline
   *   Baseline directory path.
   * @param string $diffs
   *   Directory containing diff files produced by self::diff().
   * @param string $destination
   *   Destination directory for patched output.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function patch(string $baseline, string $diffs, string $destination): static {
    Snapshot::patch($baseline, $diffs, $destination, $this->rules, $this->contentProcessor);
    return $this;
  }

  /**
   * Sync directories using configured settings.
   *
   * @param string $source
   *   Source directory path.
   * @param string $destination
   *   Destination directory path.
   * @param int $permissions
   *   Directory permissions.
   * @param bool $copy_empty_dirs
   *   Whether to copy empty directories.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function sync(string $source, string $destination, int $permissions = 0755, bool $copy_empty_dirs = FALSE): static {
    Snapshot::sync($source, $destination, $permissions, $copy_empty_dirs, $this->rules, $this->fileFilter);
    return $this;
  }

}
