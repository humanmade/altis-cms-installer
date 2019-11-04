<?php
/**
 * Main config file for loading Altis.
 *
 * DO NOT EDIT THIS FILE.
 *
 * All configuration should be done either in your project's composer.json or `config/`
 * directory.
 */

// Provide a reference to the app root directory early.
define( 'Altis\\ROOT_DIR', __DIR__ );

// Load the plugin API (like add_action etc) early, so everything loaded
// via the Composer autoloaders can using actions.
require_once __DIR__ . '/wordpress/wp-includes/plugin.php';

// Load the whole autoloader very early, this will also include
// all `autoload.files` from all modules.
require_once __DIR__ . '/vendor/autoload.php';

// Load all modules.
require_once __DIR__ . '/vendor/modules.php';

do_action( 'altis.loaded_autoloader' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wordpress/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', __DIR__ . '/content' );
}

if ( ! defined( 'WP_CONTENT_URL' ) ) {
	$protocol = ! empty( $_SERVER['HTTPS'] ) ? 'https' : 'http';
	define( 'WP_CONTENT_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . '/content' );
}

if ( ! defined( 'WP_INSTALLING' ) || ! WP_INSTALLING ) {
	// Multisite is always enabled, unless some spooky
	// early loading code tried to change that of course.
	if ( ! defined( 'MULTISITE' ) ) {
		define( 'MULTISITE', true );
	}
}

if ( ! isset( $table_prefix ) ) {
	$table_prefix = 'wp_';
}

/*
 * DB constants are expected to be provided by other modules, as they are
 * environment specific.
 */
$required_constants = [
	'DB_HOST',
	'DB_NAME',
	'DB_USER',
	'DB_PASSWORD',
];

foreach ( $required_constants as $constant ) {
	if ( ! defined( $constant ) ) {
		die( "$constant constant is not defined." );
	}
}

if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	require_once ABSPATH . 'wp-settings.php';
}
