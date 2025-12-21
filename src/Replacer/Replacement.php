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
 * - Optional exclusion patterns to skip specific matches.
 *
 * @phpstan-consistent-constructor
 */
class Replacement implements ReplacementInterface {

  /**
   * Exclusion patterns and callbacks.
   *
   * @var array<string|\Closure>
   */
  protected array $exclusions = [];

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
    // Closures handle their own logic (no exclusion support).
    if ($this->matcher instanceof \Closure) {
      $original = $content;
      $content = ($this->matcher)($content);

      return $content !== $original;
    }

    $original = $content;

    // Fast path: no exclusions, use simple preg_replace.
    if ($this->exclusions === []) {
      $result = preg_replace($this->matcher, $this->replacement, $content);
      $content = $result ?? $content;

      return $content !== $original;
    }

    // Slow path: check exclusions per match.
    $content = preg_replace_callback(
      $this->matcher,
      function (array $matches): string {
        $match_text = $matches[0];

        if ($this->isExcluded($match_text)) {
          return $match_text;
        }

        $result = preg_replace($this->matcher, $this->replacement, $match_text);

        return $result ?? $match_text;
      },
      $content
    ) ?? $content;

    return $content !== $original;
  }

  /**
   * {@inheritdoc}
   */
  public function addExclusion(string|\Closure $exclusion): static {
    if ($exclusion instanceof \Closure) {
      $this->exclusions[] = $exclusion;
    }
    elseif (str_starts_with($exclusion, '/')) {
      // Already a regex.
      $this->exclusions[] = $exclusion;
    }
    else {
      // Exact string - convert to regex.
      $this->exclusions[] = '/^' . preg_quote($exclusion, '/') . '$/';
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExclusions(): array {
    return $this->exclusions;
  }

  /**
   * {@inheritdoc}
   */
  public function clearExclusions(): static {
    $this->exclusions = [];

    return $this;
  }

  /**
   * Check if matched text should be excluded.
   *
   * @param string $match
   *   The matched text to check.
   *
   * @return bool
   *   TRUE if the match should be excluded, FALSE otherwise.
   */
  protected function isExcluded(string $match): bool {
    foreach ($this->exclusions as $exclusion) {
      if ($exclusion instanceof \Closure) {
        if ($exclusion($match)) {
          return TRUE;
        }
      }
      elseif (preg_match($exclusion, $match)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
