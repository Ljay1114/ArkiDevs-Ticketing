/**
 * Common JavaScript for Arkidevs Support
 */

(function($) {
    'use strict';

    /**
     * Show notification message
     */
    function showNotice(message, type) {
        type = type || 'success';
        var noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Remove existing notices
        $('.arkidevs-notice').remove();
        
        // Add notice class and prepend to container
        notice.addClass('arkidevs-notice');
        
        if ($('.wrap').length) {
            $('.wrap').prepend(notice);
        } else {
            $('body').prepend(notice);
        }
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
    }

    /**
     * Handle AJAX errors
     */
    function handleAjaxError(xhr, status, error) {
        var message = 'An error occurred. Please try again.';
        
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            message = xhr.responseJSON.data.message;
        }
        
        showNotice(message, 'error');
    }

    // Expose functions globally without overwriting localized data (ajaxUrl, nonce, etc.)
    // Make sure we don't overwrite existing arkidevsSupport object (which may have ajaxUrl and nonce)
    if (typeof window.arkidevsSupport === 'undefined') {
        window.arkidevsSupport = {};
    }
    
    // Only add functions if they don't exist
    if (typeof window.arkidevsSupport.showNotice === 'undefined') {
        window.arkidevsSupport.showNotice = showNotice;
    }
    if (typeof window.arkidevsSupport.handleAjaxError === 'undefined') {
        window.arkidevsSupport.handleAjaxError = handleAjaxError;
    }

})(jQuery);


