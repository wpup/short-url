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

/**
 * Uninstall Simple adress table.
 */

function simple_address_uninstall () {
  global $wpdb;
  delete_option('simple_address_db_version');
  $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'simple_address');
}

register_activation_hook(__FILE__, 'simple_address_install');
register_uninstall_hook(__FILE__, 'simple_address_uninstall');

/**
 * Add Simple address meta box.
 */

function simple_address_meta_box () {
  // Get the current post types where Simple address will be visible.
  $simple_address_post_types = get_simple_address_post_types();
  foreach ($simple_address_post_types as $post_type) {
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
 * Render the meta box for Simple address.
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
  echo wp_nonce_field(basename(__FILE__), 'simple_address_box_nonce', true, false);
}

/**
 * Save the Simple address.
 *
 * @param int $post_id
 */

function simple_address_save_postdata ($post_id) {
  // Check if our nonce is set.
  if (!isset($_POST['simple_address_box_nonce'])) {
    return $post_id;
  }

  // Verify that the nonce is valid.
  if (!wp_verify_nonce($_POST['simple_address_box_nonce'], basename(__FILE__))) {
    return $post_id;
  }

  // If this is an autosave, our form has not been submitted, so we don't want to do anything.
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return $post_id;
  }

  // Check the user's permissions.
  if (!current_user_can(($_POST['post_type'] == 'page' ? 'edit_page' : 'edit_post'), $post_id)) {
    return $post_id;
  }

  // OK, its safe for us to save the data now.
  global $wpdb;
  $table = $wpdb->prefix . 'simple_address';

  // If the value is empty we should delete to old if it's exists.
  if (empty($_POST['simple_address_field'])) {
    $wpdb->delete($table, array(
      'post_id' => $post_id
    ));
  } else {
    // Sanitize user input.
    $value = sanitize_text_field($_POST['simple_address_field']);

    // Check for existing simple address.
    $dbvalue = get_simple_address_by_address($value);

    // Fetch existing.
    if (is_null($dbvalue)) {
      $dbvalue = get_simple_address_by_post_id($post_id);
    }

    // Only add error message if the Simple address exists and don't is the current Simple address.
    if (!is_null($dbvalue) && intval($dbvalue->post_id) != $post_id) {
      if (!session_id()) session_start();
      $_SESSION['simple_address_error'] = '"' . $value . '" is used on <a href="' . get_permalink($dbvalue) . '">' . get_the_title($dbvalue) . '</a>';
      return $post_id;
    }

    // Validate the value before inserting it.
    if (!preg_match('/^[a-zA-Z0-9\-\_]+$/', $value)) {
      return $post_id;
    }

    // Update existing instead of inserting it again.
    if (!is_null($dbvalue) && intval($dbvalue->post_id) === $post_id) {
      $wpdb->update($table, array(
        'simple_address' => $value
      ), array(
        'post_id' => $post_id
      ));
    } else {
      $wpdb->insert($table, array(
        'post_id' => $post_id,
        'simple_address' => $value
      ));
    }
  }
}

add_action('save_post', 'simple_address_save_postdata');

/**
 * Get Simple address from the database by post id.
 *
 * @param int $post_id WordPress post id
 *
 * @return object|null
 */

function get_simple_address_by_post_id ($post_id) {
  global $wpdb;
  $value = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'simple_address WHERE post_id="' . $post_id . '"');
  return !empty($value) ? $value[0] : null;
}

/**
 * Get Simple address from the database by address.
 *
 * @param string $address Simple address string
 *
 * @return object|null
 */

function get_simple_address_by_address ($address) {
  global $wpdb;
  $value = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'simple_address WHERE simple_address="' . $address . '"');
  return !empty($value) ? $value[0] : null;
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
    $address = get_simple_address_by_address($request);
    if (!empty($address)) {
      $url = get_permalink($address->post_id);
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

/**
 * Show error message if we have a error not.
 */

function simple_address_error_notice () {
  if (isset($_SERVER['QUERY_STRING']) && preg_match('/message\=simple\-address/', $_SERVER['QUERY_STRING'])) {
    if (!session_id()) session_start();
    if (isset($_SESSION['simple_address_error'])) {
      echo '<div class="error">';
      echo '<p>' . __('Simple address error', 'simple_address') . ': ' . $_SESSION['simple_address_error'] . '</p>';
      echo '</div>';
      unset($_SESSION['simple_address_error']);
    }
  }
}

add_action('admin_notices', 'simple_address_error_notice');

/**
 * Check if we have a error and change the message query string value to "simple-address" so we can show the error.
 *
 * @param string $location
 *
 * @return string
 */

function simple_address_redirect_location ($location) {
  if (!session_start()) session_start();
  if (isset($_SESSION['simple_address_error'])) {
    $location = add_query_arg('message', 'simple-address', $location);
  }
  return $location;
}

add_filter('redirect_post_location','simple_address_redirect_location', 10, 2);

/**
 * Get array of post types where Simple address meta box will be visible.
 *
 * @return array
 */

function get_simple_address_post_types () {
  $value = get_option('simple_address_post_types');

  if (!is_array($value)) {
    $value = array();
  }

  // Add default post types if the value if empty.
  if (empty($value)) {
    $value = array('post', 'page');
  }

  return $value;
}

/**
 * Update Simple address post types array.
 *
 * @param array $post_types
 */

function update_simple_address_post_types ($post_types) {
  if (!is_array($post_types)) return;
  update_option('simple_address_post_types', $post_types);
}

/**
 * Simple address options page.
 */

function simple_address_options () {
  // Update where Simple address meta box will be visible.
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simple_address_action']) && $_POST['simple_address_action'] === 'update') {
    $post_types = $_POST['simple-address-post-types'];
    update_simple_address_post_types($post_types);
  }

  // Get the current post types where Simple address will be visible.
  $simple_address_post_types = get_simple_address_post_types();

  screen_icon();
  ?>
  <h2><?php echo __('Simple address', 'simple-address'); ?> <?php echo __('settings', 'simple-address'); ?></h2>
  <div class="wrap">
    <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
      <?php wp_nonce_field('update-options'); ?>
      <h3><?php echo __("Select where to show Simple address meta box", 'simple-address'); ?></h3>
      <table class="form-table">
        <tbody>
        <?php
        $post_types = get_post_types(array(
          'show_ui' => true
        ), 'objects');

        foreach ($post_types as $post_type) {
          $name = $post_type->name;
          if ($name === 'attachment') {
            // No support for media/attachment
            continue;
          } ?>
          <tr>
            <th scope="row">
              <?php echo $post_type->label; ?>
            </th>
            <td>
              <p>
                <?php $checked = (in_array($name, $simple_address_post_types)) ? ' checked="checked" ' : ''; ?>
                <input type="checkbox" <?php echo $checked; ?> name="simple-address-post-types[]" value="<?php echo $name; ?>" id="simple-address-show-<?php echo $name; ?>" />
              </p>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
      <input type="hidden" name="action" value="update" />
      <input type="hidden" name="simple_address_action" value="update" />
      <p class="submit">
        <input type="submit" class="button-primary" value="<?php echo __('Save Changes', 'simple-address'); ?>" />
      </p>
    </form>
  </div>
<?php
}

/**
 * Simple address menu, add options page.
 */

function simple_address_menu () {
  add_submenu_page('options-general.php', 'Simple address', 'Simple address', 'administrator', 'simple-address-options', 'simple_address_options');
}

add_action('admin_menu', 'simple_address_menu');