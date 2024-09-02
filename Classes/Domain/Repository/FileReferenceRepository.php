<?php

namespace Mfd\Ai\FileMetadata\Domain\Repository;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class FileReferenceRepository extends Repository
{
    public function initializeObject(): void
    {
        /** @var QuerySettingsInterface $defaultQuerySettings */
        $defaultQuerySettings = GeneralUtility::makeInstance(QuerySettingsInterface::class);
        $defaultQuerySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($defaultQuerySettings);
    }
}
