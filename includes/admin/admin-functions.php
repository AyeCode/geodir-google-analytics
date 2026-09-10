<?php
/**
 * Google Analytics Admin Functions.
 *
 * @since 2.0.0
 * @package Geodir_Google_Analytics
 * @author AyeCode Ltd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add the plugin to uninstall settings.
 *
 * @since 2.0.0
 *
 * @return array $settings the settings array.
 * @return array The modified settings.
 */
function geodir_ga_uninstall_settings( $settings ) {
    array_pop( $settings );

	$settings[] = array(
		'name'     => __( 'Google Analytics', 'geodir-ga' ),
		'desc'     => __( 'Check this box if you would like to completely remove all of its data when Google Analytics is deleted.', 'geodir-ga' ),
		'id'       => 'uninstall_geodir_google_analytics',
		'type'     => 'checkbox',
	);
	$settings[] = array( 
		'type' => 'sectionend',
		'id' => 'uninstall_options'
	);

	return $settings;
}

function geodir_ga_google_analytics_field( $field ) {
	global $aui_bs5, $gd_ga_errors;

	?>
	<div data-argument="ga_auth_token" class="<?php echo ( $aui_bs5 ? 'mb-3' : 'form-group' ); ?> row">
		<label for="ga_auth_token" class="<?php echo ( $aui_bs5 ? 'fw-bold' : 'font-weight-bold' ); ?> col-sm-3 col-form-label"><?php echo $field['name'] ?></label>
		<div class="col-sm-9">
			<?php if ( geodir_get_option( 'ga_auth_token' ) ) { ?>
				<span class="btn btn-sm btn-danger <?php echo ( $aui_bs5 ? 'me-2' : 'mr-2' ); ?>" onclick="geodir_ga_deauthorize('<?php echo wp_create_nonce( 'gd_ga_deauthorize' ); ?>');"><?php _e( 'Deauthorize', 'geodir-ga' ); ?></span>
				<span class="btn btn-sm btn-outline-primary <?php echo ( $aui_bs5 ? 'me-2' : 'mr-2' ); ?>" onclick="geodir_ga_diagnostics('<?php echo wp_create_nonce( 'gd_ga_diagnostics' ); ?>');"><i class="fas fa-stethoscope"></i> <?php _e( 'Test connection', 'geodir-ga' ); ?></span>
				<span class="text-success <?php echo ( $aui_bs5 ? 'fw-bold' : 'font-weight-bold' ); ?>"><i class="fas fa-check-circle"></i> <?php _e( 'Authorized', 'geodir-ga' ); ?></span>
				<?php if ( $auth_date = geodir_get_option( 'ga_auth_date' ) ) { ?>
				<br><small class="form-text d-block text-muted"><span class="description"><?php echo wp_sprintf( __( 'Last Authorized On: %s', 'geodir-ga' ), date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $auth_date ) ) ) . ' ' . date_default_timezone_get(); ?></span></small>
				<?php } ?>
				<br><small class="form-text d-block text-muted"><span class="description"><?php _e( 'Click on Deauthorize to disconnect your Google Analytics account.', 'geodir-ga' ); ?></span></small>
				<div id="gd-ga-diagnostics" class="mt-3" style="display:none"></div>
				<?php if ( ! empty( $gd_ga_errors ) ) { $gd_ga_errors = array_unique( $gd_ga_errors ); ?>
				<div class="alert alert-danger mt-3 mb-0" role="alert"><?php echo implode( "<br>", $gd_ga_errors ); ?></div>
				<?php }
			} else { ?>
				<a class="btn btn-sm btn-primary" href="<?php echo esc_url( GeoDir_Settings_Analytics::activation_url() ); ?>" target="_self" title="<?php esc_attr_e( 'Log in with your Google Analytics Account', 'geodir-ga' ); ?>"><?php _e( 'Log In with your Google Analytics Account', 'geodir-ga' ); ?></a>
				<small class="form-text d-block text-muted"><span class="description"><?php _e( 'Log in with your Google Analytics account to select analytics profile.', 'geodir-ga' ); ?></span></small>
				<?php
			}
			?>
		</div>
	</div>
	<script type="text/javascript">
		function geodir_ga_diagnostics(nonce) {
			var box = jQuery('#gd-ga-diagnostics');
			box.show().html('<span class="text-muted"><i class="fas fa-sync fa-spin"></i> <?php echo esc_js( __( 'Testing connection...', 'geodir-ga' ) ); ?></span>');
			jQuery.ajax({
				url: geodir_params.ajax_url,
				type: 'POST',
				dataType: 'html',
				data: {
					action: 'geodir_ga_diagnostics',
					_wpnonce: nonce
				},
				success: function(data) {
					box.html(data);
				},
				error: function(xhr, textStatus) {
					box.html('<div class="alert alert-danger mb-0">' + textStatus + '</div>');
				}
			});
		}

		function geodir_ga_deauthorize(nonce) {
			var result = confirm(geodir_params.ga_confirm_delete);
			if (result) {
				jQuery.ajax({
					url: geodir_params.ajax_url,
					type: 'POST',
					dataType: 'html',
					data: {
						action: 'geodir_ga_deauthorize',
						_wpnonce: nonce
					},
					beforeSend: function() {},
					success: function(data, textStatus, xhr) {
						if (data) {
							window.location.assign(data);
						}
					},
					error: function(xhr, textStatus, errorThrown) {
						alert(textStatus);
					}
				});
			}
		}
	</script>
	<?php
}
/**
 * Gather Google Analytics connection diagnostics.
 *
 * @since 2.4.0
 *
 * @return array List of rows with label, status and value keys.
 */
function geodir_ga_run_diagnostics() {
	$rows = array();
	$api = new GeoDir_Google_Analytics_API();
	$token = $api->get_stored_token();

	// Token state.
	if ( empty( $token['access_token'] ) ) {
		$rows[] = array(
			'label' => __( 'Token', 'geodir-ga' ),
			'status' => 'error',
			'value' => __( 'Not authorized', 'geodir-ga' )
		);

		return $rows;
	}

	if ( ! empty( $token['created'] ) && isset( $token['expires_in'] ) ) {
		$expires_in = ( (int) $token['created'] + (int) $token['expires_in'] ) - time();
	} else {
		$expires_in = 0;
	}

	if ( $expires_in > 0 ) {
		$token_value = wp_sprintf( __( 'Valid, expires in %s', 'geodir-ga' ), human_time_diff( time(), time() + $expires_in ) );
		$token_status = 'ok';
	} else {
		$token_value = __( 'Expired, will refresh on next request', 'geodir-ga' );
		$token_status = empty( $token['refresh_token'] ) ? 'error' : 'warn';
	}

	if ( empty( $token['refresh_token'] ) ) {
		$token_value .= ' ' . __( '(no refresh token, re-authorization required)', 'geodir-ga' );
	}

	$rows[] = array(
		'label' => __( 'Token', 'geodir-ga' ),
		'status' => $token_status,
		'value' => $token_value
	);

	// Live connection test, this uses the Admin API so it does not spend report quota.
	$summaries = $api->getAccountSummaries();

	if ( is_wp_error( $summaries ) ) {
		$rows[] = array(
			'label' => __( 'Connection', 'geodir-ga' ),
			'status' => 'error',
			'value' => $summaries->get_error_message()
		);
	} else {
		$rows[] = array(
			'label' => __( 'Connection', 'geodir-ga' ),
			'status' => 'ok',
			'value' => wp_sprintf( _n( 'OK, %d account visible', 'OK, %d accounts visible', count( $summaries ), 'geodir-ga' ), count( $summaries ) )
		);
	}

	// Selected property.
	$property_id = geodir_get_option( 'ga_account_id' );

	if ( empty( $property_id ) ) {
		$rows[] = array(
			'label' => __( 'Property', 'geodir-ga' ),
			'status' => 'warn',
			'value' => __( 'None selected', 'geodir-ga' )
		);
	} else {
		$properties = geodir_get_option( 'ga_properties' );
		$name = ! empty( $properties[ $property_id ]['displayName'] ) ? $properties[ $property_id ]['displayName'] : __( 'Unknown', 'geodir-ga' );

		$rows[] = array(
			'label' => __( 'Property', 'geodir-ga' ),
			'status' => geodir_ga_type( $property_id ) == 'ga4' ? 'ok' : 'error',
			'value' => geodir_ga_type( $property_id ) == 'ga4'
				? wp_sprintf( '%s (%s)', $name, $property_id )
				: wp_sprintf( __( '%s is a Universal Analytics property and is no longer supported', 'geodir-ga' ), $property_id )
		);
	}

	// Measurement ID / data stream.
	$measurement_id = geodir_get_option( 'ga_measurement_id' );

	if ( empty( $measurement_id ) ) {
		$data_stream = geodir_get_option( 'ga_data_stream' );
		$measurement_id = ! empty( $data_stream['webStreamData']['measurementId'] ) ? $data_stream['webStreamData']['measurementId'] : '';
	}

	$rows[] = array(
		'label' => __( 'Measurement ID', 'geodir-ga' ),
		'status' => $measurement_id ? 'ok' : 'warn',
		'value' => $measurement_id ? $measurement_id : __( 'Not set, tracking code will not be output', 'geodir-ga' )
	);

	// Data API quota, recorded from the last report request.
	$quota = $api->get_property_quota();

	if ( empty( $quota['tokensPerDay'] ) ) {
		$rows[] = array(
			'label' => __( 'Data API quota', 'geodir-ga' ),
			'status' => 'warn',
			'value' => __( 'Not recorded yet, view a listing stats chart first', 'geodir-ga' )
		);
	} else {
		$consumed = isset( $quota['tokensPerDay']['consumed'] ) ? (int) $quota['tokensPerDay']['consumed'] : 0;
		$remaining = isset( $quota['tokensPerDay']['remaining'] ) ? (int) $quota['tokensPerDay']['remaining'] : 0;
		$total = $consumed + $remaining;
		$used_pct = $total > 0 ? ( $consumed / $total ) * 100 : 0;

		$rows[] = array(
			'label' => __( 'Data API quota', 'geodir-ga' ),
			'status' => $used_pct >= 80 ? 'warn' : 'ok',
			'value' => wp_sprintf(
				__( '%1$s of %2$s daily tokens used (%3$s%%), checked %4$s ago', 'geodir-ga' ),
				number_format_i18n( $consumed ),
				number_format_i18n( $total ),
				number_format_i18n( $used_pct, 1 ),
				! empty( $quota['checked'] ) ? human_time_diff( $quota['checked'] ) : __( 'unknown', 'geodir-ga' )
			)
		);
	}

	// Last recorded API error.
	$last_error = $api->get_last_error();

	if ( ! empty( $last_error['message'] ) ) {
		$rows[] = array(
			'label' => __( 'Last API error', 'geodir-ga' ),
			'status' => 'warn',
			'value' => wp_sprintf( __( '%1$s (%2$s ago)', 'geodir-ga' ), $last_error['message'], human_time_diff( $last_error['time'] ) )
		);
	} else {
		$rows[] = array(
			'label' => __( 'Last API error', 'geodir-ga' ),
			'status' => 'ok',
			'value' => __( 'None recorded', 'geodir-ga' )
		);
	}

	return apply_filters( 'geodir_ga_diagnostics_rows', $rows );
}

/**
 * Render diagnostics rows as HTML.
 *
 * @since 2.4.0
 *
 * @param array $rows Rows from geodir_ga_run_diagnostics().
 * @return string
 */
function geodir_ga_render_diagnostics( $rows ) {
	$icons = array(
		'ok' => '<i class="fas fa-check-circle text-success"></i>',
		'warn' => '<i class="fas fa-exclamation-triangle text-warning"></i>',
		'error' => '<i class="fas fa-times-circle text-danger"></i>'
	);

	$html = '<table class="table table-sm mb-0 gd-ga-diagnostics">';

	foreach ( $rows as $row ) {
		$icon = isset( $icons[ $row['status'] ] ) ? $icons[ $row['status'] ] : '';

		$html .= '<tr><td class="text-nowrap">' . $icon . ' ' . esc_html( $row['label'] ) . '</td><td>' . esc_html( $row['value'] ) . '</td></tr>';
	}

	$html .= '</table>';

	return $html;
}
