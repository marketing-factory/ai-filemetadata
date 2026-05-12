<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$GLOBALS['TCA']['sys_file_metadata']['columns']['alternative']['config']['renderType'] = 'aiGeneratedAltText';

$additionalColumns = [
    'alttext_generation_date' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_ai_filemetadata.xlf:sys_file_metadata.alttext_generation_date',
        'config' => [
            'type' => 'datetime',
            'readOnly' => true,
        ],
        // Optionally only show if set:
        'displayCond' => 'FIELD:alttext_generation_date:>:0',
    ],
];

ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', $additionalColumns);

ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    'alttext_generation_date',
    '',
    'after:alternative'
);

