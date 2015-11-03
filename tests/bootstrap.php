<?php

// Load Composer autoload.
require __DIR__ . '/../vendor/autoload.php';

// Load plugin.
WP_Test_Suite::load_plugins( [
	__DIR__ . '/../short-url.php'
] );

// Run the WordPress test suite.
WP_Test_Suite::run();
