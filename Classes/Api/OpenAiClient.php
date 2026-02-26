<?php

namespace Mfd\Ai\FileMetadata\Api;

use OpenAI;
use OpenAI\Client as OpenAiApiClient;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

readonly class OpenAiClient
{
    private OpenAiApiClient $openAiClient;

    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private readonly LoggerInterface $logger,
    ) {
        $apiKey = $this->extensionConfiguration->get('ai_filemetadata', 'apiKey');
        $organizationId = $this->extensionConfiguration->get('ai_filemetadata', 'organizationId');
        $projectId = $this->extensionConfiguration->get('ai_filemetadata', 'projectId');
        $APIBaseUri = $this->extensionConfiguration->get('ai_filemetadata', 'apiBaseUri');
        if ($APIBaseUri === '') {
            $APIBaseUri = 'https://api.openai.com/v1/';
        }
        $this->openAiClient = OpenAI::factory()
            ->withBaseUri($APIBaseUri)
            ->withApiKey($apiKey)
            ->withOrganization($organizationId)
            ->withHttpHeader('OpenAI-Project', $projectId)
            ->make();
    }

    public function buildAltText(string $image, ?string $locale = null): string
    {

        $prompt = $this->extensionConfiguration->get('ai_filemetadata', 'altTextPrompt');
        if ($prompt === '') {
            $prompt = <<<'GPT'
Create an alternative text for this image to be used on websites for visually impaired people who cannot see the image.
Focus on the image's main content and ignore all elements in the image not relevant to understand its message.
The text should not exceed 50 words.
GPT;
        } else {
            $prompt = str_replace('\n', "\n", $prompt);
        }

        if ($locale) {
            $languageEnglishName = \Locale::getDisplayLanguage(\Locale::getPrimaryLanguage($locale), 'en');

            $prompt .= "\n Answer in {$languageEnglishName}.";
        }

        $this->logger->info('Prompt: ' . $prompt);

        $modell = $this->extensionConfiguration->get('ai_filemetadata', 'model');
        if ($modell === '') {
            $modell = 'gpt-4o-mini';
        }

        $requestData = [
            'model' => $modell,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $prompt,
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Generate alt text.',
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:image/jpeg;base64,' . base64_encode($image),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($this->extensionConfiguration->get('ai_filemetadata', 'temperature') !== '') {
            $temperature = (float)$this->extensionConfiguration->get('ai_filemetadata', 'temperature');
            if ($temperature < 0.1 || $temperature > 1 ) {
                $temperature = 0.6;
            }
            $requestData['temperature'] = $temperature;
        }

        $response = $this->openAiClient->chat()->create($requestData);

        if ($response->usage !== [] && ($usage = $response->usage)) {
            $this->logger->debug(print_r($usage, true));
        }
        if ($response->choices !== [] && ($choice = $response->choices[0])) {
            $this->logger->debug(print_r($choice, true));
            return trim($choice->message->content,'"') ?? '';
        }


        throw new \UnexpectedValueException('Did not find any choices in the response');
    }
}
