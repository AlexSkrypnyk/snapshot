<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Replacer;

use AlexSkrypnyk\File\File;

/**
 * Replaces content patterns in directory files.
 *
 * Provides fluent builder API for configuring replacement patterns.
 * Useful for normalizing volatile content (versions, hashes, timestamps)
 * before snapshot comparison or update.
 *
 * @phpstan-consistent-constructor
 */
class Replacer implements ReplacerInterface {

  /**
   * Named replacements.
   *
   * @var array<string, \AlexSkrypnyk\Snapshot\Replacer\ReplacementInterface>
   */
  protected array $replacements = [];

  /**
   * Maximum number of successful replacements before stopping (0 = unlimited).
   */
  protected int $maxReplacements = 4;

  /**
   * {@inheritdoc}
   */
  public static function create(): static {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public static function versions(): static {
    return static::create()
      // SRI integrity hashes.
      ->addReplacement(Replacement::create('integrity', '/sha512\-[A-Za-z0-9+\/]{86}={0,2}/', Replacement::INTEGRITY))
      // GitHub Actions with digests and version comments.
      ->addReplacement(Replacement::create('gha_digest_versioned', '/([\w.-]+\/[\w.-]+)@[a-f0-9]{40}\s*#\s*v\d+(?:\.\d+)*/', '${1}@' . Replacement::HASH . ' # ' . Replacement::VERSION))
      // GitHub Actions with digests (no version comments).
      ->addReplacement(Replacement::create('gha_digest', '/([\w.-]+\/[\w.-]+)@[a-f0-9]{40}/', '${1}@' . Replacement::HASH))
      // Git commit hashes (prefixed with #).
      ->addReplacement(Replacement::create('hash_anchor', '/#[a-fA-F0-9]{39,40}/', '#' . Replacement::HASH))
      // Git commit hashes (prefixed with @).
      ->addReplacement(Replacement::create('hash_at', '/@[a-fA-F0-9]{39,40}/', '@' . Replacement::HASH))
      // composer.json and package.json versions.
      ->addReplacement(Replacement::create('json_version', '/": "(?:\^|~|>=|<=)?\d+(?:\.\d+){0,2}(?:(?:-|@)[\w.-]+)?"/', '": "' . Replacement::VERSION . '"'))
      // Docker images with digests (must come before regular docker pattern).
      ->addReplacement(Replacement::create('docker_digest', '/([\w.-]+\/[\w.-]+:)(?:v)?\d+(?:\.\d+){0,2}(?:-[\w.-]+)?@sha256:[a-f0-9]{64}/', '${1}' . Replacement::VERSION))
      // Docker image tags.
      ->addReplacement(Replacement::create('docker_tag', '/([\w.-]+\/[\w.-]+:)(?:v)?\d+(?:\.\d+){0,2}(?:-[\w.-]+)?/', '${1}' . Replacement::VERSION))
      // Docker canary tags.
      ->addReplacement(Replacement::create('docker_canary', '/([\w.-]+\/[\w.-]+:)canary$/m', '${1}' . Replacement::VERSION))
      // GitHub Actions versions.
      ->addReplacement(Replacement::create('gha_version', '/([\w.-]+\/[\w.-]+)@(?:v)?\d+(?:\.\d+){0,2}(?:-[\w.-]+)?/', '${1}@' . Replacement::VERSION))
      // Node version in workflows.
      ->addReplacement(Replacement::create('node_version', '/(node-version:\s)(?:v)?\d+(?:\.\d+){0,2}(?:-[\w.-]+)?/', '${1}' . Replacement::VERSION))
      // Catch-all semver pattern.
      ->addReplacement(Replacement::create('semver', '/(?:\^|~)?v?\d+\.\d+\.\d+(?:(?:-|@)[\w.-]+)?/'));
  }

  /**
   * {@inheritdoc}
   */
  public function addReplacement(ReplacementInterface $replacement): static {
    $this->replacements[$replacement->getName()] = $replacement;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeReplacement(string $name): static {
    unset($this->replacements[$name]);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasReplacement(string $name): bool {
    return isset($this->replacements[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function getReplacement(string $name): ?ReplacementInterface {
    return $this->replacements[$name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getReplacements(): array {
    return $this->replacements;
  }

  /**
   * {@inheritdoc}
   */
  public function setMaxReplacements(int $max): static {
    $this->maxReplacements = $max;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxReplacements(): int {
    return $this->maxReplacements;
  }

  /**
   * {@inheritdoc}
   */
  public function replace(string &$content, ?int $max_replacements = NULL): bool {
    $max = $max_replacements ?? $this->maxReplacements;
    $replaced = 0;

    foreach ($this->replacements as $replacement) {
      if ($replacement->apply($content)) {
        $replaced++;
      }

      // Early exit after reaching max replacements to prevent excessive
      // replacements and optimize performance.
      if ($max > 0 && $replaced >= $max) {
        break;
      }
    }

    return $replaced > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function replaceInDir(string $directory): static {
    $files = File::scandir($directory);

    foreach ($files as $file) {
      // @codeCoverageIgnoreStart
      if (!is_file($file)) {
        continue;
      }
      if (!is_readable($file)) {
        continue;
      }
      if (!is_writable($file)) {
        continue;
      }
      // @codeCoverageIgnoreEnd
      $content = (string) file_get_contents($file);

      $was_replaced = $this->replace($content);

      if ($was_replaced) {
        file_put_contents($file, $content);
      }
    }

    return $this;
  }

}
