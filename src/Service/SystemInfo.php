<?php

namespace KuKu\SFSystemToolsBundle\Service;
class SystemInfo
{
    public function __construct(
        private bool $exposeSFVersion = false,
        private bool $exposePHPVersion = false
    )
    {
    }

    public function getInfo()
    {
        return $this->exposeSFVersion ? 'x' : 'y';
    }
}
