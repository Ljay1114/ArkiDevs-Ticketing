<?php
/**
 * SLA management admin page
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_SLA_Admin
 */
class Arkidevs_Support_SLA_Admin {

    /**
     * Instance
     *
     * @var Arkidevs_Support_SLA_Admin
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_SLA_Admin
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
            __( 'SLA Rules', 'arkidevs-support' ),
            __( 'SLA Rules', 'arkidevs-support' ),
            'manage_options',
            'arkidevs-support-sla',
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

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-rule.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';
        $rules = Arkidevs_Support_SLA_Rule::get_all();
        $priorities = Arkidevs_Support_Priority::get_priorities();

        // Handle form submissions
        if ( isset( $_POST['action'] ) && check_admin_referer( 'arkidevs_sla_action', 'arkidevs_sla_nonce' ) ) {
            $this->handle_form_submission();
            // Reload rules after submission
            $rules = Arkidevs_Support_SLA_Rule::get_all();
        }

        // Handle edit mode
        $edit_rule_id = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : 0;
        $edit_rule = $edit_rule_id ? Arkidevs_Support_SLA_Rule::get_by_id( $edit_rule_id ) : null;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'SLA Rules', 'arkidevs-support' ); ?></h1>
            
            <div style="margin-top: 20px;">
                <p class="description">
                    <?php esc_html_e( 'Configure Service Level Agreement (SLA) rules based on ticket priority. These rules define response and resolution time targets.', 'arkidevs-support' ); ?>
                </p>
            </div>

            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Add/Edit Rule Form -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php echo $edit_rule ? esc_html__( 'Edit SLA Rule', 'arkidevs-support' ) : esc_html__( 'Add New SLA Rule', 'arkidevs-support' ); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'arkidevs_sla_action', 'arkidevs_sla_nonce' ); ?>
                        <input type="hidden" name="action" value="<?php echo $edit_rule ? 'update_sla_rule' : 'add_sla_rule'; ?>" />
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
                                    <p class="description"><?php esc_html_e( 'A descriptive name for this SLA rule', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="rule_priority"><?php esc_html_e( 'Priority', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <select id="rule_priority" name="rule_priority" class="regular-text" required>
                                        <option value=""><?php esc_html_e( '-- Select Priority --', 'arkidevs-support' ); ?></option>
                                        <?php foreach ( $priorities as $key => $label ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $edit_rule ? $edit_rule->priority : '', $key ); ?>>
                                                <?php echo esc_html( $label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'This rule will apply to tickets with this priority', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="first_response_hours"><?php esc_html_e( 'First Response Time (hours)', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="number" id="first_response_hours" name="first_response_hours" class="small-text" value="<?php echo $edit_rule ? esc_attr( $edit_rule->first_response_hours ) : '24'; ?>" min="1" required />
                                    <p class="description"><?php esc_html_e( 'Target time in hours for first agent response', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="resolution_hours"><?php esc_html_e( 'Resolution Time (hours)', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="number" id="resolution_hours" name="resolution_hours" class="small-text" value="<?php echo $edit_rule ? esc_attr( $edit_rule->resolution_hours ) : '72'; ?>" min="1" required />
                                    <p class="description"><?php esc_html_e( 'Target time in hours for ticket resolution', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="rule_enabled"><?php esc_html_e( 'Enabled', 'arkidevs-support' ); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="rule_enabled" name="rule_enabled" value="1" <?php checked( $edit_rule ? $edit_rule->enabled : 1, 1 ); ?> />
                                        <?php esc_html_e( 'Enable this SLA rule', 'arkidevs-support' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php echo $edit_rule ? esc_html__( 'Update Rule', 'arkidevs-support' ) : esc_html__( 'Add Rule', 'arkidevs-support' ); ?></button>
                            <?php if ( $edit_rule ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=arkidevs-support-sla' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'arkidevs-support' ); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>

                <!-- Existing Rules List -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Existing SLA Rules', 'arkidevs-support' ); ?></h2>
                    
                    <?php if ( empty( $rules ) ) : ?>
                        <p><?php esc_html_e( 'No SLA rules created yet.', 'arkidevs-support' ); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Name', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Priority', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'First Response', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Resolution', 'arkidevs-support' ); ?></th>
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
                                            $priority_label = isset( $priorities[ $rule->priority ] ) ? $priorities[ $rule->priority ] : $rule->priority;
                                            echo esc_html( $priority_label );
                                            ?>
                                        </td>
                                        <td><?php echo esc_html( $rule->first_response_hours ); ?> <?php esc_html_e( 'hours', 'arkidevs-support' ); ?></td>
                                        <td><?php echo esc_html( $rule->resolution_hours ); ?> <?php esc_html_e( 'hours', 'arkidevs-support' ); ?></td>
                                        <td>
                                            <?php if ( $rule->enabled ) : ?>
                                                <span style="color: green;"><?php esc_html_e( 'Enabled', 'arkidevs-support' ); ?></span>
                                            <?php else : ?>
                                                <span style="color: red;"><?php esc_html_e( 'Disabled', 'arkidevs-support' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url( add_query_arg( 'edit', $rule->id, admin_url( 'admin.php?page=arkidevs-support-sla' ) ) ); ?>" class="button button-small">
                                                <?php esc_html_e( 'Edit', 'arkidevs-support' ); ?>
                                            </a>
                                            <form method="post" action="" style="display: inline;">
                                                <?php wp_nonce_field( 'arkidevs_sla_action', 'arkidevs_sla_nonce' ); ?>
                                                <input type="hidden" name="action" value="delete_sla_rule" />
                                                <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->id ); ?>" />
                                                <button type="submit" class="button-link delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this SLA rule?', 'arkidevs-support' ); ?>');">
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
        <?php
    }

    /**
     * Handle form submission
     */
    private function handle_form_submission() {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/sla/class-sla-rule.php';

        $action = sanitize_text_field( $_POST['action'] ?? '' );

        if ( 'add_sla_rule' === $action ) {
            $name = sanitize_text_field( $_POST['rule_name'] ?? '' );
            $priority = sanitize_text_field( $_POST['rule_priority'] ?? '' );
            $first_response_hours = intval( $_POST['first_response_hours'] ?? 24 );
            $resolution_hours = intval( $_POST['resolution_hours'] ?? 72 );
            $enabled = isset( $_POST['rule_enabled'] ) ? 1 : 0;

            if ( empty( $name ) || empty( $priority ) ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Rule name and priority are required.', 'arkidevs-support' ) . '</p></div>';
                } );
                return;
            }

            $result = Arkidevs_Support_SLA_Rule::create( array(
                'name'                => $name,
                'priority'            => $priority,
                'first_response_hours' => $first_response_hours,
                'resolution_hours'     => $resolution_hours,
                'enabled'              => $enabled,
            ) );

            if ( is_wp_error( $result ) ) {
                add_action( 'admin_notices', function() use ( $result ) {
                    echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                } );
            } else {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'SLA rule created successfully!', 'arkidevs-support' ) . '</p></div>';
                } );
            }
        } elseif ( 'update_sla_rule' === $action ) {
            $rule_id = intval( $_POST['rule_id'] ?? 0 );
            $name = sanitize_text_field( $_POST['rule_name'] ?? '' );
            $priority = sanitize_text_field( $_POST['rule_priority'] ?? '' );
            $first_response_hours = intval( $_POST['first_response_hours'] ?? 24 );
            $resolution_hours = intval( $_POST['resolution_hours'] ?? 72 );
            $enabled = isset( $_POST['rule_enabled'] ) ? 1 : 0;

            if ( ! $rule_id ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid rule ID.', 'arkidevs-support' ) . '</p></div>';
                } );
                return;
            }

            $result = Arkidevs_Support_SLA_Rule::update( $rule_id, array(
                'name'                => $name,
                'priority'            => $priority,
                'first_response_hours' => $first_response_hours,
                'resolution_hours'     => $resolution_hours,
                'enabled'              => $enabled,
            ) );

            if ( is_wp_error( $result ) ) {
                add_action( 'admin_notices', function() use ( $result ) {
                    echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                } );
            } else {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'SLA rule updated successfully!', 'arkidevs-support' ) . '</p></div>';
                } );
            }
        } elseif ( 'delete_sla_rule' === $action ) {
            $rule_id = intval( $_POST['rule_id'] ?? 0 );

            if ( $rule_id ) {
                $result = Arkidevs_Support_SLA_Rule::delete( $rule_id );

                if ( $result ) {
                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'SLA rule deleted successfully!', 'arkidevs-support' ) . '</p></div>';
                    } );
                } else {
                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to delete SLA rule.', 'arkidevs-support' ) . '</p></div>';
                    } );
                }
            }
        }
    }
}
