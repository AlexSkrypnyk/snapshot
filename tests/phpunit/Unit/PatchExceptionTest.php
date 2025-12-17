<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\Snapshot\Exception\PatchException;
use AlexSkrypnyk\Snapshot\Exception\SnapshotException;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(PatchException::class)]
final class PatchExceptionTest extends UnitTestCase {

  #[DataProvider('dataProviderMessageFormatting')]
  public function testMessageFormatting(
    string $message,
    ?string $file_path,
    int|string|null $line_number,
    ?string $line_content,
    string $expected_message,
  ): void {
    $exception = new PatchException($message, $file_path, $line_number, $line_content);
    $this->assertSame($expected_message, $exception->getMessage());
  }

  public static function dataProviderMessageFormatting(): \Iterator {
    yield 'message only' => [
      'Test message',
      NULL,
      NULL,
      NULL,
      'Test message.',
    ];
    yield 'message with period' => [
      'Test message.',
      NULL,
      NULL,
      NULL,
      'Test message.',
    ];
    yield 'message with file path' => [
      'Test message',
      '/path/to/file.txt',
      NULL,
      NULL,
      'Test message in file "/path/to/file.txt".',
    ];
    yield 'message with line number' => [
      'Test message',
      NULL,
      42,
      NULL,
      'Test message on line 42.',
    ];
    yield 'message with line content' => [
      'Test message',
      NULL,
      NULL,
      'Line content',
      'Test message: "Line content".',
    ];
    yield 'message with file path and line number' => [
      'Test message',
      '/path/to/file.txt',
      42,
      NULL,
      'Test message in file "/path/to/file.txt" on line 42.',
    ];
    yield 'message with file path and line content' => [
      'Test message',
      '/path/to/file.txt',
      NULL,
      'Line content',
      'Test message in file "/path/to/file.txt": "Line content".',
    ];
    yield 'message with line number and line content' => [
      'Test message',
      NULL,
      42,
      'Line content',
      'Test message on line 42: "Line content".',
    ];
    yield 'message with all details' => [
      'Test message',
      '/path/to/file.txt',
      42,
      'Line content',
      'Test message in file "/path/to/file.txt" on line 42: "Line content".',
    ];
    yield 'empty message with details' => [
      '',
      '/path/to/file.txt',
      42,
      'Line content',
      'An error occurred in file "/path/to/file.txt" on line 42: "Line content".',
    ];
    yield 'string line number' => [
      'Test message',
      '/path/to/file.txt',
      'ABC',
      'Line content',
      'Test message in file "/path/to/file.txt" on line ABC: "Line content".',
    ];
    yield 'message with period and other details' => [
      'Test message.',
      '/path/to/file.txt',
      42,
      'Line content',
      'Test message in file "/path/to/file.txt" on line 42: "Line content".',
    ];
  }

  public function testGetters(): void {
    $file_path = '/path/to/file.txt';
    $line_number = 42;
    $line_content = 'Line content';

    $exception = new PatchException(
      'Test message',
      $file_path,
      $line_number,
      $line_content
    );

    $this->assertSame($file_path, $exception->getFilePath());
    $this->assertSame($line_number, $exception->getLineNumber());
    $this->assertSame($line_content, $exception->getLineContent());
  }

  public function testExceptionInheritance(): void {
    $exception = new PatchException('Test message');
    $this->assertInstanceOf(\Exception::class, $exception);
    $this->assertInstanceOf(SnapshotException::class, $exception);
  }

}
