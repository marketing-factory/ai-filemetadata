<?php

namespace Mfd\Ai\FileMetadata\Services;

use Mfd\Ai\FileMetadata\Api\OpenAiClient;
use Mfd\Ai\FileMetadata\Event\ModifyUpdateArrayEvent;
use Mfd\Ai\FileMetadata\Sites\SiteLanguageProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\FileProcessingAspect;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\MetaDataAspect;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Search\FileSearchDemand;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class FalAdapter
{
    private array $siteLanguageMapping = [];

    public function __construct(
        private readonly OpenAiClient $openAiClient,
        private readonly ImageService $imageService,
        private readonly ConfigurationService $configurationService,
        private readonly SiteLanguageProvider $languageProvider,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcher $eventDispatcher
    )
    {}

    public function iterate(
        Folder $folder,
        bool $overwriteMetadata,
        ?int $limit = null,
        ?OutputInterface  $output = null
    )
    {
        if (!$output) {
            $output = new NullOutput();
        }

        $this->siteLanguageMapping = $this->languageProvider->getFalLanguages();

        $fileSearch = FileSearchDemand::create()
            // Sure, native support for empty searches or at least recursive iterators would be better than this
            ->withSearchTerm(' ')
            ->withRecursive();

        $files = $folder->searchFiles($fileSearch);

        $filteredFiles = [];
        foreach ($files as $file) {
            $meta = $file->getMetaData()->get();
            $identifier = $file->getIdentifier();
            if (strpos($identifier, '/_recycler_/') !== false) {
                $this->logger->debug('Skipped due deleted file in recycler');
                continue;
            }

            if (!in_array($file->getExtension(), ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                $this->logger->debug('Skipped due to wrong file extension');
                continue;
            }

            if (!in_array($file->getMimeType(), [
                'image/png',
                'image/jpeg',
                'image/gif',
                'image/webp',
            ])) {
                $this->logger->debug('Skipped due to wrong mime-type');
                continue;
            }

            if ($this->configurationService->shouldBeExcluded($file)) {
                $this->logger->debug('Skipped due to exclude pattern');
                continue;
            }
            if (!$overwriteMetadata && intval($meta['alttext_generation_date']) > 0) {
                $this->logger->debug('Skipped due already generated alt text');
                continue;
            }
            if (!$overwriteMetadata && (isset($meta['alternative']) && trim($meta['alternative']) !== '')) {
                $this->logger->debug('Skipped due already existing (manual?) alt text');
                continue;
            }

            $filteredFiles[] = $file;
            if ($limit !== null && count($filteredFiles) >= $limit) {
                break;
            }
        }

        $progress = new ProgressBar($output);
        $progress->setFormat('with_message');
        $progress->setMessage('');
        $progress->setRedrawFrequency(25);
        $processedCount = 0;
        foreach ($progress->iterate($filteredFiles) as $file) {
            $progress->setMessage($file->getIdentifier());
            $this->localizeFile($file, $overwriteMetadata, 'cli');
            $processedCount++;
        }
        if ($output) {
            $output->writeln('');
            $output->writeln(sprintf('Summary: %d file(s) processed.', $processedCount));
        }
    }

    private function getLanguageMappingForFile(File $file): array
    {
        if ($this->siteLanguageMapping === []) {
            $this->siteLanguageMapping = $this->languageProvider->getFalLanguages();
        }

        return $this->configurationService->getLanguageMappingForFile($file) ?? $this->siteLanguageMapping;
    }

    public function localizeFile(File $file, bool $overwriteMetadata, string $context = 'batch')
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);

        $originalMetadata = $file->getMetaData()->get();
        if (isset($originalMetadata['uid'])) {
            $metadataUid = [
                0 => $originalMetadata['uid'],
            ];
        } else {
            $metadataAspect = GeneralUtility::makeInstance(MetadataAspect::class, $file);
            $originalMetadata = $metadataAspect->get();
            $metadataUid = [
                0 => $originalMetadata['uid'],
            ];
        }

        $falLanguages = $this->getLanguageMappingForFile($file);
        $this->logger->debug(
            'Localize {file} with language mapping {languages}',
            [
                'file' => $file->getIdentifier(),
                'languages' => $falLanguages,
            ]
        );

        foreach ($falLanguages as $sysLanguageUid => $locale) {
            if ($sysLanguageUid === 0) {
                continue;
            }

            $translatedRecords = BackendUtility::getRecordLocalization(
                'sys_file_metadata',
                $metadataUid[0],
                $sysLanguageUid,
            );

            if (!isset($translatedRecords[0])) {
                $metadataUid[$sysLanguageUid] = StringUtility::getUniqueId('NEW');
                continue;
            }

            if (!$overwriteMetadata && !empty(trim($translatedRecords[0]['alternative'] ?? ''))) {
                continue;
            }

            $metadataUid[$sysLanguageUid] = $translatedRecords[0]['uid'];
        }

        $metadata = [];
        foreach (array_keys($metadataUid) as $sysLanguageUid) {
            if ($sysLanguageUid === 0) {
                if (!$overwriteMetadata && !empty(trim($originalMetadata['alternative'] ?? ''))) {
                    continue;
                }
            }

            if (!$file->exists()) {
                continue;
            }
            $altText = $this->openAiClient->buildAltText(
                $this->resizeImage($file)->getContents(),
                $falLanguages[$sysLanguageUid],
                $context,
                $file->getUid(),
            );
            $metadata[$metadataUid[$sysLanguageUid]] = [
                'pid' => $originalMetadata['pid'],
                'sys_language_uid' => $sysLanguageUid,
                'l10n_parent' => $sysLanguageUid === 0 ? 0 : $metadataUid[0],
                'file' => $originalMetadata['file'],
                'alternative' => $altText,
                'alttext_generation_date' => time(),
            ];

            /** @var ModifyUpdateArrayEvent $event */
            $event = $this->eventDispatcher->dispatch(
                new ModifyUpdateArrayEvent($metadata[$metadataUid[$sysLanguageUid]], $originalMetadata)
            );
            $metadata[$metadataUid[$sysLanguageUid]] = $event->getMetadata();

        }

        $cmd = [];
        $data = [
            'sys_file_metadata' => $metadata,
        ];

        $dataHandler->bypassAccessCheckForRecords = true;
        $dataHandler->BE_USER = $GLOBALS['BE_USER'];
        if (version_compare(VersionNumberUtility::getCurrentTypo3Version(), '14.0', '<')) {
            $dataHandler->userid = $GLOBALS['BE_USER']->user['uid'];
        }
        // For Version below 13 use old way to initialize Admin User
        if (version_compare(VersionNumberUtility::getCurrentTypo3Version(), '13.0', '<')) {
            $dataHandler->admin = true;
            $dataHandler->BE_USER->user['admin'] = 1;
        }
        $dataHandler->start($data, $cmd);
        $dataHandler->process_datamap();
        if ($dataHandler->errorLog !== []) {
            DebuggerUtility::var_dump($dataHandler->errorLog);
            throw new \RuntimeException('Error while mass updating file metadata');
        }
    }

    public function resizeImage(File $file): File|ProcessedFile
    {
        $maxDimension = $this->configurationService->getImageResizing();
        if ($maxDimension <= 0
            || !GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], $file->getExtension())
        ) {
            return $file;
        }

        if ($file->getProperty('width') <= $maxDimension && $file->getProperty('height') <= $maxDimension) {
            return $file;
        }

        $instructions = [
            'maxWidth' => $maxDimension,
            'maxHeight' => $maxDimension,
        ];

        // Backend requests defer image processing: DeferredBackendImageProcessor only persists a
        // sys_file_processedfile row and defers writing the actual file to a later HTTP request.
        // Reading such a derivative server-side therefore hits a row without a file and fails in
        // LocalDriver::getPermissions() with "fileperms(): stat failed". Disabling the aspect makes
        // the local processors generate the file synchronously in this request.
        $context = GeneralUtility::makeInstance(Context::class);
        $previousAspect = $context->hasAspect('fileProcessing')
            ? $context->getAspect('fileProcessing')
            : null;

        $context->setAspect('fileProcessing', new FileProcessingAspect(false));
        try {
            $processedFile = $this->imageService->applyProcessingInstructions($file, $instructions);

            // A row may exist from an earlier deferred run while its file was never written, in
            // which case TYPO3 considers the derivative up to date. Drop the stale row and rebuild
            // instead of silently falling back to the unresized original.
            if ($processedFile instanceof ProcessedFile && !$processedFile->exists()) {
                $processedFile->delete(true);
                $processedFile = $this->imageService->applyProcessingInstructions($file, $instructions);
            }

            if (!$processedFile->exists()) {
                $this->logger->warning(
                    'Could not produce a resized derivative, falling back to the original file {file}',
                    ['file' => $file->getIdentifier()]
                );
                return $file;
            }

            return $processedFile;
        } catch (\Exception $e) {
            $this->logger->debug('Image resizing failed. Unsupported file type?');
        } finally {
            if ($previousAspect !== null) {
                $context->setAspect('fileProcessing', $previousAspect);
            } else {
                $context->unsetAspect('fileProcessing');
            }
        }

        return $file;
    }
}
