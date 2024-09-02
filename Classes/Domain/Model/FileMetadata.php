<?php

namespace Mfd\Ai\FileMetadata\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\File;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class FileMetadata extends AbstractEntity
{
    protected File $file;

    public function getFile(): File
    {
        return $this->file;
    }

    public function setFile(File $file): FileMetadata
    {
        $this->file = $file;
        return $this;
    }
}
