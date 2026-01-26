<?php
/**
 * Ticket attachments handler
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Attachments
 */
class Arkidevs_Support_Attachments {

    /**
     * Upload directory path
     *
     * @return string
     */
    public static function get_upload_dir() {
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/arkidevs-support';

        if ( ! file_exists( $plugin_dir ) ) {
            wp_mkdir_p( $plugin_dir );
        }

        return $plugin_dir;
    }

    /**
     * Upload directory URL
     *
     * @return string
     */
    public static function get_upload_url() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/arkidevs-support';
    }

    /**
     * Save attachment
     *
     * @param array $file File array from $_FILES
     * @param int   $ticket_id Ticket ID
     * @param int   $reply_id Reply ID (optional)
     * @return int|WP_Error Attachment ID on success, WP_Error on failure
     */
    public static function save_attachment( $file, $ticket_id, $reply_id = null ) {
        global $wpdb;

        // Validate file
        $validated = Arkidevs_Support_Security::sanitize_file_upload( $file );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        // Generate unique filename
        $file_ext = pathinfo( $file['name'], PATHINFO_EXTENSION );
        $file_name = sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) );
        $unique_name = $file_name . '_' . time() . '_' . wp_generate_password( 8, false ) . '.' . $file_ext;

        $upload_dir = self::get_upload_dir();
        $file_path = $upload_dir . '/' . $unique_name;

        // Move uploaded file
        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            return new WP_Error( 'upload_failed', __( 'Failed to upload file.', 'arkidevs-support' ) );
        }

        // Insert into database
        $table = Arkidevs_Support_DB::get_table_name( 'ticket_attachments' );
        $result = $wpdb->insert(
            $table,
            array(
                'ticket_id'   => $ticket_id,
                'reply_id'    => $reply_id,
                'file_name'   => $file['name'],
                'file_path'   => $unique_name,
                'file_type'   => $file['type'],
                'file_size'   => $file['size'],
                'uploaded_by' => get_current_user_id(),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%d', '%d' )
        );

        if ( $result ) {
            return $wpdb->insert_id;
        }

        return new WP_Error( 'db_error', __( 'Failed to save attachment to database.', 'arkidevs-support' ) );
    }

    /**
     * Get attachments for ticket
     *
     * @param int $ticket_id Ticket ID
     * @param int $reply_id Reply ID (optional)
     * @return array
     */
    public static function get_attachments( $ticket_id, $reply_id = null ) {
        global $wpdb;
        $table = Arkidevs_Support_DB::get_table_name( 'ticket_attachments' );

        if ( $reply_id ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE ticket_id = %d AND reply_id = %d ORDER BY created_at ASC",
                    $ticket_id,
                    $reply_id
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ticket_id = %d AND reply_id IS NULL ORDER BY created_at ASC",
                $ticket_id
            )
        );
    }

    /**
     * Get attachment URL
     *
     * @param object $attachment Attachment object
     * @return string
     */
    public static function get_attachment_url( $attachment ) {
        return self::get_upload_url() . '/' . $attachment->file_path;
    }

    /**
     * Delete attachment
     *
     * @param int $attachment_id Attachment ID
     * @return bool
     */
    public static function delete_attachment( $attachment_id ) {
        global $wpdb;
        $table = Arkidevs_Support_DB::get_table_name( 'ticket_attachments' );

        $attachment = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $attachment_id )
        );

        if ( ! $attachment ) {
            return false;
        }

        // Delete file
        $file_path = self::get_upload_dir() . '/' . $attachment->file_path;
        if ( file_exists( $file_path ) ) {
            unlink( $file_path );
        }

        // Delete from database
        return (bool) $wpdb->delete(
            $table,
            array( 'id' => $attachment_id ),
            array( '%d' )
        );
    }
}


