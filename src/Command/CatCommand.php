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
    name: 'cat',
    description: 'Output HAR file contents, optionally filtered'
)]
class CatCommand extends Command
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
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config YAML file (default: harphp.yaml)')
            ->addOption('compact', null, InputOption::VALUE_NONE, 'Output compact JSON (default: pretty-printed)')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: stdout)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $configPath = $input->getOption('config');
        $compact = $input->getOption('compact');
        $outputPath = $input->getOption('output');

        // Resolve config path
        $filterConfig = $this->resolveFilterConfig($configPath);

        $parser = new HarParser();
        $filter = new HarFilter($parser);

        try {
            $harData = $parser->parse($this->resolvePath($filePath));
            $originalCount = $filter->countEntries($harData);

            // Apply filter if we have one
            if ($filterConfig !== null) {
                $harData = $filter->filter($harData, $filterConfig);
                $filteredCount = $filter->countEntries($harData);

                if ($output->isVerbose()) {
                    $output->writeln(sprintf(
                        '<info>Filtered: %d -> %d entries (removed %d)</info>',
                        $originalCount,
                        $filteredCount,
                        $originalCount - $filteredCount
                    ), OutputInterface::VERBOSITY_VERBOSE);
                }
            }

            $json = $parser->toJson($harData, !$compact);

            if ($outputPath) {
                file_put_contents($outputPath, $json);
                $output->writeln(sprintf('<info>Written to %s</info>', $outputPath));
            } else {
                $output->write($json);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function resolveFilterConfig(?string $configPath): ?FilterConfig
    {
        // Use explicit config path if provided
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
        // Absolute path
        if (str_starts_with($path, '/')) {
            return $path;
        }

        // Try relative to cwd first
        $cwdPath = getcwd() . '/' . $path;
        if (file_exists($cwdPath)) {
            return $cwdPath;
        }

        // Try relative to base dir
        $basePath = $this->baseDir . '/' . $path;
        if (file_exists($basePath)) {
            return $basePath;
        }

        // Return cwd-relative path (will fail with proper error)
        return $cwdPath;
    }
}
