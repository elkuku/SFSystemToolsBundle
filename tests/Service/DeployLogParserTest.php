<?php

namespace KuKu\SFSystemToolsBundle\Tests\Service;

use KuKu\SFSystemToolsBundle\Service\DeployLogParser;
use KuKu\SFSystemToolsBundle\Service\DeployRun;
use PHPUnit\Framework\TestCase;

class DeployLogParserTest extends TestCase
{
    public function testParsesFinishedRuns(): void
    {
        $runs = (new DeployLogParser())->parse(<<<'LOG'
            >>>=============================================================================
            Wed 18 Nov 2026 07:23:47 PM UTC
            **** Installing composer dependencies ...

            Nothing to install.
            <<<=============================================================================
            >>>=============================================================================
            Wed 18 Nov 2026 07:48:54 PM UTC
            **** Executing db migrations ...
            <<<=============================================================================
            LOG);

        self::assertEquals([
            new DeployRun('Wed 18 Nov 2026 07:23:47 PM UTC', "**** Installing composer dependencies ...\nNothing to install.", true),
            new DeployRun('Wed 18 Nov 2026 07:48:54 PM UTC', '**** Executing db migrations ...', true),
        ], $runs);
    }

    public function testKeepsARunWithoutItsEndLineAsUnfinished(): void
    {
        $runs = (new DeployLogParser())->parse(<<<'LOG'
            >>>===
            Mon 1 Jan
            **** Installing composer dependencies ...
            Your requirements could not be resolved.
            >>>===
            Tue 2 Jan
            OK
            <<<===
            LOG);

        self::assertCount(2, $runs);
        self::assertFalse($runs[0]->finished);
        self::assertSame("**** Installing composer dependencies ...\nYour requirements could not be resolved.", $runs[0]->output);
        self::assertTrue($runs[1]->finished);
    }

    public function testOutputOutsideARunBecomesARunOfItsOwn(): void
    {
        $runs = (new DeployLogParser())->parse(<<<'LOG'
            >>>===
            Mon 1 Jan
            OK
            <<<===
            ./bin/deploy: line 18: dirname: command not found
            ❌ ERROR: Composer could not be found. Set COMPOSER_BIN to its path.
            >>>===
            Tue 2 Jan
            OK
            <<<===
            LOG);

        self::assertCount(3, $runs);
        self::assertNull($runs[1]->startedAt);
        self::assertFalse($runs[1]->finished);
        self::assertStringContainsString('Composer could not be found', $runs[1]->output);
    }

    public function testAStrayEndLineAndAnEmptyLogGiveNoRuns(): void
    {
        $parser = new DeployLogParser();

        self::assertSame([], $parser->parse(''));
        self::assertSame([], $parser->parse("<<<===\n\n"));
    }

    public function testARunWithoutADateLine(): void
    {
        $runs = (new DeployLogParser())->parse(">>>===\n<<<===\n>>>===\n>>>===\nMon 1 Jan\n<<<===");

        self::assertEquals([
            new DeployRun('(no date)', '', true),
            new DeployRun('(no date)', '', false),
            new DeployRun('Mon 1 Jan', '', true),
        ], $runs);
    }

    public function testARunStillInProgressIsUnfinished(): void
    {
        $runs = (new DeployLogParser())->parse(">>>===\nMon 1 Jan\n**** Installing composer dependencies ...\n");

        self::assertEquals([new DeployRun('Mon 1 Jan', '**** Installing composer dependencies ...', false)], $runs);
    }
}
