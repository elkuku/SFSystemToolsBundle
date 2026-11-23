<?php

namespace KuKu\SFSystemToolsBundle\Tests\Service;

use DateTimeImmutable;
use KuKu\SFSystemToolsBundle\Service\DeployCalendar;
use KuKu\SFSystemToolsBundle\Service\DeployRun;
use PHPUnit\Framework\TestCase;

class DeployCalendarTest extends TestCase
{
    /**
     * @return list<DeployRun>
     */
    private function runs(): array
    {
        return [
            new DeployRun('Fri 16 Oct 2026 01:29:04 PM UTC', 'OK', true),
            new DeployRun('Mon 23 Nov 2026 02:45:58 PM UTC', 'Script cache:clear returned with error code 1', true),
            new DeployRun('Mon 23 Nov 2026 03:04:17 PM UTC', 'OK', true),
            new DeployRun('Mon 1 Jan', 'no date', false),
            new DeployRun('Tue 22 Dec 2026 09:00:00 AM UTC', 'OK', false),
        ];
    }

    public function testLaysOutTheMonthFromMonday(): void
    {
        $calendar = (new DeployCalendar())->build($this->runs(), new DateTimeImmutable('2026-11-15'));

        self::assertSame('November 2026', $calendar['label']);
        self::assertSame('2026-11', $calendar['month']);
        // 1 Nov 2026 is a Sunday: six empty cells, then the 1st.
        self::assertSame([null, null, null, null, null, null], \array_slice($calendar['weeks'][0], 0, 6));
        self::assertSame(1, $calendar['weeks'][0][6]['day'] ?? null);
        self::assertCount(6, $calendar['weeks']);
        // The 30th is a Monday, the rest of the last week is empty.
        self::assertSame(30, $calendar['weeks'][5][0]['day'] ?? null);
        self::assertSame([null, null, null, null, null, null], \array_slice($calendar['weeks'][5], 1));
    }

    public function testMarksTheDaysWithRuns(): void
    {
        $calendar = (new DeployCalendar())->build($this->runs(), new DateTimeImmutable('2026-11-01'), 2);

        $days = array_values(array_filter(array_merge(...$calendar['weeks']), static fn (?array $day): bool => null !== $day && [] !== $day['runs']));

        self::assertCount(1, $days);
        self::assertSame(23, $days[0]['day']);
        self::assertSame([1, 2], $days[0]['runs']);
        self::assertSame(2, $days[0]['latest']);
        // The worse status of the day wins.
        self::assertSame(DeployRun::STATUS_ERRORS, $days[0]['status']);
        self::assertTrue($days[0]['selected']);
    }

    public function testLinksToTheNearestMonthsWithRuns(): void
    {
        $builder = new DeployCalendar();

        $november = $builder->build($this->runs(), new DateTimeImmutable('2026-11-01'));
        self::assertSame('2026-10', $november['prev']);
        self::assertSame('2026-12', $november['next']);

        $october = $builder->build($this->runs(), new DateTimeImmutable('2026-10-01'));
        self::assertNull($october['prev']);

        // A month without runs still links to its neighbours.
        $june = $builder->build($this->runs(), new DateTimeImmutable('2027-06-01'));
        self::assertSame('2026-12', $june['prev']);
        self::assertNull($june['next']);
    }
}
