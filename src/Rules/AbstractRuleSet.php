<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Rules;

/**
 * Abstract base class for rule sets.
 *
 * Subclasses should define SKIP_PATTERNS and IGNORE_CONTENT_PATTERNS constants.
 */
abstract class AbstractRuleSet implements RuleSetInterface {

  /**
   * Patterns to skip during comparison.
   *
   * @var array<int, string>
   */
  protected const SKIP_PATTERNS = [];

  /**
   * Patterns where content differences should be ignored.
   *
   * @var array<int, string>
   */
  protected const IGNORE_CONTENT_PATTERNS = [];

  /**
   * {@inheritdoc}
   */
  public function getSkipPatterns(): array {
    return static::SKIP_PATTERNS;
  }

  /**
   * {@inheritdoc}
   */
  public function getIgnoreContentPatterns(): array {
    return static::IGNORE_CONTENT_PATTERNS;
  }

  /**
   * {@inheritdoc}
   */
  public function applyTo(?Rules $rules = NULL): Rules {
    $rules ??= new Rules();

    foreach ($this->getSkipPatterns() as $pattern) {
      $rules->addSkip($pattern);
    }

    foreach ($this->getIgnoreContentPatterns() as $pattern) {
      $rules->addIgnoreContent($pattern);
    }

    return $rules;
  }

  /**
   * {@inheritdoc}
   */
  public function toRules(): Rules {
    return $this->applyTo();
  }

}
