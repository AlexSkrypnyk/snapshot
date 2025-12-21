<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Replacer;

/**
 * Interface for content replacement in directories.
 */
interface ReplacerInterface {

  /**
   * Create a new replacer instance.
   *
   * @return static
   *   A new replacer instance.
   */
  public static function create(): static;

  /**
   * Create a replacer with version normalization patterns preset.
   *
   * @return static
   *   A new replacer instance with version patterns.
   */
  public static function versions(): static;

  /**
   * Add a replacement (replaces if name already exists).
   *
   * @param \AlexSkrypnyk\Snapshot\Replacer\ReplacementInterface $replacement
   *   The replacement to add.
   *
   * @return static
   *   The replacer instance for chaining.
   */
  public function addReplacement(ReplacementInterface $replacement): static;

  /**
   * Remove a replacement by name.
   *
   * @param string $name
   *   Replacement name to remove.
   *
   * @return static
   *   The replacer instance for chaining.
   */
  public function removeReplacement(string $name): static;

  /**
   * Check if a replacement exists.
   *
   * @param string $name
   *   Replacement name to check.
   *
   * @return bool
   *   TRUE if replacement exists.
   */
  public function hasReplacement(string $name): bool;

  /**
   * Get a replacement by name.
   *
   * @param string $name
   *   Replacement name.
   *
   * @return \AlexSkrypnyk\Snapshot\Replacer\ReplacementInterface|null
   *   The replacement or NULL if not found.
   */
  public function getReplacement(string $name): ?ReplacementInterface;

  /**
   * Get all configured replacements.
   *
   * @return array<string, \AlexSkrypnyk\Snapshot\Replacer\ReplacementInterface>
   *   All replacements keyed by name.
   */
  public function getReplacements(): array;

  /**
   * Set the maximum number of successful replacements before stopping.
   *
   * @param int $max
   *   Maximum replacements (0 = unlimited).
   *
   * @return static
   *   The replacer instance for chaining.
   */
  public function setMaxReplacements(int $max): static;

  /**
   * Get the maximum number of replacements.
   *
   * @return int
   *   Maximum replacements (0 = unlimited).
   */
  public function getMaxReplacements(): int;

  /**
   * Apply all replacements to a string content.
   *
   * @param string $content
   *   The content to process (passed by reference).
   * @param int|null $max_replacements
   *   Optional override for max replacements (NULL uses instance default).
   *
   * @return bool
   *   TRUE if any replacement was made, FALSE otherwise.
   */
  public function replace(string &$content, ?int $max_replacements = NULL): bool;

  /**
   * Apply all replacements to files in a directory.
   *
   * @param string $directory
   *   The directory to process.
   *
   * @return static
   *   The replacer instance for chaining.
   */
  public function replaceInDir(string $directory): static;

  /**
   * Add exclusions to replacement rules.
   *
   * @param array<string|\Closure> $matchers
   *   Exclusion patterns or callbacks. Each can be:
   *   - Regex pattern (string starting with /)
   *   - Exact string (will be converted to regex)
   *   - Callback: fn(string $match): bool (return TRUE to exclude)
   *   Empty array clears exclusions from targeted rules.
   * @param string|null $name
   *   Target specific replacement by name, or NULL for all.
   *
   * @return static
   *   The replacer instance for chaining.
   *
   * @throws \InvalidArgumentException
   *   If named replacement does not exist.
   */
  public function addExclusions(array $matchers, ?string $name = NULL): static;

}
