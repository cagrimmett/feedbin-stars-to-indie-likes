<?php
/**
 * Plugin Name:       Feedbin Stars to Indie Likes
 * Plugin URI:        https://github.com/cagrimmett/feedbin-stars-to-indie-likes
 * Description:       Takes starred posts from Feedbin and turns them into Indie Likes.
 * Version:           0.0.1
 * Author:            cagrimmett
 * Author URI:        https://cagrimmett.com
 * Text Domain:       fs2il
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

function fs2il_activate() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = $wpdb->prefix . 'fs2il';
	$sql             = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        feedbin_id BIGINT(20) NOT NULL,
        indie_like_id int(20) NOT NULL,
        UNIQUE KEY id (id)
    ) $charset_collate;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// Register plugin options
	register_setting(
		'fs2il_settings',
		'fs2il_username'
	);
	register_setting(
		'fs2il_settings',
		'fs2il_password'
	);
	register_setting(
		'fs2il_settings',
		'fs2il_author'
	);
	register_setting(
		'fs2il_settings',
		'fs2il_date'
	);
}
register_activation_hook( __FILE__, 'fs2il_activate' );

// schedule a cron job to run every 5 minutes
function fs2il_schedule() {
	wp_schedule_event( time(), 'hourly', 'fs2il_fetch_new_likes' );
}

register_activation_hook( __FILE__, 'fs2il_schedule' );
// hook that function onto our scheduled event
add_action( 'fs2il_fetch_new_likes', 'fs2il_convert_stars_to_likes' );

function fs2il_deactivate() {
	wp_clear_scheduled_hook( 'fs2il_fetch_new_likes' );
}
register_deactivation_hook( __FILE__, 'fs2il_deactivate' );

function fs2il_uninstall() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'fs2il';
	$sql        = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query( $sql );

	delete_option( 'fs2il_username' );
	delete_option( 'fs2il_password' );
	delete_option( 'fs2il_author' );
	delete_option( 'fs2il_date' );
}
 register_uninstall_hook( __FILE__, 'fs2il_uninstall' );

function fs2il_convert_stars_to_likes() {

	if ( is_plugin_active( 'indieblocks/indieblocks.php' ) ) {
		$username = get_option( 'fs2il_username' );
		$password = get_option( 'fs2il_password' );
		$author   = get_option( 'fs2il_author' );
		$date     = get_option( 'fs2il_date' );

		// Check if the plugin options have been set

		if ( ! $username || ! $password || ! $author || ! $date ) {
			printf( 'No username, password, author, or date set. Please go to the <a href="%s">plugin settings page</a> and set them.', admin_url( 'options-general.php?page=feedbin-stars-to-indie-likes' ) );
			return;
		} else {
			// Fetch the existing starred posts from the database
			global $wpdb;
			$table_name     = $wpdb->prefix . 'fs2il';
			$existing_posts = $wpdb->get_results( "SELECT feedbin_id FROM $table_name" );
			//turn the array of objects into an array of integers so the array_filter function works
			$existing_posts = array_map(
				function( $existing_post ) {
					settype( $existing_post->feedbin_id, 'int' );
					return $existing_post->feedbin_id;
				},
				$existing_posts
			);

			// Get Feedbin posts.
			$credentials         = base64_encode( $username . ':' . $password );
			$feedbin_request_url = 'https://api.feedbin.com/v2/entries.json?starred=true&since=' . rawurlencode( $date );
			$feedbin_response    = wp_remote_get(
				$feedbin_request_url,
				array(
					'headers' => array(
						'Authorization' => 'Basic ' . $credentials,
					),
				)
			);

			$feedbin_posts = json_decode( $feedbin_response['body'], true );

			// Filter out starred posts that have already been processed and stored in the DB

			if ( ! empty( $existing_posts ) ) {
				$feedbin_posts = array_filter(
					$feedbin_posts,
					function( $feedbin_post ) use ( $existing_posts ) {
						settype( $feedbin_post['id'], 'string' );
						return ! in_array( $feedbin_post['id'], $existing_posts );
					}
				);
			}

			foreach ( $feedbin_posts as $feedbin_post ) {
				$indie_like_id = wp_insert_post(
					array(
						'post_type'    => 'indieblocks_like',
						'post_title'   => 'Likes ' . $feedbin_post['url'],
						'post_status'  => 'publish',
						'to_ping'      => $feedbin_post['url'],
						'post_author'  => $author,
						'post_content' => '<!-- wp:indieblocks/context -->
            <div class="wp-block-indieblocks-context"><i>Likes <a class="u-like-of" href="' . $feedbin_post['url'] . '">' . $feedbin_post['url'] . '</a>.</i></div>
            <!-- /wp:indieblocks/context -->',
					)
				);

				// Save the Feedbin ID and Indie Like ID to the database.
				global $wpdb;
				$table_name = $wpdb->prefix . 'fs2il';
				$wpdb->insert(
					$table_name,
					array(
						'feedbin_id'    => $feedbin_post['id'],
						'indie_like_id' => $indie_like_id,
					)
				);

			}
		}
	} else {
		return;
	}
}

function fs2il_options_page() {
	add_submenu_page(
		'tools.php', // Parent page slug
		'Feedbin Stars to Indie Likes Settings', // Page title
		'Feedbin Stars to Indie Likes', // Menu title
		'manage_options', // Capability required to access the page
		'fs2il-settings', // Menu slug
		'fs2il_settings_page' // Callback function to render the page
	);
}
add_action( 'admin_menu', 'fs2il_options_page' );

// Callback function to render the plugin settings page
function fs2il_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}

			// Check if form has been submitted
	if ( isset( $_POST['fs2il_settings_form_submitted'] ) ) {
		// Validate and sanitize form input
		$username = sanitize_text_field( $_POST['fs2il_username'] );
		$password = sanitize_text_field( $_POST['fs2il_password'] );
		$author   = intval( $_POST['fs2il_author'] );
		$date     = sanitize_text_field( $_POST['fs2il_date'] );

		// Save form input to plugin options
		update_option( 'fs2il_username', $username );
		update_option( 'fs2il_password', $password );
		update_option( 'fs2il_author', $author );
		update_option( 'fs2il_date', $date );

		// Display success message
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p>Settings saved successfully.</p>';
		echo '</div>';
	}

			// Render form
	?>

		<div class="wrap">
			<h1>Feedbin Stars to Indie Likes Settings</h1>

			<?php
			if ( ! is_plugin_active( 'indieblocks/indieblocks.php' ) ) {
				echo '<div class="notice notice-error">
                <p>This plugin requires <a href="https://wordpress.org/plugins/indieblocks/">IndieBlocks</a> to be installed and activated.</p>
                </div>';
			}
			?>
			<form method="post" action="">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="fs2il_username">Feedbin Username</label>
						</th>
						<td>
							<input type="text" name="fs2il_username" id="fs2il_username" value="<?php echo esc_attr( get_option( 'fs2il_username' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="fs2il_password">Feedbin Password</label>
						</th>
						<td>
							<input type="text" name="fs2il_password" id="fs2il_password" value="<?php echo esc_attr( get_option( 'fs2il_password' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="fs2il_author">Author to attribute the likes to</label>
						</th>
						<td>
							<?php
							// Get a list of all users
							$users = get_users();
							?>
							<select name="fs2il_author" id="fs2il_author">
								<?php
								foreach ( $users as $user ) {
									$selected = selected( $user->ID, get_option( 'fs2il_author' ), false );
									echo "<option value='$user->ID' $selected>$user->display_name</option>";
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="fs2il_date">Date</label>
						</th>
						<td>
							<input type="text" name="fs2il_date" id="fs2il_date" value="<?php echo esc_attr( get_option( 'fs2il_date' ) ); ?>" class="regular-text" />
							<p class="description">The date string to append to the Feedbin API request, in the format YYYY-MM-DDTHH:MM:SS - Only posts after this date will be fetched</p>
						</td>
					</tr>
				</table>
				<input type="hidden" name="fs2il_settings_form_submitted" value="1" />
				<?php submit_button(); ?>
			</form>
		</div>
	<?php
}

function fs2il_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'tools.php?page=fs2il-settings' ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'fs2il_settings_link' );