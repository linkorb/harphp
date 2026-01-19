<?php

declare(strict_types=1);

namespace HarPhp\Service;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class FilterConfig
{
    private array $rules = [];

    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    /**
     * Load filter configuration from a YAML file
     */
    public static function fromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Filter config file not found: $filePath");
        }

        $config = Yaml::parseFile($filePath);
        
        return new self($config['filters'] ?? []);
    }

    /**
     * Get all ignore rules
     */
    public function getIgnoreRules(): array
    {
        return $this->rules['ignore'] ?? [];
    }

    /**
     * Get all include rules (whitelist)
     */
    public function getIncludeRules(): array
    {
        return $this->rules['include'] ?? [];
    }

    /**
     * Check if config has any rules
     */
    public function hasRules(): bool
    {
        return !empty($this->rules);
    }

    /**
     * Get raw rules array
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
