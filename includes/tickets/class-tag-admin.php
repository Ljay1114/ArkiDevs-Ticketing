<?php
/**
 * Tag management admin page
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Tag_Admin
 */
class Arkidevs_Support_Tag_Admin {

    /**
     * Instance
     *
     * @var Arkidevs_Support_Tag_Admin
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Arkidevs_Support_Tag_Admin
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
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'arkidevs-support-admin',
            __( 'Tags', 'arkidevs-support' ),
            __( 'Tags', 'arkidevs-support' ),
            'manage_options',
            'arkidevs-support-tags',
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts( $hook ) {
        if ( 'support_page_arkidevs-support-tags' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }

    /**
     * Render admin page
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'arkidevs-support' ) );
        }

        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';
        $tags = Arkidevs_Support_Tag::get_all();

        // Handle form submissions
        if ( isset( $_POST['action'] ) && check_admin_referer( 'arkidevs_tag_action', 'arkidevs_tag_nonce' ) ) {
            $this->handle_form_submission();
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Ticket Tags', 'arkidevs-support' ); ?></h1>
            
            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Add New Tag Form -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Add New Tag', 'arkidevs-support' ); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'arkidevs_tag_action', 'arkidevs_tag_nonce' ); ?>
                        <input type="hidden" name="action" value="add_tag" />
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="tag_name"><?php esc_html_e( 'Tag Name', 'arkidevs-support' ); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="tag_name" name="tag_name" class="regular-text" required />
                                    <p class="description"><?php esc_html_e( 'The name of the tag (e.g., "Bug", "Feature Request", "Billing")', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="tag_color"><?php esc_html_e( 'Color', 'arkidevs-support' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="tag_color" name="tag_color" class="color-picker" value="#0073aa" />
                                    <p class="description"><?php esc_html_e( 'Color for the tag badge', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="tag_description"><?php esc_html_e( 'Description', 'arkidevs-support' ); ?></label>
                                </th>
                                <td>
                                    <textarea id="tag_description" name="tag_description" rows="3" class="large-text"></textarea>
                                    <p class="description"><?php esc_html_e( 'Optional description for this tag', 'arkidevs-support' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Tag', 'arkidevs-support' ); ?></button>
                        </p>
                    </form>
                </div>

                <!-- Existing Tags List -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Existing Tags', 'arkidevs-support' ); ?></h2>
                    
                    <?php if ( empty( $tags ) ) : ?>
                        <p><?php esc_html_e( 'No tags created yet.', 'arkidevs-support' ); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Name', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Color', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Description', 'arkidevs-support' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'arkidevs-support' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $tags as $tag ) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $tag->name ); ?></strong></td>
                                        <td>
                                            <span style="display: inline-block; padding: 4px 8px; background-color: <?php echo esc_attr( $tag->color ); ?>; color: #fff; border-radius: 3px;">
                                                <?php echo esc_html( $tag->color ); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html( $tag->description ); ?></td>
                                        <td>
                                            <form method="post" action="" style="display: inline;">
                                                <?php wp_nonce_field( 'arkidevs_tag_action', 'arkidevs_tag_nonce' ); ?>
                                                <input type="hidden" name="action" value="delete_tag" />
                                                <input type="hidden" name="tag_id" value="<?php echo esc_attr( $tag->id ); ?>" />
                                                <button type="submit" class="button-link delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this tag? This will remove it from all tickets.', 'arkidevs-support' ); ?>');">
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
            $('.color-picker').wpColorPicker();
        });
        </script>
        <?php
    }

    /**
     * Handle form submission
     */
    private function handle_form_submission() {
        require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-tag.php';

        $action = sanitize_text_field( $_POST['action'] ?? '' );

        if ( 'add_tag' === $action ) {
            $name = sanitize_text_field( $_POST['tag_name'] ?? '' );
            $color = sanitize_hex_color( $_POST['tag_color'] ?? '#0073aa' );
            $description = sanitize_textarea_field( $_POST['tag_description'] ?? '' );

            if ( empty( $name ) ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Tag name is required.', 'arkidevs-support' ) . '</p></div>';
                } );
                return;
            }

            $result = Arkidevs_Support_Tag::create( array(
                'name'        => $name,
                'color'       => $color,
                'description' => $description,
            ) );

            if ( is_wp_error( $result ) ) {
                add_action( 'admin_notices', function() use ( $result ) {
                    echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                } );
            } else {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Tag created successfully!', 'arkidevs-support' ) . '</p></div>';
                } );
            }
        } elseif ( 'delete_tag' === $action ) {
            $tag_id = intval( $_POST['tag_id'] ?? 0 );

            if ( $tag_id ) {
                $result = Arkidevs_Support_Tag::delete( $tag_id );

                if ( $result ) {
                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Tag deleted successfully!', 'arkidevs-support' ) . '</p></div>';
                    } );
                } else {
                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to delete tag.', 'arkidevs-support' ) . '</p></div>';
                    } );
                }
            }
        }
    }
}
