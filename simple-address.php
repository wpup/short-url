<?php

/**
 * Plugin Name: Simple address
 * Description: Simple address to the permalink, like in EPiServer.
 * Author: Fredrik Forsmo
 * Author URI: http://forsmo.me/
 * Version: 2.0.0
 * Plugin URI: https://github.com/frozzare/simple-address
 */
class Simple_Address {

	/**
	 * The instance of Simple address.
	 *
	 * @var Simple_Address|null
	 */

	private static $instance;

	/**
	 * The cache key that is used by Simple address.
	 *
	 * @var string
	 * @since 2.0.0
	 */

	private $cache_key = '_simple_address_query';

	/**
	 * The post meta key that is used by Simple address.
	 *
	 * @var string
	 * @since 2.0.0
	 */

	private $meta_key = '_simple_address';

	/**
	 * Simple address version.
	 *
	 * @var string
	 * @since 2.0.0
	 */

	private $version = '2.0.0';

	/**
	 * Find post by post name.
	 *
	 * @param $post_name
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */

	private function find_post( $post_name ) {
		global $wpdb;
		$query   = $wpdb->prepare( "SELECT * FROM wp_posts WHERE post_name = %s", $post_name );
		$results = $wpdb->get_results( $query );

		return array_shift( $results );
	}

	/**
	 * Get all posts with the given Simple address.
	 *
	 * @param string $simple_address
	 * @param bool $no_cache
	 *
	 * @since 2.0.0
	 * @access private
	 *
	 * @return mixed
	 */

	private function get_posts( $simple_address, $no_cache = false ) {
		$posts = wp_cache_get( $this->cache_key );

		if ( empty( $posts ) || $no_cache ) {
			$args = array(
				'meta_key'   => $this->meta_key,
				'meta_value' => $simple_address
			);

			$query = new WP_Query( $args );
			$posts = $query->get_posts();

			wp_cache_set( $this->cache_key, $posts );
		}

		return $posts;
	}

	/**
	 * Setup actions.
	 *
	 * @since 2.0.0
	 */

	private function setup_actions() {
		add_action( 'send_headers', array( $this, 'router' ), 10, 2 );

		if ( is_admin() ) {
			add_action( 'admin_head', array( $this, 'admin_head' ) );
			add_action( 'admin_footer', array( $this, 'admin_footer' ) );
			add_action( 'edit_form_after_title', array( $this, 'post_submitbox_misc_actions' ) );
			add_action( 'save_post', array( $this, 'save_post' ) );
			add_action( 'wp_ajax_generate_simple_address', array( $this, 'wp_ajax_generate_simple_address' ) );
		}
	}

	/**
	 * Empty constructor.
	 *
	 * @since 2.0.0
	 */

	public function __construct() {
		// Empty, don't do anything here.
	}

	/**
	 * Get the instance of Simple address class.
	 *
	 * @since 2.0.0
	 *
	 * @return Simple_Address
	 */

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Simple_Address();
			self::$instance->setup_actions();
		}

		return self::$instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 2.0.0
	 */

	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'simple-address' ), '2.0.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 2.0.0
	 */

	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'simple-address' ), '2.0.0' );
	}

	/**
	 * Simple address router.
	 * Will validation the request path and check so it don't equals `wp-admin`, `wp-content` or `wp`.
	 * If any simple address exists in the database it will collect the post and try to redirect to the permalink if it not empty.
	 *
	 * @param $query
	 *
	 * @since 2.0.0
	 */

	public function router( $query ) {
		$request = $query->request;
		$req_uri = $_SERVER['REQUEST_URI'];

		// If the request uri ends with a slash it should
		if ( $req_uri[ strlen( $req_uri ) - 1 ] === '/' ) {
			return $query;
		}

		$paths_to_prevent = apply_filters( 'simple_address_prevent_paths', array( 'wp-admin', 'wp-content', 'wp' ) );

		// If the request don't match with the regex or match 'wp-admin' or 'wp-content' should we not proceeed with the redirect.
		if ( ! preg_match( '/^[a-zA-Z0-9\-\_]+$/', $request ) && in_array( $request, $paths_to_prevent ) ) {
			return $query;
		}

		$posts = $this->get_posts( $request );
		$post  = array_shift( $posts );

		// Don't allow empty post.
		if ( empty( $post ) ) {
			return $query;
		}

		$url = get_permalink( $post->ID );

		// If the url is false or empty we should not proceed with the redirect.
		if ( $url === false || empty( $url ) ) {
			return $query;
		}

		// Let's redirect baby!
		header( 'HTTP/1.1 301 Moved Permanently' );
		header( 'Location: ' . $url );
		exit;
	}

	/**
	 * Output css in admin head.
	 *
	 * @since 2.0.0
	 */

	public function admin_head() {
		?>
		<style type="text/css">
			#shortlink + a {
				display: none;
			}

			.simple-address {
				padding: 6px 0px 0px 10px;
			}

			.simple-address .hide {
				display: none;
			}

			.simple-address strong {
				color: #666;
			}

			.simple-address-view {
				line-height: 24px;
				min-height: 25px;
				margin-top: 5px;
				padding: 0 10px;
				color: #666;
			}

			.simple-address-view-edit input[type="text"] {
				font-size: 13px;
				height: 22px;
				margin: 0px 0px 0px -3px;
				width: 16em;
			}

			.simple-address-view-show {
				margin-left: -3px;
			}

			.simple-address-view-show span {
				background: #FFFBCC;
			}

			.simple-address-button-cancel {
				font-size: 11px;
			}
		</style>
	<?php
	}

	/**
	 * Output JavaScript in admin footer.
	 *
	 * @since 2.0.0
	 */

	public function admin_footer() {
		?>
		<script type="text/javascript">
			(function ($) {

				/**
				 *  Change view to edit view when a user hits edit button.
				 */

				$('body').on('click', '.simple-address-button-edit', function (e) {
					e.preventDefault();

					$('.simple-address-view-show').hide();
					$('.simple-address-view-edit').show().find('input').focus();
				});

				/**
				 * Update the Simple address when a user hits ok button.
				 */

				$('body').on('click', '.simple-address-button-ok', function (e) {
					e.preventDefault();

					var $showView = $('.simple-address-view-show'),
					    $editView = $('.simple-address-view-edit'),
					    data = {
						    action: 'generate_simple_address',
						    value: $editView.find('input').val(),
						    post_id: $('#post_ID').val()
					    };

					$.post(window.ajaxurl, data, function (res) {
						res = $.parseJSON(res);

						if (typeof res !== 'object' || res.value === undefined || !res.value.length) {
							return;
						}

						$showView.find('span').text(res.value);

						$editView.hide();
						$showView.show();
					});

				});

				/**
				 * Cancel the edit view and show the show view.
				 */

				$('body').on('click', '.simple-address-button-cancel', function (e) {
					e.preventDefault();

					$('.simple-address-view-show').show();
					$('.simple-address-view-edit').hide();
				});

			})(window.jQuery);
		</script>
	<?php
	}

	/**
	 * Render the Simple address input field in the post submitbox.
	 *
	 * @since 2.0.0
	 */

	public function post_submitbox_misc_actions() {
		global $post;
		$value       = $this->get_simple_address( $post->ID );
		$home_url    = get_home_url();
		$home_url    = ( $home_url[ strlen( $home_url ) - 1 ] == '/' ? $home_url : $home_url . '/' );
		$empty_value = empty( $value );

		if ( empty( $post->post_title ) ) {
			return;
		}

		?>
		<div class="simple-address">

			<strong><?php _e( 'Simple address', 'simple-address' ); ?>:</strong>

			<span class="simple-address-view">
				<?php echo $home_url; ?><span
					class="simple-address-view-edit <?php echo $empty_value ? '' : 'hide'; ?>">
					<input type="text" name="simple_address_field" value="<?php echo esc_attr( $value ); ?>"/>
					<a class="button button-small simple-address-button-ok"><?php _e( 'OK' ); ?></a>
					<a href="#" class="simple-address-button-cancel"><?php _e( 'Cancel' ); ?></a>
				</span>
				<span
					class="simple-address-view-show <?php echo $empty_value ? 'hide' : ''; ?>"><span><?php echo $value; ?></span>
				<a class="button button-small simple-address-button-edit <?php echo $empty_value ? 'hide' : ''; ?>"><?php _e( 'Edit' ); ?></a></span>
			</span>

			<?php wp_nonce_field( basename( __FILE__ ), 'simple_address_box_nonce' ); ?>
		</div>
	<?php
	}

	/**
	 * Save the simple address on the post if it exsists.
	 *
	 * @param $post_id
	 *
	 * @since 2.0.0
	 */

	public function save_post( $post_id ) {
		// Check if our nonce is set.
		if ( ! isset( $_POST['simple_address_box_nonce'] ) ) {
			return $post_id;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['simple_address_box_nonce'], basename( __FILE__ ) ) ) {
			return $post_id;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check the user's permissions.
		if ( ! current_user_can( ( $_POST['post_type'] == 'page' ? 'edit_page' : 'edit_post' ), $post_id ) ) {
			return $post_id;
		}

		$value = $_POST['simple_address_field'];

		if ( empty( $value ) ) {
			return $post_id;
		}

		$value      = $this->generate_simple_address( $value, $post_id );
		$meta_value = $this->get_simple_address( $post_id );

		wp_cache_delete( $this->cache_key );

		if ( is_null( $meta_value ) ) {
			// Add post meta key and value.
			add_post_meta( $post_id, $this->meta_key, $value, true );
		} else if ( ! is_null( $meta_value ) && ! is_null( $value ) ) {
			// Update post meta key and value.
			update_post_meta( $post_id, $this->meta_key, $value );
		} else {
			// Delete post meta row.
			delete_post_meta( $post_id, $this->meta_key );
		}
	}

	/**
	 * Generate simple address
	 *
	 * @param string $value
	 * @param int $post_id
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */

	public function generate_simple_address( $value, $post_id ) {
		$value        = sanitize_title( $value );
		$posts        = $this->get_posts( $value );
		$is_permalink = ! is_null( $this->find_post( $value ) );
		$count        = count( $posts ) === 0 ? 1 : count( $posts );
		$exists       = ! empty( $posts ) || $is_permalink;
		$temp_value   = '';

		// Don't update on the same post.
		if ( count( $posts ) === 1 && $posts[0]->ID === intval( $post_id ) ) {
			$exists = false || $is_permalink;
		}

		// Add "-X" where X is the final number to the value so it don't
		// save the same value on posts.
		while ( $exists ) {

			$temp_value = $value . '-' . $count;

			$posts = $this->get_posts( $temp_value );

			if ( empty( $posts ) || is_null( $this->find_post( $temp_value ) ) ) {
				$value = $temp_value;
				break;
			}

			$count ++;
		}

		return $value;
	}

	public function wp_ajax_generate_simple_address() {
		$value   = isset( $_POST['value'] ) ? $_POST['value'] : '';
		$post_id = isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0;

		if ( ! empty( $value ) ) {
			$value = $this->generate_simple_address( $value, $post_id );
		}

		echo json_encode( array(
			'value' => $value
		) );

		exit;
	}

	/**
	 * Get Simple address by post id.
	 *
	 * @param $post_id
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */

	public function get_simple_address( $post_id ) {
		return get_post_meta( $post_id, $this->meta_key, true );
	}
}

/**
 * Get Simple address instance.
 *
 * @since 2.0.0
 *
 * @return Simple_Address
 */

function simple_address() {
	return Simple_Address::instance();
}

/**
 * Get Simple address from a post.
 *
 * @param $post_id
 *
 * @since 2.0.0
 *
 * @return string|null
 */

function get_simple_address( $post_id ) {
	$simple_address = simple_address();

	return $simple_address->get_simple_address( $post_id );
}

$GLOBALS['simple_address'] = simple_address();