<?php

/**
 * Plugin Name: Short url
 * Description: Short url to the permalink, like Simple Address in EPiServer.
 * Author: Fredrik Forsmo
 * Author URI: https://frozzare.com/
 * Version: 2.0.4
 * Plugin URI: https://github.com/frozzare/short-url
 */

// Load short url files.
require_once __DIR__ . '/src/class-short-url.php';
require_once __DIR__ . '/src/functions.php';

/**
 * Load Short Url plugin.
 *
 * @return Short_Url
 */
add_action( 'plugins_loaded', function () {
	return Short_Url::instance();
} );
