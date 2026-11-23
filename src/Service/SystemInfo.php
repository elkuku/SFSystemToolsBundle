<?php

namespace KuKu\SFSystemToolsBundle\Service;

use DateTimeInterface;
use Symfony\Component\HttpKernel\Kernel;
use Throwable;

/**
 * The facts for the public /system/info/ endpoint, e.g. for the KuKu project checker.
 *
 * Everything is opt-in, since anyone can read the endpoint. The Symfony and PHP versions
 * are always present ("N/A" when not exposed); the database and last deploy keys are only
 * added when exposed.
 */
readonly class SystemInfo
{
    public function __construct(
        private bool $exposeSFVersion = false,
        private bool $exposePHPVersion = false,
        private bool $exposeDbVersion = false,
        private bool $exposeLastDeploy = false,
        /** The Doctrine DBAL connection, or null if the project has none. */
        private ?object $connection = null,
        private ?DeployLogParser $deployLogParser = null,
        private ?string $deployLog = null,
    ) {
    }

    /**
     * @return array{
     *     sf_version: string,
     *     php_version: string,
     *     db_platform?: ?string,
     *     db_version?: ?string,
     *     last_deploy?: ?array{at: ?string, status: string},
     * }
     */
    public function getInfo(): array
    {
        $info = [
            'sf_version' => $this->exposeSFVersion ? Kernel::VERSION : 'N/A',
            'php_version' => $this->exposePHPVersion ? \PHP_VERSION : 'N/A',
        ];

        if ($this->exposeDbVersion) {
            [$info['db_platform'], $info['db_version']] = $this->database();
        }

        if ($this->exposeLastDeploy) {
            $info['last_deploy'] = $this->lastDeploy();
        }

        return $info;
    }

    /**
     * The platform as a plain name ("mariadb", "mysql", "postgresql", "sqlite", ...) and the
     * version the server reports; nulls if there is no connection or it fails.
     *
     * @return array{?string, ?string}
     */
    private function database(): array
    {
        if (null === $this->connection) {
            return [null, null];
        }

        try {
            $class = strtolower((new \ReflectionClass($this->connection->getDatabasePlatform()))->getShortName());
            $platform = match (true) {
                str_contains($class, 'mariadb') => 'mariadb',
                str_contains($class, 'mysql') => 'mysql',
                str_contains($class, 'postgre') => 'postgresql',
                str_contains($class, 'sqlite') => 'sqlite',
                str_contains($class, 'sqlserver') => 'sqlserver',
                str_contains($class, 'oracle') => 'oracle',
                default => preg_replace('/\d*platform$/', '', $class),
            };

            return [$platform, (string) $this->connection->getServerVersion()];
        } catch (Throwable) {
            return [null, null];
        }
    }

    /**
     * The newest run of the deploy log: when it started (ISO 8601, or null if its date line
     * can't be read) and its status ("ok", "errors" or "unfinished"); null without a log.
     *
     * @return ?array{at: ?string, status: string}
     */
    private function lastDeploy(): ?array
    {
        if (null === $this->deployLogParser || null === $this->deployLog || !is_file($this->deployLog)) {
            return null;
        }

        $contents = @file_get_contents($this->deployLog);
        $runs = false === $contents ? [] : $this->deployLogParser->parse($contents);
        $run = [] === $runs ? null : $runs[\count($runs) - 1];

        if (null === $run) {
            return null;
        }

        return [
            'at' => $run->startedAtDate()?->format(DateTimeInterface::ATOM),
            'status' => $run->status(),
        ];
    }
}
