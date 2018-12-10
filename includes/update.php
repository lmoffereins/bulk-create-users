<?php

/**
 * Bulk Create Users Updater
 *
 * @package Bulk Create Users
 * @subpackage Updater
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * If there is no raw DB version, this is the first installation
 *
 * @since 1.2.0
 *
 * @return bool True if update, False if not
 */
function bulk_create_users_is_install() {
	return ! bulk_create_users_get_db_version_raw();
}

/**
 * Compare the plugin version to the DB version to determine if updating
 *
 * @since 1.2.0
 *
 * @return bool True if update, False if not
 */
function bulk_create_users_is_update() {
	$raw    = (int) bulk_create_users_get_db_version_raw();
	$cur    = (int) bulk_create_users_get_db_version();
	$retval = (bool) ( $raw < $cur );
	return $retval;
}

/**
 * Determine if the plugin is being activated
 *
 * Note that this function currently is not used in the plugin's core and is here
 * for third party plugins to use to check for plugin activation.
 *
 * @since 1.2.0
 *
 * @return bool True if activating the plugin, false if not
 */
function bulk_create_users_is_activation( $basename = '' ) {
	global $pagenow;

	$plugin = bulk_create_users();
	$action = false;

	// Bail if not in admin/plugins
	if ( ! ( is_admin() && ( 'plugins.php' === $pagenow ) ) ) {
		return false;
	}

	if ( ! empty( $_REQUEST['action'] ) && ( '-1' !== $_REQUEST['action'] ) ) {
		$action = $_REQUEST['action'];
	} elseif ( ! empty( $_REQUEST['action2'] ) && ( '-1' !== $_REQUEST['action2'] ) ) {
		$action = $_REQUEST['action2'];
	}

	// Bail if not activating
	if ( empty( $action ) || ! in_array( $action, array( 'activate', 'activate-selected' ) ) ) {
		return false;
	}

	// The plugin(s) being activated
	if ( $action === 'activate' ) {
		$plugins = isset( $_GET['plugin'] ) ? array( $_GET['plugin'] ) : array();
	} else {
		$plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();
	}

	// Set basename if empty
	if ( empty( $basename ) && ! empty( $plugin->basename ) ) {
		$basename = $plugin->basename;
	}

	// Bail if no basename
	if ( empty( $basename ) ) {
		return false;
	}

	// Is the plugin being activated?
	return in_array( $basename, $plugins );
}

/**
 * Determine if the plugin is being deactivated
 *
 * @since 1.2.0
 * 
 * @return bool True if deactivating the plugin, false if not
 */
function bulk_create_users_is_deactivation( $basename = '' ) {
	global $pagenow;

	$plugin = bulk_create_users();
	$action = false;

	// Bail if not in admin/plugins
	if ( ! ( is_admin() && ( 'plugins.php' === $pagenow ) ) ) {
		return false;
	}

	if ( ! empty( $_REQUEST['action'] ) && ( '-1' !== $_REQUEST['action'] ) ) {
		$action = $_REQUEST['action'];
	} elseif ( ! empty( $_REQUEST['action2'] ) && ( '-1' !== $_REQUEST['action2'] ) ) {
		$action = $_REQUEST['action2'];
	}

	// Bail if not deactivating
	if ( empty( $action ) || ! in_array( $action, array( 'deactivate', 'deactivate-selected' ) ) ) {
		return false;
	}

	// The plugin(s) being deactivated
	if ( $action === 'deactivate' ) {
		$plugins = isset( $_GET['plugin'] ) ? array( $_GET['plugin'] ) : array();
	} else {
		$plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();
	}

	// Set basename if empty
	if ( empty( $basename ) && ! empty( $plugin->basename ) ) {
		$basename = $plugin->basename;
	}

	// Bail if no basename
	if ( empty( $basename ) ) {
		return false;
	}

	// Is the plugin being deactivated?
	return in_array( $basename, $plugins );
}

/**
 * Update the DB to the latest version
 *
 * @since 1.2.0
 */
function bulk_create_users_version_bump() {
	update_option( 'bulk_create_users_db_version', bulk_create_users_get_db_version() );
}

/**
 * Setup the plugin updater
 *
 * @since 1.2.0
 */
function bulk_create_users_setup_updater() {

	// Bail if no update needed
	if ( ! bulk_create_users_is_update() )
		return;

	// Call the automated updater
	bulk_create_users_version_updater();
}

/**
 * Plugin's version updater looks at what the current database version is, and
 * runs whatever other code is needed.
 *
 * This is most-often used when the data schema changes, but should also be used
 * to correct issues with plugin meta-data silently on software update.
 *
 * @since 1.2.0
 *
 * @todo Log update event
 */
function bulk_create_users_version_updater() {

	// Get the raw database version
	$raw_db_version = (int) bulk_create_users_get_db_version_raw();

	/** 1.2.x Branch ********************************************************/

	// 1.2.0
	if ( $raw_db_version < 20180928 ) {
		// Do stuff
	}

	/** All done! ***********************************************************/

	// Bump the version
	bulk_create_users_version_bump();
}
