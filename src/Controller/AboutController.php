<?php

namespace KuKu\SFSystemToolsBundle\Controller;

use KuKu\SFSystemToolsBundle\Service\AboutInfo;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * Shows facts about Symfony, PHP, the database and the server, and the output of the
 * "about" console command.
 */
class AboutController
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Environment $twig,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly string $role,
        private readonly string $baseTemplate,
        private readonly ?AboutInfo $aboutInfo = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->authorizationChecker->isGranted($this->role)) {
            throw new AccessDeniedException();
        }

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $application->run(new ArrayInput(['command' => 'about']), $output);

        return new Response($this->twig->render('@KuKuSFSystemTools/about.html.twig', [
            'base_template' => $this->baseTemplate,
            'project_dir' => $this->kernel->getProjectDir(),
            'sections' => ($this->aboutInfo ?? new AboutInfo($this->kernel))->sections($request),
            'about' => $output->fetch(),
        ]));
    }
}
