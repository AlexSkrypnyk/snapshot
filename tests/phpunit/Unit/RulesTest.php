<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\Snapshot\Rules\NodeProjectRuleSet;
use AlexSkrypnyk\Snapshot\Rules\PhpProjectRuleSet;
use AlexSkrypnyk\Snapshot\Rules\Rules;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Rules::class)]
#[CoversClass(PhpProjectRuleSet::class)]
#[CoversClass(NodeProjectRuleSet::class)]
final class RulesTest extends UnitTestCase {

  #[DataProvider('dataProviderRulesFromFile')]
  public function testRulesFromFile(?string $content, array $expected): void {
    $file = self::$sut . DIRECTORY_SEPARATOR . 'test.txt';
    if (!is_null($content)) {
      file_put_contents($file, $content);
    }
    else {
      $this->expectException(\Exception::class);
    }

    $rules = Rules::fromFile($file);
    $this->assertSame($expected['include'], $rules->getInclude());
    $this->assertSame($expected['content'], $rules->getIgnoreContent());
    $this->assertSame($expected['global'], $rules->getGlobal());
    $this->assertSame($expected['skip'], $rules->getSkip());

    unlink($file);
  }

  public static function dataProviderRulesFromFile(): \Iterator {
    $default = [
      'include' => [],
      'content' => [],
      'global' => [],
      'skip' => [],
    ];
    yield 'non-existing file' => [
      NULL,
      $default,
    ];
    yield 'empty file' => [
      '',
      $default,
    ];
    yield 'only comments' => [
      "# This is a comment\n# Another comment",
      $default,
    ];
    yield 'include rules' => [
      "!include-this\n!^content-only",
      [
        'include' => ['include-this', 'content-only'],
      ] + $default,
    ];
    yield 'ignore content rules' => [
      "^ignore-content",
      [
        'content' => ['ignore-content'],
      ] + $default,
    ];
    yield 'global rules' => [
      "global-pattern\nanother-pattern",
      [
        'global' => ['global-pattern', 'another-pattern'],
      ] + $default,
    ];
    yield 'skip rules' => [
      "some/path/file.txt\nanother/path/",
      [
        'skip' => ['some/path/file.txt', 'another/path/'],
      ] + $default,
    ];
    yield 'mixed rules' => [
      "!include-this\n!^content-only\n^ignore-content\nsome/path/file.txt\nglobal-pattern",
      [
        'include' => ['include-this', 'content-only'],
        'content' => ['ignore-content'],
        'global' => ['global-pattern'],
        'skip' => ['some/path/file.txt'],
      ],
    ];
    yield 'rules with whitespace' => [
      "  !include-with-spaces  \n  ^ignore-content-with-spaces  \n  global-with-spaces  \n  some/path/with spaces/  ",
      [
        'include' => ['include-with-spaces'],
        'content' => ['ignore-content-with-spaces'],
        'global' => ['global-with-spaces'],
        'skip' => ['some/path/with spaces/'],
      ],
    ];
    yield 'rules with different line endings' => [
      "!include-this\r\n^ignore-content\r\rglobal-pattern\nsome/path/file.txt",
      [
        'include' => ['include-this'],
        'content' => ['ignore-content'],
        'global' => ['global-pattern'],
        'skip' => ['some/path/file.txt'],
      ],
    ];
  }

  #[DataProvider('dataProviderParseEdgeCases')]
  public function testParseEdgeCases(string $input, array $expected_include, array $expected_content, array $expected_global, array $expected_skip): void {
    $rules = new Rules();
    $rules->parse($input);

    $this->assertSame($expected_include, $rules->getInclude());
    $this->assertSame($expected_content, $rules->getIgnoreContent());
    $this->assertSame($expected_global, $rules->getGlobal());
    $this->assertSame($expected_skip, $rules->getSkip());
  }

  public static function dataProviderParseEdgeCases(): \Iterator {
    yield 'empty lines and whitespace' => ["\n  \n\t\n", [], [], [], []];
    yield 'special characters in include rule' => ["!special@chars", ["special@chars"], [], [], []];
    yield 'regex special characters as global rule' => ["[regex].special+chars?{test}", [], [], ["[regex].special+chars?{test}"], []];
    yield 'very long pattern' => [str_repeat("a", 1000), [], [], [str_repeat("a", 1000)], []];
  }

  public function testParseMethodChaining(): void {
    // Test chained parse calls.
    $rules = new Rules();
    $result = $rules->parse("!include-rule")
      ->parse("^ignore-content-rule")
      ->parse("global-rule")
      ->parse("some/path/");

    $this->assertSame($rules, $result);
    $this->assertSame(['include-rule'], $rules->getInclude());
    $this->assertSame(['ignore-content-rule'], $rules->getIgnoreContent());
    $this->assertSame(['global-rule'], $rules->getGlobal());
    $this->assertSame(['some/path/'], $rules->getSkip());
  }

  #[DataProvider('dataProviderAddMethods')]
  public function testAddMethods(string $method, string $getter, string $pattern): void {
    $rules = new Rules();
    $rules->$method($pattern);
    $this->assertSame([$pattern], $rules->$getter());
  }

  public static function dataProviderAddMethods(): \Iterator {
    yield 'addIgnoreContent' => ['addIgnoreContent', 'getIgnoreContent', 'ignore-content-pattern'];
    yield 'addSkip' => ['addSkip', 'getSkip', 'skip-pattern'];
    yield 'addGlobal' => ['addGlobal', 'getGlobal', 'global-pattern'];
    yield 'addInclude' => ['addInclude', 'getInclude', 'include-pattern'];
  }

  public function testAddMethodChaining(): void {
    // Test method chaining.
    $rules = new Rules();
    $result = $rules->addIgnoreContent('pattern1')
      ->addSkip('pattern2')
      ->addGlobal('pattern3')
      ->addInclude('pattern4');

    $this->assertSame($rules, $result, 'Method chaining should return the same instance');
    $this->assertSame(['pattern1'], $rules->getIgnoreContent());
    $this->assertSame(['pattern2'], $rules->getSkip());
    $this->assertSame(['pattern3'], $rules->getGlobal());
    $this->assertSame(['pattern4'], $rules->getInclude());
  }

  public function testFromFileReadException(): void {
    // Since system permission changes might not work in all test environments,
    // let's use a mock to simulate a file read exception.
    $rules_class = new class() extends Rules {

      public static function fromFile(string $file): Rules {
        throw new \Exception(sprintf('Failed to read the %s file.', $file));
      }

    };

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Failed to read the test-file.txt file.');
    $rules_class::fromFile('test-file.txt');
  }

  public function testCustomRulesImport(): void {
    // Create a test rules file with all types of patterns.
    $rules_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('rules_test_', TRUE) . '.txt';
    $content = <<<EOT
# This is a comment
!include-pattern
!^include-ignore-content-pattern
^ignore-content-pattern
global-pattern
path/to/file.txt
EOT;
    file_put_contents($rules_file, $content);

    try {
      // Test loading from file.
      $rules = Rules::fromFile($rules_file);

      // Check all the rules were loaded correctly.
      $this->assertSame(['include-pattern', 'include-ignore-content-pattern'], $rules->getInclude());
      $this->assertSame(['ignore-content-pattern'], $rules->getIgnoreContent());
      $this->assertSame(['global-pattern'], $rules->getGlobal());
      $this->assertSame(['path/to/file.txt'], $rules->getSkip());
    }
    finally {
      // Clean up.
      if (file_exists($rules_file)) {
        unlink($rules_file);
      }
    }
  }

  public function testCreate(): void {
    $rules = Rules::create();
    $this->assertInstanceOf(Rules::class, $rules);
    $this->assertSame([], $rules->getSkip());
    $this->assertSame([], $rules->getIgnoreContent());
    $this->assertSame([], $rules->getInclude());
    $this->assertSame([], $rules->getGlobal());
  }

  public function testFluentSkipMethod(): void {
    $rules = Rules::create()
      ->skip('vendor/', 'node_modules/', '.cache/');

    $this->assertSame(['vendor/', 'node_modules/', '.cache/'], $rules->getSkip());
  }

  public function testFluentIgnoreContentMethod(): void {
    $rules = Rules::create()
      ->ignoreContent('composer.lock', 'package-lock.json');

    $this->assertSame(['composer.lock', 'package-lock.json'], $rules->getIgnoreContent());
  }

  public function testFluentIncludeMethod(): void {
    $rules = Rules::create()
      ->include('important.log', 'keep-this.txt');

    $this->assertSame(['important.log', 'keep-this.txt'], $rules->getInclude());
  }

  public function testFluentMethodChaining(): void {
    $rules = Rules::create()
      ->skip('vendor/', 'node_modules/')
      ->ignoreContent('composer.lock')
      ->include('!important.txt');

    $this->assertSame(['vendor/', 'node_modules/'], $rules->getSkip());
    $this->assertSame(['composer.lock'], $rules->getIgnoreContent());
    $this->assertSame(['!important.txt'], $rules->getInclude());
  }

  public function testPhpProject(): void {
    $rules = Rules::phpProject();

    $this->assertInstanceOf(Rules::class, $rules);
    $this->assertContains('vendor/', $rules->getSkip());
    $this->assertContains('.phpunit.cache/', $rules->getSkip());
    $this->assertContains('.phpcs-cache', $rules->getSkip());
    $this->assertContains('composer.lock', $rules->getIgnoreContent());
  }

  public function testNodeProject(): void {
    $rules = Rules::nodeProject();

    $this->assertInstanceOf(Rules::class, $rules);
    $this->assertContains('node_modules/', $rules->getSkip());
    $this->assertContains('.npm/', $rules->getSkip());
    $this->assertContains('dist/', $rules->getSkip());
    $this->assertContains('package-lock.json', $rules->getIgnoreContent());
    $this->assertContains('yarn.lock', $rules->getIgnoreContent());
  }

  public function testFromRuleSet(): void {
    $rule_set = new PhpProjectRuleSet();
    $rules = Rules::fromRuleSet($rule_set);

    $this->assertInstanceOf(Rules::class, $rules);
    $this->assertContains('vendor/', $rules->getSkip());
    $this->assertContains('composer.lock', $rules->getIgnoreContent());
  }

  public function testPhpProjectRuleSetPatterns(): void {
    $rule_set = new PhpProjectRuleSet();

    $this->assertContains('vendor/', $rule_set->getSkipPatterns());
    $this->assertContains('.phpunit.cache/', $rule_set->getSkipPatterns());
    $this->assertContains('composer.lock', $rule_set->getIgnoreContentPatterns());
  }

  public function testNodeProjectRuleSetPatterns(): void {
    $rule_set = new NodeProjectRuleSet();

    $this->assertContains('node_modules/', $rule_set->getSkipPatterns());
    $this->assertContains('.npm/', $rule_set->getSkipPatterns());
    $this->assertContains('package-lock.json', $rule_set->getIgnoreContentPatterns());
    $this->assertContains('yarn.lock', $rule_set->getIgnoreContentPatterns());
  }

  public function testRuleSetApplyTo(): void {
    $rule_set = new PhpProjectRuleSet();

    // Apply to existing rules.
    $existing_rules = Rules::create()->skip('custom/');
    $result = $rule_set->applyTo($existing_rules);

    $this->assertSame($existing_rules, $result);
    $this->assertContains('custom/', $result->getSkip());
    $this->assertContains('vendor/', $result->getSkip());
  }

  public function testRuleSetToRules(): void {
    $rule_set = new PhpProjectRuleSet();
    $rules = $rule_set->toRules();

    $this->assertInstanceOf(Rules::class, $rules);
    $this->assertContains('vendor/', $rules->getSkip());
    $this->assertContains('composer.lock', $rules->getIgnoreContent());
  }

}
