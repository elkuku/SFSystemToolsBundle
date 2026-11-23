<?php

namespace KuKu\SFSystemToolsBundle\Service;

use DateTimeImmutable;

/**
 * Lays out the deploy runs as a month calendar (weeks from Monday to Sunday).
 *
 * Runs are referred to by their position in the log (oldest first), which stays the
 * same when new runs are appended.
 */
class DeployCalendar
{
    /** Worse statuses win when a day has several runs. */
    private const SEVERITY = [
        DeployRun::STATUS_OK => 0,
        DeployRun::STATUS_ERRORS => 1,
        DeployRun::STATUS_UNFINISHED => 2,
    ];

    /**
     * @param array<int, DeployRun> $runs     by their position in the log
     * @param DateTimeImmutable     $month    any day of the month to show
     * @param ?int                  $selected the position of the selected run
     *
     * @return array{
     *     label: string,
     *     month: string,
     *     prev: ?string,
     *     next: ?string,
     *     weeks: list<list<?array{day: int, runs: list<int>, latest: ?int, status: ?string, selected: bool}>>,
     * }
     */
    public function build(array $runs, DateTimeImmutable $month, ?int $selected = null): array
    {
        $first = $month->modify('first day of this month')->setTime(0, 0);
        $key = $first->format('Y-m');

        /** @var array<string, list<int>> $byDay runs per day (Y-m-d), oldest first */
        $byDay = [];
        $months = [];
        foreach ($runs as $position => $run) {
            $date = $run->startedAtDate();
            if (null === $date) {
                continue;
            }
            $byDay[$date->format('Y-m-d')][] = $position;
            $months[$date->format('Y-m')] = true;
        }

        $weeks = [];
        $week = array_fill(0, (int) $first->format('N') - 1, null);
        $daysInMonth = (int) $first->format('t');

        for ($day = 1; $day <= $daysInMonth; ++$day) {
            $positions = $byDay[$key.'-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT)] ?? [];
            $status = null;
            foreach ($positions as $position) {
                $runStatus = $runs[$position]->status();
                if (null === $status || self::SEVERITY[$runStatus] > self::SEVERITY[$status]) {
                    $status = $runStatus;
                }
            }

            $week[] = [
                'day' => $day,
                'runs' => $positions,
                'latest' => [] === $positions ? null : $positions[\count($positions) - 1],
                'status' => $status,
                'selected' => null !== $selected && \in_array($selected, $positions, true),
            ];

            if (7 === \count($week)) {
                $weeks[] = $week;
                $week = [];
            }
        }

        if ([] !== $week) {
            $weeks[] = array_pad($week, 7, null);
        }

        $months = array_keys($months);
        sort($months);
        $earlier = array_values(array_filter($months, static fn (string $m): bool => $m < $key));
        $later = array_values(array_filter($months, static fn (string $m): bool => $m > $key));

        return [
            'label' => $first->format('F Y'),
            'month' => $key,
            'prev' => [] === $earlier ? null : $earlier[\count($earlier) - 1],
            'next' => $later[0] ?? null,
            'weeks' => $weeks,
        ];
    }
}
