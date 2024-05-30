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
			'ga_callback' => false,
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
	
	public static function ga_callback() {
		if ( ! empty( $_REQUEST['code'] ) && current_user_can( 'manage_options' ) ) {
			$oAuthURL = "https://www.googleapis.com/oauth2/v3/token?";
			$code = "code=" . sanitize_text_field( $_REQUEST['code'] );
			$grant_type = "&grant_type=authorization_code";
			$redirect_uri = "&redirect_uri=" . admin_url( 'admin-ajax.php' ) . "?action=geodir_ga_callback";
			$client_id = "&client_id=" . geodir_get_option( 'ga_client_id' );
			$client_secret = "&client_secret=" . geodir_get_option( 'ga_client_secret' );

			$auth_url = $oAuthURL . $code . $redirect_uri .  $grant_type . $client_id . $client_secret;

			$response = wp_remote_post( $auth_url, array( 'timeout' => 15 ) );

			$error_msg =  __('Something went wrong','geodirectory');
			if ( ! empty( $response['response']['code'] ) && $response['response']['code'] == 200 ) {
				$parts = json_decode( $response['body'] );
				if ( ! isset( $parts->access_token ) ) {
					echo $error_msg . " - #1";
					exit;
				} else {
					geodir_update_option( 'gd_ga_access_token', $parts->access_token );
					geodir_update_option( 'gd_ga_refresh_token', $parts->refresh_token );
					?><script>window.close();</script><?php
				}
			} elseif ( ! empty( $response['response']['code'] ) ) {
				$parts = json_decode( $response['body'] );

				if ( isset( $parts->error ) ) {
					echo $parts->error . ": " . $parts->error_description;
					exit;
				} else {
					echo $error_msg . " - #2";
					exit;
				}
			} else {
				echo $error_msg . " - #3";
				exit;
			}
		}
		geodir_die();
	}
}