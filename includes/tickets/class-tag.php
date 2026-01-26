<?php
/**
 * Ticket tag management class
 *
 * @package Arkidevs_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Arkidevs_Support_Tag
 */
class Arkidevs_Support_Tag {

    /**
     * Get table name
     *
     * @return string
     */
    public static function get_table_name() {
        return Arkidevs_Support_DB::get_table_name( 'ticket_tags' );
    }

    /**
     * Get relationship table name
     *
     * @return string
     */
    public static function get_relationship_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'arkidevs_ticket_tag_relationships';
    }

    /**
     * Create a new tag
     *
     * @param array $data Tag data (name, slug, color, description)
     * @return int|WP_Error Tag ID on success, WP_Error on failure
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        if ( empty( $name ) ) {
            return new WP_Error( 'missing_name', __( 'Tag name is required.', 'arkidevs-support' ) );
        }

        // Generate slug if not provided
        $slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $name );
        if ( empty( $slug ) ) {
            $slug = sanitize_title( $name );
        }

        // Check if slug already exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s",
                $slug
            )
        );
        if ( $existing ) {
            return new WP_Error( 'duplicate_slug', __( 'A tag with this slug already exists.', 'arkidevs-support' ) );
        }

        $color = isset( $data['color'] ) ? sanitize_hex_color( $data['color'] ) : '#0073aa';
        $description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';

        $result = $wpdb->insert(
            $table,
            array(
                'name'        => $name,
                'slug'        => $slug,
                'color'       => $color,
                'description' => $description,
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        if ( $result ) {
            return $wpdb->insert_id;
        }

        return new WP_Error( 'create_failed', __( 'Failed to create tag.', 'arkidevs-support' ) );
    }

    /**
     * Get tag by ID
     *
     * @param int $tag_id Tag ID
     * @return object|null
     */
    public static function get_by_id( $tag_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $tag_id
            )
        );
    }

    /**
     * Get tag by slug
     *
     * @param string $slug Tag slug
     * @return object|null
     */
    public static function get_by_slug( $slug ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE slug = %s",
                $slug
            )
        );
    }

    /**
     * Get all tags
     *
     * @return array
     */
    public static function get_all() {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY name ASC"
        );
    }

    /**
     * Update tag
     *
     * @param int   $tag_id Tag ID
     * @param array $data   Data to update
     * @return bool|WP_Error
     */
    public static function update( $tag_id, $data ) {
        global $wpdb;
        $table = self::get_table_name();

        $update_data = array();
        $format = array();

        if ( isset( $data['name'] ) ) {
            $update_data['name'] = sanitize_text_field( $data['name'] );
            $format[] = '%s';
        }

        if ( isset( $data['slug'] ) ) {
            $slug = sanitize_title( $data['slug'] );
            // Check if slug already exists for another tag
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE slug = %s AND id != %d",
                    $slug,
                    $tag_id
                )
            );
            if ( $existing ) {
                return new WP_Error( 'duplicate_slug', __( 'A tag with this slug already exists.', 'arkidevs-support' ) );
            }
            $update_data['slug'] = $slug;
            $format[] = '%s';
        }

        if ( isset( $data['color'] ) ) {
            $update_data['color'] = sanitize_hex_color( $data['color'] );
            $format[] = '%s';
        }

        if ( isset( $data['description'] ) ) {
            $update_data['description'] = sanitize_textarea_field( $data['description'] );
            $format[] = '%s';
        }

        if ( empty( $update_data ) ) {
            return true;
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $tag_id ),
            $format,
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Delete tag
     *
     * @param int $tag_id Tag ID
     * @return bool
     */
    public static function delete( $tag_id ) {
        global $wpdb;
        $table = self::get_table_name();
        $relationship_table = self::get_relationship_table_name();

        // Delete relationships first
        $wpdb->delete(
            $relationship_table,
            array( 'tag_id' => $tag_id ),
            array( '%d' )
        );

        // Delete tag
        return (bool) $wpdb->delete(
            $table,
            array( 'id' => $tag_id ),
            array( '%d' )
        );
    }

    /**
     * Get tags for a ticket
     *
     * @param int $ticket_id Ticket ID
     * @return array
     */
    public static function get_ticket_tags( $ticket_id ) {
        global $wpdb;
        $table = self::get_table_name();
        $relationship_table = self::get_relationship_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.* FROM {$table} t
                INNER JOIN {$relationship_table} tr ON t.id = tr.tag_id
                WHERE tr.ticket_id = %d
                ORDER BY t.name ASC",
                $ticket_id
            )
        );
    }

    /**
     * Add tag to ticket
     *
     * @param int $ticket_id Ticket ID
     * @param int $tag_id    Tag ID
     * @return bool
     */
    public static function add_tag_to_ticket( $ticket_id, $tag_id ) {
        global $wpdb;
        $relationship_table = self::get_relationship_table_name();

        // Check if relationship already exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$relationship_table} WHERE ticket_id = %d AND tag_id = %d",
                $ticket_id,
                $tag_id
            )
        );

        if ( $existing ) {
            return true; // Already exists
        }

        $result = $wpdb->insert(
            $relationship_table,
            array(
                'ticket_id' => $ticket_id,
                'tag_id'    => $tag_id,
            ),
            array( '%d', '%d' )
        );

        return (bool) $result;
    }

    /**
     * Remove tag from ticket
     *
     * @param int $ticket_id Ticket ID
     * @param int $tag_id    Tag ID
     * @return bool
     */
    public static function remove_tag_from_ticket( $ticket_id, $tag_id ) {
        global $wpdb;
        $relationship_table = self::get_relationship_table_name();

        return (bool) $wpdb->delete(
            $relationship_table,
            array(
                'ticket_id' => $ticket_id,
                'tag_id'    => $tag_id,
            ),
            array( '%d', '%d' )
        );
    }

    /**
     * Set tags for a ticket (replaces existing tags)
     *
     * @param int   $ticket_id Ticket ID
     * @param array $tag_ids   Array of tag IDs
     * @return bool
     */
    public static function set_ticket_tags( $ticket_id, $tag_ids ) {
        global $wpdb;
        $relationship_table = self::get_relationship_table_name();

        // Remove existing tags
        $wpdb->delete(
            $relationship_table,
            array( 'ticket_id' => $ticket_id ),
            array( '%d' )
        );

        // Add new tags
        if ( ! empty( $tag_ids ) ) {
            foreach ( $tag_ids as $tag_id ) {
                $tag_id = intval( $tag_id );
                if ( $tag_id > 0 ) {
                    $wpdb->insert(
                        $relationship_table,
                        array(
                            'ticket_id' => $ticket_id,
                            'tag_id'    => $tag_id,
                        ),
                        array( '%d', '%d' )
                    );
                }
            }
        }

        return true;
    }
}
