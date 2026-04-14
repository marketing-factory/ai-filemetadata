<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Event;

use Mfd\Ai\FileMetadata\Domain\Dto\TokenUsageResult;

final readonly class AfterTokenUsageRecordedEvent
{
    public function __construct(
        private TokenUsageResult $usage,
        private string $context,
        private int $fileUid,
        private ?string $locale,
    ) {}

    public function getUsage(): TokenUsageResult
    {
        return $this->usage;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getFileUid(): int
    {
        return $this->fileUid;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
