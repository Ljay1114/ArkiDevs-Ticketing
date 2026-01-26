<?php
/**
 * Customer ticket view template
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-time-calculator.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket-rating.php';

// Get ticket ID from query var (for public page) or GET parameter (for shortcode)
$ticket_id = get_query_var( 'arkidevs_ticket_id' );
if ( ! $ticket_id && isset( $_GET['ticket_id'] ) ) {
    $ticket_id = intval( $_GET['ticket_id'] );
} else {
    $ticket_id = intval( $ticket_id );
}

$ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );

// For the public customer view, if the ticket exists, show it.
// Access to /support/ is already restricted to logged-in customers/agents/admins.
if ( ! $ticket ) {
    echo '<p>' . esc_html__( 'Ticket not found.', 'arkidevs-support' ) . '</p>';
    return;
}

$replies         = $ticket->get_replies();
$ticket_time     = Arkidevs_Support_Time_Calculator::calculate_ticket_time( $ticket_id );
$ticket_rating   = Arkidevs_Support_Ticket_Rating::get_by_ticket( $ticket_id );
?>

<div class="arkidevs-support-customer-ticket">
    <h1>
        <a href="<?php echo esc_url( home_url( '/support/' ) ); ?>">← <?php esc_html_e( 'Back to My Tickets', 'arkidevs-support' ); ?></a>
    </h1>
    
    <div class="ticket-header">
        <h2>#<?php echo esc_html( $ticket->ticket_number ); ?> - <?php echo esc_html( $ticket->subject ); ?></h2>
        <div class="ticket-meta">
            <span class="status-badge <?php echo esc_attr( Arkidevs_Support_Status::get_status_class( $ticket->status ) ); ?>">
                <?php echo esc_html( Arkidevs_Support_Status::get_status_label( $ticket->status ) ); ?>
            </span>
            <span class="priority-badge <?php echo esc_attr( Arkidevs_Support_Priority::get_priority_class( $ticket->priority ) ); ?>">
                <?php echo esc_html( Arkidevs_Support_Priority::get_priority_label( $ticket->priority ) ); ?>
            </span>
            <?php
            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
            $tags = $ticket->get_tags();
            if ( ! empty( $tags ) ) :
                ?>
                <div class="ticket-tags" style="display: inline-block; margin-left: 10px;">
                    <?php foreach ( $tags as $tag ) : ?>
                        <span class="ticket-tag" style="display: inline-block; padding: 4px 8px; margin-right: 5px; background-color: <?php echo esc_attr( $tag->color ); ?>; color: #fff; border-radius: 3px; font-size: 12px;">
                            <?php echo esc_html( $tag->name ); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <span class="ticket-date">
                <?php esc_html_e( 'Created:', 'arkidevs-support' ); ?> 
                <?php echo Arkidevs_Support_Utils::format_datetime_with_relative( $ticket->created_at ); ?>
            </span>
            <?php if ( ! empty( $ticket->updated_at ) && $ticket->updated_at !== $ticket->created_at ) : ?>
                <span class="ticket-date">
                    <?php esc_html_e( 'Updated:', 'arkidevs-support' ); ?> 
                    <?php echo Arkidevs_Support_Utils::format_datetime_with_relative( $ticket->updated_at ); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="time-tracking">
        <p><strong><?php esc_html_e( 'Time Spent:', 'arkidevs-support' ); ?></strong> <?php echo esc_html( Arkidevs_Support_Utils::format_duration( $ticket_time ) ); ?></p>
    </div>

    <div class="ticket-description">
        <h3><?php esc_html_e( 'Description', 'arkidevs-support' ); ?></h3>
        <div class="description-content">
            <?php echo wp_kses_post( wpautop( $ticket->description ) ); ?>
        </div>
    </div>

    <div class="ticket-replies">
        <h3><?php esc_html_e( 'Comments', 'arkidevs-support' ); ?></h3>
        
        <div class="replies-thread">
        <?php
        /**
         * Recursive renderer for customer comments (shows nested replies and reply buttons)
         */
        function render_customer_comment_thread( $reply, $ticket, $depth = 0 ) {
            if ( ! $ticket ) {
                return;
            }

            $is_nested   = $depth > 0;
            $author_name = Arkidevs_Support_Utils::get_user_display_name( $reply->user_id );
            ?>
            <div class="reply-item <?php echo $is_nested ? 'reply-nested' : ''; ?>" data-reply-id="<?php echo esc_attr( $reply->id ); ?>" data-depth="<?php echo esc_attr( $depth ); ?>">
                <?php if ( $is_nested ) : ?>
                    <div class="reply-thread-line"></div>
                <?php endif; ?>

                <div class="reply-author">
                    <strong><?php echo esc_html( $author_name ); ?></strong>
                    <span class="reply-date"><?php echo Arkidevs_Support_Utils::format_datetime_with_relative( $reply->created_at ); ?></span>
                </div>

                <div class="reply-content">
                    <?php echo wp_kses_post( wpautop( $reply->message ) ); ?>
                </div>

                <?php if ( $ticket && 'closed' !== $ticket->status ) : ?>
                    <div class="reply-actions">
                        <button type="button"
                                class="button-link reply-to-comment"
                                data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>"
                                data-reply-id="<?php echo esc_attr( $reply->id ); ?>"
                                data-author-name="<?php echo esc_attr( $author_name ); ?>">
                            <?php esc_html_e( 'Reply', 'arkidevs-support' ); ?>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $reply->children ) ) : ?>
                    <div class="reply-children" data-parent-reply-id="<?php echo esc_attr( $reply->id ); ?>">
                        <?php foreach ( $reply->children as $child_reply ) : ?>
                            <?php render_customer_comment_thread( $child_reply, $ticket, $depth + 1 ); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

        // Render all top-level comments with full nesting
        foreach ( $replies as $reply ) {
            render_customer_comment_thread( $reply, $ticket, 0 );
        }
        ?>
        </div><!-- .replies-thread -->

        <?php if ( $ticket && 'closed' !== $ticket->status ) : ?>
            <div class="reply-form" id="main-reply-form">
                <h4><?php esc_html_e( 'Add Comment', 'arkidevs-support' ); ?></h4>
                <textarea id="reply-message" rows="5" style="width: 100%;"></textarea>
                <input type="hidden" id="parent-reply-id" value="" />
                <button type="button" id="submit-reply" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Submit Comment', 'arkidevs-support' ); ?>
                </button>
                <button type="button" id="cancel-reply" class="button" style="display: none;">
                    <?php esc_html_e( 'Cancel', 'arkidevs-support' ); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ( 'closed' === $ticket->status ) : ?>
        <div class="ticket-rating" style="margin-top: 25px; padding: 15px; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc;">
            <h3><?php esc_html_e( 'Rate Your Support Experience', 'arkidevs-support' ); ?></h3>
            <?php if ( $ticket_rating ) : ?>
                <p><strong><?php esc_html_e( 'Your rating:', 'arkidevs-support' ); ?></strong> <?php echo esc_html( $ticket_rating->rating ); ?> / 5</p>
                <?php if ( ! empty( $ticket_rating->feedback ) ) : ?>
                    <p><strong><?php esc_html_e( 'Feedback:', 'arkidevs-support' ); ?></strong> <?php echo esc_html( $ticket_rating->feedback ); ?></p>
                <?php endif; ?>
                <p class="description"><?php esc_html_e( 'Thank you for sharing your feedback.', 'arkidevs-support' ); ?></p>
            <?php else : ?>
                <p class="description"><?php esc_html_e( 'How satisfied are you with the resolution of this ticket?', 'arkidevs-support' ); ?></p>
                <label for="arkidevs-rating-score"><strong><?php esc_html_e( 'Rating (1-5):', 'arkidevs-support' ); ?></strong></label>
                <select id="arkidevs-rating-score" style="display: block; margin: 8px 0;">
                    <option value=""><?php esc_html_e( 'Select…', 'arkidevs-support' ); ?></option>
                    <option value="5">5 - <?php esc_html_e( 'Excellent', 'arkidevs-support' ); ?></option>
                    <option value="4">4 - <?php esc_html_e( 'Good', 'arkidevs-support' ); ?></option>
                    <option value="3">3 - <?php esc_html_e( 'Okay', 'arkidevs-support' ); ?></option>
                    <option value="2">2 - <?php esc_html_e( 'Poor', 'arkidevs-support' ); ?></option>
                    <option value="1">1 - <?php esc_html_e( 'Very Poor', 'arkidevs-support' ); ?></option>
                </select>
                <label for="arkidevs-rating-feedback"><strong><?php esc_html_e( 'Optional feedback:', 'arkidevs-support' ); ?></strong></label>
                <textarea id="arkidevs-rating-feedback" rows="4" style="width: 100%;"></textarea>
                <button type="button" class="button button-primary" id="arkidevs-submit-rating" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" style="margin-top: 10px;">
                    <?php esc_html_e( 'Submit Rating', 'arkidevs-support' ); ?>
                </button>
                <p id="arkidevs-rating-message" class="description" style="margin-top: 8px; display: none;"></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>


