<?php

return [
    'ctrl' => [
        'title' => 'Token Usage',
        'label' => 'model',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'adminOnly' => true,
        'rootLevel' => 1,
       // 'hideTable' => true,
        'iconfile' => 'EXT:ai_filemetadata/Resources/Public/Icons/actions-ai-generate.svg',
    ],
    'columns' => [
        'model' => [
            'label' => 'Model',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'input_tokens' => [
            'label' => 'Input Tokens',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'output_tokens' => [
            'label' => 'Output Tokens',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'total_tokens' => [
            'label' => 'Total Tokens',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'context' => [
            'label' => 'Context',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'file_uid' => [
            'label' => 'File UID',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'be_user_uid' => [
            'label' => 'BE User UID',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'locale' => [
            'label' => 'Locale',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'tstamp' => [
            'label' => 'Timestamp',
            'config' => [
                'type' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'crdate' => [
            'label' => 'Creation Date',
            'config' => [
                'type' => 'datetime',
                'readOnly' => true,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'model, input_tokens, output_tokens, total_tokens, context, file_uid, be_user_uid, locale, tstamp, crdate',
        ],
    ],
];
