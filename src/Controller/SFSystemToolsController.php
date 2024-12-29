<?php

namespace KuKu\SFSystemToolsBundle\Controller;

use KuKu\SFSystemToolsBundle\Service\SystemInfo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class SFSystemToolsController extends AbstractController
{
    public function __construct(private readonly SystemInfo $systemInfo)
    {
    }

    public function index(): JsonResponse
    {
        return $this->json($this->systemInfo->getInfo());
    }
}
