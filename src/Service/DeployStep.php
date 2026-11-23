<?php

namespace KuKu\SFSystemToolsBundle\Service;

/**
 * One step of a deploy run: a "**** ..." line of bin/deploy and the output below it.
 */
final readonly class DeployStep
{
    public function __construct(
        /** The step name without the stars and dots, or null for output before the first step. */
        public ?string $title,
        /** @var list<string> */
        public array $lines,
        /** True if this is the step a run stopped in. */
        public bool $failed = false,
    ) {
    }

    /**
     * True if one of the lines is an error.
     */
    public function hasErrors(): bool
    {
        foreach ($this->lines as $line) {
            if (DeployRun::isErrorLine($line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The lines with a Bootstrap text class each, for highlighting.
     *
     * @return list<array{text: string, class: string}>
     */
    public function styledLines(): array
    {
        return array_map(
            static fn (string $line): array => ['text' => $line, 'class' => DeployRun::lineClass($line)],
            $this->lines,
        );
    }
}
