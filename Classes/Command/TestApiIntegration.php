<?php

namespace Mfd\Ai\FileMetadata\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand('ai:test')]
class TestApiIntegration extends Command
{
    private const API_URL = 'https://api.openai.com/v1';

    public function __construct(private readonly RequestFactory $requestFactory)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiKey = 'sk-svcacct-V2GRcJBAJJQmdl5hGJ1LT3BlbkFJtp0QoNO7JC4iRwmwVkN7';
        $apiOrganization = 'org-5m35vasVrNfFuax9H4VEzAxw';
        $apiProject = 'proj_YLAqKq0KfrjFRORj95DUQZGH';

        $image = file_get_contents(
            '/var/www/html/public/fileadmin/_processed_/7/5/csm_auf-dem-segger-stand_2ad6bf883b.png'
        );

        $response = $this->requestFactory->request(
            self::API_URL . '/chat/completions',
            'POST',
            [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'OpenAI-Organization' => $apiOrganization,
                    'OpenAI-Project' => $apiProject,
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => <<<'GPT'
Create an alternative text for this image to be used on websites for visually impaired people who cannot see the image.
Focus on the image's main content and ignore all elements in the image not relevant to understand its message.
The text should not exceed 50 words.
Answer in German.
GPT
            ,
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
                    'max_tokens' => 300,
                ],
            ],
        );

        echo "{$response->getStatusCode()}\n";
        dump(json_decode($response->getBody(), true));

        return 0;
    }
}
