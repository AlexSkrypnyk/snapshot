<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Rules;

/**
 * Interface for predefined rule sets.
 *
 * Rule sets provide preset configurations for common project types.
 */
interface RuleSetInterface {

  /**
   * Get patterns for files to skip.
   *
   * @return array<int, string>
   *   Array of patterns.
   */
  public function getSkip(): array;

  /**
   * Get patterns for files where only content should be ignored.
   *
   * @return array<int, string>
   *   Array of patterns.
   */
  public function getIgnoreContent(): array;

  /**
   * Get global patterns that apply everywhere.
   *
   * @return array<int, string>
   *   Array of patterns.
   */
  public function getGlobal(): array;

  /**
   * Get patterns for files to explicitly include.
   *
   * @return array<int, string>
   *   Array of patterns.
   */
  public function getInclude(): array;

  /**
   * Get patterns for files where content should be explicitly compared.
   *
   * @return array<int, string>
   *   Array of patterns.
   */
  public function getIncludeContent(): array;

  /**
   * Apply this rule set to a Rules instance.
   *
   * @param \AlexSkrypnyk\Snapshot\Rules\RulesInterface|null $rules
   *   Optional existing rules to extend. Creates new Rules if NULL.
   *
   * @return \AlexSkrypnyk\Snapshot\Rules\RulesInterface
   *   Rules instance with this rule set applied.
   */
  public function applyTo(?RulesInterface $rules = NULL): RulesInterface;

}
