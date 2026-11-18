<?php

namespace KuKu\SFSystemToolsBundle\Service;

/**
 * Splits the log written by bin/deploy into runs.
 *
 * A run starts with a ">>>===" line followed by the date, and ends with a "<<<===" line.
 * A run without its end line (the script stopped early) is kept as unfinished, and lines
 * outside any run (e.g. an error before the script got going) become a run of their own.
 */
class DeployLogParser
{
    private const START = '>>>===';
    private const END = '<<<===';

    /**
     * @return list<DeployRun> in the order of the log, oldest first
     */
    public function parse(string $contents): array
    {
        $runs = [];
        $open = false;
        $startedAt = null;
        $lines = [];

        $flush = static function (bool $finished) use (&$runs, &$open, &$startedAt, &$lines): void {
            if ($open || [] !== $lines) {
                // A run that ended before its date line still gets a title.
                $title = $open ? ($startedAt ?? '(no date)') : null;
                $runs[] = new DeployRun($title, implode("\n", $lines), $finished);
            }
            $open = false;
            $startedAt = null;
            $lines = [];
        };

        foreach (explode("\n", $contents) as $line) {
            $line = rtrim($line);

            if (str_starts_with($line, self::START)) {
                $flush(false);
                $open = true;
                continue;
            }

            if (str_starts_with($line, self::END)) {
                if ($open) {
                    $flush(true);
                }
                continue;
            }

            if ('' === trim($line)) {
                continue;
            }

            if ($open && null === $startedAt) {
                $startedAt = trim($line);
                continue;
            }

            $lines[] = $line;
        }

        $flush(false);

        return $runs;
    }
}
