<?php

namespace KuKu\SFSystemToolsBundle;

use KuKu\SFSystemToolsBundle\DependencyInjection\KuKuSFSystemToolsExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Bundle\BundleExtension;

class KuKuSFSystemToolsBundle extends AbstractBundle
{
    public string $extensionAlias = 'kuku_sf_system_tools';

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            return new KuKuSFSystemToolsExtension();
        }

        return $this->extension;
    }
}
