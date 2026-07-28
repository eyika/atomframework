<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

class ConsoleColorizer extends LineFormatter
{
    private array $levelColors = [
        100 => "\033[36m",   // Cyan
        200 => "\033[32m",   // Green
        250 => "\033[34m",   // Blue
        300 => "\033[33m",   // Yellow
        400 => "\033[31m",   // Red
        500 => "\033[35m",   // Magenta
        550 => "\033[41m",   // Red Background
        600 => "\033[41;97m" // Red Background + White Text
    ];

    /** Named foreground colours → ANSI SGR code. */
    private const FG = [
        'black' => 30, 'red' => 31, 'green' => 32, 'yellow' => 33, 'blue' => 34,
        'magenta' => 35, 'cyan' => 36, 'white' => 37, 'default' => 39,
        'gray' => 90, 'grey' => 90, 'bright-red' => 91, 'bright-green' => 92,
        'bright-yellow' => 93, 'bright-blue' => 94,
    ];

    /** Named background colours → ANSI SGR code. */
    private const BG = [
        'black' => 40, 'red' => 41, 'green' => 42, 'yellow' => 43, 'blue' => 44,
        'magenta' => 45, 'cyan' => 46, 'white' => 47, 'default' => 49,
    ];

    /** Text options → ANSI SGR code. */
    private const OPTIONS = [
        'bold' => 1, 'dim' => 2, 'faint' => 2, 'italic' => 3,
        'underscore' => 4, 'underline' => 4, 'blink' => 5, 'reverse' => 7, 'conceal' => 8,
    ];

    public function format(LogRecord $record): string
    {
        // Translate any inline formatter markup to ANSI (and strip the rest) so tags like
        // <options=bold>/<fg=gray>/</> never leak to the terminal as literal text.
        $line = $this->renderTags(parent::format($record));

        // Emphasise warnings and above with a whole-line level colour; leave normal
        // output (debug/info/notice) alone so its own inline styling shows through.
        if ($record['level'] >= 300 && isset($this->levelColors[$record['level']])) {
            $line = $this->levelColors[$record['level']] . rtrim($line, "\r\n") . "\033[0m\n";
        }

        return $line;
    }

    /**
     * Convert Symfony-style output tags to ANSI escapes:
     *   <options=bold>, <fg=gray>, <bg=red>, <fg=white;bg=red;options=bold>, and </>.
     * Any other <tag>…</tag> markup is stripped so it can't reach the terminal verbatim.
     */
    private function renderTags(string $text): string
    {
        // <fg=...;bg=...;options=...> (Symfony allows a single tag with ; separated parts)
        $text = preg_replace_callback('/<((?:fg|bg|options)=[a-z0-9,;=\-]+)>/i', function ($m) {
            $codes = [];
            foreach (explode(';', $m[1]) as $part) {
                [$kind, $values] = array_pad(explode('=', $part, 2), 2, '');
                foreach (explode(',', strtolower($values)) as $value) {
                    $code = match (strtolower($kind)) {
                        'fg'      => self::FG[$value] ?? null,
                        'bg'      => self::BG[$value] ?? null,
                        'options' => self::OPTIONS[$value] ?? null,
                        default   => null,
                    };
                    if ($code !== null) {
                        $codes[] = $code;
                    }
                }
            }
            return $codes ? "\033[" . implode(';', $codes) . 'm' : '';
        }, $text);

        // Reset tag, then strip any leftover named tags (<info>, <comment>, </error>, …).
        $text = str_replace('</>', "\033[0m", $text);

        return preg_replace('/<\/?[a-z][a-z0-9,;=\-]*>/i', '', $text);
    }
}
