<?php

namespace KuKu\SFSystemToolsBundle\Service;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

/**
 * Collects the facts shown on the About page, grouped in sections.
 *
 * A row is [label, value, state], where state is null or one of "ok", "warning", "danger"
 * and gives the value a colored badge.
 */
class AboutInfo
{
    public function __construct(
        private readonly KernelInterface $kernel,
        /** The Doctrine DBAL connection, or null if the project has none. */
        private readonly ?object $connection = null,
    ) {
    }

    /**
     * @return list<array{title: string, rows: list<array{0: string, 1: string, 2: ?string}>}>
     */
    public function sections(?Request $request = null, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();

        $sections = [
            ['title' => 'Symfony', 'rows' => $this->symfony($now)],
            ['title' => 'PHP', 'rows' => $this->php()],
        ];

        if (null !== $this->connection) {
            $sections[] = ['title' => 'Database', 'rows' => $this->database()];
        }

        $sections[] = ['title' => 'Server', 'rows' => $this->server($request)];

        return $sections;
    }

    /**
     * @return list<array{0: string, 1: string, 2: ?string}>
     */
    private function symfony(DateTimeImmutable $now): array
    {
        return [
            ['Version', Kernel::VERSION, null],
            ['Maintained until', Kernel::END_OF_MAINTENANCE, $this->lifecycleState(Kernel::END_OF_MAINTENANCE, $now)],
            ['End of life', Kernel::END_OF_LIFE, $this->lifecycleState(Kernel::END_OF_LIFE, $now)],
            ['Environment', $this->kernel->getEnvironment(), 'prod' === $this->kernel->getEnvironment() ? null : 'warning'],
            ['Debug', $this->kernel->isDebug() ? 'on' : 'off', $this->kernel->isDebug() ? 'warning' : null],
            ['Project directory', $this->kernel->getProjectDir(), null],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: ?string}>
     */
    private function php(): array
    {
        $opcache = \function_exists('opcache_get_status') ? @opcache_get_status(false) : false;

        return [
            ['Version', \PHP_VERSION, null],
            ['SAPI', \PHP_SAPI, null],
            ['Memory limit', (string) \ini_get('memory_limit'), null],
            ['Max execution time', \ini_get('max_execution_time').' s', null],
            ['Upload max size', (string) \ini_get('upload_max_filesize'), null],
            ['Time zone', date_default_timezone_get(), null],
            ['OPcache', \is_array($opcache) && ($opcache['opcache_enabled'] ?? false) ? 'enabled' : 'disabled', \is_array($opcache) && ($opcache['opcache_enabled'] ?? false) ? 'ok' : null],
            ['intl', \extension_loaded('intl') ? 'ICU '.(\defined('INTL_ICU_VERSION') ? \INTL_ICU_VERSION : '?') : 'not loaded', null],
            ['Xdebug', \extension_loaded('xdebug') ? 'loaded' : 'not loaded', \extension_loaded('xdebug') ? 'warning' : null],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: ?string}>
     */
    private function database(): array
    {
        $connection = $this->connection;
        /** @var array<string, mixed> $params */
        $params = method_exists($connection, 'getParams') ? $connection->getParams() : [];

        $rows = [];

        try {
            $platform = $connection->getDatabasePlatform();
            $rows[] = ['Platform', (new \ReflectionClass($platform))->getShortName(), null];
        } catch (Throwable $e) {
            return [['Connection', 'failed: '.$e->getMessage(), 'danger']];
        }

        $actual = null;
        try {
            $actual = (string) $connection->getServerVersion();
            $rows[] = ['Server version', $actual, null];
        } catch (Throwable $e) {
            $rows[] = ['Server version', 'unknown: '.$e->getMessage(), 'danger'];
        }

        $configured = $params['serverVersion'] ?? $params['primary']['serverVersion'] ?? null;
        if (\is_string($configured) && '' !== $configured) {
            $matches = null === $actual || $this->sameVersion($configured, $actual);
            $rows[] = [
                'Configured serverVersion',
                $configured.($matches ? '' : ' (the server runs '.$actual.')'),
                $matches ? 'ok' : 'warning',
            ];
        }

        $rows[] = ['Database', (string) ($connection->getDatabase() ?? '?'), null];
        $rows[] = ['Driver', (string) ($params['driver'] ?? $params['driverClass'] ?? '?'), null];
        if (isset($params['charset']) && \is_string($params['charset'])) {
            $rows[] = ['Charset', $params['charset'], null];
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: string, 2: ?string}>
     */
    private function server(?Request $request): array
    {
        $dir = $this->kernel->getProjectDir();
        $free = @disk_free_space($dir);
        $total = @disk_total_space($dir);
        $load = \function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        $rows = [
            ['Host', (string) gethostname(), null],
            ['OS', php_uname('s').' '.php_uname('r'), null],
        ];

        if (null !== $request && \is_string($software = $request->server->get('SERVER_SOFTWARE')) && '' !== $software) {
            $rows[] = ['Web server', $software, null];
        }

        if (false !== $free && false !== $total && $total > 0) {
            $share = $free / $total;
            $rows[] = [
                'Free disk space',
                self::bytes($free).' of '.self::bytes($total).' ('.round($share * 100).' %)',
                $share < 0.1 ? 'danger' : ($share < 0.2 ? 'warning' : 'ok'),
            ];
        }

        if (\is_array($load)) {
            $rows[] = ['Load (1 / 5 / 15 min)', implode(' / ', array_map(static fn (float $l): string => number_format($l, 2), $load)), null];
        }

        return $rows;
    }

    /**
     * "ok" before the month of the date (m/Y), "warning" in its last 6 months, "danger" after it.
     */
    private function lifecycleState(string $monthYear, DateTimeImmutable $now): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!m/Y', $monthYear);
        if (false === $date) {
            return null;
        }
        $end = $date->modify('last day of this month 23:59:59');

        if ($now > $end) {
            return 'danger';
        }

        return $now > $end->modify('-6 months') ? 'warning' : 'ok';
    }

    /**
     * Compares the major and minor version and the flavor (MariaDB or not), e.g.
     * "12.3.4-MariaDB" matches "12.3.4-MariaDB-deb12", but "5.7" doesn't.
     */
    private function sameVersion(string $configured, string $actual): bool
    {
        $short = static fn (string $v): string => preg_match('/^(\d+)\.(\d+)/', $v, $m) ? $m[1].'.'.$m[2] : $v;
        $mariaDb = static fn (string $v): bool => false !== stripos($v, 'mariadb');

        return $short($configured) === $short($actual) && $mariaDb($configured) === $mariaDb($actual);
    }

    public static function bytes(float $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024 || 'TB' === $unit) {
                return round($bytes, 'B' === $unit ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return $bytes.' B';
    }
}
