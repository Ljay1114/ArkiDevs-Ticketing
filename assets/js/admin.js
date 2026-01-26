/**
 * Admin JavaScript for Arkidevs Support
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        /**
         * ==============================
         * Ticket list search & filters
         * (Agent/Admin dashboard)
         * ==============================
         */

        function performTicketSearch() {
            var $list = $('.arkidevs-tickets-list');

            // Only run on pages that have the list container
            if (!$list.length) {
                return;
            }

            // Detect current page from URL or form action
            var pageSlug = 'arkidevs-support-agent'; // default
            var $form = $('form[action*="admin.php"]');
            if ($form.length) {
                var formAction = $form.attr('action') || '';
                var pageMatch = formAction.match(/page=([^&]+)/);
                if (pageMatch && pageMatch[1]) {
                    pageSlug = pageMatch[1];
                }
            }

            var filters = {
                search: $('#ticket-search').val() || '',
                status: $('#filter-status').val() || '',
                priority: $('#filter-priority').val() || '',
                tag_id: $('#filter-tag').val() || '0',
                agent_id: $('#filter-agent').val() || '',
                customer_id: $('#filter-customer').val() || '',
                date_from: $('#date-from').val() || '',
                date_to: $('#date-to').val() || '',
                page_slug: pageSlug
            };

            // Loading state
            $list.html('<p style="padding:20px;text-align:center;"><span class="spinner is-active" style="float:none;"></span> Loading tickets...</p>');

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_search_tickets_admin',
                    nonce: arkidevsSupport.nonce,
                    search: filters.search,
                    status: filters.status,
                    priority: filters.priority,
                    tag_id: filters.tag_id,
                    agent_id: filters.agent_id,
                    customer_id: filters.customer_id,
                    date_from: filters.date_from,
                    date_to: filters.date_to,
                    page_slug: filters.page_slug
                },
                success: function(response) {
                    if (response.success && response.data && typeof response.data.html === 'string') {
                        $list.html(response.data.html);
                    } else {
                        $list.html('<p style="padding:20px;text-align:center;color:#d63638;">Error loading tickets. Please try again.</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ticket search error:', status, error);
                    $list.html('<p style="padding:20px;text-align:center;color:#d63638;">Error loading tickets. Please refresh the page and try again.</p>');
                    if (typeof arkidevsSupport.handleAjaxError === 'function') {
                        arkidevsSupport.handleAjaxError(xhr, status, error);
                    }
                }
            });
        }

        // Intercept filter form submit to use AJAX (keep URL clean)
        $(document).on('submit', 'form[action*="arkidevs-support-agent"], form[action*="arkidevs-support-my-tickets"]', function(e) {
            if ($(this).find('#ticket-search').length) {
                e.preventDefault();
                performTicketSearch();
            }
        });

        // Trigger search when filter fields change
        $('#ticket-search, #filter-status, #filter-priority, #filter-tag, #filter-agent, #filter-customer, #date-from, #date-to').on('change', function() {
            performTicketSearch();
        });

        // Optional: debounced search on typing in search box
        var searchTimer = null;
        $('#ticket-search').on('keyup', function() {
            clearTimeout(searchTimer);
            var self = this;
            searchTimer = setTimeout(function() {
                performTicketSearch();
            }, 400);
        });

        /**
         * ==============================
         * Bulk ticket actions
         * ==============================
         */

        function getSelectedTicketIds() {
            var ids = [];
            $('.arkidevs-ticket-checkbox:checked').each(function() {
                var id = $(this).val();
                if (id) {
                    ids.push(id);
                }
            });
            return ids;
        }

        // Select / deselect all
        $(document).on('change', '#arkidevs-select-all-tickets', function() {
            var checked = $(this).is(':checked');
            $('.arkidevs-ticket-checkbox').prop('checked', checked);
        });

        // Keep master checkbox in sync when individual ones change
        $(document).on('change', '.arkidevs-ticket-checkbox', function() {
            var total = $('.arkidevs-ticket-checkbox').length;
            var selected = $('.arkidevs-ticket-checkbox:checked').length;
            $('#arkidevs-select-all-tickets').prop('checked', total > 0 && selected === total);
        });

        // Apply bulk actions (status, priority, assign, archive, delete)
        $(document).on('click', '#arkidevs-apply-bulk-actions', function(e) {
            e.preventDefault();

            var ticketIds = getSelectedTicketIds();
            if (!ticketIds.length) {
                alert('Please select at least one ticket.');
                return;
            }

            var bulkValue = $('#arkidevs-bulk-action').val();
            if (!bulkValue) {
                alert('Please choose a bulk action.');
                return;
            }

            var actionParts = bulkValue.split(':');
            var primaryAction = actionParts[0];
            var secondaryValue = actionParts.length > 1 ? actionParts[1] : '';

            var ajaxData = {
                action: 'arkidevs_bulk_update_tickets',
                nonce: arkidevsSupport.nonce,
                ticket_ids: ticketIds,
                bulk_action: ''
            };

            if (primaryAction === 'status') {
                ajaxData.bulk_action = 'status';
                ajaxData.status = secondaryValue;
            } else if (primaryAction === 'priority') {
                ajaxData.bulk_action = 'priority';
                ajaxData.priority = secondaryValue;
            } else if (primaryAction === 'assign') {
                ajaxData.bulk_action = 'assign';

                if (secondaryValue === 'self') {
                    ajaxData.agent_id = arkidevsSupport.currentUserId || 0;
                    if (!ajaxData.agent_id) {
                        alert('Unable to detect current user ID for assignment.');
                        return;
                    }
                } else {
                    var agentId = $('#arkidevs-bulk-agent').val();
                    if (!agentId || agentId === '0') {
                        alert('Please select an agent to assign to.');
                        return;
                    }
                    ajaxData.agent_id = agentId;
                }
            } else if (primaryAction === 'archive') {
                ajaxData.bulk_action = 'archive';
                if (!confirm('Are you sure you want to archive the selected tickets?')) {
                    return;
                }
            } else if (primaryAction === 'delete') {
                ajaxData.bulk_action = 'delete';
                if (!confirm('This will permanently delete the selected tickets and their data. Continue?')) {
                    return;
                }
            } else {
                alert('Invalid bulk action.');
                return;
            }

            var $btn = $(this);
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Processing...');

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    $btn.prop('disabled', false).text(originalText);
                    if (response.success && response.data) {
                        if (arkidevsSupport.showNotice) {
                            arkidevsSupport.showNotice(response.data.message || 'Bulk action completed.');
                        }
                        // Refresh the list (AJAX or full reload depending on context)
                        if (typeof performTicketSearch === 'function' && $('.arkidevs-tickets-list').length) {
                            performTicketSearch();
                        } else {
                            location.reload();
                        }
                    } else {
                        var msg = response.data && response.data.message ? response.data.message : 'Bulk action failed.';
                        if (arkidevsSupport.showNotice) {
                            arkidevsSupport.showNotice(msg, 'error');
                        } else {
                            alert(msg);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).text(originalText);
                    if (typeof arkidevsSupport.handleAjaxError === 'function') {
                        arkidevsSupport.handleAjaxError(xhr, status, error);
                    } else {
                        alert('Bulk action failed: ' + error);
                    }
                }
            });
        });

        // Bulk export selected tickets as CSV
        $(document).on('click', '#arkidevs-bulk-export', function(e) {
            e.preventDefault();

            var ticketIds = getSelectedTicketIds();
            if (!ticketIds.length) {
                alert('Please select at least one ticket to export.');
                return;
            }

            var $btn = $(this);
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Exporting...');

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_bulk_export_tickets',
                    nonce: arkidevsSupport.nonce,
                    ticket_ids: ticketIds
                },
                success: function(response) {
                    $btn.prop('disabled', false).text(originalText);
                    if (response.success && response.data && response.data.csv) {
                        var csvContent = response.data.csv;
                        var filename = response.data.filename || 'tickets-export.csv';

                        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                        var link = document.createElement('a');
                        if (link.download !== undefined) {
                            var url = URL.createObjectURL(blob);
                            link.setAttribute('href', url);
                            link.setAttribute('download', filename);
                            link.style.visibility = 'hidden';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        }
                    } else {
                        var msg = response.data && response.data.message ? response.data.message : 'Export failed.';
                        if (arkidevsSupport.showNotice) {
                            arkidevsSupport.showNotice(msg, 'error');
                        } else {
                            alert(msg);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).text(originalText);
                    if (typeof arkidevsSupport.handleAjaxError === 'function') {
                        arkidevsSupport.handleAjaxError(xhr, status, error);
                    } else {
                        alert('Export failed: ' + error);
                    }
                }
            });
        });

        /**
         * ==============================
         * Live comments refresh (agent)
         * ==============================
         *
         * Periodically fetches the ticket page HTML and replaces only the
         * .replies-thread content so other users see new comments without
         * refreshing the page. Runs only on the single ticket view.
         */
        (function setupLiveRepliesRefresh() {
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
                                // Preserve current reply form content
                                $thread.html(newHtml);
                                lastHtml = newHtml;
                            }
                        }
                    } catch (e) {
                        console.error('Error refreshing replies:', e);
                    }
                });
            }, 10000); // every 10 seconds
        })();

        // Update ticket status
        $('#ticket-status').on('change', function() {
            var ticketId = $(this).data('ticket-id');
            var status = $(this).val();
            
            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_update_ticket',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message);
                        // Update status badge dynamically
                        if (response.data.status && response.data.status_label && response.data.status_class) {
                            $('.status-badge').removeClass().addClass('status-badge ' + response.data.status_class)
                                .text(response.data.status_label);
                            $('#ticket-status').val(response.data.status);
                        }
                    } else {
                        arkidevsSupport.showNotice(response.data.message, 'error');
                    }
                },
                error: arkidevsSupport.handleAjaxError
            });
        });

        // Agents: take ticket (assign to self)
        $('#take-ticket').on('click', function() {
            var ticketId = $(this).data('ticket-id');
            var agentId = $(this).data('agent-id');
            var $btn = $(this);

            if (!agentId || !ticketId) {
                arkidevsSupport.showNotice('Unable to take this ticket. Missing data.', 'error');
                return;
            }

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_assign_ticket',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId,
                    agent_id: agentId
                },
                success: function(response) {
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message);
                        // Update agent badge
                        if (response.data.agent_id && response.data.agent_name) {
                            $('.agent-badge')
                                .removeClass('unassigned')
                                .html(arkidevsSupport.i18n.assignedTo + ' <strong>' + response.data.agent_name + '</strong>');
                        }
                        // Disable the button after taking
                        $btn.prop('disabled', true).addClass('button-disabled');
                    } else {
                        arkidevsSupport.showNotice(response.data.message, 'error');
                    }
                },
                error: arkidevsSupport.handleAjaxError
            });
        });

        // Update ticket priority
        $('#ticket-priority').on('change', function() {
            var ticketId = $(this).data('ticket-id');
            var priority = $(this).val();
            
            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_update_ticket',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId,
                    priority: priority
                },
                success: function(response) {
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message);
                        // Update priority badge dynamically
                        if (response.data.priority && response.data.priority_label && response.data.priority_class) {
                            $('.priority-badge').removeClass().addClass('priority-badge ' + response.data.priority_class)
                                .text(response.data.priority_label);
                            $('#ticket-priority').val(response.data.priority);
                        }
                    } else {
                        arkidevsSupport.showNotice(response.data.message, 'error');
                    }
                },
                error: arkidevsSupport.handleAjaxError
            });
        });

        // Assign ticket to agent
        $('#ticket-agent').on('change', function() {
            var ticketId = $(this).data('ticket-id');
            var agentId = $(this).val();
            
            if (!agentId || agentId === '0') {
                return;
            }
            
            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_assign_ticket',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId,
                    agent_id: agentId
                },
                success: function(response) {
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message);
                        // Update agent assignment dynamically
                        if (response.data.agent_id && response.data.agent_name) {
                            $('.agent-badge').html(
                                arkidevsSupport.i18n.assignedTo + ' <strong>' + response.data.agent_name + '</strong>'
                            ).removeClass('unassigned');
                        } else {
                            $('.agent-badge').html(arkidevsSupport.i18n.unassigned).addClass('unassigned');
                        }
                    } else {
                        arkidevsSupport.showNotice(response.data.message, 'error');
                    }
                },
                error: arkidevsSupport.handleAjaxError
            });
        });

        // Start timer
        $('#start-timer').on('click', function() {
            var ticketId = $(this).data('ticket-id');
            var $btn = $(this);
            
            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_start_timer',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId
                },
                success: function(response) {
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message);
                        $btn.addClass('hidden');
                        $('#stop-timer').removeClass('hidden');
                        // Optionally update time display if available
                        if (response.data.time_spent) {
                            $('.time-tracking p strong').next().text(response.data.time_spent);
                        }
                    } else {
                        arkidevsSupport.showNotice(response.data.message, 'error');
                    }
                },
                error: arkidevsSupport.handleAjaxError
            });
        });

        // Stop timer
        $('#stop-timer').on('click', function() {
            var ticketId = $(this).data('ticket-id');
            var $btn = $(this);
            
            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_stop_timer',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId
                },
                success: function(response) {
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message);
                        $btn.addClass('hidden');
                        $('#start-timer').removeClass('hidden');
                        // Update time display dynamically
                        if (response.data.time_spent) {
                            $('.time-tracking p strong').next().text(response.data.time_spent);
                        } else {
                            // Reload only if time data not available
                            setTimeout(function() {
                                location.reload();
                            }, 500);
                        }
                    } else {
                        arkidevsSupport.showNotice(response.data.message, 'error');
                    }
                },
                error: arkidevsSupport.handleAjaxError
            });
        });

        // Close ticket
        $('#close-ticket').on('click', function() {
            if (!confirm('Are you sure you want to close this ticket?')) {
                return;
            }
            
            var ticketId = $(this).data('ticket-id');
            
            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_close_ticket',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId
                },
                success: function(response) {
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message);
                        location.reload();
                    } else {
                        arkidevsSupport.showNotice(response.data.message, 'error');
                    }
                },
                error: arkidevsSupport.handleAjaxError
            });
        });

        // Merge ticket into another
        $('#merge-ticket').on('click', function() {
            var sourceTicketId = $(this).data('source-ticket-id');
            var targetTicket = $('#merge-target-ticket').val();

            if (!targetTicket) {
                arkidevsSupport.showNotice('Please enter a target Ticket ID or Ticket Number.', 'error');
                return;
            }

            if (!confirm('Merge this ticket into ' + targetTicket + '? This will move all comments/history and hide the source ticket.')) {
                return;
            }

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_merge_tickets',
                    nonce: arkidevsSupport.nonce,
                    source_ticket_id: sourceTicketId,
                    target_ticket: targetTicket
                },
                success: function(response) {
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message);
                        // Redirect to target ticket view
                        var currentUrl = window.location.href;
                        var newUrl = currentUrl.replace(/([?&])ticket_id=\d+/, '$1ticket_id=' + response.data.target_ticket_id);
                        if (newUrl === currentUrl) {
                            // ticket_id param not found, append it
                            var sep = currentUrl.indexOf('?') === -1 ? '?' : '&';
                            newUrl = currentUrl + sep + 'ticket_id=' + response.data.target_ticket_id;
                        }
                        window.location.href = newUrl;
                    } else {
                        arkidevsSupport.showNotice(response.data.message || 'Merge failed.', 'error');
                    }
                },
                error: arkidevsSupport.handleAjaxError
            });
        });

        // Reply to specific comment
        $('.reply-item').on('click', '.reply-to-comment', function() {
            var $btn = $(this);
            var replyId = $btn.data('reply-id');
            var authorName = $btn.data('author-name');
            var $replyItem = $btn.closest('.reply-item');
            
            // Set parent reply ID
            $('#parent-reply-id').val(replyId);
            
            // Scroll to reply form
            $('html, body').animate({
                scrollTop: $('#main-reply-form').offset().top - 100
            }, 300);
            
            // Focus textarea and optionally add mention
            var $textarea = $('#reply-message');
            var currentText = $textarea.val().trim();
            if (!currentText || currentText.indexOf('@' + authorName) === -1) {
                var mention = '@' + authorName + ' ';
                $textarea.val(mention + currentText).trigger('focus');
                // Move cursor to end
                var len = $textarea.val().length;
                $textarea[0].setSelectionRange(len, len);
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

        // Add reply (supports nested replies)
        $('#submit-reply').on('click', function(e) {
            e.preventDefault();
            console.log('Submit reply button clicked'); // Debug
            
            var $btn = $(this);
            var ticketId = $btn.data('ticket-id');
            var message = $('#reply-message').val().trim();
            var parentReplyId = $('#parent-reply-id').val();
            var isInternal = $('#reply-is-internal').is(':checked');
            
            console.log('Ticket ID:', ticketId, 'Message:', message, 'Parent Reply ID:', parentReplyId); // Debug
            
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
                nonce: arkidevsSupport.nonce,
                ticket_id: ticketId,
                message: message
            };
            
            if (parentReplyId) {
                ajaxData.parent_reply_id = parentReplyId;
            }

            if (isInternal) {
                ajaxData.is_internal = 1;
            }
            
            console.log('Sending AJAX request:', ajaxData); // Debug
            
            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    console.log('Add reply response:', response); // Debug
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message);
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
                                if (!$newComment.length || !$newComment.is('.reply-item')) {
                                    console.error('Invalid comment HTML structure:', response.data.comment_html);
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
                                            var $replyForm = $('#main-reply-form');
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
                    console.error('AJAX error:', xhr, status, error); // Debug
                    console.error('Response:', xhr.responseText); // Debug
                    arkidevsSupport.handleAjaxError(xhr, status, error);
                    
                    // Re-enable button on error
                    $btn.prop('disabled', false)
                        .text(originalText)
                        .removeClass('button-disabled');
                }
            });
        });

        /**
         * ==============================
         * Related tickets
         * ==============================
         */
        $(document).on('click', '#add-related-ticket', function() {
            var ticketId = $(this).data('ticket-id');
            var relatedInput = $('#related-ticket-input').val();
            var relationType = $('#related-type').val() || 'related';
            var $btn = $(this);

            if (!relatedInput) {
                arkidevsSupport.showNotice('Enter a ticket ID or Ticket Number to relate.', 'error');
                return;
            }

            $btn.prop('disabled', true).text('Adding...');

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_add_related_ticket',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId,
                    related_ticket: relatedInput,
                    relation_type: relationType
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Add Related');
                    if (response.success && response.data && response.data.html) {
                        $('#related-tickets-list').html(response.data.html);
                        $('#related-ticket-input').val('');
                        arkidevsSupport.showNotice(response.data.message);
                    } else {
                        arkidevsSupport.showNotice(response.data ? response.data.message : 'Failed to add related ticket.', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Add Related');
                    arkidevsSupport.handleAjaxError();
                }
            });
        });

        $(document).on('click', '.remove-related-ticket', function() {
            var $btn = $(this);
            var ticketId = $btn.data('ticket-id');
            var relatedId = $btn.data('related-id');
            var $li = $btn.closest('li');

            if (!confirm('Remove this related ticket link?')) {
                return;
            }

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_remove_related_ticket',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId,
                    related_ticket_id: relatedId
                },
                success: function(response) {
                    if (response.success && response.data && response.data.html) {
                        $('#related-tickets-list').html(response.data.html);
                        arkidevsSupport.showNotice(response.data.message);
                    } else {
                        arkidevsSupport.showNotice(response.data ? response.data.message : 'Failed to remove relation.', 'error');
                    }
                },
                error: arkidevsSupport.handleAjaxError
            });
        });

        // Edit comment
        $('.reply-item').on('click', '.edit-reply', function() {
            var $btn = $(this);
            var ticketId = $btn.data('ticket-id');
            var replyId = $btn.data('reply-id');
            var $item = $btn.closest('.reply-item');
            var $textDiv = $item.find('.reply-text');
            var currentHtml = $textDiv.html();
            var currentText = $textDiv.text().trim();

            // If already in edit mode, do nothing
            if ($item.data('editing')) {
                return;
            }
            $item.data('editing', true);

            // Build simple inline editor
            var editorHtml = '<textarea class="reply-edit-text" style="width:100%;min-height:80px;margin-bottom:8px;">' +
                currentText.replace(/</g, '&lt;').replace(/>/g, '&gt;') +
                '</textarea>' +
                '<div class="reply-edit-actions">' +
                '<button type="button" class="button button-primary save-reply-edit">Save</button> ' +
                '<button type="button" class="button reply-cancel-edit">Cancel</button>' +
                '</div>';

            $textDiv.data('original-html', currentHtml).html(editorHtml);

            // Handle save
            $item.find('.save-reply-edit').on('click', function() {
                var newMessage = $item.find('.reply-edit-text').val().trim();
                if (!newMessage) {
                    arkidevsSupport.showNotice('Please enter a comment.', 'error');
                    return;
                }

                $.ajax({
                    url: arkidevsSupport.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'arkidevs_update_reply',
                        nonce: arkidevsSupport.nonce,
                        ticket_id: ticketId,
                        reply_id: replyId,
                        message: newMessage
                    },
                    success: function(response) {
                        console.log('Update reply response:', response); // Debug
                        if (response.success && response.data) {
                            arkidevsSupport.showNotice(response.data.message);
                            
                            // Update comment content dynamically
                            if (response.data.comment_html) {
                                try {
                                    var $updatedComment = $(response.data.comment_html);
                                    var replyId = response.data.reply_id;
                                    var $existingItem = $item.closest('.reply-item');
                                    
                                    if ($existingItem.length) {
                                        // Replace the content while preserving structure
                                        var $textDiv = $existingItem.find('.reply-content .reply-text');
                                        if ($textDiv.length) {
                                            $textDiv.html($updatedComment.find('.reply-text').html());
                                        }
                                        
                                        var $dateSpan = $existingItem.find('.reply-date');
                                        if ($dateSpan.length) {
                                            $dateSpan.html($updatedComment.find('.reply-date').html());
                                        }
                                        
                                        // Exit edit mode - remove editor and restore view
                                        $item.find('.reply-edit-text, .reply-edit-actions').remove();
                                        $item.data('editing', false);
                                    } else {
                                        console.error('Existing item not found');
                                        location.reload();
                                    }
                                } catch (e) {
                                    console.error('Error updating comment:', e);
                                    location.reload();
                                }
                            } else {
                                console.warn('No comment_html in response, reloading');
                                // Fallback to reload
                                setTimeout(function() {
                                    location.reload();
                                }, 300);
                            }
                        } else {
                            arkidevsSupport.showNotice(response.data ? response.data.message : 'An error occurred', 'error');
                        }
                    },
                    error: arkidevsSupport.handleAjaxError
                });
            });

            // Handle cancel
            $item.find('.reply-cancel-edit').on('click', function() {
                var original = $textDiv.data('original-html');
                $textDiv.html(original);
                $item.data('editing', false);
            });
        });

        // Delete comment
        $('.reply-item').on('click', '.delete-reply', function() {
            if (!confirm('Are you sure you want to delete this comment?')) {
                return;
            }

            var $btn = $(this);
            var ticketId = $btn.data('ticket-id');
            var replyId = $btn.data('reply-id');

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_delete_reply',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId,
                    reply_id: replyId
                },
                success: function(response) {
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message);
                        // Remove from DOM
                        $btn.closest('.reply-item').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        arkidevsSupport.showNotice(response.data.message, 'error');
                    }
                },
                error: arkidevsSupport.handleAjaxError
            });
        });

        // Reply to a specific comment (pre-fill mention and focus textarea)
        $('.reply-item').on('click', '.reply-to-comment', function() {
            var $item = $(this).closest('.reply-item');
            var authorName = $item.data('author-name') || $item.find('.reply-author-name').text().trim();

            var $textarea = $('#reply-message');
            var prefix = '@' + authorName + ' ';

            // If textarea is empty or doesn't already start with this mention, prepend it
            var current = $textarea.val();
            if (!current) {
                $textarea.val(prefix);
            } else if (current.indexOf(prefix) !== 0) {
                $textarea.val(prefix + current);
            }

            $('html, body').animate({
                scrollTop: $textarea.offset().top - 100
            }, 200);

            $textarea.trigger('focus');
        });

        // View more replies (use document delegation for dynamically added elements)
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

        // Hide replies (use document delegation for dynamically added elements)
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

        // Save ticket details (subject & description)
        $('#save-ticket-details').on('click', function() {
            var ticketId = $(this).data('ticket-id');
            var subject = $('#ticket-edit-subject').val().trim();
            var description = $('#ticket-edit-description').val().trim();
            var $btn = $(this);

            if (!subject || !description) {
                arkidevsSupport.showNotice('Subject and description are required.', 'error');
                return;
            }

            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_update_ticket',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId,
                    subject: subject,
                    description: description
                },
                success: function(response) {
                    console.log('Update ticket response:', response); // Debug
                    if (response.success && response.data) {
                        arkidevsSupport.showNotice(response.data.message);
                        
                        // Update subject and description dynamically
                        if (response.data.subject) {
                            var $header = $('.ticket-header h2');
                            var currentText = $header.text();
                            var ticketNumber = currentText.match(/#\d+/);
                            if (ticketNumber) {
                                $header.html(ticketNumber[0] + ' - ' + response.data.subject);
                            } else {
                                $header.html('#' + ticketId + ' - ' + response.data.subject);
                            }
                            $('#ticket-edit-subject').val(response.data.subject);
                        }
                        
                        if (response.data.description) {
                            $('.ticket-description .description-content').html(response.data.description);
                            $('#ticket-edit-description').val(response.data.description);
                        }
                        
                        $btn.prop('disabled', false).text('Save Changes');
                    } else {
                        arkidevsSupport.showNotice(response.data ? response.data.message : 'An error occurred', 'error');
                        $btn.prop('disabled', false).text('Save Changes');
                    }
                },
                error: function() {
                    arkidevsSupport.handleAjaxError();
                    $btn.prop('disabled', false).text('Save Changes');
                }
            });
        });

        /**
         * ==============================
         * Create Ticket Form (Admin/Agent)
         * ==============================
         */

        // Show/hide ticket form
        $('#show-ticket-form').on('click', function() {
            $('#ticket-form').removeClass('hidden').slideDown();
            $(this).addClass('hidden');
        });

        $('#cancel-ticket-form').on('click', function() {
            $('#ticket-form').addClass('hidden').slideUp();
            $('#show-ticket-form').removeClass('hidden');
            $('#create-ticket-form')[0].reset();
        });

        // Create ticket form submission
        $(document).on('submit', '#create-ticket-form', function(e) {
            e.preventDefault();
            
            var formData = {
                action: 'arkidevs_create_ticket',
                nonce: arkidevsSupport.nonce,
                subject: $('#ticket-subject').val().trim(),
                description: $('#ticket-description').val().trim(),
                priority: $('#ticket-priority').val() || 'medium'
            };

            // For admin: include customer_id if provided
            var customerId = $('#ticket-customer').val();
            if (customerId) {
                // Override customer_id for admin-created tickets
                formData.customer_id = customerId;
            }

            // Get selected tags
            var selectedTags = [];
            $('#ticket-tags option:selected').each(function() {
                selectedTags.push($(this).val());
            });
            if (selectedTags.length > 0) {
                formData.tags = selectedTags;
            }

            // Validation
            if (!formData.subject) {
                arkidevsSupport.showNotice('Subject is required.', 'error');
                return false;
            }
            if (!formData.description) {
                arkidevsSupport.showNotice('Description is required.', 'error');
                return false;
            }
            // Only validate customer selection if the field exists (admin dashboard)
            if ($('#ticket-customer').length && !customerId) {
                arkidevsSupport.showNotice('Please select a customer.', 'error');
                return false;
            }

            // Show loading state
            var $submitBtn = $(this).find('button[type="submit"]');
            var originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).text('Creating...');

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    $submitBtn.prop('disabled', false).text(originalText);
                    if (response.success) {
                        arkidevsSupport.showNotice(response.data.message || 'Ticket created successfully!');
                        $('#create-ticket-form')[0].reset();
                        $('#ticket-form').addClass('hidden').slideUp();
                        $('#show-ticket-form').removeClass('hidden');
                        
                        // Reload page to show new ticket
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        arkidevsSupport.showNotice(response.data ? response.data.message : 'Failed to create ticket.', 'error');
                    }
                },
                error: function() {
                    arkidevsSupport.handleAjaxError();
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });

        /**
         * ==============================
         * Tag Management
         * ==============================
         */

        // Add tag to ticket
        $(document).on('click', '#add-tag-btn', function() {
            var $btn = $(this);
            var ticketId = $btn.data('ticket-id');
            var tagId = $('#add-tag-select').val();

            if (!tagId || tagId === '0') {
                alert('Please select a tag to add.');
                return;
            }

            $btn.prop('disabled', true);

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_add_tag_to_ticket',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId,
                    tag_id: tagId
                },
                success: function(response) {
                    if (response.success && response.data.tag) {
                        var tag = response.data.tag;
                        var $tagItem = $('<span class="ticket-tag-item" data-tag-id="' + tag.id + '" style="display: inline-block; padding: 4px 8px; margin: 5px 5px 5px 0; background-color: ' + tag.color + '; color: #fff; border-radius: 3px; font-size: 12px;">' +
                            escapeHtml(tag.name) +
                            ' <button type="button" class="remove-tag" data-ticket-id="' + ticketId + '" data-tag-id="' + tag.id + '" style="margin-left: 5px; background: rgba(255,255,255,0.3); border: none; color: #fff; cursor: pointer; border-radius: 2px; padding: 2px 5px;">×</button>' +
                            '</span>');
                        $('#ticket-tags-display').append($tagItem);
                        $('#add-tag-select option[value="' + tagId + '"]').remove();
                        $('#add-tag-select').val('');
                        arkidevsSupport.showNotice(response.data.message);
                    } else {
                        arkidevsSupport.showNotice(response.data ? response.data.message : 'Failed to add tag', 'error');
                    }
                    $btn.prop('disabled', false);
                },
                error: function() {
                    arkidevsSupport.handleAjaxError();
                    $btn.prop('disabled', false);
                }
            });
        });

        // Remove tag from ticket
        $(document).on('click', '.remove-tag', function() {
            var $btn = $(this);
            var ticketId = $btn.data('ticket-id');
            var tagId = $btn.data('tag-id');
            var $tagItem = $btn.closest('.ticket-tag-item');

            if (!confirm('Are you sure you want to remove this tag?')) {
                return;
            }

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_remove_tag_from_ticket',
                    nonce: arkidevsSupport.nonce,
                    ticket_id: ticketId,
                    tag_id: tagId
                },
                success: function(response) {
                    if (response.success) {
                        $tagItem.fadeOut(300, function() {
                            $(this).remove();
                            // Reload page to refresh tag list in select
                            location.reload();
                        });
                        arkidevsSupport.showNotice(response.data.message);
                    } else {
                        arkidevsSupport.showNotice(response.data ? response.data.message : 'Failed to remove tag', 'error');
                    }
                },
                error: function() {
                    arkidevsSupport.handleAjaxError();
                }
            });
        });

        /**
         * ==============================
         * Template Management
         * ==============================
         */

        // Insert template into reply form
        $('#insert-template').on('click', function() {
            var $btn = $(this);
            var templateId = $('#template-selector').val();
            var ticketId = $btn.data('ticket-id');

            if (!templateId || templateId === '') {
                alert('Please select a template.');
                return;
            }

            $btn.prop('disabled', true).text('Loading...');

            $.ajax({
                url: arkidevsSupport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arkidevs_get_template_content',
                    nonce: arkidevsSupport.nonce,
                    template_id: templateId,
                    ticket_id: ticketId
                },
                success: function(response) {
                    if (response.success && response.data && response.data.content) {
                        var $textarea = $('#reply-message');
                        var currentContent = $textarea.val().trim();
                        var templateContent = response.data.content;

                        // If there's existing content, add template on a new line
                        if (currentContent) {
                            $textarea.val(currentContent + '\n\n' + templateContent);
                        } else {
                            $textarea.val(templateContent);
                        }

                        // Focus textarea and move cursor to end
                        $textarea.focus();
                        var len = $textarea.val().length;
                        $textarea[0].setSelectionRange(len, len);

                        // Reset selector
                        $('#template-selector').val('');

                        arkidevsSupport.showNotice('Template "' + response.data.template_name + '" inserted successfully.');
                    } else {
                        arkidevsSupport.showNotice(response.data ? response.data.message : 'Failed to load template.', 'error');
                    }
                    $btn.prop('disabled', false).text('Insert');
                },
                error: function() {
                    arkidevsSupport.handleAjaxError();
                    $btn.prop('disabled', false).text('Insert');
                }
            });
        });

        /**
         * ==============================
         * Analytics (admin dashboard)
         * ==============================
         */

        function loadChartJs() {
            return new Promise(function(resolve, reject) {
                if (window.Chart) {
                    resolve();
                    return;
                }
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                script.onload = function() { resolve(); };
                script.onerror = function() { reject(new Error('Failed to load Chart.js')); };
                document.head.appendChild(script);
            });
        }

        function renderLineChart(ctx, labels, datasets) {
            if (!ctx) { return; }
            return new Chart(ctx, {
                type: 'line',
                data: { labels: labels, datasets: datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        function renderBarChart(ctx, labels, datasets) {
            if (!ctx) { return; }
            return new Chart(ctx, {
                type: 'bar',
                data: { labels: labels, datasets: datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        function formatDateLabels(dataObj) {
            return Object.keys(dataObj).sort();
        }

        function buildGroupedDataset(dataObj, palette) {
            var labels = formatDateLabels(dataObj);
            var groups = {};
            labels.forEach(function(day) {
                var entries = dataObj[day];
                Object.keys(entries).forEach(function(groupKey) {
                    if (!groups[groupKey]) {
                        groups[groupKey] = [];
                    }
                });
            });

            Object.keys(groups).forEach(function(groupKey) {
                groups[groupKey] = labels.map(function(day) {
                    return dataObj[day] && dataObj[day][groupKey] ? dataObj[day][groupKey] : 0;
                });
            });

            var colors = palette || ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9'];
            var datasets = [];
            var idx = 0;
            Object.keys(groups).forEach(function(groupKey) {
                datasets.push({
                    label: groupKey,
                    data: groups[groupKey],
                    borderColor: colors[idx % colors.length],
                    backgroundColor: colors[idx % colors.length],
                    tension: 0.2,
                    fill: false
                });
                idx++;
            });

            return { labels: labels, datasets: datasets };
        }

        function initAnalyticsDashboard() {
            var $analytics = $('.arkidevs-analytics');
            if (!$analytics.length) {
                return;
            }

            loadChartJs().then(function() {
                $.ajax({
                    url: arkidevsSupport.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'arkidevs_get_analytics',
                        nonce: arkidevsSupport.nonce,
                        days: 30
                    },
                    success: function(response) {
                        if (!response.success || !response.data) {
                            arkidevsSupport.showNotice('Unable to load analytics data.', 'error');
                            return;
                        }

                        var data = response.data;

                        // Avg resolution
                        if (typeof data.avg_resolution_hours !== 'undefined' && data.avg_resolution_hours !== null) {
                            $('#arkidevs-avg-resolution').text(data.avg_resolution_hours + 'h');
                        } else {
                            $('#arkidevs-avg-resolution').text('—');
                        }

                        // CSAT averages
                        var csatAvg = '—';
                        var csatCount = 0;
                        var csatSum = 0;
                        if (data.csat_trend) {
                            Object.keys(data.csat_trend).forEach(function(day) {
                                csatSum += parseFloat(data.csat_trend[day].avg_rating || 0) * parseInt(data.csat_trend[day].total || 0, 10);
                                csatCount += parseInt(data.csat_trend[day].total || 0, 10);
                            });
                            if (csatCount > 0) {
                                csatAvg = (csatSum / csatCount).toFixed(2);
                            }
                        }
                        $('#arkidevs-csat-avg').text(csatAvg);
                        $('#arkidevs-csat-count').text(csatCount ? csatCount + ' responses' : 'No responses yet');

                        // Resolved total
                        var resolvedTotal = 0;
                        if (Array.isArray(data.agent_performance)) {
                            data.agent_performance.forEach(function(agent) {
                                resolvedTotal += parseInt(agent.resolved || 0, 10);
                            });
                        }
                        $('#arkidevs-resolved-total').text(resolvedTotal);

                        // Charts: status
                        var statusData = buildGroupedDataset(data.tickets_by_status || {});
                        renderLineChart(document.getElementById('arkidevs-status-chart'), statusData.labels, statusData.datasets);

                        // Charts: priority
                        var priorityData = buildGroupedDataset(data.tickets_by_priority || {});
                        renderLineChart(document.getElementById('arkidevs-priority-chart'), priorityData.labels, priorityData.datasets);

                        // Agent performance chart
                        if (Array.isArray(data.agent_performance)) {
                            var agentLabels = data.agent_performance.map(function(a) { return a.agent_name; });
                            var resolved = data.agent_performance.map(function(a) { return a.resolved || 0; });
                            var timeLogged = data.agent_performance.map(function(a) { return a.time_logged || 0; });

                            renderBarChart(document.getElementById('arkidevs-agent-chart'), agentLabels, [
                                { label: 'Resolved', data: resolved, backgroundColor: '#2563eb' },
                                { label: 'Hours Logged', data: timeLogged, backgroundColor: '#10b981' }
                            ]);
                        }

                        // CSAT trend
                        if (data.csat_trend) {
                            var csatLabels = formatDateLabels(data.csat_trend);
                            var csatValues = csatLabels.map(function(day) { return data.csat_trend[day].avg_rating || 0; });
                            renderLineChart(document.getElementById('arkidevs-csat-chart'), csatLabels, [
                                { label: 'Avg rating', data: csatValues, borderColor: '#f59e0b', backgroundColor: '#f59e0b', tension: 0.2, fill: false }
                            ]);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Analytics fetch failed', status, error);
                        arkidevsSupport.handleAjaxError(xhr, status, error);
                    }
                });
            }).catch(function(err) {
                console.error(err);
                arkidevsSupport.showNotice('Analytics library failed to load.', 'error');
            });
        }

        initAnalyticsDashboard();
    });

})(jQuery);


