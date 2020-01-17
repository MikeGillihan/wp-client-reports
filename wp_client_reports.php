<?php
/*
Plugin Name: WP Client Reports
Plugin URI: https://switchwp.com/wp-client-reports/
Description: Track Plugin and Theme Updates and send them as Client Reports
Version: 1.0
Author: Jesse Sutherland
Author URI: http://jessesutherland.com/
Text Domain: wp-client-reports
Domain Path: /languages/
*/

if( !defined( 'ABSPATH' ) )
	exit;


/**
 * Add scripts and styles into the admin as needed
 */
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
            'moment_date_format' => wp_client_reports_convert_date_format($date_format),
        );
        wp_localize_script( 'wp-client-reports-js', 'wp_client_reports_data', $js_data );
        wp_enqueue_script( 'wp-client-reports-js' );

    }

}
add_action( 'admin_print_scripts', 'wp_client_reports_scripts' );


/**
 * On plugin activation create the database tables needed to store updates
 */
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


/**
 * On plugin activation schedule our daily check for updates
 */
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


/**
 * Loop through each type of update and determine if there is now a newer version
 */
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

    do_action('wp_client_reports_check');

}


/**
 * Track a single update and add it to the database
 */
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


/**
 * Add a widget to the dashboard.
 */
function wp_client_reports_add_dashboard_widget() {
	wp_add_dashboard_widget(
		'wp_client_reports_last30_widget',         // Widget slug.
		__('Updates Run - Last 30 Days', 'wp-client-reports'),         // Title.
		'wp_client_reports_last30_widget_function' // Display function.
	);
}
add_action( 'wp_dashboard_setup', 'wp_client_reports_add_dashboard_widget' );


/**
 * Create the function to output the contents of our Dashboard Widget.
 */
function wp_client_reports_last30_widget_function() {
    $timezone_string = get_option('timezone_string');
    if ($timezone_string) {
        date_default_timezone_set(get_option('timezone_string'));
    }
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
    $data = wp_client_reports_get_stats_data($start_date, $end_date);
    ?>
    <div class="wp-client-reports-big-numbers wp-client-reports-postbox wp-client-reports-last30-widget">
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-wp-update-count"><?php echo esc_html($data->updates->wp_updated); ?></h2>
            <h3><?php printf( __( 'WordPress %s Updated', 'wp-client-reports' ), '<br>' ); ?></h3>
        </div><!-- .wp-client-reports-big-number -->
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-theme-update-count"><?php echo esc_html($data->updates->total_themes_updated); ?></h2>
            <h3><?php printf( __( 'Themes %s Updated', 'wp-client-reports' ), '<br>' ); ?></h3>
        </div><!-- .wp-client-reports-big-number -->
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-plugin-update-count"><?php echo esc_html($data->updates->total_plugins_updated); ?></h2>
            <h3><?php printf( __( 'Plugins %s Updated', 'wp-client-reports' ), '<br>' ); ?></h3>
        </div><!-- .wp-client-reports-big-number -->
    </div><!-- .wp-client-reports-last30-widget -->
<?php
}


/**
 * Register options pages for the menu
 */
add_action( 'admin_menu', 'wp_client_reports_add_admin_menu' );
function wp_client_reports_add_admin_menu(  ) {
    add_options_page( 'WP Client Report Options', 'WP Client Report Options', 'manage_options', 'wp_client_reports', 'wp_client_reports_options_page' );
    add_submenu_page( 'index.php', 'Reports', 'Reports', 'manage_options', 'wp_client_reports', 'wp_client_reports_stats_page');
}


/**
 * Stats page for updates
 */
function wp_client_reports_stats_page() {
    $default_email = get_option( 'wp_client_reports_default_email' );
    if (!$default_email) {
        $default_email = get_bloginfo('admin_email');
    }
    $default_intro = get_option( 'wp_client_reports_default_intro' );
	?>
        <div class="wp-client-reports-stats-screen">
            <div class="wp-client-reports-header">
                <h1><?php _e('WP Client Reports','wp-client-reports'); ?></h1>
                <div class="wp-client-reports-date-chooser-area">
                    <a href="#TB_inline?width=600&height=550&inlineId=wp-client-reports-which-email-modal" id="wp-client-reports-email-report" class="thickbox button wp-client-reports-email-report-button"><?php _e('Email Report','wp-client-reports'); ?> <span class="dashicons dashicons-email"></span></a>
                    <button id="wp-client-reports-force-update" class="button wp-client-reports-force-update-button"><?php _e('Refresh Report','wp-client-reports'); ?> <span class="dashicons dashicons-update-alt"></span></button>
                    <button id="wp-client-reports-date-chooser-button" class="button button-primary wp-client-reports-date-chooser-button"><span id="wp-client-reports-button-label"><?php _e('Last 30 Days','wp-client-reports'); ?></span> <span class="dashicons dashicons-arrow-down"></span></button><!-- #wp-client-reports-date-chooser-menu -->
                    <div id="wp-client-reports-date-chooser" style="display:none;">
                        <div class="date-chooser-presets">
                            <ul>
                                <li><a href="#" id="wp-client-reports-quick-today"><?php _e('Today','wp-client-reports'); ?></a></li>
                                <li><a href="#" id="wp-client-reports-quick-yesterday"><?php _e('Yesterday','wp-client-reports'); ?></a></li>
                                <li><a href="#" id="wp-client-reports-quick-last7"><?php _e('Last 7 Days','wp-client-reports'); ?></a></li>
                                <li><a href="#" id="wp-client-reports-quick-last14"><?php _e('Last 14 Days','wp-client-reports'); ?></a></li>
                                <li><a href="#" id="wp-client-reports-quick-last30"><?php _e('Last 30 Days','wp-client-reports'); ?></a></li>
                                <li><a href="#" id="wp-client-reports-quick-lastmonth"><?php _e('Last Month','wp-client-reports'); ?></a></li>
                                <li><a href="#" id="wp-client-reports-quick-thismonth"><?php _e('This Month','wp-client-reports'); ?></a></li>
                            </ul>
                        </div>
                        <div id="date-range"></div>
                        <div class="date-chooser-footer">
                            <span class="wp-client-reports-dates"><span id="wp-client-reports-start-date"></span> - <span id="wp-client-reports-end-date"></span></span> <button class="button" id="wp-client-reports-cancel"><?php _e('Cancel','wp-client-reports'); ?></button> <button class="button button-primary" id="wp-client-reports-apply"><?php _e('Apply','wp-client-reports'); ?></button>
                        </div><!-- .date-chooser-footer -->
                        <input type="hidden" id="from_value" class="from_value" name="from_value"/><input type="hidden" id="to_value"  class="to_value" name="to_value"/>
                    </div><!-- #wp-client-reports-date-chooser -->
                </div><!-- .wp-client-reports-date-chooser-area -->
            </div><!-- .wp-client-reports-header -->

            <div class="metabox-holder">
                <div class="postbox wp-client-reports-postbox" id="wp-client-reports-updates">
                    <button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text"><?php _e('Toggle panel','wp-client-reports'); ?>: <?php _e('Software Updates','wp-client-reports'); ?></span><span class="toggle-indicator" aria-hidden="true"></span></button><h2 class="hndle ui-sortable-handle"><span><?php _e('Software Updates','wp-client-reports'); ?></span></h2>
                    <div class="inside">
                        <div class="main">
                            <div class="wp-client-reports-big-numbers">
                                <div class="wp-client-reports-big-number">
                                    <h2 id="wp-client-reports-total-update-count">0</h2>
                                    <h3><?php printf( __( 'Total %s Updates', 'wp-client-reports' ), '<br>' ); ?></h3>
                                </div><!-- .wp-client-reports-big-number -->
                                <div class="wp-client-reports-big-number">
                                    <h2 id="wp-client-reports-wp-update-count">0</h2>
                                    <h3><?php printf( __( 'WordPress %s Updated', 'wp-client-reports' ), '<br>' ); ?></h3>
                                </div><!-- .wp-client-reports-big-number -->
                                <div class="wp-client-reports-big-number">
                                    <h2 id="wp-client-reports-theme-update-count">0</h2>
                                    <h3><?php printf( __( 'Themes %s Updated', 'wp-client-reports' ), '<br>' ); ?></h3>
                                </div><!-- .wp-client-reports-big-number -->
                                <div class="wp-client-reports-big-number">
                                    <h2 id="wp-client-reports-plugin-update-count">0</h2>
                                    <h3><?php printf( __( 'Plugins %s Updated', 'wp-client-reports' ), '<br>' ); ?></h3>
                                </div><!-- .wp-client-reports-big-number -->
                            </div><!-- .wp-client-reports-big-numbers -->

                            <div class="wp-client-report-section wp-client-report-border-top">

                                <h3><?php _e('WordPress Updates','wp-client-reports'); ?></h3>
                                <ul id="wp-client-reports-wp-updates-list" class="wp-client-reports-list"></ul>

                            </div>
                            <div class="wp-client-report-section wp-client-report-border-top">

                                <h3><?php _e('Plugin Updates','wp-client-reports'); ?></h3>
                                <ul id="wp-client-reports-plugin-updates-list" class="wp-client-reports-list"></ul>

                            </div>
                            <div class="wp-client-report-section wp-client-report-border-top">

                                <h3><?php _e('Theme Updates','wp-client-reports'); ?></h3>
                                <ul id="wp-client-reports-theme-updates-list" class="wp-client-reports-list"></ul>

                            </div><!-- .wp-client-report-section -->
                            

                            <div id="wp-client-reports-which-email-modal" class="wp-client-reports-which-email-modal" style="display:none;">
                                <form method="GET" action="#" id="wp-client-reports-send-email-report">
                                    <table class="form-table" role="presentation">
                                        <tbody>
                                            <tr>
                                                <th scope="row"><label for="report-email"><?php _e('Send Email To','wp-client-reports'); ?></label></th>
                                                <td><input name="report_email" type="email" id="report-email" value="<?php echo esc_attr($default_email); ?>" class="regular-text"></td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><label for="report-intro"><?php _e('Email Introduction','wp-client-reports'); ?></label></th>
                                                <td><textarea name="report_intro" id="report-intro" class="large-text"><?php echo esc_attr($default_intro); ?></textarea></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <input type="hidden" name="action" value="wp_client_reports_send_email_report">
                                    <input type="hidden" name="start" class="from_value" id="start_date_email">
                                    <input type="hidden" name="end" class="to_value" id="end_date_email">
                                    <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Send Now"></p>
                                </form>
                            </div><!-- #wp-client-reports-which-email-modal -->

                        </div><!-- .inside -->
                    </div><!-- .main -->
                </div><!-- .postbox -->
            </div><!-- .metabox-holder -->

            <?php do_action('wp_client_reports_stats'); ?>

        </div><!-- .wp-client-reports-stats-screen -->
	<?php
}


/**
 * Ajax call for stats data
 */
add_action('wp_ajax_wp_client_reports_stats_data', 'wp_client_reports_stats_data');
function wp_client_reports_stats_data() {

    $start = sanitize_text_field($_GET['start']);
    $end = sanitize_text_field($_GET['end']);

    if (isset($start) && isset($end)) {
        $start_date_object = date_create_from_format('Y-m-d', $start);
        $start_date = $start_date_object->format('Y-m-d');
        $end_date_object = date_create_from_format('Y-m-d', $end);
        $end_date = $end_date_object->format('Y-m-d');
    } else {
        $timezone_string = get_option('timezone_string');
        if ($timezone_string) {
            date_default_timezone_set($timezone_string);
        }
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
    }

    $data = wp_client_reports_get_stats_data($start_date, $end_date);

    $data = apply_filters( 'wp_client_reports_data', $data, $start_date, $end_date );

    print json_encode($data);
    wp_die();

}


/**
 * Get the stats data from the database
 */
function wp_client_reports_get_stats_data($start_date, $end_date) {

    global $wpdb;
    $wp_client_reports_table_name = $wpdb->prefix . 'update_tracking';

    $data = new \stdClass;

    $update_results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `version_before` IS NOT NULL AND `date` >= %s AND `date` <= %s ORDER BY `date` ASC", array($start_date, $end_date) ) );

    $data->updates = new \stdClass;
    $data->updates->total_updates = 0;
    $data->updates->total_themes_updated = 0;
    $data->updates->total_plugins_updated = 0;
    $data->updates->wp_updated = 0;
    $data->updates->updates = [];

    if (isset($update_results) && is_array($update_results)) {
        $data->updates->updates = $update_results;
        foreach($data->updates->updates as $update) {
            $data->updates->total_updates++;
            if ($update->type == 'theme') {
                $data->updates->total_themes_updated++;
            }
            if ($update->type == 'plugin') {
                $data->updates->total_plugins_updated++;
            }
            if ($update->type == 'wp') {
                $data->updates->wp_updated++;
            }
        }
    }

    return $data;
    
}


/**
 * Force an update to the update statistics
 */
add_action('wp_ajax_wp_client_reports_force_update', 'wp_client_reports_force_update');
function wp_client_reports_force_update() {
    wp_client_reports_check_for_updates();
    print json_encode(['status'=>'success']);
    wp_die();
}


/**
 * Send an emailed report
 */
add_action('wp_ajax_wp_client_reports_send_email_report', 'wp_client_reports_send_email_report');
function wp_client_reports_send_email_report() {

    $report_email_input = sanitize_email($_GET['report_email']);
    $report_intro_input = sanitize_text_field($_GET['report_intro']);
    $start = sanitize_text_field($_GET['start']);
    $end = sanitize_text_field($_GET['end']);

    if (isset($report_email_input)) {
        $report_email = $report_email_input;
    } else {
        $report_email = get_bloginfo('admin_email');
    }

    $report_intro = null;
    if (isset($report_intro_input)) {
        $report_intro = $report_intro_input;
    }

    if (isset($start) && isset($end)) {
        $start_date_object = date_create_from_format('Y-m-d', $start);
        $start_date = $start_date_object->format('Y-m-d');
        $end_date_object = date_create_from_format('Y-m-d', $end);
        $end_date = $end_date_object->format('Y-m-d');
    } else {
        $timezone_string = get_option('timezone_string');
        if ($timezone_string) {
            date_default_timezone_set($timezone_string);
        }
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
    }

    $data = wp_client_reports_get_stats_data($start_date, $end_date);
    $data = apply_filters( 'wp_client_reports_data', $data, $start_date, $end_date );

    $date_format = get_option('date_format');
    
    $start_date_formatted = date($date_format, strtotime($start_date));
    $end_date_formatted = date($date_format, strtotime($end_date));

    ob_start();

    include("email/report-email-header.php");
    
    ?>
    
        <!-- start copy -->
        <tr>
        <td bgcolor="#ffffff" align="left" style="padding: 40px 40px 20px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
            <h1 style="margin: 0 0 12px; font-size: 30px; font-weight: bold; line-height: 42px; color: #007cba; "><?php echo esc_html(get_bloginfo('name')); ?> <?php _e('Site Report','wp-client-reports'); ?></h1>
            <p style="margin: 0; color:#212529;"><?php _e('From','wp-client-reports'); ?> <?php echo esc_html($start_date_formatted); ?> - <?php echo $end_date_formatted; ?></p>
        </td>
        </tr>
        <!-- end copy -->

        <?php if($report_intro) : ?>
            <!-- start copy -->
            <tr>
            <td bgcolor="#ffffff" align="left" style="padding: 10px 40px 20px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                <p style="margin: 0; color:#212529;"><?php echo esc_html($report_intro); ?></p>
            </td>
            </tr>
            <!-- end copy -->
        <?php endif; ?>

        <!-- start copy -->
        <tr>
            <td align="left" bgcolor="#ffffff" style="padding: 0px 40px 0px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                <h5 style="font-weight:bold; font-size: 16px; line-height:18px; padding-bottom:10px; margin: 15px 0px 10px;border-bottom:solid 1px #ddd;"><?php _e( 'Software Updates', 'wp-client-reports' ); ?></h5>
            </td>
        </tr>
        <!-- end copy -->
        
        <!-- start copy -->
        <tr>
            <td align="left" bgcolor="#ffffff" style="padding: 0px 40px 0px 40px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <td align="center" width="250" style="padding: 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                    <h1 style="font-weight: bold; color: #007cba; margin: 0px; font-size: 66px; line-height: 1em;"><?php echo esc_html($data->updates->total_updates); ?></h1>
                    <h5 style="text-transform: uppercase; color: #888888; font-size: 16px; line-height:18px; font-weight: 300; margin: 0px;"><?php printf( __( 'Total %s Updates', 'wp-client-reports' ), '<br>' ); ?></h5>
                </td>
                <td bgcolor="#ffffff" align="center" width="20">&nbsp;</td>
                <td align="center" width="250" style="padding: 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                    <h1 style="font-weight: bold; color: #007cba; margin: 0px; font-size: 66px; line-height: 1em;"><?php echo esc_html($data->updates->wp_updated); ?></h1>
                    <h5 style="text-transform: uppercase; color: #888888; font-size: 16px; line-height:18px; font-weight: 300; margin: 0px;"><?php printf( __( 'WordPress %s Updated', 'wp-client-reports' ), '<br>' ); ?></h5>
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
                    <h1 style="font-weight: bold; color: #007cba; margin: 0px; font-size: 66px; line-height: 1em;"><?php echo esc_html($data->updates->total_themes_updated); ?></h1>
                    <h5 style="text-transform: uppercase; color: #888888; font-size: 16px; line-height:18px; font-weight: 300; margin: 0px;"><?php printf( __( 'Themes %s Updated', 'wp-client-reports' ), '<br>' ); ?></h5>
                </td>
                <td bgcolor="#ffffff" align="center" width="20">&nbsp;</td>
                <td align="center" width="250" style="padding: 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                    <h1 style="font-weight: bold; color: #007cba; margin: 0px; font-size: 66px; line-height: 1em;"><?php echo esc_html($data->updates->total_plugins_updated); ?></h1>
                    <h5 style="text-transform: uppercase; color: #888888; font-size: 16px; line-height:18px; font-weight: 300; margin: 0px;"><?php printf( __( 'Plugins %s Updated', 'wp-client-reports' ), '<br>' ); ?></h5>
                </td>
                </table>
            </td>
        </tr>
        <!-- end copy -->
        
        <!-- start copy -->
        <tr>
        <td bgcolor="#ffffff" align="left" style="padding: 20px 40px 40px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 14px; line-height: 20px;">
            <h3 style="font-size:14px;margin:0px 0px 4px 0px;"><?php _e('WordPress Updates','wp-client-reports'); ?></h3>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top:solid 1px #dddddd;margin-bottom:30px;">
            <?php
            if ($data->updates->wp_updated > 0) : 
                foreach($data->updates->updates as $update) :
                    if ($update->type == 'wp') :
                        echo '<tr><td style="width:40%;padding:8px 8px 8px 0px;border-bottom:solid 1px #dddddd;">' . esc_html($update->name) . '</td><td style="text-align:center;width:30%;padding:8px;border-bottom:solid 1px #dddddd;"">' . esc_html($update->version_before) . ' -> ' . esc_html($update->version_after) . '</td><td style="text-align:right;width:30%;padding:8px 0px 8px 8px;border-bottom:solid 1px #dddddd;"">' . esc_html(date($date_format, strtotime($update->date))) . '</td>';
                    endif;
                endforeach;
            else:
                echo '<tr><td style="width:40%;padding:8px 0px 8px 0px;border-bottom:solid 1px #dddddd;">No WordPress Updates</td>';
            endif;
            ?>
            </table>

            <h3 style="font-size:14px;margin:0px 0px 4px 0px;"><?php _e('Plugin Updates','wp-client-reports'); ?></h3>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top:solid 1px #dddddd;margin-bottom:30px;">
            <?php 
            if ($data->updates->total_plugins_updated > 0) : 
                foreach($data->updates->updates as $update) :
                    if ($update->type == 'plugin') :
                        echo '<tr><td style="width:40%;padding:8px 8px 8px 0px;border-bottom:solid 1px #dddddd;">' . esc_html($update->name) . '</td><td style="text-align:center;width:30%;padding:8px;border-bottom:solid 1px #dddddd;"">' . esc_html($update->version_before) . ' -> ' . esc_html($update->version_after) . '</td><td style="text-align:right;width:30%;padding:8px 0px 8px 8px;border-bottom:solid 1px #dddddd;"">' . esc_html(date($date_format, strtotime($update->date))) . '</td>';
                    endif;
                endforeach;
            else:
                echo '<tr><td style="width:40%;padding:8px 0px 8px 0px;border-bottom:solid 1px #dddddd;">No Plugin Updates</td>';
            endif;
            ?>
            </table>

            <h3 style="font-size:14px;margin:0px 0px 4px 0px;"><?php _e('Theme Updates','wp-client-reports'); ?></h3>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top:solid 1px #dddddd;margin-bottom:20px;">
            <?php 
            if ($data->updates->total_themes_updated > 0) : 
                foreach($data->updates->updates as $update) :
                    if ($update->type == 'theme') :
                        echo '<tr><td style="width:40%;padding:8px 8px 8px 0px;border-bottom:solid 1px #dddddd;">' . esc_html($update->name) . '</td><td style="text-align:center;width:30%;padding:8px;border-bottom:solid 1px #dddddd;"">' . esc_html($update->version_before) . ' -> ' . esc_html($update->version_after) . '</td><td style="text-align:right;width:30%;padding:8px 0px 8px 8px;border-bottom:solid 1px #dddddd;"">' . esc_html(date($date_format, strtotime($update->date))) . '</td>';
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

        <?php do_action('wp_client_reports_stats_email', $data, $start_date, $end_date); ?>

        <!-- start button -->
        <tr>
        <td align="left" bgcolor="#ffffff">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="center" bgcolor="#ffffff" style="padding: 0px 40px 40px 40px;">
                    <table border="0" cellpadding="0" cellspacing="0">
                        <tr>
                        <td align="center" bgcolor="#007cba">
                            <a href="<?php echo site_url(); ?>" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 8px 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 13px; color: #ffffff; text-decoration: none;"><?php _e('Visit Website','wp-client-reports'); ?></a>
                        </td>
                        </tr>
                    </table>
                </td>
            </tr>
            </table>
        </td>
        </tr>
        <!-- end button -->
    
    <?php
    
    include("email/report-email-footer.php");
    
    $body = ob_get_clean();
        
    $subject = get_bloginfo('name') . __('Site Report','wp-client-reports');
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>';
    
    wp_mail( $report_email, $subject, $body, $headers );
}


/**
 * Register the options that will be available on the options page
 */
add_action( 'admin_init', 'wp_client_reports_options_init', 10 );
function wp_client_reports_options_init(  ) {

	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_default_email' );
	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_default_intro' );

	add_settings_section(
		'wp_client_reports_pluginPage_section',
		__( 'Settings', 'wp-client-reports' ),
		'wp_client_reports_settings_section_callback',
		'wp_client_reports_options_page'
	);

	add_settings_field(
		'wp_client_reports_default_email',
		__( 'Default Email Address(es)', 'wp-client-reports' ),
		'wp_client_reports_default_email_render',
		'wp_client_reports_options_page',
		'wp_client_reports_pluginPage_section'
	);

	add_settings_field(
		'wp_client_reports_default_intro',
		__( 'Default Email Intro', 'wp-client-reports' ),
		'wp_client_reports_default_intro_render',
		'wp_client_reports_options_page',
		'wp_client_reports_pluginPage_section'
	);

}


/**
 * Add default email field to the options page
 */
function wp_client_reports_default_email_render(  ) {
    $option = get_option( 'wp_client_reports_default_email' );
    if (!$option) {
        $option = get_bloginfo('admin_email');
    }
	?>
	<input type='email' name='wp_client_reports_default_email' value='<?php echo esc_attr($option); ?>'class="regular-text">
	<?php
}


/**
 * Add default intro field to the options page
 */
function wp_client_reports_default_intro_render(  ) {
	$option = get_option( 'wp_client_reports_default_intro' );
	?>
	<textarea name='wp_client_reports_default_intro' class="large-text" rows="8" cols="50"><?php echo esc_attr($option); ?></textarea>
	<?php
}


/**
 * Settings section help
 */
function wp_client_reports_settings_section_callback(  ) {
	echo __( 'You can update the default email address(es) of whom will recieve client reports when you send them. If there are multiple emails, they should use a comma and a space to separate them.', 'wp-client-reports' );
}


/**
 * Create the basic structure for the options page
 */
function wp_client_reports_options_page(  ) {
	?>
    <div class="wrap">
        <h1><?php _e('WP Client Reports Options','wp-client-reports'); ?></h1>
        <form action='options.php' method='post'>
            <?php
            settings_fields( 'wp_client_reports_options_page' );
            do_settings_sections( 'wp_client_reports_options_page' );
            submit_button();
            ?>
        </form>
    </div><!-- .wrap -->
	<?php
}


/**
 * Convert PHP date format to Moment.js date format
 */
function wp_client_reports_convert_date_format($format) {
    $replacements = [
        'd' => 'DD',
        'D' => 'ddd',
        'j' => 'D',
        'l' => 'dddd',
        'N' => 'E',
        'S' => 'o',
        'w' => 'e',
        'z' => 'DDD',
        'W' => 'W',
        'F' => 'MMMM',
        'm' => 'MM',
        'M' => 'MMM',
        'n' => 'M',
        't' => '', // no equivalent
        'L' => '', // no equivalent
        'o' => 'YYYY',
        'Y' => 'YYYY',
        'y' => 'YY',
        'a' => 'a',
        'A' => 'A',
        'B' => '', // no equivalent
        'g' => 'h',
        'G' => 'H',
        'h' => 'hh',
        'H' => 'HH',
        'i' => 'mm',
        's' => 'ss',
        'u' => 'SSS',
        'e' => 'zz', // deprecated since version 1.6.0 of moment.js
        'I' => '', // no equivalent
        'O' => '', // no equivalent
        'P' => '', // no equivalent
        'T' => '', // no equivalent
        'Z' => '', // no equivalent
        'c' => '', // no equivalent
        'r' => '', // no equivalent
        'U' => 'X',
    ];
    $moment_js_format = strtr($format, $replacements);
    return $moment_js_format;
}