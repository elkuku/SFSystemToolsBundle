<?php

namespace KuKu\SFSystemToolsBundle\Controller;

use KuKu\SFSystemToolsBundle\Service\DeployLogParser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * Shows the runs in the deploy log, newest first.
 */
class LogviewController
{
    public function __construct(
        private readonly DeployLogParser $parser,
        private readonly Environment $twig,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly string $role,
        private readonly string $baseTemplate,
        private readonly string $logFile,
    ) {
    }

    public function __invoke(): Response
    {
        if (!$this->authorizationChecker->isGranted($this->role)) {
            throw new AccessDeniedException();
        }

        $runs = [];
        $error = null;

        if (!is_file($this->logFile)) {
            $error = 'No deploy log found at '.$this->logFile;
        } else {
            $contents = @file_get_contents($this->logFile);
            if (false === $contents) {
                $error = 'The deploy log could not be read: '.$this->logFile;
            } else {
                $runs = array_reverse($this->parser->parse($contents));
            }
        }

        return new Response($this->twig->render('@KuKuSFSystemTools/logview.html.twig', [
            'base_template' => $this->baseTemplate,
            'log_file' => $this->logFile,
            'runs' => $runs,
            'error' => $error,
        ]));
    }
}
