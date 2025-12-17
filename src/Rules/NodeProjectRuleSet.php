<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Rules;

/**
 * Rule set for Node.js projects.
 *
 * Skips common Node.js project directories and ignores content of lock files.
 *
 * @code
 * $rules = (new NodeProjectRuleSet())->toRules();
 * Snapshot::compare($baseline, $actual, $rules);
 * @endcode
 */
class NodeProjectRuleSet extends AbstractRuleSet {

  /**
   * {@inheritdoc}
   */
  protected const SKIP_PATTERNS = [
    'node_modules/',
    '.npm/',
    '.yarn/',
    'dist/',
    'build/',
    '.next/',
    '.nuxt/',
    '.cache/',
  ];

  /**
   * {@inheritdoc}
   */
  protected const IGNORE_CONTENT_PATTERNS = [
    'package-lock.json',
    'yarn.lock',
    'pnpm-lock.yaml',
  ];

}
