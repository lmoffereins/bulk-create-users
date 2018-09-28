<?php

/**
 * Bulk Create Users Buddypress Functions
 * 
 * @package Bulk Create Users
 * @subpackage Buddypress
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Bulk_Create_Users_Buddypress' ) ) :
/**
 * Bulk Create Users Buddypress Class
 *
 * @since 1.0.0
 */
final class Bulk_Create_Users_Buddypress {

	/**
	 * Class constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/** Private methods *************************************************/

	/**
	 * Setup default actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		// Add selectable fields
		add_filter( 'bulk_create_users_data_fields', array( $this, 'data_fields' ) );

		// Saving fields
		add_action( 'bulk_create_users_import_bp-member-types', array( $this, 'import_member_types' ), 10, 3 );
		add_action( 'bulk_create_users_import_bp-xprofile',     array( $this, 'import_xprofile'     ), 10, 3 );
		add_action( 'bulk_create_users_import_bp-groups',       array( $this, 'import_groups'       ), 10, 3 );
	}

	/** Public methods **************************************************/

	/**
	 * Add Buddypress destination fields to the data options
	 *
	 * @since 1.0.0
	 * 
	 * @param array $fields Data fields
	 * @return array Data fields
	 */
	public function data_fields( $fields ) {

		// Member Types (BP 2.2+)
		if ( function_exists( 'bp_get_member_types' ) ) {

			// Get all member types
			$types = bp_get_member_types( array(), 'objects' );

			// Setup options array
			$options = array();
			foreach ( $types as $member_type ) {
				$options[ $member_type->name ] = $member_type->labels['singular_name'];
			}

			// Append Member Types options
			$fields['bp-member-types'] = array(
				'label'   => __( 'Buddpress Member Types', 'bulk-create-users' ),
				'options' => $options,
			);
		}

		// XProfile Component
		if ( bp_is_active( 'xprofile' ) ) {

			// Get all field groups with their fields
			$xprofile = bp_xprofile_get_groups( array( 'fetch_fields' => true, 'hide_empty_groups' => true ) );

			// Setup options array
			$options  = array();
			foreach ( $xprofile as $field_group ) {
				foreach ( $field_group->fields as $field ) {
					$options[ $field->id ] = "{$field->name} ({$field_group->name})";
				}
			}

			// Append XProfile options
			$fields['bp-xprofile'] = array(
				'label'   => __( 'Buddpress XProfile', 'bulk-create-users' ),
				'options' => $options,
			);
		}

		// Groups Component
		if ( bp_is_active( 'groups' ) ) {

			// Get all groups
			$groups  = groups_get_groups( array( 'show_hidden' => true, 'type' => 'alphabetical', 'populate_extras' => false ) );

			// Setup options array
			$options = array( 0 => __( 'Group Slugs', 'bulk-create-users' ) );
			foreach ( $groups['groups'] as $group ) {
				$options[ $group->id ] = $group->name;
			}

			// Append Groups options
			$fields['bp-groups'] = array(
				'label'   => __( 'Buddypress Goups', 'bulk-create-users' ),
				'options' => $options,
			);
		}

		return $fields;
	}

	/**
	 * Handle logic of registering users to Buddypress Member Types
	 *
	 * @since 1.1.0
	 *
	 * @uses bp_get_member_type_object()
	 * @uses bool_from_yn()
	 * @uses bp_set_member_type()
	 * @uses bp_get_member_type()
	 * @uses bp_set_object_terms()
	 *
	 * @param string $member_type Selected Member Type name
	 * @param int $user_id User ID
	 * @param string $value Uploaded field value
	 */
	public function import_member_types( $member_type, $user_id, $value ) {

		// Member Type exists
		if ( bp_get_member_type_object( $member_type ) ) {

			// Add or remove based on boolean value
			if ( ( is_numeric( $value ) && (bool) $value ) || bool_from_yn( $value ) ) {
				bp_set_member_type( $user_id, $member_type, true );
			} else {
				$retval = bp_set_object_terms( $user_id, array_diff( (array) bp_get_member_type( $user_id, false ), array( $member_type ) ), 'bp_member_type' );

				// Bust the cache if the type has been updated.
				if ( ! is_wp_error( $retval ) ) {
					wp_cache_delete( $user_id, 'bp_member_type' );
				}
			}
		}
	}

	/**
	 * Handle logic of saving data to Buddypress XProfile fields
	 *
	 * @since 1.0.0
	 * 
	 * @param string $field_id Selected XProfile field ID
	 * @param int $user_id User ID
	 * @param string $value Uploaded field value
	 */
	public function import_xprofile( $field_id, $user_id, $value ) {
		xprofile_set_field_data( $field_id, $user_id, $value );
	}

	/**
	 * Handle logic of registering users to Buddypress Groups fields
	 *
	 * @since 1.0.0
	 * 
	 * @param string $group_id Selected group ID
	 * @param int $user_id User ID
	 * @param string $value Uploaded field value
	 */
	public function import_groups( $group_id, $user_id, $value ) {

		// Collect group slugs
		if ( '0' === $group_id && ! empty( $value ) ) {
			$slugs = array_map( 'trim', array_map( 'sanitize_key', explode( ',', $value ) ) );

			// Walk all groups
			$groups = groups_get_groups( array( 'show_hidden' => true, 'populate_extras' => false, 'per_page' => false ) );
			foreach ( $groups['groups'] as $group ) {

				// Join group when the found in slug collection
				if ( in_array( $group->slug, $slugs ) ) {
					groups_join_group( $group->id, $user_id );
				}
			}

		// Single group exists
		} elseif ( is_numeric( $group_id ) && groups_get_group( array( 'group_id' => (int) $group_id ) ) instanceof BP_Groups_Group ) {

			// Join or leave based on boolean value
			if ( ( is_numeric( $value ) && (bool) $value ) || bool_from_yn( $value ) ) {
				groups_join_group( (int) $group_id, $user_id );
			} else {
				groups_leave_group( (int) $group_id, $user_id );
			}
		}
	}
}

/**
 * Initiate the Buddypress extension
 *
 * @since 1.0.0
 * 
 * @uses Bulk_Create_Users_Buddypress
 */
function bulk_create_users_buddypress() {
	bulk_create_users()->extend->buddypress = new Bulk_Create_Users_Buddypress;
}
add_action( 'bp_loaded', 'bulk_create_users_buddypress' );

endif; // class_exists
