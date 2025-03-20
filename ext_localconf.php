<?php

use Mfd\Ai\FileMetadata\Form\Element\AiGeneratedAltTextElement;

call_user_func(static function () {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1722516398] = [
        'nodeName' => 'aiGeneratedAltText',
        'priority' => 40,
        'class' => AiGeneratedAltTextElement::class,
    ];
});

Mfd\Ai\FileMetadata\Extension::loadVendorLibraries();