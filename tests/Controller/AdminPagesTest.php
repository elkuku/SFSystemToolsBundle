<?php

namespace KuKu\SFSystemToolsBundle\Tests\Controller;

use KuKu\SFSystemToolsBundle\Tests\App\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\User\InMemoryUser;

class AdminPagesTest extends TestCase
{
    private string $deployLog;
    private ?TestKernel $kernel = null;

    protected function setUp(): void
    {
        $this->deployLog = sys_get_temp_dir().'/kuku_sf_system_tools_test/deploy.log';
        (new Filesystem())->remove($this->deployLog);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove($this->deployLog);
    }

    private function client(?string $role = 'ROLE_ADMIN'): KernelBrowser
    {
        $this->kernel = new TestKernel($this->deployLog);
        $this->kernel->boot();
        $client = new KernelBrowser($this->kernel);

        if (null !== $role) {
            // The users of the memory provider in TestKernel (name = password).
            $name = 'ROLE_ADMIN' === $role ? 'admin' : 'user';
            $client->loginUser(new InMemoryUser($name, $name, [$role]));
        }

        return $client;
    }

    public function testLogviewShowsOnlyTheNewestRun(): void
    {
        (new Filesystem())->dumpFile($this->deployLog, implode("\n", [
            '>>>===', 'Mon 1 Jan', 'first deploy', '<<<===',
            '>>>===', 'Tue 2 Jan', 'second deploy, failed',
        ]));

        $client = $this->client();
        $crawler = $client->request('GET', '/system/logview');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('.kuku-deploy-run'));
        $run = $crawler->filter('.kuku-deploy-run');
        self::assertStringContainsString('Tue 2 Jan', $run->filter('.card-header')->text());
        self::assertStringContainsString('did not finish', $run->filter('.card-header')->text());
        self::assertStringContainsString('second deploy, failed', $run->filter('pre')->text());
        self::assertStringNotContainsString('first deploy', $crawler->filter('body')->text());

        // Runs without a readable date can't go in the calendar; they are listed below it.
        self::assertSame(['Mon 1 Jan', 'Tue 2 Jan'], $crawler->filter('.kuku-deploy-undated a')->each(static fn ($node) => trim($node->text())));

        $crawler = $client->request('GET', '/system/logview?run=0');
        self::assertStringContainsString('first deploy', $crawler->filter('.kuku-deploy-run pre')->text());
    }

    public function testLogviewCalendar(): void
    {
        (new Filesystem())->dumpFile($this->deployLog, implode("\n", [
            '>>>===', 'Fri 16 Oct 2026 01:29:04 PM UTC',
            '**** Compiling assets ...', 'In AssetMapper.php: Fatal error',
            '>>>===', 'Wed 18 Nov 2026 08:11:59 PM UTC',
            '**** Installing composer dependencies ...', 'Nothing to install, update or remove',
            '**** Executing db migrations ...', ' [OK] Already at the latest version',
            '<<<===',
        ]));

        $client = $this->client();
        $crawler = $client->request('GET', '/system/logview');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('2 runs', $crawler->filter('.kuku-deploy-summary')->text());
        self::assertStringContainsString('1 with problems', $crawler->filter('.kuku-deploy-summary')->text());

        // The calendar shows the month of the newest run, with its day marked and selected.
        $calendar = $crawler->filter('.kuku-deploy-calendar');
        self::assertSame('November 2026', trim($calendar->filter('.card-header .fw-semibold')->text()));
        self::assertSame(['18'], $calendar->filter('tbody a')->each(static fn ($node) => trim($node->text())));
        self::assertSame('date', $calendar->filter('tbody a')->attr('aria-current'));
        self::assertStringContainsString('btn-success', (string) $calendar->filter('tbody a')->attr('class'));
        // The arrows change the month and keep the selected run.
        self::assertSame('?run=1&month=2026-10', $calendar->filter('a[title="Earlier runs"]')->attr('href'));
        self::assertCount(0, $calendar->filter('a[title="Later runs"]'));

        // The details show the newest run, with the [OK] line highlighted.
        $run = $crawler->filter('.kuku-deploy-run');
        self::assertStringContainsString('Wednesday, 18 Nov 2026 · 20:11', $run->filter('.card-header')->text());
        self::assertStringContainsString('latest', $run->filter('.card-header')->text());
        self::assertStringContainsString('Executing db migrations', $run->text());
        self::assertSame('[OK] Already at the latest version', trim($run->filter('pre .text-success')->text()));

        // October, and its unfinished run with the step it stopped in.
        $crawler = $client->request('GET', '/system/logview?run=0&month=2026-10');
        $calendar = $crawler->filter('.kuku-deploy-calendar');
        self::assertSame('October 2026', trim($calendar->filter('.card-header .fw-semibold')->text()));
        self::assertStringContainsString('btn-danger', (string) $calendar->filter('tbody a')->attr('class'));
        $run = $crawler->filter('.kuku-deploy-run');
        self::assertStringNotContainsString('latest', $run->filter('.card-header')->text());
        self::assertSame('In AssetMapper.php: Fatal error', $run->filter('pre .text-danger')->text());
        self::assertCount(1, $run->filter('[title="The run stopped here"]'));

        // An unknown run or month falls back to the newest run and its month.
        $crawler = $client->request('GET', '/system/logview?run=99&month=nope');
        self::assertSame('November 2026', trim($crawler->filter('.kuku-deploy-calendar .card-header .fw-semibold')->text()));
        self::assertStringContainsString('18 Nov 2026', $crawler->filter('.kuku-deploy-run .card-header')->text());
    }

    public function testLogviewShowsTheStateOfTheOtherRunsThatDay(): void
    {
        (new Filesystem())->dumpFile($this->deployLog, implode("\n", [
            '>>>===', 'Mon 23 Nov 2026 02:45:58 PM UTC', 'Script cache:clear returned with error code 1', '<<<===',
            '>>>===', 'Mon 23 Nov 2026 02:55:20 PM UTC', 'stopped',
            '>>>===', 'Mon 23 Nov 2026 03:04:17 PM UTC', ' [OK] done', '<<<===',
        ]));

        $client = $this->client();
        $crawler = $client->request('GET', '/system/logview');

        $buttons = $crawler->filter('nav[aria-label="Runs on this day"] a');
        self::assertSame(['14:45', '14:55', '15:04'], $buttons->each(static fn ($node) => trim($node->text())));
        self::assertSame(
            ['btn-outline-warning', 'btn-outline-danger', 'btn-success'],
            $buttons->each(static fn ($node) => preg_replace('/^.*\b(btn-(?:outline-)?(?:success|warning|danger))\b.*$/', '$1', (string) $node->attr('class'))),
        );
        self::assertSame(['Finished with errors', 'Did not finish', 'Deployed'], $buttons->each(static fn ($node) => $node->attr('title')));
        self::assertSame('true', $buttons->last()->attr('aria-current'));
    }

    public function testLogviewWithoutALogFile(): void
    {
        $client = $this->client();
        $crawler = $client->request('GET', '/system/logview');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('No deploy log found', $crawler->filter('.alert')->text());
    }

    public function testAboutShowsTheAboutCommand(): void
    {
        $client = $this->client();
        $crawler = $client->request('GET', '/system/about');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Symfony', $crawler->filter('.kuku-about-raw pre')->text());
        self::assertSame('About', $crawler->filter('title')->text());
    }

    public function testAboutShowsTheSections(): void
    {
        $client = $this->client();
        $crawler = $client->request('GET', '/system/about', server: ['SERVER_SOFTWARE' => 'nginx/1.26']);

        // The test app has no Doctrine, so there is no database section.
        self::assertSame(
            ['Symfony', 'PHP', 'Server'],
            $crawler->filter('.kuku-about-section .card-header')->each(static fn ($node) => trim($node->text())),
        );
        self::assertStringContainsString(\Symfony\Component\HttpKernel\Kernel::VERSION, $crawler->filter('.kuku-about-section')->first()->text());
        // The test kernel runs in "test" with debug on: both are flagged.
        $warnings = $crawler->filter('.kuku-about-section')->first()->filter('.badge.text-bg-warning')->each(static fn ($node) => $node->text());
        self::assertContains('test', $warnings);
        self::assertContains('on', $warnings);
        self::assertStringContainsString('nginx/1.26', $crawler->filter('.kuku-about-section')->last()->text());
    }

    public function testThePagesNeedTheRole(): void
    {
        foreach (['/system/about', '/system/logview'] as $url) {
            $client = $this->client('ROLE_USER');
            $client->request('GET', $url);
            self::assertSame(403, $client->getResponse()->getStatusCode(), $url);
            $this->kernel->shutdown();

            $client = $this->client(null);
            $client->request('GET', $url);
            self::assertSame(401, $client->getResponse()->getStatusCode(), $url);
            $this->kernel->shutdown();
        }
    }

    public function testSystemInfoStillWorks(): void
    {
        $client = $this->client(null);
        $client->request('GET', '/system/info/');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame(
            ['sf_version' => \Symfony\Component\HttpKernel\Kernel::VERSION, 'php_version' => 'N/A'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }
}
