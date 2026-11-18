<?php

namespace KuKu\SFSystemToolsBundle\Service;

/**
 * One run of bin/deploy in the deploy log.
 */
final readonly class DeployRun
{
    public function __construct(
        /** The first line of the run (the date), or null for output outside a run. */
        public ?string $startedAt,
        public string $output,
        /** False if the run has no closing line: it failed, was aborted or is still running. */
        public bool $finished,
    ) {
    }
}
