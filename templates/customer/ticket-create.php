<?php
/**
 * Customer ticket creation template (can be included or used as standalone)
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="arkidevs-create-ticket">
    <h2><?php esc_html_e( 'Create New Ticket', 'arkidevs-support' ); ?></h2>
    <form id="create-ticket-form">
        <p>
            <label for="ticket-subject"><?php esc_html_e( 'Subject:', 'arkidevs-support' ); ?></label>
            <input type="text" id="ticket-subject" name="subject" required style="width: 100%;" />
        </p>
        <p>
            <label for="ticket-priority"><?php esc_html_e( 'Priority:', 'arkidevs-support' ); ?></label>
            <select id="ticket-priority" name="priority" style="width: 100%;">
                <option value="low"><?php esc_html_e( 'Low', 'arkidevs-support' ); ?></option>
                <option value="medium" selected><?php esc_html_e( 'Medium', 'arkidevs-support' ); ?></option>
                <option value="high"><?php esc_html_e( 'High', 'arkidevs-support' ); ?></option>
                <option value="critical"><?php esc_html_e( 'Critical', 'arkidevs-support' ); ?></option>
            </select>
        </p>
        <p>
            <label for="ticket-description"><?php esc_html_e( 'Description:', 'arkidevs-support' ); ?></label>
            <textarea id="ticket-description" name="description" rows="10" required style="width: 100%;"></textarea>
        </p>
        <p>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Submit Ticket', 'arkidevs-support' ); ?></button>
        </p>
    </form>
</div>


