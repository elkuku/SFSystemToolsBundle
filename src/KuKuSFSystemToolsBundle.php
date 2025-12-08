<?php

namespace KuKu\SFSystemToolsBundle;

use KuKu\SFSystemToolsBundle\DependencyInjection\KuKuSFSystemToolsExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class KuKuSFSystemToolsBundle extends AbstractBundle
{
    public string $extensionAlias = 'kuku_sf_system_tools';

    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ?? new KuKuSFSystemToolsExtension();
    }
}
