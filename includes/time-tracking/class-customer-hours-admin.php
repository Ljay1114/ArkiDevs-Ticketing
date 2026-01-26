<?php
/**
 * Customer Hours Admin Management
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Customer_Hours_Admin
 */
class Arkidevs_Support_Customer_Hours_Admin {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Customer_Hours_Admin
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Customer_Hours_Admin
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
            __( 'Customer Hours', 'arkidevs-support' ),
            __( 'Customer Hours', 'arkidevs-support' ),
            'manage_options',
            'arkidevs-support-customer-hours',
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

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/database/class-customer-hours-table.php';
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/time-tracking/class-time-calculator.php';

        // Get all customers (users with customer role)
        $customers = get_users( array(
            'role' => 'arkidevs_customer',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ) );

        // Get hours for each customer
        $customers_with_hours = array();
        foreach ( $customers as $customer ) {
            $hours_summary = Arkidevs_Support_Time_Calculator::get_customer_hours_summary( $customer->ID );
            $customers_with_hours[] = array(
                'customer' => $customer,
                'hours' => $hours_summary,
            );
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Customer Hours Management', 'arkidevs-support' ); ?></h1>
            
            <div style="margin-top: 20px;">
                <p class="description">
                    <?php esc_html_e( 'Allocate support hours to customers. Hours are automatically deducted when agents track time on customer tickets.', 'arkidevs-support' ); ?>
                </p>
            </div>

            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Allocate Hours Form -->
                <div style="flex: 0 0 350px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Allocate Hours', 'arkidevs-support' ); ?></h2>
                    <form id="allocate-hours-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="customer_select"><?php esc_html_e( 'Customer', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <select id="customer_select" name="customer_id" class="regular-text" required>
                                        <option value=""><?php esc_html_e( '-- Select Customer --', 'arkidevs-support' ); ?></option>
                                        <?php foreach ( $customers as $customer ) : ?>
                                            <option value="<?php echo esc_attr( $customer->ID ); ?>">
                                                <?php echo esc_html( $customer->display_name . ' (' . $customer->user_email . ')' ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hours_amount"><?php esc_html_e( 'Hours', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="number" id="hours_amount" name="hours" class="small-text" step="0.01" min="0.01" value="" required />
                                    <p class="description"><?php esc_html_e( 'Number of hours to allocate (e.g., 10.5)', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Allocate Hours', 'arkidevs-support' ); ?></button>
                        </p>
                    </form>
                    <div id="allocate-hours-message" style="margin-top: 10px;"></div>
                </div>

                <!-- Customer Hours List -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Customer Hours Summary', 'arkidevs-support' ); ?></h2>
                    
                    <?php if ( empty( $customers_with_hours ) ) : ?>
                        <p><?php esc_html_e( 'No customers found.', 'arkidevs-support' ); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Customer', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Email', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Hours Allocated', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Hours Spent', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Hours Remaining', 'arkidevs-support' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $customers_with_hours as $item ) : 
                                    $customer = $item['customer'];
                                    $hours = $item['hours'];
                                    $remaining_class = $hours['remaining'] < 0 ? 'style="color: #dc3232; font-weight: bold;"' : '';
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $customer->display_name ); ?></strong></td>
                                        <td><?php echo esc_html( $customer->user_email ); ?></td>
                                        <td><?php echo esc_html( number_format( $hours['allocated'], 2 ) ); ?></td>
                                        <td><?php echo esc_html( number_format( $hours['spent'], 2 ) ); ?></td>
                                        <td <?php echo $remaining_class; ?>>
                                            <?php echo esc_html( number_format( $hours['remaining'], 2 ) ); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#allocate-hours-form').on('submit', function(e) {
                e.preventDefault();
                
                var customerId = $('#customer_select').val();
                var hours = $('#hours_amount').val();
                var $message = $('#allocate-hours-message');
                
                if (!customerId || !hours || parseFloat(hours) <= 0) {
                    $message.html('<div class="notice notice-error"><p><?php esc_html_e( 'Please select a customer and enter valid hours.', 'arkidevs-support' ); ?></p></div>');
                    return;
                }
                
                $message.html('<div class="notice notice-info"><p><?php esc_html_e( 'Processing...', 'arkidevs-support' ); ?></p></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'arkidevs_allocate_hours',
                        nonce: '<?php echo wp_create_nonce( 'arkidevs_support_nonce' ); ?>',
                        customer_id: customerId,
                        hours: hours
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                            $('#allocate-hours-form')[0].reset();
                            // Reload page after 1 second to show updated hours
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            $message.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $message.html('<div class="notice notice-error"><p><?php esc_html_e( 'An error occurred. Please try again.', 'arkidevs-support' ); ?></p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
