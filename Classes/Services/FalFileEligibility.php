<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Services;

use TYPO3\CMS\Core\Resource\File;

final class FalFileEligibility
{
    private const SUPPORTED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    private const SUPPORTED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public function __construct(
        private readonly ConfigurationService $configurationService,
    ) {
    }

    public function hasSupportedExtension(File $file): bool
    {
        return in_array($file->getExtension(), self::SUPPORTED_EXTENSIONS, true);
    }

    public function hasSupportedMimeType(File $file): bool
    {
        return in_array($file->getMimeType(), self::SUPPORTED_MIME_TYPES, true);
    }

    public function isEligible(File $file): bool
    {
        return $this->hasSupportedExtension($file)
            && $this->hasSupportedMimeType($file)
            && !$this->configurationService->shouldBeExcluded($file);
    }

    public function isExcludedIdentifier(int $storageUid, string $identifier): bool
    {
        $combinedIdentifier = $storageUid . ':' . $identifier;
        foreach ($this->getExcludedPrefixes() as $exclude) {
            if (str_starts_with($combinedIdentifier, $exclude)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function getSupportedExtensions(): array
    {
        return self::SUPPORTED_EXTENSIONS;
    }

    /**
     * @return list<string>
     */
    public function getSupportedMimeTypes(): array
    {
        return self::SUPPORTED_MIME_TYPES;
    }

    /**
     * @return list<string>
     */
    public function getExcludedPrefixes(): array
    {
        return array_values(array_filter(
            $this->configurationService->getFalExcludes(),
            is_string(...),
        ));
    }
}
