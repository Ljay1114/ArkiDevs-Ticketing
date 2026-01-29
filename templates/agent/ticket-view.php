<?php
/**
 * Agent ticket view template
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-ticket-relations.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-timer.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-time-calculator.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/audit/class-audit-log.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/escalation/class-escalation-engine.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/escalation/class-escalation-rule.php';

$ticket_id = isset( $_GET['ticket_id'] ) ? intval( $_GET['ticket_id'] ) : 0;
$ticket = Arkidevs_Support_Ticket::get_ticket( $ticket_id );

if ( ! $ticket ) {
    wp_die( __( 'Ticket not found.', 'arkidevs-support' ) );
}

$replies = $ticket->get_replies();
$active_timer = Arkidevs_Support_Timer::get_active_timer( $ticket_id );
$ticket_time = Arkidevs_Support_Time_Calculator::calculate_ticket_time( $ticket_id );
$audit_entries = class_exists( 'Arkidevs_Support_Audit_Log' ) ? Arkidevs_Support_Audit_Log::get_by_ticket( $ticket_id, 200 ) : array();
$relations = class_exists( 'Arkidevs_Support_Ticket_Relations' ) ? Arkidevs_Support_Ticket_Relations::get_relations( $ticket_id ) : array();

// Get escalation history
global $wpdb;
$escalation_history_table = Arkidevs_Support_Escalation_Engine::get_history_table_name();
$escalation_history = array();
if ( class_exists( 'Arkidevs_Support_Escalation_Engine' ) ) {
    Arkidevs_Support_Escalation_Engine::ensure_history_table_exists();
    $escalation_history = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT eh.*, er.name as rule_name FROM {$escalation_history_table} eh
            LEFT JOIN {$wpdb->prefix}arkidevs_escalation_rules er ON eh.escalation_rule_id = er.id
            WHERE eh.ticket_id = %d
            ORDER BY eh.escalated_at DESC",
            $ticket_id
        )
    );
}
?>

<div class="wrap arkidevs-support-agent-ticket">
    <h1>
        <?php
        // Determine back link based on current page
        $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : 'arkidevs-support-agent';
        $back_url = admin_url( 'admin.php?page=' . $current_page );
        ?>
        <a href="<?php echo esc_url( $back_url ); ?>">← <?php esc_html_e( 'Back to Tickets', 'arkidevs-support' ); ?></a>
    </h1>
    
    <div class="ticket-header">
        <h2>#<?php echo esc_html( $ticket->ticket_number ); ?> - <?php echo esc_html( $ticket->subject ); ?></h2>
        <?php if ( ! empty( $ticket->merged_into_ticket_id ) ) : ?>
            <?php
            $merged_into_id = (int) $ticket->merged_into_ticket_id;
            $merged_url = esc_url( add_query_arg( array( 'page' => $current_page, 'ticket_id' => $merged_into_id ), admin_url( 'admin.php' ) ) );
            ?>
            <div class="notice notice-warning" style="margin-top: 10px;">
                <p>
                    <?php esc_html_e( 'This ticket has been merged into another ticket.', 'arkidevs-support' ); ?>
                    <a href="<?php echo $merged_url; ?>"><?php esc_html_e( 'View merged ticket', 'arkidevs-support' ); ?></a>
                </p>
            </div>
        <?php endif; ?>
        <div class="ticket-meta">
            <span class="status-badge <?php echo esc_attr( Arkidevs_Support_Status::get_status_class( $ticket->status ) ); ?>">
                <?php echo esc_html( Arkidevs_Support_Status::get_status_label( $ticket->status ) ); ?>
            </span>
            <span class="priority-badge <?php echo esc_attr( Arkidevs_Support_Priority::get_priority_class( $ticket->priority ) ); ?>">
                <?php echo esc_html( Arkidevs_Support_Priority::get_priority_label( $ticket->priority ) ); ?>
            </span>
            <?php if ( ! empty( $escalation_history ) ) : ?>
                <span class="escalation-badge" style="display: inline-block; padding: 4px 8px; margin-left: 10px; background-color: #d32f2f; color: #fff; border-radius: 3px; font-size: 12px; font-weight: 600;">
                    ⚠ <?php esc_html_e( 'Escalated', 'arkidevs-support' ); ?>
                </span>
            <?php endif; ?>
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
            <?php if ( $ticket->agent_id ) : ?>
                <span class="agent-badge">
                    <?php esc_html_e( 'Assigned to:', 'arkidevs-support' ); ?> 
                    <strong><?php echo esc_html( Arkidevs_Support_Utils::get_user_display_name( $ticket->agent_id ) ); ?></strong>
                </span>
            <?php else : ?>
                <span class="agent-badge unassigned">
                    <?php esc_html_e( 'Unassigned', 'arkidevs-support' ); ?>
                </span>
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

    <div class="ticket-actions">
        <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <?php
            $agents = Arkidevs_Support_Utils::get_all_agents();
            ?>
            <select id="ticket-agent" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>">
                <option value="0"><?php esc_html_e( 'Assign Agent...', 'arkidevs-support' ); ?></option>
                <?php foreach ( $agents as $agent ) : ?>
                    <option value="<?php echo esc_attr( $agent->ID ); ?>" <?php selected( $ticket->agent_id, $agent->ID ); ?>>
                        <?php echo esc_html( $agent->display_name ); ?> (<?php echo esc_html( $agent->user_email ); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else : ?>
            <?php
            $current_user_id = get_current_user_id();
            $is_assigned_to_me = (int) $ticket->agent_id === (int) $current_user_id;
            $can_take = current_user_can( 'arkidevs_manage_tickets' ) && ! $is_assigned_to_me;
            ?>
            <?php if ( $can_take ) : ?>
                <button type="button"
                        id="take-ticket"
                        class="button button-primary"
                        data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>"
                        data-agent-id="<?php echo esc_attr( $current_user_id ); ?>">
                    <?php esc_html_e( 'Take Ticket', 'arkidevs-support' ); ?>
                </button>
            <?php endif; ?>
        <?php endif; ?>
        <select id="ticket-status" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>">
            <?php foreach ( Arkidevs_Support_Status::get_statuses() as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $ticket->status, $key ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="ticket-priority" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>">
            <?php foreach ( Arkidevs_Support_Priority::get_priorities() as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $ticket->priority, $key ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="button" id="start-timer" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" class="button <?php echo $active_timer ? 'hidden' : ''; ?>">
            <?php esc_html_e( 'Start Timer', 'arkidevs-support' ); ?>
        </button>
        <button type="button" id="stop-timer" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" class="button <?php echo ! $active_timer ? 'hidden' : ''; ?>">
            <?php esc_html_e( 'Stop Timer', 'arkidevs-support' ); ?>
        </button>

        <?php if ( 'closed' !== $ticket->status ) : ?>
            <button type="button" id="close-ticket" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" class="button">
                <?php esc_html_e( 'Close Ticket', 'arkidevs-support' ); ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if ( Arkidevs_Support_Security::user_can( 'manage_tickets' ) && empty( $ticket->merged_into_ticket_id ) ) : ?>
        <div class="ticket-merge" style="margin: 15px 0; padding: 12px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h3 style="margin: 0 0 10px;"><?php esc_html_e( 'Merge Ticket', 'arkidevs-support' ); ?></h3>
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e( 'Merge this ticket into another ticket. Enter the target Ticket ID or Ticket Number.', 'arkidevs-support' ); ?>
            </p>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap: wrap;">
                <input type="text" id="merge-target-ticket" placeholder="<?php esc_attr_e( 'Target Ticket ID or Number', 'arkidevs-support' ); ?>" style="min-width: 260px;" />
                <button type="button" id="merge-ticket" class="button button-secondary" data-source-ticket-id="<?php echo esc_attr( $ticket->id ); ?>">
                    <?php esc_html_e( 'Merge into Target', 'arkidevs-support' ); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div class="time-tracking">
        <p><strong><?php esc_html_e( 'Time Spent:', 'arkidevs-support' ); ?></strong> <?php echo esc_html( Arkidevs_Support_Utils::format_duration( $ticket_time ) ); ?></p>
    </div>

    <?php if ( Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) : ?>
        <div class="ticket-tags-management" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
            <h3><?php esc_html_e( 'Tags', 'arkidevs-support' ); ?></h3>
            <div id="ticket-tags-display" style="margin-bottom: 10px;">
                <?php
                $tags = $ticket->get_tags();
                if ( ! empty( $tags ) ) :
                    foreach ( $tags as $tag ) :
                        ?>
                        <span class="ticket-tag-item" data-tag-id="<?php echo esc_attr( $tag->id ); ?>" style="display: inline-block; padding: 4px 8px; margin: 5px 5px 5px 0; background-color: <?php echo esc_attr( $tag->color ); ?>; color: #fff; border-radius: 3px; font-size: 12px;">
                            <?php echo esc_html( $tag->name ); ?>
                            <button type="button" class="remove-tag" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" data-tag-id="<?php echo esc_attr( $tag->id ); ?>" style="margin-left: 5px; background: rgba(255,255,255,0.3); border: none; color: #fff; cursor: pointer; border-radius: 2px; padding: 2px 5px;">×</button>
                        </span>
                    <?php
                    endforeach;
                else :
                    ?>
                    <p style="color: #666;"><?php esc_html_e( 'No tags assigned.', 'arkidevs-support' ); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <select id="add-tag-select" style="width: 200px; display: inline-block;">
                    <option value=""><?php esc_html_e( 'Select tag to add...', 'arkidevs-support' ); ?></option>
                    <?php
                    require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
                    $all_tags = Arkidevs_Support_Tag::get_all();
                    $current_tag_ids = array_map( function( $tag ) { return $tag->id; }, $tags );
                    foreach ( $all_tags as $tag ) :
                        if ( ! in_array( $tag->id, $current_tag_ids, true ) ) :
                            ?>
                            <option value="<?php echo esc_attr( $tag->id ); ?>"><?php echo esc_html( $tag->name ); ?></option>
                        <?php
                        endif;
                    endforeach;
                    ?>
                </select>
                <button type="button" id="add-tag-btn" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" class="button"><?php esc_html_e( 'Add Tag', 'arkidevs-support' ); ?></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="ticket-description">
        <h3><?php esc_html_e( 'Description', 'arkidevs-support' ); ?></h3>
        <div class="description-content">
            <?php echo wp_kses_post( wpautop( $ticket->description ) ); ?>
        </div>
    </div>

    <?php if ( Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) : ?>
        <div class="ticket-related">
            <h3><?php esc_html_e( 'Related Tickets', 'arkidevs-support' ); ?></h3>
            <div id="related-tickets-list">
                <?php if ( empty( $relations ) ) : ?>
                    <p style="color:#666;"><?php esc_html_e( 'No related tickets yet.', 'arkidevs-support' ); ?></p>
                <?php else : ?>
                    <ul class="arkidevs-related-list">
                        <?php foreach ( $relations as $rel ) : ?>
                            <?php
                            $t = $rel['ticket'];
                            $link = esc_url( add_query_arg( array( 'page' => $current_page, 'ticket_id' => $t->id ), admin_url( 'admin.php' ) ) );
                            ?>
                            <li data-related-id="<?php echo esc_attr( $t->id ); ?>">
                                <a href="<?php echo $link; ?>">
                                    #<?php echo esc_html( $t->ticket_number ); ?> — <?php echo esc_html( $t->subject ); ?>
                                </a>
                                <span class="status-badge <?php echo esc_attr( Arkidevs_Support_Status::get_status_class( $t->status ) ); ?>"><?php echo esc_html( Arkidevs_Support_Status::get_status_label( $t->status ) ); ?></span>
                                <span class="priority-badge <?php echo esc_attr( Arkidevs_Support_Priority::get_priority_class( $t->priority ) ); ?>"><?php echo esc_html( Arkidevs_Support_Priority::get_priority_label( $t->priority ) ); ?></span>
                                <button type="button" class="button-link remove-related-ticket" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" data-related-id="<?php echo esc_attr( $t->id ); ?>">×</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="related-add-form" style="margin-top:10px;">
                <input type="text" id="related-ticket-input" placeholder="<?php esc_attr_e( 'Ticket ID or Ticket Number', 'arkidevs-support' ); ?>" style="min-width:240px;" />
                <select id="related-type" style="min-width:140px;">
                    <option value="related"><?php esc_html_e( 'Related', 'arkidevs-support' ); ?></option>
                    <option value="blocked_by"><?php esc_html_e( 'Blocked by', 'arkidevs-support' ); ?></option>
                    <option value="blocks"><?php esc_html_e( 'Blocks', 'arkidevs-support' ); ?></option>
                </select>
                <button type="button" class="button" id="add-related-ticket" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>">
                    <?php esc_html_e( 'Add Related', 'arkidevs-support' ); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) : ?>
        <div class="ticket-audit">
            <h3><?php esc_html_e( 'History', 'arkidevs-support' ); ?></h3>
            <?php if ( empty( $audit_entries ) ) : ?>
                <p style="color:#666;"><?php esc_html_e( 'No history yet.', 'arkidevs-support' ); ?></p>
            <?php else : ?>
                <table class="widefat striped arkidevs-audit-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'When', 'arkidevs-support' ); ?></th>
                            <th><?php esc_html_e( 'Who', 'arkidevs-support' ); ?></th>
                            <th><?php esc_html_e( 'What changed', 'arkidevs-support' ); ?></th>
                            <th><?php esc_html_e( 'From → To', 'arkidevs-support' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $audit_entries as $entry ) : ?>
                            <?php
                            $who   = $entry->actor_user_id ? Arkidevs_Support_Utils::get_user_display_name( (int) $entry->actor_user_id ) : __( 'System', 'arkidevs-support' );
                            $when  = ! empty( $entry->created_at ) ? Arkidevs_Support_Utils::format_datetime_with_relative( $entry->created_at ) : '';
                            $field = ! empty( $entry->field_name ) ? $entry->field_name : $entry->event_type;

                            $old_raw = isset( $entry->old_value ) ? (string) $entry->old_value : '';
                            $new_raw = isset( $entry->new_value ) ? (string) $entry->new_value : '';

                            // Reduce noise for very long text fields in table view, but keep full values available.
                            $shorten = function( $v ) {
                                $v = trim( (string) $v );
                                if ( mb_strlen( $v ) > 120 ) {
                                    return mb_substr( $v, 0, 120 ) . '…';
                                }
                                return $v;
                            };
                            $old_short = $shorten( $old_raw );
                            $new_short = $shorten( $new_raw );
                            $old_truncated = ( $old_short !== trim( (string) $old_raw ) );
                            $new_truncated = ( $new_short !== trim( (string) $new_raw ) );
                            ?>
                            <tr>
                                <td><?php echo wp_kses_post( $when ); ?></td>
                                <td><?php echo esc_html( $who ); ?></td>
                                <td>
                                    <code><?php echo esc_html( $field ); ?></code>
                                    <?php if ( ! empty( $entry->event_type ) && 'field_change' !== $entry->event_type ) : ?>
                                        <span style="color:#666;">(<?php echo esc_html( $entry->event_type ); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="arkidevs-audit-old"><?php echo esc_html( $old_short ); ?></span>
                                    <span class="arkidevs-audit-arrow">→</span>
                                    <span class="arkidevs-audit-new"><?php echo esc_html( $new_short ); ?></span>
                                    <?php if ( $old_truncated || $new_truncated ) : ?>
                                        <details class="arkidevs-audit-details">
                                            <summary><?php esc_html_e( 'View full change', 'arkidevs-support' ); ?></summary>
                                            <div class="arkidevs-audit-full">
                                                <div><strong><?php esc_html_e( 'Old:', 'arkidevs-support' ); ?></strong> <?php echo esc_html( $old_raw ); ?></div>
                                                <div><strong><?php esc_html_e( 'New:', 'arkidevs-support' ); ?></strong> <?php echo esc_html( $new_raw ); ?></div>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description" style="margin-top:8px;">
                    <?php esc_html_e( 'This log tracks status, priority, assignment, ticket edits, and comment edits/deletions.', 'arkidevs-support' ); ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $escalation_history ) ) : ?>
        <div class="ticket-escalation-history" style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3><?php esc_html_e( 'Escalation History', 'arkidevs-support' ); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Escalated At', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Rule', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Trigger', 'arkidevs-support' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'arkidevs-support' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $escalation_history as $escalation ) : ?>
                        <?php
                        $trigger_labels = array(
                            'time'       => __( 'Time', 'arkidevs-support' ),
                            'priority'   => __( 'Priority', 'arkidevs-support' ),
                            'inactivity' => __( 'Inactivity', 'arkidevs-support' ),
                        );
                        $trigger_label = isset( $trigger_labels[ $escalation->trigger_type ] ) ? $trigger_labels[ $escalation->trigger_type ] : $escalation->trigger_type;
                        
                        $action_labels = array(
                            'notify'         => __( 'Notify Supervisors', 'arkidevs-support' ),
                            'assign'         => __( 'Assign to Supervisor', 'arkidevs-support' ),
                            'priority_change' => __( 'Change Priority', 'arkidevs-support' ),
                        );
                        $action_label = isset( $action_labels[ $escalation->action_type ] ) ? $action_labels[ $escalation->action_type ] : $escalation->action_type;
                        
                        if ( 'assign' === $escalation->action_type && ! empty( $escalation->action_value ) ) {
                            $supervisor = get_userdata( $escalation->action_value );
                            if ( $supervisor ) {
                                $action_label .= ' → ' . esc_html( $supervisor->display_name );
                            }
                        } elseif ( 'priority_change' === $escalation->action_type && ! empty( $escalation->action_value ) ) {
                            $priority_label = Arkidevs_Support_Priority::get_priority_label( $escalation->action_value );
                            $action_label .= ' → ' . esc_html( $priority_label );
                        }
                        ?>
                        <tr>
                            <td><?php echo Arkidevs_Support_Utils::format_datetime_with_relative( $escalation->escalated_at ); ?></td>
                            <td><strong><?php echo esc_html( $escalation->rule_name ? $escalation->rule_name : __( 'Unknown Rule', 'arkidevs-support' ) ); ?></strong></td>
                            <td><?php echo esc_html( $trigger_label ); ?> (<?php echo esc_html( $escalation->trigger_value ); ?> <?php esc_html_e( 'hours', 'arkidevs-support' ); ?>)</td>
                            <td><?php echo esc_html( $action_label ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ( current_user_can( 'manage_options' ) ) : ?>
        <div class="ticket-edit">
            <h3><?php esc_html_e( 'Edit Ticket Details', 'arkidevs-support' ); ?></h3>
            <p>
                <label for="ticket-edit-subject"><strong><?php esc_html_e( 'Subject', 'arkidevs-support' ); ?></strong></label>
                <input type="text" id="ticket-edit-subject" value="<?php echo esc_attr( $ticket->subject ); ?>" style="width: 100%; padding: 8px; margin-top: 5px;" />
            </p>
            <p>
                <label for="ticket-edit-description"><strong><?php esc_html_e( 'Description', 'arkidevs-support' ); ?></strong></label>
                <textarea id="ticket-edit-description" rows="6" style="width: 100%; padding: 8px; margin-top: 5px;"><?php echo esc_textarea( $ticket->description ); ?></textarea>
            </p>
            <p>
                <button type="button" id="save-ticket-details" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" class="button button-primary"><?php esc_html_e( 'Save Changes', 'arkidevs-support' ); ?></button>
            </p>
        </div>
    <?php endif; ?>

    <div class="ticket-replies">
        <h3><?php esc_html_e( 'Comments', 'arkidevs-support' ); ?></h3>
        
        <div class="replies-thread">
        <?php
        /**
         * Recursive function to render comments with nested replies
         * 
         * @param object $reply Reply object
         * @param object $ticket Ticket object
         * @param int    $depth Current nesting depth
         * @param int    $initial_replies_limit Number of replies to show initially
         */
        function render_comment_thread( $reply, $ticket, $depth = 0, $initial_replies_limit = 1 ) {
            $is_nested = $depth > 0;
            $author_name = Arkidevs_Support_Utils::get_user_display_name( $reply->user_id );
            $is_internal = ! empty( $reply->is_internal );
            $total_replies = ! empty( $reply->children ) ? count( $reply->children ) : 0;
            $show_view_more = $total_replies > $initial_replies_limit;
            $visible_replies = $show_view_more ? array_slice( $reply->children, 0, $initial_replies_limit ) : ( ! empty( $reply->children ) ? $reply->children : array() );
            $hidden_replies_count = $show_view_more ? ( $total_replies - $initial_replies_limit ) : 0;
            ?>
            <div class="reply-item <?php echo $is_nested ? 'reply-nested' : ''; ?><?php echo $is_internal ? ' reply-internal' : ''; ?>" data-reply-id="<?php echo esc_attr( $reply->id ); ?>" data-author-name="<?php echo esc_attr( $author_name ); ?>" data-depth="<?php echo esc_attr( $depth ); ?>">
                <?php if ( $is_nested ) : ?>
                    <div class="reply-thread-line"></div>
                <?php endif; ?>
                <div class="reply-author">
                    <strong class="reply-author-name <?php echo $is_internal ? 'reply-author-internal' : ''; ?>"><?php echo esc_html( $author_name ); ?></strong>
                    <span class="reply-date"><?php echo Arkidevs_Support_Utils::format_datetime_with_relative( $reply->created_at ); ?></span>
                    <?php if ( $is_internal ) : ?>
                        <span class="reply-internal-badge"><?php esc_html_e( 'Internal note', 'arkidevs-support' ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="reply-content">
                    <div class="reply-text">
                        <?php echo wp_kses_post( wpautop( $reply->message ) ); ?>
                    </div>
                    <?php 
                    $current_user_id = get_current_user_id();
                    $is_comment_author = ( $reply->user_id == $current_user_id );
                    $is_admin = current_user_can( 'manage_options' );
                    $can_edit_delete = $is_comment_author || $is_admin;
                    ?>
                    <?php if ( Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) : ?>
                        <div class="reply-actions">
                            <button type="button" class="button-link reply-to-comment" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" data-reply-id="<?php echo esc_attr( $reply->id ); ?>" data-author-name="<?php echo esc_attr( $author_name ); ?>">
                                <?php esc_html_e( 'Reply', 'arkidevs-support' ); ?>
                            </button>
                            <?php if ( $can_edit_delete ) : ?>
                                <span>|</span>
                                <button type="button" class="button-link edit-reply" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" data-reply-id="<?php echo esc_attr( $reply->id ); ?>">
                                    <?php esc_html_e( 'Edit', 'arkidevs-support' ); ?>
                                </button>
                                <span>|</span>
                                <button type="button" class="button-link delete-reply" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" data-reply-id="<?php echo esc_attr( $reply->id ); ?>">
                                    <?php esc_html_e( 'Delete', 'arkidevs-support' ); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ( ! empty( $reply->children ) ) : ?>
                    <div class="reply-children" data-parent-reply-id="<?php echo esc_attr( $reply->id ); ?>">
                        <?php if ( $show_view_more ) : ?>
                            <div class="reply-hidden-replies" style="display: none;">
                                <?php foreach ( array_slice( $reply->children, $initial_replies_limit ) as $hidden_reply ) : ?>
                                    <?php render_comment_thread( $hidden_reply, $ticket, $depth + 1, $initial_replies_limit ); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php foreach ( $visible_replies as $child_reply ) : ?>
                            <?php render_comment_thread( $child_reply, $ticket, $depth + 1, $initial_replies_limit ); ?>
                        <?php endforeach; ?>
                        
                        <?php if ( $show_view_more ) : ?>
                            <div class="view-replies-toggle">
                                <button type="button" class="button-link view-replies-btn" data-parent-id="<?php echo esc_attr( $reply->id ); ?>">
                                    <?php 
                                    if ( $hidden_replies_count == 1 ) {
                                        esc_html_e( 'View 1 more reply', 'arkidevs-support' );
                                    } else {
                                        printf( 
                                            esc_html__( 'View %d more replies', 'arkidevs-support' ), 
                                            $hidden_replies_count 
                                        );
                                    }
                                    ?>
                                </button>
                                <button type="button" class="button-link hide-replies-btn" data-parent-id="<?php echo esc_attr( $reply->id ); ?>" style="display: none;">
                                    <?php esc_html_e( 'Hide replies', 'arkidevs-support' ); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

        // Render all top-level comments
        foreach ( $replies as $reply ) {
            render_comment_thread( $reply, $ticket, 0 );
        }
        ?>
        </div><!-- .replies-thread -->

        <div class="reply-form" id="main-reply-form">
            <h4><?php esc_html_e( 'Add Comment', 'arkidevs-support' ); ?></h4>
            <?php if ( Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) : ?>
                <?php
                require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-template.php';
                $templates = Arkidevs_Support_Template::get_all();
                if ( ! empty( $templates ) ) :
                    ?>
                    <div style="margin-bottom: 10px;">
                        <label for="template-selector" style="font-weight: 600; margin-right: 10px;"><?php esc_html_e( 'Insert Template:', 'arkidevs-support' ); ?></label>
                        <select id="template-selector" style="width: 300px; margin-right: 10px;">
                            <option value=""><?php esc_html_e( '-- Select Template --', 'arkidevs-support' ); ?></option>
                            <?php
                            $categories = Arkidevs_Support_Template::get_categories();
                            $grouped_templates = array();
                            foreach ( $templates as $template ) {
                                $cat = isset( $categories[ $template->category ] ) ? $categories[ $template->category ] : $template->category;
                                if ( ! isset( $grouped_templates[ $cat ] ) ) {
                                    $grouped_templates[ $cat ] = array();
                                }
                                $grouped_templates[ $cat ][] = $template;
                            }
                            foreach ( $grouped_templates as $category => $cat_templates ) :
                                ?>
                                <optgroup label="<?php echo esc_attr( $category ); ?>">
                                    <?php foreach ( $cat_templates as $template ) : ?>
                                        <option value="<?php echo esc_attr( $template->id ); ?>" data-content="<?php echo esc_attr( $template->content ); ?>">
                                            <?php echo esc_html( $template->name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="insert-template" class="button" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>">
                            <?php esc_html_e( 'Insert', 'arkidevs-support' ); ?>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <textarea id="reply-message" rows="5" style="width: 100%;"></textarea>
            <input type="hidden" id="parent-reply-id" value="" />
            <?php if ( Arkidevs_Support_Security::user_can( 'manage_tickets' ) ) : ?>
                <p>
                    <label>
                        <input type="checkbox" id="reply-is-internal" value="1" />
                        <?php esc_html_e( 'Mark as internal note (visible only to agents/admins)', 'arkidevs-support' ); ?>
                    </label>
                </p>
            <?php endif; ?>
            <button type="button" id="submit-reply" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>" class="button button-primary">
                <?php esc_html_e( 'Submit Comment', 'arkidevs-support' ); ?>
            </button>
            <button type="button" id="cancel-reply" class="button" style="display: none;">
                <?php esc_html_e( 'Cancel', 'arkidevs-support' ); ?>
            </button>
        </div>
    </div>
</div>


