<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\Snapshot\Replacer\Replacement;
use AlexSkrypnyk\Snapshot\Replacer\ReplacementInterface;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Replacement::class)]
final class ReplacementTest extends UnitTestCase {

  public function testConstants(): void {
    $this->assertSame('__VERSION__', ReplacementInterface::VERSION);
    $this->assertSame('__HASH__', ReplacementInterface::HASH);
    $this->assertSame('__INTEGRITY__', ReplacementInterface::INTEGRITY);
  }

  public function testCreateWithRegexMatcher(): void {
    $replacement = Replacement::create('test_name', '/pattern/', '__REPLACED__');

    $this->assertInstanceOf(Replacement::class, $replacement);
    $this->assertSame('test_name', $replacement->getName());
    $this->assertSame('/pattern/', $replacement->getMatcher());
    $this->assertSame('__REPLACED__', $replacement->getReplacement());
    $this->assertFalse($replacement->isCallback());
  }

  public function testCreateWithClosureMatcher(): void {
    $closure = fn(string $content): string => $content;
    $replacement = Replacement::create('test_name', $closure);

    $this->assertInstanceOf(Replacement::class, $replacement);
    $this->assertSame('test_name', $replacement->getName());
    $this->assertSame($closure, $replacement->getMatcher());
    $this->assertTrue($replacement->isCallback());
  }

  public function testCreateWithDefaultReplacement(): void {
    $replacement = Replacement::create('test_name', '/pattern/');

    $this->assertSame(ReplacementInterface::VERSION, $replacement->getReplacement());
  }

  public function testGetName(): void {
    $replacement = Replacement::create('my_name', '/pattern/');

    $this->assertSame('my_name', $replacement->getName());
  }

  public function testGetMatcher(): void {
    $replacement = Replacement::create('test', '/my-pattern/');

    $this->assertSame('/my-pattern/', $replacement->getMatcher());
  }

  public function testGetReplacement(): void {
    $replacement = Replacement::create('test', '/pattern/', '__CUSTOM__');

    $this->assertSame('__CUSTOM__', $replacement->getReplacement());
  }

  public function testIsCallbackWithRegex(): void {
    $replacement = Replacement::create('test', '/pattern/');

    $this->assertFalse($replacement->isCallback());
  }

  public function testIsCallbackWithClosure(): void {
    $replacement = Replacement::create('test', fn(string $c): string => $c);

    $this->assertTrue($replacement->isCallback());
  }

  #[DataProvider('dataProviderApply')]
  public function testApply(string|\Closure $matcher, string $replacement_str, string $input, string $expected, bool $expected_changed): void {
    $replacement = Replacement::create('test', $matcher, $replacement_str);

    $result = $replacement->apply($input);

    $this->assertSame($expected_changed, $result);
    $this->assertSame($expected, $input);
  }

  public static function dataProviderApply(): \Iterator {
    yield 'regex match' => [
      '/foo/',
      'bar',
      'foo baz foo',
      'bar baz bar',
      TRUE,
    ];

    yield 'regex no match' => [
      '/nonexistent/',
      '__REPLACED__',
      'original content',
      'original content',
      FALSE,
    ];

    yield 'regex with backreference' => [
      '/(hello) (world)/',
      '${2} ${1}',
      'hello world',
      'world hello',
      TRUE,
    ];

    yield 'closure matcher' => [
      strtoupper(...),
      '__IGNORED__',
      'hello',
      'HELLO',
      TRUE,
    ];

    yield 'closure no change' => [
      fn(string $content): string => $content,
      '__IGNORED__',
      'unchanged',
      'unchanged',
      FALSE,
    ];

    yield 'closure with preg_replace_callback' => [
      fn(string $content): string => preg_replace_callback(
        '/(\d+)/',
        fn(array $m): string => (string) ((int) $m[1] * 2),
        $content
      ) ?? $content,
      '__IGNORED__',
      'value: 5',
      'value: 10',
      TRUE,
    ];
  }

  public function testAddExclusionWithRegexPattern(): void {
    $replacement = Replacement::create('test', '/\d+\.\d+\.\d+/', '__VERSION__')
      ->addExclusion('/^0\.0\./');

    $content = '1.2.3 0.0.1 2.0.0';
    $replacement->apply($content);

    $this->assertSame('__VERSION__ 0.0.1 __VERSION__', $content);
  }

  public function testAddExclusionWithExactString(): void {
    $replacement = Replacement::create('test', '/\d+\.\d+\.\d+/', '__VERSION__')
      ->addExclusion('1.0.0');

    $content = '1.0.0 1.2.3 2.0.0';
    $replacement->apply($content);

    $this->assertSame('1.0.0 __VERSION__ __VERSION__', $content);
  }

  public function testAddExclusionWithCallback(): void {
    $replacement = Replacement::create('test', '/\d+\.\d+\.\d+/', '__VERSION__')
      ->addExclusion(fn(string $match): bool => str_starts_with($match, '0.'));

    $content = '1.2.3 0.0.1 0.5.0 2.0.0';
    $replacement->apply($content);

    $this->assertSame('__VERSION__ 0.0.1 0.5.0 __VERSION__', $content);
  }

  public function testAddExclusionMultiple(): void {
    $replacement = Replacement::create('test', '/\d+\.\d+\.\d+/', '__VERSION__')
      ->addExclusion('1.0.0')
      ->addExclusion('/^0\./')
      ->addExclusion(fn(string $match): bool => $match === '9.9.9');

    $content = '1.0.0 0.1.0 1.2.3 9.9.9 2.0.0';
    $replacement->apply($content);

    $this->assertSame('1.0.0 0.1.0 __VERSION__ 9.9.9 __VERSION__', $content);
  }

  public function testGetExclusions(): void {
    $callback = fn(string $match): bool => FALSE;
    $replacement = Replacement::create('test', '/pattern/')
      ->addExclusion('/^0\./')
      ->addExclusion('1.0.0')
      ->addExclusion($callback);

    $exclusions = $replacement->getExclusions();

    $this->assertCount(3, $exclusions);
    $this->assertSame('/^0\./', $exclusions[0]);
    $this->assertSame('/^1\.0\.0$/', $exclusions[1]);
    $this->assertSame($callback, $exclusions[2]);
  }

  public function testClearExclusions(): void {
    $replacement = Replacement::create('test', '/\d+\.\d+\.\d+/', '__VERSION__')
      ->addExclusion('1.0.0')
      ->addExclusion('/^0\./');

    $this->assertCount(2, $replacement->getExclusions());

    $result = $replacement->clearExclusions();

    $this->assertSame($replacement, $result);
    $this->assertCount(0, $replacement->getExclusions());

    // After clearing, all matches should be replaced.
    $content = '1.0.0 0.1.0 1.2.3';
    $replacement->apply($content);

    $this->assertSame('__VERSION__ __VERSION__ __VERSION__', $content);
  }

  public function testAddExclusionNoMatchingContent(): void {
    $replacement = Replacement::create('test', '/\d+\.\d+\.\d+/', '__VERSION__')
      ->addExclusion('/^0\./');

    $content = 'no versions here';
    $result = $replacement->apply($content);

    $this->assertFalse($result);
    $this->assertSame('no versions here', $content);
  }

  public function testAddExclusionAllMatches(): void {
    $replacement = Replacement::create('test', '/\d+\.\d+\.\d+/', '__VERSION__')
      ->addExclusion('/^0\./');

    $content = '0.0.1 0.1.0 0.2.0';
    $result = $replacement->apply($content);

    // All matches are excluded, so no change.
    $this->assertFalse($result);
    $this->assertSame('0.0.1 0.1.0 0.2.0', $content);
  }

  public function testClosureMatcherIgnoresExclusions(): void {
    // Closures don't support exclusions - they handle their own logic.
    $replacement = Replacement::create('test', strtoupper(...))
      ->addExclusion('/foo/');

    $content = 'foo bar';
    $replacement->apply($content);

    // Closure applies to entire content, exclusion is ignored.
    $this->assertSame('FOO BAR', $content);
  }

  public function testAddExclusionReturnsSelf(): void {
    $replacement = Replacement::create('test', '/pattern/');

    $result = $replacement->addExclusion('/exclusion/');

    $this->assertSame($replacement, $result);
  }

  public function testAddExclusionWithBackreference(): void {
    $replacement = Replacement::create('test', '/(v?)(\d+\.\d+\.\d+)/', '${1}__VERSION__')
      ->addExclusion('/^v?0\./');

    $content = 'v1.2.3 0.0.1 v0.5.0 2.0.0';
    $replacement->apply($content);

    $this->assertSame('v__VERSION__ 0.0.1 v0.5.0 __VERSION__', $content);
  }

}
