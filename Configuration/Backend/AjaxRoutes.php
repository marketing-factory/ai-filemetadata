<?php

use Mfd\Ai\FileMetadata\Backend\Controller;

return [
    // AJAX route for AI-generated alt text suggestions
    'record_ai_generated_alt_text' => [
        'path' => '/record/ai-generated-alt-text',
        'target' => Controller\AiGeneratedAltTextAjaxController::class . '::suggestAction',
    ],
];
