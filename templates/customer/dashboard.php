<?php
/**
 * Customer dashboard template
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-time-log-table.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-time-calculator.php';

$customer_id = get_current_user_id();

// Filters from query string.
$status_filter   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$priority_filter = isset( $_GET['priority'] ) ? sanitize_text_field( wp_unslash( $_GET['priority'] ) ) : '';
$tag_filter      = isset( $_GET['tag_id'] ) ? (int) $_GET['tag_id'] : 0;
$search_query    = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
$date_from       = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$date_to         = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

// Search tickets for this customer.
$tickets = Arkidevs_Support_Ticket_Table::search_by_customer(
    $customer_id,
    array(
        'search'    => $search_query,
        'status'    => $status_filter,
        'priority'  => $priority_filter,
        'tag_id'    => $tag_filter,
        'date_from' => $date_from,
        'date_to'   => $date_to,
        'limit'     => 200,
        'offset'    => 0,
    )
);

$hours_summary = Arkidevs_Support_Time_Calculator::get_customer_hours_summary( $customer_id );
?>

<div class="arkidevs-support-customer">
    <h1><?php esc_html_e( 'My Support Tickets', 'arkidevs-support' ); ?></h1>

    <div class="customer-hours-summary">
        <h2><?php esc_html_e( 'Time Tracking', 'arkidevs-support' ); ?></h2>
        <div class="hours-stats">
            <div class="hours-box">
                <strong><?php esc_html_e( 'Hours Allocated:', 'arkidevs-support' ); ?></strong>
                <span><?php echo esc_html( number_format( $hours_summary['allocated'], 2 ) ); ?></span>
            </div>
            <div class="hours-box">
                <strong><?php esc_html_e( 'Hours Spent:', 'arkidevs-support' ); ?></strong>
                <span><?php echo esc_html( number_format( $hours_summary['spent'], 2 ) ); ?></span>
            </div>
            <div class="hours-box">
                <strong><?php esc_html_e( 'Hours Remaining:', 'arkidevs-support' ); ?></strong>
                <span><?php echo esc_html( number_format( $hours_summary['remaining'], 2 ) ); ?></span>
            </div>
        </div>
    </div>

    <div class="create-ticket-section">
        <h2><?php esc_html_e( 'Create New Ticket', 'arkidevs-support' ); ?></h2>
        <button type="button" id="show-ticket-form" class="button button-primary">
            <?php esc_html_e( 'Create Ticket', 'arkidevs-support' ); ?>
        </button>

        <div id="ticket-form" class="hidden">
            <form id="create-ticket-form">
                <?php wp_nonce_field( 'arkidevs_support_nonce', 'arkidevs_ticket_nonce' ); ?>
                <p>
                    <label for="ticket-subject"><?php esc_html_e( 'Subject:', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                    <input type="text" id="ticket-subject" name="subject" required style="width: 100%;" />
                </p>
                <p>
                    <label for="ticket-priority"><?php esc_html_e( 'Priority:', 'arkidevs-support' ); ?></label>
                    <select id="ticket-priority" name="priority" style="width: 100%;">
                        <option value="low"><?php esc_html_e( 'Low', 'arkidevs-support' ); ?></option>
                        <option value="medium" selected><?php esc_html_e( 'Medium', 'arkidevs-support' ); ?></option>
                        <option value="high"><?php esc_html_e( 'High', 'arkidevs-support' ); ?></option>
                        <option value="critical"><?php esc_html_e( 'Critical', 'arkidevs-support' ); ?></option>
                    </select>
                </p>
                <p>
                    <label for="ticket-description"><?php esc_html_e( 'Description:', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                    <textarea id="ticket-description" name="description" rows="10" required style="width: 100%;"></textarea>
                </p>
                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Submit Ticket', 'arkidevs-support' ); ?></button>
                    <button type="button" id="cancel-ticket-form" class="button"><?php esc_html_e( 'Cancel', 'arkidevs-support' ); ?></button>
                </p>
            </form>
        </div>
    </div>

    <!-- Search and filter (customer side, server-side) -->
    <form method="get" action="<?php echo esc_url( home_url( '/support/' ) ); ?>" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
            <div style="flex: 2; min-width: 220px;">
                <label for="customer-search"><strong><?php esc_html_e( 'Search', 'arkidevs-support' ); ?></strong></label>
                <input type="text" id="customer-search" name="search" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Subject, description, ticket #', 'arkidevs-support' ); ?>" style="width: 100%;" />
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label for="customer-status"><strong><?php esc_html_e( 'Status', 'arkidevs-support' ); ?></strong></label>
                <select id="customer-status" name="status" style="width: 100%;">
                    <option value=""><?php esc_html_e( 'All', 'arkidevs-support' ); ?></option>
                    <option value="open" <?php selected( $status_filter, 'open' ); ?>><?php esc_html_e( 'Open', 'arkidevs-support' ); ?></option>
                    <option value="in_progress" <?php selected( $status_filter, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'arkidevs-support' ); ?></option>
                    <option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'arkidevs-support' ); ?></option>
                    <option value="resolved" <?php selected( $status_filter, 'resolved' ); ?>><?php esc_html_e( 'Resolved', 'arkidevs-support' ); ?></option>
                    <option value="closed" <?php selected( $status_filter, 'closed' ); ?>><?php esc_html_e( 'Closed', 'arkidevs-support' ); ?></option>
                </select>
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label for="customer-priority"><strong><?php esc_html_e( 'Priority', 'arkidevs-support' ); ?></strong></label>
                <select id="customer-priority" name="priority" style="width: 100%;">
                    <option value=""><?php esc_html_e( 'All', 'arkidevs-support' ); ?></option>
                    <option value="low" <?php selected( $priority_filter, 'low' ); ?>><?php esc_html_e( 'Low', 'arkidevs-support' ); ?></option>
                    <option value="medium" <?php selected( $priority_filter, 'medium' ); ?>><?php esc_html_e( 'Medium', 'arkidevs-support' ); ?></option>
                    <option value="high" <?php selected( $priority_filter, 'high' ); ?>><?php esc_html_e( 'High', 'arkidevs-support' ); ?></option>
                    <option value="critical" <?php selected( $priority_filter, 'critical' ); ?>><?php esc_html_e( 'Critical', 'arkidevs-support' ); ?></option>
                </select>
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label for="customer-tag"><strong><?php esc_html_e( 'Tag', 'arkidevs-support' ); ?></strong></label>
                <select id="customer-tag" name="tag_id" style="width: 100%;">
                    <option value="0"><?php esc_html_e( 'All', 'arkidevs-support' ); ?></option>
                    <?php
                    require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
                    $tags = Arkidevs_Support_Tag::get_all();
                    foreach ( $tags as $tag ) :
                        ?>
                        <option value="<?php echo esc_attr( $tag->id ); ?>" <?php selected( $tag_filter, $tag->id ); ?>>
                            <?php echo esc_html( $tag->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label for="customer-date-from"><strong><?php esc_html_e( 'Date from', 'arkidevs-support' ); ?></strong></label>
                <input type="date" id="customer-date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" style="width: 100%;" />
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label for="customer-date-to"><strong><?php esc_html_e( 'Date to', 'arkidevs-support' ); ?></strong></label>
                <input type="date" id="customer-date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" style="width: 100%;" />
            </div>
            <div style="flex: 0 0 auto; min-width: 140px;">
                <button type="submit" class="button button-primary" style="margin-top: 4px;"><?php esc_html_e( 'Apply', 'arkidevs-support' ); ?></button>
                <a href="<?php echo esc_url( home_url( '/support/' ) ); ?>" class="button" style="margin-top: 4px;"><?php esc_html_e( 'Reset', 'arkidevs-support' ); ?></a>
            </div>
        </div>
    </form>

    <div class="tickets-list">
        <h2><?php esc_html_e( 'My Tickets', 'arkidevs-support' ); ?></h2>
        <?php if ( empty( $tickets ) ) : ?>
            <p><?php esc_html_e( 'You have no tickets yet.', 'arkidevs-support' ); ?></p>
        <?php else : ?>
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Ticket #', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Subject', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Created', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'arkidevs-support' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tickets as $ticket_data ) : ?>
                        <?php
                        // Check if ticket has active timer
                        $active_timer = Arkidevs_Support_Time_Log_Table::has_active_timer( $ticket_data->id );
                        ?>
                        <tr class="<?php echo $active_timer ? 'has-active-timer' : ''; ?>">
                            <td>
                                #<?php echo esc_html( $ticket_data->ticket_number ); ?>
                                <?php if ( $active_timer ) : ?>
                                    <span class="timer-indicator" title="<?php esc_attr_e( 'Timer is running', 'arkidevs-support' ); ?>" style="margin-left: 8px;">
                                        <span class="timer-icon">⏱</span>
                                        <span class="timer-pulse"></span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $ticket_data->subject ); ?></td>
                            <td>
                                <span class="status-badge <?php echo esc_attr( Arkidevs_Support_Status::get_status_class( $ticket_data->status ) ); ?>">
                                    <?php echo esc_html( Arkidevs_Support_Status::get_status_label( $ticket_data->status ) ); ?>
                                </span>
                            </td>
                            <td>
                                <span class="priority-badge <?php echo esc_attr( Arkidevs_Support_Priority::get_priority_class( $ticket_data->priority ) ); ?>">
                                    <?php echo esc_html( Arkidevs_Support_Priority::get_priority_label( $ticket_data->priority ) ); ?>
                                </span>
                            </td>
                            <td><?php echo Arkidevs_Support_Utils::format_datetime_with_relative( $ticket_data->created_at ); ?></td>
                            <td>
                                <?php
                                // Use public support URL
                                $view_url = home_url( '/support/ticket/' . $ticket_data->id . '/' );
                                // Fallback to query string if permalink structure doesn't support it
                                if ( ! get_option( 'permalink_structure' ) ) {
                                    $view_url = home_url( '/support/?ticket_id=' . $ticket_data->id );
                                }
                                ?>
                                <a href="<?php echo esc_url( $view_url ); ?>" class="button">
                                    <?php esc_html_e( 'View', 'arkidevs-support' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

