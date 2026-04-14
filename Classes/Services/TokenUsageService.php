<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Services;

use Mfd\Ai\FileMetadata\Domain\Dto\TokenUsageResult;
use Mfd\Ai\FileMetadata\Event\AfterTokenUsageRecordedEvent;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

class TokenUsageService
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcher $eventDispatcher,
        private readonly ConfigurationService $configurationService,
    ) {}

    public function track(
        TokenUsageResult $usage,
        string $context = '',
        int $fileUid = 0,
        ?string $locale = null,
    ): void {
        if (!$this->configurationService->getEnableTokenTracking()) {
            return;
        }

        $beUserUid = (int)($GLOBALS['BE_USER']->user['uid'] ?? 0);
        $now = time();

        $connection = $this->connectionPool->getConnectionForTable('tx_aifilemetadata_token_usage');
        $connection->insert('tx_aifilemetadata_token_usage', [
            'pid' => 0,
            'tstamp' => $now,
            'crdate' => $now,
            'model' => $usage->model,
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'total_tokens' => $usage->totalTokens,
            'context' => $context,
            'file_uid' => $fileUid,
            'be_user_uid' => $beUserUid,
            'locale' => $locale ?? '',
        ]);

        $this->eventDispatcher->dispatch(
            new AfterTokenUsageRecordedEvent($usage, $context, $fileUid, $locale)
        );
    }
}
