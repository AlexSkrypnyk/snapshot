<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Exception;

/**
 * Exception thrown when patch operations fail.
 */
class PatchException extends SnapshotException {

  /**
   * Constructs a PatchException.
   *
   * @param string $message
   *   The exception message.
   * @param string|null $filePath
   *   The file path, if applicable.
   * @param int|string|null $lineNumber
   *   The line number, if applicable.
   * @param string|null $lineContent
   *   The line content, if applicable.
   * @param int $code
   *   The exception code.
   * @param \Throwable|null $previous
   *   The previous throwable, if any.
   */
  public function __construct(
    string $message,
    protected ?string $filePath = NULL,
    protected int|string|null $lineNumber = NULL,
    protected ?string $lineContent = NULL,
    int $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    if (empty($message)) {
      $message = 'An error occurred';
    }

    if (($this->filePath || $this->lineNumber || $this->lineContent) && str_ends_with($message, '.')) {
      $message = rtrim($message, '.');
    }

    if ($this->filePath !== NULL) {
      $message .= sprintf(' in file "%s"', $this->filePath);
    }

    if ($this->lineNumber !== NULL) {
      $message .= ' on line ' . $this->lineNumber;
    }

    if ($this->lineContent !== NULL) {
      $message .= sprintf(': "%s"', $this->lineContent);
    }

    if (!str_ends_with($message, '.')) {
      $message .= '.';
    }

    parent::__construct($message, $code, $previous);
  }

  /**
   * Gets the file path.
   *
   * @return string|null
   *   The file path.
   */
  public function getFilePath(): ?string {
    return $this->filePath;
  }

  /**
   * Gets the line number.
   *
   * @return int|string|null
   *   The line number.
   */
  public function getLineNumber(): int|string|null {
    return $this->lineNumber;
  }

  /**
   * Gets the line content.
   *
   * @return string|null
   *   The line content.
   */
  public function getLineContent(): ?string {
    return $this->lineContent;
  }

}
