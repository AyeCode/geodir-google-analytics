<?php
/**
 * Google Analytics Stat API.
 *
 * @package    GeoDir_Google_Analytics
 * @since      2.0.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GeoDir_Google_Analytics_API class.
 */
class GeoDir_Google_Analytics_API {

	/**
	 * OAuth 2.0 token endpoint.
	 */
	const OAUTH2_TOKEN_URI = 'https://accounts.google.com/o/oauth2/token';

	/**
	 * Google Analytics Admin API root.
	 */
	const ADMIN_API_URL = 'https://analyticsadmin.googleapis.com/';

	/**
	 * Google Analytics Data API root.
	 */
	const DATA_API_URL = 'https://analyticsdata.googleapis.com/';

	/**
	 * Treat a token as expired this many seconds before it actually expires.
	 */
	const TOKEN_EXPIRY_MARGIN = 30;

	/**
	 * Default request timeout in seconds.
	 *
	 * Report requests get longer, see get_timeout(). The library this class
	 * replaced allowed 100 seconds, so a short timeout here is a regression.
	 */
	const REQUEST_TIMEOUT = 20;

	/**
	 * Timeout in seconds for Data API report requests, which can be slow.
	 */
	const REPORT_TIMEOUT = 60;

	/**
	 * How many times to retry a request that failed before the server replied.
	 */
	const MAX_RETRIES = 1;

	/**
	 * The current OAuth token as an array.
	 *
	 * @var array
	 */
	public $token = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->token = $this->get_stored_token();
	}

	/**
	 * Read the stored OAuth token.
	 *
	 * @return array
	 */
	public function get_stored_token() {
		$token = geodir_get_option( 'ga_auth_token' );

		if ( is_scalar( $token ) && $token !== '' ) {
			$token = json_decode( $token, true );
		}

		return is_array( $token ) ? $token : array();
	}

	/**
	 * Save the OAuth token.
	 *
	 * @param array $token Token data.
	 */
	public function set_stored_token( $token ) {
		$this->token = is_array( $token ) ? $token : array();

		geodir_update_option( 'ga_auth_token', ! empty( $this->token ) ? wp_json_encode( $this->token ) : '' );
	}

	/**
	 * Check whether the current token has expired.
	 *
	 * @param array $token Optional. Token to check. Default the current token.
	 * @return bool
	 */
	public function is_token_expired( $token = null ) {
		if ( $token === null ) {
			$token = $this->token;
		}

		if ( empty( $token['access_token'] ) || empty( $token['created'] ) || ! isset( $token['expires_in'] ) ) {
			return true;
		}

		return ( (int) $token['created'] + ( (int) $token['expires_in'] - self::TOKEN_EXPIRY_MARGIN ) ) < time();
	}

	/**
	 * Build a readable message from a Google API error response.
	 *
	 * @param mixed $data Decoded response body.
	 * @param int   $code HTTP status code.
	 * @return string
	 */
	protected function parse_error( $data, $code ) {
		$message = '';

		if ( ! empty( $data['error']['message'] ) ) {
			$message = $data['error']['message'];

			if ( ! empty( $data['error']['code'] ) ) {
				$code = $data['error']['code'];
			}
		} else if ( ! empty( $data['error'] ) && is_string( $data['error'] ) ) {
			$message = $data['error'];

			if ( ! empty( $data['error_description'] ) ) {
				$message .= ': ' . $data['error_description'];
			}
		}

		if ( $message === '' ) {
			$message = __( 'Unknown Google Analytics API error.', 'geodir-ga' );
		}

		return '[' . $code . '] ' . $message;
	}

	/**
	 * Request a token from the OAuth 2.0 token endpoint.
	 *
	 * @param array $body       Request body.
	 * @param array $merge_into Optional. Existing token to merge the response into.
	 * @return array|WP_Error Token data on success.
	 */
	protected function request_token( $body, $merge_into = array() ) {
		$response = wp_remote_post(
			self::OAUTH2_TOKEN_URI,
			array(
				'body' => $body,
				'timeout' => $this->get_timeout( 'token' )
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->transport_error( $response, self::OAUTH2_TOKEN_URI );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 != $code || empty( $data['access_token'] ) ) {
			return new WP_Error( 'geodir_ga_token_request', $this->parse_error( $data, $code ) );
		}

		// A refresh response does not include the refresh token, so keep the existing one.
		$token = array_merge( $merge_into, $data );
		$token['created'] = time();

		$this->set_stored_token( $token );

		return $token;
	}

	/**
	 * Exchange an authorization code for an access token.
	 *
	 * @param string $code Authorization code.
	 * @return array|WP_Error Token data on success.
	 */
	public function authenticate( $code ) {
		if ( empty( $code ) ) {
			return new WP_Error( 'geodir_ga_invalid_code', __( 'Invalid authorization code.', 'geodir-ga' ) );
		}

		return $this->request_token(
			array(
				'code' => $code,
				'grant_type' => 'authorization_code',
				'client_id' => GEODIR_GA_CLIENTID,
				'client_secret' => GEODIR_GA_CLIENTSECRET,
				'redirect_uri' => GEODIR_GA_REDIRECT
			)
		);
	}

	/**
	 * Refresh the stored access token.
	 *
	 * @return array|WP_Error Token data on success.
	 */
	public function refresh_token() {
		if ( empty( $this->token['refresh_token'] ) ) {
			return new WP_Error( 'geodir_ga_no_refresh_token', __( 'The Google Analytics access token has expired and no refresh token is available, please re-authorize.', 'geodir-ga' ) );
		}

		return $this->request_token(
			array(
				'refresh_token' => $this->token['refresh_token'],
				'grant_type' => 'refresh_token',
				'client_id' => GEODIR_GA_CLIENTID,
				'client_secret' => GEODIR_GA_CLIENTSECRET
			),
			$this->token
		);
	}

	/**
	 * Load the stored token and make sure it is usable.
	 *
	 * @return bool|WP_Error True when authorized, false when not set up.
	 */
	function checkLogin() {
		$this->token = $this->get_stored_token();

		if ( empty( $this->token['access_token'] ) ) {
			$auth_code = geodir_get_option( 'ga_auth_code' );

			if ( empty( $auth_code ) ) {
				return false;
			}

			$token = $this->authenticate( $auth_code );

			if ( is_wp_error( $token ) ) {
				return $token;
			}

			geodir_update_option( 'ga_auth_date', date( 'Y-m-d H:i:s' ) );
		}

		if ( $this->is_token_expired() ) {
			$token = $this->refresh_token();

			if ( is_wp_error( $token ) ) {
				return $token;
			}
		}

		return ! empty( $this->token['access_token'] );
	}

	function deauthorize() {
		geodir_update_option( 'ga_auth_code', '' );
		geodir_update_option( 'ga_auth_token', '' );
		geodir_update_option( 'ga_auth_date', '' );

		$this->token = array();
	}

	/**
	 * Store the property quota reported by the Data API.
	 *
	 * @param array $quota Quota block from the API response.
	 */
	public function store_property_quota( $quota ) {
		if ( empty( $quota ) || ! is_array( $quota ) ) {
			return;
		}

		$quota['checked'] = time();

		geodir_update_option( 'ga_property_quota', $quota );
	}

	/**
	 * Get the last known property quota.
	 *
	 * @return array
	 */
	public function get_property_quota() {
		$quota = geodir_get_option( 'ga_property_quota' );

		return is_array( $quota ) ? $quota : array();
	}

	/**
	 * Record the last API error so it can be shown in the diagnostics panel.
	 *
	 * @param WP_Error $error Error to record.
	 */
	public function store_last_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}

		geodir_update_option( 'ga_last_error', array(
			'code' => $error->get_error_code(),
			'message' => $error->get_error_message(),
			'time' => time()
		) );
	}

	/**
	 * Get the last recorded API error.
	 *
	 * @return array
	 */
	public function get_last_error() {
		$error = geodir_get_option( 'ga_last_error' );

		return is_array( $error ) ? $error : array();
	}

	/**
	 * Get a valid access token, refreshing it when needed.
	 *
	 * @return string|null
	 */
	public function get_access_token() {
		if ( empty( $this->token['access_token'] ) ) {
			$this->token = $this->get_stored_token();
		}

		if ( empty( $this->token['access_token'] ) ) {
			return null;
		}

		if ( $this->is_token_expired() ) {
			$token = $this->refresh_token();

			if ( is_wp_error( $token ) ) {
				geodir_error_log( $token->get_error_message(), 'Analytics API Token Error', __FILE__, __LINE__ );

				return null;
			}
		}

		return $this->token['access_token'];
	}

	/**
	 * Build the headers for an authenticated API request.
	 *
	 * @param string $access_token Optional. Access token to use.
	 * @return array
	 */
	public function get_request_headers( $access_token = null ) {
		if ( $access_token === null ) {
			$access_token = $this->get_access_token();
		}

		return array(
			'Authorization' => 'Bearer ' . $access_token,
			'User-Agent' => 'geodir-google-analytics/' . GEODIR_GA_VERSION,
			'x-goog-api-client' => sprintf( 'gl-php/%s', phpversion() )
		);
	}

	/**
	 * Perform an authenticated request against a Google Analytics API.
	 *
	 * @param string $url    Full request URL.
	 * @param string $method HTTP method.
	 * @param array  $body   Optional. Body to send as JSON.
	 * @return array|WP_Error Decoded response on success.
	 */
	/**
	 * Timeout in seconds for a given kind of request.
	 *
	 * @param string $context One of default, token or report.
	 * @return int
	 */
	public function get_timeout( $context = 'default' ) {
		$timeout = $context === 'report' ? self::REPORT_TIMEOUT : self::REQUEST_TIMEOUT;

		/**
		 * Filter the Google Analytics API request timeout.
		 *
		 * @since 2.4.0
		 *
		 * @param int    $timeout Timeout in seconds.
		 * @param string $context Request context.
		 */
		return (int) apply_filters( 'geodir_ga_request_timeout', $timeout, $context );
	}

	/**
	 * Whether a transport error is worth retrying.
	 *
	 * @param WP_Error $error Error returned by the HTTP API.
	 * @return bool
	 */
	protected function is_transient_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}

		$message = $error->get_error_message();

		$needles = array(
			'cURL error 6',  // could not resolve host
			'cURL error 7',  // could not connect
			'cURL error 28', // operation timed out
			'cURL error 35', // ssl connect error
			'cURL error 52', // empty reply from server
			'cURL error 56', // receive failure
			'timed out',
			'Connection refused',
			'Connection reset'
		);

		foreach ( $needles as $needle ) {
			if ( stripos( $message, $needle ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Turn a raw transport failure into something worth showing a visitor.
	 *
	 * @param WP_Error $error Error returned by the HTTP API.
	 * @param string   $url   Request URL, for the log entry.
	 * @return WP_Error
	 */
	protected function transport_error( $error, $url = '' ) {
		geodir_error_log(
			wp_sprintf( '%s [%s]', $error->get_error_message(), $url ),
			'Analytics API Transport Error',
			__FILE__,
			__LINE__
		);

		if ( $this->is_transient_error( $error ) ) {
			$friendly = new WP_Error(
				'geodir_ga_request_timeout',
				__( 'Could not reach Google Analytics in time. This is usually a temporary network issue or a slow report, please try again in a moment.', 'geodir-ga' )
			);
		} else {
			$friendly = new WP_Error(
				'geodir_ga_request_failed',
				__( 'Could not reach Google Analytics. Please check the site can make outgoing requests to googleapis.com.', 'geodir-ga' )
			);
		}

		$this->store_last_error( $friendly );

		return $friendly;
	}

	public function request( $url, $method = 'GET', $body = null, $context = 'default' ) {
		$access_token = $this->get_access_token();

		if ( empty( $access_token ) ) {
			return new WP_Error( 'geodir_ga_no_access_token', __( 'No valid Google Analytics access token, please re-authorize.', 'geodir-ga' ) );
		}

		$args = array(
			'method' => $method,
			'headers' => $this->get_request_headers( $access_token ),
			'timeout' => $this->get_timeout( $context )
		);

		if ( $body !== null ) {
			$args['headers']['Content-Type'] = 'application/json; charset=UTF-8';
			$args['body'] = wp_json_encode( $body );
		}

		$attempt = 0;

		do {
			$response = wp_remote_request( $url, $args );

			if ( ! is_wp_error( $response ) ) {
				break;
			}

			$attempt++;
		} while ( $attempt <= self::MAX_RETRIES && $this->is_transient_error( $response ) );

		if ( is_wp_error( $response ) ) {
			return $this->transport_error( $response, $url );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code > 299 ) {
			$error = new WP_Error( 'geodir_ga_invalid_request', $this->parse_error( $data, $code ) );

			$this->store_last_error( $error );

			return $error;
		}

		return is_array( $data ) ? $data : array();
	}

	function get_properties( $cached = false ) {
		if ( $cached && ( $properties = geodir_get_option( 'ga_properties' ) ) ) {
			return $properties;
		}

		// GA4 has replaced Universal Analytics after July 1, 2024.
		$ga4_properties = $this->get_ga4_properties();

		geodir_update_option( 'ga_properties', $ga4_properties );
		geodir_update_option( 'ga_data_stream', '' );
		geodir_update_option( 'ga_profile_view', '' );

		return $ga4_properties;
	}

	function get_ga4_properties( $cached = false ) {
		$properties = array();

		$account_summaries = $this->getAccountSummaries();

		if ( is_wp_error( $account_summaries ) ) {
			geodir_error_log( $account_summaries->get_error_message(), 'Analytics API Service Error', __FILE__, __LINE__ );

			return $properties;
		}

		foreach ( $account_summaries as $account ) {
			if ( empty( $account['propertySummaries'] ) ) {
				continue;
			}

			foreach ( $account['propertySummaries'] as $property ) {
				$property_id = str_replace( 'properties/', '', $property['property'] );

				$property['accountId'] = str_replace( 'accounts/', '', $property['parent'] );
				$property['accountName'] = $account['displayName'];
				$property['pType'] = 'ga4';

				$properties[ $property_id ] = $property;
			}
		}

		return $properties;
	}

	public function getAccountSummaries() {
		$query_args = array(
			'pageSize' => 200
		);

		$response = $this->request( self::ADMIN_API_URL . 'v1beta/accountSummaries?' . build_query( $query_args ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return ! empty( $response['accountSummaries'] ) ? $response['accountSummaries'] : array();
	}

	public function getResource( $resource_name ) {
		$resources = array(
			'dataStreams' => array(
				'serviceName' => 'analyticsadmin', 
				'resourceName' => 'dataStreams',
				'servicePath' => '',
				'rootUrl' => 'https://analyticsadmin.googleapis.com',
				'methods' => array(
					'list' => array(
						'path' => 'v1beta/properties/{propertyId}/dataStreams', 
						'httpMethod' => 'GET', 
						'parameters' => array( 
							'propertyId' => array(
								'location' => 'path',
								'type' => 'string',
								'required' => true
							),
							'pageSize' => array(
								'location' => 'query',
								'type' => 'integer'
							),
							'pageToken' => array(
								'location' => 'query',
								'type' => 'string'
							)
						)
					)
				)
			),
			'runRealtimeReport' => array(
				'serviceName' => 'analyticsdata', 
				'resourceName' => 'runRealtimeReport',
				'servicePath' => '',
				'rootUrl' => 'https://analyticsdata.googleapis.com/',
				'methods' => array(
					'runRealtimeReport' => array(
						'path' => 'v1beta/properties/{property}:runRealtimeReport', 
						'httpMethod' => 'POST', 
						'parameters' => array( 
							'property' => array(
								'location' => 'path',
								'type' => 'string',
								'required' => true
							)
						)
					)
				)
			),
			'runReport' => array(
				'serviceName' => 'analyticsdata', 
				'resourceName' => 'runReport',
				'servicePath' => '',
				'rootUrl' => 'https://analyticsdata.googleapis.com/',
				'methods' => array(
					'runReport' => array(
						'path' => 'v1beta/properties/{property}:runReport', 
						'httpMethod' => 'POST', 
						'parameters' => array( 
							'property' => array(
								'location' => 'path',
								'type' => 'string',
								'required' => true
							)
						)
					)
				)
			)
		);

		$resource = isset( $resources[ $resource_name ] ) ? $resources[ $resource_name ] : array();

		return $resource;
	}

	/**
	 * Build a request URI from a discovery style method path and parameters.
	 *
	 * Replaces Google_Http_REST::createRequestUri() from the removed library.
	 *
	 * @param string $rest_path  Path template, e.g. v1beta/properties/{property}:runReport.
	 * @param array  $param_spec Parameter definitions from the resource method.
	 * @param array  $values     Supplied parameter values.
	 * @return string
	 */
	protected function create_request_uri( $rest_path, $param_spec, $values ) {
		$query = array();

		foreach ( $param_spec as $name => $spec ) {
			if ( ! isset( $values[ $name ] ) ) {
				continue;
			}

			$value = $values[ $name ];
			$location = ! empty( $spec['location'] ) ? $spec['location'] : 'query';

			if ( isset( $spec['type'] ) && $spec['type'] == 'boolean' ) {
				$value = $value ? 'true' : 'false';
			}

			if ( $location == 'path' ) {
				$rest_path = str_replace( '{' . $name . '}', rawurlencode( $value ), $rest_path );
			} else if ( $location == 'query' ) {
				foreach ( (array) $value as $item ) {
					$query[] = rawurlencode( $name ) . '=' . rawurlencode( $item );
				}
			}
		}

		if ( ! empty( $query ) ) {
			$rest_path .= ( strpos( $rest_path, '?' ) === false ? '?' : '&' ) . implode( '&', $query );
		}

		return $rest_path;
	}

	/**
	 * Call a resource method defined by getResource().
	 *
	 * @param array  $method       Method definition.
	 * @param array  $arguments    Parameter values, optionally including postBody.
	 * @param string $servicePath  Optional. Service path prefix.
	 * @param string $rootUrl      Optional. API root URL.
	 * @param bool   $shouldDefer  Optional. Return the request description instead of sending it.
	 * @return array|WP_Error
	 */
	public function call( $method, $arguments, $servicePath = '', $rootUrl = '', $shouldDefer = false, $context = 'default' ) {
		$parameters = $arguments;

		// postBody is a special case since it's not defined in the discovery
		// document as parameter, but we abuse the param entry for storing it.
		$postBody = null;
		if ( isset( $parameters['postBody'] ) ) {
			$postBody = $parameters['postBody'];

			unset( $parameters['postBody'] );
		}

		if ( isset( $parameters['optParams'] ) ) {
			$optParams = $parameters['optParams'];
			unset( $parameters['optParams'] );
			$parameters = array_merge( $parameters, $optParams );
		}

		if ( ! isset( $method['parameters'] ) ) {
			$method['parameters'] = array();
		}

		$path = $this->create_request_uri( $servicePath . $method['path'], $method['parameters'], $parameters );

		$url = trailingslashit( $rootUrl ? $rootUrl : self::DATA_API_URL ) . ltrim( $path, '/' );

		if ( $shouldDefer ) {
			return array(
				'url' => $url,
				'method' => $method['httpMethod'],
				'body' => $postBody
			);
		}

		return $this->request( $url, $method['httpMethod'], $postBody, $context );
	}

	public function listPropertyDataStreams( $params = array() ) {
		$resource = $this->getResource( 'dataStreams' );

		return $this->call( $resource['methods']['list'], $params, $resource['servicePath'], $resource['rootUrl'] );
	}

	public function getDataStreams( $property_id, $limit = 50 ) {
		$data_streams = $this->listPropertyDataStreams( array( 'propertyId' => $property_id, 'pageSize' => $limit ) );

		if ( is_wp_error( $data_streams ) ) {
			geodir_error_log( $data_streams->get_error_message(), 'Analytics API Service Error', __FILE__, __LINE__ );

			return array();
		}

		return $data_streams;
	}

	public function getDataStream( $property_id ) {
		$data_stream = array();

		if ( empty( $property_id ) ) {
			return $data_stream;
		}

		if ( $_data_stream = geodir_get_option( 'ga_data_stream' ) ) {
			return $_data_stream;
		}

		$data_streams = $this->getDataStreams( $property_id );

		if ( ! empty( $data_streams['dataStreams'] ) ) {
			$data_stream = $data_streams['dataStreams'][0];
		}

		geodir_update_option( 'ga_data_stream', $data_stream );

		return $data_stream;
	}

	public function runRealtimeReport( $params ) {
		$resource = $this->getResource( 'runRealtimeReport' );

		return $this->call( $resource['methods']['runRealtimeReport'], $params, $resource['servicePath'], $resource['rootUrl'], false, 'report' );
	}

	public function runReport( $params, $shouldDefer = false ) {
		$resource = $this->getResource( 'runReport' );

		return $this->call( $resource['methods']['runReport'], $params, $resource['servicePath'], $resource['rootUrl'], $shouldDefer, 'report' );
	}

	/**
	 * Human readable, translatable labels for known dimension values.
	 *
	 * @since 2.4.0
	 *
	 * @return array Dimension name => array of raw value => label.
	 */
	public function get_dimension_labels() {
		$labels = array(
			'deviceCategory' => array(
				'desktop' => __( 'Desktop', 'geodir-ga' ),
				'mobile' => __( 'Mobile', 'geodir-ga' ),
				'tablet' => __( 'Tablet', 'geodir-ga' ),
				'smart tv' => __( 'Smart TV', 'geodir-ga' ),
				'console' => __( 'Console', 'geodir-ga' ),
				'wearable' => __( 'Wearable', 'geodir-ga' )
			),
			'newVsReturning' => array(
				'new' => __( 'New visitors', 'geodir-ga' ),
				'returning' => __( 'Returning visitors', 'geodir-ga' )
			),
			'sessionSource' => array(
				'(direct)' => __( 'Direct', 'geodir-ga' ),
				'(none)' => __( 'Direct', 'geodir-ga' )
			)
		);

		/**
		 * Filter the translatable labels used for Google Analytics dimension values.
		 *
		 * @since 2.4.0
		 *
		 * @param array $labels Dimension name => array of raw value => label.
		 */
		return apply_filters( 'geodir_ga_dimension_labels', $labels );
	}

	/**
	 * Labels for placeholder values Google can return for any dimension.
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function get_placeholder_labels() {
		$labels = array(
			'(not set)' => __( 'Unknown', 'geodir-ga' ),
			'(other)' => __( 'Other', 'geodir-ga' ),
			'(none)' => __( 'Unknown', 'geodir-ga' ),
			'' => __( 'Unknown', 'geodir-ga' )
		);

		/**
		 * Filter the labels used for placeholder dimension values.
		 *
		 * @since 2.4.0
		 *
		 * @param array $labels Raw value => label.
		 */
		return apply_filters( 'geodir_ga_placeholder_labels', $labels );
	}

	/**
	 * Turn a raw dimension value into something worth showing a visitor.
	 *
	 * @since 2.4.0
	 *
	 * @param string $dimension Dimension name, e.g. deviceCategory.
	 * @param string $value     Raw value from the API.
	 * @return string
	 */
	public function translate_dimension_value( $dimension, $value ) {
		$key = is_string( $value ) ? strtolower( trim( $value ) ) : '';

		$labels = $this->get_dimension_labels();

		if ( ! empty( $labels[ $dimension ] ) ) {
			foreach ( $labels[ $dimension ] as $raw => $label ) {
				if ( strtolower( $raw ) === $key ) {
					return $label;
				}
			}
		}

		$placeholders = $this->get_placeholder_labels();

		if ( isset( $placeholders[ $key ] ) ) {
			return $placeholders[ $key ];
		}

		/**
		 * Filter a single translated dimension value.
		 *
		 * @since 2.4.0
		 *
		 * @param string $value     The label to display.
		 * @param string $dimension Dimension name.
		 * @param string $raw       The raw value from the API.
		 */
		return apply_filters( 'geodir_ga_dimension_value_label', $value, $dimension, $value );
	}

	/**
	 * Dimensions that produce a simple "top N" breakdown of one metric.
	 *
	 * @return array
	 */
	public function get_breakdown_dimensions() {
		return apply_filters( 'geodir_ga_breakdown_dimensions', array( 'country', 'city', 'deviceCategory', 'sessionSource', 'newVsReturning' ) );
	}

	/**
	 * Report types that render as a "top N" breakdown.
	 *
	 * @return array
	 */
	public function get_breakdown_types() {
		return apply_filters( 'geodir_ga_breakdown_types', array( 'country', 'city', 'devices', 'sources', 'newreturning' ) );
	}

	/**
	 * Normalise a metric argument into the Data API metrics structure.
	 *
	 * @param string|array $metrics Metric name(s).
	 * @return array
	 */
	public function parse_metrics( $metrics ) {
		if ( empty( $metrics ) ) {
			return array();
		}

		if ( is_string( $metrics ) ) {
			$metrics = explode( ',', $metrics );
		}

		$parsed = array();

		foreach ( (array) $metrics as $metric ) {
			$metric = trim( $metric );

			if ( $metric !== '' ) {
				$parsed[] = array( 'name' => $metric );
			}
		}

		return $parsed;
	}

	public function getRequestParams( $property_id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'dimensions' => 'date',
				'metrics'    => 'screenPageViews',
				'start_date' => '',
				'end_date'   => '',
				'page_path'  => '',
				'page_title' => '',
				'limit'      => '',
				'type'       => ''
			)
		);

		$dimensions = array();
		$metrics = array();
		$dateRanges = array();
		$dimensionFilter = array();
		$orderBys = array();

		if ( ! empty( $args['dimensions'] ) && $args['dimensions'] == 'date' ) {
			$dimensions = array(
				array(
					'name' => 'date'
				),
				array(
					'name' => 'nthDay'
				)
			);

			$orderBys = array(
				'dimension' => array(
					'dimensionName' => 'date'
				)
			);
		} else if ( ! empty( $args['dimensions'] ) && $args['dimensions'] == 'month' ) {
			$dimensions = array(
				array(
					'name' => 'month'
				),
				array(
					'name' => 'nthMonth'
				)
			);

			$orderBys = array(
				'dimension' => array(
					'dimensionName' => 'month'
				)
			);
		} else if ( ! empty( $args['dimensions'] ) && in_array( $args['dimensions'], $this->get_breakdown_dimensions(), true ) ) {
			$dimensions = array(
				array(
					'name' => $args['dimensions']
				)
			);

			// Order a breakdown by its metric so the "top" rows really are the top ones.
			$primary_metric = $this->parse_metrics( $args['metrics'] );

			if ( ! empty( $primary_metric ) ) {
				$orderBys = array(
					array(
						'metric' => array(
							'metricName' => $primary_metric[0]['name']
						),
						'desc' => true
					)
				);
			}
		} else if ( $args['type'] === 'realtime' || $args['type'] === '' ) {
			// pagePath is not part of the realtime schema, match on the screen name instead.
			// See https://developers.google.com/analytics/devguides/reporting/data/v1/realtime-api-schema
			if ( ! empty( $args['page_path'] ) ) {
				$args['page_path'] = '';
			}

			if ( ! empty( $args['page_title'] ) ) {
				$dimensionFilter = array(
					'filter' => array(
						'fieldName' => 'unifiedScreenName',
						'stringFilter' => array(
							'matchType' => 'EXACT',
							'value' => $args['page_title']
						)
					)
				);
			}

			$args['metrics'] = 'activeUsers';
		}

		$metrics = $this->parse_metrics( $args['metrics'] );

		if ( ! empty( $args['start_date'] ) && ! empty( $args['end_date'] ) ) {
			$dateRanges = array(
				array(
					'startDate' => $args['start_date'],
					'endDate' => $args['end_date']
				)
			);
		}

		if ( ! empty( $args['page_path'] ) ) {
			$dimensionFilter = array(
				'filter' => array(
					'fieldName' => 'pagePathPlusQueryString',
					'stringFilter' => array(
						'value' => $args['page_path']
					)
				)
			);
		}

		$params = array(
			'property' => $property_id,
			'postBody' => array(
				'dimensions' => $dimensions,
				'metrics' => $metrics,
				'orderBys' => $orderBys,
				'keepEmptyRows' => true,
				'returnPropertyQuota' => true
			)
		);

		if ( isset( $args['metrics'] ) && $args['metrics'] == 'activeUsers' ) {
			unset( $params['postBody']['keepEmptyRows'] );
		}

		if ( ! empty( $dateRanges ) ) {
			$params['postBody']['dateRanges'] = $dateRanges;
		}

		if ( ! empty( $dimensionFilter ) ) {
			$params['postBody']['dimensionFilter'] = $dimensionFilter;
		}

		if ( ! empty( $args['limit'] ) ) {
			$params['postBody']['limit'] = (int) $args['limit'];
		}

		return $params;
	}

	public function parseReport( $response, $params, $type, $report = array() ) {
		$data = array();

		$metric = ! empty( $params['postBody']['metrics'][0]['name'] ) ? $params['postBody']['metrics'][0]['name'] : '';

		if ( ! empty( $params['postBody']['dateRanges'][0]['startDate'] ) && ! empty( $params['postBody']['dateRanges'][0]['endDate'] ) ) {
			$startDate = $params['postBody']['dateRanges'][0]['startDate'];
			$endDate = $params['postBody']['dateRanges'][0]['endDate'];
		} else {
			$startDate = '';
			$endDate = '';
		}

		if ( in_array( $type, $this->get_breakdown_types(), true ) ) {
			$data['rows'] = array();

			$dimension = ! empty( $params['postBody']['dimensions'][0]['name'] ) ? $params['postBody']['dimensions'][0]['name'] : '';

			if ( ! empty( $response['rows'] ) ) {
				foreach ( $response['rows'] as $key => $row ) {
					if ( ! empty( $row['dimensionValues'][0]['value'] ) && isset( $row['metricValues'][0]['value'] ) ) {
						$raw = $row['dimensionValues'][0]['value'];

						// Third element is the display label, the raw value is kept in place
						// so anything already reading rows[0] keeps working.
						$data['rows'][] = array(
							$raw,
							(int) $row['metricValues'][0]['value'],
							$this->translate_dimension_value( $dimension, $raw )
						);
					}
				}
			}

			$data['rowCount'] = ! empty( $response['rowCount'] ) ? (int) $response['rowCount'] : 0;
		} else if ( in_array( $type, array( 'week', 'lastweek', 'thisweek', 'month', 'lastmonth', 'thismonth', 'sessions' ), true ) ) {
			$data['rows'] = array();
			$dates = array();

			if ( ! empty( $startDate ) && ! empty( $endDate ) ) {
				for ( $i = 0; $i < 31; $i++ ) {
					$date = date( 'Ymd', strtotime( $startDate . ' + ' . $i . ' day' ) );

					$dates[ $date ] = array(
						$date,
						str_pad( $i, 4, '0', STR_PAD_LEFT ),
						0
					);

					if ( strtotime( $date ) >= strtotime( $endDate ) ) {
						break;
					}
				}
			}

			if ( ! empty( $response['rows'] ) ) {
				foreach ( $response['rows'] as $key => $row ) {
					if ( ! empty( $row['dimensionValues'][0]['value'] ) && isset( $row['dimensionValues'][1]['value'] ) && isset( $row['metricValues'][0]['value'] ) ) {
						$dates[ $row['dimensionValues'][0]['value'] ] = array(
							$row['dimensionValues'][0]['value'],
							$row['dimensionValues'][1]['value'],
							(int) $row['metricValues'][0]['value']
						);
					}
				}
			}

			$data['rows'] = array_values( $dates );
			$data['rowCount'] = ! empty( $response['rowCount'] ) ? (int) $response['rowCount'] : 0;
		} else if ( in_array( $type, array( 'year', 'lastyear', 'thisyear' ), true ) ) {
			$data['rows'] = array();
			$dates = array();

			if ( ! empty( $startDate ) && ! empty( $endDate ) ) {
				for ( $i = 0; $i < 12; $i++ ) {
					$date = date( 'Ym01', strtotime( $startDate . ' + ' . $i . ' month' ) );
					$month = date( 'm', strtotime( $date ) );

					$dates[ $month ] = array(
						$month,
						str_pad( $i, 4, '0', STR_PAD_LEFT ),
						0
					);

					if ( strtotime( $date ) >= strtotime( $endDate ) ) {
						break;
					}
				}
			}

			if ( ! empty( $response['rows'] ) ) {
				foreach ( $response['rows'] as $key => $row ) {
					if ( ! empty( $row['dimensionValues'][0]['value'] ) && isset( $row['dimensionValues'][1]['value'] ) && isset( $row['metricValues'][0]['value'] ) ) {
						$dates[ $row['dimensionValues'][0]['value'] ] = array(
							$row['dimensionValues'][0]['value'],
							$row['dimensionValues'][1]['value'],
							(int) $row['metricValues'][0]['value']
						);
					}
				}
			}

			$data['rows'] = array_values( $dates );
			$data['rowCount'] = ! empty( $response['rowCount'] ) ? (int) $response['rowCount'] : 0;
		} else if ( $type == 'engagement' ) {
			// A single row carrying one value per requested metric.
			$data['totals'] = array();

			if ( ! empty( $params['postBody']['metrics'] ) ) {
				foreach ( $params['postBody']['metrics'] as $index => $requested ) {
					$value = isset( $response['rows'][0]['metricValues'][ $index ]['value'] ) ? $response['rows'][0]['metricValues'][ $index ]['value'] : 0;

					$data['totals'][ $requested['name'] ] = is_numeric( $value ) ? (float) $value : 0;
				}
			}
		} else if ( $metric == 'activeUsers' ) {
			$data['totalActiveUsers'] = 0;

			if ( isset( $response['rows'][0]['metricValues'][0]['value'] ) ) {
				$data['totalActiveUsers'] = (int) $response['rows'][0]['metricValues'][0]['value'];
			}
		}

		// The API returns quota usage when returnPropertyQuota is set, keep it for the diagnostics panel.
		if ( ! empty( $response['propertyQuota'] ) ) {
			$this->store_property_quota( $response['propertyQuota'] );
		}

		if ( empty( $report[ $metric ] ) ) {
			$report[ $metric ] = array();
		}

		if ( empty( $report[ $metric ][ $type ] ) ) {
			$report[ $metric ][ $type ] = array();
		}

		$report[ $metric ][ $type ] = $data;

		return $report;
	}

	function getReport( $property_id, $type, $start_date, $end_date, $dimensions, $page_path, $page_title, $limit, $metrics = 'screenPageViews' ) {
		$params = $this->getRequestParams( $property_id, array( 'start_date' => $start_date, 'end_date' => $end_date, 'dimensions' => $dimensions, 'metrics' => $metrics, 'page_path' => $page_path, 'page_title' => $page_title, 'limit' => $limit, 'type' => $type ) );

		if ( ! empty( $type ) && $type != 'realtime' ) {
			$report = $this->runReport( $params );
		} else {
			$report = $this->runRealtimeReport( $params );
		}

		if ( is_wp_error( $report ) ) {
			return $report;
		}

		return $this->parseReport( $report, $params, $type );
	}
}
