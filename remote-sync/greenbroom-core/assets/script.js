jQuery(document).ready(function($) {
    
    // 1. Front-End Search
    $('#gb-search-form').on('submit', function(e) {
        e.preventDefault();
        
        let data = {
            action: 'gb_cleaner_search',
            nonce: gb_ajax.nonce,
            postcode: $('input[name="postcode"]').val(),
            radius: $('select[name="radius"]').val(),
            max_rate: $('input[name="max_rate"]').val()
        };

        $('#gb-results-grid').html('<p>Searching...</p>');

        $.post(gb_ajax.url, data, function(response) {
            if(response.success) {
                $('#gb-results-grid').html(response.data);
            } else {
                $('#gb-results-grid').html('<p>' + response.data + '</p>');
            }
        });
    });

    // 2. Admin Subscription Toggle
    $('.gb-toggle-sub').on('click', function(e) {
        e.preventDefault();
        let btn = $(this);
        let uid = btn.data('uid');
        let status = btn.data('status');

        $.post(gb_admin_ajax.url, {
            action: 'gb_toggle_sub',
            nonce: gb_admin_ajax.nonce,
            user_id: uid,
            status: status
        }, function(response) {
            if(response.success) {
                location.reload(); // Simple reload to reflect changes
            }
        });
    });
});

// ... existing code ...

// 3. Admin Avatar Uploader
jQuery(document).ready(function($){
    var mediaUploader;
    
    $('#gb-upload-avatar').click(function(e) {
        e.preventDefault();
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Profile Photo',
            button: { text: 'Use this photo' },
            multiple: false
        });
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#gb-avatar-id').val(attachment.id);
            $('#gb-avatar-preview').attr('src', attachment.url);
            $('#gb-remove-avatar').show();
        });
        mediaUploader.open();
    });

    $('#gb-remove-avatar').click(function(e){
        e.preventDefault();
        $('#gb-avatar-id').val('');
        $('#gb-avatar-preview').attr('src', 'https://www.gravatar.com/avatar/?d=mp&s=150');
        $(this).hide();
    });
});