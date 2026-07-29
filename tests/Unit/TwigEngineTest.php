<?php

namespace Eyika\Atom\Framework\Tests\Unit;

use Eyika\Atom\Framework\Support\Config;
use Eyika\Atom\Framework\Support\View\Exceptions\ViewNotFoundException;
use Eyika\Atom\Framework\Support\View\Twig;
use PHPUnit\Framework\TestCase;

/**
 * Covers the hardened home-grown Twig engine: escape-by-default output, the raw {!! !!} opt-in,
 * literal-PHP expressions (no dot corruption), control flow, layouts/includes, a persistent
 * mtime-invalidated cache, per-render isolation, and fail-loud missing templates.
 */
class TwigEngineTest extends TestCase
{
    private string $views;
    private string $compiled;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/atom-twig-' . uniqid();
        $this->views = $base . '/views';
        $this->compiled = $base . '/compiled';
        mkdir($this->views, 0755, true);
        mkdir($this->compiled, 0755, true);

        Config::set('view.compiled', $this->compiled);
        Config::set('view.cache', true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->compiled . '/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->views . '/*') ?: [] as $f) {
            @unlink($f);
        }
    }

    private function write(string $name, string $contents): void
    {
        file_put_contents($this->views . '/' . $name, $contents);
    }

    private function render(string $name, array $data = []): string
    {
        return (string) Twig::make($name, $this->views . '/', $data, true);
    }

    // --- Escaping --------------------------------------------------------------------------

    public function test_double_braces_escape_by_default(): void
    {
        $this->write('e.html', 'Hi {{ $name }}');
        $this->assertSame('Hi &lt;script&gt;', $this->render('e.html', ['name' => '<script>']));
    }

    public function test_bang_braces_emit_raw_html(): void
    {
        $this->write('r.html', 'Body: {!! $body !!}');
        $this->assertSame('Body: <b>bold</b>', $this->render('r.html', ['body' => '<b>bold</b>']));
    }

    public function test_triple_braces_are_a_legacy_escaped_alias(): void
    {
        $this->write('t.html', 'X {{{ $v }}} Y');
        $this->assertSame('X &lt;i&gt; Y', $this->render('t.html', ['v' => '<i>']));
    }

    public function test_raw_and_escaped_can_coexist_in_one_template(): void
    {
        $this->write('mix.html', '{!! $html !!}|{{ $text }}');
        $out = $this->render('mix.html', ['html' => '<hr>', 'text' => '<hr>']);
        $this->assertSame('<hr>|&lt;hr&gt;', $out);
    }

    // --- No dot corruption -----------------------------------------------------------------

    public function test_dotted_string_literals_survive(): void
    {
        // The old engine turned every "." into "->", corrupting URLs/config keys. It must not now.
        $this->write('d.html', '{!! $url !!}');
        $this->assertSame(
            'https://cdn.example.com/a.b.png',
            $this->render('d.html', ['url' => 'https://cdn.example.com/a.b.png'])
        );
    }

    public function test_object_property_access_uses_real_arrow(): void
    {
        $this->write('o.html', '{{ $user->name }}');
        $this->assertSame('Ada', $this->render('o.html', ['user' => (object) ['name' => 'Ada']]));
    }

    // --- Control flow ----------------------------------------------------------------------

    public function test_foreach_and_if_control_flow(): void
    {
        $this->write('c.html', "{% foreach(\$items as \$i): %}[{{ \$i }}]{% endforeach; %}{% if(\$show): %}!{% endif; %}");
        $this->assertSame('[a][b]!', $this->render('c.html', ['items' => ['a', 'b'], 'show' => true]));
    }

    // --- Layouts ---------------------------------------------------------------------------

    public function test_extends_block_yield_and_include(): void
    {
        // A layout that yields two sections and pulls in a footer partial (which defines + yields
        // its own block). compileBlock scans the whole expanded tree first, so yield/def order
        // across files doesn't matter.
        $this->write('layout.html', 'H:{% yield head %}|B:{% yield body %}|{% include foot.html %}');
        $this->write('foot.html', '{% block foot %}[foot]{% endblock %}F:{% yield foot %}');
        $this->write('page.html', "{% extends layout.html %}{% block head %}{{ \$t }}{% endblock %}{% block body %}hello{% endblock %}");

        $this->assertSame('H:TITLE|B:hello|F:[foot]', $this->render('page.html', ['t' => 'TITLE']));
    }

    public function test_parent_keyword_appends_to_block(): void
    {
        $this->write('base.html', '{% block c %}base{% endblock %}{% yield c %}');
        $this->write('child.html', "{% extends base.html %}{% block c %}@parent-child{% endblock %}");
        $this->assertSame('base-child', $this->render('child.html'));
    }

    // --- Caching ---------------------------------------------------------------------------

    public function test_compiled_file_persists_after_render(): void
    {
        $this->write('p.html', 'hi');
        $this->render('p.html');
        // The old engine deleted the compiled file immediately; it must survive now.
        $this->assertNotEmpty(glob($this->compiled . '/*'), 'compiled artifact should be retained');
    }

    public function test_cache_hit_does_not_recompile(): void
    {
        $this->write('h.html', 'v1');
        $this->render('h.html');
        $compiledFile = glob($this->compiled . '/*')[0];
        clearstatcache();
        $mtimeBefore = filemtime($compiledFile);

        // Second render with an unchanged source: no rewrite.
        $this->render('h.html');
        clearstatcache();
        $this->assertSame($mtimeBefore, filemtime($compiledFile));
    }

    public function test_touching_the_source_invalidates_the_cache(): void
    {
        $this->write('inv.html', 'first');
        $this->assertSame('first', $this->render('inv.html'));

        // Rewrite the source and stamp it in the future so its mtime beats the compiled artifact.
        $this->write('inv.html', 'second');
        touch($this->views . '/inv.html', time() + 5);

        $this->assertSame('second', $this->render('inv.html'));
    }

    public function test_cache_can_be_disabled_for_debugging(): void
    {
        Config::set('view.cache', false);
        $this->write('dbg.html', 'one');
        $this->assertSame('one', $this->render('dbg.html'));

        // Same-second edit: only a forced recompile (cache off) picks it up.
        $this->write('dbg.html', 'two');
        $this->assertSame('two', $this->render('dbg.html'));
    }

    // --- Per-render isolation (worker safety) ----------------------------------------------

    public function test_blocks_do_not_bleed_between_renders(): void
    {
        Config::set('view.cache', false); // force recompile so the static block table is exercised each time
        $this->write('shell.html', 'X{% yield slot %}X');
        $this->write('fills.html', "{% extends shell.html %}{% block slot %}FILLED{% endblock %}");
        $this->write('empty.html', '{% extends shell.html %}');

        $this->assertSame('XFILLEDX', $this->render('fills.html'));
        // If the static $blocks table bled across renders, 'empty' would inherit FILLED.
        $this->assertSame('XX', $this->render('empty.html'));
    }

    // --- Fail loud -------------------------------------------------------------------------

    public function test_missing_template_throws(): void
    {
        $this->expectException(ViewNotFoundException::class);
        $this->render('does-not-exist.html');
    }

    public function test_missing_include_throws(): void
    {
        $this->write('parent.html', 'A{% include ghost.html %}B');
        $this->expectException(ViewNotFoundException::class);
        $this->render('parent.html');
    }
}
