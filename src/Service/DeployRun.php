<?php

namespace KuKu\SFSystemToolsBundle\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;

/**
 * One run of bin/deploy in the deploy log.
 */
final readonly class DeployRun
{
    public const STATUS_OK = 'ok';
    public const STATUS_ERRORS = 'errors';
    public const STATUS_UNFINISHED = 'unfinished';

    /** Formats the "date" command writes, depending on the server's locale. */
    private const DATE_FORMATS = [
        'D d M Y h:i:s A T', // Wed 18 Nov 2026 08:11:59 PM UTC
        'D M d h:i:s A T Y', // Mon Nov 23 03:04:17 PM UTC 2026
        'D M d H:i:s T Y',   // Mon Nov 23 15:04:17 UTC 2026
    ];

    /**
     * Whole words only: "symfony/error-handler" or "$error" in composer output are no errors.
     */
    private const CLASS_ERROR = 'text-danger fw-semibold';

    private const ERROR_PATTERN = '/(?<![\w\/$-])(error|exception|fatal|failed|critical)(?![\w-])|❌/i';

    public function __construct(
        /** The first line of the run (the date), or null for output outside a run. */
        public ?string $startedAt,
        public string $output,
        /** False if the run has no closing line: it failed, was aborted or is still running. */
        public bool $finished,
    ) {
    }

    /**
     * The start of the run, or null if the date line can't be read.
     */
    public function startedAtDate(): ?DateTimeImmutable
    {
        if (null === $this->startedAt) {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $this->startedAt);
            if (false !== $date) {
                return $date;
            }
        }

        // Only accept free-form dates with a year and a time, "Mon 1 Jan" is no date.
        if (!preg_match('/\d{4}/', $this->startedAt) || !preg_match('/\d{1,2}:\d{2}/', $this->startedAt)) {
            return null;
        }

        try {
            return new DateTimeImmutable($this->startedAt);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * How long ago the run started, e.g. "3 hours ago", or null without a date.
     */
    public function startedAgo(DateTimeInterface $now): ?string
    {
        $date = $this->startedAtDate();
        if (null === $date) {
            return null;
        }

        $seconds = $now->getTimestamp() - $date->getTimestamp();

        foreach ([['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60]] as [$unit, $size]) {
            $count = intdiv($seconds, $size);
            if ($count >= 1) {
                return $count.' '.$unit.(1 === $count ? '' : 's').' ago';
            }
        }

        return 'just now';
    }

    public function hasErrors(): bool
    {
        foreach (explode("\n", $this->output) as $line) {
            if (self::isErrorLine($line)) {
                return true;
            }
        }

        return false;
    }

    public static function isErrorLine(string $line): bool
    {
        return self::CLASS_ERROR === self::lineClass($line);
    }

    /**
     * One of the STATUS_* constants.
     */
    public function status(): string
    {
        if (!$this->finished) {
            return self::STATUS_UNFINISHED;
        }

        return $this->hasErrors() ? self::STATUS_ERRORS : self::STATUS_OK;
    }

    /**
     * The output split at the "**** ..." lines of bin/deploy.
     *
     * @return list<DeployStep>
     */
    public function steps(): array
    {
        $steps = [];
        $title = null;
        $lines = [];

        foreach ('' === $this->output ? [] : explode("\n", $this->output) as $line) {
            if (str_starts_with($line, '****')) {
                if (null !== $title || [] !== $lines) {
                    $steps[] = [$title, $lines];
                }
                $title = trim($line, " *.\t");
                $title = '' === $title ? null : $title;
                $lines = [];
                continue;
            }
            $lines[] = $line;
        }

        if (null !== $title || [] !== $lines) {
            $steps[] = [$title, $lines];
        }

        $last = \count($steps) - 1;
        $result = [];
        foreach ($steps as $i => [$stepTitle, $stepLines]) {
            // An unfinished run stopped in its last step.
            $result[] = new DeployStep($stepTitle, $stepLines, !$this->finished && $i === $last);
        }

        return $result;
    }

    /**
     * A Bootstrap text class for a line of output: red for errors, green for "[OK]", muted for comments.
     */
    public static function lineClass(string $line): string
    {
        return match (true) {
            // Warnings that mention "error" or "failed" but don't break a deploy: PHP deprecations
            // (also behind a composer progress bar), and dist downloads that composer retries from source.
            1 === preg_match('/Deprecation Notice|PHP Deprecated|Failed to download .+ from dist/i', $line) => 'text-warning',
            1 === preg_match(self::ERROR_PATTERN, $line), str_contains($line, '[KO]') => self::CLASS_ERROR,
            str_contains($line, '[OK]'), str_starts_with($line, 'Successfully') => 'text-success',
            str_starts_with(ltrim($line), '//'), str_starts_with($line, 'Warning') => 'text-body-tertiary',
            default => '',
        };
    }
}
