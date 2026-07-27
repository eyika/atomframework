<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\NamespaceHelper;
use PHPUnit\Framework\TestCase;

/**
 * Covers PERF-17: composer.json is parsed once per path and memoized, instead of
 * being re-read + json_decoded on every getBaseNamespace() call (the Application
 * constructor calls it three times per boot).
 */
class NamespaceHelperCacheTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        NamespaceHelper::flushComposerCache();
        $this->path = sys_get_temp_dir() . '/atomtest_composer_' . getmypid() . '.json';
        $this->writeComposer('App\\');
    }

    protected function tearDown(): void
    {
        NamespaceHelper::flushComposerCache();
        @unlink($this->path);
        parent::tearDown();
    }

    private function writeComposer(string $appNamespace): void
    {
        file_put_contents($this->path, json_encode([
            'autoload' => ['psr-4' => [$appNamespace => 'app/', 'Database\\' => 'database/']],
        ]));
    }

    public function test_resolves_namespace_for_folder(): void
    {
        $this->assertSame('App', NamespaceHelper::getBaseNamespace($this->path, 'app'));
        $this->assertSame('Database', NamespaceHelper::getBaseNamespace($this->path, 'database'));
    }

    public function test_result_is_memoized_until_flush(): void
    {
        $this->assertSame('App', NamespaceHelper::getBaseNamespace($this->path, 'app'));

        // Rewrite the file on disk — a memoized lookup must NOT observe the change.
        $this->writeComposer('Rewritten\\');
        $this->assertSame('App', NamespaceHelper::getBaseNamespace($this->path, 'app'));

        // After a flush the fresh contents are read.
        NamespaceHelper::flushComposerCache();
        $this->assertSame('Rewritten', NamespaceHelper::getBaseNamespace($this->path, 'app'));
    }
}
