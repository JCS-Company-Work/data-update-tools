<?php
/**
 * Plugin Name: PT Hub Data Update Tools
 * Description: Data update tools for PT Hub.
 * Author: Porcelain Tiles
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DUPT_VERSION', '1.0.0' );
define( 'DUPT_PATH', plugin_dir_path( __FILE__ ) );
define( 'DUPT_URL', plugin_dir_url( __FILE__ ) );

// Composer autoload
if ( file_exists( DUPT_PATH . 'vendor/autoload.php' ) ) {
    require_once DUPT_PATH . 'vendor/autoload.php';
}

// Initilise the plugin
use DataUpdateTools\Plugin;

( new Plugin() )->boot();