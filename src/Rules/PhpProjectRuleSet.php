<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Rules;

/**
 * Rule set for PHP projects.
 *
 * Skips common PHP project directories and ignores content of lock files.
 *
 * @code
 * $rules = (new PhpProjectRuleSet())->toRules();
 * Snapshot::compare($baseline, $actual, $rules);
 * @endcode
 */
class PhpProjectRuleSet extends AbstractRuleSet {

  /**
   * {@inheritdoc}
   */
  protected const SKIP_PATTERNS = [
    'vendor/',
    '.phpunit.cache/',
    '.phpcs-cache',
    '.php-cs-fixer.cache',
    '.phpbench/',
  ];

  /**
   * {@inheritdoc}
   */
  protected const IGNORE_CONTENT_PATTERNS = [
    'composer.lock',
  ];

}
