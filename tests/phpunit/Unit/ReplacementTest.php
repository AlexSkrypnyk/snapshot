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

}
