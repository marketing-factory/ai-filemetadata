<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Backend\Controller;

use Doctrine\DBAL\Exception as DatabaseException;
use Mfd\Ai\FileMetadata\Backend\GeneratedAltTextQuery;
use Mfd\Ai\FileMetadata\Services\FalFileEligibility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\Exception as ResourceException;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
final class AiAlternativeTextsController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const ITEMS_PER_PAGE = 50;
    private const REVIEW_FORM_NAME = 'ai_filemetadata';
    private const REVIEW_FORM_ACTION = 'review';
    private const EDIT_FORM_ACTION = 'edit';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ResourceFactory $resourceFactory,
        private readonly GeneratedAltTextQuery $generatedAltTextQuery,
        private readonly SiteFinder $siteFinder,
        private readonly FalFileEligibility $falFileEligibility,
        private readonly UriBuilder $uriBuilder,
        private readonly FormProtectionFactory $formProtectionFactory,
        private readonly FlashMessageService $flashMessageService,
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $queryParameters = $request->getQueryParams();
        $selectedFolderIdentifier = (string)($queryParameters['id'] ?? '');
        $selectedFolder = $this->resolveSelectedFolder($selectedFolderIdentifier);
        $status = $this->resolveStatus($queryParameters['filter'] ?? null);
        $requestedPage = max(1, (int)($queryParameters['page'] ?? 1));
        $scopeIsEligible = $selectedFolderIdentifier === '' || (
            $selectedFolder !== null
            && !$this->falFileEligibility->isExcludedIdentifier(
                $selectedFolder->getStorage()->getUid(),
                $selectedFolder->getIdentifier(),
            )
        );
        $scopeFolders = $selectedFolder !== null ? [$selectedFolder] : $this->getGlobalScopeFolders();
        $moduleTemplate->assignMultiple([
            'dateFormat' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?? 'Y-m-d',
            'timeFormat' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] ?? 'H:i',
            'selectedFolder' => $selectedFolder,
            'selectedFolderIdentifier' => $selectedFolderIdentifier,
            'isGlobalScope' => $selectedFolderIdentifier === '',
            'scopeIsEligible' => $scopeIsEligible,
            'status' => $status,
            'isGeneratedStatus' => $status === GeneratedAltTextQuery::STATUS_GENERATED,
            'isMissingStatus' => $status === GeneratedAltTextQuery::STATUS_MISSING,
            'isNeedsReviewStatus' => $status === GeneratedAltTextQuery::STATUS_NEEDS_REVIEW,
            'requestedPage' => $requestedPage,
            'records' => [],
            'pagination' => null,
            'hasError' => false,
        ]);

        if (!$scopeIsEligible) {
            return $moduleTemplate->renderResponse('Backend/AiAlternativeTexts');
        }

        try {
            $totalItems = $this->generatedAltTextQuery->count($scopeFolders, $status);
            $totalPages = max(1, (int)ceil($totalItems / self::ITEMS_PER_PAGE));
            $currentPage = min($requestedPage, $totalPages);
            $rows = $this->generatedAltTextQuery->find(
                $scopeFolders,
                $status,
                ($currentPage - 1) * self::ITEMS_PER_PAGE,
                self::ITEMS_PER_PAGE,
            );
        } catch (DatabaseException $exception) {
            $this->logger?->error('Unable to load AI-generated alternative texts.', ['exception' => $exception]);
            $moduleTemplate->assign('hasError', true);

            return $moduleTemplate->renderResponse('Backend/AiAlternativeTexts');
        }

        $moduleTemplate->assignMultiple([
            'records' => $this->prepareRecords($rows, $scopeFolders, $request),
            'pagination' => [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'previousPage' => $currentPage > 1 ? $currentPage - 1 : null,
                'nextPage' => $currentPage < $totalPages ? $currentPage + 1 : null,
            ],
        ]);

        return $moduleTemplate->renderResponse('Backend/AiAlternativeTexts');
    }

    public function reviewAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $parsedBody = is_array($parsedBody) ? $parsedBody : [];
        $metadataUid = is_scalar($parsedBody['metadata'] ?? null) ? (int)$parsedBody['metadata'] : 0;
        $selectedFolderIdentifier = is_string($parsedBody['id'] ?? null) ? $parsedBody['id'] : '';
        $status = $this->resolveStatus($parsedBody['filter'] ?? null);
        $page = max(1, is_scalar($parsedBody['page'] ?? null) ? (int)$parsedBody['page'] : 1);
        $redirectUri = $this->uriBuilder->buildUriFromRoute('ai_filemetadata', [
            'id' => $selectedFolderIdentifier,
            'filter' => $status,
            'page' => $page,
        ]);

        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $formToken = is_string($parsedBody['formToken'] ?? null) ? $parsedBody['formToken'] : '';
        if ($metadataUid < 1 || !$formProtection->validateToken(
            $formToken,
            self::REVIEW_FORM_NAME,
            self::REVIEW_FORM_ACTION,
            (string)$metadataUid,
        )) {
            return new RedirectResponse($redirectUri, 303);
        }

        try {
            if (!$this->markMetadataReviewedIfAllowed($metadataUid, $selectedFolderIdentifier)) {
                $this->addFlashMessage('module.review.error', ContextualFeedbackSeverity::ERROR);

                return new RedirectResponse($redirectUri, 303);
            }
        } catch (DatabaseException|ResourceException|\InvalidArgumentException $exception) {
            $this->logger?->error('Unable to mark alternative text as reviewed.', ['exception' => $exception]);
            $this->addFlashMessage('module.review.error', ContextualFeedbackSeverity::ERROR);

            return new RedirectResponse($redirectUri, 303);
        }

        $this->addFlashMessage('module.review.success', ContextualFeedbackSeverity::OK);

        return new RedirectResponse($redirectUri, 303);
    }

    public function editAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $parsedBody = is_array($parsedBody) ? $parsedBody : [];
        $metadataUid = is_scalar($parsedBody['metadata'] ?? null) ? (int)$parsedBody['metadata'] : 0;
        $alternative = is_string($parsedBody['alternative'] ?? null) ? $parsedBody['alternative'] : null;
        $selectedFolderIdentifier = is_string($parsedBody['id'] ?? null) ? $parsedBody['id'] : '';
        $status = $this->resolveStatus($parsedBody['filter'] ?? null);
        $page = max(1, is_scalar($parsedBody['page'] ?? null) ? (int)$parsedBody['page'] : 1);
        $redirectUri = $this->uriBuilder->buildUriFromRoute('ai_filemetadata', [
            'id' => $selectedFolderIdentifier,
            'filter' => $status,
            'page' => $page,
        ]);

        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $formToken = is_string($parsedBody['formToken'] ?? null) ? $parsedBody['formToken'] : '';
        if ($metadataUid < 1 || $alternative === null || !$formProtection->validateToken(
            $formToken,
            self::REVIEW_FORM_NAME,
            self::EDIT_FORM_ACTION,
            (string)$metadataUid,
        )) {
            return new RedirectResponse($redirectUri, 303);
        }

        try {
            if (!$this->updateAlternativeIfAllowed($metadataUid, $alternative, $selectedFolderIdentifier)) {
                $this->addFlashMessage('module.edit.error', ContextualFeedbackSeverity::ERROR);

                return new RedirectResponse($redirectUri, 303);
            }
        } catch (DatabaseException|ResourceException|\InvalidArgumentException $exception) {
            $this->logger?->error('Unable to update alternative text.', ['exception' => $exception]);
            $this->addFlashMessage('module.edit.error', ContextualFeedbackSeverity::ERROR);

            return new RedirectResponse($redirectUri, 303);
        }

        $this->addFlashMessage('module.edit.success', ContextualFeedbackSeverity::OK);

        return new RedirectResponse($redirectUri, 303);
    }

    private function markMetadataReviewedIfAllowed(int $metadataUid, string $selectedFolderIdentifier): bool
    {
        $metadata = $this->generatedAltTextQuery->findMetadata($metadataUid);
        if ($metadata === null || (int)$metadata['alttext_generation_date'] <= 0) {
            return false;
        }

        $file = $this->resourceFactory->getFileObject((int)$metadata['file']);
        if ($selectedFolderIdentifier === '') {
            $scopeFolders = $this->getGlobalScopeFolders();
        } else {
            $selectedFolder = $this->resolveSelectedFolder($selectedFolderIdentifier);
            if ($selectedFolder === null || $this->falFileEligibility->isExcludedIdentifier(
                $selectedFolder->getStorage()->getUid(),
                $selectedFolder->getIdentifier(),
            )) {
                return false;
            }
            $scopeFolders = [$selectedFolder];
        }

        $allowedStorage = null;
        foreach ($scopeFolders as $scopeFolder) {
            if ($scopeFolder->getStorage()->getUid() === $file->getStorage()->getUid()
                && $scopeFolder->getStorage()->isWithinFolder($scopeFolder, $file)
            ) {
                $allowedStorage = $scopeFolder->getStorage();
                break;
            }
        }

        $backendUser = $this->getBackendUser();
        if ($allowedStorage === null
            || $file->isMissing()
            || !$allowedStorage->checkFileActionPermission('read', $file)
            || !$allowedStorage->checkFileActionPermission('editMeta', $file)
            || !$backendUser->check('tables_modify', 'sys_file_metadata')
            || !$backendUser->checkLanguageAccess((int)$metadata['sys_language_uid'])
            || !$this->falFileEligibility->isEligible($file)
        ) {
            return false;
        }

        $this->generatedAltTextQuery->markReviewed($metadataUid, $file->getUid());

        return true;
    }

    private function updateAlternativeIfAllowed(
        int $metadataUid,
        string $alternative,
        string $selectedFolderIdentifier,
    ): bool {
        $metadata = $this->generatedAltTextQuery->findMetadata($metadataUid);
        if ($metadata === null) {
            return false;
        }

        $file = $this->resourceFactory->getFileObject((int)$metadata['file']);
        if ($selectedFolderIdentifier === '') {
            $scopeFolders = $this->getGlobalScopeFolders();
        } else {
            $selectedFolder = $this->resolveSelectedFolder($selectedFolderIdentifier);
            if ($selectedFolder === null || $this->falFileEligibility->isExcludedIdentifier(
                $selectedFolder->getStorage()->getUid(),
                $selectedFolder->getIdentifier(),
            )) {
                return false;
            }
            $scopeFolders = [$selectedFolder];
        }

        $allowedStorage = null;
        foreach ($scopeFolders as $scopeFolder) {
            if ($scopeFolder->getStorage()->getUid() === $file->getStorage()->getUid()
                && $scopeFolder->getStorage()->isWithinFolder($scopeFolder, $file)
            ) {
                $allowedStorage = $scopeFolder->getStorage();
                break;
            }
        }

        $backendUser = $this->getBackendUser();
        if ($allowedStorage === null
            || $file->isMissing()
            || !$allowedStorage->checkFileActionPermission('read', $file)
            || !$allowedStorage->checkFileActionPermission('editMeta', $file)
            || !$backendUser->check('tables_modify', 'sys_file_metadata')
            || !$backendUser->checkLanguageAccess((int)$metadata['sys_language_uid'])
            || !$this->falFileEligibility->isEligible($file)
        ) {
            return false;
        }

        $alternative = trim($alternative);
        if ($alternative !== trim((string)$metadata['alternative'])) {
            $this->generatedAltTextQuery->updateAlternative(
                $metadataUid,
                $file->getUid(),
                $alternative,
                (int)$metadata['alttext_generation_date'] > 0 && $alternative !== '',
            );
        }

        return true;
    }

    private function resolveStatus(mixed $status): string
    {
        return in_array($status, [
            GeneratedAltTextQuery::STATUS_GENERATED,
            GeneratedAltTextQuery::STATUS_MISSING,
            GeneratedAltTextQuery::STATUS_NEEDS_REVIEW,
        ], true) ? (string)$status : GeneratedAltTextQuery::STATUS_GENERATED;
    }

    private function addFlashMessage(string $label, ContextualFeedbackSeverity $severity): void
    {
        $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(
            new FlashMessage(
                $this->getLanguageService()->sL(
                    'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:' . $label,
                ),
                '',
                $severity,
                true,
            ),
        );
    }

    /**
     * @return list<Folder>
     */
    private function getGlobalScopeFolders(): array
    {
        $scopeFolders = [];
        $backendUser = $this->getBackendUser();
        $backendUser->evaluateUserSpecificFileFilterSettings();
        foreach ($backendUser->getFileStorages() as $storage) {
            if (!$storage->checkUserActionPermission('read', 'File')) {
                continue;
            }
            $fileMounts = $storage->getFileMounts();
            if ($fileMounts === []) {
                $fileMounts = [['folder' => $storage->getRootLevelFolder()]];
            }
            foreach ($fileMounts as $fileMount) {
                $folder = $fileMount['folder'] ?? null;
                if (!$folder instanceof Folder
                    || !$storage->checkFolderActionPermission('read', $folder)
                    || $this->falFileEligibility->isExcludedIdentifier(
                        $storage->getUid(),
                        $folder->getIdentifier(),
                    )
                ) {
                    continue;
                }
                $scopeFolders[$folder->getCombinedIdentifier()] = $folder;
            }
        }

        return array_values($scopeFolders);
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
     * @param list<Folder> $scopeFolders
     * @return array<int, array{metadataUid: int, file: File, alternative: string, language: string, generationDate: int, reviewed: bool, canEdit: bool, editToken: string, canReview: bool, reviewToken: string}>
     */
    private function prepareRecords(array $rows, array $scopeFolders, ServerRequestInterface $request): array
    {
        $records = [];
        $languageLabels = $this->getLanguageLabels();
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $backendUser = $this->getBackendUser();
        $allowedStorages = [];
        foreach ($scopeFolders as $scopeFolder) {
            $allowedStorages[$scopeFolder->getStorage()->getUid()] = $scopeFolder->getStorage();
        }
        foreach ($rows as $row) {
            try {
                $file = $this->resourceFactory->getFileObject((int)$row['file']);
            } catch (FileDoesNotExistException|\InvalidArgumentException) {
                continue;
            }
            $allowedStorage = $allowedStorages[$file->getStorage()->getUid()] ?? null;
            if ($allowedStorage === null
                || !$allowedStorage->checkFileActionPermission('read', $file)
                || !$this->falFileEligibility->isEligible($file)
            ) {
                continue;
            }
            $languageId = (int)$row['sys_language_uid'];
            $metadataUid = (int)$row['uid'];
            $generationDate = (int)$row['alttext_generation_date'];
            $reviewed = (bool)$row['alttext_reviewed'];
            $canEdit = $allowedStorage->checkFileActionPermission('editMeta', $file)
                && $backendUser->check('tables_modify', 'sys_file_metadata')
                && $backendUser->checkLanguageAccess($languageId);
            $canReview = $generationDate > 0 && $canEdit && !$reviewed;
            $records[] = [
                'metadataUid' => $metadataUid,
                'file' => $file,
                'alternative' => (string)$row['alternative'],
                'language' => $this->getLanguageLabel($languageId, $languageLabels),
                'generationDate' => $generationDate,
                'reviewed' => $reviewed,
                'canEdit' => $canEdit,
                'editToken' => $canEdit ? $formProtection->generateToken(
                    self::REVIEW_FORM_NAME,
                    self::EDIT_FORM_ACTION,
                    (string)$metadataUid,
                ) : '',
                'canReview' => $canReview,
                'reviewToken' => $canReview ? $formProtection->generateToken(
                    self::REVIEW_FORM_NAME,
                    self::REVIEW_FORM_ACTION,
                    (string)$metadataUid,
                ) : '',
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
