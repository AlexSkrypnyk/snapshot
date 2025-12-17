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
   * Get patterns to skip during comparison.
   *
   * @return array<int, string>
   *   Array of patterns to skip.
   */
  public function getSkipPatterns(): array;

  /**
   * Get patterns where content differences should be ignored.
   *
   * @return array<int, string>
   *   Array of patterns to ignore content.
   */
  public function getIgnoreContentPatterns(): array;

  /**
   * Apply this rule set to a Rules instance.
   *
   * @param \AlexSkrypnyk\Snapshot\Rules\Rules|null $rules
   *   Optional existing rules to extend. Creates new Rules if NULL.
   *
   * @return \AlexSkrypnyk\Snapshot\Rules\Rules
   *   Rules instance with this rule set applied.
   */
  public function applyTo(?Rules $rules = NULL): Rules;

  /**
   * Create a new Rules instance with this rule set applied.
   *
   * @return \AlexSkrypnyk\Snapshot\Rules\Rules
   *   New Rules instance configured with this rule set.
   */
  public function toRules(): Rules;

}
