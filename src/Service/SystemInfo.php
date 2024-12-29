<?php

namespace KuKu\SFSystemToolsBundle\Service;

use Symfony\Component\HttpKernel\Kernel;

readonly class SystemInfo
{
    public function __construct(
        private bool $exposeSFVersion = false,
        private bool $exposePHPVersion = false,
    )
    {
    }

    public function getInfo(): array
    {
        return [
            'sf_version' => $this->exposeSFVersion ? Kernel::VERSION : 'N/A',
            'php_version' => $this->exposePHPVersion ? PHP_VERSION : 'N/A',
        ];
    }
}
