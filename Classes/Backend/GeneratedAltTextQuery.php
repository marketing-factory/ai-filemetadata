<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Backend;

use Doctrine\DBAL\ArrayParameterType;
use Mfd\Ai\FileMetadata\Services\FalFileEligibility;
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
    public const STATUS_GENERATED = 'generated';
    public const STATUS_MISSING = 'missing';

    private const LEGACY_IMAGE_FILE_TYPE = 2;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly Context $context,
        private readonly FalFileEligibility $falFileEligibility,
    ) {
    }

    /**
     * @param list<Folder> $scopeFolders
     */
    public function count(array $scopeFolders, string $status): int
    {
        if ($scopeFolders === []) {
            return 0;
        }

        return (int)$this->createQueryBuilder($scopeFolders, $status)
            ->count('metadata.uid')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @param list<Folder> $scopeFolders
     * @return array<int, array<string, mixed>>
     */
    public function find(array $scopeFolders, string $status, int $offset, int $limit): array
    {
        if ($scopeFolders === []) {
            return [];
        }

        return $this->createQueryBuilder($scopeFolders, $status)
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

    /**
     * @param list<Folder> $scopeFolders
     */
    private function createQueryBuilder(array $scopeFolders, string $status): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder->getRestrictions()
            ->add(new RootLevelRestriction())
            ->add(new WorkspaceRestriction($this->context->getAspect('workspace')->getId()));

        $queryBuilder
            ->from('sys_file_metadata', 'metadata')
            ->innerJoin(
                'metadata',
                'sys_file',
                'file',
                $queryBuilder->expr()->eq('file.uid', $queryBuilder->quoteIdentifier('metadata.file')),
            )
            ->where(
                $this->createScopeConstraint($queryBuilder, $scopeFolders),
                $queryBuilder->expr()->eq(
                    'file.type',
                    $queryBuilder->createNamedParameter($this->getImageFileType(), Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->in(
                    'file.extension',
                    $queryBuilder->createNamedParameter(
                        $this->falFileEligibility->getSupportedExtensions(),
                        ArrayParameterType::STRING,
                    ),
                ),
                $queryBuilder->expr()->in(
                    'file.mime_type',
                    $queryBuilder->createNamedParameter(
                        $this->falFileEligibility->getSupportedMimeTypes(),
                        ArrayParameterType::STRING,
                    ),
                ),
                $queryBuilder->expr()->eq(
                    'file.missing',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT),
                ),
                $this->createStatusConstraint($queryBuilder, $status),
            );

        $storageUids = array_unique(array_map(
            static fn(Folder $folder): int => $folder->getStorage()->getUid(),
            $scopeFolders,
        ));
        foreach ($this->falFileEligibility->getExcludedPrefixes() as $excludedPrefix) {
            foreach ($storageUids as $storageUid) {
                $storagePrefix = $storageUid . ':';
                if (str_starts_with($storagePrefix, $excludedPrefix)) {
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->neq(
                            'file.storage',
                            $queryBuilder->createNamedParameter($storageUid, Connection::PARAM_INT),
                        ),
                    );
                } elseif (str_starts_with($excludedPrefix, $storagePrefix)) {
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->or(
                            $queryBuilder->expr()->neq(
                                'file.storage',
                                $queryBuilder->createNamedParameter($storageUid, Connection::PARAM_INT),
                            ),
                            $queryBuilder->expr()->notLike(
                                'file.identifier',
                                $queryBuilder->createNamedParameter(
                                    $queryBuilder->escapeLikeWildcards(substr($excludedPrefix, strlen($storagePrefix))) . '%',
                                ),
                            ),
                        ),
                    );
                }
            }
        }

        return $queryBuilder;
    }

    /**
     * @param list<Folder> $scopeFolders
     */
    private function createScopeConstraint(QueryBuilder $queryBuilder, array $scopeFolders): string
    {
        $scopeConstraints = [];
        foreach ($scopeFolders as $folder) {
            $scopeConstraints[] = $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq(
                    'file.storage',
                    $queryBuilder->createNamedParameter($folder->getStorage()->getUid(), Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->like(
                    'file.identifier',
                    $queryBuilder->createNamedParameter(
                        $queryBuilder->escapeLikeWildcards($folder->getIdentifier()) . '%',
                    ),
                ),
            );
        }

        return (string)$queryBuilder->expr()->or(...$scopeConstraints);
    }

    private function createStatusConstraint(QueryBuilder $queryBuilder, string $status): string
    {
        if ($status === self::STATUS_MISSING) {
            return (string)$queryBuilder->expr()->or(
                $queryBuilder->expr()->isNull('metadata.alternative'),
                $queryBuilder->expr()->comparison(
                    $queryBuilder->expr()->trim('metadata.alternative'),
                    '=',
                    $queryBuilder->createNamedParameter(''),
                ),
            );
        }

        return $queryBuilder->expr()->gt(
            'metadata.alttext_generation_date',
            $queryBuilder->createNamedParameter(0, Connection::PARAM_INT),
        );
    }

    private function getImageFileType(): int
    {
        return class_exists(FileType::class) ? FileType::IMAGE->value : self::LEGACY_IMAGE_FILE_TYPE;
    }
}
