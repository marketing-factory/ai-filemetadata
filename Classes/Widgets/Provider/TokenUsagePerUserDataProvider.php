<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Widgets\Provider;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;

class TokenUsagePerUserDataProvider
{
    private const TABLE = 'tx_aifilemetadata_token_usage';
    private const DAYS = 30;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @return array<int, array{name: string, beUserUid: int, requests: int, inputTokens: int, outputTokens: int, totalTokens: int}>
     */
    public function getItems(): array
    {
        $since = strtotime('-' . self::DAYS . ' days midnight');

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->selectLiteral(
                $queryBuilder->expr()->sum('total_tokens', 'total'),
                $queryBuilder->expr()->sum('input_tokens', 'input'),
                $queryBuilder->expr()->sum('output_tokens', 'output'),
                'COUNT(*) AS requests',
            )
            ->addSelect('t.be_user_uid', 'u.username', 'u.realName')
            ->from(self::TABLE, 't')
            ->leftJoin('t', 'be_users', 'u', $queryBuilder->expr()->eq('t.be_user_uid', 'u.uid'))
            ->where(
                $queryBuilder->expr()->gte('t.crdate', $queryBuilder->createNamedParameter($since, ParameterType::INTEGER))
            )
            ->groupBy('t.be_user_uid', 'u.username', 'u.realName')
            ->orderBy('total', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'name' => trim($row['realName'] ?: $row['username'] ?: '(CLI)'),
                'beUserUid' => (int)$row['be_user_uid'],
                'requests' => (int)$row['requests'],
                'inputTokens' => (int)$row['input'],
                'outputTokens' => (int)$row['output'],
                'totalTokens' => (int)$row['total'],
            ];
        }

        return $items;
    }
}
