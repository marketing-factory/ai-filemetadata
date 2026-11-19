<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Hooks;

use Mfd\Ai\FileMetadata\Cache\AltTextSuggestionCache;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Keeps sys_file_metadata.alttext_generation_date honest for every write that goes through the DataHandler:
 *
 * - FalAdapter::localizeFile() always sets "alternative" and "alttext_generation_date" together in the same
 *   field array - that write is left untouched, it's the AI generation itself.
 * - The upload autogeneration (EnrichFileMetadataAfterCreation) bypasses the DataHandler entirely via a direct
 *   query, so it never reaches this hook either.
 * - Any other write that changes "alternative" without also carrying "alttext_generation_date" is either a
 *   manual edit in the backend form, or the AI-suggested text from the "recreate" button that was edited
 *   before saving. Both cases must clear the generation date - unless the saved text is byte-for-byte the
 *   suggestion that was just generated, in which case it still counts as AI-generated.
 */
class ResetAltTextGenerationDateHook
{
    private const TABLE = 'sys_file_metadata';

    public function __construct(private readonly AltTextSuggestionCache $suggestionCache)
    {
    }

    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, string $table, string $id, DataHandler $parentObject): void
    {
        // CLI writes (e.g. GenerateAltTextsCommand via FalAdapter) are trusted AI/import contexts -
        // let them through untouched instead of running the manual-edit detection below.
        if (Environment::isCli()) {
            return;
        }

        // Frontend-triggered uploads/edits (e.g. via a form) are intentionally out of scope here -
        // anyone needing this reset behavior for FE writes has to implement it themselves.
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof ServerRequestInterface && ApplicationType::fromRequest($request)->isFrontend()) {
            return;
        }

        if ($table !== self::TABLE) {
            return;
        }

        if (!array_key_exists('alternative', $incomingFieldArray)) {
            return;
        }

        // Already an all-in-one AI write (FalAdapter): don't interfere.
        if (array_key_exists('alttext_generation_date', $incomingFieldArray)) {
            return;
        }

        // New records can't have an AI-generated date to invalidate yet.
        if (!MathUtility::canBeInterpretedAsInteger($id)) {
            return;
        }
        $id = intval($id);

        $currentRecord = BackendUtility::getRecord(self::TABLE, $id, 'alternative,alttext_generation_date');
        if ($currentRecord === null) {
            return;
        }

        $incomingAlternative = trim((string)$incomingFieldArray['alternative']);

        // FormEngine resubmits unchanged fields on every save - only react if the text actually changed.
        if ($incomingAlternative === trim((string)$currentRecord['alternative'])) {
            return;
        }

        $backendUserUid = (int)($parentObject->BE_USER->user['uid'] ?? 0);
        $suggestion = $this->suggestionCache->get($id, $backendUserUid);
        $this->suggestionCache->forget($id, $backendUserUid);

        if ($suggestion !== null && $incomingAlternative === trim($suggestion)) {
            // The AI suggestion was saved unmodified.
            $incomingFieldArray['alttext_generation_date'] = time();
            return;
        }

        // Typed by hand, or the AI suggestion was edited before saving.
        if ((int)$currentRecord['alttext_generation_date'] !== 0) {
            $incomingFieldArray['alttext_generation_date'] = 0;
        }
    }
}
