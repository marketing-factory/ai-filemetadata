<?php

use Mfd\Ai\FileMetadata\Cache\AltTextSuggestionCache;
use Mfd\Ai\FileMetadata\Form\Element\AiGeneratedAltTextElement;
use Mfd\Ai\FileMetadata\Hooks\ResetAltTextGenerationDateHook;
use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

call_user_func(static function () {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1722516398] = [
        'nodeName' => 'aiGeneratedAltText',
        'priority' => 40,
        'class' => AiGeneratedAltTextElement::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][AltTextSuggestionCache::CACHE_IDENTIFIER] ??= [
        'frontend' => VariableFrontend::class,
        'backend' => SimpleFileBackend::class,
        'options' => [
            'defaultLifetime' => 3600,
        ],
    ];

    // Resets sys_file_metadata.alttext_generation_date whenever "alternative" is changed by hand
    // (see ResetAltTextGenerationDateHook for the full reasoning).
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][ResetAltTextGenerationDateHook::class]
        = ResetAltTextGenerationDateHook::class;
});

