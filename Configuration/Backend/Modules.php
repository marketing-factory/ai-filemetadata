<?php

declare(strict_types=1);

use Mfd\Ai\FileMetadata\Backend\Controller\AiAlternativeTextsController;

return [
    'ai_filemetadata' => [
        'parent' => 'file',
        'position' => ['after' => 'media_management'],
        'access' => 'user',
        'path' => '/module/file/ai-alternative-texts',
        'iconIdentifier' => 'actions-ai-generate',
        'labels' => [
            'title' => 'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:module.title',
            'description' => 'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:module.description',
            'shortDescription' => 'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:module.description',
        ],
        'navigationComponent' => '@typo3/backend/tree/file-storage-tree-container',
        'routes' => [
            '_default' => [
                'target' => AiAlternativeTextsController::class . '::handleRequest',
            ],
            'review' => [
                'target' => AiAlternativeTextsController::class . '::reviewAction',
                'methods' => ['POST'],
            ],
            'edit' => [
                'target' => AiAlternativeTextsController::class . '::editAction',
                'methods' => ['POST'],
            ],
        ],
    ],
];
