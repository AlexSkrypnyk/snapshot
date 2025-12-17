<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Compare;

use AlexSkrypnyk\Snapshot\Index\IndexedFileInterface;
use AlexSkrypnyk\Snapshot\Index\IndexInterface;

/**
 * Compares two directories and provides difference information.
 */
class Comparer implements ComparerInterface {

  /**
   * Collection of file differences.
   *
   * @var \AlexSkrypnyk\Snapshot\Compare\Diff[]
   */
  protected array $diffs = [];

  /**
   * Constructs a new Comparer instance.
   *
   * @param \AlexSkrypnyk\Snapshot\Index\IndexInterface $left
   *   The left (source) index.
   * @param \AlexSkrypnyk\Snapshot\Index\IndexInterface $right
   *   The right (destination) index.
   */
  public function __construct(
    protected IndexInterface $left,
    protected IndexInterface $right,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function compare(): static {
    $dir_left_files = $this->left->getFiles();
    $dir_right_files = $this->right->getFiles();

    // Process all left files and matching right files.
    foreach ($dir_left_files as $path => $left_file) {
      $this->addLeftFile($left_file);
      if (isset($dir_right_files[$path])) {
        $this->addRightFile($dir_right_files[$path]);
        // Mark as processed to avoid duplicate processing.
        unset($dir_right_files[$path]);
      }
    }

    // Process remaining right files that don't exist in left.
    foreach ($dir_right_files as $dir_right_file) {
      $this->addRightFile($dir_right_file);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addLeftFile(IndexedFileInterface $file): void {
    $this->diffs[$file->getPathnameFromBasepath()] ??= new Diff();
    $this->diffs[$file->getPathnameFromBasepath()]->setLeft($file);
  }

  /**
   * {@inheritdoc}
   */
  public function addRightFile(IndexedFileInterface $file): void {
    $this->diffs[$file->getPathnameFromBasepath()] ??= new Diff();
    $this->diffs[$file->getPathnameFromBasepath()]->setRight($file);
  }

  /**
   * {@inheritdoc}
   */
  public function getAbsentLeftDiffs(?callable $cb = NULL): array {
    return $this->filter(fn(Diff $diff): bool => !$diff->existsLeft(), $cb);
  }

  /**
   * {@inheritdoc}
   */
  public function getAbsentRightDiffs(?callable $cb = NULL): array {
    return $this->filter(fn(Diff $diff): bool => !$diff->existsRight(), $cb);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentDiffs(?callable $cb = NULL): array {
    return $this->filter(fn(Diff $diff): bool => $diff->existsLeft() && $diff->existsRight() && !$diff->isSameContent(), $cb);
  }

  /**
   * Filters the diffs collection using a callback.
   *
   * @param callable $filter
   *   The filter callback. Should return TRUE to include an item.
   * @param callable|null $cb
   *   Optional transformation callback applied to each filtered diff.
   *
   * @return array<string, Diff|mixed>
   *   Filtered (and optionally transformed) array of diffs.
   */
  protected function filter(callable $filter, ?callable $cb = NULL): array {
    $diffs = array_filter($this->diffs, $filter);

    if (is_callable($cb)) {
      foreach ($diffs as $path => $diff) {
        $diffs[$path] = $cb($diff);
      }
    }

    return $diffs;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $options = [], ?callable $renderer = NULL): ?string {
    return call_user_func($renderer ?? [static::class, 'doRender'], $this->left, $this->right, $this, $options);
  }

  /**
   * Default renderer for directory comparison results.
   *
   * @param \AlexSkrypnyk\Snapshot\Index\IndexInterface $left
   *   The left (source) index.
   * @param \AlexSkrypnyk\Snapshot\Index\IndexInterface $right
   *   The right (destination) index.
   * @param \AlexSkrypnyk\Snapshot\Compare\Comparer $comparer
   *   The comparer containing comparison results.
   * @param array<string, mixed> $options
   *   Rendering options.
   *
   * @return string|null
   *   The rendered comparison or NULL if there are no differences.
   */
  protected static function doRender(IndexInterface $left, IndexInterface $right, Comparer $comparer, array $options = []): ?string {
    $options += [
      'show_diff' => TRUE,
      // Number of files to include in the diff output. This allows to prevent
      // an output that could potentially eat a lot of memory.
      'show_diff_file_limit' => 10,
    ];

    if (empty($comparer->getAbsentLeftDiffs()) && empty($comparer->getAbsentRightDiffs()) && empty($comparer->getContentDiffs())) {
      return NULL;
    }

    $render = sprintf("Differences between directories \n[left] %s\nand\n[right] %s\n", $left->getDirectory(), $right->getDirectory());

    if (!empty($comparer->getAbsentLeftDiffs())) {
      $render .= "Files absent in [left]:\n";
      foreach (array_keys($comparer->getAbsentLeftDiffs()) as $file) {
        $render .= sprintf("  %s\n", $file);
      }
    }

    if (!empty($comparer->getAbsentRightDiffs())) {
      $render .= "Files absent in [right]:\n";
      foreach (array_keys($comparer->getAbsentRightDiffs()) as $file) {
        $render .= sprintf("  %s\n", $file);
      }
    }

    $file_diffs = $comparer->getContentDiffs();
    if (!empty($file_diffs)) {
      $render .= "Files that differ in content:\n";

      $file_diffs_render_count = is_int($options['show_diff_file_limit']) ? $options['show_diff_file_limit'] : count($file_diffs);
      foreach ($file_diffs as $file => $diff) {
        $render .= sprintf("  %s\n", $file);

        if ($options['show_diff'] && $file_diffs_render_count > 0 && $diff instanceof Diff) {
          $render .= '--- DIFF START ---' . PHP_EOL;
          $render .= $diff->render();
          $render .= '--- DIFF END ---' . PHP_EOL;
          $file_diffs_render_count--;
        }
      }
    }

    return $render;
  }

}
