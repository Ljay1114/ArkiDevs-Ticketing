<?php
/**
 * Ticket replied email template
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-status.php';
require_once ARKIDEVS_SUPPORT_PLUGIN_DIR . 'includes/tickets/class-priority.php';

$customer = get_userdata( $ticket->customer_id );
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo esc_html( sprintf( __( 'Reply to Ticket #%s', 'arkidevs-support' ), $ticket->ticket_number ) ); ?></title>
</head>
<body>
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <h2><?php echo esc_html( sprintf( __( 'Reply to Ticket #%s', 'arkidevs-support' ), $ticket->ticket_number ) ); ?></h2>
        
        <p><?php esc_html_e( 'Hello,', 'arkidevs-support' ); ?></p>
        
        <p><?php esc_html_e( 'A new reply has been added to your support ticket.', 'arkidevs-support' ); ?></p>
        
        <div style="background: #f5f5f5; padding: 15px; margin: 20px 0;">
            <p><strong><?php esc_html_e( 'Ticket Number:', 'arkidevs-support' ); ?></strong> #<?php echo esc_html( $ticket->ticket_number ); ?></p>
            <p><strong><?php esc_html_e( 'Subject:', 'arkidevs-support' ); ?></strong> <?php echo esc_html( $ticket->subject ); ?></p>
            <p><strong><?php esc_html_e( 'Status:', 'arkidevs-support' ); ?></strong> <?php echo esc_html( Arkidevs_Support_Status::get_status_label( $ticket->status ) ); ?></p>
        </div>
        
        <p>
            <a href="<?php echo esc_url( home_url( '/support/ticket/' . $ticket->id . '/' ) ); ?>" style="background: #0073aa; color: #fff; padding: 10px 20px; text-decoration: none; display: inline-block;">
                <?php esc_html_e( 'View Ticket', 'arkidevs-support' ); ?>
            </a>
        </p>
        
        <p style="color: #666; font-size: 12px; margin-top: 30px;">
            <?php esc_html_e( 'This is an automated email. Please do not reply to this email.', 'arkidevs-support' ); ?>
        </p>
    </div>
</body>
</html>

