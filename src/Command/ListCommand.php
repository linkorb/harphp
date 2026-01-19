<?php

declare(strict_types=1);

namespace HarPhp\Command;

use HarPhp\Service\FilterConfig;
use HarPhp\Service\HarFilter;
use HarPhp\Service\HarParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'requests',
    description: 'List requests in a HAR file with their indices'
)]
class ListCommand extends Command
{
    public function __construct(
        private readonly string $baseDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to HAR file')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config YAML file (default: harphp.yaml)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $configPath = $input->getOption('config');

        $filterConfig = $this->resolveFilterConfig($configPath);

        $parser = new HarParser();
        $filter = new HarFilter($parser);

        try {
            $harData = $parser->parse($this->resolvePath($filePath));

            // Apply filter if we have one
            if ($filterConfig !== null) {
                $harData = $filter->filter($harData, $filterConfig);
            }

            $entries = $parser->getEntries($harData);

            if (empty($entries)) {
                $output->writeln('<comment>No entries found in HAR file</comment>');
                return Command::SUCCESS;
            }

            // Calculate column widths
            $maxIndexWidth = strlen((string) (count($entries) - 1));

            foreach ($entries as $index => $entry) {
                $method = $parser->getEntryMethod($entry);
                $url = $parser->getEntryUrl($entry);
                $status = $parser->getEntryStatus($entry);
                $time = $parser->getEntryTime($entry);

                // Format status with color
                $statusFormatted = $this->formatStatus($status);

                // Truncate URL if too long
                $maxUrlLength = 100;
                $displayUrl = strlen($url) > $maxUrlLength
                    ? substr($url, 0, $maxUrlLength - 3) . '...'
                    : $url;

                $output->writeln(sprintf(
                    '<fg=cyan>%' . $maxIndexWidth . 'd</> │ <fg=yellow>%-6s</> │ %s │ %8.2fms │ %s',
                    $index,
                    $method,
                    $statusFormatted,
                    $time,
                    $displayUrl
                ));
            }

            $output->writeln('');
            $output->writeln(sprintf('<info>Total: %d entries</info>', count($entries)));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function formatStatus(int $status): string
    {
        if ($status >= 200 && $status < 300) {
            return sprintf('<fg=green>%3d</>', $status);
        }
        if ($status >= 300 && $status < 400) {
            return sprintf('<fg=blue>%3d</>', $status);
        }
        if ($status >= 400 && $status < 500) {
            return sprintf('<fg=yellow>%3d</>', $status);
        }
        if ($status >= 500) {
            return sprintf('<fg=red>%3d</>', $status);
        }
        return sprintf('%3d', $status);
    }

    private function resolveFilterConfig(?string $configPath): ?FilterConfig
    {
        if ($configPath !== null) {
            return FilterConfig::fromFile($this->resolvePath($configPath));
        }

        // Try harphp.yaml in current directory
        $defaultConfig = getcwd() . '/harphp.yaml';
        if (file_exists($defaultConfig)) {
            return FilterConfig::fromFile($defaultConfig);
        }

        // Try harphp.yaml in base directory
        $baseConfig = $this->baseDir . '/harphp.yaml';
        if (file_exists($baseConfig)) {
            return FilterConfig::fromFile($baseConfig);
        }

        // Fall back to HARPHP_FILTER env var
        $envFilter = getenv('HARPHP_FILTER');
        if ($envFilter !== false && $envFilter !== '') {
            return FilterConfig::fromFile($envFilter);
        }

        return null;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        $cwdPath = getcwd() . '/' . $path;
        if (file_exists($cwdPath)) {
            return $cwdPath;
        }

        $basePath = $this->baseDir . '/' . $path;
        if (file_exists($basePath)) {
            return $basePath;
        }

        return $cwdPath;
    }
}
