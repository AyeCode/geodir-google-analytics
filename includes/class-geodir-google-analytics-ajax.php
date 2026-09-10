<?php
/**
 * Google Analytics AJAX class.
 *
 * Google Analytics AJAX Event Handler.
 *
 * @since 2.0.0
 * @package Geodir_Google_Analytics
 * @author AyeCode Ltd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GeoDir_Google_Analytics_AJAX class.
 */
class GeoDir_Google_Analytics_AJAX {

	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		// geodirectory_EVENT => nopriv
		$ajax_events = array(
			'ga_stats' => true,
			'ga_deauthorize' => false,
			'ga_diagnostics' => false,
		);

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_geodir_' . $ajax_event, array( __CLASS__, $ajax_event ) );

			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_geodir_' . $ajax_event, array( __CLASS__, $ajax_event ) );

				// GeoDir AJAX can be used for frontend ajax requests.
				add_action( 'geodir_google_analytics_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	public static function ga_stats() {
		$referer = wp_get_referer();
		if ( ! $referer && ! empty( $_REQUEST['ga_post'] ) ) {
			$referer = get_permalink( (int) $_REQUEST['ga_post'] );
		}

		$page = isset( $_REQUEST['ga_page'] ) ? urldecode( $_REQUEST['ga_page'] ) : '';
		$page_token = isset( $_REQUEST['pt'] ) ? sanitize_text_field( $_REQUEST['pt'] ) : '';

		if (
			$referer
			&& $page
			&& $page_token
			&& $referer !== wp_unslash( $_SERVER['REQUEST_URI'] )
			&& $referer !== home_url() . wp_unslash( $_SERVER['REQUEST_URI'] )
			&& ( ( untrailingslashit( home_url() ) . $page ) == $referer || ( strpos( $referer, home_url() ) === 0 && strpos( $referer, $page ) > 0 ) )
			&& geodir_ga_validate_page_access_token( $page_token, $page )
		) {
			$start = isset( $_REQUEST['ga_start'] ) ? sanitize_file_name( $_REQUEST['ga_start'] ) : '';
			$end = isset( $_REQUEST['ga_end'] ) ? sanitize_file_name( $_REQUEST['ga_end'] ) : '';

			try {
				geodir_ga_get_analytics( $page, $start, $end );
			} catch ( Exception $e ) {
				echo json_encode( array() );
				geodir_error_log( wp_sprintf( __( 'GD Google Analytics API Error(%s) : %s', 'geodir-ga' ), $e->getCode(), $e->getMessage() ) );
			}
		} else {
			geodir_error_log( __( 'GD Google Analytics API Error : empty stats', 'geodir-ga' ) );
			echo json_encode( array() );
		}

		geodir_die();
	}

	/**
	 * Deauthorize Google Analytics
	 */
	public static function ga_deauthorize(){
		// security
		check_ajax_referer( 'gd_ga_deauthorize', '_wpnonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}

		geodir_update_option( 'ga_auth_token', '' );
		geodir_update_option( 'ga_auth_date', '' );
		geodir_update_option( 'ga_auth_code', '' );
		geodir_update_option( 'ga_properties', '' );
		geodir_update_option( 'ga_account_id', '' );
		geodir_update_option( 'ga_measurement_id', '' );
		geodir_update_option( 'ga_data_streams', '' );
		geodir_update_option( 'ga_data_stream', '' );

		echo admin_url( 'admin.php?page=gd-settings&tab=analytics' );

		geodir_die();
	}

	/**
	 * Run and return the Google Analytics connection diagnostics.
	 *
	 * @since 2.4.0
	 */
	public static function ga_diagnostics() {
		check_ajax_referer( 'gd_ga_diagnostics', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}

		if ( ! function_exists( 'geodir_ga_run_diagnostics' ) ) {
			require_once( GEODIR_GA_PLUGIN_DIR . 'includes/admin/admin-functions.php' );
		}

		echo geodir_ga_render_diagnostics( geodir_ga_run_diagnostics() );

		geodir_die();
	}
}
