<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Rules;

/**
 * Abstract base class for rule sets.
 *
 * Subclasses define the pattern constants for the rule kinds they cover and
 * inherit the empty defaults for the rest.
 */
abstract class AbstractRuleSet implements RuleSetInterface {

  /**
   * Patterns for files where only content should be ignored.
   *
   * @var array<int, string>
   */
  protected const IGNORE_CONTENT_PATTERNS = [];

  /**
   * Patterns for files to skip.
   *
   * @var array<int, string>
   */
  protected const SKIP_PATTERNS = [];

  /**
   * Global patterns that apply everywhere.
   *
   * @var array<int, string>
   */
  protected const GLOBAL_PATTERNS = [];

  /**
   * Patterns for files to explicitly include.
   *
   * @var array<int, string>
   */
  protected const INCLUDE_PATTERNS = [];

  /**
   * Patterns for files where content should be explicitly compared.
   *
   * @var array<int, string>
   */
  protected const INCLUDE_CONTENT_PATTERNS = [];

  /**
   * {@inheritdoc}
   */
  public function getIgnoreContent(): array {
    return static::IGNORE_CONTENT_PATTERNS;
  }

  /**
   * {@inheritdoc}
   */
  public function getSkip(): array {
    return static::SKIP_PATTERNS;
  }

  /**
   * {@inheritdoc}
   */
  public function getGlobal(): array {
    return static::GLOBAL_PATTERNS;
  }

  /**
   * {@inheritdoc}
   */
  public function getInclude(): array {
    return static::INCLUDE_PATTERNS;
  }

  /**
   * {@inheritdoc}
   */
  public function getIncludeContent(): array {
    return static::INCLUDE_CONTENT_PATTERNS;
  }

  /**
   * {@inheritdoc}
   */
  public function applyTo(?RulesInterface $rules = NULL): RulesInterface {
    $rules ??= new Rules();

    $rules->addIgnoreContent(...$this->getIgnoreContent());
    $rules->addSkip(...$this->getSkip());
    $rules->addGlobal(...$this->getGlobal());
    $rules->addInclude(...$this->getInclude());
    $rules->addIncludeContent(...$this->getIncludeContent());

    return $rules;
  }

}
