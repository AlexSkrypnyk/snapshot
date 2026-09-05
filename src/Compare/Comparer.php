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
   * Cached filter results, keyed by filter type.
   *
   * @var array<string, array<string, Diff>>
   */
  protected array $cache = [];

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
    // Full rebuild: drop any diffs retained from a previous invocation.
    $this->diffs = [];

    $left_files = $this->left->getFiles();
    $right_files = $this->right->getFiles();

    // The index keys are already the pathname relative to the base directory
    // that addLeftFile()/addRightFile() would recompute, so they are reused
    // as the diff keys.
    foreach ($left_files as $path => $left_file) {
      // getFiles() allows a transform callback to return non-file values;
      // skip anything that is not a file since none is passed here.
      if (!$left_file instanceof IndexedFileInterface) {
        // @codeCoverageIgnoreStart
        continue;
        // @codeCoverageIgnoreEnd
      }

      ($this->diffs[$path] ??= new Diff())->setLeft($left_file);

      if (isset($right_files[$path]) && $right_files[$path] instanceof IndexedFileInterface) {
        $this->diffs[$path]->setRight($right_files[$path]);
        unset($right_files[$path]);
      }
    }

    foreach ($right_files as $path => $right_file) {
      if (!$right_file instanceof IndexedFileInterface) {
        // @codeCoverageIgnoreStart
        continue;
        // @codeCoverageIgnoreEnd
      }

      ($this->diffs[$path] ??= new Diff())->setRight($right_file);
    }

    // Filter results derive from $this->diffs; reset the cache once after the
    // full rebuild instead of on every added file.
    $this->cache = [];

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addLeftFile(IndexedFileInterface $file): void {
    $path = $file->getPathnameFromBasepath();
    $this->diffs[$path] ??= new Diff();
    $this->diffs[$path]->setLeft($file);
    $this->cache = [];
  }

  /**
   * {@inheritdoc}
   */
  public function addRightFile(IndexedFileInterface $file): void {
    $path = $file->getPathnameFromBasepath();
    $this->diffs[$path] ??= new Diff();
    $this->diffs[$path]->setRight($file);
    $this->cache = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getAbsentLeftDiffs(?callable $transformer = NULL): array {
    return $this->filterCached('absent_left', fn(Diff $diff): bool => !$diff->existsLeft(), $transformer);
  }

  /**
   * {@inheritdoc}
   */
  public function getAbsentRightDiffs(?callable $transformer = NULL): array {
    return $this->filterCached('absent_right', fn(Diff $diff): bool => !$diff->existsRight(), $transformer);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentDiffs(?callable $transformer = NULL): array {
    return $this->filterCached('content', fn(Diff $diff): bool => $diff->existsLeft() && $diff->existsRight() && !$diff->isSameContent(), $transformer);
  }

  /**
   * Filters the diffs collection with caching support.
   *
   * @param string $cache_key
   *   Cache key for this filter type.
   * @param callable $filter
   *   The filter callback. Should return TRUE to include an item.
   * @param callable|null $transformer
   *   Optional transformation callback applied to each filtered diff.
   *
   * @return array<string, Diff|mixed>
   *   Filtered (and optionally transformed) array of diffs.
   */
  protected function filterCached(string $cache_key, callable $filter, ?callable $transformer = NULL): array {
    $this->cache[$cache_key] ??= array_filter($this->diffs, $filter);

    $diffs = $this->cache[$cache_key];

    if (is_callable($transformer)) {
      foreach ($diffs as $path => $diff) {
        $diffs[$path] = $transformer($diff);
      }
    }

    return $diffs;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $options = [], ?callable $renderer = NULL): ?string {
    return call_user_func($renderer ?? static::doRender(...), $this->left, $this->right, $this, $options);
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
      // Cap on the number of files rendered with a full diff; an unbounded
      // output could consume excessive memory.
      'show_diff_file_limit' => 10,
    ];

    $absent_left = $comparer->getAbsentLeftDiffs();
    $absent_right = $comparer->getAbsentRightDiffs();
    $content_diffs = $comparer->getContentDiffs();

    if (empty($absent_left) && empty($absent_right) && empty($content_diffs)) {
      return NULL;
    }

    $render = sprintf("Differences between directories \n[left] %s\nand\n[right] %s\n", $left->getDirectory(), $right->getDirectory());

    if (!empty($absent_left)) {
      $render .= "Files absent in [left]:\n";
      foreach (array_keys($absent_left) as $file) {
        $render .= sprintf("  %s\n", $file);
      }
    }

    if (!empty($absent_right)) {
      $render .= "Files absent in [right]:\n";
      foreach (array_keys($absent_right) as $file) {
        $render .= sprintf("  %s\n", $file);
      }
    }

    if (!empty($content_diffs)) {
      $render .= "Files that differ in content:\n";

      $content_diffs_render_count = is_int($options['show_diff_file_limit']) ? $options['show_diff_file_limit'] : count($content_diffs);
      foreach ($content_diffs as $file => $diff) {
        $render .= sprintf("  %s\n", $file);

        if ($options['show_diff'] && $content_diffs_render_count > 0 && $diff instanceof Diff) {
          $render .= "--- DIFF START ---\n";
          $render .= $diff->render();
          $render .= "--- DIFF END ---\n";
          $content_diffs_render_count--;
        }
      }
    }

    return $render;
  }

}
