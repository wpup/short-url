<?php

/**
 * Plugin Name: Simple address
 * Description:
 * Author: Fredrik Forsmo
 * Author URI: http://forsmo.me/
 * Version: 1.0
 * Plugin URI: https://github.com/frozzare/simple-address
 */
 
if (!defined('SIMPLE_ADDRESS_DB_VERSION')) define('SIMPLE_ADDRESS_DB_VERSION', '1.0');

/**
 * Install the Simple address table.
 */

function simple_address_install () {
  global $wpdb;
  $table_name = $wpdb->prefix . 'simple_address';
  $sql = "CREATE TABLE $table_name (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    post_id bigint(20) NOT NULL,
    simple_address varchar(200) NOT NULL,
    UNIQUE KEY id (id)
  );";
  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);
  add_option('simple_address_db_version', SIMPLE_ADDRESS_DB_VERSION);
}

function simple_address_uninstall () {
  global $wpdb;
  delete_option('simple_address_db_version');
  $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'simple_address');
}

register_activation_hook(__FILE__, 'simple_address_install');
register_uninstall_hook(__FILE__, 'simple_address_uninstall');

/**
 * Add simple address meta box.
 */

function simple_address_meta_box () {
  $post_types = array('post', 'page');
  foreach ($post_types as $post_type) {
    add_meta_box(
      'simple_address',
      __('Simple address', 'simple_address'),
      'simple_address_box',
      $post_type,
      'side',
      ''
    );
  }
}

add_action( 'add_meta_boxes', 'simple_address_meta_box' );

/**
 * Render the meta box for simple address.
 *
 * @param object $post
 */

function simple_address_box ($post) {
  global $wpdb;
  wp_nonce_field('simple_address_box', 'simple_address_box_nonce');
  $value = $wpdb->get_results('SELECT simple_address FROM ' . $wpdb->prefix . 'simple_address WHERE post_id=' . $post->ID);
  $value = !empty($value) ? $value[0]->simple_address : '';
  ?>
  <?php
    if (!empty($value)) {
      $url = get_site_url();
      $link = ($url[strlen($url) - 1] == '/' ? $url : $url . '/') . $value;
      echo '<p>' . __('Current url', 'simple_address') . ': <br /><a href=' . $link . ' target="_blank">' . $link .'</a></p>';
    }
  ?>
  <input type="text" id="simple_address_field" name="simple_address_field" value="<?php echo esc_attr($value); ?>" size="25" />
  <p><i><?php echo __('This will not override any existing permalinks for posts or pages.', 'simple_address'); ?></i></p>
  <?php
}

/**
 * Save the simple address.
 *
 * @param int $post_id
 */

function simple_address_save_postdata ($post_id) {
  // Check if our nonce is set.
  if ( ! isset( $_POST['simple_address_box_nonce'] ) )
    return $post_id;

  $nonce = $_POST['simple_address_box_nonce'];

  // Verify that the nonce is valid.
  if (!wp_verify_nonce($nonce, 'simple_address_box'))
      return $post_id;

  // If this is an autosave, our form has not been submitted, so we don't want to do anything.
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
      return $post_id;
  
  // Check the user's permissions.
  if ('page' == $_POST['post_type']) {

    if (!current_user_can('edit_page', $post_id))
      return $post_id;

  } else {

    if (!current_user_can('edit_post', $post_id))
      return $post_id;
  }

  /* OK, its safe for us to save the data now. */
  global $wpdb;
  
  // Sanitize user input.
  $value = sanitize_text_field($_POST['simple_address_field']);
  
  // Check for existing simple address.
  $dbvalue = get_simple_address_redirect($value);
  
  if (!empty($dbvalue) && is_null(get_page_by_title($value)))
    return $post_id;
    
  // Validate the simple address before inserting it.
  if (!preg_match('/^[a-zA-Z0-9\-\_]+$/', $value))
    return $post_id;
  
  // Update the meta field in the database.
  $wpdb->insert($wpdb->prefix . 'simple_address', array(
    'post_id' => $post_id,
    'simple_address' => $value
  ));
  
  wp_nonce_field( basename( __FILE__ ), 'example_nonce' );
}

add_action('save_post', 'simple_address_save_postdata');

/**
 * Get the post id from the simple address table.
 *
 * @param string $address Simple address string
 *
 * @return int|string Post id or empty string
 */

function get_simple_address_redirect ($address) {
  global $wpdb;
  $value = $wpdb->get_results('SELECT post_id FROM ' . $wpdb->prefix . 'simple_address WHERE simple_address="' . $address . '"');
  return !empty($value) ? $value[0]->post_id : '';
}

/**
 * Simple address router. Will validation the request path and check so it don't equals `wp-admin` or `wp-content`.
 * If any simple address exists in the database it will collect the id and try to redirect to the permalink if it not empty.
 *
 * @param object $query
 *
 * @return object
 */

function simple_address_router ($query) {
  $request = $query->request;
  if (preg_match('/^[a-zA-Z0-9\-\_]+$/', $request) && !in_array($request, array('wp-admin', 'wp-content'))) {
    $id = get_simple_address_redirect($request);
    if (!empty($id)) {
      $url = get_permalink($id);
      if (!empty($url)) {
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $url);
        exit;
      }
      return $query;
    }
    return $query;
  }
  return $query;
}

add_action('send_headers', 'simple_address_router', 10, 2);