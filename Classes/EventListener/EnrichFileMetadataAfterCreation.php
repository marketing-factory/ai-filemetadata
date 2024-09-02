<?php

namespace Mfd\Ai\FileMetadata\EventListener;

use Mfd\Ai\FileMetadata\Api\OpenAiClient;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Event\AfterFileMetaDataCreatedEvent;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

readonly class EnrichFileMetadataAfterCreation
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private FileRepository $fileRepository,
        private OpenAiClient $openAiClient,
        private SiteFinder $siteFinder,
    ) {
    }

    public function __invoke(AfterFileMetaDataCreatedEvent $event): void
    {
        $metadataRow = $event->getRecord();
        if (($metadataRow['alternative'] ?? '') !== '') {
            return;
        }

        $file = $this->fileRepository->findByUid($event->getFileUid());
        if (!$file->isImage()) {
            return;
        }

        $sites = $this->siteFinder->getAllSites();
        $locale = null;

        if ($sites !== []) {
            /** @var Site $site */
            $site = reset($sites);
            $locale = $site->getDefaultLanguage()->getLocale()->posixFormatted();
        }

        $alternative = $this->openAiClient->buildAltText($file->getContents(), $locale);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder->update('sys_file_metadata')
            ->set('alternative', $alternative)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($event->getMetaDataUid())
                )
            )
            ->executeStatement();
    }
}
