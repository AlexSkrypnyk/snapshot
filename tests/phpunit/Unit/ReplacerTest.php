<?php

declare(strict_types=1);

namespace AlexSkrypnyk\Snapshot\Tests\Unit;

use AlexSkrypnyk\Snapshot\Replacer\ReplacementInterface;
use AlexSkrypnyk\Snapshot\Replacer\Replacement;
use AlexSkrypnyk\Snapshot\Replacer\Replacer;
use AlexSkrypnyk\File\File;
use AlexSkrypnyk\Snapshot\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Replacer::class)]
final class ReplacerTest extends UnitTestCase {

  #[DataProvider('dataProviderVersionsPreset')]
  public function testVersionsPreset(string $name, string $input, string $expected, bool $expected_changed): void {
    $replacement = Replacer::versions()->getReplacement($name);
    $this->assertInstanceOf(ReplacementInterface::class, $replacement, sprintf('Replacement "%s" not found', $name));

    $result = $replacement->apply($input);

    $this->assertSame($expected_changed, $result);
    $this->assertSame($expected, $input);
  }

  public static function dataProviderVersionsPreset(): \Iterator {
    yield 'integrity - sha512 hash, replaced' => [
      'integrity',
      'integrity="sha512-abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/abcdefghijklmnopqrstuv=="',
      'integrity="__INTEGRITY__"',
      TRUE,
    ];
    yield 'integrity - sha256 hash, not replaced' => [
      'integrity',
      'integrity="sha256-abcdef"',
      'integrity="sha256-abcdef"',
      FALSE,
    ];
    yield 'integrity - random string, not replaced' => [
      'integrity',
      'some random text',
      'some random text',
      FALSE,
    ];

    yield 'gha_digest_versioned - hash with version comment, replaced' => [
      'gha_digest_versioned',
      'uses: actions/checkout@a81bbbf8298c0fa03ea29cdc473d45769f953675 # v4.2.0',
      'uses: actions/checkout@__HASH__ # __VERSION__',
      TRUE,
    ];
    yield 'gha_digest_versioned - hash without comment, not replaced' => [
      'gha_digest_versioned',
      'uses: actions/checkout@a81bbbf8298c0fa03ea29cdc473d45769f953675',
      'uses: actions/checkout@a81bbbf8298c0fa03ea29cdc473d45769f953675',
      FALSE,
    ];

    yield 'gha_digest - hash only, replaced' => [
      'gha_digest',
      'uses: actions/checkout@a81bbbf8298c0fa03ea29cdc473d45769f953675',
      'uses: actions/checkout@__HASH__',
      TRUE,
    ];
    yield 'gha_digest - short hash, not replaced' => [
      'gha_digest',
      'uses: actions/checkout@a81bbbf',
      'uses: actions/checkout@a81bbbf',
      FALSE,
    ];

    yield 'hash_anchor - 40 char hash with #, replaced' => [
      'hash_anchor',
      'commit #a81bbbf8298c0fa03ea29cdc473d45769f953675',
      'commit #__HASH__',
      TRUE,
    ];
    yield 'hash_anchor - short hash with #, not replaced' => [
      'hash_anchor',
      'commit #a81bbbf',
      'commit #a81bbbf',
      FALSE,
    ];

    yield 'hash_at - 40 char hash with @, replaced' => [
      'hash_at',
      'ref @a81bbbf8298c0fa03ea29cdc473d45769f953675',
      'ref @__HASH__',
      TRUE,
    ];
    yield 'hash_at - short hash with @, not replaced' => [
      'hash_at',
      'ref @a81bbbf',
      'ref @a81bbbf',
      FALSE,
    ];

    yield 'json_version - semver in quotes, replaced' => [
      'json_version',
      '"version": "1.2.3"',
      '"version": "__VERSION__"',
      TRUE,
    ];
    yield 'json_version - caret version, replaced' => [
      'json_version',
      '"require": "^1.2.3"',
      '"require": "__VERSION__"',
      TRUE,
    ];
    yield 'json_version - tilde version, replaced' => [
      'json_version',
      '"require": "~1.2.3"',
      '"require": "__VERSION__"',
      TRUE,
    ];
    yield 'json_version - no quotes, not replaced' => [
      'json_version',
      'version: 1.2.3',
      'version: 1.2.3',
      FALSE,
    ];

    yield 'docker_digest - image with digest, replaced' => [
      'docker_digest',
      'image: nginx/nginx:1.21.0@sha256:a81bbbf8298c0fa03ea29cdc473d45769f953675a81bbbf8298c0fa03ea29cdc',
      'image: nginx/nginx:__VERSION__',
      TRUE,
    ];
    yield 'docker_digest - image without digest, not replaced' => [
      'docker_digest',
      'image: nginx/nginx:1.21.0',
      'image: nginx/nginx:1.21.0',
      FALSE,
    ];

    yield 'docker_tag - image with tag, replaced' => [
      'docker_tag',
      'image: nginx/nginx:1.21.0',
      'image: nginx/nginx:__VERSION__',
      TRUE,
    ];
    yield 'docker_tag - image with v prefix, replaced' => [
      'docker_tag',
      'image: myorg/myapp:v1.2.3',
      'image: myorg/myapp:__VERSION__',
      TRUE,
    ];
    yield 'docker_tag - image without tag, not replaced' => [
      'docker_tag',
      'image: nginx/nginx',
      'image: nginx/nginx',
      FALSE,
    ];
    yield 'docker_tag - latest tag, not replaced' => [
      'docker_tag',
      'image: nginx/nginx:latest',
      'image: nginx/nginx:latest',
      FALSE,
    ];

    yield 'docker_canary - canary tag, replaced' => [
      'docker_canary',
      'image: myorg/myapp:canary',
      'image: myorg/myapp:__VERSION__',
      TRUE,
    ];
    yield 'docker_canary - other tag, not replaced' => [
      'docker_canary',
      'image: myorg/myapp:latest',
      'image: myorg/myapp:latest',
      FALSE,
    ];

    yield 'gha_version - action with version, replaced' => [
      'gha_version',
      'uses: actions/setup-node@v4.0.0',
      'uses: actions/setup-node@__VERSION__',
      TRUE,
    ];
    yield 'gha_version - action with major only, replaced' => [
      'gha_version',
      'uses: actions/checkout@v4',
      'uses: actions/checkout@__VERSION__',
      TRUE,
    ];
    yield 'gha_version - action without version, not replaced' => [
      'gha_version',
      'uses: actions/checkout@main',
      'uses: actions/checkout@main',
      FALSE,
    ];

    yield 'node_version - version number, replaced' => [
      'node_version',
      'node-version: 20.10.0',
      'node-version: __VERSION__',
      TRUE,
    ];
    yield 'node_version - with v prefix, replaced' => [
      'node_version',
      'node-version: v18.0.0',
      'node-version: __VERSION__',
      TRUE,
    ];
    yield 'node_version - major only, replaced' => [
      'node_version',
      'node-version: 20',
      'node-version: __VERSION__',
      TRUE,
    ];

    yield 'semver - basic version, replaced' => [
      'semver',
      'version 1.2.3',
      'version __VERSION__',
      TRUE,
    ];
    yield 'semver - with v prefix, replaced' => [
      'semver',
      'tag v1.2.3',
      'tag __VERSION__',
      TRUE,
    ];
    yield 'semver - with caret, replaced' => [
      'semver',
      'require ^1.2.3',
      'require __VERSION__',
      TRUE,
    ];
    yield 'semver - with tilde, replaced' => [
      'semver',
      'require ~1.2.3',
      'require __VERSION__',
      TRUE,
    ];
    yield 'semver - with prerelease, replaced' => [
      'semver',
      'version 1.2.3-beta.1',
      'version __VERSION__',
      TRUE,
    ];
    yield 'semver - two parts only, not replaced' => [
      'semver',
      'version 1.2',
      'version 1.2',
      FALSE,
    ];
    yield 'semver - single number, not replaced' => [
      'semver',
      'version 1',
      'version 1',
      FALSE,
    ];
  }

  public function testCreate(): void {
    $replacer = Replacer::create();

    $this->assertInstanceOf(Replacer::class, $replacer);
    $this->assertSame([], $replacer->getReplacements());
  }

  public function testMaxReplacementsDefault(): void {
    $replacer = Replacer::create();

    $this->assertSame(4, $replacer->getMaxReplacements());
  }

  public function testSetMaxReplacements(): void {
    $replacer = Replacer::create();

    $result = $replacer->setMaxReplacements(10);

    $this->assertSame($replacer, $result);
    $this->assertSame(10, $replacer->getMaxReplacements());
  }

  public function testAddReplacement(): void {
    $replacer = Replacer::create();
    $replacement = Replacement::create('test', '/foo/', 'bar');

    $result = $replacer->addReplacement($replacement);

    $this->assertSame($replacer, $result);
    $this->assertTrue($replacer->hasReplacement('test'));
    $this->assertSame($replacement, $replacer->getReplacement('test'));
  }

  public function testRemoveReplacement(): void {
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('test', '/foo/', 'bar'));

    $result = $replacer->removeReplacement('test');

    $this->assertSame($replacer, $result);
    $this->assertFalse($replacer->hasReplacement('test'));
  }

  public function testGetReplacementNotFound(): void {
    $replacer = Replacer::create();

    $this->assertNotInstanceOf(ReplacementInterface::class, $replacer->getReplacement('nonexistent'));
  }

  public function testReplace(): void {
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('test', '/foo/', 'bar'));

    $content = 'foo baz foo';
    $result = $replacer->replace($content);

    $this->assertTrue($result);
    $this->assertSame('bar baz bar', $content);
  }

  public function testReplaceNoChange(): void {
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('test', '/nonexistent/', 'bar'));

    $content = 'foo baz foo';
    $result = $replacer->replace($content);

    $this->assertFalse($result);
    $this->assertSame('foo baz foo', $content);
  }

  public function testReplaceWithMaxReplacements(): void {
    $replacer = Replacer::create()
      ->setMaxReplacements(2)
      ->addReplacement(Replacement::create('r1', '/a/', 'A'))
      ->addReplacement(Replacement::create('r2', '/b/', 'B'))
      ->addReplacement(Replacement::create('r3', '/c/', 'C'))
      ->addReplacement(Replacement::create('r4', '/d/', 'D'));

    $content = 'a b c d';
    $replacer->replace($content);

    // Only first 2 replacements should be applied.
    $this->assertSame('A B c d', $content);
  }

  public function testReplaceWithMaxReplacementsOverride(): void {
    $replacer = Replacer::create()
      ->setMaxReplacements(2)
      ->addReplacement(Replacement::create('r1', '/a/', 'A'))
      ->addReplacement(Replacement::create('r2', '/b/', 'B'))
      ->addReplacement(Replacement::create('r3', '/c/', 'C'));

    $content = 'a b c';
    $replacer->replace($content, 3);

    // Override allows all 3 replacements.
    $this->assertSame('A B C', $content);
  }

  public function testReplaceWithUnlimitedReplacements(): void {
    $replacer = Replacer::create()
      ->setMaxReplacements(0)
      ->addReplacement(Replacement::create('r1', '/a/', 'A'))
      ->addReplacement(Replacement::create('r2', '/b/', 'B'))
      ->addReplacement(Replacement::create('r3', '/c/', 'C'))
      ->addReplacement(Replacement::create('r4', '/d/', 'D'))
      ->addReplacement(Replacement::create('r5', '/e/', 'E'));

    $content = 'a b c d e';
    $replacer->replace($content);

    // All replacements should be applied.
    $this->assertSame('A B C D E', $content);
  }

  public function testReplaceInDir(): void {
    $before_dir = self::$fixtures . '/replacer/before';
    $after_dir = self::$fixtures . '/replacer/after';
    $temp_dir = self::$tmp . '/replacer_test';

    // Copy fixture to temp.
    File::copy($before_dir, $temp_dir);

    $replacer = Replacer::versions()->setMaxReplacements(0);

    $result = $replacer->replaceInDir($temp_dir);

    $this->assertSame($replacer, $result);
    $this->assertDirectoriesIdentical($after_dir, $temp_dir);
  }

  public function testAddExclusionsAppliesToAllRules(): void {
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('r1', '/\d+\.\d+\.\d+/', '__V1__'))
      ->addReplacement(Replacement::create('r2', '/v\d+/', '__V2__'))
      ->addExclusions(['/^0\./']);

    // Both rules should have the exclusion.
    $this->assertCount(1, $replacer->getReplacement('r1')?->getExclusions() ?? []);
    $this->assertCount(1, $replacer->getReplacement('r2')?->getExclusions() ?? []);

    $content = '1.2.3 0.0.1 v1';
    $replacer->replace($content);

    $this->assertSame('__V1__ 0.0.1 __V2__', $content);
  }

  public function testAddExclusionsAppliesToSpecificRule(): void {
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('r1', '/\d+\.\d+\.\d+/', '__V1__'))
      ->addReplacement(Replacement::create('r2', '/v\d+/', '__V2__'))
      ->addExclusions(['/^0\./'], 'r1');

    // Only r1 should have the exclusion.
    $this->assertCount(1, $replacer->getReplacement('r1')?->getExclusions() ?? []);
    $this->assertCount(0, $replacer->getReplacement('r2')?->getExclusions() ?? []);

    $content = '1.2.3 0.0.1';
    $replacer->replace($content);

    $this->assertSame('__V1__ 0.0.1', $content);
  }

  public function testAddExclusionsClearsAllExclusions(): void {
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('r1', '/a/', 'A'))
      ->addReplacement(Replacement::create('r2', '/b/', 'B'))
      ->addExclusions(['/pattern1/', '/pattern2/']);

    $this->assertCount(2, $replacer->getReplacement('r1')?->getExclusions() ?? []);
    $this->assertCount(2, $replacer->getReplacement('r2')?->getExclusions() ?? []);

    $result = $replacer->addExclusions([]);

    $this->assertSame($replacer, $result);
    $this->assertCount(0, $replacer->getReplacement('r1')?->getExclusions() ?? []);
    $this->assertCount(0, $replacer->getReplacement('r2')?->getExclusions() ?? []);
  }

  public function testAddExclusionsClearsSpecificRuleExclusions(): void {
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('r1', '/a/', 'A'))
      ->addReplacement(Replacement::create('r2', '/b/', 'B'))
      ->addExclusions(['/pattern/']);

    $this->assertCount(1, $replacer->getReplacement('r1')?->getExclusions() ?? []);
    $this->assertCount(1, $replacer->getReplacement('r2')?->getExclusions() ?? []);

    $replacer->addExclusions([], 'r1');

    $this->assertCount(0, $replacer->getReplacement('r1')?->getExclusions() ?? []);
    $this->assertCount(1, $replacer->getReplacement('r2')?->getExclusions() ?? []);
  }

  public function testAddExclusionsThrowsOnNonexistentName(): void {
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('existing', '/a/', 'A'));

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Replacement "nonexistent" does not exist.');

    $replacer->addExclusions(['/pattern/'], 'nonexistent');
  }

  public function testAddExclusionsReturnsSelf(): void {
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('test', '/a/', 'A'));

    $result = $replacer->addExclusions(['/pattern/']);

    $this->assertSame($replacer, $result);
  }

  public function testAddExclusionsWithMixedTypes(): void {
    $callback = fn(string $match): bool => $match === '9.9.9';

    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('test', '/\d+\.\d+\.\d+/', '__VERSION__'))
      ->addExclusions([
        // Regex.
        '/^0\./',
        // Exact string.
        '1.0.0',
        // Callback.
        $callback,
      ]);

    $content = '0.0.1 1.0.0 1.2.3 9.9.9 2.0.0';
    $replacer->replace($content);

    $this->assertSame('0.0.1 1.0.0 __VERSION__ 9.9.9 __VERSION__', $content);
  }

  public function testAddExclusionsIntegrationWithVersionsPreset(): void {
    $replacer = Replacer::versions()
      ->setMaxReplacements(0)
      ->addExclusions(['/^0\.0\./'], 'semver');

    $content = 'versions: 1.2.3, 0.0.1, 2.0.0';
    $replacer->replace($content);

    $this->assertSame('versions: __VERSION__, 0.0.1, __VERSION__', $content);
  }

  public function testAddExclusionsChaining(): void {
    $replacer = Replacer::create()
      ->addReplacement(Replacement::create('test', '/\d+\.\d+\.\d+/', '__VERSION__'))
      ->addExclusions(['/^0\./'])
      ->addExclusions(['1.0.0'], 'test');

    $exclusions = $replacer->getReplacement('test')?->getExclusions() ?? [];

    $this->assertCount(2, $exclusions);
  }

  public function testAddExclusionsWithIpAddress(): void {
    $replacer = Replacer::versions()
      ->setMaxReplacements(0)
      ->addExclusions(['127.0.0.1']);

    $content = 'version: 1.2.3, localhost: 127.0.0.1';
    $replacer->replace($content);

    $this->assertSame('version: __VERSION__, localhost: 127.0.0.1', $content);
  }

}
