document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ea-fileupload input[type="file"]').forEach((fileUploadElement) => {
        new FileUpload(fileUploadElement);
    });
});

class FileUpload
{
    constructor(fileUploadElement) {
        this.#initialize(fileUploadElement);
    }

    #initialize(fileUploadElement) {
        this.#createFileUploadCard(fileUploadElement);
        this.#createFileMetadataUpdater(fileUploadElement);
        this.#createImagePreview(fileUploadElement);
        this.#createDeleteButton(fileUploadElement);
    }

    #createFileUploadCard(fileUploadElement) {
        fileUploadElement.addEventListener('change', () => {
            const fileUploadContainer = fileUploadElement.closest('.ea-fileupload');
            if (!fileUploadContainer.classList.contains('ea-fileupload-empty')) {
                return;
            }

            const fileUploadSelectButton = fileUploadContainer.querySelector('.ea-fileupload-select-btn');
            const fileUploadCardElement = fileUploadContainer.querySelector('.card');
            fileUploadCardElement.style.display = 'block';
            fileUploadSelectButton.parentNode.replaceChild(fileUploadCardElement, fileUploadSelectButton);
        });
    }

    #createFileMetadataUpdater(fileUploadElement) {
        fileUploadElement.addEventListener('change', () => {
            if (fileUploadElement.files && fileUploadElement.files[0]) {
                const fileUploadContainer = fileUploadElement.closest('.ea-fileupload');
                const fileUploadFilenameElement = fileUploadContainer.querySelector('.ea-fileupload-filename');
                const fileUploadFilesizeElement = fileUploadContainer.querySelector('.ea-fileupload-filesize');

                fileUploadFilenameElement.innerHTML = fileUploadElement.files[0].name;
                fileUploadFilesizeElement.innerHTML = this.#humanizeFileSize(fileUploadElement.files[0].size);
            }
        });
    }

    #createImagePreview(fileUploadElement) {
        fileUploadElement.addEventListener('change', () => {
            if (fileUploadElement.files && fileUploadElement.files[0]) {
                const reader = new FileReader();

                reader.addEventListener('load', () => {
                    const imagePreviewElement = fileUploadElement.closest('.ea-fileupload').querySelector('.ea-fileupload-image-preview img');
                    imagePreviewElement.src = reader.result;
                });

                reader.readAsDataURL(fileUploadElement.files[0]);
            }
        });
    }

    #createDeleteButton(fileUploadElement) {
        const fileUploadContainer = fileUploadElement.closest('.ea-fileupload');
        const fileUploadDeleteButton = fileUploadContainer.querySelector('.ea-fileupload-delete-btn');
        if (null === fileUploadDeleteButton) {
            return;
        }

        fileUploadDeleteButton.addEventListener('click', () => {
            const fileUploadInput = fileUploadContainer.querySelector('input[type="file"]');
            const fileUploadCard = fileUploadContainer.querySelector('.card');
            const selectFilesButton = fileUploadContainer.querySelector('.ea-fileupload-select-btn');

            fileUploadCard.outerHTML = selectFilesButton.outerHTML;

            // Problem 1: this should work to "remove" the file when clicking on the delete button. But it doesn't work
            fileUploadInput.value = '';
            // Problem 2: if the above doesn't work, the following should definitely work ... but it doesn't work either
            const emptyFileUploadElement = document.createElement('input');
            emptyFileUploadElement.type = 'file';
            emptyFileUploadElement.value = '';
            emptyFileUploadElement.name = fileUploadInput.name;
            emptyFileUploadElement.className = fileUploadInput.className;
            emptyFileUploadElement.style.display = 'none';
            fileUploadInput.parentNode.replaceChild(emptyFileUploadElement, fileUploadInput);
        });
    }

    #humanizeFileSize(bytes) {
        const unit = ['B', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];
        const factor = Math.trunc(Math.floor(Math.log(bytes) / Math.log(1024)));

        return Math.trunc(bytes / (1024 ** factor)) + unit[factor];
    }
}
