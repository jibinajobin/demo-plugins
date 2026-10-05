jQuery(function ($) {

    console.log('Evodent Admin JS Loaded');

    let selectedFiles = [];

    // Add selected files
    $('#evodent_documents').on('change', function () {

        const files = Array.from(this.files);

        files.forEach(function (file) {

            if (file.type !== 'application/pdf') {
                alert(file.name + ' is not a PDF.');
                return;
            }

            // Prevent duplicate filenames
            if (selectedFiles.find(f => f.name === file.name)) {
                return;
            }

            selectedFiles.push(file);

        });

        renderFiles();

        // Reset input so same file can be selected again later
        $(this).val('');

    });

    function escapeHtml(str) {
        return $('<div>').text(str).html();
    }

    // Render file list
    function renderFiles() {

        let html = '';

        selectedFiles.forEach(function (file, index) {

            html += `
                <li style="margin:6px 0;">
                    📄 ${escapeHtml(file.name)}
                    <a href="#"
                       class="remove-document"
                       data-index="${index}"
                       style="float:right;color:red;text-decoration:none;">
                       ✕
                    </a>
                </li>
            `;

        });

        $('#evodent_document_list').html(html);

    }

    // Remove file
    $(document).on('click', '.remove-document', function (e) {

        e.preventDefault();

        selectedFiles.splice($(this).data('index'), 1);

        renderFiles();

    });

    // Send Documents
    $('#evodent_send_documents').on('click', function () {

        if (selectedFiles.length === 0) {
            alert('Please select at least one PDF.');
            return;
        }

        // const orderId = $('#post_ID').val();
        const orderId = $('#evodent_order_id').val();

        if (!orderId) {
            alert('Order ID not found.');
            return;
        }

        const formData = new FormData();

        formData.append('action', 'evodent_send_documents');
        formData.append('nonce', evodentDocs.nonce);
        // formData.append('order_id', orderId);

        const orderNumber = $('#evodent_order_number').val();

formData.append('order_id', orderId);
formData.append('order_number', orderNumber);

        selectedFiles.forEach(function (file) {
            formData.append('documents[]', file);
        });

        $('#evodent_send_documents')
            .prop('disabled', true)
            .text('Uploading...');

        $.ajax({

            url: evodentDocs.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,

            success: function (response) {

                console.log(response);

                if (response.success) {

                    alert('Email sent successfully.');

                    selectedFiles = [];

                    renderFiles();

                } else {

                    alert(response.data);

                }

            },

            error: function () {

                alert('Upload failed.');

            },

            complete: function () {

                $('#evodent_send_documents')
                    .prop('disabled', false)
                    .text('Send Documents');

            }

        });

    });

});