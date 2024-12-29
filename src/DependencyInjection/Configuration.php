<?php

namespace KuKu\SFSystemToolsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('kuku_sf_system_tools');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->booleanNode('expose_sf_version')->defaultFalse()->end()
            ->booleanNode('expose_php_version')->defaultFalse()->end()
            ->end();

        return $treeBuilder;
    }
}
