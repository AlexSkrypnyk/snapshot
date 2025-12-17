<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Compare;

/**
 * Interface for rendering diff results.
 */
interface RenderableInterface {

  /**
   * Renders a diff result.
   *
   * @param array<string, mixed> $options
   *   Rendering options.
   * @param callable|null $renderer
   *   Optional custom renderer callback.
   *
   * @return string|null
   *   The rendered diff or NULL if there is nothing to render.
   */
  public function render(array $options = [], ?callable $renderer = NULL): ?string;

}
