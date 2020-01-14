<?php
/*
Plugin Name: WP Client Reports
Plugin URI: http://switchthemes.com/wp-client-reports/
Description: Track Plugin and Theme Updates and send them as Client Reports
Version: 1.0
Author: Jesse Sutherland
Author URI: http://jessesutherland.com/
Text Domain: uptrack
Domain Path: /languages/
*/

if( !defined( 'ABSPATH' ) )
	exit;

function wp_client_reports_scripts() {

    wp_enqueue_style( 'wp-client-reports-css', plugin_dir_url( __FILE__ ) . '/css/wp-client-reports.css', array(), '1.0' );

    $screen = get_current_screen();
    if($screen && $screen->id == 'dashboard_page_wp_client_reports') {

        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_script( 'moment-js', plugin_dir_url( __FILE__ ) . '/js/moment.js', array(), '2.24.0', true );
        wp_enqueue_script('thickbox');
        wp_enqueue_style( 'thickbox' );
        wp_register_script( 'wp-client-reports-js', plugin_dir_url( __FILE__ ) . '/js/wp-client-reports.js', array('jquery','jquery-ui-datepicker'), '1.0', true );
        $date_format = get_option('date_format');
        $js_data = array(
            'php_date_format' => $date_format,
        );
        wp_localize_script( 'wp-client-reports-js', 'wp_client_reports_data', $js_data );
        wp_enqueue_script( 'wp-client-reports-js' );

    }

}
add_action( 'admin_print_scripts', 'wp_client_reports_scripts' );
//add_action( 'admin_print_scripts-$hook', 'wp_client_reports_scripts' );



global $wp_client_reports_version;
$wp_client_reports_version = '1.0';

register_activation_hook( __FILE__, 'wp_client_reports_data_install' );
function wp_client_reports_data_install() {
	global $wpdb;
	global $wp_client_reports_version;

	$wp_client_reports_table_name = $wpdb->prefix . 'update_tracking';

	$charset_collate = $wpdb->get_charset_collate();

	$wp_client_reports_sql = "CREATE TABLE $wp_client_reports_table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		date date DEFAULT '0000-00-00' NOT NULL,
        type varchar(191),
        name varchar(191),
        slug varchar(191),
        version_before varchar(191),
        version_after varchar(191),
        active tinyint(1),
		UNIQUE KEY id (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $wp_client_reports_sql );

	add_option( 'wp_client_reports_version', $wp_client_reports_version );
}

//On plugin activation schedule our daily database backup 
register_activation_hook( __FILE__, 'wp_client_reports_check_for_updates_daily_schedule' );
function wp_client_reports_check_for_updates_daily_schedule(){
    //Use wp_next_scheduled to check if the event is already scheduled
    $timestamp = wp_next_scheduled( 'wp_client_reports_check_for_updates_daily' );

    //If $timestamp == false schedule daily backups since it hasn't been done previously
    if( $timestamp == false ){
        //Schedule the event for right now, then to repeat daily using the hook 'wp_client_reports_check_for_updates_daily'
        wp_schedule_event( time(), 'daily', 'wp_client_reports_check_for_updates_daily' );
    }
}

//Hook our function , wp_client_reports_check_for_updates(), into the action wp_client_reports_check_for_updates_daily
add_action( 'wp_client_reports_check_for_updates_daily', 'wp_client_reports_check_for_updates' );
//add_action( 'init', 'wp_client_reports_check_for_updates' );
function wp_client_reports_check_for_updates() {

    global $wpdb;
    $wp_client_reports_table_name = $wpdb->prefix . 'update_tracking';

    $timezone_string = get_option('timezone_string');
    if ($timezone_string) {
        date_default_timezone_set(get_option('timezone_string'));
    }
    
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $mysqldate = date('Y-m-d');

    $wordpress_version = get_bloginfo( 'version' );

    $last_wp_update = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'wp' AND `slug` = %s ORDER BY `date` DESC", array('wp') ) );

    $today_wp_update = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'wp' AND slug = %s AND date = %s", array('wp', $mysqldate) ) );

    if (!$last_wp_update || version_compare($wordpress_version, $last_wp_update->version_after, '>')) {

        $last_version = null;
        if ($last_wp_update) {
            $last_version = $last_wp_update->version_after;
        }

        $update_id = null;
        if ($today_wp_update) {
            $update_id = $today_wp_update->id;
        }

        $wp_update = array(
            'id' => $update_id,
            'date' => $mysqldate,
            'type' => 'wp',
            'name' => 'WordPress',
            'slug' => 'wp',
            'version_before' => $last_version,
            'version_after' => $wordpress_version,
            'active' => null,
        );

        wp_client_reports_track_update($wp_update);

    }
    
    $themes = wp_get_themes();

    foreach($themes as $theme_slug => $theme) {

        $theme_active = false;
        $active_theme = get_option('stylesheet');

        if ( $theme_slug == $active_theme ) {
            $theme_active = true;
        } 

        $last_theme_update = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'theme' AND `slug` = %s ORDER BY `date` DESC", array($theme_slug) ) );

        $today_theme_update = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'theme' AND slug = %s AND date = %s", array($theme_slug, $mysqldate) ) );

        if (!$last_theme_update || version_compare($theme->get( 'Version'), $last_theme_update->version_after, '>')) {

            $last_version = null;
            if ($last_theme_update) {
                $last_version = $last_theme_update->version_after;
            }

            $update_id = null;
            if ($today_theme_update) {
                $update_id = $today_theme_update->id;
            }

            $theme_update = array(
                'id' => $update_id,
                'date' => $mysqldate,
                'type' => 'theme',
                'name' => $theme['Name'],
                'slug' => $theme_slug,
                'version_before' => $last_version,
                'version_after' => $theme['Version'],
                'active' => $theme_active,
            );

            wp_client_reports_track_update($theme_update);
        }

    }

    $plugins = get_plugins();

    foreach($plugins as $plugin_slug => $plugin) {

        $plugin_active = false;
        if ( is_plugin_active( $plugin_slug ) ) {
            $plugin_active = true;
        } 

        $last_plugin_update = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'plugin' AND `slug` = %s ORDER BY `date` DESC", array($plugin_slug) ) );

        $today_plugin_update = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $wp_client_reports_table_name WHERE `type` = 'plugin' AND `slug` = %s AND `date` = %s", array($plugin_slug, $mysqldate) ) );

        if (!$last_plugin_update || version_compare($plugin['Version'], $last_plugin_update->version_after, '>')) {

            $last_version = null;
            if ($last_plugin_update) {
                $last_version = $last_plugin_update->version_after;
            }

            $update_id = null;
            if ($today_plugin_update) {
                $update_id = $today_plugin_update->id;
            }
            
            $plugin_update = array(
                'id' => $update_id,
                'date' => $mysqldate,
                'type' => 'plugin',
                'name' => $plugin['Name'],
                'slug' => $plugin_slug,
                'version_before' => $last_version,
                'version_after' => $plugin['Version'],
                'active' => $plugin_active,
            );

            wp_client_reports_track_update($plugin_update);

        }
		
    }
}


function wp_client_reports_track_update( $thing_to_track ) {

    global $wpdb;
    $wp_client_reports_table_name = $wpdb->prefix . 'update_tracking';

	$new_entry = $wpdb->replace(
        $wp_client_reports_table_name,
        $thing_to_track,
        array(
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
        )
    );

    return $new_entry;

}

// add_action( 'wp_loaded', 'wp_client_reports_process_forms' );
// function wp_client_reports_process_forms(){
//     if( isset( $_POST['hsno_spark_submit'] ) ):
//         if ($_POST['hsno_spark_submit'] == 'yes') :
// 	        wp_redirect( admin_url('admin-ajax.php') . "?action=spark_export" );
// 	        exit();
// 		endif;
//     endif;
// 	if( isset( $_POST['hsno_crack_submit'] ) ):
//         if ($_POST['hsno_crack_submit'] == 'yes') :
// 	        wp_redirect( admin_url('admin-ajax.php') . "?action=crack_export" );
// 	        exit();
// 		endif;
//     endif;
// }

/**
 * Add a widget to the dashboard.
 */
function wp_client_reports_add_dashboard_widget() {
	wp_add_dashboard_widget(
		'wp_client_reports_last30_widget',         // Widget slug.
		'Updates Run - Last 30 Days',         // Title.
		'wp_client_reports_last30_widget_function' // Display function.
	);
}
add_action( 'wp_dashboard_setup', 'wp_client_reports_add_dashboard_widget' );

/**
 * Create the function to output the contents of our Dashboard Widget.
 */
function wp_client_reports_last30_widget_function() {
    $data = wp_client_reports_get_stats_data();
    ?>
    <div class="wp-client-reports-big-numbers wp-client-reports-big-numbers-widget">
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-wp-update-count"><?php echo $data->wp_updated; ?></h2>
            <h3>WordPress <br>Updated</h3>
        </div><!-- .wp-client-reports-big-number -->
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-theme-update-count"><?php echo $data->total_themes_updated; ?></h2>
            <h3>Themes <br>Updated</h3>
        </div><!-- .wp-client-reports-big-number -->
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-plugin-update-count"><?php echo $data->total_plugins_updated; ?></h2>
            <h3>Plugins <br>Updated</h3>
        </div><!-- .wp-client-reports-big-number -->
    </div><!-- .wp-client-reports-big-numbers -->
<?php
}


add_action( 'admin_menu', 'wp_client_reports_add_admin_menu' );
function wp_client_reports_add_admin_menu(  ) {
    add_options_page( 'WP Client Report Options', 'WP Client Report Options', 'manage_options', 'wp_client_reports', 'wp_client_reports_options_page' );
    add_submenu_page( 'index.php', 'Reports', 'Reports', 'manage_options', 'wp_client_reports', 'wp_client_reports_stats_page');
}


function wp_client_reports_stats_page() {
	?>
        <div class="wp-client-reports-stats-screen">
            <div class="wp-client-reports-header">
                <h1>WP Client Reports Update Stats</h1>
                <div class="wp-client-reports-date-chooser-area">
                    <a href="#TB_inline?width=600&height=550&inlineId=wp-client-reports-which-email-modal" id="wp-client-reports-email-report" class="thickbox button wp-client-reports-email-report-button">Email Report <span class="dashicons dashicons-email"></span></a>
                    <button id="wp-client-reports-force-update" class="button wp-client-reports-force-update-button">Check For Updates <span class="dashicons dashicons-update-alt"></span></button>
                    <button id="wp-client-reports-date-chooser-button" class="button button-primary wp-client-reports-date-chooser-button"><span id="wp-client-reports-button-label">Last 30 Days</span> <span class="dashicons dashicons-arrow-down"></span></button><!-- #wp-client-reports-date-chooser-menu -->
                    <div id="wp-client-reports-date-chooser" style="display:none;">
                        <div class="date-chooser-presets">
                            <ul>
                                <li><a href="#" id="wp-client-reports-quick-today">Today</a></li>
                                <li><a href="#" id="wp-client-reports-quick-yesterday">Yesterday</a></li>
                                <li><a href="#" id="wp-client-reports-quick-last7">Last 7 Days</a></li>
                                <li><a href="#" id="wp-client-reports-quick-last14">Last 14 Days</a></li>
                                <li><a href="#" id="wp-client-reports-quick-last30">Last 30 Days</a></li>
                                <li><a href="#" id="wp-client-reports-quick-lastmonth">Last Month</a></li>
                                <li><a href="#" id="wp-client-reports-quick-thismonth">This Month</a></li>
                            </ul>
                        </div>
                        <div id="date-range"></div>
                        <div class="date-chooser-footer">
                            <span class="wp-client-reports-dates"><span id="wp-client-reports-start-date"></span> - <span id="wp-client-reports-end-date"></span></span> <button class="button" id="wp-client-reports-cancel">Cancel</button> <button class="button button-primary" id="wp-client-reports-apply">Apply</button>
                        </div><!-- .date-chooser-footer -->
                        <input type="hidden" id="from_value" class="from_value" name="from_value"/><input type="hidden" id="to_value"  class="to_value" name="to_value"/>
                    </div><!-- #wp-client-reports-date-chooser -->
                </div><!-- .wp-client-reports-date-chooser-area -->
            </div><!-- .wp-client-reports-header -->
            <div class="postbox wp-client-reports-postbox-big-numbers">
                <div class="inside">
                    <div class="wp-client-reports-big-numbers">
                        <div class="wp-client-reports-big-number">
                            <h2 id="wp-client-reports-total-update-count">0</h2>
                            <h3>Total <br>Updates</h3>
                        </div><!-- .wp-client-reports-big-number -->
                        <div class="wp-client-reports-big-number">
                            <h2 id="wp-client-reports-wp-update-count">0</h2>
                            <h3>WordPress <br>Updated</h3>
                        </div><!-- .wp-client-reports-big-number -->
                        <div class="wp-client-reports-big-number">
                            <h2 id="wp-client-reports-theme-update-count">0</h2>
                            <h3>Themes <br>Updated</h3>
                        </div><!-- .wp-client-reports-big-number -->
                        <div class="wp-client-reports-big-number">
                            <h2 id="wp-client-reports-plugin-update-count">0</h2>
                            <h3>Plugins <br>Updated</h3>
                        </div><!-- .wp-client-reports-big-number -->
                    </div><!-- .wp-client-reports-big-numbers -->
                </div><!-- .inside -->
            </div><!-- .postbox -->

            <h3>WordPress Updates</h3>
            <ul id="wp-client-reports-wp-updates-list" class="wp-client-reports-updates-list"></ul>

            <h3>Plugin Updates</h3>
            <ul id="wp-client-reports-plugin-updates-list" class="wp-client-reports-updates-list"></ul>

            <h3>Theme Updates</h3>
            <ul id="wp-client-reports-theme-updates-list" class="wp-client-reports-updates-list"></ul>

            <div id="wp-client-reports-which-email-modal" class="wp-client-reports-which-email-modal" style="display:none;">
                <form method="GET" action="#" id="wp-client-reports-send-email-report">
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="report-email">Send Email To</label></th>
                                <td><input name="report_email" type="email" id="report-email" value="<?php echo get_bloginfo('admin_email'); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="report-intro">Email Introduction</label></th>
                                <td><textarea name="report_intro" id="report-intro" class="large-text"></textarea></td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="hidden" name="action" value="wp_client_reports_send_email_report">
                    <input type="hidden" name="start" class="from_value" id="start_date_email">
                    <input type="hidden" name="end" class="to_value" id="end_date_email">
                    <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Send Now"></p>
                </form>
            </div>

        </div><!-- .wp-client-reports-stats-screen -->
	<?php
}


add_action('wp_ajax_wp_client_reports_stats_data', 'wp_client_reports_stats_data');
function wp_client_reports_stats_data() {

    if (isset($_GET['start']) && isset($_GET['end'])) {
        $start_date = date_create_from_format('Y-m-d', $_GET['start']);
        $start_date = $start_date->format('Y-m-d');
        $end_date = date_create_from_format('Y-m-d', $_GET['end']);
        $end_date = $end_date->format('Y-m-d');
    } else {
        $timezone_string = get_option('timezone_string');
        if ($timezone_string) {
            date_default_timezone_set(get_option('timezone_string'));
        }
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
    }

    $data = wp_client_reports_get_stats_data($start_date, $end_date);
    print json_encode($data);
    wp_die();

}


function wp_client_reports_get_stats_data($start_date, $end_date) {

    global $wpdb;
    $wp_client_reports_table_name = $wpdb->prefix . 'update_tracking';

    $data = new \stdClass;

    $data->updates = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `version_before` IS NOT NULL AND `date` >= %s AND `date` <= %s ORDER BY `date` ASC", array($start_date, $end_date) ) );

    $data->total_updates = 0;
    $data->total_themes_updated = 0;
    $data->total_plugins_updated = 0;
    $data->wp_updated = 0;

    foreach($data->updates as $update) {
        $data->total_updates++;
        if ($update->type == 'theme') {
            $data->total_themes_updated++;
        }
        if ($update->type == 'plugin') {
            $data->total_plugins_updated++;
        }
        if ($update->type == 'wp') {
            $data->wp_updated++;
        }
    }

    return $data;
    
}


add_action('wp_ajax_wp_client_reports_force_update', 'wp_client_reports_force_update');
function wp_client_reports_force_update() {
    wp_client_reports_check_for_updates();
    print json_encode(['status'=>'success']);
    wp_die();
}

add_action('wp_ajax_wp_client_reports_send_email_report', 'wp_client_reports_send_email_report');
function wp_client_reports_send_email_report() {

    $report_email = get_bloginfo('admin_email');
    if (isset($_GET['report_email'])) {
        $report_email = $_GET['report_email'];
    }

    $report_intro = null;
    if (isset($_GET['report_intro'])) {
        $report_intro = $_GET['report_intro'];
    }

    if (isset($_GET['start']) && isset($_GET['end'])) {
        $start_date = date_create_from_format('Y-m-d', $_GET['start']);
        $start_date = $start_date->format('Y-m-d');
        $end_date = date_create_from_format('Y-m-d', $_GET['end']);
        $end_date = $end_date->format('Y-m-d');
    } else {
        $timezone_string = get_option('timezone_string');
        if ($timezone_string) {
            date_default_timezone_set(get_option('timezone_string'));
        }
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
    }

    $data = wp_client_reports_get_stats_data($start_date, $end_date);

    $date_format = get_option('date_format');
    
    $start_date_formatted = date($date_format, strtotime($start_date));
    $end_date_formatted = date($date_format, strtotime($end_date));

    ob_start();

    include("email/report-email-header.php");
    
    ?>
    
        <!-- start copy -->
        <tr>
        <td bgcolor="#ffffff" align="left" style="padding: 40px 40px 20px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
            <h1 style="margin: 0 0 12px; font-size: 30px; font-weight: bold; line-height: 42px; color: #007cba; "><?php echo get_bloginfo('name'); ?> Site Report</h1>
            <p style="margin: 0; color:#212529;">From <?php echo $start_date_formatted; ?> - <?php echo $end_date_formatted; ?></p>
        </td>
        </tr>
        <!-- end copy -->

        <?php if($report_intro) : ?>
            <!-- start copy -->
            <tr>
            <td bgcolor="#ffffff" align="left" style="padding: 10px 40px 20px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                <p style="margin: 0; color:#212529;"><?php echo $report_intro; ?></p>
            </td>
            </tr>
            <!-- end copy -->
        <?php endif; ?>
        
        <!-- start copy -->
        <tr>
            <td align="left" bgcolor="#ffffff" style="padding: 0px 40px 0px 40px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <td align="center" width="250" style="padding: 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                    <h1 style="font-weight: bold; color: #007cba; margin: 0px; font-size: 66px; line-height: 1em;"><?php echo $data->total_updates; ?></h1>
                    <h5 style="text-transform: uppercase; color: #888888; font-size: 16px; line-height:18px; font-weight: 300; margin: 0px;">Total <br>Updates</h5>
                </td>
                <td bgcolor="#ffffff" align="center" width="20">&nbsp;</td>
                <td align="center" width="250" style="padding: 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                    <h1 style="font-weight: bold; color: #007cba; margin: 0px; font-size: 66px; line-height: 1em;"><?php echo $data->wp_updated; ?></h1>
                    <h5 style="text-transform: uppercase; color: #888888; font-size: 16px; line-height:18px; font-weight: 300; margin: 0px;">WordPress <br>Updated</h5>
                </td>
                </table>
            </td>
        </tr>
        <!-- end copy -->

        <!-- start copy -->
        <tr>
            <td align="left" bgcolor="#ffffff" style="padding: 0px 40px 0px 40px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <td align="center" width="250" style="padding: 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                    <h1 style="font-weight: bold; color: #007cba; margin: 0px; font-size: 66px; line-height: 1em;"><?php echo $data->total_themes_updated; ?></h1>
                    <h5 style="text-transform: uppercase; color: #888888; font-size: 16px; line-height:18px; font-weight: 300; margin: 0px;">Themes <br>Updated</h5>
                </td>
                <td bgcolor="#ffffff" align="center" width="20">&nbsp;</td>
                <td align="center" width="250" style="padding: 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                    <h1 style="font-weight: bold; color: #007cba; margin: 0px; font-size: 66px; line-height: 1em;"><?php echo $data->total_plugins_updated; ?></h1>
                    <h5 style="text-transform: uppercase; color: #888888; font-size: 16px; line-height:18px; font-weight: 300; margin: 0px;">Plugins <br>Updated</h5>
                </td>
                </table>
            </td>
        </tr>
        <!-- end copy -->
        
        <!-- start copy -->
        <tr>
        <td bgcolor="#ffffff" align="left" style="padding: 20px 40px 40px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 14px; line-height: 20px;">
            <h3 style="font-size:14px;margin:0px 0px 4px 0px;">WordPress Updates</h3>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top:solid 1px #dddddd;margin-bottom:30px;">
            <?php
            if ($data->wp_updated > 0) : 
                foreach($data->updates as $update) :
                    if ($update->type == 'wp') :
                        echo '<tr><td style="width:40%;padding:8px 8px 8px 0px;border-bottom:solid 1px #dddddd;">' . $update->name . '</td><td style="text-align:center;width:30%;padding:8px;border-bottom:solid 1px #dddddd;"">' . $update->version_before . ' -> ' . $update->version_after . '</td><td style="text-align:right;width:30%;padding:8px 0px 8px 8px;border-bottom:solid 1px #dddddd;"">' . date($date_format, strtotime($update->date)) . '</td>';
                    endif;
                endforeach;
            else:
                echo '<tr><td style="width:40%;padding:8px 0px 8px 0px;border-bottom:solid 1px #dddddd;">No WordPress Updates</td>';
            endif;
            ?>
            </table>

            <h3 style="font-size:14px;margin:0px 0px 4px 0px;">Plugin Updates</h3>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top:solid 1px #dddddd;margin-bottom:30px;">
            <?php 
            if ($data->total_plugins_updated > 0) : 
                foreach($data->updates as $update) :
                    if ($update->type == 'plugin') :
                        echo '<tr><td style="width:40%;padding:8px 8px 8px 0px;border-bottom:solid 1px #dddddd;">' . $update->name . '</td><td style="text-align:center;width:30%;padding:8px;border-bottom:solid 1px #dddddd;"">' . $update->version_before . ' -> ' . $update->version_after . '</td><td style="text-align:right;width:30%;padding:8px 0px 8px 8px;border-bottom:solid 1px #dddddd;"">' . date($date_format, strtotime($update->date)) . '</td>';
                    endif;
                endforeach;
            else:
                echo '<tr><td style="width:40%;padding:8px 0px 8px 0px;border-bottom:solid 1px #dddddd;">No Plugin Updates</td>';
            endif;
            ?>
            </table>

            <h3 style="font-size:14px;margin:0px 0px 4px 0px;">Theme Updates</h3>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top:solid 1px #dddddd;margin-bottom:30px;">
            <?php 
            if ($data->total_themes_updated > 0) : 
                foreach($data->updates as $update) :
                    if ($update->type == 'theme') :
                        echo '<tr><td style="width:40%;padding:8px 8px 8px 0px;border-bottom:solid 1px #dddddd;">' . $update->name . '</td><td style="text-align:center;width:30%;padding:8px;border-bottom:solid 1px #dddddd;"">' . $update->version_before . ' -> ' . $update->version_after . '</td><td style="text-align:right;width:30%;padding:8px 0px 8px 8px;border-bottom:solid 1px #dddddd;"">' . date($date_format, strtotime($update->date)) . '</td>';
                    endif;
                endforeach;
            else:
                echo '<tr><td style="width:40%;padding:8px 0px 8px 0px;border-bottom:solid 1px #dddddd;">No Theme Updates</td>';
            endif;
            ?>
            </table>
        </td>
        </tr>
        <!-- end copy -->

        <?php /* <!-- start button -->
        <tr>
        <td align="left" bgcolor="#ffffff">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="center" bgcolor="#ffffff" style="padding: 20px 40px 40px 40px;">
                <table border="0" cellpadding="0" cellspacing="0">
                    <tr>
                    <td align="center" bgcolor="#f15922">
                        <a href="#" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 16px 36px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 14px; color: #ffffff; text-decoration: none; text-transform:uppercase; font-weight:bold; border-bottom: solid 2px #cd4312;">View Challenge Stats</a>
                    </td>
                    </tr>
                </table>
                </td>
            </tr>
            </table>
        </td>
        </tr>
        <!-- end button --> */ ?>
    
    <?php
    
    include("email/report-email-footer.php");
    
    $body = ob_get_clean();
        
    $subject = get_bloginfo('name') . ' Site Report';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>';
    
    wp_mail( $report_email, $subject, $body, $headers );
}

/* 

add_action( 'admin_init', 'wp_client_reports_settings_init' );
function wp_client_reports_settings_init(  ) {

	register_setting( 'hsnoPluginPage', 'wp_client_reports_send_email' );
	register_setting( 'hsnoPluginPage', 'hsno_spark_max' );
	register_setting( 'hsnoPluginPage', 'hsno_crack_max' );

	add_settings_section(
		'wp_client_reports_pluginPage_section',
		__( 'Settings', 'hsno_crack_spark' ),
		'wp_client_reports_settings_section_callback',
		'hsnoPluginPage'
	);

	add_settings_field(
		'wp_client_reports_send_email',
		__( 'Email Address(es)', 'hsno_crack_spark' ),
		'wp_client_reports_send_email_render',
		'hsnoPluginPage',
		'wp_client_reports_pluginPage_section'
	);

	// add_settings_field(
	// 	'hsno_spark_max',
	// 	__( 'Spark Chart Max Value', 'hsno_crack_spark' ),
	// 	'hsno_spark_max_render',
	// 	'hsnoPluginPage',
	// 	'wp_client_reports_pluginPage_section'
	// );

	// add_settings_field(
	// 	'hsno_crack_max',
	// 	__( 'Crack Chart Max Value', 'hsno_crack_spark' ),
	// 	'hsno_crack_max_render',
	// 	'hsnoPluginPage',
	// 	'wp_client_reports_pluginPage_section'
	// );

}

function wp_client_reports_send_email_render(  ) {
	$option = get_option( 'wp_client_reports_send_email' );
	?>
	<input type='text' name='wp_client_reports_send_email' value='<?php echo $option; ?>'class="regular-text">
	<?php
}

function hsno_spark_max_render(  ) {
	$option = get_option( 'hsno_spark_max' );
	?>
	<input type='text' name='hsno_spark_max' value='<?php echo $option; ?>'class="regular-text">
	<?php
}

function hsno_crack_max_render(  ) {
	$option = get_option( 'hsno_crack_max' );
	?>
	<input type='text' name='hsno_crack_max' value='<?php echo $option; ?>'class="regular-text">
	<?php
}

function wp_client_reports_settings_section_callback(  ) {
	echo __( 'You can update the email address(es) of the people who will get daily emails when new data is scraped. If there are multiple emails, they should use a comma and a space to separate them.', 'hsno_crack_spark' );
}

function wp_client_reports_options_page(  ) {
	?>
	<form action='options.php' method='post'>
		<h2>WP Client Reports</h2>
		<?php
		settings_fields( 'hsnoPluginPage' );
		do_settings_sections( 'hsnoPluginPage' );
		submit_button();
		?>
	</form>
	<?php
}

*/