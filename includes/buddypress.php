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
	 *
	 * @uses Bulk_Create_Users_Buddypress::setup_actions()
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
		add_action( 'bulk_create_users_import_bp-xprofile', array( $this, 'import_xprofile' ), 10, 3 );
		add_action( 'bulk_create_users_import_bp-groups',   array( $this, 'import_groups'   ), 10, 3 );
	}

	/** Public methods **************************************************/

	/**
	 * Add Buddypress destination fields to the data options
	 *
	 * @since 1.0.0
	 *
	 * @uses bp_is_active()
	 * @uses bp_xprofile_get_groups()
	 * @uses groups_get_groups()
	 * 
	 * @param array $fields Data fields
	 * @return array Data fields
	 */
	public function data_fields( $fields ) {

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
	 * Handle logic of saving data to Buddypress XProfile fields
	 *
	 * @since 1.0.0
	 *
	 * @uses xprofile_set_field_data()
	 * 
	 * @param string $field Field destination
	 * @param int $user_id User ID
	 * @param string $value Uploaded field value
	 */
	public function import_xprofile( $field, $user_id, $value ) {
		xprofile_set_field_data( $field, $user_id, $value );
	}

	/**
	 * Handle logic of saving data to Buddypress Groups fields
	 *
	 * @since 1.0.0
	 *
	 * @uses groups_get_groups()
	 * @uses groups_join_group()
	 * @uses groups_get_group()
	 * @uses bool_from_yn()
	 * @uses groups_leave_group()
	 * 
	 * @param string $field Field destination
	 * @param int $user_id User ID
	 * @param string $value Uploaded field value
	 */
	public function import_groups( $field, $user_id, $value ) {

		// Collect group slugs
		if ( '0' === $field && ! empty( $value ) ) {
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
		} elseif ( is_numeric( $field ) && is_a( groups_get_group( array( 'group_id' => (int) $field ) ), 'BP_Groups_Group' ) ) {

			// Join or leave based on boolean field
			if ( ( is_numeric( $value ) && (bool) $value ) || bool_from_yn( $value ) ) {
				groups_join_group( (int) $field, $user_id );
			} else {
				groups_leave_group( (int) $field, $user_id );
			}
		}
	}
}

/**
 * Setup Buddypress extension class on 'bp_loaded' action
 *
 * @since 1.0.0
 * 
 * @return bulk_create_users()
 * @return Bulk_Create_Users_Buddypress
 */
function bulk_create_users_buddypress() {
	bulk_create_users()->extend->buddypress = new Bulk_Create_Users_Buddypress;
}
add_action( 'bp_loaded', 'bulk_create_users_buddypress' );

endif; // class_exists
