<?php

namespace KuKu\SFSystemToolsBundle\Tests\Service;

use DateTimeImmutable;
use KuKu\SFSystemToolsBundle\Service\AboutInfo;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

class AboutInfoTest extends TestCase
{
    private function kernel(string $env = 'prod', bool $debug = false): KernelInterface
    {
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn($env);
        $kernel->method('isDebug')->willReturn($debug);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        return $kernel;
    }

    /**
     * A stand-in for a Doctrine DBAL connection.
     */
    private function connection(string $serverVersion, ?string $configured, bool $fails = false): object
    {
        return new class($serverVersion, $configured, $fails) {
            public function __construct(private string $version, private ?string $configured, private bool $fails)
            {
            }

            /** @return array<string, mixed> */
            public function getParams(): array
            {
                return array_filter(['driver' => 'pdo_mysql', 'charset' => 'utf8mb4', 'serverVersion' => $this->configured]);
            }

            public function getDatabasePlatform(): object
            {
                if ($this->fails) {
                    throw new RuntimeException('Connection refused');
                }

                return new \ArrayObject();
            }

            public function getServerVersion(): string
            {
                return $this->version;
            }

            public function getDatabase(): string
            {
                return 'main';
            }
        };
    }

    /**
     * @param list<array{title: string, rows: list<array{0: string, 1: string, 2: ?string}>}> $sections
     *
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    private function rows(array $sections, string $title): array
    {
        foreach ($sections as $section) {
            if ($section['title'] === $title) {
                return array_column($section['rows'], null, 0);
            }
        }

        self::fail('No section '.$title);
    }

    public function testSectionsWithoutADatabase(): void
    {
        $sections = (new AboutInfo($this->kernel()))->sections();

        self::assertSame(['Symfony', 'PHP', 'Server'], array_column($sections, 'title'));
        self::assertSame(Kernel::VERSION, $this->rows($sections, 'Symfony')['Version'][1]);
        self::assertSame(\PHP_VERSION, $this->rows($sections, 'PHP')['Version'][1]);
    }

    public function testDatabaseVersionMatchesTheConfiguredOne(): void
    {
        $info = new AboutInfo($this->kernel(), $this->connection('12.3.4-MariaDB-deb12', '12.3.4-MariaDB'));
        $rows = $this->rows($info->sections(), 'Database');

        self::assertSame(['Platform', 'ArrayObject', null], $rows['Platform']);
        self::assertSame('12.3.4-MariaDB-deb12', $rows['Server version'][1]);
        self::assertSame(['Configured serverVersion', '12.3.4-MariaDB', 'ok'], $rows['Configured serverVersion']);
        self::assertSame('main', $rows['Database'][1]);
        self::assertSame('pdo_mysql', $rows['Driver'][1]);
        self::assertSame('utf8mb4', $rows['Charset'][1]);
    }

    public function testWarnsWhenTheConfiguredServerVersionIsWrong(): void
    {
        // MariaDB configured as MySQL 5.7: Doctrine would pick the wrong platform.
        $info = new AboutInfo($this->kernel(), $this->connection('12.3.4-MariaDB-deb12', '5.7'));
        $row = $this->rows($info->sections(), 'Database')['Configured serverVersion'];

        self::assertSame('warning', $row[2]);
        self::assertSame('5.7 (the server runs 12.3.4-MariaDB-deb12)', $row[1]);

        $row = $this->rows((new AboutInfo($this->kernel(), $this->connection('12.3.4-MariaDB', '12.3.4')))->sections(), 'Database')['Configured serverVersion'];
        self::assertSame('warning', $row[2], 'Same number, but MySQL instead of MariaDB');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function serverVersions(): iterable
    {
        yield 'PostgreSQL, major only' => ['16', '16.4 (Debian 16.4-1.pgdg120+1)', 'ok'];
        yield 'PostgreSQL, older server' => ['16', '13.23 (Debian 13.23-1.pgdg120+1)', 'warning'];
        yield 'PostgreSQL, minor configured' => ['16.4', '16.2', 'warning'];
        yield 'MySQL' => ['8.0.32', '8.0.45', 'ok'];
        yield 'MySQL, old replication prefix' => ['8.4', '5.5.5-8.4.3', 'ok'];
        yield 'MariaDB' => ['12.3.4-MariaDB', '12.3.4-MariaDB-deb12', 'ok'];
        yield 'MariaDB, other minor' => ['12.3.4-MariaDB', '12.2.1-MariaDB', 'warning'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('serverVersions')]
    public function testComparesTheConfiguredServerVersion(string $configured, string $actual, string $state): void
    {
        $info = new AboutInfo($this->kernel(), $this->connection($actual, $configured));

        self::assertSame($state, $this->rows($info->sections(), 'Database')['Configured serverVersion'][2]);
    }

    public function testAFailingConnection(): void
    {
        $info = new AboutInfo($this->kernel(), $this->connection('', null, true));

        self::assertSame(
            ['Connection' => ['Connection', 'failed: Connection refused', 'danger']],
            $this->rows($info->sections(), 'Database'),
        );
    }

    public function testLifecycleStates(): void
    {
        $info = new AboutInfo($this->kernel());
        $end = DateTimeImmutable::createFromFormat('!m/Y', Kernel::END_OF_MAINTENANCE);
        self::assertNotFalse($end);

        $state = fn (string $when): ?string => $this->rows($info->sections(null, $end->modify($when)), 'Symfony')['Maintained until'][2];

        self::assertSame('ok', $state('-1 year'));
        self::assertSame('warning', $state('-2 months'));
        self::assertSame('danger', $state('+2 months'));
    }

    public function testEnvironmentAndDebugWarnings(): void
    {
        $rows = $this->rows((new AboutInfo($this->kernel('dev', true)))->sections(), 'Symfony');

        self::assertSame(['Environment', 'dev', 'warning'], $rows['Environment']);
        self::assertSame(['Debug', 'on', 'warning'], $rows['Debug']);

        $rows = $this->rows((new AboutInfo($this->kernel()))->sections(), 'Symfony');
        self::assertSame(['Environment', 'prod', null], $rows['Environment']);
        self::assertSame(['Debug', 'off', null], $rows['Debug']);
    }

    public function testServerSection(): void
    {
        $request = Request::create('/system/about', server: ['SERVER_SOFTWARE' => 'nginx/1.26']);
        $rows = $this->rows((new AboutInfo($this->kernel()))->sections($request), 'Server');

        self::assertSame((string) gethostname(), $rows['Host'][1]);
        self::assertSame('nginx/1.26', $rows['Web server'][1]);
        self::assertArrayHasKey('Free disk space', $rows);
    }

    public function testOpcacheEnabled(): void
    {
        // opcache_get_status() can be called: its answer counts.
        self::assertTrue(AboutInfo::opcacheEnabled(['opcache_enabled' => true], true, '1', '0', 'fpm-fcgi'));
        self::assertFalse(AboutInfo::opcacheEnabled(['opcache_enabled' => false], true, '1', '0', 'fpm-fcgi'));

        // Blocked by disable_functions, as Plesk does: the settings tell.
        self::assertTrue(AboutInfo::opcacheEnabled(null, true, '1', '0', 'fpm-fcgi'));
        self::assertTrue(AboutInfo::opcacheEnabled(null, true, 'On', '', 'fpm-fcgi'));
        self::assertFalse(AboutInfo::opcacheEnabled(null, true, '0', '0', 'fpm-fcgi'));
        self::assertFalse(AboutInfo::opcacheEnabled(null, false, '1', '1', 'fpm-fcgi'), 'Extension not loaded');
        // On the command line opcache.enable_cli decides.
        self::assertFalse(AboutInfo::opcacheEnabled(null, true, '1', '0', 'cli'));
        self::assertTrue(AboutInfo::opcacheEnabled(null, true, '1', '1', 'cli'));
    }

    public function testBytes(): void
    {
        self::assertSame('512 B', AboutInfo::bytes(512));
        self::assertSame('1.5 KB', AboutInfo::bytes(1536));
        self::assertSame('20 GB', AboutInfo::bytes(20 * 1024 ** 3));
    }
}
