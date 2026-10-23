<?php

declare(strict_types=1);

namespace PhpRuntimeAudit\Presentation;

use PhpRuntimeAudit\Application\ReportFormatter;
use PhpRuntimeAudit\Application\ScanProject;
use PhpRuntimeAudit\Domain\AssessmentStatus;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'scan', description: 'Audit PHP image references in local manifests')]
final class ScanCommand extends Command
{
    /**
     * @param array<string, ReportFormatter> $formatters
     */
    public function __construct(
        private readonly ScanProject $scanner,
        private readonly array $formatters,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Repository directory to scan', '.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatOption = $input->getOption('format');

        if (! is_string($formatOption)) {
            $output->writeln('<error>Output format must be text or json.</error>');

            return Command::INVALID;
        }

        $format = $formatOption;
        $formatter = $this->formatters[$format] ?? null;

        if ($formatter === null) {
            $output->writeln('<error>Unsupported format. Choose text or json.</error>');

            return Command::INVALID;
        }

        try {
            $path = $input->getArgument('path');

            if (! is_string($path)) {
                $output->writeln('<error>Scan path must be a directory path.</error>');

                return Command::INVALID;
            }

            $assessments = $this->scanner->scan($path);
        } catch (RuntimeException $runtimeException) {
            $message = $this->sanitizeTerminalControls($runtimeException->getMessage());
            $message = OutputFormatter::escape($message);

            $output->writeln(sprintf('<error>%s</error>', $message));

            return Command::INVALID;
        }

        $output->writeln($formatter->format($assessments), OutputInterface::OUTPUT_RAW);

        foreach ($assessments as $assessment) {
            if ($assessment->status === AssessmentStatus::PotentiallyVulnerable) {
                return 1;
            }
        }

        foreach ($assessments as $assessment) {
            if ($assessment->status === AssessmentStatus::Unknown) {
                return 3;
            }
        }

        return Command::SUCCESS;
    }

    private function sanitizeTerminalControls(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]|\xC2[\x80-\x9F]/', '?', $value) ?? '';
    }
}
