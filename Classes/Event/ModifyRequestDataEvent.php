<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Event;

final class ModifyRequestDataEvent
{
    public function __construct(
        private array $requestData,
    ) {}

    public function getRequestData(): array
    {
        return $this->requestData;
    }

    public function setRequestData(array $requestData): void
    {
        $this->requestData = $requestData;
    }

    public function addTool(array $tool): void
    {
        $this->requestData['tools'][] = $tool;
    }

    public function hasToolOfType(string $type): bool
    {
        foreach ($this->requestData['tools'] ?? [] as $tool) {
            if (($tool['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }
}
