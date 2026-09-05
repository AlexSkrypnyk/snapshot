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
   * Apply this rule set to a rules instance.
   *
   * @param \AlexSkrypnyk\Snapshot\Rules\RulesInterface|null $rules
   *   Optional existing rules to extend. Creates new rules if NULL.
   *
   * @return \AlexSkrypnyk\Snapshot\Rules\RulesInterface
   *   The same rules instance the patterns were applied to: the given one, or
   *   the one created when NULL was passed. Implementations must not return a
   *   different instance, so callers may keep using the one they passed in.
   */
  public function applyTo(?RulesInterface $rules = NULL): RulesInterface;

}
