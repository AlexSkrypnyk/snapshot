<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot;

use AlexSkrypnyk\Snapshot\Compare\Comparer;
use AlexSkrypnyk\Snapshot\Index\Index;
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\Sync\Syncer;

/**
 * Configurable snapshot builder for repeated operations.
 *
 * Use this class when you need to configure rules or content processors
 * and perform multiple operations with the same settings.
 *
 * @code
 * $builder = SnapshotBuilder::create()
 *     ->withRules(Rules::phpProject())
 *     ->addSkip('custom/')
 *     ->withContentProcessor(fn($content) => trim($content));
 *
 * $builder->sync($src, $dest);
 * $comparer = $builder->compare($dir1, $dir2);
 * @endcode
 */
class SnapshotBuilder {

  /**
   * Configured rules for operations.
   */
  protected ?Rules $rules = NULL;

  /**
   * Configured content processor callback.
   *
   * @var callable|null
   */
  protected $contentProcessor;

  /**
   * Creates a new configurable SnapshotBuilder instance.
   *
   * @return self
   *   A new SnapshotBuilder instance.
   */
  public static function create(): self {
    return new self();
  }

  /**
   * Set the rules for snapshot operations.
   *
   * @param \AlexSkrypnyk\Snapshot\Rules\Rules $rules
   *   The rules to use.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function withRules(Rules $rules): static {
    $this->rules = $rules;
    return $this;
  }

  /**
   * Set the content processor callback.
   *
   * @param callable $processor
   *   Callback to process file content before operations.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function withContentProcessor(callable $processor): static {
    $this->contentProcessor = $processor;
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
   * Get the configured rules.
   *
   * @return \AlexSkrypnyk\Snapshot\Rules\Rules|null
   *   The configured rules or NULL.
   */
  public function getRules(): ?Rules {
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
   * Scan a directory and create an index.
   *
   * @param string $directory
   *   Directory to scan.
   *
   * @return \AlexSkrypnyk\Snapshot\Index\Index
   *   The directory index.
   */
  public function scan(string $directory): Index {
    return new Index($directory, $this->rules, $this->contentProcessor);
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
    return Snapshot::compare($baseline, $actual, $this->rules, $this->contentProcessor);
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
    Snapshot::diff($baseline, $actual, $output, $this->rules, $this->contentProcessor);
    return $this;
  }

  /**
   * Apply patches using configured settings.
   *
   * @param string $baseline
   *   Baseline directory path.
   * @param string $patches
   *   Directory containing patch/diff files.
   * @param string $destination
   *   Destination directory for patched output.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function patch(string $baseline, string $patches, string $destination): static {
    Snapshot::patch($baseline, $patches, $destination, $this->contentProcessor);
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
    $index = new Index($source, $this->rules, $this->contentProcessor);
    (new Syncer($index))->sync($destination, $permissions, $copy_empty_dirs);
    return $this;
  }

}
