<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Cache;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Stores the exact text an AI "recreate alt text" suggestion produced for a
 * sys_file_metadata record, so the DataHandler hook check:
 *  - the suggestion being saved unmodified (still AI-generated)
 *  - the suggestion being edited by hand before saving (no longer AI-generated)
 */
class AltTextSuggestionCache
{
    public const CACHE_IDENTIFIER = 'ai_filemetadata_alttext_suggestions';

    public function __construct(private readonly CacheManager $cacheManager)
    {
    }

    public function remember(int $metadataUid, int $backendUserUid, string $suggestedText): void
    {
        $this->getCache()->set($this->cacheEntryIdentifier($metadataUid, $backendUserUid), $suggestedText);
    }

    public function get(int $metadataUid, int $backendUserUid): ?string
    {
        $value = $this->getCache()->get($this->cacheEntryIdentifier($metadataUid, $backendUserUid));

        return $value === false ? null : $value;
    }

    public function forget(int $metadataUid, int $backendUserUid): void
    {
        $this->getCache()->remove($this->cacheEntryIdentifier($metadataUid, $backendUserUid));
    }

    private function cacheEntryIdentifier(int $metadataUid, int $backendUserUid): string
    {
        return 'suggestion_' . $metadataUid . '_' . $backendUserUid;
    }

    private function getCache(): FrontendInterface
    {
        try {
            return $this->cacheManager->getCache(self::CACHE_IDENTIFIER);
        } catch (NoSuchCacheException) {
            return $this->cacheManager->getCache('runtime');
        }
    }
}
