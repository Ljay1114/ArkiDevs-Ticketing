/**
 * Customer JavaScript for Arkidevs Support
 */

(function($) {
    'use strict';

    // Wait for both jQuery and arkidevsSupport to be available
    function initCustomerScripts() {
        // Check if arkidevsSupport is available
        if (typeof window.arkidevsSupport === 'undefined') {
            console.warn('arkidevsSupport not loaded yet, retrying in 500ms...');
            setTimeout(initCustomerScripts, 500);
            return;
        }

        // Check if required properties exist
        if (!window.arkidevsSupport.ajaxUrl || !window.arkidevsSupport.nonce) {
            console.warn('arkidevsSupport.ajaxUrl or nonce missing, retrying in 500ms...');
            setTimeout(initCustomerScripts, 500);
            return;
        }

        console.log('Initializing customer scripts...');
        console.log('arkidevsSupport:', window.arkidevsSupport);

        // Run the actual initialization
        initializeCustomerDashboard();
    }

    function initializeCustomerDashboard() {
        // Show/hide ticket form
        $('#show-ticket-form').on('click', function() {
            $('#ticket-form').removeClass('hidden');
            $(this).addClass('hidden');
        });

        $('#cancel-ticket-form').on('click', function() {
            $('#ticket-form').addClass('hidden');
            $('#show-ticket-form').removeClass('hidden');
            $('#create-ticket-form')[0].reset();
        });

        // Create ticket - use document delegation to ensure it works even if form loads later
        $(document).on('submit', '#create-ticket-form', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('=== TICKET CREATION ATTEMPT ===');
            
            // Debug: Check if arkidevsSupport is available
            if (typeof window.arkidevsSupport === 'undefined') {
                console.error('ERROR: arkidevsSupport object is not defined.');
                console.error('Make sure scripts are loaded. Check Network tab for customer.js and common.js');
                alert('Error: Scripts not loaded properly. Please refresh the page and try again.');
                return false;
            }
            
            // Check if required properties exist
            if (!window.arkidevsSupport.ajaxUrl) {
                console.error('ERROR: arkidevsSupport.ajaxUrl is missing');
                alert('Error: AJAX URL not configured. Please refresh the page.');
                return false;
            }
            
            if (!window.arkidevsSupport.nonce) {
                console.error('ERROR: arkidevsSupport.nonce is missing');
                alert('Error: Security token missing. Please refresh the page.');
                return false;
            }
            
            console.log('✓ arkidevsSupport object found');
            console.log('✓ ajaxUrl:', window.arkidevsSupport.ajaxUrl);
            console.log('✓ nonce:', window.arkidevsSupport.nonce ? 'Present' : 'Missing');
            
            var formData = {
                action: 'arkidevs_create_ticket',
                nonce: window.arkidevsSupport.nonce,
                subject: $('#ticket-subject').val().trim(),
                description: $('#ticket-description').val().trim(),
                priority: $('#ticket-priority').val()
            };
            
            console.log('Form data prepared:', {
                action: formData.action,
                nonce: formData.nonce ? 'Present' : 'Missing',
                subject: formData.subject,
                description: formData.description ? 'Present (' + formData.description.length + ' chars)' : 'Missing',
                priority: formData.priority
            });
            
            if (!formData.subject || !formData.description) {
                var errorMsg = 'Please fill in all required fields.';
                console.error('Validation failed:', {
                    subject: formData.subject ? 'OK' : 'MISSING',
                    description: formData.description ? 'OK' : 'MISSING'
                });
                
                if (typeof window.arkidevsSupport !== 'undefined' && window.arkidevsSupport.showNotice) {
                    window.arkidevsSupport.showNotice(errorMsg, 'error');
                } else {
                    alert(errorMsg);
                }
                return false;
            }
            
            // Show loading state
            var $submitBtn = $(this).find('button[type="submit"]');
            var originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).text('Submitting...');
            
            console.log('Sending AJAX request to:', window.arkidevsSupport.ajaxUrl);
            
            $.ajax({
                url: window.arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: formData,
                timeout: 30000, // 30 second timeout
                success: function(response) {
                    console.log('=== AJAX SUCCESS ===');
                    console.log('Response:', response);
                    $submitBtn.prop('disabled', false).text(originalText);
                    if (response.success) {
                        console.log('✓ Ticket created successfully!');
                        var successMsg = response.data && response.data.message ? response.data.message : 'Ticket created successfully!';
                        
                        if (typeof window.arkidevsSupport !== 'undefined' && window.arkidevsSupport.showNotice) {
                            window.arkidevsSupport.showNotice(successMsg);
                        } else {
                            alert(successMsg);
                        }
                        
                        $('#create-ticket-form')[0].reset();
                        $('#ticket-form').addClass('hidden');
                        $('#show-ticket-form').removeClass('hidden');
                        
                        // Reload page to show new ticket
                        console.log('Reloading page in 1 second...');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'An error occurred. Please try again.';
                        console.error('✗ Ticket creation failed:', errorMsg);
                        console.error('Full response:', response);
                        
                        if (typeof window.arkidevsSupport !== 'undefined' && window.arkidevsSupport.showNotice) {
                            window.arkidevsSupport.showNotice(errorMsg, 'error');
                        } else {
                            alert(errorMsg);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('=== AJAX ERROR ===');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Status Code:', xhr.status);
                    console.error('Response Text:', xhr.responseText);
                    
                    if (xhr.responseJSON) {
                        console.error('Response JSON:', xhr.responseJSON);
                    }
                    
                    $submitBtn.prop('disabled', false).text(originalText);
                    
                    var errorMsg = 'An error occurred. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    } else if (xhr.responseText) {
                        // Try to extract error from HTML response
                        var match = xhr.responseText.match(/<p[^>]*>([^<]+)<\/p>/i);
                        if (match) {
                            errorMsg = match[1];
                        }
                    }
                    
                    if (typeof window.arkidevsSupport !== 'undefined' && window.arkidevsSupport.handleAjaxError) {
                        window.arkidevsSupport.handleAjaxError(xhr, status, error);
                    } else {
                        alert('Error: ' + errorMsg + '\n\nStatus: ' + status + '\nCode: ' + xhr.status);
                    }
                }
            });
            
            return false; // Prevent default form submission
        });

        // Reply to specific comment (customers)
        $(document).on('click', '.reply-to-comment', function() {
            var $btn = $(this);
            var replyId = $btn.data('reply-id');
            var authorName = $btn.data('author-name');

            // Set parent reply ID
            $('#parent-reply-id').val(replyId);

            // Scroll to reply form
            if ($('#main-reply-form').length) {
                $('html, body').animate({
                    scrollTop: $('#main-reply-form').offset().top - 100
                }, 300);
            }

            // Focus textarea and add @mention
            var $textarea = $('#reply-message');
            var currentText = $textarea.val().trim();
            var mention = '@' + authorName + ' ';

            if (!currentText || currentText.indexOf(mention) !== 0) {
                $textarea.val(mention + currentText).trigger('focus');
            } else {
                $textarea.trigger('focus');
            }

            // Show cancel button
            $('#cancel-reply').show();
        });

        // Cancel reply
        $('#cancel-reply').on('click', function() {
            $('#parent-reply-id').val('');
            $('#reply-message').val('');
            $(this).hide();
        });

        // Add reply (supports nested replies for customers)
        $('#submit-reply').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var ticketId = $btn.data('ticket-id');
            var message = $('#reply-message').val().trim();
            var parentReplyId = $('#parent-reply-id').val();
            
            if (!message) {
                arkidevsSupport.showNotice('Please enter a message.', 'error');
                return;
            }
            
            // Disable button and show loading state
            var originalText = $btn.text();
            $btn.prop('disabled', true)
                .text('Submitting...')
                .addClass('button-disabled');
            
            var ajaxData = {
                action: 'arkidevs_add_reply',
                nonce: window.arkidevsSupport.nonce,
                ticket_id: ticketId,
                message: message
            };

            if (parentReplyId) {
                ajaxData.parent_reply_id = parentReplyId;
            }

            $.ajax({
                url: window.arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    console.log('Add reply response:', response); // Debug
                    if (response.success) {
                        window.arkidevsSupport.showNotice(response.data.message);
                        $('#reply-message').val('');
                        $('#parent-reply-id').val('');
                        $('#cancel-reply').hide();
                        
                        // Dynamically add the new comment
                        if (response.data && response.data.comment_html) {
                            try {
                                // Ensure HTML is valid before parsing
                                if (!response.data.comment_html || response.data.comment_html.trim() === '') {
                                    console.error('Empty comment_html received');
                                    location.reload();
                                    return;
                                }
                                
                                var $newComment = $(response.data.comment_html);
                                
                                // Verify the comment was parsed correctly
                                if (!$newComment.length) {
                                    console.error('Invalid comment HTML structure');
                                    location.reload();
                                    return;
                                }
                                
                                var parentReplyId = response.data.parent_reply_id;
                                
                                if (parentReplyId) {
                                    // Add as nested reply
                                    var $parentItem = $('.reply-item[data-reply-id="' + parentReplyId + '"]');
                                    if ($parentItem.length) {
                                        var $childrenContainer = $parentItem.find('.reply-children');
                                        
                                        if ($childrenContainer.length) {
                                            // Check if we need to show "View more replies" button
                                            var $existingReplies = $childrenContainer.find('.reply-item');
                                            if ($existingReplies.length >= 1) {
                                                // Hide existing "View more" button if any
                                                $childrenContainer.find('.view-replies-toggle').remove();
                                                // Show all replies now
                                                $childrenContainer.find('.reply-hidden-replies').removeClass('reply-hidden-replies');
                                            }
                                            $childrenContainer.append($newComment);
                                        } else {
                                            // Create children container if it doesn't exist
                                            var $replyContent = $parentItem.find('.reply-content');
                                            if ($replyContent.length) {
                                                $replyContent.append('<div class="reply-children" data-parent-reply-id="' + parentReplyId + '"></div>');
                                                $parentItem.find('.reply-children').append($newComment);
                                            } else {
                                                console.error('Reply content not found in parent item');
                                                location.reload();
                                                return;
                                            }
                                        }
                                        
                                        // Scroll to new comment after DOM update
                                        setTimeout(function() {
                                            if ($newComment.length && $newComment.offset()) {
                                                $('html, body').animate({
                                                    scrollTop: $newComment.offset().top - 100
                                                }, 300);
                                            }
                                        }, 200);
                                    } else {
                                        console.warn('Parent item not found, adding as top-level');
                                        var $repliesContainer = $('.ticket-replies');
                                        if ($repliesContainer.length) {
                                            var $insertPoint = $repliesContainer.find('h3');
                                            if ($insertPoint.length) {
                                                $insertPoint.after($newComment);
                                            } else {
                                                $repliesContainer.append($newComment);
                                            }
                                        } else {
                                            location.reload();
                                        }
                                    }
                                } else {
                                    // Add as top-level comment
                                    var $repliesContainer = $('.ticket-replies');
                                    if ($repliesContainer.length) {
                                        // Find the h3 or the reply form, insert before it
                                        var $insertPoint = $repliesContainer.find('h3');
                                        if ($insertPoint.length) {
                                            $insertPoint.after($newComment);
                                        } else {
                                            // If no h3, try to find the reply form
                                            var $replyForm = $('#main-reply-form, .reply-form');
                                            if ($replyForm.length) {
                                                $replyForm.before($newComment);
                                            } else {
                                                // Last resort: append to container
                                                $repliesContainer.append($newComment);
                                            }
                                        }
                                        
                                        // Scroll to new comment after it's in the DOM
                                        setTimeout(function() {
                                            if ($newComment.length && $newComment.offset()) {
                                                $('html, body').animate({
                                                    scrollTop: $newComment.offset().top - 100
                                                }, 300);
                                            }
                                        }, 200);
                                    } else {
                                        console.error('Replies container not found');
                                        location.reload();
                                    }
                                }
                            } catch (e) {
                                console.error('Error inserting comment:', e);
                                location.reload();
                            }
                        } else {
                            console.warn('No comment_html in response, reloading');
                            location.reload();
                        }
                    } else {
                        arkidevsSupport.showNotice(response.data ? response.data.message : 'An error occurred', 'error');
                    }
                    
                    // Re-enable button
                    $btn.prop('disabled', false)
                        .text(originalText)
                        .removeClass('button-disabled');
                },
                error: function(xhr, status, error) {
                    arkidevsSupport.handleAjaxError(xhr, status, error);
                    
                    // Re-enable button on error
                    $btn.prop('disabled', false)
                        .text(originalText)
                        .removeClass('button-disabled');
                }
            });
        });

        // Submit CSAT rating (customers)
        $(document).on('click', '#arkidevs-submit-rating', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var ticketId = $btn.data('ticket-id');
            var rating = parseInt($('#arkidevs-rating-score').val(), 10);
            var feedback = $('#arkidevs-rating-feedback').val().trim();
            var $message = $('#arkidevs-rating-message');

            if (!rating || rating < 1 || rating > 5) {
                arkidevsSupport.showNotice('Please select a rating from 1 to 5.', 'error');
                return;
            }

            $btn.prop('disabled', true).text('Submitting...');
            $message.hide();

            $.ajax({
                url: window.arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_submit_rating',
                    nonce: window.arkidevsSupport.nonce,
                    ticket_id: ticketId,
                    rating: rating,
                    feedback: feedback
                },
                success: function(response) {
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message || 'Thanks for your feedback!');
                        $message.text(response.data.message || 'Thank you!').css('color', '#198754').show();
                        $btn.prop('disabled', true).text('Submitted');
                        $('#arkidevs-rating-score, #arkidevs-rating-feedback').prop('disabled', true);
                    } else {
                        arkidevsSupport.showNotice(response.data ? response.data.message : 'Unable to submit rating.', 'error');
                        $btn.prop('disabled', false).text('Submit Rating');
                    }
                },
                error: function(xhr, status, error) {
                    arkidevsSupport.handleAjaxError(xhr, status, error);
                    $btn.prop('disabled', false).text('Submit Rating');
                }
            });
        });

        // View more replies (for customer view - use document delegation)
        $(document).on('click', '.view-replies-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var parentId = $btn.data('parent-id');
            var $parent = $btn.closest('.reply-children');
            var $hiddenReplies = $parent.find('.reply-hidden-replies');
            
            if ($hiddenReplies.length) {
                $hiddenReplies.slideDown(300);
                $btn.hide();
                $parent.find('.hide-replies-btn[data-parent-id="' + parentId + '"]').show();
            }
        });

        // Hide replies (for customer view - use document delegation)
        $(document).on('click', '.hide-replies-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var parentId = $btn.data('parent-id');
            var $parent = $btn.closest('.reply-children');
            var $hiddenReplies = $parent.find('.reply-hidden-replies');
            
            if ($hiddenReplies.length) {
                $hiddenReplies.slideUp(300, function() {
                    // Scroll to parent comment after hiding
                    var $parentItem = $parent.closest('.reply-item');
                    if ($parentItem.length) {
                        $('html, body').animate({
                            scrollTop: $parentItem.offset().top - 100
                        }, 300);
                    }
                });
                $btn.hide();
                $parent.find('.view-replies-btn[data-parent-id="' + parentId + '"]').show();
            }
        });

        /**
         * ==============================
         * Live comments refresh (customer)
         * ==============================
         */
        (function setupCustomerLiveRepliesRefresh() {
            var $thread = $('.ticket-replies .replies-thread');
            if (!$thread.length) {
                return; // Not on ticket view
            }

            var lastHtml = $thread.html();

            setInterval(function() {
                $.get(window.location.href, function(response) {
                    try {
                        var $html = $(response);
                        var $newThread = $html.find('.ticket-replies .replies-thread');
                        if ($newThread.length) {
                            var newHtml = $newThread.html();
                            if (newHtml && newHtml !== lastHtml) {
                                $thread.html(newHtml);
                                lastHtml = newHtml;
                            }
                        }
                    } catch (e) {
                        console.error('Error refreshing customer replies:', e);
                    }
                });
            }, 10000); // every 10 seconds
        })();

        /**
         * ==============================
         * Customer ticket list search & filters
         * (My Tickets table)
         * ==============================
         */

        function performCustomerTicketSearch() {
            var $list = $('.tickets-list');

            if (!$list.length) {
                return;
            }

            var filters = {
                search: $('#customer-search').val() || '',
                status: $('#customer-status').val() || '',
                priority: $('#customer-priority').val() || '',
                tag_id: $('#customer-tag').val() || '0',
                date_from: $('#customer-date-from').val() || '',
                date_to: $('#customer-date-to').val() || ''
            };

            $list.html('<p style="padding:20px;text-align:center;"><span class="spinner is-active" style="float:none;"></span> Loading tickets...</p>');

            $.ajax({
                url: window.arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_search_tickets_customer',
                    nonce: window.arkidevsSupport.nonce,
                    search: filters.search,
                    status: filters.status,
                    priority: filters.priority,
                    tag_id: filters.tag_id,
                    date_from: filters.date_from,
                    date_to: filters.date_to
                },
                success: function(response) {
                    if (response.success && response.data && typeof response.data.html === 'string') {
                        $list.html(response.data.html);
                    } else {
                        $list.html('<p style="padding:20px;text-align:center;color:#d63638;">Error loading tickets. Please try again.</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Customer ticket search error:', status, error);
                    $list.html('<p style="padding:20px;text-align:center;color:#d63638;">Error loading tickets. Please refresh the page and try again.</p>');
                    if (typeof window.arkidevsSupport.handleAjaxError === 'function') {
                        window.arkidevsSupport.handleAjaxError(xhr, status, error);
                    }
                }
            });
        }

        // Intercept customer filter form submit to use AJAX
        $(document).on('submit', 'form[action*="/support/"]', function(e) {
            if ($(this).find('#customer-search').length) {
                e.preventDefault();
                performCustomerTicketSearch();
            }
        });

        // Trigger search when customer filter fields change
        $('#customer-search, #customer-status, #customer-priority, #customer-tag, #customer-date-from, #customer-date-to').on('change', function() {
            performCustomerTicketSearch();
        });

        // Debounced search while typing
        var customerSearchTimer = null;
        $('#customer-search').on('keyup', function() {
            clearTimeout(customerSearchTimer);
            customerSearchTimer = setTimeout(function() {
                performCustomerTicketSearch();
            }, 400);
        });
    }

    // Start initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCustomerScripts);
    } else {
        // DOM is already ready
        initCustomerScripts();
    }

})(jQuery);

