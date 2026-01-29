<?php
/**
 * Escalation Rules management admin page
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Escalation_Admin
 */
class Arkidevs_Support_Escalation_Admin {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Escalation_Admin
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Escalation_Admin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'arkidevs-support-admin',
            __( 'Escalation Rules', 'arkidevs-support' ),
            __( 'Escalation Rules', 'arkidevs-support' ),
            'manage_options',
            'arkidevs-support-escalation',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render admin page
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'arkidevs-support' ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/escalation/class-escalation-rule.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/helpers/class-utils.php';

        $rules = Arkidevs_Support_Escalation_Rule::get_all();
        $priorities = Arkidevs_Support_Priority::get_priorities();
        $statuses = Arkidevs_Support_Status::get_statuses();
        $supervisors = Arkidevs_Support_Escalation_Engine::get_supervisors();

        // Handle form submissions
        if ( isset( $_POST['action'] ) && check_admin_referer( 'arkidevs_escalation_action', 'arkidevs_escalation_nonce' ) ) {
            $this->handle_form_submission();
            // Reload rules after submission
            $rules = Arkidevs_Support_Escalation_Rule::get_all();
        }

        // Handle edit mode
        $edit_rule_id = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : 0;
        $edit_rule = $edit_rule_id ? Arkidevs_Support_Escalation_Rule::get_by_id( $edit_rule_id ) : null;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Escalation Rules', 'arkidevs-support' ); ?></h1>
            
            <div style="margin-top: 20px;">
                <p class="description">
                    <?php esc_html_e( 'Configure automatic escalation rules to prevent tickets from being forgotten. Escalations can be based on time, priority, or inactivity.', 'arkidevs-support' ); ?>
                </p>
            </div>

            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Add/Edit Rule Form -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php echo $edit_rule ? esc_html__( 'Edit Escalation Rule', 'arkidevs-support' ) : esc_html__( 'Add New Escalation Rule', 'arkidevs-support' ); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'arkidevs_escalation_action', 'arkidevs_escalation_nonce' ); ?>
                        <input type="hidden" name="action" value="<?php echo $edit_rule ? 'update_escalation_rule' : 'add_escalation_rule'; ?>" />
                        <?php if ( $edit_rule ) : ?>
                            <input type="hidden" name="rule_id" value="<?php echo esc_attr( $edit_rule->id ); ?>" />
                        <?php endif; ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="rule_name"><?php esc_html_e( 'Rule Name', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="rule_name" name="rule_name" class="regular-text" value="<?php echo $edit_rule ? esc_attr( $edit_rule->name ) : ''; ?>" required />
                                    <p class="description"><?php esc_html_e( 'A descriptive name for this escalation rule', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="trigger_type"><?php esc_html_e( 'Trigger Type', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <select id="trigger_type" name="trigger_type" class="regular-text" required>
                                        <option value=""><?php esc_html_e( '-- Select Trigger --', 'arkidevs-support' ); ?></option>
                                        <option value="time" <?php selected( $edit_rule ? $edit_rule->trigger_type : '', 'time' ); ?>>
                                            <?php esc_html_e( 'Time-based (hours since creation)', 'arkidevs-support' ); ?>
                                        </option>
                                        <option value="priority" <?php selected( $edit_rule ? $edit_rule->trigger_type : '', 'priority' ); ?>>
                                            <?php esc_html_e( 'Priority-based (high/critical priority)', 'arkidevs-support' ); ?>
                                        </option>
                                        <option value="inactivity" <?php selected( $edit_rule ? $edit_rule->trigger_type : '', 'inactivity' ); ?>>
                                            <?php esc_html_e( 'Inactivity-based (hours since last activity)', 'arkidevs-support' ); ?>
                                        </option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'What condition triggers this escalation?', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="trigger_value"><?php esc_html_e( 'Trigger Value', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="number" id="trigger_value" name="trigger_value" class="small-text" value="<?php echo $edit_rule ? esc_attr( $edit_rule->trigger_value ) : '24'; ?>" min="1" required />
                                    <span id="trigger_value_label"><?php esc_html_e( 'hours', 'arkidevs-support' ); ?></span>
                                    <p class="description" id="trigger_value_desc">
                                        <?php esc_html_e( 'For time-based: hours since ticket creation. For inactivity: hours since last activity. For priority: minimum priority level (1=low, 2=medium, 3=high, 4=critical).', 'arkidevs-support' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="priority_filter"><?php esc_html_e( 'Priority Filter (Optional)', 'arkidevs-support' ); ?></label>
                                </th>
                                <td>
                                    <select id="priority_filter" name="priority_filter" class="regular-text">
                                        <option value=""><?php esc_html_e( '-- All Priorities --', 'arkidevs-support' ); ?></option>
                                        <?php foreach ( $priorities as $key => $label ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $edit_rule ? $edit_rule->priority_filter : '', $key ); ?>>
                                                <?php echo esc_html( $label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Only apply this rule to tickets with this priority (leave blank for all)', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="status_filter"><?php esc_html_e( 'Status Filter (Optional)', 'arkidevs-support' ); ?></label>
                                </th>
                                <td>
                                    <select id="status_filter" name="status_filter" class="regular-text">
                                        <option value=""><?php esc_html_e( '-- All Statuses --', 'arkidevs-support' ); ?></option>
                                        <?php foreach ( $statuses as $key => $label ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $edit_rule ? $edit_rule->status_filter : '', $key ); ?>>
                                                <?php echo esc_html( $label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Only apply this rule to tickets with this status (leave blank for all)', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="action_type"><?php esc_html_e( 'Action Type', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <select id="action_type" name="action_type" class="regular-text" required>
                                        <option value=""><?php esc_html_e( '-- Select Action --', 'arkidevs-support' ); ?></option>
                                        <option value="notify" <?php selected( $edit_rule ? $edit_rule->action_type : '', 'notify' ); ?>>
                                            <?php esc_html_e( 'Notify Supervisors', 'arkidevs-support' ); ?>
                                        </option>
                                        <option value="assign" <?php selected( $edit_rule ? $edit_rule->action_type : '', 'assign' ); ?>>
                                            <?php esc_html_e( 'Assign to Supervisor', 'arkidevs-support' ); ?>
                                        </option>
                                        <option value="priority_change" <?php selected( $edit_rule ? $edit_rule->action_type : '', 'priority_change' ); ?>>
                                            <?php esc_html_e( 'Change Priority', 'arkidevs-support' ); ?>
                                        </option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'What action should be taken when this rule triggers?', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr id="action_value_row" style="display: none;">
                                <th scope="row">
                                    <label for="action_value"><?php esc_html_e( 'Action Value', 'arkidevs-support' ); ?></label>
                                </th>
                                <td>
                                    <select id="action_value" name="action_value" class="regular-text">
                                        <option value=""><?php esc_html_e( '-- Select --', 'arkidevs-support' ); ?></option>
                                        <optgroup id="supervisor_group" label="<?php esc_attr_e( 'Supervisors', 'arkidevs-support' ); ?>">
                                            <?php foreach ( $supervisors as $supervisor ) : ?>
                                                <option value="<?php echo esc_attr( $supervisor->ID ); ?>" <?php selected( $edit_rule ? $edit_rule->action_value : '', $supervisor->ID ); ?>>
                                                    <?php echo esc_html( $supervisor->display_name . ' (' . $supervisor->user_email . ')' ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup id="priority_group" label="<?php esc_attr_e( 'Priority Levels', 'arkidevs-support' ); ?>">
                                            <?php foreach ( $priorities as $key => $label ) : ?>
                                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $edit_rule ? $edit_rule->action_value : '', $key ); ?>>
                                                    <?php echo esc_html( $label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                    <p class="description" id="action_value_desc"></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="rule_enabled"><?php esc_html_e( 'Enabled', 'arkidevs-support' ); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="rule_enabled" name="rule_enabled" value="1" <?php checked( $edit_rule ? $edit_rule->enabled : 1, 1 ); ?> />
                                        <?php esc_html_e( 'Enable this escalation rule', 'arkidevs-support' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php echo $edit_rule ? esc_html__( 'Update Rule', 'arkidevs-support' ) : esc_html__( 'Add Rule', 'arkidevs-support' ); ?></button>
                            <?php if ( $edit_rule ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=arkidevs-support-escalation' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'arkidevs-support' ); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>

                <!-- Existing Rules List -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Existing Escalation Rules', 'arkidevs-support' ); ?></h2>
                    
                    <?php if ( empty( $rules ) ) : ?>
                        <p><?php esc_html_e( 'No escalation rules created yet.', 'arkidevs-support' ); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Name', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Trigger', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Action', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Filters', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'arkidevs-support' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $rules as $rule ) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $rule->name ); ?></strong></td>
                                        <td>
                                            <?php
                                            $trigger_labels = array(
                                                'time'       => __( 'Time', 'arkidevs-support' ),
                                                'priority'   => __( 'Priority', 'arkidevs-support' ),
                                                'inactivity' => __( 'Inactivity', 'arkidevs-support' ),
                                            );
                                            $trigger_label = isset( $trigger_labels[ $rule->trigger_type ] ) ? $trigger_labels[ $rule->trigger_type ] : $rule->trigger_type;
                                            echo esc_html( $trigger_label ) . ' (' . esc_html( $rule->trigger_value ) . ')';
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $action_labels = array(
                                                'notify'         => __( 'Notify', 'arkidevs-support' ),
                                                'assign'         => __( 'Assign', 'arkidevs-support' ),
                                                'priority_change' => __( 'Change Priority', 'arkidevs-support' ),
                                            );
                                            $action_label = isset( $action_labels[ $rule->action_type ] ) ? $action_labels[ $rule->action_type ] : $rule->action_type;
                                            echo esc_html( $action_label );
                                            if ( ! empty( $rule->action_value ) ) {
                                                if ( 'assign' === $rule->action_type ) {
                                                    $supervisor = get_userdata( $rule->action_value );
                                                    if ( $supervisor ) {
                                                        echo ' → ' . esc_html( $supervisor->display_name );
                                                    }
                                                } elseif ( 'priority_change' === $rule->action_type ) {
                                                    $priority_label = isset( $priorities[ $rule->action_value ] ) ? $priorities[ $rule->action_value ] : $rule->action_value;
                                                    echo ' → ' . esc_html( $priority_label );
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $filters = array();
                                            if ( ! empty( $rule->priority_filter ) ) {
                                                $priority_label = isset( $priorities[ $rule->priority_filter ] ) ? $priorities[ $rule->priority_filter ] : $rule->priority_filter;
                                                $filters[] = __( 'Priority:', 'arkidevs-support' ) . ' ' . esc_html( $priority_label );
                                            }
                                            if ( ! empty( $rule->status_filter ) ) {
                                                $status_label = isset( $statuses[ $rule->status_filter ] ) ? $statuses[ $rule->status_filter ] : $rule->status_filter;
                                                $filters[] = __( 'Status:', 'arkidevs-support' ) . ' ' . esc_html( $status_label );
                                            }
                                            echo ! empty( $filters ) ? implode( '<br>', $filters ) : esc_html__( 'None', 'arkidevs-support' );
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ( $rule->enabled ) : ?>
                                                <span style="color: green;"><?php esc_html_e( 'Enabled', 'arkidevs-support' ); ?></span>
                                            <?php else : ?>
                                                <span style="color: red;"><?php esc_html_e( 'Disabled', 'arkidevs-support' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url( add_query_arg( 'edit', $rule->id, admin_url( 'admin.php?page=arkidevs-support-escalation' ) ) ); ?>" class="button button-small">
                                                <?php esc_html_e( 'Edit', 'arkidevs-support' ); ?>
                                            </a>
                                            <form method="post" action="" style="display: inline;">
                                                <?php wp_nonce_field( 'arkidevs_escalation_action', 'arkidevs_escalation_nonce' ); ?>
                                                <input type="hidden" name="action" value="delete_escalation_rule" />
                                                <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->id ); ?>" />
                                                <button type="submit" class="button-link delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this escalation rule?', 'arkidevs-support' ); ?>');">
                                                    <?php esc_html_e( 'Delete', 'arkidevs-support' ); ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            function updateActionValueField() {
                var actionType = $('#action_type').val();
                var actionValueRow = $('#action_value_row');
                var actionValue = $('#action_value');
                var supervisorGroup = $('#supervisor_group');
                var priorityGroup = $('#priority_group');
                var actionValueDesc = $('#action_value_desc');

                if (actionType === 'notify') {
                    actionValueRow.hide();
                    actionValue.val('');
                } else if (actionType === 'assign') {
                    actionValueRow.show();
                    supervisorGroup.show();
                    priorityGroup.hide();
                    actionValueDesc.text('<?php esc_html_e( 'Select a supervisor to assign the ticket to', 'arkidevs-support' ); ?>');
                } else if (actionType === 'priority_change') {
                    actionValueRow.show();
                    supervisorGroup.hide();
                    priorityGroup.show();
                    actionValueDesc.text('<?php esc_html_e( 'Select the new priority level', 'arkidevs-support' ); ?>');
                } else {
                    actionValueRow.hide();
                }
            }

            $('#action_type').on('change', updateActionValueField);
            updateActionValueField(); // Initialize on page load
        });
        </script>
        <?php
    }

    /**
     * Handle form submission
     */
    private function handle_form_submission() {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/escalation/class-escalation-rule.php';

        $action = sanitize_text_field( $_POST['action'] ?? '' );

        if ( 'add_escalation_rule' === $action ) {
            $name = sanitize_text_field( $_POST['rule_name'] ?? '' );
            $trigger_type = sanitize_text_field( $_POST['trigger_type'] ?? '' );
            $trigger_value = intval( $_POST['trigger_value'] ?? 24 );
            $priority_filter = ! empty( $_POST['priority_filter'] ) ? sanitize_text_field( $_POST['priority_filter'] ) : null;
            $status_filter = ! empty( $_POST['status_filter'] ) ? sanitize_text_field( $_POST['status_filter'] ) : null;
            $action_type = sanitize_text_field( $_POST['action_type'] ?? '' );
            $action_value = ! empty( $_POST['action_value'] ) ? sanitize_text_field( $_POST['action_value'] ) : null;
            $enabled = isset( $_POST['rule_enabled'] ) ? 1 : 0;

            if ( empty( $name ) || empty( $trigger_type ) || empty( $action_type ) ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Rule name, trigger type, and action type are required.', 'arkidevs-support' ) . '</p></div>';
                } );
                return;
            }

            $result = Arkidevs_Support_Escalation_Rule::create( array(
                'name'           => $name,
                'trigger_type'   => $trigger_type,
                'trigger_value'  => $trigger_value,
                'priority_filter' => $priority_filter,
                'status_filter'   => $status_filter,
                'action_type'     => $action_type,
                'action_value'    => $action_value,
                'enabled'         => $enabled,
            ) );

            if ( is_wp_error( $result ) ) {
                add_action( 'admin_notices', function() use ( $result ) {
                    echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                } );
            } else {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Escalation rule created successfully!', 'arkidevs-support' ) . '</p></div>';
                } );
            }
        } elseif ( 'update_escalation_rule' === $action ) {
            $rule_id = intval( $_POST['rule_id'] ?? 0 );
            $name = sanitize_text_field( $_POST['rule_name'] ?? '' );
            $trigger_type = sanitize_text_field( $_POST['trigger_type'] ?? '' );
            $trigger_value = intval( $_POST['trigger_value'] ?? 24 );
            $priority_filter = ! empty( $_POST['priority_filter'] ) ? sanitize_text_field( $_POST['priority_filter'] ) : null;
            $status_filter = ! empty( $_POST['status_filter'] ) ? sanitize_text_field( $_POST['status_filter'] ) : null;
            $action_type = sanitize_text_field( $_POST['action_type'] ?? '' );
            $action_value = ! empty( $_POST['action_value'] ) ? sanitize_text_field( $_POST['action_value'] ) : null;
            $enabled = isset( $_POST['rule_enabled'] ) ? 1 : 0;

            if ( ! $rule_id ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid rule ID.', 'arkidevs-support' ) . '</p></div>';
                } );
                return;
            }

            $result = Arkidevs_Support_Escalation_Rule::update( $rule_id, array(
                'name'           => $name,
                'trigger_type'   => $trigger_type,
                'trigger_value'  => $trigger_value,
                'priority_filter' => $priority_filter,
                'status_filter'   => $status_filter,
                'action_type'     => $action_type,
                'action_value'    => $action_value,
                'enabled'         => $enabled,
            ) );

            if ( is_wp_error( $result ) ) {
                add_action( 'admin_notices', function() use ( $result ) {
                    echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                } );
            } else {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Escalation rule updated successfully!', 'arkidevs-support' ) . '</p></div>';
                } );
            }
        } elseif ( 'delete_escalation_rule' === $action ) {
            $rule_id = intval( $_POST['rule_id'] ?? 0 );

            if ( $rule_id ) {
                $result = Arkidevs_Support_Escalation_Rule::delete( $rule_id );

                if ( $result ) {
                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Escalation rule deleted successfully!', 'arkidevs-support' ) . '</p></div>';
                    } );
                } else {
                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to delete escalation rule.', 'arkidevs-support' ) . '</p></div>';
                    } );
                }
            }
        }
    }
}
