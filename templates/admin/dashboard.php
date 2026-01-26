<?php
/**
 * Admin dashboard template
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap arkidevs-support-admin">
    <h1><?php esc_html_e( 'Arkidevs Support - Admin Dashboard', 'arkidevs-support' ); ?></h1>
    
    <div class="arkidevs-stats">
        <div class="stat-box">
            <h3><?php esc_html_e( 'Total Tickets', 'arkidevs-support' ); ?></h3>
            <p class="stat-number"><?php
                require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
                $all_tickets = Arkidevs_Support_Ticket_Table::get_all();
                echo esc_html( count( $all_tickets ) );
            ?></p>
        </div>
        
        <div class="stat-box">
            <h3><?php esc_html_e( 'Open Tickets', 'arkidevs-support' ); ?></h3>
            <p class="stat-number"><?php
                $open_tickets = Arkidevs_Support_Ticket_Table::get_by_status( 'open' );
                echo esc_html( count( $open_tickets ) );
            ?></p>
        </div>
        
        <div class="stat-box">
            <h3><?php esc_html_e( 'In Progress', 'arkidevs-support' ); ?></h3>
            <p class="stat-number"><?php
                $in_progress = Arkidevs_Support_Ticket_Table::get_by_status( 'in_progress' );
                echo esc_html( count( $in_progress ) );
            ?></p>
        </div>
        
        <div class="stat-box">
            <h3><?php esc_html_e( 'Closed Tickets', 'arkidevs-support' ); ?></h3>
            <p class="stat-number"><?php
                $closed = Arkidevs_Support_Ticket_Table::get_by_status( 'closed' );
                echo esc_html( count( $closed ) );
            ?></p>
        </div>
    </div>

    <div class="arkidevs-analytics" style="margin: 25px 0; padding: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Analytics & Insights (Last 30 Days)', 'arkidevs-support' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Status/priority trends, agent performance, resolution times, and customer satisfaction.', 'arkidevs-support' ); ?></p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit,minmax(240px,1fr)); gap: 16px; margin-top: 12px;">
            <div class="card" style="padding:12px; border:1px solid #e2e8f0; border-radius:6px;">
                <h4 style="margin:0 0 8px;"><?php esc_html_e( 'Avg Resolution (hrs)', 'arkidevs-support' ); ?></h4>
                <div id="arkidevs-avg-resolution" style="font-size:24px;font-weight:700;">—</div>
            </div>
            <div class="card" style="padding:12px; border:1px solid #e2e8f0; border-radius:6px;">
                <h4 style="margin:0 0 8px;"><?php esc_html_e( 'CSAT (avg)', 'arkidevs-support' ); ?></h4>
                <div id="arkidevs-csat-avg" style="font-size:24px;font-weight:700;">—</div>
                <small id="arkidevs-csat-count" class="description">—</small>
            </div>
            <div class="card" style="padding:12px; border:1px solid #e2e8f0; border-radius:6px;">
                <h4 style="margin:0 0 8px;"><?php esc_html_e( 'Resolved by Agents', 'arkidevs-support' ); ?></h4>
                <div id="arkidevs-resolved-total" style="font-size:24px;font-weight:700;">—</div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:20px; margin-top:16px;">
            <div>
                <h3><?php esc_html_e( 'Tickets by Status', 'arkidevs-support' ); ?></h3>
                <div class="arkidevs-chart-container">
                    <canvas id="arkidevs-status-chart"></canvas>
                </div>
            </div>
            <div>
                <h3><?php esc_html_e( 'Tickets by Priority', 'arkidevs-support' ); ?></h3>
                <div class="arkidevs-chart-container">
                    <canvas id="arkidevs-priority-chart"></canvas>
                </div>
            </div>
        </div>

        <div style="margin-top:16px;">
            <h3><?php esc_html_e( 'Agent Performance', 'arkidevs-support' ); ?></h3>
            <div class="arkidevs-chart-container">
                <canvas id="arkidevs-agent-chart"></canvas>
            </div>
        </div>

        <div style="margin-top:16px;">
            <h3><?php esc_html_e( 'Customer Satisfaction Trend', 'arkidevs-support' ); ?></h3>
            <div class="arkidevs-chart-container">
                <canvas id="arkidevs-csat-chart"></canvas>
            </div>
        </div>
    </div>

    <div class="arkidevs-actions" style="margin: 20px 0;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=arkidevs-support-agent' ) ); ?>" class="button button-primary">
            <?php esc_html_e( 'View All Tickets', 'arkidevs-support' ); ?>
        </a>
    </div>

    <!-- Create New Ticket Section for Admins -->
    <div class="create-ticket-section" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2><?php esc_html_e( 'Create New Ticket', 'arkidevs-support' ); ?></h2>
        <button type="button" id="show-ticket-form" class="button button-primary">
            <?php esc_html_e( 'Create Ticket', 'arkidevs-support' ); ?>
        </button>

        <div id="ticket-form" class="hidden" style="margin-top: 20px;">
            <form id="create-ticket-form">
                <?php wp_nonce_field( 'arkidevs_support_nonce', 'arkidevs_ticket_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ticket-customer"><?php esc_html_e( 'Customer:', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                        </th>
                        <td>
                            <select id="ticket-customer" name="customer_id" required style="width: 100%;">
                                <option value=""><?php esc_html_e( 'Select Customer...', 'arkidevs-support' ); ?></option>
                                <?php
                                require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
                                $customers = get_users(
                                    array(
                                        'role'    => 'arkidevs_customer',
                                        'orderby' => 'display_name',
                                        'number'  => 500,
                                    )
                                );
                                foreach ( $customers as $customer ) :
                                    ?>
                                    <option value="<?php echo esc_attr( $customer->ID ); ?>">
                                        <?php echo esc_html( Arkidevs_Support_Utils::get_user_display_name( $customer->ID ) . ' (' . $customer->user_email . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Select the customer for this ticket', 'arkidevs-support' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ticket-subject"><?php esc_html_e( 'Subject:', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="ticket-subject" name="subject" required style="width: 100%;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ticket-priority"><?php esc_html_e( 'Priority:', 'arkidevs-support' ); ?></label>
                        </th>
                        <td>
                            <select id="ticket-priority" name="priority" style="width: 100%;">
                                <option value="low"><?php esc_html_e( 'Low', 'arkidevs-support' ); ?></option>
                                <option value="medium" selected><?php esc_html_e( 'Medium', 'arkidevs-support' ); ?></option>
                                <option value="high"><?php esc_html_e( 'High', 'arkidevs-support' ); ?></option>
                                <option value="critical"><?php esc_html_e( 'Critical', 'arkidevs-support' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ticket-description"><?php esc_html_e( 'Description:', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                        </th>
                        <td>
                            <textarea id="ticket-description" name="description" rows="10" required style="width: 100%;"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ticket-tags"><?php esc_html_e( 'Tags:', 'arkidevs-support' ); ?></label>
                        </th>
                        <td>
                            <?php
                            require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
                            $tags = Arkidevs_Support_Tag::get_all();
                            if ( ! empty( $tags ) ) :
                                ?>
                                <select id="ticket-tags" name="tags[]" multiple style="width: 100%; min-height: 100px;">
                                    <?php foreach ( $tags as $tag ) : ?>
                                        <option value="<?php echo esc_attr( $tag->id ); ?>">
                                            <?php echo esc_html( $tag->name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple tags', 'arkidevs-support' ); ?></p>
                            <?php else : ?>
                                <p class="description"><?php esc_html_e( 'No tags available. Create tags in Support > Tags', 'arkidevs-support' ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Submit Ticket', 'arkidevs-support' ); ?></button>
                    <button type="button" id="cancel-ticket-form" class="button"><?php esc_html_e( 'Cancel', 'arkidevs-support' ); ?></button>
                </p>
            </form>
        </div>
    </div>

    <?php
    // Show ticket list similar to agent dashboard
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-ticket-table.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-time-log-table.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';

    $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

    $open_tickets = Arkidevs_Support_Ticket_Table::get_by_status( 'open' );
    $in_progress_tickets = Arkidevs_Support_Ticket_Table::get_by_status( 'in_progress' );
    $closed_tickets = Arkidevs_Support_Ticket_Table::get_by_status( 'closed' );
    ?>

    <div class="arkidevs-tabs" style="margin-top: 30px;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=arkidevs-support-admin' ) ); ?>" class="tab <?php echo empty( $status_filter ) || $status_filter === 'open' ? 'active' : ''; ?>">
            <?php esc_html_e( 'Open', 'arkidevs-support' ); ?> (<?php echo esc_html( count( $open_tickets ) ); ?>)
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'status', 'in_progress', admin_url( 'admin.php?page=arkidevs-support-admin' ) ) ); ?>" class="tab <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">
            <?php esc_html_e( 'In Progress', 'arkidevs-support' ); ?> (<?php echo esc_html( count( $in_progress_tickets ) ); ?>)
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'status', 'closed', admin_url( 'admin.php?page=arkidevs-support-admin' ) ) ); ?>" class="tab <?php echo $status_filter === 'closed' ? 'active' : ''; ?>">
            <?php esc_html_e( 'Closed', 'arkidevs-support' ); ?> (<?php echo esc_html( count( $closed_tickets ) ); ?>)
        </a>
    </div>

    <div class="arkidevs-tickets-list">
        <?php
        $tickets = array();
        if ( $status_filter === 'in_progress' ) {
            $tickets = $in_progress_tickets;
        } elseif ( $status_filter === 'closed' ) {
            $tickets = $closed_tickets;
        } else {
            $tickets = $open_tickets;
        }

        if ( empty( $tickets ) ) {
            echo '<p>' . esc_html__( 'No tickets found.', 'arkidevs-support' ) . '</p>';
        } else {
            ?>
            <div class="arkidevs-bulk-actions-bar" style="margin-top: 10px; margin-bottom: 10px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
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
            <?php
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
                            <a href="<?php echo esc_url( add_query_arg( 'ticket_id', $ticket->id, admin_url( 'admin.php?page=arkidevs-support-admin' ) ) ); ?>">
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
                        <?php if ( $ticket->agent_id ) : ?>
                            <span><?php esc_html_e( 'Assigned:', 'arkidevs-support' ); ?> <strong><?php echo esc_html( Arkidevs_Support_Utils::get_user_display_name( $ticket->agent_id ) ); ?></strong></span>
                        <?php else : ?>
                            <span class="unassigned-text"><?php esc_html_e( 'Unassigned', 'arkidevs-support' ); ?></span>
                        <?php endif; ?>
                        <span><?php echo Arkidevs_Support_Utils::format_datetime_with_relative( $ticket->created_at ); ?></span>
                    </div>
                </div>
                <?php
            }
        }
        ?>
    </div>
</div>


