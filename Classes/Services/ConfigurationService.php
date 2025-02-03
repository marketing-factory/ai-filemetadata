<?php

namespace Mfd\Ai\FileMetadata\Services;

use TYPO3\CMS\Core\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\ArrayUtility;

class ConfigurationService
{
    private array $falExcludes = [];
    private array $falLanguageMappings = [];

    public function __construct(private readonly ConfigurationManager $configurationManager)
    {
        $this->loadConfiguration();
    }

    private function loadConfiguration(): void
    {
        $configuration = $this->configurationManager->getMergedLocalConfiguration();

        try {
            $this->falLanguageMappings = ArrayUtility::getValueByPath(
                $configuration,
                'EXTCONF/ai_filemetadata/falLanguageMappings'
            );
        } catch (\Exception) {
            $this->falLanguageMappings = [];
        }

        try {
            $this->falExcludes = ArrayUtility::getValueByPath(
                $configuration,
                'EXTCONF/ai_filemetadata/falExcludedPrefixes'
            );
        } catch (\Exception) {
            $this->falExcludes = [];
        }
    }

    public function shouldBeExcluded(File $file): bool
    {
        foreach ($this->falExcludes as $exclude) {
            if ($this->fileIsInPrefix($file, $exclude)) {
                return true;
            }
        }

        return false;
    }

    public function getLanguageMappingForFile(File $file): ?array
    {
        foreach ($this->falLanguageMappings as $prefix => $mapping) {
            if ($this->fileIsInPrefix($file, $prefix)) {
                return $mapping;
            }
        }

        return null;
    }

    private function fileIsInPrefix(File $file, string $prefix): bool
    {
        return str_starts_with(
            $file->getStorage()->getUid() . ':' . $file->getIdentifier(),
            $prefix
        );
    }

    public function getFalExcludes(): array
    {
        return $this->falExcludes;
    }

    public function getFalLanguageMappings(): array
    {
        return $this->falLanguageMappings;
    }
}
