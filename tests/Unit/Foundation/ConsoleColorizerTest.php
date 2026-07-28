<?php

namespace Eyika\Atom\Framework\Tests\Unit\Foundation;

use Eyika\Atom\Framework\Foundation\Console\ConsoleColorizer;
use Eyika\Atom\Framework\Support\Inspiring;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * The console output path is Monolog-based, not Symfony's OutputFormatter, so Symfony-style
 * tags (<options=bold>, <fg=gray>, </>) used to leak to the terminal as literal text and
 * every line carried a "[datetime] channel.LEVEL:" log prefix. The colorizer now translates
 * those tags to ANSI (and strips the rest), emits just the message, and only wraps warnings+
 * in a level colour. Inspiring emits ANSI directly rather than relying on any of that.
 */
class ConsoleColorizerTest extends TestCase
{
    private function render(string $message, Level $level = Level::Info): string
    {
        $formatter = new ConsoleColorizer("%message%\n", 'D M j H:i:s Y', true, true);

        return $formatter->format(
            new LogRecord(new \DateTimeImmutable('2026-07-28T08:04:11'), 'app', $level, $message)
        );
    }

    public function test_translates_formatter_tags_to_ansi_and_leaks_none(): void
    {
        $out = $this->render('<options=bold>Bold</> and <fg=red>Red</>');

        $this->assertStringNotContainsString('<options', $out);
        $this->assertStringNotContainsString('<fg', $out);
        $this->assertStringNotContainsString('</>', $out);
        $this->assertStringContainsString("\033[1m", $out);   // bold
        $this->assertStringContainsString("\033[31m", $out);  // red fg
        $this->assertStringContainsString("\033[0m", $out);   // reset from </>
    }

    public function test_supports_a_combined_tag(): void
    {
        $out = $this->render('<fg=white;bg=red;options=bold>alert</>');
        $this->assertStringContainsString("\033[37;41;1m", $out);
        $this->assertStringNotContainsString('<fg', $out);
    }

    public function test_strips_unknown_named_tags(): void
    {
        $out = $this->render('<info>hi</info> <comment>c</comment>');
        $this->assertStringNotContainsString('<info>', $out);
        $this->assertStringNotContainsString('<comment>', $out);
        $this->assertStringContainsString('hi', $out);
    }

    public function test_no_log_prefix_and_message_is_preserved(): void
    {
        $out = $this->render('just a message');
        $this->assertStringNotContainsString('INFO', $out);
        $this->assertStringNotContainsString('app.', $out);   // no "app.INFO:" channel prefix
        $this->assertStringNotContainsString('2026', $out);   // no datetime
        $this->assertStringContainsString('just a message', $out);
    }

    public function test_warnings_and_above_get_a_level_colour_but_info_does_not(): void
    {
        $this->assertStringContainsString("\033[33m", $this->render('careful', Level::Warning)); // yellow
        $this->assertStringContainsString("\033[31m", $this->render('boom', Level::Error));      // red
        $this->assertStringNotContainsString("\033[33m", $this->render('fyi', Level::Info));     // not wrapped
    }

    public function test_inspiring_quote_uses_ansi_not_tags(): void
    {
        $quote = Inspiring::quote();
        $this->assertStringNotContainsString('<options', $quote);
        $this->assertStringNotContainsString('<fg', $quote);
        $this->assertStringNotContainsString('</>', $quote);
        $this->assertStringContainsString("\033[1m", $quote); // bold quote
    }
}
