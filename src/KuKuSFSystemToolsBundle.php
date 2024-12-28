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
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder):void
    {
        var_dump($config);die('jjx');
        $loader = new XmlFileLoader($builder, new FileLocator(__DIR__.'/Resources/config'));
        $loader->load('services.xml');

        //$configuration = $this->configure();
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        #dd(new BundleExtension($this, $this->extensionAlias));
        #$extensionAlias = Container::underscore(preg_replace('/Bundle$/', '', $this->getName()));
        #dd($extensionAlias);
        if (null === $this->extension) {
            return new KuKuSFSystemToolsExtension();
        }

        return $this->extension;
    }
}
