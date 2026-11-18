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

    public function testLogviewShowsTheRunsNewestFirst(): void
    {
        (new Filesystem())->dumpFile($this->deployLog, implode("\n", [
            '>>>===', 'Mon 1 Jan', 'first deploy', '<<<===',
            '>>>===', 'Tue 2 Jan', 'second deploy, failed',
        ]));

        $client = $this->client();
        $crawler = $client->request('GET', '/system/logview');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $headers = $crawler->filter('.accordion-button')->each(static fn ($node) => preg_replace('/\s+/', ' ', trim($node->text())));
        self::assertSame(['Tue 2 Jan did not finish', 'Mon 1 Jan'], $headers);
        self::assertStringContainsString('second deploy, failed', $crawler->filter('pre')->first()->text());
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
        self::assertStringContainsString('Symfony', $crawler->filter('pre')->text());
        self::assertSame('About', $crawler->filter('title')->text());
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
