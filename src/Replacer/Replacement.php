<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Replacer;

/**
 * Represents a single replacement definition.
 *
 * A replacement consists of:
 * - A unique name for identification
 * - A matcher (regex pattern or closure for custom logic)
 * - A replacement string (used with regex matchers)
 *
 * @phpstan-consistent-constructor
 */
class Replacement implements ReplacementInterface {

  /**
   * Constructor.
   *
   * @param string $name
   *   Unique name for this replacement.
   * @param string|\Closure $matcher
   *   Regex pattern string or closure for custom matching.
   * @param string $replacement
   *   Replacement string (used only with regex matcher).
   */
  public function __construct(
    protected readonly string $name,
    protected readonly string|\Closure $matcher,
    protected readonly string $replacement = self::VERSION,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(string $name, string|\Closure $matcher, string $replacement = self::VERSION): static {
    return new static($name, $matcher, $replacement);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getMatcher(): string|\Closure {
    return $this->matcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getReplacement(): string {
    return $this->replacement;
  }

  /**
   * {@inheritdoc}
   */
  public function isCallback(): bool {
    return $this->matcher instanceof \Closure;
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string &$content): bool {
    $original = $content;

    if ($this->matcher instanceof \Closure) {
      $content = ($this->matcher)($content);
    }
    else {
      $result = preg_replace($this->matcher, $this->replacement, $content);
      $content = $result ?? $content;
    }

    return $content !== $original;
  }

}
