<?php

namespace KuKu\SFSystemToolsBundle\Controller;

use KuKu\SFSystemToolsBundle\Service\SystemInfo;
use Symfony\Component\HttpFoundation\JsonResponse;

class SFSystemToolsController
{
    public function __construct(private readonly SystemInfo $systemInfo) {}

    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->systemInfo->getInfo());
    }
}
