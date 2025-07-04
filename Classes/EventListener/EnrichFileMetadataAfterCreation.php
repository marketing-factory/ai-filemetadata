<?php

namespace Mfd\Ai\FileMetadata\EventListener;

use Mfd\Ai\FileMetadata\Api\OpenAiClient;
use Mfd\Ai\FileMetadata\Services\ConfigurationService;
use Mfd\Ai\FileMetadata\Services\FalAdapter;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Event\AfterFileMetaDataCreatedEvent;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class EnrichFileMetadataAfterCreation
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private FileRepository $fileRepository,
        private OpenAiClient $openAiClient,
        private SiteFinder $siteFinder,
        private ConfigurationService $configurationService,
        private LoggerInterface $logger,
        private FalAdapter $falAdapter,
    ) {
    }

    public function __invoke(AfterFileMetaDataCreatedEvent $event): void
    {
        $metadataRow = $event->getRecord();
        if (($metadataRow['alternative'] ?? '') !== '') {
            return;
        }

        $file = $this->fileRepository->findByUid($event->getFileUid());
        $this->logger->debug(sprintf('file: %s, %u', $file->getIdentifier(), $file->getStorage()?->getUid()));
        if (!in_array($file->getExtension(), ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
            $this->logger->debug('Skipped due to wrong file extension');
            return;
        }

        $languageMappings = $this->configurationService->getLanguageMappingForFile($file);
        if (is_null($languageMappings)) {
            $sites = $this->siteFinder->getAllSites();
            $locale = null;

            // No active site. We do not know for which language to create file metadata
            if ($sites === []) {
                return;
            }

            /** @var Site $site */
            $site = reset($sites);
            $defaultLocale = $site->getDefaultLanguage()->getLocale()->posixFormatted();

            $languageMappings = [
                0 => $defaultLocale,
            ];
        }

        // During FAL upload we can safely assume that the current metadata record belongs to site language 0
        $locale = $languageMappings[0];
        $alternative = $this->openAiClient->buildAltText($file->getContents(), $locale);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder->update('sys_file_metadata')
            ->set('alternative', $alternative)
            ->set('alttext_generation_date', time())
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($event->getMetaDataUid())
                )
            )
            ->executeStatement();

        // If there are more than one languages to handle for this part of FAL's storage, ...
        if (count($languageMappings) > 1) {
            // ... reload file metadata
            $file = $this->fileRepository->findByUid($file->getUid());

            // ... and generate remaining file metadata translations
            $this->falAdapter->localizeFile($file, false);
        }
    }
}
