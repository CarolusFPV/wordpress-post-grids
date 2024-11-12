document.addEventListener('DOMContentLoaded', function() {
    var uploadButtons = document.querySelectorAll('.upload_image_button');
    var customUploader;

    uploadButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            var container = document.getElementById('cpg_image_container');

            if (customUploader) {
                customUploader.open();
                return;
            }

            customUploader = wp.media({
                title: 'Choose Image',
                button: {
                    text: 'Choose Image'
                },
                multiple: true
            });

            customUploader.on('select', function() {
                var attachments = customUploader.state().get('selection').toArray();
                attachments.forEach(function(attachment) {
                    var imageUrl = attachment.attributes.url;
                    var imageHtml = document.createElement('div');
                    imageHtml.className = 'cpg-image-item';
                    imageHtml.innerHTML = '<img src="' + imageUrl + '" style="max-width: 100px; height: auto; margin-right: 10px;" />' +
                                          '<input type="hidden" name="cpg_image_library[]" value="' + imageUrl + '" />' +
                                          '<button type="button" class="remove_image_button button">Remove</button>';
                    container.appendChild(imageHtml);
                });
            });

            customUploader.open();
        });
    });

    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove_image_button')) {
            e.preventDefault();
            e.target.closest('.cpg-image-item').remove();
        }
    });
});