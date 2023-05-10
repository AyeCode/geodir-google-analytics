<?php
/**
 * GeoDirectory Google Analytics Uninstall
 *
 * Uninstalling Google Analytics deletes analytics options.
 *
 * @author      AyeCode Ltd
 * @package     GeoDir_Google_Analytics
 * @version     1.0.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb, $plugin_prefix;

$geodir_settings = get_option( 'geodir_settings' );

if ( ( ! empty( $geodir_settings ) && ( ! empty( $geodir_settings['admin_uninstall'] ) || ! empty( $geodir_settings['uninstall_geodir_google_analytics'] ) ) ) || ( defined( 'GEODIR_UNINSTALL_GOOGLE_ANALYTICS' ) && true === GEODIR_UNINSTALL_GOOGLE_ANALYTICS ) ) {
	if ( empty( $plugin_prefix ) ) {
		$plugin_prefix = $wpdb->prefix . 'geodir_';
	}

	$package_meta_table = defined( 'GEODIR_PRICING_PACKAGE_META_TABLE' ) ? GEODIR_PRICING_PACKAGE_META_TABLE : $plugin_prefix . 'pricemeta';

	if ( ! empty( $geodir_settings ) ) {
		$save_settings = $geodir_settings;

		$remove_options = array(
			'ga_account_id',
			'ga_add_tracking_code',
			'ga_anonymize_ip',
			'ga_auth_code',
			'ga_auto_refresh',
			'ga_auth_token',
			'ga_auth_date',
			'ga_client_id',
			'ga_client_secret',
			'gd_ga_access_token',
			'gd_ga_refresh_token',
			'ga_refresh_time',
			'ga_stats',
			'ga_tracking_code',
			'ga_uids',
			'ga_properties',
			'ga_data_stream',
			'ga_measurement_id',
			'ga_data_streams',
			'ga_profile_view',
			'geodir_disable_google_analytics_section',
			'uninstall_geodir_google_analytics',
		);

		foreach ( $remove_options as $option ) {
			if ( isset( $save_settings[ $option ] ) ) {
				unset( $save_settings[ $option ] );
			}
		}

		// Update options.
		update_option( 'geodir_settings', $save_settings );
	}

	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$package_meta_table}'" ) ) {
		$wpdb->query( "DELETE FROM {$package_meta_table} WHERE meta_key = 'google_analytics'" );
	}

	// Delete core options
	delete_option( 'geodir_ga_version' );
	delete_option( 'geodir_ga_db_version' );
	delete_option( 'widget_gd_google_analytics' );
	
	// Clear any cached data that has been removed.
	wp_cache_flush();
}