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
				<span class="text-success <?php echo ( $aui_bs5 ? 'fw-bold' : 'font-weight-bold' ); ?>"><i class="fas fa-check-circle"></i> <?php _e( 'Authorized', 'geodir-ga' ); ?></span>
				<?php if ( $auth_date = geodir_get_option( 'ga_auth_date' ) ) { ?>
				<br><small class="form-text d-block text-muted"><span class="description"><?php echo wp_sprintf( __( 'Last Authorized On: %s', 'geodir-ga' ), date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $auth_date ) ) ) . ' ' . date_default_timezone_get(); ?></span></small>
				<?php } ?>
				<br><small class="form-text d-block text-muted"><span class="description"><?php _e( 'Click on Deauthorize to disconnect your Google Analytics account.', 'geodir-ga' ); ?></span></small>
				<?php
				if ( ! empty( $gd_ga_errors ) ) {
					print_r( $gd_ga_errors );
				}
			} else {
				?>
				<a class="btn btn-sm btn-primary" href="<?php echo esc_url( GeoDir_Settings_Analytics::activation_url() ); ?>" target="_self" title="<?php esc_attr_e( 'Log in with your Google Analytics Account', 'geodir-ga' ); ?>"><?php _e( 'Log In with your Google Analytics Account', 'geodir-ga' ); ?></a>
				<small class="form-text d-block text-muted"><span class="description"><?php _e( 'Log in with your Google Analytics account to select analytics profile.', 'geodir-ga' ); ?></span></small>
				<?php
			}
			?>
		</div>
	</div>
	<script type="text/javascript">
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