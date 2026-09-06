<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Rules;

/**
 * Interface for file matching rules.
 */
interface RulesInterface {

  /**
   * Gets patterns for files where only content should be ignored.
   *
   * @return array<int, string>
   *   Array of patterns.
   */
  public function getIgnoreContent(): array;

  /**
   * Gets patterns for files to skip.
   *
   * @return array<int, string>
   *   Array of patterns.
   */
  public function getSkip(): array;

  /**
   * Gets global patterns that apply everywhere.
   *
   * @return array<int, string>
   *   Array of patterns.
   */
  public function getGlobal(): array;

  /**
   * Gets patterns for files to explicitly include.
   *
   * @return array<int, string>
   *   Array of patterns.
   */
  public function getInclude(): array;

  /**
   * Gets patterns for files where content should be explicitly compared.
   *
   * @return array<int, string>
   *   Array of patterns.
   */
  public function getIncludeContent(): array;

  /**
   * Adds patterns for files where only content should be ignored.
   *
   * @param string ...$patterns
   *   The patterns to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addIgnoreContent(string ...$patterns): static;

  /**
   * Adds patterns for files to skip.
   *
   * @param string ...$patterns
   *   The patterns to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addSkip(string ...$patterns): static;

  /**
   * Adds global patterns that apply everywhere.
   *
   * @param string ...$patterns
   *   The patterns to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addGlobal(string ...$patterns): static;

  /**
   * Adds patterns for files to explicitly include.
   *
   * @param string ...$patterns
   *   The patterns to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addInclude(string ...$patterns): static;

  /**
   * Adds patterns for files where content should be explicitly compared.
   *
   * @param string ...$patterns
   *   The patterns to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addIncludeContent(string ...$patterns): static;

  /**
   * Fluent method to skip multiple patterns.
   *
   * @param string ...$patterns
   *   Patterns to skip.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function skip(string ...$patterns): static;

  /**
   * Fluent method to ignore content of multiple patterns.
   *
   * @param string ...$patterns
   *   Patterns to ignore content.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function ignoreContent(string ...$patterns): static;

  /**
   * Fluent method to add multiple global patterns.
   *
   * @param string ...$patterns
   *   Patterns that apply everywhere.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function global(string ...$patterns): static;

  /**
   * Fluent method to include multiple patterns.
   *
   * @param string ...$patterns
   *   Patterns to include.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function include(string ...$patterns): static;

  /**
   * Fluent method to include content of multiple patterns.
   *
   * @param string ...$patterns
   *   Patterns to compare content for.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function includeContent(string ...$patterns): static;

  /**
   * Parse the rules content.
   *
   * @param string $content
   *   The content of the rules file.
   *
   * @return static
   *   The current instance.
   */
  public function parse(string $content): static;

}
