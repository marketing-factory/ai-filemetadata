<?php

namespace Mfd\Ai\FileMetadata\Event;

use TYPO3\CMS\Core\Resource\File;

class ModifyUpdateArrayEvent
{
    /**
     * @param File $file
     */
    public function __construct(
        private array $metadata,
        private array $originalMetadata
    ) {
    }

    /**
     * @return array
     */
    public function getMetaData() : array
    {
        return $this->metadata;
    }

    /**
     * @param array $metadata
     * @return void
     */
    public function setMetaData(array $metadata) : void
    {
        $this->metadata = $metadata;
    }

    /**
     * @return array
     */
    public function getOriginalMetadata(): array
    {
        return $this->originalMetadata;
    }

}
