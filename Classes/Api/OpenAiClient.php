<?php

namespace Mfd\Ai\FileMetadata\Api;

use OpenAI;
use OpenAI\Client as OpenAiApiClient;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

readonly class OpenAiClient
{
    private OpenAiApiClient $openAiClient;

    public function __construct(private ExtensionConfiguration $extensionConfiguration)
    {
        $apiKey = $this->extensionConfiguration->get('ai_filemetadata', 'apiKey');
        $organizationId = $this->extensionConfiguration->get('ai_filemetadata', 'organizationId');
        $projectId = $this->extensionConfiguration->get('ai_filemetadata', 'projectId');

        $this->openAiClient = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withOrganization($organizationId)
            ->withHttpHeader('OpenAI-Project', $projectId)
            ->make();
    }

    public function buildAltText(string $image, ?string $locale = null): string
    {
        $prompt = <<<'GPT'
Create an alternative text for this image to be used on websites for visually impaired people who cannot see the image.
Focus on the image's main content and ignore all elements in the image not relevant to understand its message.
The text should not exceed 50 words.
GPT;

        if ($locale) {
            $languageEnglishName = \Locale::getDisplayLanguage(\Locale::getPrimaryLanguage($locale), 'en');

            $prompt .= "\n Answer in {$languageEnglishName}.";
        }

        $response = $this->openAiClient->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
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
        ]);

        if ($response->choices !== [] && ($choice = $response->choices[0])) {
            return $choice->message->content ?? '';
        }

        throw new \UnexpectedValueException('Did not find any choices in the response');
    }
}
