<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Widgets\Provider;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;

class TokenUsageChartDataProvider implements ChartDataProviderInterface
{
    private const TABLE = 'tx_aifilemetadata_token_usage';
    private const DAYS = 30;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function getChartData(): array
    {
        $labels = [];
        $inputData = [];
        $outputData = [];

        $dailyUsage = $this->fetchDailyUsage();

        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('d.m.', strtotime($date));
            $inputData[] = $dailyUsage[$date]['input'] ?? 0;
            $outputData[] = $dailyUsage[$date]['output'] ?? 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Input Tokens',
                    'backgroundColor' => '#ff8700',
                    'data' => $inputData,
                ],
                [
                    'label' => 'Output Tokens',
                    'backgroundColor' => '#1a568f',
                    'data' => $outputData,
                ],
            ],
        ];
    }

    private function fetchDailyUsage(): array
    {
        $since = strtotime('-' . self::DAYS . ' days midnight');

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->selectLiteral(
                'FLOOR(crdate / 86400) AS day_bucket',
                $queryBuilder->expr()->sum('input_tokens', 'input'),
                $queryBuilder->expr()->sum('output_tokens', 'output'),
            )
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, ParameterType::INTEGER))
            )
            ->groupBy('day_bucket')
            ->orderBy('day_bucket')
            ->executeQuery()
            ->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $row) {
            $date = date('Y-m-d', (int)$row['day_bucket'] * 86400);
            $grouped[$date] = [
                'input' => (int)$row['input'],
                'output' => (int)$row['output'],
            ];
        }

        return $grouped;
    }
}
