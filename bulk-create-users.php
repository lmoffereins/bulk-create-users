<?php

/**
 * The Bulk Create Users Plugin
 * 
 * @package Bulk Create Users
 * @subpackage Main
 *
 * @todo Add custom email vars hint
 */

/**
 * Plugin Name:       Bulk Create Users
 * Description:       Create, import or update multiple users at once
 * Plugin URI:        https://github.com/lmoffereins/bulk-create-users/
 * Version:           1.3.0
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins/
 * Text Domain:       bulk-create-users
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/bulk-create-users
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Bulk_Create_Users' ) ) :
/**
 * The main plugin class
 *
 * @since 1.0.0
 */
final class Bulk_Create_Users {

	/**
	 * Setup and return the singleton pattern
	 *
	 * @since 1.0.0
	 *
	 * @return The single Bulk_Create_Users
	 */
	public static function instance() {

		// Store instance locally
		static $instance = null;

		if ( null === $instance ) {
			$instance = new Bulk_Create_Users;
			$instance->setup_globals();
			$instance->includes();
			$instance->setup_actions();
		}

		return $instance;
	}

	/**
	 * Prevent the plugin class from being loaded more than once
	 */
	private function __construct() { /* Nothing to do */ }

	/** Private methods *************************************************/

	/**
	 * Setup default class globals
	 *
	 * @since 1.0.0
	 */
	private function setup_globals() {

		/** Versions **********************************************************/

		$this->version      = '1.3.0';
		$this->db_version   = 20180928;

		/** Paths *************************************************************/

		// Setup some base path and URL information
		$this->file         = __FILE__;
		$this->basename     = plugin_basename( $this->file );
		$this->plugin_dir   = plugin_dir_path( $this->file );
		$this->plugin_url   = plugin_dir_url ( $this->file );

		// Includes
		$this->includes_dir = trailingslashit( $this->plugin_dir . 'includes'  );
		$this->includes_url = trailingslashit( $this->plugin_url . 'includes'  );

		// Extensions
		$this->extend_dir   = trailingslashit( $this->includes_dir . 'extend' );
		$this->extend_url   = trailingslashit( $this->includes_url . 'extend' );

		// Languages
		$this->lang_dir     = trailingslashit( $this->plugin_dir . 'languages' );

		/** Misc **************************************************************/

		$this->extend       = new stdClass();
		$this->domain       = 'bulk-create-users';
	}

	/**
	 * Include required files
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		require ( $this->includes_dir . 'functions.php' );
		require ( $this->includes_dir . 'update.php'    );

		/** Extensions ********************************************************/

		require ( $this->extend_dir . 'buddypress.php' );
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		// Load textdomain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 20 );

		// Admin
		add_action( 'admin_menu',         array( $this, 'admin_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );

		// Update
		add_action( 'admin_init', 'bulk_create_users_setup_updater', 999 );

		// Ajax
		add_action( 'wp_ajax_bulk_create_users_import', array( $this, 'load_admin_page' ) );
	}

	/** Public methods **************************************************/

	/**
	 * Load the translation file for current language. Checks the languages
	 * folder inside the plugin first, and then the default WordPress
	 * languages folder.
	 *
	 * Note that custom translation files inside the plugin folder will be
	 * removed on plugin updates. If you're creating custom translation
	 * files, please use the global language folder.
	 *
	 * @since 1.2.0
	 *
	 * @uses apply_filters() Calls 'plugin_locale' with {@link get_locale()} value
	 */
	public function load_textdomain() {

		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/bulk-create-users/' . $mofile;

		// Look in global /wp-content/languages/bulk-create-users folder
		load_textdomain( $this->domain, $mofile_global );

		// Look in local /wp-content/plugins/bulk-create-users/languages/ folder
		load_textdomain( $this->domain, $mofile_local );

		// Look in global /wp-content/languages/plugins/
		load_plugin_textdomain( $this->domain );
	}

	/**
	 * Import sessions are tied to the current user, so session options
	 * are stored as user meta.
	 */

	/**
	 * Shortcut for returning the given plugin option
	 *
	 * @since 1.0.0
	 * 
	 * @param string $option Option name
	 * @param bool $delete Whether to delete the option immediately after getting it
	 * @return mixed Option
	 */
	public function get_option( $option, $delete = false ) {
		$key = sanitize_key( $option );
		$value = isset( $_SESSION[ $key ] ) ? $_SESSION[ $key ] : false;
		if ( $delete ) {
			$this->delete_option( $key );
		}
		return $value;
	}

	/**
	 * Shortcut for updating the given plugin option
	 *
	 * @since 1.0.0
	 * 
	 * @param string $option Option name
	 * @param mixed $value New value
	 * @param bool $merge Whether to merge the given value with the current value
	 * @return bool Update result
	 */
	public function update_option( $option, $value, $merge = false ) {
		$key = sanitize_key( $option );
		if ( $merge && isset( $_SESSION[ $key ] ) && is_array( $_SESSION[ $key ] ) ) {
			$value = array_merge_recursive( $_SESSION[ $key ], $value );
		}

		$_SESSION[ $key ] = $value;
		return true;
	}

	/**
	 * Shortcut for deleting the given plugin option
	 *
	 * @since 1.0.0
	 * 
	 * @param string $option Option name
	 * @return bool Deletion result
	 */
	public function delete_option( $option ) {
		unset( $_SESSION[ sanitize_key( $option ) ] );
		return true;
	}

	/** Administration **************************************************/

	/**
	 * Create the plugin's admin menu item
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Removed network-only restriction
	 */
	public function admin_menu() {

		// Create menu page
		$hook = add_submenu_page( 'users.php', esc_html__( 'Bulk Create Users', 'bulk-create-users' ), esc_html__( 'Bulk Create', 'bulk-create-users' ), 'create_users', 'bulk-create-users', array( $this, 'admin_page' ) );

		// Add load hook
		add_action( "load-$hook", array( $this, 'load_admin_page' ) );
	}

	/**
	 * Run logic before on loading the plugin's admin page
	 *
	 * @since 1.0.0
	 */
	public function load_admin_page() {

		// Bail when user is not capable
		if ( ! current_user_can( 'create_users' ) )
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' , 'bulk-create-users' ) );

		// Define local variable(s)
		$step    = isset( $_REQUEST['step'] ) ? $_REQUEST['step'] : 0;
		$referer = isset( $_REQUEST['_wp_http_referer'] ) ? $_REQUEST['_wp_http_referer'] : add_query_arg( 'page', 'bulk-create-users', self_admin_url( 'users.php' ) );
		$errors  = new WP_Error();
		$csv     = null;

		// Start PHP session
		session_start();

		// Check which submit button is used
		// Cast $step to string to handle loose comparison
		if ( ! empty( $step ) ) : switch ( (string) $step ) :

		/**
		 * Check for file errors before parsing. If so, rewind.
		 */
		case 'read-uploaded-file' :

			// Bail when not accessed properly
			if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-create-users-read' ) ) {
				wp_redirect( $referer );
				exit;
			}

			// File was not submitted or found
			if ( ! isset( $_POST['file-upload'] ) || empty( $_FILES ) || ! isset( $_FILES['file']['tmp_name'] )
				|| ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
				$errors->add( 'no_file_found', '' );

			// No errors found. Read file
			} else {

				// Read the file
				$filename = $_FILES['file']['tmp_name'];
				$file     = fopen( $filename, 'r' );

				// Could not read the file 
				if ( false === $file ) {
					$errors->add( 'unreadable_file', '' );
				} else {

					// Use the ReadCSV class
					require_once( $this->includes_dir . 'classes/class-readcsv.php' );

					// Define csv details
					$rows = $columns = array();
					$first = true;
					$sep = isset( $_REQUEST['sep'] ) ? $_REQUEST['sep'] : ',';

					// Open the file
					$csv = new ReadCSV( $file, $sep, "\xEF\xBB\xBF" ); // Skip any UTF-8 byte order mark
					while ( null !== ( $row = $csv->get_row() ) ) {

						// Read first row
						if ( $first ) {
							$first = false;
							$columns = $row;

							// Account for single column files
							if ( count( $columns ) == 1 ) {

								// No email addresses provided
								if ( ! is_email( $columns[0] ) ) {
									$errors->add( 'invalid_single_column', '' );
									break;
								} else {
									$rows[] = $columns;
								}
							}

						// Collect all other rows
						} else {
							$rows[] = $row;
						}
					}

					// Close the file
					fclose( $file );

					// Temporarily store the file data for the current user
					$this->update_option( '_bulk_create_users_uploaded_file_data', compact( 'columns', 'rows' ) );
				}
			}

			break; // read-uploaded-file

		/**
		 * Import the uploaded user data
		 */
		case 'import-uploaded-data-ajax' :

			// Bail when not accessed properly
			check_ajax_referer( 'bulk-create-users-import' );

			if ( ! isset( $_SESSION['bulk_create_users_import_start'] ) ) {
				$_SESSION['bulk_create_users_import_start'] = 0;
			}

			$ajax_start = $_SESSION['bulk_create_users_import_start'];
		
		case 'import-uploaded-data' :

			// Bail when import process was just executed
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX && ! $ajax_start && $this->get_option( '_bulk_create_users_imported_users' ) )
				return;

			// Bail when not accessed properly
			if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-create-users-import' ) ) {
				wp_redirect( $referer );
				exit;
			}

			// Get uploaded file data
			$file_data = $this->get_option( '_bulk_create_users_uploaded_file_data' );

			// Fetch import settings
			$first_row = ! empty( $_REQUEST['first-row'] );
			$sites     = is_multisite() && ! empty( $_REQUEST['register-sites'] ) ? $_REQUEST['register-sites'] : array();
			$overwrite = ! empty( $_REQUEST['update-existing'] );

			/**
			 * The following logic is placed within a one-run do-while loop so 
			 * we can break out of it more easily instead of using complex 
			 * nested if-else statements.
			 */
			do { 

				// Check column and row data
				if ( empty( $file_data ) || ! is_array( $file_data ) || ! isset( $file_data['columns'] ) || ! isset( $file_data['rows'] ) ) {
					$errors->add( 'invalid_data', '' );
					break;
				}

				// Define columns and rows
				$file_columns = $file_data['columns'];
				$file_rows    = $file_data['rows'];

				// Define whether we're processing a single column file
				$is_single = ( 1 == count( $file_columns ) );

				// Consider the first data row
				if ( $first_row ) {

					// Exclude for single column data
					if ( $is_single ) {
						array_shift( $file_rows );

					// Include for multi-column data
					} else {
						array_unshift( $file_rows, $file_columns );
					}
				}

				// Setup multi-column data map
				if ( ! $is_single ) {

					// Report error when data map is missing
					if ( ! isset( $_REQUEST['map_to'] ) || empty( $_REQUEST['map_to'] ) ) {
						$errors->add( 'invalid_mapping', '' );
						break;
					}

					// Get the provided data map
					$_data_map = $_REQUEST['map_to'];
					$sep_map = isset( $_REQUEST['separator'] ) ? $_REQUEST['separator'] : array();

					// Report error when missing the required email field to identify the user with
					if ( ! in_array( 'users.user_email', $_data_map ) ) {
						$errors->add( 'missing_email_field', '' );
						break;
					}

					// Setup data map
					$data_map  = array();
					foreach ( $_data_map as $col => $contextfield ) {

						// Skip when this col's field is not defined
						if ( empty( $contextfield ) )
							continue;

						// This field's data map is an object...
						$data_map[ $col ] = new stdClass;

						// ... with the context, before the '.'
						$data_map[ $col ]->context = strtok( $contextfield, '.' );

						// ... and the field name, after the '.'
						$data_map[ $col ]->field   = substr( $contextfield, strpos( $contextfield, '.' ) + 1 );
					}
				}

				// Setup collection of created and updated users
				$created_users = array();
				$updated_users = array();

				// Define AJAX process
				if ( defined('DOING_AJAX') && DOING_AJAX ) {
					$col_count = count( $data_map );
					$row_count = count( $file_rows );
					$ajax_len  = null;
					$per_run   = 50;

					// Process max num items per run
					if ( ( $col_count * $row_count > $per_run ) || ( empty( $col_count ) && $row_count > $per_run ) ) {
						$ajax_len = floor( $per_run / $col_count );
					}

					// Slice rows for next run
					$file_rows = array_slice( $file_rows, $ajax_start, $ajax_len );
				}

				// Walk soon-to-be users
				foreach ( $file_rows as $i => $user_row ) {

					// Define local variable(s)
					$user_id = false;
					$login   = false;

					// Key: get the email field value
					$email = $is_single ? $user_row[0] : $user_row[ array_search( 'users.user_email', $_data_map ) ];

					// Trim the fat
					$email = trim( $email );

					// Skip invalid emails
					if ( ! is_email( $email ) ) {
						$created_users[ $i ] = new WP_Error( 'invalid_email', __( '<strong>ERROR</strong>: The email address isn&#8217;t correct.' ) );
						continue;
					}

					// User (email) does not exist
					if ( ! $user_id = email_exists( $email ) ) {

						// Multi-column
						if ( ! $is_single ) {

							// Find fields we can create a username with
							$login_col = array_search( 'users.user_login',     $_data_map );
							$fname_col = array_search( 'users.user_firstname', $_data_map );
							$lname_col = array_search( 'users.user_lastname',  $_data_map );

							// Define login based on login field ... 
							if ( false !== $login_col ) {
								$login = sanitize_user( $user_row[ $login_col ], true );
							}

							// ... or firstname + lastname field ...
							if ( ( empty( $login ) || username_exists( $login ) ) && false !== $fname_col && false !== $lname_col ) {
								$login = sanitize_user( $user_row[ $fname_col ] . '_' . $user_row[ $lname_col ], true );
							}

							// ... or lastname field ...
							if ( ( empty( $login ) || username_exists( $login ) ) && false !== $lname_col ) {
								$login = sanitize_user( $user_row[ $lname_col ], true );
							}

							// ... or firstname field
							if ( ( empty( $login ) || username_exists( $login ) ) && false !== $fname_col ) {
								$login = sanitize_user( $user_row[ $fname_col ], true );
							}
						}

						// Any column: default to email field
						if ( empty( $login ) || username_exists( $login ) ) {
							$login = $this->generate_login_from_email( $email );
						}

						// Could not create a valid username
						if ( empty( $login ) ) {
							$created_users[ $i ] = new WP_Error( 'invalid_username', __( '<strong>ERROR</strong>: Could not gerenate a valid user name.', 'bulk-create-users' ) );
							continue;

						// Create new user
						} else {
							$created_users[ $i ] = $user_id = $this->register_new_user( $login, $email );

							// For single column files it ends here
							if ( $is_single )
								continue;
						}

					// User already exists and not overwriting: next!
					} elseif ( $user_id && ! $overwrite ) {
						continue;

					// Updating this user
					} else {
						$updated_users[] = $user_id;
					}

					// Setup base user data without email. We used the email field already.
					$data_map_without_email = $data_map;
					unset( $data_map_without_email[ array_search( 'users.user_email', $_data_map ) ] );

					// Update base user data
					if ( ( $users_data = wp_list_filter( $data_map_without_email, array( 'context' => 'users' ) ) ) && ! empty( $users_data ) ) {

						// Define update args variable
						$update_args = array( 'ID' => $user_id );

						// Collect data for users fields
						foreach ( $users_data as $col => $field ) {
							$update_args[ $field->field ] = $user_row[ $col ];
						}

						// Update the user data
						wp_update_user( $update_args );
					}

					// Do user registration for sites
					$this->register_user_for_sites( $user_id );

					// Walk addittional mapped fields
					foreach ( array_keys( $data_map ) as $col ) {

						// Define this row & column's specific params
						$field = $data_map[ $col ];
						$input = trim( $user_row[ $col ] );
						$separator = trim( $sep_map[ $col ] );

						// Split data on separator to create an array
						if ( ! empty( $separator ) ) {
							$input = explode( $separator, $input );
						}

						// Check the field's context
						switch ( $field->context ) {

							// Skip users context
							case 'users' :
								break;

							// Update user meta
							case 'usermeta' :

								// Use column title as meta key or use the map's field
								$meta_key = ( 'usermeta' == $field->field ) ? $file_columns[ $col ] : $field->field;

								// Process array data: remove previous values, add new ones
								if ( is_array( $input ) ) {
									delete_user_meta( $user_id, $meta_key );
									foreach ( $input as $value ) {
										add_user_meta( $user_id, $meta_key, $value );
									}

								// Process single data: overwrite existing value
								} else {
									update_user_meta( $user_id, $meta_key, $input );
								}
								break;

							// Provide hook for custom field saving
							default :
								/**
								 * Act on importing a specific field of a specific context for the imported user.
								 * 
								 * The dynamic portion of the hook name, `$field->context`, refers to the
								 * context name of the current processed field. This is the context under
								 * which the field is registered in bulk_create_users_data_fields().
								 *
								 * @since 1.0.0
								 *
								 * @param string       $field     Field name to process.
								 * @param int          $user_id   User ID of the imported user.
								 * @param string|array $input     Provided input value.
								 * @param string       $separator Separator token.
								 */
								do_action( "bulk_create_users_import_{$field->context}", $field->field, $user_id, $input );
								break;
						}
					}
				}

				// Temporarily store the created users collection
				if ( ! empty( $created_users ) || ! empty( $updated_users ) ) {
					$this->update_option( '_bulk_create_users_imported_users', compact( 'created_users', 'updated_users' ), defined( 'DOING_AJAX' ) && DOING_AJAX );
				} else {
					$errors->add( 'nothing_changed', '' );
				}

			} while ( 0 );

			// Return AJAX response
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				if ( $errors->get_error_code() ) {
					wp_send_json_error( $errors );
				} else {
					$process = compact( 'created_users', 'updated_users' );

					// More rows to process
					if ( $row_count > $ajax_start + count( $file_rows ) ) {
						$_SESSION[ 'bulk_create_users_import_start' ] += $ajax_len;
						$process['done'] = false;
					} else {
						$_SESSION[ 'bulk_create_users_import_start' ] = 0;
						$process['done'] = true;
					}

					wp_send_json_success( $process );
				}
			}

			break; // import-uploaded-data

		/**
		 * Delete created users
		 */
		case 'remove-created-users' :

			// Bail when not accessed properly
			if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-create-users-remove' ) || empty( $_REQUEST['user_ids'] ) ) {
				wp_redirect( $referer );
				exit;
			}

			// Remove all created users
			foreach ( wp_parse_id_list( $_REQUEST['user_ids'] ) as $user_id ) {
				is_multisite() ? wpmu_delete_user( $user_id ) : wp_delete_user( $user_id );
			}

			// Register feedback message
			$this->update_option( '_bulk_create_users_import_error', new WP_Error( 'removed_users', '', array( 'type' => 'success' ) ) );

			// Redirect
			wp_redirect( $referer );
			exit;

			break; // remove-created-users

		case 'after-ajax-import' :
			if ( ! $this->get_option( '_bulk_create_users_imported_users' ) ) {
				wp_redirect( $referer );
			}

		endswitch; endif; // step

		// Handle registered errors
		if ( $errors->get_error_code() ) {

			// Rewind the import step
			if ( ! empty( $step ) ) {
				$steps = array( '0', 'read-uploaded-file', 'import-uploaded-data' );
				$pos   = array_search( $step, $steps );
				$_REQUEST['step'] = $steps[ ! empty( $pos ) ? $pos - 1 : 0 ];
			}

			// Store error
			$this->update_option( '_bulk_create_users_import_error', $errors );
		}

		// Remove previous upload attempts and results when restarting
		if ( empty( $step ) ) {
			session_unset();
		}
	}

	/**
	 * Output the plugin's admin page
	 *
	 * @since 1.0.0
	 */
	public function admin_page() {

		// Define local variable(s)
		$step = isset( $_REQUEST['step'] ) ? $_REQUEST['step'] : 0;

		// Define file data variable(s)
		$file_data = $this->get_option( '_bulk_create_users_uploaded_file_data' );
		if ( $file_data ) {
			$row_count    = count( $file_data['rows']    );
			$column_count = count( $file_data['columns'] );

			// Define whether we're processing a single column file
			$is_single = 1 == $column_count;
		} else {
			$step = 0;
		} ?>

		<div class="wrap">
			<h2><?php esc_html_e( 'Bulk Create Users', 'bulk-create-users' );

				// Restart button
				if ( $step ) {
					echo ' <a class="add-new-h2" href="' . add_query_arg( 'page', 'bulk-create-users', self_admin_url( 'users.php' ) ) . '">' . esc_html__( 'Restart', 'bulk-create-users' ) . '</a>';
				}
			?></h2>

			<?php

			// Display the recentest feedback message
			$this->display_feedback_message();

			// Display the step's section
			// Cast $step to string to handle loose comparison
			switch ( (string) $step ) :

				/**
				 * Report after creating the users from the file
				 */
				case 'import-uploaded-data' :
				case 'after-ajax-import' :

					// Collect the import data
					$imported      = (array) $this->get_option( '_bulk_create_users_imported_users', true );
					$created_users = $created_errors = array();
					$updated_users = $imported['updated_users'];

					// Collect results types
					foreach ( (array) $imported['created_users'] as $i => $user ) {
						if ( ! is_wp_error( $user ) ) {
							$created_users[ $i ] = $user;
						} else {
							$created_errors[ $i ] = $user;
						}
					} ?>

				<h3><?php esc_html_e( 'Import Results', 'bulk-create-users' ); ?></h3>

					<?php // Display created and updated messages ?>
					<?php foreach ( array( 'created_users', 'updated_users' ) as $users ) :

						// Only show message when there is good news
						if ( ! empty( $$users ) ) {
							$user_links = array();
							foreach ( $$users as $user_id ) {
								$user_links[] = '<a href="' . add_query_arg( 'user_id', $user_id, self_admin_url( 'user-edit.php' ) ) . '">' . get_userdata( $user_id )->display_name . '</a>';
							}

							echo $this->get_feedback_message( $users, 'success', count( $$users ), implode( ', ', $user_links ) );
						}
					endforeach;

					// Display import errors
					if ( ! empty( $created_errors ) ) : ?>

				<style>.widefat .notice { -webkit-box-shadow: none; box-shadow: none; }</style>
				<table class="widefat striped fixed">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Row number',       'bulk-create-users' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Feedback message', 'bulk-create-users' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $created_errors as $i => $error ) : ?>
						<tr>
							<td><?php echo number_format_i18n( $i + 1 ); ?></td>
							<td><span class="notice notice-error"><?php echo $error->get_error_message(); ?></span></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

					<?php endif;

					// Provide remove button
					if ( ! empty( $created_users ) ) : ?>

				<form id="remove-users" method="post" action="">
					<input type="hidden" name="user_ids" value="<?php echo implode( ',', $created_users ); ?>"/>
					<input type="hidden" name="step" value="remove-created-users" />
					<?php wp_nonce_field( 'bulk-create-users-remove' ); ?>

					<p class="submit">
						<a class="button button-primary" href="<?php echo add_query_arg( array( 'orderby' => 'id', 'order' => 'desc' ), self_admin_url( 'users.php' ) ); ?>"><?php esc_html_e( 'View Created Users', 'bulk-create-users' ); ?></a>
						<?php submit_button( esc_html__( 'Remove Created Users', 'bulk-create-users' ), 'delete', 'remove-created', false ); ?>
						<span class="spinner"></span>
					</p>
				</form>
					
					<?php endif;

					break;

				/**
				 * Handle uploaded file and further import details
				 */
				case 'read-uploaded-file' :

					// Get uploaded file data
					$file_data = $this->get_option( '_bulk_create_users_uploaded_file_data' );

					// Render page when file data is available
					if ( ! empty( $file_data ) ) : ?>

				<div class="notice notice-info">
					<p><?php printf( ( ! $is_single ? esc_html__( 'We found %1$d columns and %2$d rows in your file.', 'bulk-create-users' ) : esc_html__( 'We found 1 column and %2$d rows in your file', 'bulk-create-users' ) ),
						$column_count, $row_count + 1
					); ?></p>
				</div>

				<h3><?php esc_html_e( 'Import Settings', 'bulk-create-users' ); ?></h3>

				<p><?php _e( 'At least an <strong>email address</strong> is required to be able to setup your (new) users.', 'bulk-create-users' ); ?></p>

				<form id="import-settings" method="post" action="">

					<?php // Map columns for multi-column files ?>
					<?php if ( ! $is_single ) : $field_options = $this->field_options(); ?>
					<table class="widefat striped fixed">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'File Column', 'bulk-create-users' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Data Field',  'bulk-create-users' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $file_data['columns'] as $column => $name ) : ?>
							<tr>
								<th class="file-column" scope="row"><?php echo $name; ?></th>
								<td class="data-field">
									<div class="data-field-wrapper">
										<select name="map_to[<?php echo esc_attr( strtolower( $column ) ); ?>]"><?php echo $field_options; ?></select>
										<input name="separator[<?php echo esc_attr( strtolower( $column ) ); ?>]" value="" placeholder="<?php esc_html_e( 'Separator', 'bulk-create-users' ); ?>" class="small-text">
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>

					<table class="form-table">

						<tr id="setting-first-row">
							<th scope="row"><?php esc_html_e( 'First Row', 'bulk-create-users' ); ?></th>
							<td>
								<input type="checkbox" name="first-row" value="1" id="first-row" />
								<label for="first-row"><?php 
									if ( $is_single ) :
										esc_html_e( 'Exclude the first row from the import', 'bulk-create-users' );
									else :
										esc_html_e( 'Include the first row in the import',   'bulk-create-users' );
									endif;
								?></label>
								<p class="description"><?php 
									if ( $is_single ) :
										esc_html_e( 'By default, the first column will be included in the import.',   'bulk-create-users' );
									else :
										esc_html_e( 'By default, the first column will be excluded from the import.', 'bulk-create-users' );
									endif;
								?></p>
							</td>
						</tr>

						<?php if ( ! $is_single ) : ?>
						<tr id="setting-update-existing">
							<th scope="row"><?php esc_html_e( 'Update Existing', 'bulk-create-users' ); ?></th>
							<td>
								<input type="checkbox" name="update-existing" value="1" id="update-existing" />
								<label for="update-existing"><?php esc_html_e( 'When the email address already exists, update the user', 'bulk-create-users' ); ?></label>
								<p class="description"><?php esc_html_e( 'By default, existing users (email addresses) will be skipped.', 'bulk-create-users' ); ?></p>
							</td>
						</tr>
						<?php endif; ?>

						<?php if ( is_multisite() && ( $sites = wp_get_sites() ) && 1 < count( $sites ) ) : ?>
						<tr id="setting-register-sites">
							<th scope="row"><?php esc_html_e( 'Register to Sites', 'bulk-create-users' ); ?></th>
							<td>
								<p class="description"><?php esc_html_e( 'Select the sites for which to register the users. Defaults to the main site.', 'bulk-create-users' ); ?></p>
								<ul>
									<li>
										<input type="checkbox" id="register-sites-toggle" onclick="toggle(this)" />
										<label for="register-sites-toggle" style="color:#aaa;"><?php esc_html_e( 'Select/Deselect All', 'bulk-create-users' ); ?></label>
										<script>
											function toggle( s ) {
												c = document.getElementsByName( 'register-sites[]' );
												for ( var i = 0; i < c.length; i++ ) {
													c[i].checked = s.checked;
												}
											}
										</script>
									</li>
									<?php foreach ( $sites as $site ) : ?>
									<li>
										<input type="checkbox" name="register-sites[]" value="<?php echo $site['blog_id']; ?>" id="register-sites-<?php echo $site['blog_id']; ?>" <?php checked( is_main_site( $site['blog_id'] ) ); ?> />
										<label for="register-sites-<?php echo $site['blog_id']; ?>"><?php echo $site['domain'] . $site['path']; ?></label>
									</li>
									<?php endforeach; ?>
								</ul>
							</td>
						</tr>
						<?php endif; ?>

						<tr id="setting-notification-email">
							<th scope="row"><?php esc_html_e( 'Notify New Users', 'bulk-create-users' ); ?></th>
							<td>
								<input type="radio" name="notification-email" value="none" id="notification-email-none" checked="checked" />
								<label for="notification-email-none"><?php esc_html_e( 'Do not send a notification email', 'bulk-create-users' ); ?></label><br/>
								<input type="radio" name="notification-email" value="default" id="notification-email-default" />
								<label for="notification-email-default"><?php esc_html_e( 'Send the default WordPress notification emails', 'bulk-create-users' ); ?></label><br/>
								<input type="radio" name="notification-email" value="custom" id="notification-email-custom" />
								<label for="notification-email-custom"><?php esc_html_e( 'Send a custom notification email', 'bulk-create-users' ); ?></label><br/>

								<div id="notification-email-custom-settings">
									<h4><?php esc_html_e( 'Custom Email Settings', 'bulk-create-users' ); ?></h4>
									<?php $settings = (object) wp_parse_args( get_site_option( 'bulk_create_users_custom_email', array() ), array(
										'from'      => '',
										'from_name' => '',

										// Mimic wp_new_user_notification() subject and content
										'subject'   => sprintf( esc_html__( '[%s] Your username and password' ), '%site_name%' ),
										'content'   => sprintf( esc_html__( 'Username: %s' ), '###USERNAME###' ) . "\r\n" . sprintf( esc_html__( 'Password: %s' ), '###PASSWORD###' ) . "\r\n###LOGINURL###\r\n",
										'redirect'  => ''
									) ); ?>

									<table class="form-table">
										<tr>
											<th><?php esc_html_e( 'From Name', 'bulk-create-users' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="notification-email-custom[from_name]" value="<?php echo $settings->from_name; ?>" id="email-custom-from-name" />
												<p class="description"><label for="email-custom-from-name"><?php esc_html_e( 'Define the name of the sender.', 'bulk-create-users' ); ?></label></p>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'From Address', 'bulk-create-users' ); ?></th>
											<td>
												<input type="email" class="regular-text" name="notification-email-custom[from]" value="<?php echo $settings->from; ?>" id="email-custom-from" />
												<p class="description"><label for="email-custom-from"><?php esc_html_e( 'Define the email address of the sender.', 'bulk-create-users' ); ?></label></p>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Email Subject', 'bulk-create-users' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="notification-email-custom[subject]" value="<?php echo $settings->subject; ?>" id="email-custom-subject" />
												<p class="description"><label for="email-custom-subject"><?php esc_html_e( 'Define the subject of the notification email.', 'bulk-create-users' ); ?></label></p>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Email Content', 'bulk-create-users' ); ?></th>
											<td>
												<p class="description"><label for="email-custom-content"><?php printf( __( 'Define the content of the notification email. Click <a href="%s">the help tab</a> to view the available variables (i.e. login, password).', 'bulk-create-users' ), '#' ); ?></label></p>
												<?php wp_editor( $settings->content, 'email-custom-content', array( 'textarea_name' => 'notification-email-custom[content]', 'media_buttons' => false ) ); ?>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Login Redirect', 'bulk-create-users' ); ?></th>
											<td>
												<input type="url" class="regular-text" name="notification-email-custom[redirect]" value="<?php echo $settings->redirect; ?>" id="email-custom-redirect" />
												<p class="description"><label for="email-custom-redirect"><?php esc_html_e( 'When sending the login link: Define the url to which to redirect the user after login.', 'bulk-create-users' ); ?></label></p>
											</td>
										</tr>
									</table>
								</div>

								<style>
									#setting-notification-email td > input,
									#setting-notification-email td > input + label {
										display: inline-block;
										margin-bottom: 6px;
									}
									#notification-email-custom-settings {
										display: none;
										margin-top: 1em;
									}
									input[name="notification-email"][value="custom"]:checked ~ #notification-email-custom-settings {
										display: block;
									}
								</style>
							</td>
						</tr>
					</table>

					<input type="hidden" name="step" value="import-uploaded-data" />
					<?php // wp_nonce_field( 'bulk-create-users-import' ); ?>
					<?php // submit_button( esc_html__( 'Import Users', 'bulk-create-users' ), 'primary', 'run-import', false ); ?>
					<input type="button" name="submit" class="button-primary" value="<?php esc_attr_e( 'Import Users', 'bulk-create-users' ); ?>" onclick="bcuimporter_start();" />
					<span class="spinner"></span>
					<span class="feedback-message"></span>
				</form>

						<?php
						break;
					endif;

				/**
				 * Start with uploading a file
				 */
				default : ?>

				<h3><?php esc_html_e( 'Start Import', 'bulk-create-users' ); ?></h3>

				<form enctype="multipart/form-data" id="upload-form" class="wp-upload-form" method="post" action="">
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Select a CSV file', 'bulk-create-users' ); ?></th>
							<td><input type="file" name="file" id="upload" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Separator', 'bulk-create-users' ); ?></th>
							<td><select name="sep"><option value=",">,</option><option value=";">;</option><option value="|">|</option></select></td>
						</tr>
					</table>

					<input type="hidden" name="step" value="read-uploaded-file" />
					<?php wp_nonce_field( 'bulk-create-users-read' ); ?>
					<?php submit_button( esc_html__( 'Upload', 'bulk-create-users' ), 'primary', 'file-upload', false ); ?>
					<span class="spinner"></span>
				</form>

			<?php endswitch; ?>

			<style>
				#import-settings .spinner { float: none; }
				.widefat tbody th:after { font-family: 'Dashicons'; font-size: 18px; content: '\f345'; float: right; }
			</style>

			<script>
				var bcuimporter_is_running = false;
				var bcuimporter_run_timer;
				var bcuimporter_delay_time = 0;
				var users = { 'created': [], 'updated': [] };
				var BCUMessages = <?php echo json_encode( $this->get_messages() ); ?>;
				var message = '<?php esc_html_e( 'Imported %d rows', 'bulk-create-users' ); ?>';

				function bcuimporter_grab_data() {
					var values = {}, arrayRegExp = /\[(.*?)\]/, i, name;
					jQuery.each( jQuery( '#import-settings' ).serializeArray(), function(i, field) {

						// Array-like inputs
						if ( null !== ( i = arrayRegExp.exec( field.name ) ) ) {
							name = field.name.substr(0, field.name.indexOf('['));
							if ( ! values[name] ) {
								values[name] = i[1] ? {} : [];
							}
							if ( ! i[1] ) {
								values[name].push( field.value );
							} else {
								values[name][ i[1] ] = field.value;
							}
						} else {
							values[field.name] = field.value;
						}
					});

					values['step']     = 'import-uploaded-data-ajax';
					values['action']   = 'bulk_create_users_import';
					values['_wpnonce'] = '<?php echo wp_create_nonce( 'bulk-create-users-import' ); ?>';

					return values;
				}

				function bcuimporter_start() {
					if ( false === bcuimporter_is_running ) {
						bcuimporter_is_running = true;
						jQuery('#import-settings .spinner').addClass('is-active');
						bcuimporter_feedback_message( '<?php esc_html_e( 'Started importing', 'bulk-create-users' ); ?>' );
						bcuimporter_run();
					}
				}

				function bcuimporter_run() {
					jQuery.post(ajaxurl, bcuimporter_grab_data(), function(response) {
						bcuimporter_success(response);
					});
				}

				function bcuimporter_stop(forced) {
					jQuery('#import-settings .spinner').removeClass('is-active');
					forced && bcuimporter_feedback_message( '<?php esc_html_e( 'Stopped importing', 'bulk-create-users' ); ?>' );
					bcuimporter_is_running = false;
					clearTimeout( bcuimporter_run_timer );
				}

				function bcuimporter_success(response) {
					if ( ! response.success ) {
						bcuimporter_stop(true);
						alert( BCUMessages.error[ response.data[0].code ] );

					} else if ( response.data.done ) {
						bcuimporter_feedback_message( '<?php esc_html_e( 'Importing succesfull', 'bulk-create-users' ); ?>' );
						bcuimporter_stop();
						bcuimporter_redirect();

					} else if ( bcuimporter_is_running ) { // keep going
						users.created.push.apply( users.created, response.data.created_users );
						users.updated.push.apply( users.updated, response.data.updated_users );
						bcuimporter_feedback_message( message.replace( '%d', users.created.length + users.updated.length ) );

						clearTimeout( bcuimporter_run_timer );
						bcuimporter_run_timer = setTimeout( 'bcuimporter_run()', bcuimporter_delay_time );
					} else {
						bcuimporter_stop(true);
					}
				}

				function bcuimporter_feedback_message(message) {
					jQuery('#import-settings .feedback-message').text( message );
				}

				function bcuimporter_redirect() {
					window.location.replace( '<?php echo esc_url_raw( add_query_arg( 'step', 'after-ajax-import' ) ); ?>' );
				}
			</script>
		</div>

		<?php
	}

	/**
	 * Return the data mapper for the given column
	 *
	 * @since 1.0.0
	 *
	 * @return string Option elements
	 */
	public function field_options() {

		// Get data options
		$options = bulk_create_users_data_fields();
		$opts    = '';

		// No data options 
		if ( empty ( $options ) ) {
			$opts = '<option>' . esc_html__( 'Nothing to select', 'bulk-create-users' ) . '</option>';
		} else {

			// Noselect option
			$opts .= '<option value="">' . esc_html__( 'Select an option', 'bulk-create-users' ) . '</option>';

			foreach ( $options as $context => $details ) {
				if ( empty( $details['options'] ) )
					continue;
				if ( count( $options ) > 1 ) {
					$opts .= '<optgroup value="' . esc_attr( $context ) . '" label="' . esc_attr( $details['label'] ) . '">';
				}
				foreach ( $details['options'] as $value => $label ) {
					$opts .= '<option value="' . esc_attr( $context . '.' . $value ) . '">' . $label . '</option>';
				}
				if ( count( $options ) > 1 ) {
					$opts .= '</optgroup>';
				}
			}
		}

		return $opts;
	}

	/**
	 * Output the main plugin's feedback message
	 *
	 * @since 1.0.0
	 */
	public function display_feedback_message() {

		// An error was reported
		if ( $error = $this->get_option( '_bulk_create_users_import_error', true ) ) {

			// Using a WP_Error object
			if ( is_wp_error( $error ) ) {

				// Fetch the error type
				if ( ( $data = $error->get_error_data( $error->get_error_code() ) ) && isset( $data['type'] ) ) {
					$type = $data['type'];
				} else {
					$type = 'error';
				}

				$errormsg = $error->get_error_message();

				// Display message
				if ( ! empty( $errormsg ) ) {
					echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . $errormsg . '</p></div>';
				} else {
					echo $this->get_feedback_message( $error->get_error_code(), $type );
				}

			// Using a single error code
			} else {
				echo $this->get_feedback_message( $error );
			}
		}
	}

	/**
	 * Return the given message code's feedback message
	 *
	 * @since 1.0.0
	 * 
	 * @param string $code Message code
	 * @param string $type Messege type. Either 'error', 'success' or 'info'. Defaults to 'info'
	 * @return string Feedback message element
	 */
	public function get_feedback_message( $code = 0, $type = 'error' ) {

		// Sanitize message type
		if ( ! in_array( $type, array( 'info', 'error', 'success' ) ) ) {
			$type = 'error';
		}

		// Setup messages
		$messages = $this->get_messages();

		// Output the message
		if ( ! empty( $code ) && isset( $messages[ $type ][ $code ] ) ) {

			// Get message arguments
			$args = array_slice( func_get_args(), 2 );

			// Handle nooped plurals
			if ( is_array( $messages[ $type ][ $code ] ) ) {
				$messages[ $type ][ $code ] = translate_nooped_plural( $messages[ $type ][ $code ], $args[0] ); // Use first message arg to determine count
			}

			// Error messages
			return '<div class="notice notice-' . $type . '"><p>' . vsprintf( $messages[ $type ][ $code ], $args ) . '</p></div>';
		} 
	}

	/**
	 * Return collection of all available messages
	 *
	 * @since 1.2.0
	 *
	 * @uses apply_filters() Calls 'bulk_create_users_error_messages'
	 * @uses apply_filters() Calls 'bulk_create_users_success_messages'
	 * @uses apply_filters() Calls 'bulk_create_users_info_messages'
	 * 
	 * @return array Messages
	 */
	public function get_messages() {
		return array(
			'info'    => apply_filters( 'bulk_create_users_info_messages', array() ),
			'error'   => apply_filters( 'bulk_create_users_error_messages', array(
				'no_file_found'               => esc_html__( 'Sorry, we could not find your file.', 'bulk-create-users' ),
				'unreadable_file'             => esc_html__( 'Sorry, we could not read the uploaded file.', 'bulk-create-users' ),
				'invalid_single_column'       => esc_html__( 'Sorry, we can only proces single column files if it contains email addresses.', 'bulk-create-users' ),
				'invalid_data'                => esc_html__( 'Sorry, the data from your file is gone or invalid.', 'bulk-create-users' ),
				'invalid_mapping'             => esc_html__( 'Sorry, the selected data options were incomplete or invalid.', 'bulk-create-users' ),
				'missing_email_field'         => esc_html__( 'Sorry, but we need an email field to register or recognize the users with.', 'bulk-create-users' ),
				'nothing_changed'             => esc_html__( 'Sorry, all users already exist.', 'bulk-create-users' ),
				'custom_email_missing_fields' => esc_html__( 'Sorry, we did not find any settings for your custom email to be sent.', 'bulk-create-users' ),
			) ),
			'success' => apply_filters( 'bulk_create_users_success_messages', array(
				'created_users'               => _n_noop( 'Successfully created %d user: %s', 'Successfully created %d users: %s', 'bulk-create-users' ),
				'updated_users'               => _n_noop( 'Successfully updated %d user: %s', 'Successfully updated %d users: %s', 'bulk-create-users' ),
				'removed_users'               => esc_html__( 'Succesfully removed the recently created users from your installation.', 'bulk-create-users' ),
			) ),
		);
	}

	/**
	 * Return a valid user login from a given email address
	 *
	 * @since 1.0.0
	 * 
	 * @param string $email Valid email address
	 * @return string|bool Valid user login or False when the login already exists
	 */
	public function generate_login_from_email( $email ) {

		// With second arg = true the login should validate
		$login = sanitize_user( strtok( $email, '@' ), true );

		// Return unique username or false
		return ( ! username_exists( $login ) ) ? $login : false;
	}

	/**
	 * Run the logic for registering a new user
	 *
	 * Exchange user notification email hooks on the fly.
	 *
	 * @see register_new_user()
	 *
	 * @since 1.0.0
	 * 
	 * @param string $user_login User login name
	 * @param string $user_email User email address
	 * @return WP_Error|int Error object or created user ID
	 */
	private function register_new_user( $user_login, $user_email ) {

		// Get notification type
		$notification = ! empty( $_REQUEST['notification-email'] ) ? $_REQUEST['notification-email'] : false;

		// Remove the default notification
		if ( 'default' !== $notification ) {
			remove_action( 'register_new_user', 'wp_send_new_user_notifications' );
		}

		// Custom notification, require settings
		if ( 'custom' === $notification && isset( $_REQUEST['notification-email-custom'] ) ) {
			add_action( 'register_new_user', 'bcu_send_new_user_notifications' );

			// Collect custom email settings
			$email_settings = array();
			foreach ( array( 'from_name', 'from', 'subject', 'content', 'redirect' ) as $email_field ) {
				$value = $_REQUEST['notification-email-custom'][ $email_field ];
				switch ( $email_field ) {
					case 'from_name' : $value = strip_tags( $value ); break;
					case 'from' : $value = sanitize_email( $value ); break;
					case 'subject' : $value = strip_tags( $value ); break;
					case 'content' : $value = wpautop( wp_kses( $value, wp_kses_allowed_html( 'post' ) ) ); break;
					case 'redirect' : $value = esc_url_raw( $value ); break;
				}
				if ( empty( $value ) ) {
					$value = '';
				}

				$email_settings[ $email_field ] = $value;
			}

			// Store custom email settings as option for repeated use
			update_site_option( 'bulk_create_users_custom_email', $email_settings );
		}

		// Run WP's user registration
		$user_id = register_new_user( $user_login, $user_email );

		// Reverse (un)hooking
		if ( 'default' !== $notification ) {
			add_action( 'register_new_user', 'wp_send_new_user_notifications' );
		}
		if ( 'custom' === $notification ) {
			remove_action( 'register_new_user', 'bcu_send_new_user_notifications' );
		}

		return $user_id;
	}

	/**
	 * Register the given user for the given sites
	 *
	 * @since 1.0.0
	 * 
	 * @param int $user_id User ID
	 * @param array $sites Sites to register
	 * @param bool $exact Whether to deregister from unprovided sites
	 */
	public function register_user_for_sites( $user_id, $sites = array(), $exact = false ) {

		// Setup sites variable
		if ( empty( $sites ) && ! empty( $_REQUEST['register-sites'] ) ) {
			$sites = $_REQUEST['register-sites'];
		} 

		// Bail when not doing multisite or no sites were given
		if ( ! is_multisite() || empty( $sites ) )
			return;

		// Walk the given sites
		foreach ( array_map( 'intval', $sites ) as $site_id ) {

			// Default to subscriber role
			add_user_to_blog( $site_id, $user_id, 'subscriber' );
		}

		// When running after user creation, match sites exactly
		if ( doing_action( 'bulk_create_users_new_user' ) ) {
			$exact = true;
		}

		// Remove user from main blog when it was not provided
		if ( $exact && ! in_array( get_current_site()->blog_id, $sites ) ) {
			remove_user_from_blog( $user_id, get_current_site()->blog_id );
		}
	}
}

/**
 * Return single instance of this main plugin class
 *
 * @since 1.0.0
 * 
 * @return Bulk_Create_Users
 */
function bulk_create_users() {
	return Bulk_Create_Users::instance();
}

// Initiate
bulk_create_users();

endif; // class_exists
