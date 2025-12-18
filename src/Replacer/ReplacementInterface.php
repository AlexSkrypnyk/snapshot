<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Replacer;

/**
 * Interface for a single replacement definition.
 */
interface ReplacementInterface {

  /**
   * Default replacement placeholder for version strings.
   */
  public const VERSION = '__VERSION__';

  /**
   * Replacement placeholder for hash strings.
   */
  public const HASH = '__HASH__';

  /**
   * Replacement placeholder for integrity hashes (SRI).
   */
  public const INTEGRITY = '__INTEGRITY__';

  /**
   * Create a new replacement.
   *
   * @param string $name
   *   Unique name for this replacement.
   * @param string|\Closure $matcher
   *   Regex pattern string or closure for custom matching.
   * @param string $replacement
   *   Replacement string (used only with regex matcher).
   */
  public static function create(string $name, string|\Closure $matcher, string $replacement = self::VERSION): static;

  /**
   * Get the replacement name.
   *
   * @return string
   *   The name.
   */
  public function getName(): string;

  /**
   * Get the matcher (regex pattern or closure).
   *
   * @return string|\Closure
   *   The matcher.
   */
  public function getMatcher(): string|\Closure;

  /**
   * Get the replacement string.
   *
   * @return string
   *   The replacement.
   */
  public function getReplacement(): string;

  /**
   * Check if this replacement uses a callback matcher.
   *
   * @return bool
   *   TRUE if matcher is a closure.
   */
  public function isCallback(): bool;

  /**
   * Apply this replacement to content.
   *
   * @param string $content
   *   The content to process (passed by reference).
   *
   * @return bool
   *   TRUE if replacement was made, FALSE otherwise.
   */
  public function apply(string &$content): bool;

}
