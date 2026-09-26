<?php

namespace Eyika\Atom\Framework\Tests\Unit\Console;

use DateTimeImmutable;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * Reported by a downstream consumer building a benchmark command: *"there is no `line()`"*, and
 * every output method *"routes through the PSR logger, so each arrives with a level and a
 * timestamp. Correct for events, wrong for a table of numbers."*
 *
 * The first half is true and is now fixed. **The second half is not:** `info()` resolves the logger
 * with `isConsole: true`, which formats records as `"%message%\n"` — no datetime, no channel, no
 * level. Writing to `STDOUT` by hand was never necessary. The report is a fair account of what the
 * API *looks* like, though: `info()` is a log-level name, so nobody reaches for it to print a table
 * of numbers, and there was no method named for that job. `line()` and `newLine()` close that.
 *
 * Note how the decoration claim is tested. Console output goes through Monolog to `php://stdout`,
 * which `ob_start()` does **not** capture — a buffering test sees an empty string and proves
 * nothing in either direction, which is how the first version of this file managed to "fail"
 * against working code. The formatter is what would add a level or a timestamp, so it is asked
 * directly.
 */
class CommandOutputTest extends TestCase
{
    private function command(): Command
    {
        return new class extends Command {
            public string $signature = 'probe';

            public function handle(): bool
            {
                return true;
            }

            /** The methods under test are protected; this exposes them without loosening them. */
            public function emit(string $method, ...$args): void
            {
                $this->{$method}(...$args);
            }
        };
    }

    // ---------------------------------------------------------------- output shape

    public function test_the_command_base_offers_a_line_method(): void
    {
        $this->assertTrue(
            method_exists(Command::class, 'line'),
            'there is still no line(), so printing a plain row has no obvious method'
        );
    }

    /** The claim worth pinning: a table of numbers comes out as a table of numbers. */
    public function test_console_output_carries_no_level_and_no_timestamp(): void
    {
        $handlers = logger(isConsole: true)->getHandlers();
        $this->assertNotEmpty($handlers, 'the console logger has no handler');

        $formatted = $handlers[0]->getFormatter()->format(new LogRecord(
            new DateTimeImmutable(),
            'Atom',
            Level::Info,
            '4   1820   38ms'
        ));

        $this->assertSame("4   1820   38ms\n", $formatted, 'console output arrived decorated');
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $formatted, 'a date was prefixed');
        $this->assertStringNotContainsStringIgnoringCase('info', $formatted, 'a level name was prefixed');
    }

    /** A file log IS decorated — which is the distinction the report was reaching for. */
    public function test_a_file_log_line_is_still_decorated(): void
    {
        $formatted = logger()->getHandlers()[0]->getFormatter()->format(new LogRecord(
            new DateTimeImmutable(),
            'Atom',
            Level::Info,
            'an event worth recording'
        ));

        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $formatted, 'a file log lost its timestamp');
        $this->assertStringContainsStringIgnoringCase('INFO', $formatted, 'a file log lost its level');
    }

    public function test_line_and_new_line_are_callable(): void
    {
        $command = $this->command();

        $command->emit('line', 'a plain row');
        $command->emit('newLine', 2);

        $this->assertTrue(method_exists($command, 'newLine'));
    }

    // ---------------------------------------------------------------- the argument shapes

    /**
     * The other half of the report: flags are NOT readable off the arguments array.
     *
     * `arguments()` is the raw argv list, numerically indexed, so `$arguments['workers']` finds
     * nothing — and with a `?? $default` beside it, silently. On their benchmark that meant a run
     * asked for 4 workers and used 32. The docs already said `argument()` takes an index and
     * `option()` takes a name, so this is a guard rather than a correction: neither should quietly
     * become the other.
     */
    public function test_argument_is_indexed_and_option_is_named(): void
    {
        $command = $this->command();
        $command->setArguments(['make:thing', '--workers=4', 'Widget']);

        $this->assertSame('4', $command->option('workers'), 'a named option is not readable by name');
        $this->assertSame('make:thing', $command->argument(0), 'a positional argument is not readable by index');

        // The trap itself: the flag is not in the arguments array under its name.
        $this->assertArrayNotHasKey('workers', $command->arguments());
    }

    /**
     * A default must come back as the caller wrote it.
     *
     * The return type was `null|string`, which **rewrote the caller's own default**:
     * `option('workers', 32)` handed back the string `"32"`, and `option('force', false)` handed
     * back `""` — falsy by luck rather than by contract.
     */
    public function test_a_missing_option_returns_the_default_unchanged(): void
    {
        $command = $this->command();
        $command->setArguments(['bench:contention']);

        $this->assertSame(32, $command->option('workers', 32));
        $this->assertFalse($command->option('force', false));
        $this->assertNull($command->option('absent'));
    }

    /** A flag given without `=` is a real boolean, not the string "1". */
    public function test_a_valueless_flag_reads_as_true(): void
    {
        $command = $this->command();
        $command->setArguments(['queue:work', '--daemon']);

        $this->assertTrue($command->option('daemon'));
    }
}
