// fileUpload component (requires jquery)
let Uploadfiles = {};
(function ($) {
    $.fn.fileUpload = function (options = {}) {
        //region const defaults
        const defaults = {
            defaultIcon: '<i class="fa fa-upload"></i>',
            removeIcon: '<i class="fa fa-times red"></i>',
            busyIcon: '<i class="fa fa-spin fa-spinner"></i>',
            emptyMessage: 'No file selected',
            invalidFileError: 'Invalid file type was selected.',
            fileTooLargeError: 'File is bigger than 2MB.',
            maxFileSize: 2097152,
            errorClass: 'error',
            allowedFiles: [
                //Text file
                //'text/plain',
                // office files
                //'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                //'.xml,application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                // audio files
                //'audio/*', 'audio/mpeg', 'audio/wav', 'audio/ogg',
                // video files
                //'video/*', 'video/mp4',
                // Image files
                //'image/*', 'image/apng', 'image/bmp', 'image/gif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/svg+xml', 'image/tiff', 'image/webp', 'image/x-icon',
                // pdf file
                //'application/pdf',
            ]
        };
        const settings = $.extend({}, defaults, options);
        let fileCounter = 0;
        //endregion

        //region function getFileSize(...)
        function getFileSize(num) {
            if (num < 1024) {
                return num + 'B';
            } else if (num >= 1024 && num < 1048576) {
                return (num / 1024).toFixed(1) + 'KB';
            } else if (num >= 1048576) {
                return (num / 1048576).toFixed(1) + ' MB';
            }
        }
        //endregion

        //region function isValidFile(...)
        function isValidFile(fileType) {
            console.debug('filetype is: ' + fileType);
            return (settings.allowedFiles.length < 1) || (settings.allowedFiles.includes(fileType) && fileType !== '');
        }
        //endregion

        //region function readFile(...)
        function readFile(fileInput) {
            let file = fileInput.files[0];

            //TODO do crazy stuff with capture (file.capture) attribute
            //TODO do more crazy stuff with file.multiple attribute

            let inputElement = $(fileInput);
            let parentId = inputElement.parent().attr('id');

            if (!file || file.name === '' || file.size < 1) {
                return false;
            }
            // Verify file type
            if (!isValidFile(file.type)) {
                inputElement.siblings('span').removeClass('d-none').html(' ' + settings.invalidFileError).addClass(settings.errorClass);
                return false;
            }
            // Max file size limit
            if (file.size > settings.maxFileSize) {
                inputElement.siblings('span').removeClass('d-none').html(' ' + settings.fileTooLargeError).addClass(settings.errorClass);
                return false;
            }

            let reader = new FileReader();
            inputElement.siblings('.busy').removeClass('d-none');
            inputElement.siblings('.add').addClass('d-none');
            inputElement.siblings('span').addClass('d-none');
            reader.onload = function () {
                Uploadfiles[parentId] = {
                    'name': file.name, 'type': file.type, 'size': file.size, 'file': reader.result
                };
                inputElement.siblings('.busy').addClass('d-none');
                inputElement.siblings('.remove').removeClass('d-none');
                inputElement.siblings('span').removeClass('d-none').removeClass(settings.errorClass).html(' ' + file.name + ' (' + getFileSize(file.size) + ')');
            };
            reader.onerror = function (error) {
                inputElement.siblings('span').html(' ' + error).addClass(settings.errorClass);
            };
            reader.readAsDataURL(file);
        }
        //endregion

        //region function removeFile(...)
        function removeFile(removeElement) {
            const removeBtn = $(removeElement);
            let parentId = removeBtn.parent().attr('id');
            delete Uploadfiles[parentId];
            removeBtn.addClass('d-none');
            removeBtn.siblings('.add').removeClass('d-none');
            removeBtn.siblings('span').addClass('d-none');
        }
        //endregion

        //region function init(...)
        function init($this) {
            let id = $this.attr('id') || 'file_' + fileCounter++;
            let text = $this.html() || settings.emptyMessage;
            $this.empty().attr('id', id).on('click', 'a', function (e) {
                const $element = $(this);
                if ($element.hasClass('add')) {
                    $(this).siblings('input').click();
                    e.stopPropagation();
                    e.preventDefault();
                } else if ($element.hasClass('remove')) {
                    removeFile($element);
                    e.stopPropagation();
                    e.preventDefault();
                }
            })
            $('<input type="file" class="d-none"' + (settings.allowedFiles.length < 1 ? '' : ' accept="' + settings.allowedFiles.join(',') + '"') + ' />').on('change', '', function () {
                readFile(this);
            }).appendTo($this);
            $('<a href="#" class="add" title="Select file">' + settings.defaultIcon + ' ' + text + '</a>').appendTo($this);
            $('<a href="#" class="remove d-none" title="Remove file">' + settings.removeIcon + '</a>').appendTo($this);
            $(settings.busyIcon).addClass('busy d-none').appendTo($this);
            $('<span />').addClass('d-none').appendTo($this)
        }
        //endregion

        $(this).each(function () {
            const $this = $(this)
            init($this);
        });
    };
}(jQuery));