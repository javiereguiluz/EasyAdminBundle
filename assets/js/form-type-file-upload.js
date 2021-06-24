document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ea-fileupload input[type="file"]').forEach((fileUploadElement) => {
        new FileUpload(fileUploadElement);
    });

        document.querySelectorAll('.ea-fileupload .ea-fileupload-delete-btn').forEach((fileUploadDeleteButton) => {
            fileUploadDeleteButton.addEventListener('click', () => {
                const fileUploadContainer = fileUploadDeleteButton.closest('.ea-fileupload');
                const fileUploadInput = fileUploadContainer.querySelector('input');
                const fileUploadCustomInput = fileUploadContainer.querySelector('.custom-file-label');
                const fileUploadFileSizeLabel = fileUploadContainer.querySelector('.input-group-text');
                const fileUploadListOfFiles = fileUploadContainer.querySelector('.fileupload-list');

                fileUploadInput.value = '';
                fileUploadCustomInput.innerHTML = '';
                fileUploadFileSizeLabel.innerHTML = '';
                fileUploadFileSizeLabel.style.display = 'none';
                fileUploadDeleteButton.style.display = 'none';

                if (null !== fileUploadListOfFiles) {
                    fileUploadListOfFiles.style.display = 'none';
                }
            });
        });
});

class FileUpload
{
    constructor(element) {
        this.element = element;
        this.#initialize();
    }

    #initialize() {
        this.element.addEventListener('change', () => {
            const numberOfFiles = this.element.files.length;

            if (0 === numberOfFiles) {
                return;
            }

            let filename = '';
            if (1 === numberOfFiles) {
                filename = this.element.files[0].name;
            } else {
                filename = numberOfFiles + ' ' + this.element.getAttribute('data-files-label');
            }

            let bytes = 0;
            for (let i = 0; i < numberOfFiles; i++) {
                bytes += this.element.files[i].size;
            }

            const fileUploadContainer = this.element.closest('.ea-fileupload');
            const fileUploadCustomInput = fileUploadContainer.querySelector('.custom-file-label');
            const fileUploadFileSizeLabel = fileUploadContainer.querySelector('.input-group-text');
            const fileUploadDeleteButton = fileUploadContainer.querySelector('.ea-fileupload-delete-btn');

            fileUploadCustomInput.value = filename;
            //fileUploadFileSizeLabel.innerHTML = '***' + this.#humanizeFileSize(bytes);
            fileUploadFileSizeLabel.style.display = 'inherit';
            fileUploadDeleteButton.style.display = 'block';
        });
    }

    #humanizeFileSize(bytes) {
        const unit = ['B', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];
        const factor = Math.trunc(Math.floor(Math.log(bytes) / Math.log(1024)));

        return Math.trunc(bytes / (1024 ** factor)) + unit[factor];
    }
}
