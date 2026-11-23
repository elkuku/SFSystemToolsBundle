<?php

namespace KuKu\SFSystemToolsBundle\Tests\Service;

use DateTimeImmutable;
use KuKu\SFSystemToolsBundle\Service\DeployRun;
use KuKu\SFSystemToolsBundle\Service\DeployStep;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeployRunTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function dates(): iterable
    {
        yield 'day first' => ['Wed 18 Nov 2026 08:11:59 PM UTC', '2026-11-18 20:11:59'];
        yield 'month first, year last' => ['Mon Nov 23 03:04:17 PM UTC 2026', '2026-11-23 15:04:17'];
        yield '24 hour clock' => ['Mon Nov 23 15:04:17 UTC 2026', '2026-11-23 15:04:17'];
        yield 'no year or time' => ['Mon 1 Jan', null];
        yield 'no date line' => ['(no date)', null];
        yield 'outside a run' => [null, null];
    }

    #[DataProvider('dates')]
    public function testStartedAtDate(?string $line, ?string $expected): void
    {
        $date = (new DeployRun($line, '', true))->startedAtDate();

        self::assertSame($expected, $date?->format('Y-m-d H:i:s'));
    }

    public function testStartedAgo(): void
    {
        $run = new DeployRun('Wed 18 Nov 2026 08:11:59 PM UTC', '', true);

        self::assertSame('just now', $run->startedAgo(new DateTimeImmutable('2026-11-18 20:12:30 UTC')));
        self::assertSame('1 minute ago', $run->startedAgo(new DateTimeImmutable('2026-11-18 20:13:30 UTC')));
        self::assertSame('3 hours ago', $run->startedAgo(new DateTimeImmutable('2026-11-18 23:20:00 UTC')));
        self::assertSame('5 days ago', $run->startedAgo(new DateTimeImmutable('2026-11-23 21:00:00 UTC')));
        self::assertSame('2 months ago', $run->startedAgo(new DateTimeImmutable('2027-01-20 00:00:00 UTC')));
        self::assertNull((new DeployRun('Mon 1 Jan', '', true))->startedAgo(new DateTimeImmutable()));
    }

    public function testStatus(): void
    {
        self::assertSame(DeployRun::STATUS_OK, (new DeployRun('d', " [OK] Already at the latest version", true))->status());
        self::assertSame(DeployRun::STATUS_ERRORS, (new DeployRun('d', 'Script cache:clear returned with error code 255', true))->status());
        self::assertSame(DeployRun::STATUS_UNFINISHED, (new DeployRun('d', 'OK', false))->status());
    }

    public function testSteps(): void
    {
        $run = new DeployRun('d', implode("\n", [
            'warming up',
            '**** Installing composer dependencies ...',
            'Nothing to install',
            '**** Compiling assets ...',
            'Compiled 123 assets',
        ]), false);

        self::assertEquals([
            new DeployStep(null, ['warming up']),
            new DeployStep('Installing composer dependencies', ['Nothing to install']),
            new DeployStep('Compiling assets', ['Compiled 123 assets'], true),
        ], $run->steps());

        self::assertSame([], (new DeployRun('d', '', true))->steps());
        self::assertEquals([new DeployStep('Executing db migrations', [])], (new DeployRun('d', '**** Executing db migrations ...', true))->steps());
    }

    public function testLineClass(): void
    {
        self::assertSame('text-success', DeployRun::lineClass(' [OK] Already at the latest version'));
        self::assertSame('text-success', DeployRun::lineClass('Successfully dumped .env files in .env.local.php'));
        self::assertSame('text-danger fw-semibold', DeployRun::lineClass('Executing script cache:clear [KO]'));
        self::assertSame('text-danger fw-semibold', DeployRun::lineClass('❌ ERROR: Composer could not be found.'));
        self::assertSame('text-danger fw-semibold', DeployRun::lineClass('PHP Fatal error: Allowed memory size exhausted'));
        self::assertSame('text-body-tertiary', DeployRun::lineClass(' // Compiled 123 assets'));
        self::assertSame('text-danger fw-semibold', DeployRun::lineClass('17:36:33 CRITICAL  [console] Error thrown while running command'));
        self::assertSame('', DeployRun::lineClass('Nothing to install, update or remove'));
        self::assertSame('', DeployRun::lineClass('  - Upgrading symfony/error-handler (v8.1.8 => v8.1.10): Extracting archive'));
        self::assertSame('text-warning', DeployRun::lineClass('Deprecation Notice: Constant E_STRICT is deprecated since 8.4, the error level was removed'));
        self::assertSame('text-warning', DeployRun::lineClass(' 10/39 [=====>---]  25%Deprecation Notice: the error level was removed'));
        self::assertSame('text-warning', DeployRun::lineClass('    Failed to download symfony/dotenv from dist: The "https://codeload.github.com/x.zip" file could not be downloaded'));
        self::assertSame(DeployRun::STATUS_OK, (new DeployRun('d', 'Deprecation Notice: the error level was removed', true))->status());
    }
}
