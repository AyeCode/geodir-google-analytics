<?php
/**
 * GeoDirectory Google Analytics
 *
 * @package           GeoDir_Google_Analytics
 * @author            AyeCode Ltd
 * @copyright         2019 AyeCode Ltd
 * @license           GPLv3
 *
 * @wordpress-plugin
 * Plugin Name:       GeoDirectory Google Analytics
 * Plugin URI:        https://wpgeodirectory.com/downloads/google-analytics/
 * Description:       Allows Google Analytics tracking for the listings.
 * Version:           2.3
 * Requires at least: 4.9
 * Requires PHP:      5.6
 * Author:            AyeCode Ltd
 * Author URI:        https://ayecode.io
 * License:           GPLv3
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       geodir-ga
 * Domain Path:       /languages
 * Update URL:        https://github.com/AyeCode/geodir-google-analytics/
 * Update ID:         588338
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( !defined( 'GEODIR_GA_VERSION' ) ) {
	define( 'GEODIR_GA_VERSION', '2.3' );
}

if ( ! defined( 'GEODIR_GA_MIN_CORE' ) ) {
	define( 'GEODIR_GA_MIN_CORE', '2.2' );
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function geodir_google_analytics() {
    global $geodir_ga;

	if ( !defined( 'GEODIR_GA_PLUGIN_FILE' ) ) {
		define( 'GEODIR_GA_PLUGIN_FILE', __FILE__ );
	}

	// Min core version check
	if ( ! function_exists( 'geodir_min_version_check' ) || ! geodir_min_version_check( 'Google Analytics', GEODIR_GA_MIN_CORE ) ) {
		return '';
	}

	/**
	 * The core plugin class that is used to define internationalization,
	 * dashboard-specific hooks, and public-facing site hooks.
	 */
	require_once ( plugin_dir_path( GEODIR_GA_PLUGIN_FILE ) . 'includes/class-geodir-google-analytics.php' );

    $geodir_ga = GeoDir_Google_Analytics::instance();
}
add_action( 'geodirectory_loaded', 'geodir_google_analytics' );
