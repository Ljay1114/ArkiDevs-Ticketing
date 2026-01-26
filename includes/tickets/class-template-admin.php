<?php
/**
 * Template management admin page
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Template_Admin
 */
class Arkidevs_Support_Template_Admin {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Template_Admin
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Template_Admin
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
            __( 'Templates', 'arkidevs-support' ),
            __( 'Templates', 'arkidevs-support' ),
            'manage_options',
            'arkidevs-support-templates',
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

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-template.php';
        $templates = Arkidevs_Support_Template::get_all();
        $categories = Arkidevs_Support_Template::get_categories();

        // Handle form submissions
        if ( isset( $_POST['action'] ) && check_admin_referer( 'arkidevs_template_action', 'arkidevs_template_nonce' ) ) {
            $this->handle_form_submission();
            // Reload templates after submission
            $templates = Arkidevs_Support_Template::get_all();
        }

        // Handle edit mode
        $edit_template_id = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : 0;
        $edit_template = $edit_template_id ? Arkidevs_Support_Template::get_by_id( $edit_template_id ) : null;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Ticket Templates', 'arkidevs-support' ); ?></h1>
            
            <div style="margin-top: 20px;">
                <p class="description">
                    <?php esc_html_e( 'Create reusable response templates with variables. Use variables like {customer_name}, {ticket_number}, {agent_name}, etc.', 'arkidevs-support' ); ?>
                </p>
            </div>

            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Add/Edit Template Form -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php echo $edit_template ? esc_html__( 'Edit Template', 'arkidevs-support' ) : esc_html__( 'Add New Template', 'arkidevs-support' ); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'arkidevs_template_action', 'arkidevs_template_nonce' ); ?>
                        <input type="hidden" name="action" value="<?php echo $edit_template ? 'update_template' : 'add_template'; ?>" />
                        <?php if ( $edit_template ) : ?>
                            <input type="hidden" name="template_id" value="<?php echo esc_attr( $edit_template->id ); ?>" />
                        <?php endif; ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="template_name"><?php esc_html_e( 'Template Name', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="template_name" name="template_name" class="regular-text" value="<?php echo $edit_template ? esc_attr( $edit_template->name ) : ''; ?>" required />
                                    <p class="description"><?php esc_html_e( 'A descriptive name for this template', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="template_category"><?php esc_html_e( 'Category', 'arkidevs-support' ); ?></label>
                                </th>
                                <td>
                                    <select id="template_category" name="template_category" class="regular-text">
                                        <?php foreach ( $categories as $key => $label ) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $edit_template ? $edit_template->category : 'general', $key ); ?>>
                                                <?php echo esc_html( $label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="template_content"><?php esc_html_e( 'Template Content', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <textarea id="template_content" name="template_content" rows="10" class="large-text" required><?php echo $edit_template ? esc_textarea( $edit_template->content ) : ''; ?></textarea>
                                    <p class="description">
                                        <?php esc_html_e( 'Available variables:', 'arkidevs-support' ); ?>
                                        <code>{customer_name}</code>, <code>{customer_email}</code>, <code>{customer_first_name}</code>, <code>{customer_last_name}</code>,
                                        <code>{ticket_number}</code>, <code>{ticket_subject}</code>, <code>{ticket_status}</code>, <code>{ticket_priority}</code>,
                                        <code>{agent_name}</code>, <code>{agent_email}</code>,
                                        <code>{current_date}</code>, <code>{current_time}</code>, <code>{current_datetime}</code>,
                                        <code>{site_name}</code>, <code>{site_url}</code>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php echo $edit_template ? esc_html__( 'Update Template', 'arkidevs-support' ) : esc_html__( 'Add Template', 'arkidevs-support' ); ?></button>
                            <?php if ( $edit_template ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=arkidevs-support-templates' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'arkidevs-support' ); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>

                <!-- Existing Templates List -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Existing Templates', 'arkidevs-support' ); ?></h2>
                    
                    <?php if ( empty( $templates ) ) : ?>
                        <p><?php esc_html_e( 'No templates created yet.', 'arkidevs-support' ); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Name', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Category', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Preview', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'arkidevs-support' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $templates as $template ) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $template->name ); ?></strong></td>
                                        <td>
                                            <?php
                                            $cat_label = isset( $categories[ $template->category ] ) ? $categories[ $template->category ] : $template->category;
                                            echo esc_html( $cat_label );
                                            ?>
                                        </td>
                                        <td>
                                            <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr( wp_strip_all_tags( $template->content ) ); ?>">
                                                <?php echo esc_html( wp_trim_words( wp_strip_all_tags( $template->content ), 15 ) ); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url( add_query_arg( 'edit', $template->id, admin_url( 'admin.php?page=arkidevs-support-templates' ) ) ); ?>" class="button button-small">
                                                <?php esc_html_e( 'Edit', 'arkidevs-support' ); ?>
                                            </a>
                                            <form method="post" action="" style="display: inline;">
                                                <?php wp_nonce_field( 'arkidevs_template_action', 'arkidevs_template_nonce' ); ?>
                                                <input type="hidden" name="action" value="delete_template" />
                                                <input type="hidden" name="template_id" value="<?php echo esc_attr( $template->id ); ?>" />
                                                <button type="submit" class="button-link delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this template?', 'arkidevs-support' ); ?>');">
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
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-template.php';

        $action = sanitize_text_field( $_POST['action'] ?? '' );

        if ( 'add_template' === $action ) {
            $name = sanitize_text_field( $_POST['template_name'] ?? '' );
            $content = wp_kses_post( $_POST['template_content'] ?? '' );
            $category = sanitize_text_field( $_POST['template_category'] ?? 'general' );

            if ( empty( $name ) ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Template name is required.', 'arkidevs-support' ) . '</p></div>';
                } );
                return;
            }

            if ( empty( $content ) ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Template content is required.', 'arkidevs-support' ) . '</p></div>';
                } );
                return;
            }

            $result = Arkidevs_Support_Template::create( array(
                'name'     => $name,
                'content'  => $content,
                'category' => $category,
            ) );

            if ( is_wp_error( $result ) ) {
                add_action( 'admin_notices', function() use ( $result ) {
                    echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                } );
            } else {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template created successfully!', 'arkidevs-support' ) . '</p></div>';
                } );
            }
        } elseif ( 'update_template' === $action ) {
            $template_id = intval( $_POST['template_id'] ?? 0 );
            $name = sanitize_text_field( $_POST['template_name'] ?? '' );
            $content = wp_kses_post( $_POST['template_content'] ?? '' );
            $category = sanitize_text_field( $_POST['template_category'] ?? 'general' );

            if ( ! $template_id ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid template ID.', 'arkidevs-support' ) . '</p></div>';
                } );
                return;
            }

            $result = Arkidevs_Support_Template::update( $template_id, array(
                'name'     => $name,
                'content'  => $content,
                'category' => $category,
            ) );

            if ( is_wp_error( $result ) ) {
                add_action( 'admin_notices', function() use ( $result ) {
                    echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                } );
            } else {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template updated successfully!', 'arkidevs-support' ) . '</p></div>';
                } );
            }
        } elseif ( 'delete_template' === $action ) {
            $template_id = intval( $_POST['template_id'] ?? 0 );

            if ( $template_id ) {
                $result = Arkidevs_Support_Template::delete( $template_id );

                if ( $result ) {
                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template deleted successfully!', 'arkidevs-support' ) . '</p></div>';
                    } );
                } else {
                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to delete template.', 'arkidevs-support' ) . '</p></div>';
                    } );
                }
            }
        }
    }
}
