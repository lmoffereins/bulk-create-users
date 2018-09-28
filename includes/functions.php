<?php

/**
 * Bulk Create Users Functions
 *
 * @package Bulk Create Users
 * @subpackage Main
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;


/**
 * Send the custom registration notification email
 *
 * @see wp_new_user_notification()
 *
 * @since 1.2.0
 *
 * @global $wpdb WPDB
 * @global $wp_hasher PasswordHash
 * 
 * @param int $user_id User ID
 */
function bcu_send_new_user_notifications( $user_id ) {
	global $wpdb, $wp_hasher;
	$user = get_userdata( $user_id );

	// Parse email settings
	$email_settings = get_site_option( 'bulk_create_users_custom_email', array() );
	$args = apply_filters( 'bulk_create_users_custom_email', wp_parse_args( $email_settings, array(
		'from'      => '',
		'from_name' => '',
		'subject'   => '',
		'content'   => '',
		'redirect'  => ''
	) ) );

	// Generate something random for a password reset key.
	$key = wp_generate_password( 20, false );

	/** This action is documented in wp-login.php */
	do_action( 'retrieve_password_key', $user->user_login, $key );

	// Now insert the key, hashed, into the DB.
	if ( empty( $wp_hasher ) ) {
		$wp_hasher = new PasswordHash( 8, true );
	}
	$hashed = time() . ':' . $wp_hasher->HashPassword( $key );
	$wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user->user_login ) );

	$message = $args['content'];
	$placeholders = apply_filters( 'bulk_create_users_email_placeholders', array(
		'USERNAME' => $user->user_login,
		'PASSWORD' => sprintf( '<a href="%s">%s</a>', esc_url( site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login') ), __( 'Klik hier om te activeren', 'bulk-create-users' ) ),
		'LOGINURL' => wp_login_url( $args['redirect'] ),
	), $user, $args );

	// Parse content placeholders
	foreach ( $placeholders as $variable => $replacement ) {
		$message = str_replace( "###{$variable}###", $replacement, $message );
	}

	// Define email headers
	$headers = array( 'From: "' . $args['from_name'] . '" <' . $args['from'] . '>' );

	// Hook content type
	add_filter( 'wp_mail_content_type', 'bcu_email_html_content_type' );

	// Send the notification email
	wp_mail( $user->user_email, wp_specialchars_decode( $args['subject'], ENT_QUOTES ), $message, $headers );

	// Unhook content type
	remove_filter( 'wp_mail_content_type', 'bcu_email_html_content_type' );
}

/**
 * Return the HTML email content type
 *
 * @since 1.2.0
 * 
 * @return string HTML email content type
 */
function bcu_email_html_content_type() {
	return 'text/html';
}
