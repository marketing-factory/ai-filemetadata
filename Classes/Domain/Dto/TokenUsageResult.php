<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Domain\Dto;

readonly class TokenUsageResult
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public int $totalTokens,
        public string $model,
    ) {}
}
