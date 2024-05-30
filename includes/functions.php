<?php
/**
 * Google analytics related functions.
 *
 * @since 1.0.0
 * @package GeoDirectory
 */

/**
 * Register widgets.
 *
 * @since 2.0.0.0
 *
 * @param array $widgets The list of available widgets.
 * @return array Available GD widgets.
 */
function goedir_ga_register_widgets( $widgets ) {
	if ( get_option( 'geodir_ga_version' ) ) {
		$widgets[] = 'GeoDir_Google_Analytics_Widget_Post_Analytics';
	}

	return $widgets;
}

/**
 * Formats seconds into to h:m:s.
 *
 * @since 1.0.0
 *
 * @param int  $sec The number of seconds.
 * @param bool $padHours Whether add leading zero for less than 10 hours. Default false.
 * @return string h:m:s format.
 */
function geodir_ga_sec2hms( $sec, $padHours = false ) {
    // holds formatted string
    $hms = "";
    // there are 3600 seconds in an hour, so if we
    // divide total seconds by 3600 and throw away
    // the remainder, we've got the number of hours
    $hours = intval(intval($sec) / 3600);

    // add to $hms, with a leading 0 if asked for
    $hms .= ($padHours) ? str_pad($hours, 2, "0", STR_PAD_LEFT) . ':' : $hours . ':';

    // dividing the total seconds by 60 will give us
    // the number of minutes, but we're interested in
    // minutes past the hour: to get that, we need to
    // divide by 60 again and keep the remainder
    $minutes = intval(($sec / 60) % 60);

    // then add to $hms (with a leading 0 if needed)
    $hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT) . ':';

    // seconds are simple - just divide the total
    // seconds by 60 and keep the remainder
    $seconds = intval($sec % 60);

    // add to $hms, again with a leading 0 if needed
    $hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);

    // done!
    return $hms;
}

/**
 * Get the google analytics via api.
 *
 * @since 1.0.0
 *
 * @param string $page Page url to use in analytics filters.
 * @param bool   $ga_start The start date of the data to include in YYYY-MM-DD format.
 * @param bool   $ga_end The end date of the data to include in YYYY-MM-DD format.
 * @return string Html text content.
 */
function geodir_ga_get_analytics( $page, $ga_start, $ga_end ) {
	$type = ! empty( $_REQUEST['ga_type'] ) ? sanitize_text_field( $_REQUEST['ga_type'] ) : 'realtime';
	$start_date = '';
	$end_date = '';
	$dimensions = '';
	$sort = '';
	$filters = "ga:pagePath==".$page;
	$metrics = "ga:pageviews";
	$realtime = false;
	$limit = false;

	if ( $type == 'thisweek' ) {
		$start_date = date( 'Y-m-d', strtotime( '-6 day' ) );
		$end_date = date( 'Y-m-d' );
		$dimensions = "ga:date,ga:nthDay";
		$_dimensions = 'date';
	} else if ( $type == 'lastweek' ) {
		$start_date = date( 'Y-m-d', strtotime( '-13 day' ) );
		$end_date = date( 'Y-m-d', strtotime( '-7 day' ) );
		$dimensions = "ga:date,ga:nthDay";
		$_dimensions = 'date';
	} else if ( $type == 'thismonth' ) {
		$start_date = date( 'Y-m-01' );
		$end_date = date( 'Y-m-d' );
		$dimensions = "ga:date,ga:nthDay";
		$_dimensions = 'date';
	} else if ( $type == 'lastmonth' ) {
		$start_date = date( 'Y-m-01', strtotime( '-1 month' ) );
		$end_date = date( 'Y-m-t', strtotime( '-1 month' ) );
		$dimensions = "ga:date,ga:nthDay";
		$_dimensions = 'date';
	} else if ( $type == 'thisyear' ) {
		$start_date = date( 'Y' )."-01-01";
		$end_date = date( 'Y-m-d' );
		$dimensions = "ga:month,ga:nthMonth";
		$_dimensions = 'month';
	} else if ( $type == 'lastyear' ) {
		$start_date = date( 'Y', strtotime( '-1 year' ) ) . "-01-01";
		$end_date = date( 'Y', strtotime( '-1 year' ) ) . "-12-31";
		$dimensions = "ga:month,ga:nthMonth";
		$_dimensions = 'month';
	} else if ( $type == 'country' ) {
		$start_date = "14daysAgo";
		$end_date = "today";
		$dimensions = "ga:country";
		$sort = "-ga:pageviews";
		$limit  = 10;
		$_dimensions = 'country';
	} else {
		$metrics = "rt:activeUsers";
		$realtime = true;
		$_dimensions = '';
	}

	# Create a new Gdata call
	$gaApi = new GeoDir_Google_Analytics_API();

	# Check if Google successfully logged in
	$check_token = $gaApi->checkLogin();

	if ( is_wp_error( $check_token ) ) {
		geodir_error_log( $check_token->get_error_message(), 'Google Analytics Error', __FILE__, __LINE__ );

		echo json_encode( array( 'error' => $check_token->get_error_message() ) );

		return false;
	} else if ( ! $check_token ) {
		echo json_encode( array( 'error' => __( 'Please check Google Analytics Settings', 'geodir-ga' ) ) );

		return false;
	}

	$stats = array();

	$property_id = geodir_get_option( 'ga_account_id' );

	if ( geodir_ga_type( $property_id ) == 'ga4' ) {
		$page_title = ! empty( $_REQUEST['ga_title'] ) ? sanitize_text_field( $_REQUEST['ga_title'] ) : '';

		$stats = $gaApi->getReport( $property_id, $type, $start_date, $end_date, $_dimensions, $page, $page_title, $limit );
	} else {
		$view = $gaApi->getView( $property_id );

		if ( empty( $view['id'] ) ) {
			echo json_encode( array( 'error' => __( 'Google Analytics account property view is not setup properly!', 'geodir-ga' ) ) );
			return false;
		}

		# Set the account to the one requested
		$gaApi->setAccount( $view['id'] );

		# Get the metrics needed to build the visits graph;
		try {
			$stats = $gaApi->getMetrics( $metrics, $start_date, $end_date, $dimensions, $sort, $filters, $limit , $realtime );
		} catch ( Exception $e ) {
			print 'GA Summary Widget - there was a service error ' . $e->getCode() . ':' . $e->getMessage();
		}
	}

	echo json_encode( $stats );
	exit;
}

function geodir_ga_type( $property_id = '' ) {
	if ( empty( $property_id ) ) {
		$property_id = geodir_get_option( 'ga_account_id' );
	}

	if ( ! empty( $property_id ) && strpos( strtolower( $property_id ), 'ua-' ) === false ) {
		$type = 'ga4';
	} else {
		$type = 'ua';
	}

	return $type;
}

function geodir_ga_get_token() {
    $at = geodir_get_option( 'gd_ga_access_token' );
    $use_url = "https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=" . $at;
    $response = wp_remote_get( $use_url, array( 'timeout' => 15 ) );

    if ( ! empty( $response['response']['code'] ) && $response['response']['code'] == 200 ) { // access token is valid
        return $at;
    } else { // get new access token
        if ( ! current_user_can( 'manage_options' ) ) {
            echo json_encode( array( 'error' => __( 'Invalid access.', 'geodir-ga' ) ) );
            exit;
        }

        $refresh_at = geodir_get_option( 'gd_ga_refresh_token' );
        if ( ! $refresh_at ) {
            echo json_encode( array( 'error' => __( 'Not authorized, please click authorized in GD > Google Analytics settings.', 'geodir-ga' ) ) );
            exit;
        }

        $rat_url = "https://www.googleapis.com/oauth2/v3/token?";
        $client_id = "client_id=" . geodir_get_option('ga_client_id');
        $client_secret = "&client_secret=" . geodir_get_option('ga_client_secret');
        $refresh_token = "&refresh_token=" . $refresh_at;
        $grant_type = "&grant_type=refresh_token";

        $rat_url_use = $rat_url . $client_id . $client_secret . $refresh_token . $grant_type;

        $rat_response = wp_remote_post( $rat_url_use, array( 'timeout' => 15 ) );
        if ( ! empty( $rat_response['response']['code'] ) && $rat_response['response']['code'] == 200 ) {
            $parts = json_decode( $rat_response['body'] );
            geodir_update_option( 'gd_ga_access_token', $parts->access_token );
            return $parts->access_token;
        } else {
            echo json_encode( array( 'error' => __( 'Login failed', 'geodir-ga' ) ) );
            exit;
        }
    }
}

/**
 * Outputs the google analytics section on details page.
 *
 * Outputs the google analytics html if the current logged in user owns the post.
 *
 * @global WP_Post|null $post The current post, if available.
 * @since 1.0.0
 * @package GeoDirectory
 */
function geodir_ga_display_analytics($args = array()) {
    global $aui_bs5, $post, $preview;

    if ( $preview || empty( $post ) ) {
		return;
	}

    $id = trim( geodir_get_option( 'ga_account_id' ) );
    $month_last_day = max( (int) date( 't' ), (int) date( 't', strtotime( '-1 month' ) ) );
    $month_days = array();
    for ( $d = 1; $d <= $month_last_day; $d++ ) {
        $month_days[] = $d;
    }

    if ( ! $id ) {
        return; // if no Google Analytics ID then bail.
    }

	if ( ! geodir_ga_check_post_google_analytics( $post ) ) {
		return;
	}

	$design_style = geodir_design_style();

	if ( empty( $args['height'] ) || absint( $args['height'] ) < 100 ) {
		$args['height'] = 200;
	}

	if ( $design_style ) {
		if ( empty( $args['btn_color'] ) ) {
			$args['btn_color'] = 'primary';
		}

		if ( $args['btn_size'] ) {
			switch ( $args['btn_size'] ) {
				case 'small':
					$args['btn_size'] = 'sm';
				break;
				case 'large':
					$args['btn_size'] = 'lg';
				break;
				case 'medium':
					$args['btn_size'] = '';
				break;
			}
		}
	}

	ob_start(); // Start buffering;
	/**
	 * This is called before the edit post link html in the function geodir_detail_page_google_analytics()
	 *
	 * @since 1.0.0
	 */
	do_action( 'geodir_before_google_analytics' );

	$refresh_time = geodir_get_option( 'ga_refresh_time', 5 );
	/**
	 * Filter the time interval to check & refresh new users results.
	 *
	 * @since 1.0.0
	 *
	 * @param int $refresh_time Time interval to check & refresh new users results.
	 */
	$refresh_time = apply_filters('geodir_google_analytics_refresh_time', $refresh_time);
	$refresh_time = absint( $refresh_time ) * 1000;

	$hide_refresh = geodir_get_option('ga_auto_refresh');

	$auto_refresh = $hide_refresh && $refresh_time && $refresh_time > 0 ? 1 : 0;
	if ( geodir_get_option( 'ga_stats' ) ) {
		$page_url  = urlencode( $_SERVER['REQUEST_URI'] );

		/* Here we list the shorthand days of the week so it can be used in translation. */
		__( "Mon", 'geodir-ga' );
		__( "Tue", 'geodir-ga' );
		__( "Wed", 'geodir-ga' );
		__( "Thu", 'geodir-ga' );
		__( "Fri", 'geodir-ga' );
		__( "Sat", 'geodir-ga' );
		__( "Sun", 'geodir-ga' );
		?>
<script type="text/javascript">
var gd_gaTimeOut,gd_gaTime=<?php echo absint( $refresh_time ); ?>,gd_gaHideRefresh=<?php echo (int) $hide_refresh; ?>,gd_gaAutoRefresh=<?php echo (int) $auto_refresh; ?>,gd_gaPageToken="<?php echo esc_attr( geodir_ga_get_page_access_token( $args['user_roles'] ) ); ?>",ga_data1=false,ga_data2=false,ga_data3=false,ga_data4=false,ga_data5=false,ga_data6=false,ga_au=0;
jQuery(function(){Chart.defaults.animationSteps=60;Chart.defaults.animationEasing="easeInOutQuart";Chart.defaults.responsive=true;Chart.defaults.maintainAspectRatio=false;jQuery(".gdga-show-analytics").on("click",function(e){jQuery(this).hide();jQuery(".gdga-analytics-box").show();gdga_weekVSweek();gdga_realtime(true)});if(gd_gaAutoRefresh!==1){jQuery("#gdga-loader-icon").on("click",function(e){gdga_refresh();clearTimeout(gd_gaTimeOut);gdga_realtime()})}});
function gdga_weekVSweek(){jQuery.ajax({url:"<?php echo ( admin_url( 'admin-ajax.php?action=geodir_ga_stats&ga_page=' . $page_url . '&ga_type=thisweek&ga_post=' . $post->ID ) ); ?>&pt="+gd_gaPageToken,beforeSend:function(){jQuery("#gdga-chart-container").css({opacity:.6})},success:function(result){ga_data1=jQuery.parseJSON(result);if(ga_data1.error){jQuery("#ga_stats").html(result);return}gd_renderWeekOverWeekChart();jQuery("#gdga-chart-container").css({opacity:1})},error:function(xhr,textStatus,errorThrown){jQuery("#gdga-chart-container").css({opacity:1})}});jQuery.ajax({url:"<?php echo ( admin_url( 'admin-ajax.php?action=geodir_ga_stats&ga_page=' . $page_url . '&ga_type=lastweek&ga_post=' . $post->ID ) ); ?>&pt="+gd_gaPageToken,success:function(result){ga_data2=jQuery.parseJSON(result);gd_renderWeekOverWeekChart()}});}
function gdga_monthVSmonth(){jQuery.ajax({url:"<?php echo ( admin_url( 'admin-ajax.php?action=geodir_ga_stats&ga_page=' . $page_url . '&ga_type=thismonth&ga_post=' . $post->ID ) ); ?>&pt="+gd_gaPageToken,beforeSend:function(){jQuery("#gdga-chart-container").css({opacity:.6})},success:function(result){ga_data1=jQuery.parseJSON(result);if(ga_data1.error){jQuery("#ga_stats").html(result);return}gd_renderMonthOverMonthChart();jQuery("#gdga-chart-container").css({opacity:1})},error:function(xhr,textStatus,errorThrown){jQuery("#gdga-chart-container").css({opacity:1})}});jQuery.ajax({url:"<?php echo ( admin_url( 'admin-ajax.php?action=geodir_ga_stats&ga_page=' . $page_url . '&ga_type=lastmonth&ga_post=' . $post->ID ) ); ?>&pt="+gd_gaPageToken,success:function(result){ga_data2=jQuery.parseJSON(result);gd_renderMonthOverMonthChart()}})}
function gdga_yearVSyear(){jQuery.ajax({url:"<?php echo ( admin_url( 'admin-ajax.php?action=geodir_ga_stats&ga_page=' . $page_url . '&ga_type=thisyear&ga_post=' . $post->ID ) ); ?>&pt="+gd_gaPageToken,beforeSend:function(){jQuery("#gdga-chart-container").css({opacity:.6})},success:function(result){ga_data3=jQuery.parseJSON(result);if(ga_data3.error){jQuery("#ga_stats").html(result);return}gd_renderYearOverYearChart();jQuery("#gdga-chart-container").css({opacity:1})},error:function(xhr,textStatus,errorThrown){jQuery("#gdga-chart-container").css({opacity:1})}});jQuery.ajax({url:"<?php echo ( admin_url( 'admin-ajax.php?action=geodir_ga_stats&ga_page=' . $page_url . '&ga_type=lastyear&ga_post=' . $post->ID ) ); ?>&pt="+gd_gaPageToken,success:function(result){ga_data4=jQuery.parseJSON(result);gd_renderYearOverYearChart()}})}
function gdga_country(){jQuery.ajax({url:"<?php echo ( admin_url( 'admin-ajax.php?action=geodir_ga_stats&ga_page=' . $page_url . '&ga_type=country&ga_post=' . $post->ID ) ); ?>&pt="+gd_gaPageToken,beforeSend:function(){jQuery("#gdga-chart-container").css({opacity:.6})},success:function(result){ga_data5=jQuery.parseJSON(result);if(ga_data5.error){jQuery("#ga_stats").html(result);return}gd_renderTopCountriesChart();jQuery("#gdga-chart-container").css({opacity:1})},error:function(xhr,textStatus,errorThrown){jQuery("#gdga-chart-container").css({opacity:1})}})}
function gdga_realtime(dom_ready){jQuery.ajax({url:"<?php echo ( admin_url( 'admin-ajax.php?action=geodir_ga_stats&ga_page=' . $page_url .'&ga_type=realtime&ga_post=' . $post->ID ) ); ?>"+geodir_analytics_title_arg()+"&pt="+gd_gaPageToken,success:function(result){ga_data6=jQuery.parseJSON(result);if(ga_data6.error){jQuery("#ga_stats").html(result);return}gd_renderRealTime(dom_ready)}})}
function geodir_analytics_title_arg(){var title=jQuery("title:first").text();if(title){title=encodeURIComponent(title)}return"&ga_title="+title}
function gd_renderRealTime(dom_ready){if(typeof dom_ready==="undefined"){gdga_refresh(true)}ga_au_old=ga_au;if(ga_data6&&typeof ga_data6=="object"&&ga_data6.activeUsers&&ga_data6.activeUsers.realtime){ga_au=typeof ga_data6.activeUsers.realtime.totalActiveUsers!="undefined"?ga_data6.activeUsers.realtime.totalActiveUsers:0}else{ga_au=ga_data6&&typeof ga_data6=="object"&&ga_data6.totalsForAllResults?ga_data6.totalsForAllResults["rt:activeUsers"]:0}if(ga_au>ga_au_old){jQuery(".gd-ActiveUsers").addClass("is-increasing")}if(ga_au<ga_au_old){jQuery(".gd-ActiveUsers").addClass("is-decreasing")}jQuery(".gd-ActiveUsers-value").html(ga_au);if(gd_gaTime>0&&gd_gaAutoRefresh===1){gd_gaTimeOut=setTimeout(function(){jQuery(".gd-ActiveUsers").removeClass("is-increasing is-decreasing");gdga_realtime()},gd_gaTime)}}
function gd_renderTopCountriesChart(){if (ga_data5){response = ga_data5;ga_data5 = false;} else{return;}jQuery('#gdga-chart-container').show();gdga_refresh(true);jQuery('.gdga-type-container').show();jQuery('#gdga-select-analytic').prop('disabled', false);var rows = [];if (typeof response.screenPageViews !== 'undefined'){try{rows = response['screenPageViews']['country']['rows'];} catch(err){}} else{rows = response.rows;}var labels = [];var values = [];var bgcolors = [];var colors = ['rgb(255,165,0)', 'rgb(255,0,0)', 'rgb(128,0,128)','rgb(54,162,235)', 'rgb(0,128,0)', 'rgb(0,0,255)', 'rgb(192,192,192)', 'rgb(128,0,0)', 'rgb(255,127,80)', '189,183,107)', 'rgb(255,215,0)'];if (rows.length){rows.forEach(function(row, i){labels[i] = row[0];values[i] = parseInt(row[1]);bgcolors[i] = colors[i];});var data ={labels:labels,datasets:[{label:'<?php echo addslashes( __( 'Countries', 'geodir-ga' ) );?>',data:values,backgroundColor:bgcolors,hoverOffset:4}]};new Chart(geodirGaMakeCanvas('gdga-chart-container'),{type:'doughnut',data:data});}else{gdga_noResults();}}
function gd_renderYearOverYearChart(){if(ga_data3&&ga_data4){if(typeof ga_data3.screenPageViews!=="undefined"){try{ga_data3.rows=ga_data3["screenPageViews"]["thisyear"]["rows"]}catch(err){}}if(typeof ga_data4.screenPageViews!=="undefined"){try{ga_data4.rows=ga_data4["screenPageViews"]["lastyear"]["rows"]}catch(err){}}thisYear=ga_data3;lastYear=ga_data4;ga_data3=false;ga_data4=false}else{return}jQuery("#gdga-chart-container").show();gdga_refresh(true);jQuery(".gdga-type-container").show();jQuery("#gdga-select-analytic").prop("disabled",false);var now=moment();Promise.all([thisYear, lastYear]).then(function(results){var data1=results && results[0] && results[0].rows ? results[0].rows.map(function(row){return +row[2];}):[];var data2=results && results[1] && results[1].rows ? results[1].rows.map(function(row){return +row[2];}):[];var labels=[geodir_ga_htmlEscape('<?php echo esc_js( __( 'Jan', 'geodir-ga' ) ); ?>'),geodir_ga_htmlEscape('<?php echo esc_js( __( 'Feb', 'geodir-ga' ) ); ?>'),geodir_ga_htmlEscape('<?php echo esc_js( __( 'Mar', 'geodir-ga' ) ); ?>'),geodir_ga_htmlEscape('<?php echo esc_js( __( 'Apr', 'geodir-ga' ) ); ?>'),geodir_ga_htmlEscape('<?php echo esc_js( __( 'May', 'geodir-ga' ) ); ?>'),geodir_ga_htmlEscape('<?php echo esc_js( __( 'Jun', 'geodir-ga' ) ); ?>'),geodir_ga_htmlEscape('<?php echo esc_js( __( 'Jul', 'geodir-ga' ) ); ?>'),geodir_ga_htmlEscape('<?php echo esc_js( __( 'Aug', 'geodir-ga' ) ); ?>'),geodir_ga_htmlEscape('<?php echo esc_js( __( 'Sep', 'geodir-ga' ) ); ?>'),geodir_ga_htmlEscape('<?php echo esc_js( __( 'Oct', 'geodir-ga' ) ); ?>'),geodir_ga_htmlEscape('<?php echo esc_js( __( 'Nov', 'geodir-ga' ) ); ?>'),geodir_ga_htmlEscape('<?php echo esc_js( __( 'Dec', 'geodir-ga' ) ); ?>')];for (var i=0,len=labels.length;i < len;i++){if(data1[i]===undefined) data1[i]=null;if (data2[i]===undefined) data2[i]=null;}var data={labels:labels,datasets:[{label:geodir_ga_htmlEscape('<?php echo esc_js( __( 'Last Year', 'geodir-ga' ) ); ?>'),borderColor:'rgb(255,159,64)',backgroundColor:'rgba(255,159,64,0.5)',data:data2},{label:geodir_ga_htmlEscape('<?php echo esc_js( __( 'This Year', 'geodir-ga' ) ); ?>'),borderColor:'rgb(54,162,235)',backgroundColor:'rgba(54,162,235,0.5)',data:data1}]};new Chart(geodirGaMakeCanvas('gdga-chart-container'),{type:'bar',data:data});}).catch(function(err){console.error(err.stack);})}
function gd_renderWeekOverWeekChart(){if(ga_data1&&ga_data2){if(typeof ga_data1.screenPageViews!=="undefined"){try{ga_data1.rows=ga_data1["screenPageViews"]["thisweek"]["rows"]}catch(err){}}if(typeof ga_data2.screenPageViews!=="undefined"){try{ga_data2.rows=ga_data2["screenPageViews"]["lastweek"]["rows"]}catch(err){}}thisWeek=ga_data1;lastWeek=ga_data2;ga_data1=false;ga_data2=false}else{return;}jQuery('#gdga-chart-container').show();gdga_refresh(true);jQuery('.gdga-type-container').show();jQuery('#gdga-select-analytic').prop('disabled', false);var now=moment();Promise.all([thisWeek, lastWeek]).then(function(results){var data1=results && results[0] && results[0].rows ? results[0].rows.map(function(row){return +row[2];}):[];var data2=results && results[1] && results[1].rows ? results[1].rows.map(function(row){return +row[2];}):[];var labels=[geodir_ga_htmlEscape("<?php echo esc_js( date_i18n( 'D', strtotime( "+1 day" ) ) ); ?>"),geodir_ga_htmlEscape("<?php echo esc_js( date_i18n( 'D', strtotime( "+2 day" ) ) ); ?>"),geodir_ga_htmlEscape("<?php echo esc_js( date_i18n( 'D', strtotime( "+3 day" ) ) ); ?>"),geodir_ga_htmlEscape("<?php echo esc_js( date_i18n( 'D', strtotime( "+4 day" ) ) ); ?>"),geodir_ga_htmlEscape("<?php echo esc_js( date_i18n( 'D', strtotime( "+5 day" ) ) ); ?>"),geodir_ga_htmlEscape("<?php echo esc_js( date_i18n( 'D', strtotime( "+6 day" ) ) ); ?>"),geodir_ga_htmlEscape("<?php echo esc_js( date_i18n( 'D', strtotime( "+7 day" ) ) ); ?>")];var data ={labels:labels,datasets:[{label:geodir_ga_htmlEscape('<?php echo esc_js( __( 'Last Week', 'geodir-ga' ) ); ?>'),borderColor:'rgb(255,159,64)',backgroundColor:'rgba(255,159,64,0.5)',data:data2},{label:geodir_ga_htmlEscape('<?php echo esc_js( __( 'This Week', 'geodir-ga' ) ); ?>'),borderColor:'rgb(54,162,235)',backgroundColor:'rgba(54,162,235,0.5)',data:data1}]};new Chart(geodirGaMakeCanvas('gdga-chart-container'),{type:'line',data:data});});}
function gd_renderMonthOverMonthChart(){if(ga_data1&&ga_data2){if(typeof ga_data1.screenPageViews!=="undefined"){try{ga_data1.rows=ga_data1["screenPageViews"]["thismonth"]["rows"]}catch(err){}}if(typeof ga_data2.screenPageViews!=="undefined"){try{ga_data2.rows=ga_data2["screenPageViews"]["lastmonth"]["rows"]}catch(err){}}thisMonth=ga_data1;lastMonth=ga_data2;ga_data1=false;ga_data2=false}else{return}jQuery("#gdga-chart-container").show();gdga_refresh(true);jQuery(".gdga-type-container").show();jQuery("#gdga-select-analytic").prop("disabled",false);var now=moment();Promise.all([thisMonth, lastMonth]).then(function(results){var data1=results && results[0] && results[0].rows ? results[0].rows.map(function(row){return +row[2];}):[];var data2=results && results[1] && results[1].rows ? results[1].rows.map(function(row){return +row[2];}):[];var labels=[<?php echo implode( ",", $month_days ) ?>];for (var i=0, len=labels.length; i < len; i++){if (data1[i] === undefined) data1[i]=null;if (data2[i] === undefined) data2[i]=0;}var data ={labels:labels,datasets:[{label:geodir_ga_htmlEscape('<?php echo esc_js( __( 'Last Month', 'geodir-ga' ) ); ?>'),borderColor:'rgb(255,159,64)',backgroundColor:'rgba(255,159,64,0.5)',data:data2},{label:geodir_ga_htmlEscape('<?php echo esc_js( __( 'This Month', 'geodir-ga' ) ); ?>'),borderColor:'rgb(54,162,235)',backgroundColor:'rgba(54,162,235,0.5)',data:data1}]};new Chart(geodirGaMakeCanvas('gdga-chart-container'),{type:'line',data:data});});}
function gdga_noResults(){jQuery('#gdga-chart-container').html('<p>' + geodir_ga_htmlEscape('<?php echo esc_js( __( 'No results available', 'geodir-ga' ) ); ?>') + '</p>');}
function geodirGaMakeCanvas(id){var container=document.getElementById(id);var canvas=document.createElement("canvas");var ctx=canvas.getContext("2d");container.innerHTML="";canvas.width=container.offsetWidth;canvas.height=container.offsetHeight;container.appendChild(canvas);return ctx}
function gdga_select_option(){jQuery("#gdga-select-analytic").prop("disabled",true);gdga_refresh();gaType=jQuery("#gdga-select-analytic").val();if(gaType=="weeks"){gdga_weekVSweek()}else if(gaType=="months"){gdga_monthVSmonth()}else if(gaType=="years"){gdga_yearVSyear()}else if(gaType=="country"){gdga_country()}}
function gdga_refresh(stop){if(typeof stop!=="undefined"&&stop){if(gd_gaAutoRefresh===1||gd_gaHideRefresh==1){jQuery("#gdga-loader-icon").hide()}else{jQuery("#gdga-loader-icon .fa-sync").removeClass("fa-spin")}}else{if(gd_gaAutoRefresh===1||gd_gaHideRefresh==1){jQuery("#gdga-loader-icon").show()}else{if(!jQuery("#gdga-loader-icon .fa-sync").hasClass("fa-spin")){jQuery("#gdga-loader-icon .fa-sync").addClass("fa-spin")}}}}
function geodir_ga_htmlEscape(str){return String(str).replace(/&prime;/g,"'").replace(/&frasl;/g,"/").replace(/&ndash;/g,"-").replace(/&ldquo;/g,'"').replace(/&gt;/g,">").replace(/&quot;/g,'"').replace(/&apos;/g,"'").replace(/&amp;quot;/g,'"').replace(/&amp;apos;/g,"'")}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.2.0/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<?php if ( $design_style ) {
	$btn_class = '';
	if ( ! empty( $args['btn_color'] ) ) {
		$btn_class .= ' btn-' . sanitize_html_class( $args['btn_color'] );
	}
	if ( ! empty( $args['btn_size'] ) ) {
		$btn_class .= ' btn-' . sanitize_html_class( $args['btn_size'] );
	}
	$btn_wrap_class = ' text-left';
	if ( ! empty( $args['btn_alignment'] ) ) {
		if ( $args['btn_alignment'] == 'block' ) {
			$btn_class .= ' w-100';
			$btn_wrap_class = '';
		} else {
			$btn_wrap_class .= ' text-' . sanitize_html_class( $args['btn_alignment'] );
		}
	}

	if ( $aui_bs5 ) {
		$btn_wrap_class = str_replace( array( '-left', '-right' ), array( '-start', '-end' ), $btn_wrap_class );
	}
	?>
		<div class="gdga-show-analytics<?php echo $btn_wrap_class; ?>"><button role="button" class="btn<?php echo $btn_class; ?>"><i class="fas fa-chart-bar <?php echo ( $aui_bs5 ? 'me-1' : 'mr-1' ); ?>" aria-hidden="true"></i><?php echo ! empty( $args['button_text'] ) ? esc_attr( $args['button_text'] ) : __('Show Google Analytics', 'geodir-ga');?></button></div>
		<div id="ga_stats" class="gdga-analytics-box card" style="display:none">
			<div class="card-header p-3">
				<div class="gd-ActiveUsers btn btn-sm btn-info py-1 px-2 align-middle <?php echo ( $aui_bs5 ? 'float-end' : 'float-right' ); ?>"><span id="gdga-loader-icon" class="<?php echo ( $aui_bs5 ? 'me-1' : 'mr-1' ); ?>" title="<?php esc_attr_e("Refresh", 'geodir-ga');?>"><i class="fas fa-sync fa-spin" aria-hidden="true"></i></span><?php _e("Active Users:", 'geodir-ga');?> <span class="gd-ActiveUsers-value badge <?php echo ( $aui_bs5 ? 'bg-light rounded-pill' : 'badge-light badge-pill' ); ?>">0</span></div>
				<div id="ga-analytics-title" class="h5 m-0 card-title align-middle"><i class="fas fa-chart-bar <?php echo ( $aui_bs5 ? 'me-1' : 'mr-1' ); ?>" aria-hidden="true"></i><?php _e("Analytics", 'geodir-ga');?></div>
			</div>
			<div class="card-body">
				<div class="gdga-type-container <?php echo ( $aui_bs5 ? 'mb-3' : 'form-group' ); ?>" style="display:none">
					<?php
					echo aui()->select( array(
						'id' => 'gdga-select-analytic',
						'name' => '',
						'title' => '',
						'placeholder' => '',
						'value' => '',
						'label_show' => false,
						'label' => '',
						'options' => array(
							'weeks' => __( "Last Week vs This Week", 'geodir-ga' ),
							'months' => __( "This Month vs Last Month", 'geodir-ga' ),
							'years' => __( "This Year vs Last Year", 'geodir-ga' ),
							'country' => __( "Top Countries", 'geodir-ga' ),
						),
						'select2' => true,
						'extra_attributes' => array(
							'onchange' => 'gdga_select_option();'
						),
					) );
					?>
				</div>
				<div class="Chartjs-figure w-100" id="gdga-chart-container" style="display:none;height:<?php echo absint( $args['height'] ); ?>px"></div>
			</div>
		</div>
<?php } else { ?>
<style>#gdga-chart-container{clear:both}.gdga-type-container{width:100%;display:block;clear:both}.gdga-type-container > .select2-container{width:100% !important}.geodir-details-sidebar-google-analytics{min-height:60px}#ga_stats #gd-active-users-container{float:right;margin:0 0 10px}#gdga-select-analytic{clear:both;width:100%}#ga_stats #ga-analytics-title{float:left;font-weight:bold}#ga_stats #gd-active-users-container{float:right}.Chartjs{font-size:.85em}.Chartjs-figure{height:<?php echo absint( $args['height'] ); ?>px;width:100%;display:none}.Chartjs-legend{list-style:none;margin:0;padding:1em 0 0;text-align:center;width:100%;display:none}.Chartjs-legend>li{display:inline-block;padding:.25em .5em}.Chartjs-legend>li>i{display:inline-block;height:1em;margin-right:.5em;vertical-align:-.1em;width:1em}@media (min-width:570px){.Chartjs-figure{margin-right:1.5em}}.gd-ActiveUsers{background:#f3f2f0;border:1px solid #d4d2d0;border-radius:4px;font-weight:300;padding:.5em 1.5em;white-space:nowrap}.gd-ActiveUsers-value{display:inline-block;font-weight:600;margin-right:-.25em}.gd-ActiveUsers.is-increasing{-webkit-animation:increase 3s;animation:increase 3s}.gd-ActiveUsers.is-decreasing{-webkit-animation:decrease 3s;animation:decrease 3s}@-webkit-keyframes increase{10%{background-color:#eaffea;border-color:hsla(120,100%,25%,.5);color:hsla(120,100%,25%,1)}}@keyframes increase{10%{background-color:#eaffea;border-color:hsla(120,100%,25%,.5);color:hsla(120,100%,25%,1)}}@-webkit-keyframes decrease{10%{background-color:#ffeaea;border-color:hsla(0,100%,50%,.5);color:red}}@keyframes decrease{10%{background-color:#ffeaea;border-color:hsla(0,100%,50%,.5);color:red}}#gdga-loader-icon svg,#gdga-loader-icon i{margin:0 10px 0 -10px;color:#333333;cursor:pointer}.#gdga-loader-icon .fa-spin{-webkit-animation-duration:1.5s;animation-duration:1.5s} </style>
        <button type="button" class="gdga-show-analytics"><?php echo !empty($args['button_text']) ? esc_attr($args['button_text']) : esc_html__( 'Show Google Analytics', 'geodir-ga' );?></button>
        <span id="ga_stats" class="gdga-analytics-box" style="display:none">
            <div id="ga-analytics-title"><?php _e("Analytics", 'geodir-ga');?></div>
            <div id="gd-active-users-container">
                <div class="gd-ActiveUsers"><span id="gdga-loader-icon" title="<?php esc_attr_e("Refresh", 'geodir-ga');?>"><i class="fas fa-sync fa-spin" aria-hidden="true"></i></span><?php _e("Active Users:", 'geodir-ga');?> <b class="gd-ActiveUsers-value">0</b>
                </div>
            </div>
            <div class="gdga-type-container" style="display:none">
				<select id="gdga-select-analytic" class="geodir-select" onchange="gdga_select_option();">
					<option value="weeks"><?php _e("Last Week vs This Week", 'geodir-ga');?></option>
					<option value="months"><?php _e("This Month vs Last Month", 'geodir-ga');?></option>
					<option value="years"><?php _e("This Year vs Last Year", 'geodir-ga');?></option>
					<option value="country"><?php _e("Top Countries", 'geodir-ga');?></option>
				</select>
			</div>
            <div class="Chartjs-figure" id="gdga-chart-container"></div>
        </span>
<?php } ?>
    <?php
    }
    /**
     * This is called after the edit post link html in the function geodir_detail_page_google_analytics()
     *
     * @since 1.0.0
     */
    do_action('geodir_after_google_analytics');
    $content_html = ob_get_clean();
    if ( trim( $content_html ) != '' ) {
        $content_html = '<div class="geodir-details-sidebar-google-analytics">' . $content_html . '</div>';
	}

    if ((int)geodir_get_option('geodir_disable_google_analytics_section') != 1) {
        /**
         * Filter the geodir_edit_post_link() function content.
         *
         * @param string $content_html The output html of the geodir_edit_post_link() function.
         */
        echo $content_html = apply_filters('geodir_google_analytic_html', $content_html);
    }
}

/**
 * Loads Google Analytics JS on header.
 *
 * WP Admin -> Geodirectory -> Settings -> Google Analytics -> Google analytics tracking code.
 *
 * @since 1.0.0
 * @package GeoDirectory
 */
function geodir_ga_add_tracking_code() {
	if ( geodir_get_option( 'ga_add_tracking_code' ) && ( $property_id = geodir_get_option( 'ga_account_id' ) ) ) {
		if ( geodir_ga_type( $property_id ) == 'ga4' ) {
			$measurement_id = geodir_get_option( 'ga_measurement_id' );

			if ( empty( $measurement_id ) ) {
				$data_stream = geodir_get_option( 'ga_data_stream' );

				if ( ! ( ! empty( $data_stream ) && ! empty( $data_stream['webStreamData']['measurementId'] ) ) ) {
					return;
				}

				$measurement_id = $data_stream['webStreamData']['measurementId'];
			}

?><meta name="generator" content="GeoDirectory Google Analytics v<?php echo (float) GEODIR_GA_VERSION; ?>" /><script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo rawurlencode( $measurement_id ); ?>"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js', new Date());gtag('config', '<?php echo esc_attr( $measurement_id ); ?>');</script>
<?php
		} else {
?><meta name="generator" content="GeoDirectory Google Analytics v<?php echo (float) GEODIR_GA_VERSION; ?>" /><script>(function(i,s,o,g,r,a,m){ i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');ga('create', '<?php echo esc_attr( $property_id ); ?>', 'auto');<?php if ( geodir_get_option( 'ga_anonymize_ip' ) ) { echo "ga('set', 'anonymizeIP', true);"; } ?>ga('send', 'pageview');</script>
<?php
		}
	} elseif ( ( $tracking_code = geodir_get_option( 'ga_tracking_code' ) ) && ! geodir_get_option( 'ga_account_id' ) ) {
		echo stripslashes( geodir_get_option( 'ga_tracking_code' ) );
	}
}

function geodir_ga_check_post_google_analytics( $post ) {
	$package = geodir_get_post_package( $post );

	$check = ! empty( $package->google_analytics ) ? true : false;

	return apply_filters( 'geodir_ga_check_post_google_analytics', $check, $post );
}

/**
 * Generate a specific access token for a page and access level.
 * 
 * @param string $access_level
 *
 * @return string
 */
function geodir_ga_get_page_access_token( $access_level = 'administrator', $path = '' ) {
	$token = '';
	$path = $path ? wp_unslash( $path ) : wp_unslash( $_SERVER['REQUEST_URI'] );

	if ( $path && $access_level ) {
		$token = wp_hash( $path . $access_level );
	}

	return $token;
}

/**
 * Check if a page access token is valid for the specific user type.
 *
 * @param $token
 * @param $path
 *
 * @return bool
 */
function geodir_ga_validate_page_access_token( $token, $path ) {
	$result = false;
	$user_id = get_current_user_id();

	if ( $token ) {
		if ( $token == geodir_ga_get_page_access_token( 'all', $path ) ) {
			$result = true;
		} elseif ( $user_id && $token == geodir_ga_get_page_access_token( 'all-logged-in', $path ) ) {
			$result = true;
		} elseif ( $user_id && $token == geodir_ga_get_page_access_token( 'author', $path ) ) {
			$result = true;
		} elseif ( $user_id && $token == geodir_ga_get_page_access_token( 'owner', $path ) ) {
			$result = true;
		} elseif ( $user_id && $token == geodir_ga_get_page_access_token( 'owner,administrator', $path ) ) {
			$result = true;
		} elseif ( $user_id && current_user_can( 'manage_options' ) && $token == geodir_ga_get_page_access_token( 'administrator', $path ) ) {
			$result = true;
		}
	}

	return $result;
}
