<?php

namespace KuKu\SFSystemToolsBundle\Controller;

use DateTimeImmutable;
use KuKu\SFSystemToolsBundle\Service\DeployCalendar;
use KuKu\SFSystemToolsBundle\Service\DeployLogParser;
use KuKu\SFSystemToolsBundle\Service\DeployRun;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * Shows one run of the deploy log, the newest by default, next to a calendar of all runs.
 *
 * Query parameters: "run" (the run's position in the log, oldest first) and "month" (Y-m).
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

    public function __invoke(Request $request): Response
    {
        if (!$this->authorizationChecker->isGranted($this->role)) {
            throw new AccessDeniedException();
        }

        /** @var list<DeployRun> $runs oldest first */
        $runs = [];
        $error = null;

        if (!is_file($this->logFile)) {
            $error = 'No deploy log found at '.$this->logFile;
        } else {
            $contents = @file_get_contents($this->logFile);
            if (false === $contents) {
                $error = 'The deploy log could not be read: '.$this->logFile;
            } else {
                $runs = $this->parser->parse($contents);
            }
        }

        $now = new DateTimeImmutable();
        $latest = [] === $runs ? null : \count($runs) - 1;

        // The requested run, or the newest one.
        $position = filter_var($request->query->get('run'), \FILTER_VALIDATE_INT);
        $selected = false !== $position && isset($runs[$position]) ? $position : $latest;
        $run = null === $selected ? null : $runs[$selected];

        // The requested month, or the one of the selected run, or the current one.
        $monthParam = (string) $request->query->get('month', '');
        $month = 1 === preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)
            ? new DateTimeImmutable($monthParam.'-01')
            : ($run?->startedAtDate() ?? $now);

        // Other runs on the same day as the selected one, for switching between them.
        $sameDay = [];
        $day = $run?->startedAtDate()?->format('Y-m-d');
        if (null !== $day) {
            foreach ($runs as $i => $other) {
                if ($other->startedAtDate()?->format('Y-m-d') === $day) {
                    $sameDay[] = ['position' => $i, 'run' => $other];
                }
            }
        }

        return new Response($this->twig->render('@KuKuSFSystemTools/logview.html.twig', [
            'base_template' => $this->baseTemplate,
            'log_file' => $this->logFile,
            'error' => $error,
            'run_count' => \count($runs),
            'failed_count' => \count(array_filter($runs, static fn (DeployRun $r): bool => DeployRun::STATUS_OK !== $r->status())),
            'latest_ago' => null === $latest ? null : $runs[$latest]->startedAgo($now),
            'calendar' => (new DeployCalendar())->build($runs, $month, $selected),
            'undated' => array_filter($runs, static fn (DeployRun $r): bool => null === $r->startedAtDate()),
            'selected' => $selected,
            'is_latest' => null !== $selected && $selected === $latest,
            'run' => $run,
            'run_date' => $run?->startedAtDate(),
            'run_ago' => $run?->startedAgo($now),
            'same_day' => \count($sameDay) > 1 ? $sameDay : [],
        ]));
    }
}
