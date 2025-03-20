/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import DocumentService from '@typo3/core/document-service.js';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import RegularEvent from '@typo3/core/event/regular-event.js';

var Selectors;
(function (Selectors) {
    Selectors['recreateButton'] = '.t3js-form-field-alt-text-recreate';
    Selectors['inputField'] = '.t3js-form-field-alt-text-input';
})(Selectors || (Selectors = {}));

/**
 * Module: @typo3/backend/form-engine/element/ai-generated-text-element
 * Logic for a TCA type "slug"
 *
 * For new records, changes on the other fields of the record (typically the record title) are listened
 * on as well and the response is put in as "placeholder" into the input field.
 *
 * For new and existing records, the toggle switch will allow editors to modify the slug
 *  - for new records, we only need to see if that is already in use or not (uniqueInSite), if it is taken, show a message.
 *  - for existing records, we also check for conflicts, and check if we have subpages, or if we want to add a redirect (todo)
 */
class AiGeneratedAltTextElement {
    constructor(selector, options) {
        this.options = null;
        this.fullElement = null;
        this.inputField = null;
        this.request = null;
        this.fieldsToListenOn = {};
        this.options = options;
        this.fieldsToListenOn = this.options.listenerFieldNames || {};
        DocumentService.ready().then((document) => {
            this.fullElement = document.querySelector(selector);
            this.inputField = this.fullElement.querySelector(Selectors.inputField);
            this.registerEvents();
        });
    }

    registerEvents() {
        const recreateButton = this.fullElement.querySelector(Selectors.recreateButton);
        // Clicking the recreate button makes new slug proposal created from 'title' field or any defined postModifiers
        new RegularEvent('click', (e) => {
            e.preventDefault();
            this.recreateAltText();
        }).bindTo(recreateButton);
    }

    recreateAltText() {
        const input = {};
        input.uid = this.options.recordId.toString();
        if (this.request instanceof AjaxRequest) {
            this.request.abort();
        }
        this.request = (new AjaxRequest(TYPO3.settings.ajaxUrls.record_ai_generated_alt_text));
        const element = this;

        Modal.advanced({
            title: TYPO3.lang['notification.regenerate_alt_text.title'],
            content: TYPO3.lang['notification.regenerate_alt_text.message'],
            size: Modal.sizes.small,
            staticBackdrop: true,
            hideCloseButton: true,
        }).addEventListener('typo3-modal-shown', () => {
            element.request.post({
                values: input,
                tableName: element.options.tableName,
                pageId: element.options.pageId,
                parentPageId: element.options.parentPageId,
                recordId: element.options.recordId,
                language: element.options.language,
                fieldName: element.options.fieldName,
                command: element.options.command,
                signature: element.options.signature,
            }).then(async (response) => {
                const data = await response.resolve();
                element.inputField.value = data.text;
                element.fullElement.querySelector('textarea[data-formengine-input-name]')
                    .dispatchEvent(new Event('change', {bubbles: true, cancelable: true}));
                Notification.success(TYPO3.lang['notification.alt_text_regenerated.title'], TYPO3.lang['notification.alt_text_regenerated.message']);
                Modal.dismiss();
            }).finally(() => {
                element.request = null;
            });
        });
    }
}

export default AiGeneratedAltTextElement;

