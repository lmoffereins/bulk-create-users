<?php

/**
 * The Bulk Create Users Plugin
 * 
 * @package Bulk Create Users
 * @subpackage Main
 */

/**
 * Plugin Name:       Bulk Create Users
 * Description:       Create multiple users at once 
 * Plugin URI:        https://github.com/lmoffereins/bulk-create-users/
 * Version:           1.0.0
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins/
 * Network:           true
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
	 * @uses Bulk_Create_Users::setup_globals()
	 * @uses Bulk_Create_Users::includes()
	 * @uses Bulk_Create_Users::setup_actions()
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

		$this->version = '1.0.0';

		/** Paths *************************************************************/

		// Setup some base path and URL information
		$this->file         = __FILE__;
		$this->basename     = plugin_basename( $this->file );
		$this->plugin_dir   = plugin_dir_path( $this->file );
		$this->plugin_url   = plugin_dir_url ( $this->file );

		// Includes
		$this->includes_dir = trailingslashit( $this->plugin_dir . 'includes'  );
		$this->includes_url = trailingslashit( $this->plugin_url . 'includes'  );

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
		require ( $this->includes_dir . 'buddypress.php' );
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {
		add_action( 'admin_menu',         array( $this, 'admin_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );

		// After user creation
		add_action( 'bulk_create_users_user_created', array( $this, 'register_user_for_sites' ), 10, 1 ); // Only pass the first argument
		add_action( 'bulk_create_users_user_created', array( $this, 'send_registration_email' ), 10, 2 );
		add_action( 'bulk_create_users_user_created', array( $this, 'store_user_password'     ), 10, 2 );
	}

	/** Public methods **************************************************/

	/**
	 * Shortcut for returning the given plugin option
	 *
	 * @since 1.0.0
	 *
	 * @uses get_current_user_id()
	 * @uses get_user_meta()
	 * @uses delete_user_meta()
	 * 
	 * @param string $option Option name
	 * @param bool $delete Whether to delete the option immediately after getting it
	 * @return mixed Option
	 */
	public function get_option( $option, $delete = false ) {
		$user_id = get_current_user_id();
		$value = get_user_meta( $user_id, $option, true );		
		if ( $delete ) {
			delete_user_meta( $user_id, $option );
		}
		return $value;
	}

	/**
	 * Shortcut for updating the given plugin option
	 *
	 * @since 1.0.0
	 *
	 * @uses update_user_meta()
	 * 
	 * @param string $option Option name
	 * @param mixed $value New value
	 * @return bool Update result
	 */
	public function update_option( $option, $value ) {
		return update_user_meta( get_current_user_id(), $option, $value );		
	}

	/**
	 * Shortcut for deleting the given plugin option
	 *
	 * @since 1.0.0
	 *
	 * @uses delete_user_meta()
	 * 
	 * @param string $option Option name
	 * @return bool Deletion result
	 */
	public function delete_option( $option ) {
		return delete_user_meta( get_current_user_id(), $option );		
	}

	/** Administration **************************************************/

	/**
	 * Create the plugin's admin menu item
	 *
	 * @since 1.0.0
	 *
	 * @uses is_multisite()
	 * @uses add_users_page()
	 * @uses add_submenu_page()
	 * @uses add_action()
	 */
	public function admin_menu() {

		// Define admin page args
		$caller    = 'add_users_page';
		$page_args = array( __( 'Bulk Create Users', 'bulk-create-users'), __( 'Bulk Create', 'bulk-create-users' ), 'create_users', 'bulk-create-users', array( $this, 'admin_page' ) );

		// Network
		if ( is_multisite() ) {
			$caller = 'add_submenu_page';
			array_unshift( $page_args, 'users.php' );
		}

		// Create admin page
		$hook = call_user_func_array( $caller, $page_args );

		// Add load hook
		add_action( "load-$hook", array( $this, 'load_admin_page' ) );
	}

	/**
	 * Run logic before on loading the plugin's admin page
	 *
	 * @since 1.0.0
	 *
	 * @uses Bulk_Create_Users::update_option()
	 * @uses Bulk_Create_Users::get_option()
	 * @uses Bulk_Create_Users::delete_option()
	 * @uses Bulk_Create_Users::generate_login_from_email()
	 * @uses Bulk_Create_Users::register_new_user()
	 */
	public function load_admin_page() {

		// Bail when user is not capable
		if ( ! current_user_can( 'create_users' ) )
			wp_die( __( 'You do not have sufficient permissions to access this page.' , 'bulk-create-users' ) );

		// Define local variable(s)
		$step    = isset( $_REQUEST['step'] ) ? $_REQUEST['step'] : 0;
		$referer = isset( $_REQUEST['_wp_http_referer'] ) ? $_REQUEST['_wp_http_referer'] : add_query_arg( 'page', 'bulk-create-users', self_admin_url( 'users.php' ) );
		$errors  = new WP_Error();
		$csv     = null;

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
					require_once( $this->includes_dir . 'class-readcsv.php' );

					// Open the file
					$csv = new ReadCSV( $file, ',', "\xEF\xBB\xBF" ); // Skip any UTF-8 byte order mark

					// Define csv details
					$rows = $columns = array();
					$first = true;
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

					fclose( $file );

					// Temporarily store the file data for the current user
					$this->update_option( '_bulk_create_users_uploaded_file_data', compact( 'columns', 'rows' ) );
				}
			}

			break; // read-uploaded-file

		/**
		 * Import the uploaded user data
		 */
		case 'import-uploaded-data' :

			// Bail when not accessed properly
			if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-create-users-import' ) ) {
				wp_redirect( $referer );
				exit;
			}

			// Get uploaded file data
			$file_data = $this->get_option( '_bulk_create_users_uploaded_file_data' );

			// Read form data
			$overwrite = ! empty( $_REQUEST['overwrite-existing'] );
			$sites     = is_multisite() && ! empty( $_REQUEST['register-sites'] ) ? $_REQUEST['register-sites'] : array();

			/**
			 * The following logic is placed in a one-run do-while loop so 
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
				$single = 1 == count( $file_columns );

				// Setup multi-column data map
				if ( ! $single ) {

					// Report error when data map is missing
					if ( ! isset( $_REQUEST['map_to'] ) || empty( $_REQUEST['map_to'] ) ) {
						$errors->add( 'invalid_mapping', '' );
						break;
					}

					// Get the provided data map
					$_data_map = $_REQUEST['map_to'];

					// Report error when missing the required email field to identify the user with
					if ( ! in_array( 'users.user_email', $_data_map ) ) {
						$errors->add( 'missing_email_field', '' );
						break;
					}

					// Setup data map
					$data_map  = array();
					foreach ( $_data_map as $col => $contextfield ) {
						if ( empty( $contextfield ) )
							continue;
						$data_map[ $col ] = new stdClass;
						$data_map[ $col ]->context = strtok( $contextfield, '.' );
						$data_map[ $col ]->field   = substr( $contextfield, strpos( $contextfield, '.' ) + 1 );
					}
				}

				// Setup collection of created and updated users
				$created_users = array();
				$updated_users = array();

				// Walk soon-to-be users
				foreach ( $file_rows as $i => $user_row ) {

					// Define local variable(s)
					$user_id = false;
					$login   = false;

					// Key: get the email field value
					$email = $single ? $user_row[0] : $user_row[ array_search( 'users.user_email', $_data_map ) ];

					// Skip invalid emails
					if ( ! is_email( $email ) ) {
						$created_users[ $i ] = new WP_Error( 'invalid_email', __( '<strong>ERROR</strong>: The email address isn&#8217;t correct.' ) );
						continue;
					}

					// User (email) does not exist
					if ( ! $user_id = email_exists( $email ) ) {

						// Multi-column
						if ( ! $single ) {

							// Find fields we can create a username with
							$login_col = array_search( 'users.user_login',     $_data_map );
							$fname_col = array_search( 'users.user_firstname', $_data_map );
							$lname_col = array_search( 'users.user_lastname',  $_data_map );

							// Define login based on login field ... 
							if ( false !== $login_col ) {
								$login = sanitize_user( $user_row[ $login_col ], true );
							}

							// ... or firstname + lastname field ...
							if ( ( ! $login || username_exists( $login ) ) && false !== $fname_col && false !== $lname_col ) {
								$login = sanitize_user( $user_row[ $fname_col ] . '_' . $user_row[ $lname_col ], true );
							}

							// ... or lastname field ...
							if ( ( ! $login || username_exists( $login ) ) && false !== $lname_col ) {
								$login = sanitize_user( $user_row[ $lname_col ], true );
							}

							// ... or firstname field
							if ( ( ! $login || username_exists( $login ) ) && false !== $fname_col ) {
								$login = sanitize_user( $user_row[ $fname_col ], true );
							}
						}

						// Any column: default to email field
						if ( ! $login || username_exists( $login ) ) {
							$login = $this->generate_login_from_email( $email );
						}

						// Could not create a valid username
						if ( ! $login ) {
							$created_users[ $i ] = new WP_Error( 'invalid_username', __( '<strong>ERROR</strong>: Could not gerenate a valid user name.', 'bulk-create-users' ) );
							continue;

						// Create new user
						} else {
							$created_users[ $i ] = $user_id = $this->register_new_user( $login, $email );

							// For single column files it ends here
							if ( $single )
								continue;
						}

					// User already exists and not overwriting: next!
					} elseif ( $user_id && ! $overwrite ) {
						continue;
					}

					// Update base user data
					$data_map_without_email = $data_map;
					unset( $data_map_without_email[ array_search( 'users.user_email', $_data_map ) ] );
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

					// Walk addittional mapped fields
					foreach ( array_keys( $data_map ) as $col ) {

						// Define this row & column's specific params
						$field = $data_map[ $col ];
						$value = $user_row[ $col ];

						// Check the field's context
						switch ( $field->context ) {

							// Skip users context
							case 'users' :
								break;

							// Update user meta
							case 'usermeta' :
								update_user_meta( $user_id, $field->field, $value );
								break;

							// Provide custom field saving
							default :
								do_action( "bulk_create_users_import_{$field->context}", $field->field, $user_id, $value );
								break;
						}
					}
				}

				// Temporarily store the created users collection
				if ( ! empty( $created_users ) || ! empty( $updated_users ) ) {
					$this->update_option( '_bulk_create_users_imported_users', compact( 'created_users', 'updated_users' ) );
				} else {
					$errors->add( 'nothing_changed', '' );
				}

			} while ( 0 );

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

		endswitch; endif; // step

		// Handle registered errors
		if ( ( $errcode = $errors->get_error_code() ) && ! empty( $errcode ) ) {

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
			$this->delete_option( '_bulk_create_users_uploaded_file_data' );
			$this->delete_option( '_bulk_create_users_imported_users'     );
		}
	}

	/**
	 * Output the plugin's admin page
	 *
	 * @since 1.0.0
	 *
	 * @uses sanitize_key() 
	 * @uses add_query_arg()
	 * @uses self_admin_url()
	 * @uses Bulk_Create_Users::display_erro_message()
	 * @uses get_user_meta()
	 * @uses wp_nonce_field()
	 * @uses submit_button()
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
			$single = 1 == $column_count;
		} else {
			$step = 0;
		} ?>

		<div class="wrap">
			<h2><?php _e( 'Bulk Create Users', 'bulk-create-users' );

				// Restart button
				if ( $step ) {
					echo ' <a class="add-new-h2" href="' . add_query_arg( 'page', 'bulk-create-users', self_admin_url( 'users.php' ) ) . '">' . __( 'Restart', 'bulk-create-users' ) . '</a>';
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

					// Collect the import data
					$users         = $this->get_option( '_bulk_create_users_imported_users', true );
					$created_users = $created_errors = array();
					$updated_users = $users['updated_users'];

					// Collect results types
					foreach ( $users['created_users'] as $i => $user ) {
						if ( ! is_wp_error( $user ) ) {
							$created_users[ $i ] = $user;
						} else {
							$created_errors[ $i ] = $user;
						}
					} ?>

				<h3><?php _e( 'Import Results', 'bulk-create-users' ); ?></h3>

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
				<table class="widefat">
					<thead>
						<tr>
							<th scope="col"><?php _e( 'Row number',       'bulk-create-users' ); ?></th>
							<th scope="col"><?php _e( 'Feedback message', 'bulk-create-users' ); ?></th>
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
						<a class="button button-primary" href="<?php echo add_query_arg( array( 'orderby' => 'id', 'order' => 'desc' ), self_admin_url( 'users.php' ) ); ?>"><?php _e( 'View Created Users', 'bulk-create-users' ); ?></a>
						<?php submit_button( __( 'Remove Created Users', 'bulk-create-users' ), 'delete', 'remove-created', false ); ?>
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
					<p><?php printf( ( ! $single ? __( 'We found %1$d columns and %2$d rows in your file.', 'bulk-create-users' ) : __( 'We found 1 column and %2$d rows in your file', 'bulk-create-users' ) ),
						$column_count, $row_count
					); ?></p>
				</div>

				<h3><?php _e( 'Import Settings', 'bulk-create-users' ); ?></h3>

				<p><?php _e( 'At least an <strong>email address</strong> is required to be able to setup your (new) users.', 'bulk-create-users' ); ?></p>

				<form id="import-settings" method="post" action="">

					<?php // Map columns for multi-column files ?>
					<?php if ( ! $single ) : $field_options = $this->field_options(); ?>
					<table class="widefat">
						<thead>
							<tr>
								<th scope="col"><?php _e( 'File Column',  'bulk-create-users' ); ?></th>
								<th scope="col"><?php _e( 'Map To Field', 'bulk-create-users' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $file_data['columns'] as $column => $name ) : ?>
							<tr>
								<th scope="row"><?php echo $name; ?></th>
								<td><select name="map_to[<?php echo esc_attr( strtolower( $column ) ); ?>]"><?php echo $field_options; ?></select></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>

					<table class="form-table">

						<?php // Register to Sites ?>
						<?php if ( is_multisite() && ( $sites = wp_get_sites() ) && 1 < count( $sites ) ) : ?>
						<tr>
							<th scope="row"><?php _e( 'Register to Sites', 'bulk-create-users' ); ?></th>
							<td>
								<p class="description"><?php _e( 'Select the sites for which to register the users. Defaults to the main site.', 'bulk-create-users' ); ?></p>
								<ul>
									<li>
										<input type="checkbox" id="register-sites-toggle" onclick="toggle(this)" />
										<label for="register-sites-toggle" style="color:#aaa;"><?php _e( 'Select/Deselect All', 'bulk-create-users' ); ?></label>
										<script>
											function toggle(s) {
												c = document.getElementsByName('register-sites[]');
												for(var i=0, n=c.length;i<n;i++) {
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

						<?php if ( ! $single ) : ?>
						<tr>
							<th scope="row"><?php _e( 'Overwrite Existing', 'bulk-create-users' ); ?></th>
							<td>
								<input type="checkbox" name="overwrite-existing" value="1" id="overwrite-existing" />
								<label for="overwrite-existing"><?php _e( 'When the email address already exists, update the user', 'bulk-create-users' ); ?></label>
								<p class="description"><?php _e( 'By default, existing users (email addresses) will be skipped.', 'bulk-create-users' ); ?></p>
							</td>
						</tr>
						<?php endif; ?>

						<tr>
							<th scope="row"><?php _e( 'Keep Password', 'bulk-create-users' ); ?></th>
							<td>
								<input type="checkbox" name="store-password" value="1" id="store-password" />
								<label for="store-password"><?php _e( 'On user creation, store the registration password and keep it for later use.', 'bulk-create-users' ); ?></label>
								<p class="description"><?php printf( __( 'The registration password will be stored in the %s user meta field.', 'bulk-create-users' ), '<code>_registration_password</code>' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php _e( 'Registration Email', 'bulk-create-users' ); ?></th>
							<td>
								<input type="checkbox" name="registration-email" value="1" id="registration-email" />
								<label for="registration-email"><?php _e( 'On user creation, send the new user a registration notification email.', 'bulk-create-users' ); ?></label>
								<p class="description"><?php _e( 'This is the default WordPress registration notification email, containing only the login credentials.', 'bulk-create-users' ); ?></p>
							</td>
						</tr>
					</table>

					<input type="hidden" name="step" value="import-uploaded-data" />
					<?php wp_nonce_field( 'bulk-create-users-import' ); ?>
					<?php submit_button( __( 'Import Users', 'bulk-create-users' ), 'primary', 'run-import', false ); ?>
					<span class="spinner"></span>
				</form>

						<?php
						break;
					endif;

				/**
				 * Start with uploading a file
				 */
				default : ?>

				<h3><?php _e( 'Start Import', 'bulk-create-users' ); ?></h3>

				<form enctype="multipart/form-data" id="upload-form" class="wp-upload-form" method="post" action="">
					<table class="form-table">
						<tr>
							<th scope="row"><?php _e( 'Select a CSV file', 'bulk-create-users' ); ?></th>
							<td><input type="file" name="file" id="upload" /></td>
						</tr>
					</table>

					<input type="hidden" name="step" value="read-uploaded-file" />
					<?php wp_nonce_field( 'bulk-create-users-read' ); ?>
					<?php submit_button( __( 'Upload', 'bulk-create-users' ), 'primary', 'file-upload', false ); ?>
					<span class="spinner"></span>
				</form>

			<?php endswitch; ?>

			<style>
				.wrap .spinner { float: none; vertical-align: middle; }
				.wrap.loading .spinner { display: inline-block; }
			</style>
			<script>
				jQuery( '.wrap form' ).on( 'submit', function() {
					jQuery( '.wrap' ).addClass( 'loading' );
				});
			</script>

		</div>

		<?php
	}

	/**
	 * Return the data mapper for the given column
	 *
	 * @since 1.0.0
	 *
	 * @uses Bulk_Create_Users::data_fields()
	 * @return string Option elements
	 */
	public function field_options() {

		// Get data options
		$options = $this->data_fields();
		$opts    = '';

		// No data options 
		if ( empty ( $options ) ) {
			$opts = '<option>' . __( 'Nothing to select', 'bulk-create-users' ) . '</option>';
		} else {

			// Noselect option
			$opts .= '<option value="">' . __( 'Select an option', 'bulk-create-users' ) . '</option>';

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
	 * Return a collection of data options
	 *
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'bulk_create_users_data_fields'
	 * @return array Data map
	 */
	public function data_fields() {
		return (array) apply_filters( 'bulk_create_users_data_fields', array(

			// Base user data handled by wp_update_user()
			'users' => array(
				'label'   => __( 'Base Data', 'bulk-create-users' ),
				'options' => array(
					'user_email'      => __( 'Email Address',   'bulk-create-users' ) . '*', // Key
					'user_login'      => __( 'Login',           'bulk-create-users' ),
					'user_pass'       => __( 'Password',        'bulk-create-users' ),
					'user_nicename'   => __( 'Nicename',        'bulk-create-users' ),
					'user_url'        => __( 'Website',         'bulk-create-users' ),
					'first_name'      => __( 'First Name',      'bulk-create-users' ),
					'last_name'       => __( 'Last Name',       'bulk-create-users' ),
					'display_name'    => __( 'Display Name',    'bulk-create-users' ),
					'nickname'        => __( 'Nickname',        'bulk-create-users' ),
					'description'     => __( 'Description',     'bulk-create-users' ),
				)
			),

			// User meta fields
			'usermeta' => array(
				'label'   => __( 'User Meta', 'bulk-create-users' ),
				'options' => apply_filters( 'bulk_create_users_data_fields_usermeta', array() )
			),
		) );
	}

	/**
	 * Output the main plugin's feedback message
	 *
	 * @since 1.0.0
	 *
	 * @uses Bulk_Create_Users::get_option()
	 * @uses WP_Error::get_error_message()
	 * @uses WP_Error::get_error_data()
	 * @uses WP_Error::get_error_code()
	 * @uses Bulk_Create_Users::get_feedback_message()
	 */
	public function display_feedback_message() {

		// An error was reported
		if ( $error = $this->get_option( '_bulk_create_users_import_error', true ) ) {

			// Using a WP_Error object
			if ( is_a( $error, 'WP_Error' ) ) {

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
	 * @uses apply_filters() Calls 'bulk_create_users_error_messages'
	 * @uses apply_filters() Calls 'bulk_create_users_success_messages'
	 * @uses apply_filters() Calls 'bulk_create_users_info_messages'
	 * @uses translate_nooped_plural()
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
		$messages = array(
			'info'    => apply_filters( 'bulk_create_users_info_messages', array() ),
			'error'   => apply_filters( 'bulk_create_users_error_messages', array(
				'no_file_found'         => __( 'Sorry, we could not find your file.', 'bulk-create-users' ),
				'unreadable_file'       => __( 'Sorry, we could not read the uploaded file.', 'bulk-create-users' ),
				'invalid_single_column' => __( 'Sorry, we can only proces single column files if it contains email addresses.', 'bulk-create-users' ),
				'invalid_data'          => __( 'Sorry, the data from your file is gone or invalid.', 'bulk-create-users' ),
				'invalid_mapping'       => __( 'Sorry, the selected data options were incomplete or invalid.', 'bulk-create-users' ),
				'missing_email_field'   => __( 'Sorry, but we need an email field to register or recognize the users with.', 'bulk-create-users' ),
				'nothing_changed'       => __( 'Sorry, all users already exist.', 'bulk-create-users' ),
			) ),
			'success' => apply_filters( 'bulk_create_users_success_messages', array(
				'created_users'         => _n_noop( 'Successfully created %d user: %s', 'Successfully created %d users: %s', 'bulk-create-users' ),
				'updated_users'         => _n_noop( 'Successfully updated %d user: %s', 'Successfully updated %d users: %s', 'bulk-create-users' ),
				'removed_users'         => __( 'Succesfully removed the recently created users from your installation.', 'bulk-create-users' ),
			) ),
		);

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
	 * Return a valid user login from a given email address
	 *
	 * @since 1.0.0
	 *
	 * @uses sanitize_user()
	 * @uses username_exists()
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
	 * This is our own extended implementation of {@link register_new_user()}. Most
	 * importantly it removes the wp_new_user_notification() call, and adds a
	 * dedicated post-user-creation hook with the user ID and user password.
	 *
	 * @since 1.0.0
	 *
	 * @see register_new_user()
	 * 
	 * @param string $user_login User login name
	 * @param string $user_email User email address
	 * @return WP_Error|int Error object or created user ID
	 */
	private function register_new_user( $user_login, $user_email ) {
		$errors = new WP_Error();

		$sanitized_user_login = sanitize_user( $user_login );
		/** This filter is documented in wp-includes/users.php */
		$user_email = apply_filters( 'user_registration_email', $user_email );

		// Check the username
		if ( $sanitized_user_login == '' ) {
			$errors->add( 'empty_username', __( '<strong>ERROR</strong>: Please enter a username.' ) );
		} elseif ( ! validate_username( $user_login ) ) {
			$errors->add( 'invalid_username', __( '<strong>ERROR</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.' ) );
			$sanitized_user_login = '';
		} elseif ( username_exists( $sanitized_user_login ) ) {
			$errors->add( 'username_exists', __( '<strong>ERROR</strong>: This username is already registered. Please choose another one.' ) );
		}

		// Check the e-mail address
		if ( $user_email == '' ) {
			$errors->add( 'empty_email', __( '<strong>ERROR</strong>: Please type your e-mail address.' ) );
		} elseif ( ! is_email( $user_email ) ) {
			$errors->add( 'invalid_email', __( '<strong>ERROR</strong>: The email address isn&#8217;t correct.' ) );
			$user_email = '';
		} elseif ( email_exists( $user_email ) ) {
			$errors->add( 'email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.' ) );
		}

		/** This action is documented in wp-includes/users.php */
		do_action( 'register_post', $sanitized_user_login, $user_email, $errors );

		/** This filter is documented in wp-includes/users.php */
		$errors = apply_filters( 'registration_errors', $errors, $sanitized_user_login, $user_email );

		if ( $errors->get_error_code() )
			return $errors;

		$user_pass = wp_generate_password( 12, false );
		$user_id = wp_create_user( $sanitized_user_login, $user_pass, $user_email );
		if ( ! $user_id || is_wp_error( $user_id ) ) {
			$errors->add( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you&hellip; please contact the <a href="mailto:%s">webmaster</a> !' ), get_option( 'admin_email' ) ) );
			return $errors;
		}

		update_user_option( $user_id, 'default_password_nag', true, true ); //Set up the Password change nag.

		/** The missing after-new-user-creation hook */
		do_action( 'bulk_create_users_user_created', $user_id, $user_pass );

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
			$sites = array_map( 'intval', $_REQUEST['register-sites'] );
		}

		// Bail when not doing multisite or no sites were given
		if ( ! is_multisite() || empty( $sites ) )
			return;

		// Walk the given sites
		foreach ( $sites as $site_id ) {

			// User was first registered on the main site
			if ( is_main_site( $site_id ) )
				continue;

			// Default to subscriber role
			add_user_to_blog( $site_id, $user_id, 'subscriber' );
		}

		// When running after user creation, match sites exactly
		if ( doing_action( 'bulk_create_users_user_created' ) ) {
			$exact = true;
		}

		// Remove user from main blog when it was not provided
		if ( $exact && ! in_array( get_current_site()->blog_id, $sites ) ) {
			remove_user_from_blog( $user_id, get_current_site()->blog_id );
		}
	}

	/**
	 * Send the default user registration notification email
	 *
	 * @since 1.0.0
	 *
	 * @uses doing_action()
	 * @uses wp_new_user_notification()
	 *
	 * @param int $user_id User ID
	 * @param string $user_pass User password
	 */
	public function send_registration_email( $user_id, $user_pass ) {

		// Bail when requested not to send
		if ( doing_action( 'bulk_create_users_user_created' ) && empty( $_REQUEST['registration-email'] ) )
			return;

		wp_new_user_notification( $user_id, $user_pass );
	}

	/**
	 * Store the new user's password
	 *
	 * @since 1.0.0
	 *
	 * @uses update_user_meta()
	 * 
	 * @param int $user_id User ID
	 * @param string $user_pass User password
	 */
	public function store_user_password( $user_id, $user_pass ) {
		update_user_meta( $user_id, '_registration_password', $user_pass );
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
