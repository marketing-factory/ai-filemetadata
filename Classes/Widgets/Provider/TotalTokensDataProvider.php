<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Widgets\Provider;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;

class TotalTokensDataProvider
{
    private const TABLE = 'tx_aifilemetadata_token_usage';
    private const SECONDS_30_DAYS = 2_592_000;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @return array{totalTokens: int, requests: int}
     */
    public function getTotals(int $secondsBack = self::SECONDS_30_DAYS): array
    {
        $since = time() - $secondsBack;

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $row = $queryBuilder
            ->selectLiteral(
                $queryBuilder->expr()->sum('total_tokens', 'total'),
                'COUNT(*) AS requests',
            )
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        return [
            'totalTokens' => (int)($row['total'] ?? 0),
            'requests' => (int)($row['requests'] ?? 0),
        ];
    }
}
