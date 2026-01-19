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
    name: 'view',
    description: 'View full details of a specific request by index'
)]
class ViewCommand extends Command
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
            ->addArgument('index', InputArgument::REQUIRED, 'Request index to view')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config YAML file (default: harphp.yaml)')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output raw JSON')
            ->addOption('request-body', null, InputOption::VALUE_NONE, 'Show request body')
            ->addOption('response-body', null, InputOption::VALUE_NONE, 'Show response body');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $index = (int) $input->getArgument('index');
        $configPath = $input->getOption('config');
        $jsonOutput = $input->getOption('json');
        $showRequestBody = $input->getOption('request-body');
        $showResponseBody = $input->getOption('response-body');

        $filterConfig = $this->resolveFilterConfig($configPath);

        $parser = new HarParser();
        $filter = new HarFilter($parser);

        try {
            $harData = $parser->parse($this->resolvePath($filePath));

            // Apply filter if we have one
            if ($filterConfig !== null) {
                $harData = $filter->filter($harData, $filterConfig);
            }

            $entry = $parser->getEntry($harData, $index);

            if ($entry === null) {
                $totalEntries = count($parser->getEntries($harData));
                $output->writeln(sprintf(
                    '<error>Entry index %d not found. Valid range: 0-%d</error>',
                    $index,
                    $totalEntries - 1
                ));
                return Command::FAILURE;
            }

            if ($jsonOutput) {
                $output->writeln(json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            $this->displayEntry($output, $parser, $entry, $showRequestBody, $showResponseBody);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function displayEntry(
        OutputInterface $output,
        HarParser $parser,
        array $entry,
        bool $showRequestBody,
        bool $showResponseBody
    ): void {
        $request = $entry['request'] ?? [];
        $response = $entry['response'] ?? [];
        $timings = $entry['timings'] ?? [];

        // Request section
        $output->writeln('<fg=cyan;options=bold>═══════════════════════════════════════════════════════════════</>');
        $output->writeln('<fg=cyan;options=bold>REQUEST</>');
        $output->writeln('<fg=cyan;options=bold>═══════════════════════════════════════════════════════════════</>');

        $method = $request['method'] ?? 'GET';
        $url = $request['url'] ?? '';
        $output->writeln(sprintf('<fg=yellow>%s</> %s', $method, $url));
        $output->writeln('');

        // Request headers
        if (!empty($request['headers'])) {
            $output->writeln('<fg=white;options=bold>Headers:</>');
            foreach ($request['headers'] as $header) {
                $output->writeln(sprintf('  <fg=gray>%s:</> %s', $header['name'], $header['value']));
            }
            $output->writeln('');
        }

        // Query string
        if (!empty($request['queryString'])) {
            $output->writeln('<fg=white;options=bold>Query Parameters:</>');
            foreach ($request['queryString'] as $param) {
                $output->writeln(sprintf('  <fg=gray>%s:</> %s', $param['name'], $param['value']));
            }
            $output->writeln('');
        }

        // Cookies
        if (!empty($request['cookies'])) {
            $output->writeln('<fg=white;options=bold>Cookies:</>');
            foreach ($request['cookies'] as $cookie) {
                $output->writeln(sprintf('  <fg=gray>%s:</> %s', $cookie['name'], $cookie['value']));
            }
            $output->writeln('');
        }

        // Request body
        if ($showRequestBody && !empty($request['postData'])) {
            $output->writeln('<fg=white;options=bold>Request Body:</>');
            $mimeType = $request['postData']['mimeType'] ?? 'unknown';
            $output->writeln(sprintf('  <fg=gray>Content-Type:</> %s', $mimeType));
            if (isset($request['postData']['text'])) {
                $output->writeln('');
                $output->writeln($this->formatBody($request['postData']['text'], $mimeType));
            }
            $output->writeln('');
        }

        // Response section
        $output->writeln('<fg=green;options=bold>═══════════════════════════════════════════════════════════════</>');
        $output->writeln('<fg=green;options=bold>RESPONSE</>');
        $output->writeln('<fg=green;options=bold>═══════════════════════════════════════════════════════════════</>');

        $status = $response['status'] ?? 0;
        $statusText = $response['statusText'] ?? '';
        $statusColor = $this->getStatusColor($status);
        $output->writeln(sprintf('<fg=%s>%d %s</>', $statusColor, $status, $statusText));
        $output->writeln('');

        // Response headers
        if (!empty($response['headers'])) {
            $output->writeln('<fg=white;options=bold>Headers:</>');
            foreach ($response['headers'] as $header) {
                $output->writeln(sprintf('  <fg=gray>%s:</> %s', $header['name'], $header['value']));
            }
            $output->writeln('');
        }

        // Response content info
        $content = $response['content'] ?? [];
        if (!empty($content)) {
            $output->writeln('<fg=white;options=bold>Content:</>');
            $output->writeln(sprintf('  <fg=gray>Size:</> %d bytes', $content['size'] ?? 0));
            $output->writeln(sprintf('  <fg=gray>MIME Type:</> %s', $content['mimeType'] ?? 'unknown'));
            if (isset($content['compression'])) {
                $output->writeln(sprintf('  <fg=gray>Compression:</> %d bytes saved', $content['compression']));
            }
            $output->writeln('');
        }

        // Response body
        if ($showResponseBody && isset($content['text'])) {
            $output->writeln('<fg=white;options=bold>Response Body:</>');
            $mimeType = $content['mimeType'] ?? 'text/plain';
            $text = $content['text'];

            // Handle base64 encoding
            if (($content['encoding'] ?? '') === 'base64') {
                $output->writeln('<comment>(Base64 encoded content)</comment>');
                $output->writeln('');
                // Try to decode if it's text
                if (str_starts_with($mimeType, 'text/') || str_contains($mimeType, 'json')) {
                    $decoded = base64_decode($text);
                    if ($decoded !== false) {
                        $output->writeln($this->formatBody($decoded, $mimeType));
                    }
                }
            } else {
                $output->writeln($this->formatBody($text, $mimeType));
            }
            $output->writeln('');
        }

        // Timings section
        $output->writeln('<fg=magenta;options=bold>═══════════════════════════════════════════════════════════════</>');
        $output->writeln('<fg=magenta;options=bold>TIMINGS</>');
        $output->writeln('<fg=magenta;options=bold>═══════════════════════════════════════════════════════════════</>');

        $totalTime = $entry['time'] ?? 0;
        $output->writeln(sprintf('<fg=white;options=bold>Total:</> %.2f ms', $totalTime));
        $output->writeln('');

        if (!empty($timings)) {
            $timingLabels = [
                'blocked' => 'Blocked',
                'dns' => 'DNS Lookup',
                'connect' => 'Connect',
                'ssl' => 'SSL/TLS',
                'send' => 'Send',
                'wait' => 'Wait (TTFB)',
                'receive' => 'Receive',
            ];

            foreach ($timingLabels as $key => $label) {
                if (isset($timings[$key]) && $timings[$key] >= 0) {
                    $bar = $this->createTimingBar($timings[$key], $totalTime);
                    $output->writeln(sprintf('  %-12s %8.2f ms  %s', $label . ':', $timings[$key], $bar));
                }
            }
        }
    }

    private function formatBody(string $body, string $mimeType): string
    {
        // Try to pretty-print JSON
        if (str_contains($mimeType, 'json')) {
            $decoded = json_decode($body, true);
            if ($decoded !== null) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return $body;
    }

    private function getStatusColor(int $status): string
    {
        if ($status >= 200 && $status < 300) {
            return 'green';
        }
        if ($status >= 300 && $status < 400) {
            return 'blue';
        }
        if ($status >= 400 && $status < 500) {
            return 'yellow';
        }
        if ($status >= 500) {
            return 'red';
        }
        return 'white';
    }

    private function createTimingBar(float $timing, float $total): string
    {
        if ($total <= 0) {
            return '';
        }

        $maxWidth = 40;
        $width = (int) round(($timing / $total) * $maxWidth);
        $width = max(1, min($width, $maxWidth));

        return '<fg=cyan>' . str_repeat('█', $width) . '</>';
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
