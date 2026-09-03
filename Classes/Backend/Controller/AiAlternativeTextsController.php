<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Backend\Controller;

use Doctrine\DBAL\Exception as DatabaseException;
use Mfd\Ai\FileMetadata\Backend\GeneratedAltTextQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Resource\Exception as ResourceException;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Site\SiteFinder;

#[AsController]
final class AiAlternativeTextsController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const ITEMS_PER_PAGE = 50;

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ResourceFactory $resourceFactory,
        private readonly GeneratedAltTextQuery $generatedAltTextQuery,
        private readonly SiteFinder $siteFinder,
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $queryParameters = $request->getQueryParams();
        $selectedFolderIdentifier = (string)($queryParameters['id'] ?? '');
        $selectedFolder = $this->resolveSelectedFolder($selectedFolderIdentifier);
        $moduleTemplate->assignMultiple([
            'dateFormat' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?? 'Y-m-d',
            'timeFormat' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] ?? 'H:i',
            'selectedFolder' => $selectedFolder,
            'selectedFolderIdentifier' => $selectedFolderIdentifier,
            'records' => [],
            'pagination' => null,
            'hasError' => false,
        ]);

        if ($selectedFolder === null) {
            return $moduleTemplate->renderResponse('Backend/AiAlternativeTexts');
        }

        try {
            $totalItems = $this->generatedAltTextQuery->countByFolder($selectedFolder);
            $totalPages = max(1, (int)ceil($totalItems / self::ITEMS_PER_PAGE));
            $currentPage = min(max(1, (int)($queryParameters['page'] ?? 1)), $totalPages);
            $rows = $this->generatedAltTextQuery->findByFolder(
                $selectedFolder,
                ($currentPage - 1) * self::ITEMS_PER_PAGE,
                self::ITEMS_PER_PAGE,
            );
        } catch (DatabaseException $exception) {
            $this->logger?->error('Unable to load AI-generated alternative texts.', ['exception' => $exception]);
            $moduleTemplate->assign('hasError', true);

            return $moduleTemplate->renderResponse('Backend/AiAlternativeTexts');
        }

        $moduleTemplate->assignMultiple([
            'records' => $this->prepareRecords($rows, $selectedFolder),
            'pagination' => [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'previousPage' => $currentPage > 1 ? $currentPage - 1 : null,
                'nextPage' => $currentPage < $totalPages ? $currentPage + 1 : null,
            ],
        ]);

        return $moduleTemplate->renderResponse('Backend/AiAlternativeTexts');
    }

    private function resolveSelectedFolder(string $combinedIdentifier): ?Folder
    {
        if ($combinedIdentifier === '') {
            return null;
        }

        $backendUser = $this->getBackendUser();
        $backendUser->evaluateUserSpecificFileFilterSettings();

        try {
            $resolvedFolder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
            foreach ($backendUser->getFileStorages() as $storage) {
                if ($storage->getUid() !== $resolvedFolder->getStorage()->getUid()) {
                    continue;
                }
                $folder = $storage->getFolder($resolvedFolder->getIdentifier());
                if ($storage->checkFolderActionPermission('read', $folder)
                    && $storage->checkUserActionPermission('read', 'File')
                ) {
                    return $folder;
                }
            }
        } catch (ResourceException|\InvalidArgumentException) {
            return null;
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{file: File, alternative: string, language: string, generationDate: int}>
     */
    private function prepareRecords(array $rows, Folder $selectedFolder): array
    {
        $records = [];
        $languageLabels = $this->getLanguageLabels();
        foreach ($rows as $row) {
            try {
                $file = $this->resourceFactory->getFileObject((int)$row['file']);
            } catch (FileDoesNotExistException|\InvalidArgumentException) {
                continue;
            }
            if ($file->getStorage()->getUid() !== $selectedFolder->getStorage()->getUid()
                || !$selectedFolder->getStorage()->checkFileActionPermission('read', $file)
            ) {
                continue;
            }
            $languageId = (int)$row['sys_language_uid'];
            $records[] = [
                'file' => $file,
                'alternative' => (string)$row['alternative'],
                'language' => $this->getLanguageLabel($languageId, $languageLabels),
                'generationDate' => (int)$row['alttext_generation_date'],
            ];
        }

        return $records;
    }

    /**
     * @return array<int, string>
     */
    private function getLanguageLabels(): array
    {
        $languageLabels = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getAllLanguages() as $siteLanguage) {
                $languageLabels[$siteLanguage->getLanguageId()] ??= $siteLanguage->getTitle();
            }
        }

        return $languageLabels;
    }

    /**
     * @param array<int, string> $languageLabels
     */
    private function getLanguageLabel(int $languageId, array $languageLabels): string
    {
        if (isset($languageLabels[$languageId])) {
            return $languageLabels[$languageId];
        }
        if ($languageId === -1) {
            return $this->getLanguageService()->sL(
                'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:module.language.all',
            );
        }
        if ($languageId === 0) {
            return $this->getLanguageService()->sL(
                'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:module.language.default',
            );
        }

        return sprintf(
            $this->getLanguageService()->sL(
                'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:module.language.unknown',
            ),
            $languageId,
        );
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
