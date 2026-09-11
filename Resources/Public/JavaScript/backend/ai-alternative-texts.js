const generationForm = document.querySelector('[data-ai-filemetadata-generation-form]');

if (generationForm !== null) {
    let isSubmitted = false;
    generationForm.addEventListener('submit', (event) => {
        if (isSubmitted) {
            event.preventDefault();
            return;
        }
        isSubmitted = true;

        const overwriteControl = generationForm.querySelector('input[name="overwrite"]');
        if (overwriteControl instanceof HTMLInputElement && overwriteControl.checked) {
            const submittedOverwriteControl = document.createElement('input');
            submittedOverwriteControl.type = 'hidden';
            submittedOverwriteControl.name = overwriteControl.name;
            submittedOverwriteControl.value = overwriteControl.value;
            generationForm.append(submittedOverwriteControl);
        }

        generationForm.querySelectorAll('[data-ai-filemetadata-generation-control]').forEach((control) => {
            control.disabled = true;
        });

        const submitButton = generationForm.querySelector('[data-ai-filemetadata-generation-submit]');
        if (submitButton instanceof HTMLButtonElement && submitButton.dataset.loadingLabel) {
            submitButton.textContent = submitButton.dataset.loadingLabel;
        }

        const runningMessage = generationForm.querySelector('[data-ai-filemetadata-generation-running]');
        if (runningMessage instanceof HTMLElement) {
            runningMessage.hidden = false;
        }
    });
}
