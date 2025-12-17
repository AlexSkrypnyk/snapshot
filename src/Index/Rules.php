<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Index;

use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Exception\RulesException;
use AlexSkrypnyk\Snapshot\Exception\SnapshotException;

/**
 * Handles file matching rules and patterns.
 */
class Rules implements RulesInterface {

  /**
   * Patterns for files where only content should be ignored.
   *
   * @var array<int, string>
   */
  protected array $ignoreContent = [];

  /**
   * Patterns for files to skip.
   *
   * @var array<int, string>
   */
  protected array $skip = [];

  /**
   * Global patterns that apply everywhere.
   *
   * @var array<int, string>
   */
  protected array $global = [];

  /**
   * Patterns for files to explicitly include.
   *
   * @var array<int, string>
   */
  protected array $include = [];

  /**
   * {@inheritdoc}
   */
  public function getIgnoreContent(): array {
    return $this->ignoreContent;
  }

  /**
   * {@inheritdoc}
   */
  public function getSkip(): array {
    return $this->skip;
  }

  /**
   * {@inheritdoc}
   */
  public function getGlobal(): array {
    return $this->global;
  }

  /**
   * {@inheritdoc}
   */
  public function getInclude(): array {
    return $this->include;
  }

  /**
   * {@inheritdoc}
   */
  public function addIgnoreContent(string $pattern): static {
    $this->ignoreContent[] = $pattern;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addSkip(string $pattern): static {
    $this->skip[] = $pattern;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addGlobal(string $pattern): static {
    $this->global[] = $pattern;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addInclude(string $pattern): static {
    $this->include[] = $pattern;
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   *  The syntax for the file is similar to .gitignore with addition of
   *  the content ignoring using ^ prefix:
   *  Comments start with #.
   *  file    Ignore file.
   *  dir/    Ignore directory and all subdirectories.
   *  dir/*   Ignore all files in directory, but not subdirectories.
   *  ^file   Ignore content changes in file, but not the file itself.
   *  ^dir/   Ignore content changes in all files and subdirectories, but check
   *          that the directory itself exists.
   *  ^dir/*  Ignore content changes in all files, but not subdirectories and
   *          check that the directory itself exists.
   *  !file   Do not ignore file.
   *  !dir/   Do not ignore directory, including all subdirectories.
   *  !dir/*  Do not ignore all files in directory, but not subdirectories.
   *  !^file  Do not ignore content changes in file.
   *  !^dir/  Do not ignore content changes in all files and subdirectories.
   *  !^dir/* Do not ignore content changes in all files, but not subdirs.
   */
  public function parse(string $content): static {
    $lines = static::splitLines($content);

    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      if ($line[0] === '#') {
        continue;
      }
      if ($line[0] === '!') {
        $this->include[] = $line[1] === '^' ? substr($line, 2) : substr($line, 1);
      }
      elseif ($line[0] === '^') {
        $this->ignoreContent[] = substr($line, 1);
      }
      elseif (!str_contains($line, DIRECTORY_SEPARATOR)) {
        $this->global[] = $line;
      }
      else {
        $this->skip[] = $line;
      }
    }

    return $this;
  }

  /**
   * Creates a Rules instance from a file.
   *
   * @param string $file
   *   The path to the rules file.
   *
   * @return self
   *   A new Rules instance.
   *
   * @throws \AlexSkrypnyk\Snapshot\Exception\SnapshotException
   *   If the file does not exist or cannot be read.
   */
  public static function fromFile(string $file): self {
    if (!File::exists($file)) {
      throw new SnapshotException(sprintf('File %s does not exist.', $file));
    }

    try {
      $content = File::read($file);
    }
    // @codeCoverageIgnoreStart
    catch (\Exception $exception) {
      throw new RulesException(sprintf('Failed to read the %s file.', $file), $exception->getCode(), $exception);
    }
    // @codeCoverageIgnoreEnd
    return (new self())->parse($content);
  }

  /**
   * Splits a string into lines.
   *
   * @param string $content
   *   The content to split.
   *
   * @return array<int, string>
   *   Array of lines.
   *
   * @throws \AlexSkrypnyk\Snapshot\Exception\RulesException
   *   If the content cannot be split.
   */
  protected static function splitLines(string $content): array {
    $lines = preg_split('/(\r\n)|(\r)|(\n)/', $content);

    if ($lines === FALSE) {
      // @codeCoverageIgnoreStart
      throw new RulesException('Failed to split lines.');
      // @codeCoverageIgnoreEnd
    }

    return $lines;
  }

}
