<?php

namespace KuKu\tests;

use KuKu\SFSystemToolsBundle\DependencyInjection\KuKuSFSystemToolsExtension;
use KuKu\SFSystemToolsBundle\KuKuSFSystemToolsBundle;
use PHPUnit\Framework\TestCase;

class KuKuSFSystemToolsTest extends TestCase
{
    public function testGetContainerExtension(): void
    {
        $bundle = new KuKuSFSystemToolsBundle();
        $this->assertInstanceOf(KuKuSFSystemToolsExtension::class, $bundle->getContainerExtension());
    }
}


