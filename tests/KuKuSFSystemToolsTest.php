<?php

namespace tests;

use KuKu\SFSystemToolsBundle\KuKuSFSystemToolsBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\BundleExtension;

class KuKuSFSystemToolsTest extends TestCase
{
    public function testGetContainerExtension(): void
    {
        $bundle = new KuKuSFSystemToolsBundle();
        $this->assertInstanceOf(BundleExtension::class, $bundle->getContainerExtension());
    }
}


