<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Backend;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\RootLevelRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Resource\Folder;

final class GeneratedAltTextQuery
{
    private const LEGACY_IMAGE_FILE_TYPE = 2;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly Context $context,
    ) {
    }

    public function countByFolder(Folder $folder): int
    {
        return (int)$this->createQueryBuilder($folder)
            ->count('metadata.uid')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByFolder(Folder $folder, int $offset, int $limit): array
    {
        return $this->createQueryBuilder($folder)
            ->select(
                'metadata.uid',
                'metadata.file',
                'metadata.alternative',
                'metadata.sys_language_uid',
                'metadata.alttext_generation_date',
            )
            ->orderBy('metadata.alttext_generation_date', 'DESC')
            ->addOrderBy('metadata.uid', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function createQueryBuilder(Folder $folder): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder->getRestrictions()
            ->add(new RootLevelRestriction())
            ->add(new WorkspaceRestriction($this->context->getAspect('workspace')->getId()));

        return $queryBuilder
            ->from('sys_file_metadata', 'metadata')
            ->innerJoin(
                'metadata',
                'sys_file',
                'file',
                $queryBuilder->expr()->eq('file.uid', $queryBuilder->quoteIdentifier('metadata.file')),
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'file.storage',
                    $queryBuilder->createNamedParameter($folder->getStorage()->getUid(), Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->eq(
                    'file.folder_hash',
                    $queryBuilder->createNamedParameter($folder->getHashedIdentifier()),
                ),
                $queryBuilder->expr()->eq(
                    'file.type',
                    $queryBuilder->createNamedParameter($this->getImageFileType(), Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->eq(
                    'file.missing',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->gt(
                    'metadata.alttext_generation_date',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT),
                ),
            );
    }

    private function getImageFileType(): int
    {
        return class_exists(FileType::class) ? FileType::IMAGE->value : self::LEGACY_IMAGE_FILE_TYPE;
    }
}
