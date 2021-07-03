require('../css/form-type-file-upload.css');

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ea-fileupload input[type="file"]').forEach((fileUploadElement) => {
        new FileUpload(fileUploadElement);
    });
});

class FileUpload
{
    constructor(fileUploadElement) {
        this.#renderListOfFiles(fileUploadElement);
        fileUploadElement.addEventListener('change', () => { this.#renderListOfFiles(fileUploadElement); });
    }

    #createHtmlElement(tagName, cssClasses, htmlAttributes, textContent)
    {
        const element = document.createElement(tagName);

        if (null !== cssClasses && [] !== cssClasses) {
            element.classList.add(cssClasses);
        }

        if (null !== htmlAttributes && [] !== htmlAttributes) {
            htmlAttributes.forEach((propertyName, propertyValue) => {
                element[propertyName] = propertyValue;
            });
        }

        if (null !== textContent && '' !== textContent.trim()) {
            element.textContent = textContent;
        }

        return element;
    }

    #renderListOfFiles(fileUploadElement) {
        console.log(fileUploadElement);
        if (0 === fileUploadElement.files.length) {
            return;
        }

        const newListOfFilesElement = this.#createHtmlElement('div', ['ea-fileupload-selected-files-details']);
        fileUploadElement.files.forEach((file) => {
            const fileDetailsElement = this.#createHtmlElement('div', ['ea-fileupload-file-details']);

            const filePreviewElement = this.#createHtmlElement('div', ['ea-fileupload-file-preview']);
            const imageElement = this.#createHtmlElement('img', [], {src: 'https://live.symfony.com.wip/uploads/sponsors/01F8T4ZFN4EF46ZBMMR5X41671.png'});
            filePreviewElement.appendChild(imageElement);

            const fileMetadataElement = this.#createHtmlElement('div', ['ea-fileupload-file-metadata']);
            const fileNameElement = this.#createHtmlElement('span', ['ea-fileupload-file-name'], [], file.name);
            const fileSizeElement = this.#createHtmlElement('span', ['ea-fileupload-file-size'], [], this.#humanizeFileSize(file.size));
            fileMetadataElement.appendChild(fileNameElement);
            fileMetadataElement.appendChild(fileSizeElement);

            const fileActionsElement = this.#createHtmlElement('div', ['ea-fileupload-file-actions']);
            const fileDownloadElement = this.#createHtmlElement('a', ['ea-fileupload-download-action'], [], 'Download');
            const fileDeleteElement = this.#createHtmlElement('a', ['ea-fileupload-delete-action', 'text-danger'], [], 'Delete');
            fileActionsElement.appendChild(fileDownloadElement);
            fileActionsElement.appendChild(fileDeleteElement);

            fileDetailsElement.appendChild(filePreviewElement);
            fileDetailsElement.appendChild(fileMetadataElement);
            fileDetailsElement.appendChild(fileActionsElement);

            newListOfFilesElement.appendChild(fileDetailsElement);
        });
console.log(newListOfFilesElement);
        /*
            <div class="ea-fileupload-selected-files-details">
                <div class="ea-fileupload-file-details">
                    <div class="ea-fileupload-file-preview ea-fileupload-file-preview-is-image">
                        <img src="https://live.symfony.com.wip/uploads/sponsors/01F8T4ZFN4EF46ZBMMR5X41671.png" />
                    </div>
                    <div class="ea-fileupload-file-metadata">
                        <span class="ea-fileupload-file-name">{{ currentFiles|first.filename }}</span>
                        <span class="ea-fileupload-file-size">({{ currentFiles|first.size|ea_filesize }})
                    </div>
                    <div class="ea-fileupload-file-actions">
                        <a class="ea-fileupload-download-action" href="">Download</a>
                        <a class="ea-fileupload-delete-action text-danger" href="">Delete</a>
                    </div>
                </div>
            </div>
         */

        const listOfFilesElement = fileUploadElement.closest('.ea-fileupload').querySelector('.ea-fileupload-selected-files-details');
        listOfFilesElement.parentNode.replaceChild(newListOfFilesElement, listOfFilesElement);
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
