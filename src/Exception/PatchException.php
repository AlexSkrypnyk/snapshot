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
   * @param string|null $file_path
   *   The file path, if applicable.
   * @param int|string|null $line_number
   *   The line number, if applicable.
   * @param string|null $line_content
   *   The line content, if applicable.
   * @param int $code
   *   The exception code.
   * @param \Throwable|null $previous
   *   The previous throwable, if any.
   */
  public function __construct(
    string $message,
    protected ?string $file_path = NULL,
    protected int|string|null $line_number = NULL,
    protected ?string $line_content = NULL,
    int $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    if (empty($message)) {
      $message = 'An error occurred';
    }

    if (($this->file_path || $this->line_number || $this->line_content) && str_ends_with($message, '.')) {
      $message = rtrim($message, '.');
    }

    if ($this->file_path !== NULL) {
      $message .= sprintf(' in file "%s"', $this->file_path);
    }

    if ($this->line_number !== NULL) {
      $message .= ' on line ' . $this->line_number;
    }

    if ($this->line_content !== NULL) {
      $message .= sprintf(': "%s"', $this->line_content);
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
    return $this->file_path;
  }

  /**
   * Gets the line number.
   *
   * @return int|string|null
   *   The line number.
   */
  public function getLineNumber(): int|string|null {
    return $this->line_number;
  }

  /**
   * Gets the line content.
   *
   * @return string|null
   *   The line content.
   */
  public function getLineContent(): ?string {
    return $this->line_content;
  }

}
