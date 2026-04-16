<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Automatically generates FAL metadata for files by means of public LLMs',
    'description' => 'Automatically generates FAL metadata for files by means of public LLMs',
    'category' => 'frontend',
    'author' => 'Marketing Factory Digital GmbH',
    'author_email' => 'info@marketing-factory.de',
    'state' => 'beta',
    'version' => '1.6.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.3.99',
            'php' => '8.2.20-8.5.99'
        ],
        'conflicts' => [],
        'suggests' => [
            'picturecredits' => '*',
            'cms-filemetadata' => '*'
        ],
    ],
];
