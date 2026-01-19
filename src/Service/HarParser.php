<?php

declare(strict_types=1);

namespace HarPhp\Service;

use RuntimeException;

class HarParser
{
    /**
     * Parse a HAR file and return its contents as an array
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("HAR file not found: $filePath");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read HAR file: $filePath");
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['log'])) {
            throw new RuntimeException("Invalid HAR file: missing 'log' property");
        }

        return $data;
    }

    /**
     * Get all entries (requests) from HAR data
     */
    public function getEntries(array $harData): array
    {
        return $harData['log']['entries'] ?? [];
    }

    /**
     * Get a specific entry by index
     */
    public function getEntry(array $harData, int $index): ?array
    {
        $entries = $this->getEntries($harData);
        return $entries[$index] ?? null;
    }

    /**
     * Replace entries in HAR data and return new structure
     */
    public function replaceEntries(array $harData, array $entries): array
    {
        $harData['log']['entries'] = array_values($entries);
        return $harData;
    }

    /**
     * Convert HAR data back to JSON string
     */
    public function toJson(array $harData, bool $pretty = false): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return json_encode($harData, $flags);
    }

    /**
     * Extract URL from an entry
     */
    public function getEntryUrl(array $entry): string
    {
        return $entry['request']['url'] ?? '';
    }

    /**
     * Extract method from an entry
     */
    public function getEntryMethod(array $entry): string
    {
        return $entry['request']['method'] ?? 'GET';
    }

    /**
     * Extract status code from an entry
     */
    public function getEntryStatus(array $entry): int
    {
        return (int) ($entry['response']['status'] ?? 0);
    }

    /**
     * Extract content type from response
     */
    public function getEntryContentType(array $entry): string
    {
        $headers = $entry['response']['headers'] ?? [];
        foreach ($headers as $header) {
            if (strtolower($header['name'] ?? '') === 'content-type') {
                return $header['value'] ?? '';
            }
        }
        return '';
    }

    /**
     * Extract response size from an entry
     */
    public function getEntrySize(array $entry): int
    {
        return (int) ($entry['response']['content']['size'] ?? 0);
    }

    /**
     * Extract timing information
     */
    public function getEntryTime(array $entry): float
    {
        return (float) ($entry['time'] ?? 0);
    }
}
