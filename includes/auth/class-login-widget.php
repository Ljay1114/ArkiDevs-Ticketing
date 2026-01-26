<?php
/**
 * Login widget for support page
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Login_Widget
 */
class Arkidevs_Support_Login_Widget extends WP_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'arkidevs_support_login',
            __( 'Support Login', 'arkidevs-support' ),
            array( 'description' => __( 'Display login form for support system', 'arkidevs-support' ) )
        );
    }

    /**
     * Widget output
     *
     * @param array $args Widget arguments
     * @param array $instance Widget instance
     */
    public function widget( $args, $instance ) {
        if ( is_user_logged_in() ) {
            return; // Don't show login widget if user is already logged in
        }

        echo $args['before_widget'];
        
        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        }

        // Get login URL with redirect to support page
        $login_url = wp_login_url( home_url( '/support/' ) );
        $register_url = wp_registration_url();

        echo '<div class="arkidevs-login-widget">';
        echo '<p>' . esc_html__( 'Please log in to access your support tickets.', 'arkidevs-support' ) . '</p>';
        echo '<p><a href="' . esc_url( $login_url ) . '" class="button button-primary">' . esc_html__( 'Log In', 'arkidevs-support' ) . '</a></p>';
        
        if ( get_option( 'users_can_register' ) ) {
            echo '<p><a href="' . esc_url( $register_url ) . '">' . esc_html__( 'Register for an account', 'arkidevs-support' ) . '</a></p>';
        }
        
        echo '</div>';

        echo $args['after_widget'];
    }

    /**
     * Widget form
     *
     * @param array $instance Widget instance
     */
    public function form( $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Support Login', 'arkidevs-support' );
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'arkidevs-support' ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <?php
    }

    /**
     * Update widget
     *
     * @param array $new_instance New instance
     * @param array $old_instance Old instance
     * @return array
     */
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
        return $instance;
    }
}

