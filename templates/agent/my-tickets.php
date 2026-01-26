<?php
/**
 * Agent My Tickets template
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-time-log-table.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';

$current_agent_id = get_current_user_id();

// Filters from query string.
$status_filter   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$priority_filter = isset( $_GET['priority'] ) ? sanitize_text_field( wp_unslash( $_GET['priority'] ) ) : '';
$customer_filter = isset( $_GET['customer_id'] ) ? (int) $_GET['customer_id'] : 0;
$tag_filter      = isset( $_GET['tag_id'] ) ? (int) $_GET['tag_id'] : 0;
$search_query    = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
$date_from       = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$date_to         = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

// Get status counts for current agent's tickets only
$agent_tickets_all = Arkidevs_Support_Ticket_Table::get_by_agent( $current_agent_id );
$open_tickets = array_filter( $agent_tickets_all, function( $ticket ) {
    return 'open' === $ticket->status;
} );
$in_progress_tickets = array_filter( $agent_tickets_all, function( $ticket ) {
    return 'in_progress' === $ticket->status;
} );
$closed_tickets = array_filter( $agent_tickets_all, function( $ticket ) {
    return 'closed' === $ticket->status;
} );

// Build search arguments for main list - always filter by current agent
$search_args = array(
    'search'      => $search_query,
    'status'      => $status_filter,
    'priority'    => $priority_filter,
    'agent_id'    => $current_agent_id, // Always filter by current agent
    'customer_id' => $customer_filter,
    'tag_id'      => $tag_filter,
    'date_from'   => $date_from,
    'date_to'     => $date_to,
    'limit'       => 200,
    'offset'      => 0,
);

$tickets = Arkidevs_Support_Ticket_Table::search( $search_args );
?>

<div class="wrap arkidevs-support-agent">
    <h1><?php esc_html_e( 'My Tickets', 'arkidevs-support' ); ?></h1>
    
    <div class="arkidevs-tabs">
        <?php
        // Build base URL without filters for tabs.
        $base_url = admin_url( 'admin.php?page=arkidevs-support-my-tickets' );
        ?>
        <a href="<?php echo esc_url( $base_url ); ?>" class="tab <?php echo empty( $status_filter ) ? 'active' : ''; ?>">
            <?php esc_html_e( 'All', 'arkidevs-support' ); ?> (<?php echo esc_html( count( $agent_tickets_all ) ); ?>)
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'status', 'open', $base_url ) ); ?>" class="tab <?php echo 'open' === $status_filter ? 'active' : ''; ?>">
            <?php esc_html_e( 'Open', 'arkidevs-support' ); ?> (<?php echo esc_html( count( $open_tickets ) ); ?>)
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'status', 'in_progress', $base_url ) ); ?>" class="tab <?php echo 'in_progress' === $status_filter ? 'active' : ''; ?>">
            <?php esc_html_e( 'In Progress', 'arkidevs-support' ); ?> (<?php echo esc_html( count( $in_progress_tickets ) ); ?>)
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'status', 'closed', $base_url ) ); ?>" class="tab <?php echo 'closed' === $status_filter ? 'active' : ''; ?>">
            <?php esc_html_e( 'Closed', 'arkidevs-support' ); ?> (<?php echo esc_html( count( $closed_tickets ) ); ?>)
        </a>
    </div>

    <!-- Search and filter form (server-side, no JS required) -->
    <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ccd0d4; border-radius: 4px;">
        <input type="hidden" name="page" value="arkidevs-support-my-tickets" />
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
            <div style="flex: 2; min-width: 220px;">
                <label for="ticket-search"><strong><?php esc_html_e( 'Search', 'arkidevs-support' ); ?></strong></label>
                <input type="text" id="ticket-search" name="search" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Subject, description, ticket #', 'arkidevs-support' ); ?>" style="width: 100%;" />
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label for="filter-status"><strong><?php esc_html_e( 'Status', 'arkidevs-support' ); ?></strong></label>
                <select id="filter-status" name="status" style="width: 100%;">
                    <option value=""><?php esc_html_e( 'All', 'arkidevs-support' ); ?></option>
                    <option value="open" <?php selected( $status_filter, 'open' ); ?>><?php esc_html_e( 'Open', 'arkidevs-support' ); ?></option>
                    <option value="in_progress" <?php selected( $status_filter, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'arkidevs-support' ); ?></option>
                    <option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'arkidevs-support' ); ?></option>
                    <option value="resolved" <?php selected( $status_filter, 'resolved' ); ?>><?php esc_html_e( 'Resolved', 'arkidevs-support' ); ?></option>
                    <option value="closed" <?php selected( $status_filter, 'closed' ); ?>><?php esc_html_e( 'Closed', 'arkidevs-support' ); ?></option>
                </select>
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label for="filter-priority"><strong><?php esc_html_e( 'Priority', 'arkidevs-support' ); ?></strong></label>
                <select id="filter-priority" name="priority" style="width: 100%;">
                    <option value=""><?php esc_html_e( 'All', 'arkidevs-support' ); ?></option>
                    <option value="low" <?php selected( $priority_filter, 'low' ); ?>><?php esc_html_e( 'Low', 'arkidevs-support' ); ?></option>
                    <option value="medium" <?php selected( $priority_filter, 'medium' ); ?>><?php esc_html_e( 'Medium', 'arkidevs-support' ); ?></option>
                    <option value="high" <?php selected( $priority_filter, 'high' ); ?>><?php esc_html_e( 'High', 'arkidevs-support' ); ?></option>
                    <option value="critical" <?php selected( $priority_filter, 'critical' ); ?>><?php esc_html_e( 'Critical', 'arkidevs-support' ); ?></option>
                </select>
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label for="filter-tag"><strong><?php esc_html_e( 'Tag', 'arkidevs-support' ); ?></strong></label>
                <select id="filter-tag" name="tag_id" style="width: 100%;">
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
                <label for="filter-customer"><strong><?php esc_html_e( 'Customer', 'arkidevs-support' ); ?></strong></label>
                <select id="filter-customer" name="customer_id" style="width: 100%;">
                    <option value="0"><?php esc_html_e( 'All', 'arkidevs-support' ); ?></option>
                    <?php
                    $customers = get_users(
                        array(
                            'role'    => 'arkidevs_customer',
                            'orderby' => 'display_name',
                            'number'  => 200,
                        )
                    );
                    foreach ( $customers as $customer ) :
                        ?>
                        <option value="<?php echo esc_attr( $customer->ID ); ?>" <?php selected( $customer_filter, $customer->ID ); ?>>
                            <?php echo esc_html( Arkidevs_Support_Utils::get_user_display_name( $customer->ID ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label for="date-from"><strong><?php esc_html_e( 'Date from', 'arkidevs-support' ); ?></strong></label>
                <input type="date" id="date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" style="width: 100%;" />
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label for="date-to"><strong><?php esc_html_e( 'Date to', 'arkidevs-support' ); ?></strong></label>
                <input type="date" id="date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" style="width: 100%;" />
            </div>
            <div style="flex: 0 0 auto; min-width: 140px;">
                <button type="submit" class="button button-primary" style="margin-top: 4px;"><?php esc_html_e( 'Apply', 'arkidevs-support' ); ?></button>
                <a href="<?php echo esc_url( $base_url ); ?>" class="button" style="margin-top: 4px;"><?php esc_html_e( 'Reset', 'arkidevs-support' ); ?></a>
            </div>
        </div>
    </form>

    <div class="arkidevs-bulk-actions-bar" style="margin-top: 20px; margin-bottom: 10px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        <label style="margin-right: 10px;">
            <input type="checkbox" id="arkidevs-select-all-tickets" />
            <?php esc_html_e( 'Select all', 'arkidevs-support' ); ?>
        </label>

        <select id="arkidevs-bulk-action" style="min-width: 160px;">
            <option value=""><?php esc_html_e( 'Bulk actions', 'arkidevs-support' ); ?></option>
            <optgroup label="<?php esc_attr_e( 'Status', 'arkidevs-support' ); ?>">
                <?php foreach ( Arkidevs_Support_Status::get_statuses() as $status_key => $status_label ) : ?>
                    <option value="<?php echo esc_attr( 'status:' . $status_key ); ?>">
                        <?php echo esc_html( sprintf( __( 'Set status: %s', 'arkidevs-support' ), $status_label ) ); ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="<?php esc_attr_e( 'Priority', 'arkidevs-support' ); ?>">
                <?php foreach ( Arkidevs_Support_Priority::get_priorities() as $priority_key => $priority_label ) : ?>
                    <option value="<?php echo esc_attr( 'priority:' . $priority_key ); ?>">
                        <?php echo esc_html( sprintf( __( 'Set priority: %s', 'arkidevs-support' ), $priority_label ) ); ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="<?php esc_attr_e( 'Other', 'arkidevs-support' ); ?>">
                <option value="archive"><?php esc_html_e( 'Archive', 'arkidevs-support' ); ?></option>
                <option value="delete"><?php esc_html_e( 'Delete', 'arkidevs-support' ); ?></option>
            </optgroup>
        </select>

        <button type="button" class="button" id="arkidevs-apply-bulk-actions">
            <?php esc_html_e( 'Apply', 'arkidevs-support' ); ?>
        </button>
        <button type="button" class="button" id="arkidevs-bulk-export">
            <?php esc_html_e( 'Export CSV', 'arkidevs-support' ); ?>
        </button>
    </div>

    <div class="arkidevs-tickets-list">
        <?php
        if ( empty( $tickets ) ) {
            echo '<p>' . esc_html__( 'No tickets assigned to you.', 'arkidevs-support' ) . '</p>';
        } else {
            foreach ( $tickets as $ticket_data ) {
                $ticket = new Arkidevs_Support_Ticket( $ticket_data );
                ?>
                <?php
                // Check if ticket has active timer
                $active_timer = Arkidevs_Support_Time_Log_Table::has_active_timer( $ticket->id );
                ?>
                <div class="arkidevs-ticket-item <?php echo $active_timer ? 'has-active-timer' : ''; ?>">
                    <div class="ticket-header">
                        <label class="arkidevs-ticket-select" style="margin-right: 8px;">
                            <input type="checkbox" class="arkidevs-ticket-checkbox" value="<?php echo esc_attr( $ticket->id ); ?>" />
                        </label>
                        <h3>
                            <a href="<?php echo esc_url( add_query_arg( 'ticket_id', $ticket->id, $base_url ) ); ?>">
                                #<?php echo esc_html( $ticket->ticket_number ); ?> - <?php echo esc_html( $ticket->subject ); ?>
                            </a>
                            <?php if ( $active_timer ) : ?>
                                <span class="timer-indicator" title="<?php esc_attr_e( 'Timer is running', 'arkidevs-support' ); ?>">
                                    <span class="timer-icon">⏱</span>
                                    <span class="timer-pulse"></span>
                                </span>
                            <?php endif; ?>
                        </h3>
                        <span class="priority-badge <?php echo esc_attr( Arkidevs_Support_Priority::get_priority_class( $ticket->priority ) ); ?>">
                            <?php echo esc_html( Arkidevs_Support_Priority::get_priority_label( $ticket->priority ) ); ?>
                        </span>
                    </div>
                    <div class="ticket-meta">
                        <span class="status-badge <?php echo esc_attr( Arkidevs_Support_Status::get_status_class( $ticket->status ) ); ?>">
                            <?php echo esc_html( Arkidevs_Support_Status::get_status_label( $ticket->status ) ); ?>
                        </span>
                        <span><?php esc_html_e( 'Customer:', 'arkidevs-support' ); ?> <?php echo esc_html( Arkidevs_Support_Utils::get_user_display_name( $ticket->customer_id ) ); ?></span>
                        <span><?php echo Arkidevs_Support_Utils::format_datetime_with_relative( $ticket->created_at ); ?></span>
                    </div>
                </div>
                <?php
            }
        }
        ?>
    </div>
</div>
