<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Compare;

use AlexSkrypnyk\Snapshot\Index\IndexedFileInterface;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * File difference implementation.
 *
 * Compares two files and provides access to diff information.
 */
class Diff implements DiffInterface {

  /**
   * The left (source) file.
   */
  protected IndexedFileInterface $left;

  /**
   * The right (destination) file.
   */
  protected IndexedFileInterface $right;

  /**
   * {@inheritdoc}
   */
  public function setLeft(IndexedFileInterface $file): static {
    $this->left = $file;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRight(IndexedFileInterface $file): static {
    $this->right = $file;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLeft(): IndexedFileInterface {
    return $this->left;
  }

  /**
   * {@inheritdoc}
   */
  public function getRight(): IndexedFileInterface {
    return $this->right;
  }

  /**
   * {@inheritdoc}
   */
  public function existsLeft(): bool {
    return !empty($this->left);
  }

  /**
   * {@inheritdoc}
   */
  public function existsRight(): bool {
    return !empty($this->right);
  }

  /**
   * {@inheritdoc}
   *
   * Content is considered the same if:
   * - Both files exist, and
   * - Either the content is ignored for at least one file, or
   * - The content hashes match.
   */
  public function isSameContent(): bool {
    if (!$this->existsLeft() || !$this->existsRight()) {
      return FALSE;
    }

    $is_ignore_content = $this->left->isIgnoreContent() || $this->right->isIgnoreContent();
    if ($is_ignore_content) {
      return TRUE;
    }

    // File fingerprinting for regular files.
    // Skip for symlinks as metadata may not work reliably.
    if (!$this->left->isLink() && !$this->right->isLink()) {
      try {
        $left_size = $this->left->getSize();
        $right_size = $this->right->getSize();

        // Optimization 1: Different sizes = definitely different content.
        if ($left_size !== $right_size) {
          return FALSE;
        }

        // Optimization 2: Same inode = same file (hard link).
        $left_inode = $this->left->getInode();
        $right_inode = $this->right->getInode();
        if ($left_inode === $right_inode && $left_inode !== FALSE) {
          return TRUE;
        }
      }
      // @codeCoverageIgnoreStart
      catch (\RuntimeException) {
        // If metadata access fails, fall through to hash comparison.
      }
      // @codeCoverageIgnoreEnd
    }

    // Final check: Content hash comparison.
    $is_same_hash = $this->left->getHash() === $this->right->getHash();

    return $is_same_hash;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $options = [], ?callable $renderer = NULL): ?string {
    return call_user_func($renderer ?? static::doRender(...), $this, $options);
  }

  /**
   * Default renderer for diff content.
   *
   * @param \AlexSkrypnyk\Snapshot\Compare\Diff $diff
   *   The diff to render.
   * @param array<string, mixed> $options
   *   Rendering options.
   *
   * @return string
   *   The rendered diff.
   */
  protected static function doRender(Diff $diff, array $options = []): string {
    if ($diff->isSameContent()) {
      return $diff->getLeft()->getContent();
    }

    $src_content = $diff->getLeft()->getContent();
    $dst_content = $diff->getRight()->getContent();

    return (new Differ(new UnifiedDiffOutputBuilder('', TRUE)))->diff($src_content, $dst_content);
  }

}
