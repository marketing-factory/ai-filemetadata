<?php

namespace Mfd\Ai\FileMetadata\Event;

use TYPO3\CMS\Core\Resource\File;

class ShouldBeExcludedEvent
{
    /**
     * @var bool
     */
    protected $shouldBeExcluded = false;

    /**
     * @param File $file
     */
    public function __construct(
        private readonly File $file
    ) {
    }

    /**
     * @return File
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param bool $shouldBeExcluded
     * @return void
     */
    public function setShouldBeExcluded(bool $shouldBeExcluded)
    {
        $this->shouldBeExcluded = $shouldBeExcluded;
    }

    /**
     * @return bool
     */
    public function getShouldBeExcluded() : bool
    {
        return $this->shouldBeExcluded;
    }


}
