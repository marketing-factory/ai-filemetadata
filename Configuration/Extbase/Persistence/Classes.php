<?php
declare(strict_types=1);

use Mfd\Ai\FileMetadata\Domain\Model\FileMetadata;
use Mfd\Ai\FileMetadata\Domain\Model\FileReference;

return [
    FileMetadata::class => [
        'tableName' => 'sys_file_metadata',
    ],
    FileReference::class => [
        'tableName' => 'sys_file_reference',
    ],
];
