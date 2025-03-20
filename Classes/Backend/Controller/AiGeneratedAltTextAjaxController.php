<?php

namespace Mfd\Ai\FileMetadata\Backend\Controller;

use Mfd\Ai\FileMetadata\Api\OpenAiClient;
use Mfd\Ai\FileMetadata\Domain\Model\FileMetadata;
use Mfd\Ai\FileMetadata\Domain\Repository\FileMetadataRepository;
use Mfd\Ai\FileMetadata\Domain\Repository\FileReferenceRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Controller\AbstractFormEngineAjaxController;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]
class AiGeneratedAltTextAjaxController extends AbstractFormEngineAjaxController
{
    public function __construct(
        private readonly OpenAiClient $openAiClient,
        private readonly FileMetadataRepository $fileMetadataRepository,
        private readonly SiteFinder $siteFinder,
    ) {
    }


    public function suggestAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->checkRequest($request);

        $queryParameters = $request->getParsedBody() ?? [];
        $tableName = (string)($queryParameters['tableName'] ?? '');
        $languageId = (int)$queryParameters['language'];
        $recordId = (int)$queryParameters['recordId'];

        $sites = $this->siteFinder->getAllSites();
        $locale = null;

        if ($sites !== []) {
            $site = reset($sites);
            foreach ($site->getAllLanguages() as $siteLanguage) {
                if ($siteLanguage->getLanguageId() === $languageId) {
                    $locale = $siteLanguage->getLocale()->posixFormatted();
                }
            }
        }

        if ($tableName === 'sys_file_metadata') {
            /** @var FileMetadata $metadata */
            $metadata = $this->fileMetadataRepository->findByUid($recordId);

            $file = $metadata->getFile()->getOriginalResource();
            if (!in_array($file->getExtension(), ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                return new JsonResponse([
                    'text' => '',
                ]);
            }

            $altText = $this->openAiClient->buildAltText($file->getContents(), $locale);

            return new JsonResponse([
                'text' => $altText,
            ]);
        }

        throw new \InvalidArgumentException(
            "Unexpected record from table \"{$tableName}\"",
            1722538736
        );
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function checkRequest(ServerRequestInterface $request): bool
    {
        $queryParameters = $request->getParsedBody() ?? [];
        $expectedHash = GeneralUtility::hmac(
            implode(
                '',
                [
                    $queryParameters['tableName'],
                    $queryParameters['pageId'],
                    $queryParameters['recordId'],
                    $queryParameters['language'],
                    $queryParameters['fieldName'],
                    $queryParameters['command'],
                    $queryParameters['parentPageId'],
                ]
            ),
            self::class
        );
        if (!hash_equals($expectedHash, $queryParameters['signature'])) {
            throw new \InvalidArgumentException(
                'HMAC could not be verified',
                1535137045
            );
        }

        return true;
    }
}
