<?php

final class Short_Url {

	/**
	 * The instance of Short url.
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * The cache key that is used by short url.
	 *
	 * @var string
	 * @since 2.0.0
	 */
	private $cache_key = '_short_url_query';

	/**
	 * The post meta key that is used by short url.
	 *
	 * @var string
	 * @since 2.0.0
	 */
	private $meta_key = '_short_url';

	/**
	 * Find post by post name.
	 *
	 * @param $post_name
	 *
	 * @since 2.0.0
	 * @access private
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
	 * Get all posts with the given short url.
	 *
	 * @param string $short_url
	 * @param bool $no_cache
	 *
	 * @since 2.0.0
	 * @access private
	 *
	 * @return array
	 */
	private function get_posts( $short_url, $no_cache = false ) {
		$posts = wp_cache_get( sprintf( '%s:%s', $this->cache_key, $short_url ) );

		if ( empty( $posts ) || $no_cache ) {
			$meta_key = $this->meta_key;
			$args     = [
				'post_type'              => 'any',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => [
					[
						'key'     => $meta_key,
						'value'   => $short_url,
						'compare' => '='
					]
				]
			];

			$query = new WP_Query( $args );
			$posts = $query->get_posts();

			$posts = array_filter( $posts, function ( $post ) use ( $meta_key, $short_url ) {
				return get_post_meta( $post->ID, $meta_key, true ) === $short_url;
			} );

			wp_cache_set( sprintf( '%s:%s', $this->cache_key, $short_url ), $posts );
		}

		return $posts;
	}

	/**
	 * Load the right language files.
	 *
	 * @since 2.0.0
	 */
	private function load_language() {
		$domain = 'short-url';
		$path   = dirname( __FILE__ ) . '/languages/' . $domain . '-' . get_locale() . '.mo';
		load_textdomain( $domain, $path );
	}

	/**
	 * Setup actions.
	 *
	 * @since 2.0.0
	 */
	private function setup_actions() {
		add_action( 'send_headers', [$this, 'router'], 10, 2 );

		if ( is_admin() ) {
			add_action( 'admin_head', [$this, 'admin_head'] );
			add_action( 'admin_footer', [$this, 'admin_footer'] );
			add_action( 'post_submitbox_misc_actions', [$this, 'post_submitbox_misc_actions'] );
			add_action( 'save_post', [$this, 'save_post'] );
			add_action( 'wp_ajax_generate_short_url', [$this, 'wp_ajax_generate_short_url'] );
		}
	}

	/**
	 * Empty constructor.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		// Empty, don't do anything here.
	}

	/**
	 * Get the instance of short url class.
	 *
	 * @since 2.0.0
	 *
	 * @return short_url
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
			self::$instance->setup_actions();
			self::$instance->load_language();
		}

		return self::$instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 2.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'short-url' ), '2.0.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 2.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'short-url' ), '2.0.0' );
	}

	/**
	 * short url router.
	 * Will validation the request path and check so it don't equals `wp-admin`, `wp-content` or `wp`.
	 * If any short url exists in the database it will collect the post and try to redirect to the permalink if it not empty.
	 *
	 * @param $query
	 *
	 * @since 2.0.0
	 */
	public function router( $query ) {
		$request = strtolower( $query->request );
		$req_uri = $_SERVER['REQUEST_URI'];

		// If the request uri ends with a slash it should
		if ( $req_uri[ strlen( $req_uri ) - 1 ] === '/' ) {
			return $query;
		}

		$subdirectories = ['wp-admin', 'wp-content', 'wp', 'wordpress'];

		if ( function_exists( 'get_subdirectory_reserved_names' ) ) {
			$subdirectories = array_merge( $subdirectories, get_subdirectory_reserved_names() );
		}

		$paths_to_prevent = apply_filters( 'short_url_prevent_paths', $subdirectories );

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
		wp_safe_redirect( $url );
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
			.short-url-show-view a:first-child {
				color: #666;
			}

			.short-url-show-view p {
				word-break: break-all;
			}

			.short-url-show-view a span {
				background: #FFFBCC;
			}

			.short-url .hide {
				display: none;
			}

			.short-url-edit-view input[type="text"] {
				width: 82%;
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
				$('body').on('click', '.short-url-button-edit', function (e) {
					e.preventDefault();
					$(this).parent().hide();
					$('.short-url-edit-view').show();
				});

				/**
				 * Update the short url when a user hits ok button.
				 */
				$('body').on('click', '.short-url-button-ok', function (e) {
					e.preventDefault();

					var $input = $(this).prev();
					var $showView = $('.short-url-show-view');
					var $editView = $('.short-url-edit-view');
					var data = {
						    action: 'generate_short_url',
						    value: $editView.find('input').val(),
						    post_id: $('#post_ID').val()
					    };

					$.post(window.ajaxurl, data, function (res) {
						res = $.parseJSON(res);

						if (typeof res !== 'object' || res.value === undefined || !res.value.length) {
							return;
						}

						$('.short-url-edit-view').hide();

						if (res.value.length) {
							var $link = $showView.find('a:first-child');
							var homeUrl = $('#short-url-home-url').val();

							$link.attr('href', homeUrl + res.value);
							$link.find('span').text(res.value);
							$input.val(res.value);

							$showView.show();
						} else {
							$errorView.show();
						}
					});

				});

				/**
				 * Cancel the edit view and show the show view.
				 */
				$('body').on('click', '.short-url-button-cancel', function (e) {
					e.preventDefault();

					$('.short-url-view-show').show();
					$('.short-url-view-edit').hide();
				});

			})(window.jQuery);
		</script>
	<?php
	}

	/**
	 * Render the short url input field in the post submitbox.
	 *
	 * @since 2.0.0
	 */
	public function post_submitbox_misc_actions() {
		global $post;
		$value       = $this->get_short_url( $post->ID, true );
		$home_url    = get_home_url();
		$home_url    = ( $home_url[ strlen( $home_url ) - 1 ] == '/' ? $home_url : $home_url . '/' );
		$empty_value = empty( $value );

		if ( empty( $post->post_title ) ) {
			return;
		}

		?>

		<div class="misc-pub-section short-url">

			<label>
				<strong><?php _e( 'Short url', 'short-url' ); ?>:</strong>
			</label>

			<p class="short-url-error-view hide">
				<?php _e( 'No short url exists', 'short-url' ); ?>
				<a class="button short-url-edit-button">Edit</a>
			</p>

			<p class="short-url-show-view <?php echo empty( $value ) ? 'hide' : ''; ?>">
				<a href="<?php echo $home_url . $value; ?>">
					<?php echo $home_url; ?><span><?php echo $value; ?></span></a>
				<a class="button short-url-button-edit">Edit</a>
			</p>

			<div class="short-url-edit-view <?php echo empty( $value ) ? '' : 'hide'; ?>">
				<p>
					<input type="text" id="short_url_field" name="short_url_field"
					       value="<?php echo esc_attr( $value ); ?>"/>

					<a href="#" class="button short-url-button-ok"><?php _e( 'OK' ); ?></a>

				</p>

				<p>
					<i><?php echo __( 'This will not override any existing permalinks for posts, pages or custom post types.', 'simple_address' ); ?></i>
				</p>
			</div>

			<input type="hidden" id="short-url-home-url" value="<?php echo $home_url; ?>"/>
			<?php wp_nonce_field( 'short_url_save_data', 'short_url_meta_nonce' ); ?>
		</div>
	<?php
	}

	/**
	 * Save the short url on the post if it exsists.
	 *
	 * @param $post_id
	 *
	 * @since 2.0.0
	 */
	public function save_post( $post_id ) {
		// Check if our nonce is set.
		if ( ! isset( $_POST['short_url_meta_nonce'] ) ) {
			return $post_id;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['short_url_meta_nonce'], 'short_url_save_data' ) ) {
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

		$value = $_POST['short_url_field'];

		if ( empty( $value ) ) {
			return $post_id;
		}

		$value      = $this->generate_short_url( $value, $post_id );
		$meta_value = $this->get_short_url( $post_id, true );

		// Delete old cache if any.
		wp_cache_delete( sprintf( '%s:%s', $this->cache_key, $meta_value ) );

		// Delete new cache if any.
		wp_cache_delete( sprintf( '%s:%s', $this->cache_key, $value ) );

		if ( is_null( $meta_value ) ) {
			add_post_meta( $post_id, $this->meta_key, $value, true );
		} else if ( ! is_null( $meta_value ) && ! is_null( $value ) ) {
			update_post_meta( $post_id, $this->meta_key, $value );
		} else {
			delete_post_meta( $post_id, $this->meta_key );
		}
	}

	/**
	 * Generate short url
	 *
	 * @param string $value
	 * @param int $post_id
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function generate_short_url( $value, $post_id ) {
		$value = sanitize_title( $value );

		// If `url_to_postid` returns zero we can be sure that a url with the
		// value don't exists and just return the value.
		if ( url_to_postid( home_url( $value ) ) === 0 ) {
			return $value;
		}

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

	/**
	 * Generate short url via wp ajax.
	 *
	 * @since 2.0.0
	 */
	public function wp_ajax_generate_short_url() {
		$value   = isset( $_POST['value'] ) ? $_POST['value'] : '';
		$post_id = isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0;

		if ( ! empty( $value ) ) {
			$value = $this->generate_short_url( $value, $post_id );
		}

		echo json_encode( [
			'value' => $value
		] );

		exit;
	}

	/**
	 * Get short url by post id.
	 *
	 * @param int $post_id
	 * @param bool $only_short_url Default false
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public function get_short_url( $post_id, $only_short_url = false ) {
		$short_url = get_post_meta( $post_id, $this->meta_key, true );

		if ( $only_short_url ) {
			return $short_url;
		}

		$home_url = get_home_url();
		$home_url = ( $home_url[ strlen( $home_url ) - 1 ] == '/' ? $home_url : $home_url . '/' );

		return $home_url . $short_url;
	}
}
