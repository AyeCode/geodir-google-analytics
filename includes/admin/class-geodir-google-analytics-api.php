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

	var $client = false;
	var $accountId;
	var $baseFeed = 'https://www.googleapis.com/analytics/v3';
	var $token = false;
	public $analytics;

	/**
	 * Constructor
	 *
	 * @param token - a one-time use token to be exchanged for a real token
	 *
	 */
	public function __construct() {

		// Include the Google Service API
		if ( ! class_exists( 'Google_Client' ) ) {
			include_once( GEODIR_GA_PLUGIN_DIR . 'includes/libraries/google-api-php-client/src/Google/autoload.php' );
		}

		// Include Google_Service_Analytics class
		if ( ! class_exists( 'Google_Service_Analytics' ) ) {
			require_once( GEODIR_GA_PLUGIN_DIR . 'includes/libraries/google-api-php-client/src/Google/Service/Analytics.php' );
		}

		$this->client = new Google_Client();
		$this->client->setApprovalPrompt( 'force' );
		$this->client->setAccessType( 'offline' );
		$this->client->setClientId( GEODIR_GA_CLIENTID );
		$this->client->setClientSecret( GEODIR_GA_CLIENTSECRET );
		$this->client->setRedirectUri( GEODIR_GA_REDIRECT );
		$this->client->setScopes( array( GEODIR_GA_SCOPE ) );

		try {
			$this->analytics = new Google_Service_Analytics( $this->client );
		} catch ( Google_ServiceException $e ) {
			print 'Google Analytics API Service error ' . $e->getCode() . ':' . $e->getMessage();
			return false;
		}
	}

	function checkLogin() {
		$ga_google_authtoken = geodir_get_option( 'ga_auth_token' );

		if ( ! empty( $ga_google_authtoken ) ) {
			try {
				$this->client->setAccessToken( $ga_google_authtoken );
			} catch( Google_AuthException $e ) {
				return new WP_Error( 'ga_setaccesstoken', '[' . $e->getCode() . '] ' . $e->getMessage() );
			}
		} else {
			$authCode = geodir_get_option( 'ga_auth_code' );

			if ( empty( $authCode ) ) {
				return false;
			}

			try {
				$accessToken = $this->client->authenticate( $authCode );
			} catch( Exception $e ) {
				return new WP_Error( 'ga_authenticate', '[' . $e->getCode() . '] ' . $e->getMessage() );
			}

			if ( $accessToken ) {
				$this->client->setAccessToken( $accessToken );

				geodir_update_option( 'ga_auth_token', $accessToken );
				geodir_update_option( 'ga_auth_date', date( 'Y-m-d H:i:s' ) );
			} else {
				return false;
			}
		}

		$this->token =  $this->client->getAccessToken();

		return true;
	}

	function deauthorize() {
		geodir_update_option( 'ga_auth_code', '' );
		geodir_update_option( 'ga_auth_token', '' );
		geodir_update_option( 'ga_auth_date', '' );
	}

	function getView( $property_id ) {
		$profile_view = array();

		if ( empty( $property_id ) ) {
			return $profile_view;
		}

		if ( $_profile_view = geodir_get_option( 'ga_profile_view' ) ) {
			return $_profile_view;
		}

		$_property_id = explode( '-', $property_id );
		$account_id = count( $_property_id ) > 2 ? $_property_id[1] : $_property_id[0];

		try {
			$views = $this->analytics->management_profiles->listManagementProfiles( $account_id, $property_id );
		} catch ( Google_ServiceException $e ) {
			geodir_error_log( '[' . $e->getCode() . '] ' . $e->getMessage(), 'Analytics API Service Error', __FILE__, __LINE__ );

			return false;
		}

		if ( empty( $views->items ) ) {
			return false;
		}

		$_view = $views->items[0];

		$view = array();
		foreach ( $_view as $key => $value ) {
			$view[ $key ] = $value;
		}

		geodir_update_option( 'ga_profile_view', $view );

		return $view;
	}

	function get_properties( $cached = false ) {
		if ( $cached && ( $properties = geodir_get_option( 'ga_properties' ) ) ) {
			return $properties;
		}

		//$ua_properties = $this->get_ua_properties(); // GA 4 has replaced Universal Analytics after July 1, 2024.
		$ga4_properties = $this->get_ga4_properties();

		//$properties = array_filter( $ua_properties + $ga4_properties );

		geodir_update_option( 'ga_properties', $ga4_properties );
		geodir_update_option( 'ga_data_stream', '' );
		geodir_update_option( 'ga_profile_view', '' );

		return $ga4_properties;
	}

	function get_ua_properties() {
		$properties = array();

		try {
			$profiles = $this->analytics->management_webproperties->listManagementWebproperties( '~all' );
		} catch ( Google_ServiceException $e ) {
			geodir_error_log( '[' . $e->getCode() . '] ' . $e->getMessage(), 'Analytics API Service Error', __FILE__, __LINE__ );
		}

		if ( ! empty( $profiles->items ) ) {
			foreach ( $profiles->items as $profile ) {
				$property = array();

				foreach ( $profile as $key => $value ) {
					$property[ $key ] = $value;
				}

				$property['pType'] = 'ua';

				$properties[ $profile->id ] = $property;
			}
		}

		return $properties;
	}

	function get_ga4_properties( $cached = false ) {
		$properties = array();

		try {
			$account_summaries = $this->getAccountSummaries();

			if ( is_wp_error( $account_summaries ) ) {
				geodir_error_log( $account_summaries->get_error_message(), 'Analytics API Service Error', __FILE__, __LINE__ );
			} else if ( ! empty( $account_summaries ) ) {
				foreach ( $account_summaries as $account ) {
					if ( ! empty( $account['propertySummaries'] ) ) {
						foreach ( $account['propertySummaries'] as $property ) {
							$property_id = str_replace( "properties/", "", $property['property'] );

							$property['accountId'] = str_replace( "accounts/", "", $property['parent'] );
							$property['accountName'] = $account['displayName'];
							$property['pType'] = 'ga4';

							$properties[ $property_id ] = $property;
						}
					}
				}
			}
		} catch ( Google_ServiceException $e ) {
			geodir_error_log( '[' . $e->getCode() . '] ' . $e->getMessage(), 'Analytics API Service Error', __FILE__, __LINE__ );
		}

		return $properties;
	}

	function getAnalyticsAccounts() {
		$analytics = new Google_Service_Analytics( $this->client );
		$accounts = $analytics->management_accounts->listManagementAccounts();
		$account_array = array();

		$items = $accounts->getItems();

		if ( count( $items ) > 0 ) {
			foreach ( $items as $key => $item ) {
				$account_id = $item->getId();

				$webproperties = $analytics->management_webproperties->listManagementWebproperties( $account_id );

				if ( !empty( $webproperties ) ) {
					foreach ( $webproperties->getItems() as $webp_key => $webp_item ) {
						$profiles = $analytics->management_profiles->listManagementProfiles( $account_id, $webp_item->id );

						$profile_id = $profiles->items[0]->id;
						array_push( $account_array, array( 'id' => $profile_id, 'ga:webPropertyId' => $webp_item->id ) );
					}
				}
			}

			return $account_array;
		}
		return false;

	}

	/**
	 * Sets the account id to use for queries
	 *
	 * @param id - the account id
	 **/
	function setAccount( $id ) {
		$this->accountId = $id;
	}

	/**
	 * Get a specific data metrics
	 *
	 * @param metrics - the metrics to get
	 * @param startDate - the start date to get
	 * @param endDate - the end date to get
	 * @param dimensions - the dimensions to grab
	 * @param sort - the properties to sort on
	 * @param filter - the property to filter on
	 * @param limit - the number of items to get
	 * @param realtime - if the realtime api should be used
	 * @return the specific metrics in array form
	 **/
	function getMetrics( $metric, $startDate, $endDate, $dimensions = false, $sort = false, $filter = false, $limit = false, $realtime = false ) {
		$analytics = new Google_Service_Analytics( $this->client );

		$params = array();

		if ( $dimensions ) {
			$params['dimensions'] = $dimensions;
		}
		if ( $sort ) {
			$params['sort'] = $sort;
		}
		if ( $filter ) {
			$params['filters'] = $filter;
		}
		if ( $limit ) {
			$params['max-results'] = $limit;
		}
		   
		// Just incase, the ga: is still used in the account id, strip it out to prevent it breaking
		$filtered_id = str_replace( 'ga:', '', $this->accountId );

		if ( ! $filtered_id ) {
			echo 'Error - Account ID is blank';
			return false;
		}

		if ( $realtime ) {
			return $analytics->data_realtime->get(
				'ga:' . $filtered_id,
				$metric,
				$params
			);
		} else {
			return $analytics->data_ga->get(
				'ga:' . $filtered_id,
				$startDate,
				$endDate,
				$metric,
				$params
			);
		}
	}

	/**
	 * Checks the date against Jan. 1 2005 because GA API only works until that date
	 *
	 * @param date - the date to compare
	 * @return the correct date
	 **/
	function verifyStartDate( $date ) {
		if ( strtotime( $date ) > strtotime( '2005-01-01' ) )
			return $date;
		else
			return '2005-01-01';
	}

	public function get_access_token() {
		$token = $this->client->getAccessToken();

		if ( null == $token || 'null' == $token || '[]' == $token || empty( $token ) ) {
			return null;
		}

		if ( is_scalar( $token ) ) {
			$token = json_decode( $token, true );
		}

		$token = is_array( $token ) && ! empty( $token['access_token'] ) ? $token['access_token'] : null;

		return $token;
	}

	public function get_request_headers() {
		$access_token = $this->get_access_token();

		$headers = array(
			'Authorization' => 'Bearer ' . $access_token,
			'User-Agent' => 'google-api-php-client/2.12.1',
			'x-goog-api-client' => sprintf( 'gl-php/%s gdcl/2.12.1', phpversion() )
		);

		return $headers;
	}

	public function getAccountSummaries() {
		$account_summaries = array();

		$query_args = array(
			'pageSize' => 200
		);

		$response = wp_remote_get( 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries/?' . build_query( $query_args ), 
			array(
				'headers' => $this->get_request_headers(),
				'timeout' => 15
			)
		);

		if ( ! is_wp_error( $response ) ) {
			if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( ! empty( $body['accountSummaries'] ) ) {
					$account_summaries = $body['accountSummaries'];
				}
			} else {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( ! empty( $body ) && $body['error'] && $body['error']['message'] ) {
					$errorText =  '[' . $body['error']['code'] . '] ' . $body['error']['message'];

					if ( isset( $_response['error_description'] ) ) {
						$errorText .= ": " . $_response['error_description'];
					}
				}

				return new WP_Error( 'geodir_ga_invalid_request', $errorText );
			}
		} else {
			$account_summaries = $response;
		}

		return $account_summaries;
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

	public function call( $method, $arguments, $servicePath = '', $rootUrl = '', $shouldDefer = false ) {
		$parameters = $arguments;

		// postBody is a special case since it's not defined in the discovery
		// document as parameter, but we abuse the param entry for storing it.
		$postBody = null;
		if (isset($parameters['postBody'])) {
			$postBody = json_encode($parameters['postBody']);

			unset($parameters['postBody']);
		}

		// TODO: optParams here probably should have been
		// handled already - this may well be redundant code.
		if ( isset( $parameters['optParams'] ) ) {
			$optParams = $parameters['optParams'];
			unset( $parameters['optParams'] );
			$parameters = array_merge( $parameters, $optParams );
		}

		if ( ! isset( $method['parameters'] ) ) {
			$method['parameters'] = array();
		}

		foreach ( $method['parameters'] as $paramName => $paramSpec ) {
			if ( isset( $parameters[$paramName] ) ) {
				$value = $parameters[$paramName];
				$parameters[$paramName] = $paramSpec;
				$parameters[$paramName]['value'] = $value;
				unset( $parameters[$paramName]['required'] );
			} else {
				if ( in_array( $paramName, array_keys( $parameters ) ) ) {
					unset( $parameters[ $paramName ] );
				}
			}
		}

		$url = Google_Http_REST::createRequestUri( $servicePath, $method['path'], $parameters );

		$httpRequest = new Google_Http_Request( $url, $method['httpMethod'], null, $postBody );

		if ( $rootUrl ) {
			$httpRequest->setBaseComponent( $rootUrl );
		} else {
			$httpRequest->setBaseComponent( $this->client->getBasePath() );
		}

		if ( $postBody ) {
			$contentTypeHeader = array();
			$contentTypeHeader['content-type'] = 'application/json; charset=UTF-8';
			$httpRequest->setRequestHeaders( $contentTypeHeader );
			$httpRequest->setPostBody( $postBody );
		}

		$httpRequest = $this->client->getAuth()->sign( $httpRequest );
		$httpRequest->setExpectedClass( null );

		if ( $shouldDefer ) {
			return $httpRequest;
		}

		return $this->client->execute( $httpRequest );
	}

	public function listPropertyDataStreams( $params = array() ) {
		$resource = $this->getResource( 'dataStreams' );

		return $this->call( $resource['methods']['list'], $params, $resource['servicePath'], $resource['rootUrl'] );
	}

	public function getDataStreams( $property_id, $limit = 50 ) {
		$data_streams = array();

		try {
			$data_streams = $this->listPropertyDataStreams( array( 'propertyId' => $property_id, 'pageSize' => 1 ) );
		} catch ( Google_ServiceException $e ) {
			geodir_error_log( '[' . $e->getCode() . '] ' . $e->getMessage(), 'Analytics API Service Error', __FILE__, __LINE__ );
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

		$data_streams = $this->getDataStreams( $property_id, 1 );

		if ( ! empty( $data_streams ) && ! is_wp_error( $data_streams ) && ! empty( $data_streams['dataStreams'] ) ) {
			$data_stream = $data_streams['dataStreams'][0];
		}

		geodir_update_option( 'ga_data_stream', $data_stream );

		return $data_stream;
	}

	public function runRealtimeReport( $params ) {
		$resource = $this->getResource( 'runRealtimeReport' );

		return $this->call( $resource['methods']['runRealtimeReport'], $params, $resource['servicePath'], $resource['rootUrl'] );
	}

	public function runReport( $params, $shouldDefer = false ) {
		$resource = $this->getResource( 'runReport' );

		return $this->call( $resource['methods']['runReport'], $params, $resource['servicePath'], $resource['rootUrl'], $shouldDefer );
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
				'limit'      => ''
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
		} else if ( ! empty( $args['dimensions'] ) && $args['dimensions'] == 'country' ) {
			$dimensions = array(
				array(
					'name' => 'country'
				)
			);
		} else {
			// @todo pagePath dimention is not supported in GA4. See https://developers.google.com/analytics/devguides/reporting/data/v1/realtime-api-schema
			if ( ! empty( $args['page_path'] ) ) {
				unset( $args['page_path'] );
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

		if ( ! empty( $args['metrics'] ) ) {
			$metrics = array(
				array(
					'name' => $args['metrics']
				)
			);
		}

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

		$metric = $params['postBody']['metrics'][0]['name'];

		if ( $metric == 'screenPageViews' ) {
			if ( ! empty( $params['postBody']['dateRanges'][0]['startDate'] ) && ! empty( $params['postBody']['dateRanges'][0]['endDate'] ) ) {
				$startDate = $params['postBody']['dateRanges'][0]['startDate'];
				$endDate = $params['postBody']['dateRanges'][0]['endDate'];
			} else {
				$startDate = '';
				$endDate = '';
			}

			if ( $type == 'country' ) {
				$data['rows'] = array();

				if ( ! empty( $response['rows'] ) ) {
					foreach ( $response['rows'] as $key => $row ) {
						if ( ! empty( $row['dimensionValues'][0]['value'] ) && isset( $row['metricValues'][0]['value'] ) ) {
							$data['rows'][] = array(
								$row['dimensionValues'][0]['value'],
								(int) $row['metricValues'][0]['value']
							);
						}
					}
				}
			} else if ( in_array( $type, array( 'week', 'lastweek', 'thisweek', 'month', 'lastmonth', 'thismonth' ) ) ) {
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
			} else if ( in_array( $type, array( 'year', 'lastyear', 'thisyear' ) ) ) {
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
			}

			if ( ! empty( $response['rowCount'] ) ) {
				$data['rowCount'] = (int) $response['rowCount'];
			} else {
				$data['rowCount'] = 0;
			}
		} else if ( $metric == 'activeUsers' ) {
			$data['totalActiveUsers'] = 0;

			if ( ! empty( $response['rows'] ) ) {
				if ( isset( $response['rows'][0]['metricValues'][0]['value'] ) ) {
					$data['totalActiveUsers'] = (int) $response['rows'][0]['metricValues'][0]['value'];
				}
			}
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

	function getReport( $property_id, $type, $start_date, $end_date, $dimensions, $page_path, $page_title, $limit ) {
		$params = $this->getRequestParams( $property_id, array( 'start_date' => $start_date, 'end_date' => $end_date, 'dimensions' => $dimensions, 'page_path' => $page_path, 'page_title' => $page_title, 'limit' => $limit ) );

		if ( ! empty( $type ) && $type != 'realtime' ) {
			$report = $this->runReport( $params );
		} else {
			$report = $this->runRealtimeReport( $params );
		}

		$report = $this->parseReport( $report, $params, $type );

		return $report;
	}
}