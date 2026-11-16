<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Lowlevel\Event\ModifyBlindedConfigurationOptionsEvent;

#[AsEventListener]
final class BlindConfigurationOptionsEventListener
{
    private const BLINDED_CONFIGURATION_OPTIONS = [
        'TYPO3_CONF_VARS' => [
            'EXTENSIONS' => [
                'ai_filemetadata' => [
                    'apiKey' => '###',
                    'organizationId' => '###',
                    'projectId' => '###',
                ],
            ],
        ],
    ];

    public function __invoke(ModifyBlindedConfigurationOptionsEvent $event): void
    {
        $blindedConfigurationOptions = $event->getBlindedConfigurationOptions();

        foreach (self::BLINDED_CONFIGURATION_OPTIONS as $globalVariableKey => $configurationOptions) {
            $blindedConfigurationOptions[$globalVariableKey] = array_replace_recursive(
                (array)($blindedConfigurationOptions[$globalVariableKey] ?? []),
                $configurationOptions
            );
        }

        $event->setBlindedConfigurationOptions($blindedConfigurationOptions);
    }
}
