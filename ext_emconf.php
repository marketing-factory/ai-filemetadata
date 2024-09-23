<?php

$EM_CONF['ai_filemetadata'] = [
    'title' => 'Automatically generates FAL metadata for files by means of public LLMs',
    'description' => 'Automatically generates FAL metadata for files by means of public LLMs',
    'category' => 'frontend',
    'author' => 'MFD',
    'author_email' => 'info@marketing-factory.de',
    'state' => 'beta',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
        'typo3' => '12.4-13.4.99        '
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
