<?php

namespace Mfd\Ai\FileMetadata\Form\Element;

use Mfd\Ai\FileMetadata\Backend\Controller\AiGeneratedAltTextAjaxController;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Form\Element\InputTextElement;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class AiGeneratedAltTextElement extends InputTextElement
{
    public function render(): array
    {
        $row = $this->data['databaseRow'];
        $table = $this->data['tableName'];
        $fieldName = $this->data['fieldName'];
        $parameterArray = $this->data['parameterArray'];
        $resultArray = $this->initializeResultArray();
        // @deprecated since v12, will be removed with v13 when all elements handle label/legend on their own
        $resultArray['labelHasBeenHandled'] = true;
        $config = $parameterArray['fieldConf']['config'];

        // Stay compatible with both TYPO3 12 and 13 (fixes #16)
        $iconFactory = property_exists($this, 'iconFactory') ? $this->iconFactory : GeneralUtility::makeInstance(IconFactory::class);

        $languageId = 0;
        if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField']) && !empty($GLOBALS['TCA'][$table]['ctrl']['languageField'])) {
            $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
            $languageId = (int)((is_array($row[$languageField] ?? null) ? ($row[$languageField][0] ?? 0) : $row[$languageField]) ?? 0);
        }

        $itemValue = $parameterArray['itemFormElValue'];
        $width = $this->formMaxWidth(
            MathUtility::forceIntegerInRange($config['size'] ?? $this->defaultInputWidth, $this->minimumInputWidth, $this->maxInputWidth)
        );
        $fieldId = StringUtility::getUniqueId('formengine-input-');
        $itemName = (string)$parameterArray['itemFormElName'];
        $renderedLabel = $this->renderLabel($fieldId);

        $fieldInformationResult = $this->renderFieldInformation();
        $fieldInformationHtml = $fieldInformationResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        if ($config['readOnly'] ?? false) {
            $html = [];
            $html[] = $renderedLabel;
            $html[] = '<div class="formengine-field-item t3js-formengine-field-item">';
            $html[] =   $fieldInformationHtml;
            $html[] =   '<div class="form-wizards-wrap">';
            $html[] =       '<div class="form-wizards-element">';
            $html[] =           '<div class="form-control-wrap" style="max-width: ' . $width . 'px">';
            $html[] =               '<input class="form-control" id="' . htmlspecialchars($fieldId) . '" name="' . htmlspecialchars($itemName) . '" value="' . htmlspecialchars((string)$itemValue) . '" type="text" disabled>';
            $html[] =           '</div>';
            $html[] =       '</div>';
            $html[] =   '</div>';
            $html[] = '</div>';
            $resultArray['html'] = implode(LF, $html);
            return $resultArray;
        }

        $languageService = $this->getLanguageService();

        // @todo: The whole eval handling is a mess and needs refactoring
        $evalList = GeneralUtility::trimExplode(',', $config['eval'] ?? '', true);
        foreach ($evalList as $func) {
            // @todo: This is ugly: The code should find out on it's own whether an eval definition is a
            // @todo: keyword like "date", or a class reference. The global registration could be dropped then
            // Pair hook to the one in \TYPO3\CMS\Core\DataHandling\DataHandler::checkValue_input_Eval()
            if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals'][$func]) && class_exists($func)) {
                $evalObj = GeneralUtility::makeInstance($func);
                if (method_exists($evalObj, 'deevaluateFieldValue')) {
                    $_params = [
                        'value' => $itemValue,
                    ];
                    $itemValue = $evalObj->deevaluateFieldValue($_params);
                }

                $resultArray = $this->resolveJavaScriptEvaluation($resultArray, $func, $evalObj);
            }
        }

        if ($config['nullable'] ?? false) {
            $evalList[] = 'null';
        }

        $formEngineInputParams = [
            'field' => $itemName,
        ];
        // The `is_in` constraint requires two parameters to work: the "eval" setting and a configuration of the
        // actually allowed characters
        if (in_array('is_in', $evalList, true)) {
            if (($config['is_in'] ?? '') !== '') {
                $formEngineInputParams['is_in'] = $config['is_in'];
            } else {
                $evalList = array_diff($evalList, ['is_in']);
            }
        } else {
            unset($config['is_in']);
        }

        if ($evalList !== []) {
            $formEngineInputParams['evalList'] = implode(',', $evalList);
        }

        $attributes = [
            'value' => '',
            'id' => $fieldId,
            'class' => implode(' ', [
                'form-control',
                't3js-form-field-alt-text-input',
            ]),
            'rows' => 5,
            'cols' => 40,
            'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($config),
            'data-formengine-input-params' => (string)json_encode($formEngineInputParams, JSON_THROW_ON_ERROR),
            'data-formengine-input-name' => $itemName,
        ];

        $maxLength = (int)($config['max'] ?? 0);
        if ($maxLength > 0) {
            $attributes['maxlength'] = (string)$maxLength;
        }

        $minLength = (int)($config['min'] ?? 0);
        if ($minLength > 0 && ($maxLength === 0 || $minLength <= $maxLength)) {
            $attributes['minlength'] = (string)$minLength;
        }

        if (!empty($config['placeholder'])) {
            $attributes['placeholder'] = trim((string) $config['placeholder']);
        }

        if (isset($config['autocomplete'])) {
            $attributes['autocomplete'] = empty($config['autocomplete']) ? 'new-' . $fieldName : 'on';
        }

        $valuePickerHtml = [];
        if (is_array($config['valuePicker']['items'] ?? false)) {
            $valuePickerConfiguration = [
                'mode' => $config['valuePicker']['mode'] ?? 'replace',
                'linked-field' => '[data-formengine-input-name="' . $itemName . '"]',
            ];
            $valuePickerAttributes = array_merge(
                [
                    'class' => 'form-select form-control-adapt',
                ],
                $this->getOnFieldChangeAttrs('change', $parameterArray['fieldChangeFunc'] ?? [])
            );

            $valuePickerHtml[] = '<typo3-formengine-valuepicker ' . GeneralUtility::implodeAttributes($valuePickerConfiguration, true) . '>';
            $valuePickerHtml[] = '<select ' . GeneralUtility::implodeAttributes($valuePickerAttributes, true) . '>';
            $valuePickerHtml[] = '<option></option>';
            foreach ($config['valuePicker']['items'] as $item) {
                $valuePickerHtml[] = '<option value="' . htmlspecialchars((string) $item[1]) . '">' . htmlspecialchars($languageService->sL($item[0])) . '</option>';
            }

            $valuePickerHtml[] = '</select>';
            $valuePickerHtml[] = '</typo3-formengine-valuepicker>';

            $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@typo3/backend/form-engine/field-wizard/value-picker.js');
        }

        $fieldControlResult = $this->renderFieldControl();
        $fieldControlHtml = $fieldControlResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldControlResult, false);

        $fieldWizardResult = $this->renderFieldWizard();
        $fieldWizardHtml = $fieldWizardResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);

        $thisAltTextId = 't3js-form-field-alt-text-id' . StringUtility::getUniqueId();
        $recreateButtonTitle = $this->getLanguageService()->sL('LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_core.xlf:buttons.recreateAltText');

        $mainFieldHtml = [];
        $mainFieldHtml[] = '<div class="form-control-wrap" style="max-width: ' . $width . 'px">';
        $mainFieldHtml[] =  '<div class="form-wizards-wrap" id="' . htmlspecialchars($thisAltTextId) . '">';
        $mainFieldHtml[] =      '<div class="form-wizards-element">';
        $mainFieldHtml[] =          '<div class="input-group">';
        $mainFieldHtml[] =              '<textarea ' . GeneralUtility::implodeAttributes($attributes, true) . '></textarea>';
        $mainFieldHtml[] =              '<button class="btn btn-default t3js-form-field-alt-text-recreate" type="button" title="' . htmlspecialchars($recreateButtonTitle) . '">';
        $mainFieldHtml[] =                  $iconFactory->getIcon('actions-ai-generate', Icon::SIZE_SMALL)->render();
        $mainFieldHtml[] =              '</button>';
        $mainFieldHtml[] =          '</div>';
        $mainFieldHtml[] =          '<input type="hidden" name="' . $itemName . '" value="' . htmlspecialchars((string)$itemValue) . '" />';
        $mainFieldHtml[] =      '</div>';
        if ($valuePickerHtml !== [] || !empty($fieldControlHtml)) {
            $mainFieldHtml[] =      '<div class="form-wizards-items-aside form-wizards-items-aside--field-control">';
            $mainFieldHtml[] =          '<div class="btn-group">';
            $mainFieldHtml[] =              implode(LF, $valuePickerHtml);
            $mainFieldHtml[] =              $fieldControlHtml;
            $mainFieldHtml[] =          '</div>';
            $mainFieldHtml[] =      '</div>';
        }

        if (!empty($fieldWizardHtml)) {
            $mainFieldHtml[] = '<div class="form-wizards-items-bottom">';
            $mainFieldHtml[] = $fieldWizardHtml;
            $mainFieldHtml[] = '</div>';
        }

        $mainFieldHtml[] =  '</div>';
        $mainFieldHtml[] = '</div>';
        $mainFieldHtml = implode(LF, $mainFieldHtml);

        $nullControlNameEscaped = htmlspecialchars('control[active][' . $table . '][' . $this->data['databaseRow']['uid'] . '][' . $fieldName . ']');

        $fullElement = $mainFieldHtml;
        if ($this->hasNullCheckboxButNoPlaceholder()) {
            $checked = $itemValue !== null ? ' checked="checked"' : '';
            $fullElement = [];
            $fullElement[] = '<div class="t3-form-field-disable"></div>';
            $fullElement[] = '<div class="form-check t3-form-field-eval-null-checkbox">';
            $fullElement[] =     '<input type="hidden" name="' . $nullControlNameEscaped . '" value="0" />';
            $fullElement[] =     '<input type="checkbox" class="form-check-input" name="' . $nullControlNameEscaped . '" id="' . $nullControlNameEscaped . '" value="1"' . $checked . ' />';
            $fullElement[] =     '<label class="form-check-label" for="' . $nullControlNameEscaped . '">';
            $fullElement[] =         $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.nullCheckbox');
            $fullElement[] =     '</label>';
            $fullElement[] = '</div>';
            $fullElement[] = $mainFieldHtml;
            $fullElement = implode(LF, $fullElement);
        } elseif ($this->hasNullCheckboxWithPlaceholder()) {
            $checked = $itemValue !== null ? ' checked="checked"' : '';
            $placeholder = $shortenedPlaceholder = trim((string)($config['placeholder'] ?? ''));
            if ($placeholder !== '') {
                $shortenedPlaceholder = GeneralUtility::fixed_lgd_cs($placeholder, 20);
                if ($placeholder !== $shortenedPlaceholder) {
                    $overrideLabel = sprintf(
                        $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.placeholder.override'),
                        '<span title="' . htmlspecialchars($placeholder) . '">' . htmlspecialchars($shortenedPlaceholder) . '</span>'
                    );
                } else {
                    $overrideLabel = sprintf(
                        $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.placeholder.override'),
                        htmlspecialchars($placeholder)
                    );
                }
            } else {
                $overrideLabel = $languageService->sL(
                    'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.placeholder.override_not_available'
                );
            }

            $fullElement = [];
            $fullElement[] = '<div class="form-check t3js-form-field-eval-null-placeholder-checkbox">';
            $fullElement[] =     '<input type="hidden" name="' . $nullControlNameEscaped . '" value="0" />';
            $fullElement[] =     '<input type="checkbox" class="form-check-input" name="' . $nullControlNameEscaped . '" id="' . $nullControlNameEscaped . '" value="1"' . $checked . ' />';
            $fullElement[] =     '<label class="form-check-label" for="' . $nullControlNameEscaped . '">';
            $fullElement[] =         $overrideLabel;
            $fullElement[] =     '</label>';
            $fullElement[] = '</div>';
            $fullElement[] = '<div class="t3js-formengine-placeholder-placeholder">';
            $fullElement[] =    '<div class="form-control-wrap" style="max-width:' . $width . 'px">';
            $fullElement[] =        '<input type="text" class="form-control" disabled="disabled" value="' . htmlspecialchars($shortenedPlaceholder) . '" />';
            $fullElement[] =    '</div>';
            $fullElement[] = '</div>';
            $fullElement[] = '<div class="t3js-formengine-placeholder-formfield">';
            $fullElement[] =    $mainFieldHtml;
            $fullElement[] = '</div>';
            $fullElement = implode(LF, $fullElement);
        }

        $resultArray['html'] = $renderedLabel . '
            <div class="formengine-field-item t3js-formengine-field-item">
                ' . $fieldInformationHtml . $fullElement . '
            </div>';

        $parentPageId = $this->data['parentPageRow']['uid'] ?? 0;
        $signature = GeneralUtility::hmac(
            implode(
                '',
                [
                    $table,
                    $this->data['effectivePid'],
                    $row['uid'],
                    $languageId,
                    $this->data['fieldName'],
                    $this->data['command'],
                    $parentPageId,
                ]
            ),
            AiGeneratedAltTextAjaxController::class
        );
        $optionsForModule = [
            'pageId' => $this->data['effectivePid'],
            'recordId' => $row['uid'],
            'tableName' => $table,
            'fieldName' => $this->data['fieldName'],
            'config' => $config,
            'language' => $languageId,
            'originalValue' => $itemValue,
            'signature' => $signature,
            'command' => $this->data['command'],
            'parentPageId' => $parentPageId,
            'includeUidInValues' => true,
        ];

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addInlineLanguageLabelFile(
            'EXT:ai_filemetadata/Resources/Private/Language/locallang_ai_filemetadata.xlf'
        );

        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            '@mfd/ai/filemetadata/form-engine/element/ai-generated-alt-text-element.js'
        )->instance('#' . $thisAltTextId, $optionsForModule);

        return $resultArray;
    }
}
