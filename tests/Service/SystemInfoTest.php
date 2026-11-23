<?php

namespace KuKu\SFSystemToolsBundle\Tests\Service;

use KuKu\SFSystemToolsBundle\Service\DeployLogParser;
use KuKu\SFSystemToolsBundle\Service\SystemInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

class SystemInfoTest extends TestCase
{
    private string $deployLog;

    protected function setUp(): void
    {
        $this->deployLog = sys_get_temp_dir().'/kuku_sf_system_tools_test/info-deploy.log';
        (new Filesystem())->remove($this->deployLog);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->deployLog);
    }

    /**
     * A stand-in for a Doctrine DBAL connection; the platform is an object of the given class.
     */
    /**
     * @param class-string $platformClass
     */
    private function connection(string $platformClass, string $version, bool $fails = false): object
    {
        $platform = new $platformClass();

        return new class($platform, $version, $fails) {
            public function __construct(private object $platform, private string $version, private bool $fails)
            {
            }

            public function getDatabasePlatform(): object
            {
                if ($this->fails) {
                    throw new RuntimeException('Connection refused');
                }

                return $this->platform;
            }

            public function getServerVersion(): string
            {
                return $this->version;
            }
        };
    }

    public function testOnlyTheVersionsByDefault(): void
    {
        self::assertSame(['sf_version' => 'N/A', 'php_version' => 'N/A'], (new SystemInfo())->getInfo());
        self::assertSame(
            ['sf_version' => Kernel::VERSION, 'php_version' => \PHP_VERSION],
            (new SystemInfo(true, true))->getInfo(),
        );
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function platforms(): iterable
    {
        yield 'MariaDB' => [FakeMariaDB120300Platform::class, 'mariadb'];
        yield 'MySQL' => [FakeMySQL84Platform::class, 'mysql'];
        yield 'PostgreSQL' => [FakePostgreSQL120Platform::class, 'postgresql'];
        yield 'SQLite' => [FakeSQLitePlatform::class, 'sqlite'];
    }

    #[DataProvider('platforms')]
    public function testTheDatabase(string $platformClass, string $name): void
    {
        $info = (new SystemInfo(exposeDbVersion: true, connection: $this->connection($platformClass, '12.3.4-MariaDB-deb12')))->getInfo();

        self::assertSame($name, $info['db_platform'] ?? null);
        self::assertSame('12.3.4-MariaDB-deb12', $info['db_version'] ?? null);
    }

    public function testTheDatabaseWithoutOrWithAFailingConnection(): void
    {
        // The keys are there, but unknown.
        foreach ([null, $this->connection(FakeMariaDB120300Platform::class, '', true)] as $connection) {
            $info = (new SystemInfo(exposeDbVersion: true, connection: $connection))->getInfo();
            self::assertArrayHasKey('db_platform', $info);
            self::assertArrayHasKey('db_version', $info);
            self::assertNull($info['db_platform']);
            self::assertNull($info['db_version']);
        }
    }

    public function testTheDatabaseIsLeftOutWhenNotExposed(): void
    {
        $info = (new SystemInfo(connection: $this->connection(FakeMariaDB120300Platform::class, '12.3.4')))->getInfo();

        self::assertArrayNotHasKey('db_platform', $info);
        self::assertArrayNotHasKey('db_version', $info);
        self::assertArrayNotHasKey('last_deploy', $info);
    }

    public function testTheLastDeploy(): void
    {
        (new Filesystem())->dumpFile($this->deployLog, implode("\n", [
            '>>>===', 'Wed 18 Nov 2026 08:11:59 PM UTC', 'OK', '<<<===',
            '>>>===', 'Mon 23 Nov 2026 03:04:17 PM UTC', 'Script cache:clear returned with error code 1', '<<<===',
        ]));

        $info = (new SystemInfo(exposeLastDeploy: true, deployLogParser: new DeployLogParser(), deployLog: $this->deployLog))->getInfo();

        self::assertSame(['at' => '2026-11-23T15:04:17+00:00', 'status' => 'errors'], $info['last_deploy'] ?? null);
    }

    public function testTheLastDeployWithoutALog(): void
    {
        $info = (new SystemInfo(exposeLastDeploy: true, deployLogParser: new DeployLogParser(), deployLog: $this->deployLog))->getInfo();
        self::assertArrayHasKey('last_deploy', $info);
        self::assertNull($info['last_deploy']);

        (new Filesystem())->dumpFile($this->deployLog, '');
        $info = (new SystemInfo(exposeLastDeploy: true, deployLogParser: new DeployLogParser(), deployLog: $this->deployLog))->getInfo();
        self::assertNull($info['last_deploy']);

        (new Filesystem())->dumpFile($this->deployLog, ">>>===\nMon 1 Jan\nstill running\n");
        $info = (new SystemInfo(exposeLastDeploy: true, deployLogParser: new DeployLogParser(), deployLog: $this->deployLog))->getInfo();
        self::assertSame(['at' => null, 'status' => 'unfinished'], $info['last_deploy']);
    }
}

// Stand-ins for the Doctrine platforms: only their class names matter.
final class FakeMariaDB120300Platform
{
}

final class FakeMySQL84Platform
{
}

final class FakePostgreSQL120Platform
{
}

final class FakeSQLitePlatform
{
}
