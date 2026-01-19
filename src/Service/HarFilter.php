<?php

declare(strict_types=1);

namespace HarPhp\Service;

class HarFilter
{
    public function __construct(
        private readonly HarParser $parser = new HarParser()
    ) {
    }

    /**
     * Filter HAR entries based on filter configuration
     */
    public function filter(array $harData, FilterConfig $config): array
    {
        $entries = $this->parser->getEntries($harData);
        $ignoreRules = $config->getIgnoreRules();
        $includeRules = $config->getIncludeRules();

        // If we have include rules, only keep entries that match at least one
        // If we have ignore rules, remove entries that match any

        $filtered = [];
        foreach ($entries as $entry) {
            $url = $this->parser->getEntryUrl($entry);
            $parsedUrl = parse_url($url);

            // Check include rules first (whitelist mode)
            if (!empty($includeRules)) {
                if ($this->matchesAnyRule($url, $parsedUrl, $includeRules)) {
                    $filtered[] = $entry;
                }
                continue;
            }

            // Check ignore rules (blacklist mode)
            if (!empty($ignoreRules)) {
                if (!$this->matchesAnyRule($url, $parsedUrl, $ignoreRules)) {
                    $filtered[] = $entry;
                }
                continue;
            }

            // No rules, keep all
            $filtered[] = $entry;
        }

        return $this->parser->replaceEntries($harData, $filtered);
    }

    /**
     * Check if URL matches any of the given rules
     */
    private function matchesAnyRule(string $url, array $parsedUrl, array $rules): bool
    {
        // Check domain rules
        if (isset($rules['domains'])) {
            $host = $parsedUrl['host'] ?? '';
            foreach ($rules['domains'] as $pattern) {
                if ($this->matchPattern($host, $pattern)) {
                    return true;
                }
            }
        }

        // Check path rules
        if (isset($rules['paths'])) {
            $path = $parsedUrl['path'] ?? '/';
            foreach ($rules['paths'] as $pattern) {
                if ($this->matchPattern($path, $pattern)) {
                    return true;
                }
            }
        }

        // Check extension rules
        if (isset($rules['extensions'])) {
            $path = $parsedUrl['path'] ?? '';
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            foreach ($rules['extensions'] as $ext) {
                $ext = ltrim($ext, '.');
                if (strtolower($extension) === strtolower($ext)) {
                    return true;
                }
            }
        }

        // Check full URL rules
        if (isset($rules['urls'])) {
            foreach ($rules['urls'] as $pattern) {
                if ($this->matchPattern($url, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Match a string against a pattern (glob or regex)
     */
    private function matchPattern(string $subject, string $pattern): bool
    {
        // Check if it's a regex (starts and ends with delimiter)
        if ($this->isRegex($pattern)) {
            return (bool) preg_match($pattern, $subject);
        }

        // Treat as glob pattern
        return $this->globMatch($pattern, $subject);
    }

    /**
     * Check if pattern is a regex
     */
    private function isRegex(string $pattern): bool
    {
        if (strlen($pattern) < 2) {
            return false;
        }

        $delimiters = ['/', '#', '~', '@', '%'];
        $firstChar = $pattern[0];

        if (!in_array($firstChar, $delimiters, true)) {
            return false;
        }

        // Find closing delimiter
        $lastPos = strrpos($pattern, $firstChar);
        return $lastPos > 0;
    }

    /**
     * Match a string against a glob pattern
     */
    private function globMatch(string $pattern, string $subject): bool
    {
        // Convert glob to regex
        $regex = $this->globToRegex($pattern);
        return (bool) preg_match($regex, $subject);
    }

    /**
     * Convert a glob pattern to a regex
     */
    private function globToRegex(string $glob): string
    {
        $regex = '';
        $length = strlen($glob);

        for ($i = 0; $i < $length; $i++) {
            $char = $glob[$i];

            switch ($char) {
                case '*':
                    // Check for **
                    if (isset($glob[$i + 1]) && $glob[$i + 1] === '*') {
                        $regex .= '.*';
                        $i++;
                    } else {
                        $regex .= '[^/]*';
                    }
                    break;

                case '?':
                    $regex .= '[^/]';
                    break;

                case '.':
                case '(':
                case ')':
                case '{':
                case '}':
                case '[':
                case ']':
                case '^':
                case '$':
                case '+':
                case '|':
                case '\\':
                    $regex .= '\\' . $char;
                    break;

                default:
                    $regex .= $char;
            }
        }

        return '#' . $regex . '#i';
    }

    /**
     * Count entries in HAR data
     */
    public function countEntries(array $harData): int
    {
        return count($this->parser->getEntries($harData));
    }
}
