<?php
namespace Zao\ZMLPAT;

/**
 * Plugin Name: MultilingualPress Advanced Translations
 * Plugin URI:  https://zao.is
 * Description: Infuses the Google Translated version of the content when copying source data to a new page.
 * Version:     0.1.0
 * Author:      Zao
 * Author URI:  https://zao.is
 * Text Domain: zao-mlp-at
 * Domain Path: /languages
 * License:     GPL-2.0+
 */

/**
 * Copyright (c) 2017 Zao (email : justin@zao.is)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Useful global constants
define( 'ZMLPAT_VERSION',  '0.1.0' );
define( 'ZMLPAT_BASENAME', plugin_basename( __FILE__ ) );
define( 'ZMLPAT_URL',      plugin_dir_url( __FILE__ ) );
define( 'ZMLPAT_PATH',     dirname( __FILE__ ) . '/' );

/**
 * Autoloads files with classes when needed
 *
 * @since  3.0.0
 * @param  string $class_name Name of the class being requested.
 * @return void
 */
function autoload( $class_name ) {

	// project-specific namespace prefix
	$prefix = __NAMESPACE__ . '\\';

	// does the class use the namespace prefix?
	$len = strlen( $prefix );

	if ( 0 !== strncmp( $prefix, $class_name, $len ) ) {
		// no, move to the next registered autoloader
		return;
	}

	// get the relative class name
	$relative_class = substr( $class_name, $len );

	/*
	 * replace the namespace prefix with the base directory, replace namespace
	 * separators with directory separators in the relative class name, replace
	 * underscores with dashes, and append with .php
	 */
	$path = strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative_class ) );
	$file = ZMLPAT_PATH . $path . '.php';

	// if the file exists, require it
	if ( file_exists( $file ) ) {
		require $file;
	}
}

/**
 * Default setup routine
 *
 * @uses add_action()
 * @uses do_action()
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	spl_autoload_register( $n( 'autoload' ), false );

	require_once ZMLPAT_PATH . 'vendor/autoload.php';

	add_action( 'init', $n( 'i18n' ) );
	add_action( 'mlp_and_wp_loaded', $n( 'init' ) );

	do_action( 'zmlpat_loaded' );
}

/**
 * Registers the default textdomain.
 *
 * @uses apply_filters()
 * @uses get_locale()
 * @uses load_textdomain()
 * @uses load_plugin_textdomain()
 * @uses plugin_basename()
 *
 * @return void
 */
function i18n() {
	$locale = apply_filters( 'plugin_locale', get_locale(), 'zmlpat' );
	load_textdomain( 'zmlpat', WP_LANG_DIR . '/zmlpat/zmlpat-' . $locale . '.mo' );
	load_plugin_textdomain( 'zmlpat', false, plugin_basename( ZMLPAT_PATH ) . '/languages/' );
}

/**
 * Initializes the plugin and fires an action other plugins can hook into.
 *
 * @uses do_action()
 *
 * @return void
 */
function init() {
	if ( ! defined( 'ZMLPAT_DEBUG' ) ) {
		define( 'ZMLPAT_DEBUG', false );
	}

	$zmlpat = new MLP\Translate;

	add_action( 'zmlpat_init', array( $zmlpat, 'setup' ) );

	do_action( 'zmlpat_init' );
}

setup();
