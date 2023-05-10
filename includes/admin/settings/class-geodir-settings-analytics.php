<?php
/**
 * GeoDirectory Analytics Settings
 *
 * @author   AyeCode
 * @category Admin
 * @package  GeoDir_Google_Analytics/Admin
 * @version  2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'GeoDir_Settings_Analytics', false ) ) :

	/**
	 * GD_Settings_Products.
	 */
	class GeoDir_Settings_Analytics extends GeoDir_Settings_Page {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id    = 'analytics';
			$this->label = __( 'Google Analytics', 'geodir-ga' );

			add_filter( 'geodir_settings_tabs_array', array( $this, 'add_settings_page' ), 23 );
			add_action( 'geodir_settings_' . $this->id, array( $this, 'output' ) );
//			add_action( 'geodir_sections_' . $this->id, array( $this, 'output_toggle_advanced' ) );

			add_action( 'geodir_settings_save_' . $this->id, array( $this, 'save' ) );
			add_action( 'geodir_sections_' . $this->id, array( $this, 'output_sections' ) );
		}

		/**
		 * Get sections.
		 *
		 * @return array
		 */
		public function get_sections() {
			$sections = array();

			return apply_filters( 'geodir_get_sections_' . $this->id, $sections );
		}

		/**
		 * Output the settings.
		 */
		public function output() {
			global $current_section;

			$settings = $this->get_settings( $current_section );

			GeoDir_Admin_Settings::output_fields( $settings );
		}

		/**
		 * Save settings.
		 */
		public function save() {
			global $current_section;

			$settings = $this->get_settings( $current_section );

			GeoDir_Admin_Settings::save_fields( $settings );
		}

		/**
		 * Get settings array.
		 *
		 * @return array
		 */
		public function get_settings( $current_section = '' ) {
			$accounts = self::get_property_options();
			$data_streams = ! empty( $accounts ) ? self::get_data_streams_options() : array();

			if ( ! empty( $data_streams ) && ! geodir_get_option( 'ga_measurement_id' ) ) {
				$_data_streams = array_keys( $data_streams );

				if ( ! empty( $_data_streams[0] ) ) {
					geodir_update_option( 'ga_measurement_id', $_data_streams[0] );
				}
			}

			$settings = apply_filters( 'geodir_google_analytics_settings', 
				array(
					array(
						'name' => __( 'Google Analytics Settings', 'geodir-ga' ),
						'type' => 'sectionstart', 
						'id' => 'google_analytic_settings',
						'desc' => aui()->alert( array( 'type'=> 'info', 'content'=> __( '<b>Google Analytics 4</b> is replacing <b>Universal Analytics</b>. On July 1, 2023 all standard Universal Analytics properties will stop processing data.<p class="mb-0">It\'s critical that you migrate your Universal Analytics property settings to <b><a href="https://support.google.com/analytics/answer/10089681" rel="noopener" target="_blank">Google Analytics 4</a></b>, or you will begin to lose data on July 1, 2023. <b><a href="https://support.google.com/analytics/answer/10759417" rel="noopener" target="_blank">Learn how</a></b> to migrate your settings from UA to GA4.</p>', 'geodir-ga' ) ) ),
					),
					array(
						'name' => __( 'Enable output widget?', 'geodir-ga' ),
						'desc' => __( 'This will enable the output of the analytics output widget.', 'geodir-ga' ),
						'id' => 'ga_stats',
						'std' => '0',
						'type' => 'checkbox',
					),
					array(
						'name' => __( 'Google Authentication', 'geodir-ga' ),
						'desc' => '',
						'id' => 'ga_authentication',
						'type' => 'google_analytics',
						'css' => 'min-width:300px;',
						'std' => ''
					),
					array(
						'id' => 'ga_account_id',
						'type' => 'select',
						'name' => __( 'Analytics Property', 'geodir-ga' ),
						'placeholder' => ( ! empty( $accounts ) ? __( 'Select Property', 'geodir-ga' ) : __( 'Log-In to select property', 'geodir-ga' ) ),
						'desc' => __( 'Select the property that you setup for this site.', 'geodir-ga' ),
						'options' => $accounts
					),
					array(
						'id' => 'ga_measurement_id',
						'type' => 'select',
						'name' => __( 'Stream Measurement ID (GA4 Only)', 'geodir-ga' ),
						'placeholder' => ( ! empty( $data_streams ) ? __( 'Select Measurement ID', 'geodir-ga' ) : __( 'No Data Stream found', 'geodir-ga' ) ),
						'desc' => __( 'Select web stream measurement id that setup for the Google Analytics 4 property.', 'geodir-ga' ),
						'options' => $data_streams
					),
					array(
						'name' => __( 'Add tracking code to site?', 'geodir-ga' ),
						'desc' => __( 'This will automatically add the correct tracking code to your site', 'geodir-ga' ),
						'id' => 'ga_add_tracking_code',
						'std' => '0',
						'type' => 'checkbox',
					),
					array(
						'name' => __( 'Anonymize user IP?', 'geodir-ga' ),
						'desc' => __( 'In most cases this is not required, this is to comply with certain country laws such as Germany.', 'geodir-ga' ),
						'id' => 'ga_anonymize_ip',
						'type' => 'checkbox',
						'std' => '0',
						'advanced' => true
					),
					array(
						'name' => __( 'Auto refresh active users?', 'geodir-ga' ),
						'desc' => __( 'If ticked it uses the auto refresh time below, if not it never refreshes unless the refresh button is clicked.', 'geodir-ga' ),
						'id' => 'ga_auto_refresh',
						'type' => 'checkbox',
						'std' => '0',
						'advanced' => true
					),
					array(
						'name' => __( 'Time interval for auto refresh active users', 'geodir-ga' ),
						'desc' => __( 'Time interval in seconds to auto refresh active users. The active users will be auto refreshed after this time interval. Leave blank or use 0(zero) to disable auto refresh. Default: 5', 'geodir-ga' ),
						'id' => 'ga_refresh_time',
						'type' => 'text',
						'std' => '5',
						'class' => 'gd-advanced-setting',
						'advanced' => true
					),
					array(
						'type' => 'sectionend', 
						'id' => 'google_analytic_settings'
					),
				)
			);

			return apply_filters( 'geodir_get_settings_' . $this->id, $settings, $current_section );
		}

		public static function activation_url(){
			$url = add_query_arg( 
				array(
					'client_id'       => GEODIR_GA_CLIENTID,
					'scope'           => GEODIR_GA_SCOPE,
					'access_type'     => 'offline',
					'approval_prompt' => 'force',
					'response_type'   => 'code',
					'redirect_uri'    => urlencode( GEODIR_GA_REDIRECT ),
					'next'            => urlencode( admin_url( 'admin.php?page=gd-settings&tab=analytics' ) ),
					'state'           => urlencode( admin_url( 'admin.php?page=gd-settings&tab=analytics' ) ),
				), 
				GEODIR_GA_OAUTH2_AUTH_URL
			);

			return $url;
		}

		public static function get_data_streams_options() {
			global $geodir_analytics_data_streams;

			if ( is_array( $geodir_analytics_data_streams ) ) {
				return $geodir_analytics_data_streams;
			}

			$property_id = geodir_get_option( 'ga_account_id' );
			if ( ! empty( $_POST['ga_account_id'] ) ) {
				$property_id = sanitize_text_field( $_POST['ga_account_id'] );
			}
			$auth_code = geodir_get_option( 'ga_auth_code' );

			$data_stream_options = array();

			if ( ! empty( $property_id ) && ! empty( $auth_code ) && geodir_ga_type( $property_id ) == 'ga4' ) {
				try {
					$data_streams = self::get_analytics_data_streams( $property_id );

					if ( ! empty( $data_streams[ $property_id ] ) ) {
						$data_stream_options = self::parse_data_stream_options( $data_streams[ $property_id ] );

					}
				} catch ( Exception $e ) {
					geodir_error_log( wp_sprintf( __( 'GD Google Analytics API Error(%s) : %s', 'geodir-ga' ), $e->getCode(), $e->getMessage() ) );
				}

				if ( empty( $data_stream_options ) ) {
					$data_stream_options = array( '' => __( 'No Data Stream found for GA4 property #', 'geodir-ga' ) . $property_id );
				}
			}

			$geodir_analytics_data_streams = $data_stream_options;

			return $data_stream_options;
		}

		public static function get_property_options() {
			global $geodir_analytics_properties;

			if ( is_array( $geodir_analytics_properties ) ) {
				return $geodir_analytics_properties;
			}

			$auth_code = geodir_get_option( 'ga_auth_code' );

			$property_options = array();

			if ( $auth_code ) {
				try {
					$properties = self::get_analytics_properties();

					if ( is_array( $properties ) ) {
						$property_options = self::parse_property_options( $properties );
					}
				} catch ( Exception $e ) {
					geodir_error_log( wp_sprintf( __( 'GD Google Analytics API Error(%s) : %s', 'geodir-ga' ), $e->getCode(), $e->getMessage() ) );
				}

				if ( empty( $property_options ) ) {
					$property_options = array( '' => __( 'Account re-authorization may be required', 'geodir-ga' ) );
				}
			}

			$geodir_analytics_properties = $property_options;

			return $property_options;
		}

		public static function get_analytics_properties() {
			global $gd_ga_errors;

			if ( empty( $gd_ga_errors ) ) {
				$gd_ga_errors = array();
			}

			$properties = array();

			if ( geodir_get_option( 'ga_auth_token' ) === false ) {
				geodir_update_option( 'ga_auth_token', '' );
				geodir_update_option( 'ga_auth_date', '' );
			}

			if ( ! isset( $_POST['ga_auth_code'] ) && ( $_properties = geodir_get_option( 'ga_properties' ) ) ) {
				return $_properties;
			}

			# Create a new Gdata call
			if ( trim( geodir_get_option( 'ga_auth_code' ) ) != '' ) {
				$ga_api = new GeoDir_Google_Analytics_API();
			} else {
				return false;
			}

			# Check if Google sucessfully logged in
			$check_token = $ga_api->checkLogin();

			if ( is_wp_error( $check_token ) ) {
				geodir_error_log( $check_token->get_error_message(), 'Google Analytics Error', __FILE__, __LINE__ );
				$gd_ga_errors[] = $check_token->get_error_message();

				return false;
			} else if ( ! $check_token ) {
				return false;
			}

			# Get a list of properties
			try {
				$properties = $ga_api->get_properties();
			} catch ( Exception $e ) {
				$gd_ga_errors[] = $e->getMessage();

				return false;
			};

			return $properties;
		}

		public static function get_analytics_data_streams( $property_id ) {
			global $gd_ga_errors;

			if ( empty( $gd_ga_errors ) ) {
				$gd_ga_errors = array();
			}

			$data_streams = array();

			if ( $_data_streams = geodir_get_option( 'ga_data_streams' ) ) {
				if ( ! empty( $_data_streams ) && ! empty( $_data_streams[ $property_id ] ) ) {
					return $_data_streams;
				}
			}

			# Create a new Gdata call
			if ( trim( geodir_get_option( 'ga_auth_code' ) ) != '' ) {
				$geodir_analytics_api = new GeoDir_Google_Analytics_API();
			} else {
				return false;
			}

			# Check if Google sucessfully logged in
			$check_token = $geodir_analytics_api->checkLogin();

			if ( is_wp_error( $check_token ) ) {
				geodir_error_log( $check_token->get_error_message(), 'Google Analytics Error', __FILE__, __LINE__ );
				$gd_ga_errors[] = $check_token->get_error_message();

				return false;
			} else if ( ! $check_token ) {
				return false;
			}

			# Get a list of data streams
			try {
				$data_streams = $geodir_analytics_api->getDataStreams( $property_id );
				$data_streams = ! empty( $data_streams['dataStreams'] ) ? array( $property_id => $data_streams['dataStreams'] ) : '';

				geodir_update_option( 'ga_data_streams', $data_streams );
			} catch ( Exception $e ) {
				$gd_ga_errors[] = $e->getMessage();

				return false;
			};

			return $data_streams;
		}

		public static function parse_property_options( $properties ) {
			$options = array();

			if ( ! empty( $properties ) ) {
				foreach ( $properties as $property_id => $property ) {
					if ( isset( $property['pType'] ) && $property['pType'] == 'ga4' ) {
						$options[ $property_id ] = wp_sprintf( __( '%s ( Google Analytics 4 )', 'geodir-ga' ), $property['displayName'] );
					} else {
						$options[ $property_id ] = wp_sprintf( __( '%s ( Universal Analytics )', 'geodir-ga' ), $property['name'] );
					}
				}

				asort( $options );
			} else {
				$options = array( '' => __( 'No account found, try to re-authorize','geodir-ga' ) );
			}

			return $options;
		}

		public static function parse_data_stream_options( $data_streams ) {
			$options = array();

			if ( ! empty( $data_streams ) ) {
				foreach ( $data_streams as $key => $data_stream ) {
					if ( empty( $data_stream['webStreamData']['measurementId'] ) ) {
						continue;
					}

					$measurement_id = $data_stream['webStreamData']['measurementId'];

					$options[ $measurement_id ] = $data_stream['displayName'] . ' ( ' . $measurement_id . ' )';
				}

				asort( $options );
			} else {
				$options = array( '' => __( 'No data stream found','geodir-ga' ) );
			}

			return $options;
		}

		public static function check_measurement_id() {
			global $geodir_check_measurement;

			if ( ! $geodir_check_measurement && ! empty( $_REQUEST['tab'] ) && $_REQUEST['tab'] == 'analytics' && ( $property_id = geodir_get_option( 'ga_account_id' ) ) && geodir_get_option( 'ga_auth_code' ) ) {
				if ( geodir_ga_type( $property_id ) == 'ga4' ) {
					$data_stream = geodir_get_option( 'ga_data_stream' );

					if ( ! empty( $data_stream ) && ! empty( $data_stream['webStreamData']['measurementId'] ) && ! empty( $data_stream['name'] ) && strpos( $data_stream['name'], 'properties/' . $property_id . '/' ) === 0 ) {
						// measurement exists!
					} else {
						$geodir_analytics_api = new GeoDir_Google_Analytics_API();
						$check_token = $geodir_analytics_api->checkLogin();

						if ( $check_token && ! is_wp_error( $check_token ) ) {
							$data_stream = $geodir_analytics_api->getDataStream( $property_id );
						}
					}
				}
			}

			$geodir_check_measurement = true;
		}
	}

endif;

return new GeoDir_Settings_Analytics();
