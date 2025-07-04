<?php

$GLOBALS['TCA']['sys_file_metadata']['columns']['alternative']['config']['renderType'] = 'aiGeneratedAltText';

$additionalColumns = [
    'alttext_generation_date' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:ai-filemetadata/Resources/Private/Language/locallang_ai_filemetadata.xlf:sys_file_metadata.alttext_generation_date',
        'config' => [
            'type' => 'input',
            'renderType' => 'inputDateTime',
            'eval' => 'datetime,int',
            'readOnly' => true,
        ],
        // Optionally only show if set:
        'displayCond' => 'FIELD:alttext_generation_date:>:0',
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', $additionalColumns);
// Do not add to any showitem, so it's not editable in backend forms

// Add alttext_generation_date to showitem after alternative
$GLOBALS['TCA']['sys_file_metadata']['types']['1']['showitem'] =
    str_replace(
        'alternative,',
        'alternative,alttext_generation_date,',
        $GLOBALS['TCA']['sys_file_metadata']['types']['1']['showitem']
    );
