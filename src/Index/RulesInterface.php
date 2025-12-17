<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Index;

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
   * Adds a pattern for files where only content should be ignored.
   *
   * @param string $pattern
   *   The pattern to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addIgnoreContent(string $pattern): static;

  /**
   * Adds a pattern for files to skip.
   *
   * @param string $pattern
   *   The pattern to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addSkip(string $pattern): static;

  /**
   * Adds a global pattern that applies everywhere.
   *
   * @param string $pattern
   *   The pattern to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addGlobal(string $pattern): static;

  /**
   * Adds a pattern for files to explicitly include.
   *
   * @param string $pattern
   *   The pattern to add.
   *
   * @return $this
   *   Return self for chaining.
   */
  public function addInclude(string $pattern): static;

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
