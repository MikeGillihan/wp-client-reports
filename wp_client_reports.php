<?php
/*
Plugin Name: WP Client Reports
Plugin URI: https://switchwp.com/wp-client-reports/
Description: Track Plugin and Theme Updates and send them as Client Reports
Version: 1.0.2
Author: Jesse Sutherland
Author URI: http://jessesutherland.com/
Text Domain: wp-client-reports
Domain Path: /languages/
*/

if( !defined( 'ABSPATH' ) )
	exit;


define( 'WP_CLIENT_REPORTS_VERSION', '1.0.2' );


/**
 * Add scripts and styles into the admin as needed
 */
function wp_client_reports_scripts() {

    wp_enqueue_style( 'wp-client-reports-css', plugin_dir_url( __FILE__ ) . '/css/wp-client-reports.css', array(), WP_CLIENT_REPORTS_VERSION );

    $screen = get_current_screen();
    if($screen && $screen->id == 'dashboard_page_wp_client_reports') {

        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_script( 'moment-js', plugin_dir_url( __FILE__ ) . '/js/moment.js', array(), '2.24.0', true );
        wp_enqueue_script('thickbox');
        wp_enqueue_style( 'thickbox' );
        wp_register_script( 'wp-client-reports-js', plugin_dir_url( __FILE__ ) . '/js/wp-client-reports.js', array('jquery','jquery-ui-datepicker'), WP_CLIENT_REPORTS_VERSION, true );
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



add_action( 'admin_init', 'wp_client_reports_load_actions', 985 );
function wp_client_reports_load_actions(){
    
    $updates_enabled = get_option( 'wp_client_reports_enable_updates' );
    if ($updates_enabled == 'on') {
        add_action('wp_client_reports_stats', 'wp_client_reports_stats_page_updates', 10);
        add_action('wp_client_reports_stats_email', 'wp_client_reports_stats_email_updates', 10, 2);
        add_action('wp_ajax_wp_client_reports_updates_data', 'wp_client_reports_updates_data');
    }

    $content_stats_enabled = get_option( 'wp_client_reports_enable_content_stats' );
    if ($content_stats_enabled == 'on') {
        add_action('wp_client_reports_stats', 'wp_client_reports_stats_page_content', 30);
        add_action('wp_client_reports_stats_email', 'wp_client_reports_stats_email_content', 30, 2);
        add_action('wp_ajax_wp_client_reports_content_stats_data', 'wp_client_reports_content_stats_data');
    }

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
        $timezone_string = get_option('timezone_string');
        if ($timezone_string) {
            date_default_timezone_set(get_option('timezone_string'));
        }
        //Schedule the event for right now, then to repeat daily using the hook 'wp_client_reports_check_for_updates_daily'
        wp_schedule_event( strtotime('00:00:00'), 'daily', 'wp_client_reports_check_for_updates_daily' );
    }
}


/**
 * Loop through each type of update and determine if there is now a newer version
 */
add_action( 'wp_client_reports_check_for_updates_daily', 'wp_client_reports_check_for_updates' );
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
    $updates_data = wp_client_reports_get_updates_data($start_date, $end_date);
    ?>
    <div class="wp-client-reports-big-numbers wp-client-reports-postbox wp-client-reports-last30-widget">
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-wp-update-count"><?php echo esc_html($updates_data->wp_updated); ?></h2>
            <h3><?php printf( __( 'WordPress %s Updates', 'wp-client-reports' ), '<br>' ); ?></h3>
        </div><!-- .wp-client-reports-big-number -->
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-theme-update-count"><?php echo esc_html($updates_data->total_themes_updated); ?></h2>
            <h3><?php printf( __( 'Theme %s Updates', 'wp-client-reports' ), '<br>' ); ?></h3>
        </div><!-- .wp-client-reports-big-number -->
        <div class="wp-client-reports-big-number">
            <h2 id="wp-client-reports-plugin-update-count"><?php echo esc_html($updates_data->total_plugins_updated); ?></h2>
            <h3><?php printf( __( 'Plugin %s Updates', 'wp-client-reports' ), '<br>' ); ?></h3>
        </div><!-- .wp-client-reports-big-number -->
    </div><!-- .wp-client-reports-last30-widget -->
<?php
}


/**
 * Register options pages for the menu
 */
add_action( 'admin_menu', 'wp_client_reports_add_admin_menu' );
function wp_client_reports_add_admin_menu(  ) {
    add_options_page( 'WP Client Reports Settings', 'WP Client Reports', 'manage_options', 'wp_client_reports', 'wp_client_reports_options_page' );
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

            <?php do_action('wp_client_reports_stats'); ?>

            <?php if ( !is_plugin_active( 'wp-client-reports-pro/wp_client_reports_pro.php' ) ) : ?>
                <p style="margin: 20px 0;text-align:center;">Report created with <a href="https://switchwp.com/plugins/wp-client-reports/" target="_blank">WP Client Reports</a>.</p>
            <?php endif; ?>

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
                <div class="notice notice-success wp-client-reports-success" id="wp-client-reports-report-status" style="display:none;margin-top:26px;">
                    <p><?php _e( 'Report has been sent!', 'wp-client-reports' ); ?></p>
                </div>
            </div><!-- #wp-client-reports-which-email-modal -->

        </div><!-- .wp-client-reports-stats-screen -->
	<?php
}


/**
 * Stats page for updates
 */
function wp_client_reports_stats_page_updates() {
    ?>
    <div class="metabox-holder">
        <div class="postbox wp-client-reports-postbox" id="wp-client-reports-updates">
            <button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text"><?php _e('Toggle panel','wp-client-reports'); ?>: <?php _e('Software Updates','wp-client-reports'); ?></span><span class="toggle-indicator" aria-hidden="true"></span></button><h2 class="hndle ui-sortable-handle"><span><?php _e('Software Updates','wp-client-reports'); ?></span></h2>
            <div class="inside">
                <div class="main">
                    <div class="wp-client-reports-big-numbers">
                        <?php 
                            wp_client_reports_render_big_number(
                                sprintf( __( 'Total %s Updates', 'wp-client-reports' ), '<br>' ), 
                                'wp-client-reports-total-update-count'
                            );
                            wp_client_reports_render_big_number(
                                sprintf( __( 'WordPress %s Updates', 'wp-client-reports' ), '<br>' ), 
                                'wp-client-reports-wp-update-count'
                            );
                            wp_client_reports_render_big_number(
                                sprintf( __( 'Plugin %s Updates', 'wp-client-reports' ), '<br>' ), 
                                'wp-client-reports-plugin-update-count'
                            );
                            wp_client_reports_render_big_number(
                                sprintf( __( 'Theme %s Updates', 'wp-client-reports' ), '<br>' ), 
                                'wp-client-reports-theme-update-count'
                            );
                        ?>
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

                </div><!-- .inside -->
            </div><!-- .main -->
        </div><!-- .postbox -->

    </div><!-- .metabox-holder -->
    <?php
}


/**
 * Ajax call for stats data
 */
function wp_client_reports_updates_data() {

    $start = null;
    $end = null;
    if (isset($_GET['start'])) {
        $start = sanitize_text_field($_GET['start']);
    }
    if (isset($_GET['end'])) {
        $end = sanitize_text_field($_GET['end']);
    }

    $dates = wp_client_reports_validate_dates($start, $end);

    $data = wp_client_reports_get_updates_data($dates->start_date, $dates->end_date);

    print json_encode($data);
    wp_die();

}

function wp_client_reports_validate_dates($start, $end) {
    $dates = new \stdClass;
    $timezone_string = get_option('timezone_string');
    if ($timezone_string) {
        date_default_timezone_set($timezone_string);
    }
    if (isset($start) && isset($end)) {
        $start_date_object = date_create_from_format('Y-m-d', $start);
        $dates->start_date = $start_date_object->format('Y-m-d');
        $end_date_object = date_create_from_format('Y-m-d', $end);
        $dates->end_date = $end_date_object->format('Y-m-d');
    } else {
        $dates->start_date = date('Y-m-d', strtotime('-30 days'));
        $dates->end_date = date('Y-m-d');
    }
    return $dates;
}


/**
 * Get the stats data from the database
 */
function wp_client_reports_get_updates_data($start_date, $end_date) {

    global $wpdb;
    $wp_client_reports_table_name = $wpdb->prefix . 'update_tracking';

    $data = new \stdClass;

    $update_results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wp_client_reports_table_name WHERE `version_before` IS NOT NULL AND `date` >= %s AND `date` <= %s ORDER BY `date` ASC", array($start_date, $end_date) ) );

    $data = new \stdClass;
    $data->total_updates = 0;
    $data->total_themes_updated = 0;
    $data->total_plugins_updated = 0;
    $data->wp_updated = 0;
    $data->updates = [];

    if (isset($update_results) && is_array($update_results)) {
        $data->updates = $update_results;
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
    }

    $data = apply_filters( 'wp_client_reports_updates_data', $data, $start_date, $end_date );

    return $data;
    
}


add_filter('wp_client_reports_email_data', 'wp_client_reports_email_updates_data', 11, 3);
function wp_client_reports_email_updates_data($data, $start_date, $end_date) {
    $updates = new \stdClass;
    $updates = wp_client_reports_get_updates_data($start_date, $end_date);
    $data->updates = $updates;
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
 * Ajax call for stats data
 */
function wp_client_reports_content_stats_data() {

    $start = null;
    $end = null;
    if (isset($_GET['start'])) {
        $start = sanitize_text_field($_GET['start']);
    }
    if (isset($_GET['end'])) {
        $end = sanitize_text_field($_GET['end']);
    }

    $dates = wp_client_reports_validate_dates($start, $end);

    $data = wp_client_reports_get_content_stats_data($dates->start_date, $dates->end_date);

    print json_encode($data);
    wp_die();

}


/**
 * Get the stats data from the database
 */
function wp_client_reports_get_content_stats_data($start_date, $end_date) {

    global $wpdb;
    $posts_table_name = $wpdb->prefix . 'posts';
    $comments_table_name = $wpdb->prefix . 'comments';

    $data = new \stdClass;

    $posts_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $posts_table_name WHERE `post_status` = 'publish' AND `post_type` = 'post' AND `post_date_gmt` >= %s AND `post_date_gmt` <= %s", array($start_date . ' 00:00:00', $end_date . ' 23:59:59') ) );

    $pages_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $posts_table_name WHERE `post_status` = 'publish' AND `post_type` = 'page' AND `post_date_gmt` >= %s AND `post_date_gmt` <= %s", array($start_date . ' 00:00:00', $end_date . ' 23:59:59') ) );

    $comments_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $comments_table_name WHERE `comment_approved` = 1 AND `comment_type` = '' AND `comment_date_gmt` >= %s AND `comment_date_gmt` <= %s", array($start_date . ' 00:00:00', $end_date . ' 23:59:59') ) );

    $data = new \stdClass;
    $data->posts_count = $posts_count;
    $data->pages_count = $pages_count;
    $data->comments_count = $comments_count;

    $data = apply_filters( 'wp_client_reports_content_stats_data', $data, $start_date, $end_date );

    return $data;
    
}


add_filter('wp_client_reports_email_data', 'wp_client_reports_email_content_stats_data', 11, 3);
function wp_client_reports_email_content_stats_data($data, $start_date, $end_date) {
    $updates = new \stdClass;
    $updates = wp_client_reports_get_content_stats_data($start_date, $end_date);
    $data->updates = $updates;
    return $data;
}


/**
 * Stats page for updates
 */
function wp_client_reports_stats_page_content() {
    ?>
        <div class="metabox-holder">
            <div class="postbox wp-client-reports-postbox" id="wp-client-reports-content-stats">
                <button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text"><?php _e('Toggle panel','wp-client-reports'); ?>: <?php _e('Site Content','wp-client-reports'); ?></span><span class="toggle-indicator" aria-hidden="true"></span></button><h2 class="hndle ui-sortable-handle"><span><?php _e('Site Content','wp-client-reports'); ?></span></h2>
                <div class="inside">
                    <div class="main">
                        <div class="wp-client-reports-big-numbers">
                            <?php 
                                wp_client_reports_render_big_number(
                                    sprintf( __( 'Posts %s Added', 'wp-client-reports' ), '<br>' ), 
                                    'wp-client-reports-new-posts-count'
                                );
                                wp_client_reports_render_big_number(
                                    sprintf( __( 'Pages %s Added', 'wp-client-reports' ), '<br>' ), 
                                    'wp-client-reports-new-pages-count'
                                );
                                wp_client_reports_render_big_number(
                                    sprintf( __( 'Comments %s Added', 'wp-client-reports' ), '<br>' ), 
                                    'wp-client-reports-new-comments-count'
                                );
                            ?>
                        </div><!-- .wp-client-reports-big-numbers -->

                    </div><!-- .inside -->
                </div><!-- .main -->
            </div><!-- .postbox -->

        </div><!-- .metabox-holder -->
    <?php
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

    $dates = wp_client_reports_validate_dates($start, $end);

    $date_format = get_option('date_format');
    
    $start_date_formatted = date($date_format, strtotime($dates->start_date));
    $end_date_formatted = date($date_format, strtotime($dates->end_date));

    $brand_color = wp_client_reports_get_brand_color();

    ob_start();

    include("email/report-email-header.php");

    do_action('wp_client_reports_stats_email_before');
    
    ?>

        <!-- start copy -->
        <tr>
        <td bgcolor="#ffffff" align="left" style="padding: 40px 40px 20px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
            <h1 style="margin: 0 0 12px; font-size: 30px; font-weight: bold; line-height: 42px; color: <?php echo $brand_color; ?>; "><?php echo esc_html(get_bloginfo('name')); ?> <?php _e('Site Report','wp-client-reports'); ?></h1>
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

        <?php do_action('wp_client_reports_stats_email', $dates->start_date, $dates->end_date); ?>

        <!-- start button -->
        <tr>
        <td align="left" bgcolor="#ffffff">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="center" bgcolor="#ffffff" style="padding: 0px 40px 40px 40px;">
                    <table border="0" cellpadding="0" cellspacing="0">
                        <tr>
                        <td align="center" bgcolor="<?php echo $brand_color; ?>">
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
        
    $subject = get_bloginfo('name') . ' ' . __('Site Report','wp-client-reports');
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>';
    
    wp_mail( $report_email, $subject, $body, $headers );
}


function wp_client_reports_render_big_number($title, $id) {
    ?>
    <div class="wp-client-reports-big-number">
        <h2 id="<?php echo $id; ?>">0</h2>
        <h3><?php echo $title; ?></h3>
    </div><!-- .wp-client-reports-big-number -->
    <?php
}


function wp_client_reports_render_email_header($title) {
    ?>
    <tr>
        <td align="left" bgcolor="#ffffff" style="padding: 0px 40px 0px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
            <h5 style="font-weight:bold; font-size: 16px; line-height:18px; padding-bottom:10px; margin: 15px 0px 10px;border-bottom:solid 1px #ddd;"><?php echo esc_html($title); ?></h5>
        </td>
    </tr>
    <?php
}


function wp_client_reports_render_email_row($stat1, $label1, $stat2, $label2) {
    ?>
    <tr>
        <td align="left" bgcolor="#ffffff" style="padding: 0px 40px 0px 40px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <td align="center" width="250" style="padding: 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                    <?php wp_client_reports_render_email_big_number($stat1, $label1); ?>
                </td>
            <td bgcolor="#ffffff" align="center" width="20">&nbsp;</td>
                <td align="center" width="250" style="padding: 20px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 16px; line-height: 24px;">
                    <?php wp_client_reports_render_email_big_number($stat2, $label2); ?>
                </td>
            </table>
        </td>
    </tr>
    <?php
}


function wp_client_reports_render_email_big_number($stat, $label) {
    $brand_color = wp_client_reports_get_brand_color();
    $allowed_html = ['br' => [] ];
    if ($stat && $label) {
        ?>
        <h1 style="font-weight: bold; color: <?php echo esc_attr($brand_color); ?>; margin: 0px; font-size: 66px; line-height: 1em;"><?php echo esc_html($stat); ?></h1>
        <h5 style="text-transform: uppercase; color: #888888; font-size: 16px; line-height:18px; font-weight: 300; margin: 0px;"><?php echo wp_kses($label, $allowed_html); ?></h5>
        <?php
    }
}



/**
 * Stats page for updates
 */
function wp_client_reports_stats_email_updates($start_date, $end_date) {
    $updates_data = wp_client_reports_get_updates_data($start_date, $end_date);
    $date_format = get_option('date_format');
    
    wp_client_reports_render_email_header(__( 'Software Updates', 'wp-client-reports' ));

    wp_client_reports_render_email_row(
        $updates_data->total_updates, 
        sprintf( __( 'Total %s Updates', 'wp-client-reports' ), '<br>' ), 
        $updates_data->wp_updated, 
        sprintf( __( 'WordPress %s Updates', 'wp-client-reports' ), '<br>' )
    );

    wp_client_reports_render_email_row(
        $updates_data->total_plugins_updated, 
        sprintf( __( 'Plugin %s Updates', 'wp-client-reports' ), '<br>' ), 
        $updates_data->total_themes_updated, 
        sprintf( __( 'Theme %s Updates', 'wp-client-reports' ), '<br>' )
    );
        
    ?>
        
        <!-- start copy -->
        <tr>
        <td bgcolor="#ffffff" align="left" style="padding: 20px 40px 40px 40px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 14px; line-height: 20px;">
            <h3 style="font-size:14px;margin:0px 0px 4px 0px;"><?php _e('WordPress Updates','wp-client-reports'); ?></h3>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top:solid 1px #dddddd;margin-bottom:30px;">
            <?php
            if ($updates_data->wp_updated > 0) : 
                foreach($updates_data->updates as $update) :
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
            if ($updates_data->total_plugins_updated > 0) : 
                foreach($updates_data->updates as $update) :
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
            if ($updates_data->total_themes_updated > 0) : 
                foreach($updates_data->updates as $update) :
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
    <?php
}


/**
 * Stats page for updates
 */
function wp_client_reports_stats_email_content($start_date, $end_date) {
    $content_stats_data = wp_client_reports_get_content_stats_data($start_date, $end_date);
    $date_format = get_option('date_format');

    wp_client_reports_render_email_header(__( 'Site Content', 'wp-client-reports' ));

    wp_client_reports_render_email_row(
        $content_stats_data->posts_count, 
        sprintf( __( 'Total %s Updates', 'wp-client-reports' ), '<br>' ), 
        $content_stats_data->pages_count, 
        sprintf( __( 'WordPress %s Updates', 'wp-client-reports' ), '<br>' )
    );

    wp_client_reports_render_email_row(
        $content_stats_data->comments_count, 
        sprintf( __( 'Plugin %s Updates', 'wp-client-reports' ), '<br>' ), 
        null, 
        null
    );
}


/**
 * Register the options that will be available on the options page
 */
add_action( 'admin_init', 'wp_client_reports_options_init', 10 );
function wp_client_reports_options_init(  ) {

	register_setting( 'wp_client_reports_options_page', 'wp_client_reports_default_email' );
    register_setting( 'wp_client_reports_options_page', 'wp_client_reports_default_intro' );
    register_setting( 'wp_client_reports_options_page', 'wp_client_reports_enable_updates' );
    register_setting( 'wp_client_reports_options_page', 'wp_client_reports_enable_content_stats' );

	add_settings_section(
		'wp_client_reports_email_section',
		__( 'Email Settings', 'wp-client-reports' ),
		'wp_client_reports_email_section_callback',
		'wp_client_reports_options_page'
	);

	add_settings_field(
		'wp_client_reports_default_email',
		__( 'Default Email Address(es)', 'wp-client-reports' ),
		'wp_client_reports_default_email_render',
		'wp_client_reports_options_page',
		'wp_client_reports_email_section'
	);

	add_settings_field(
		'wp_client_reports_default_intro',
		__( 'Default Email Intro', 'wp-client-reports' ),
		'wp_client_reports_default_intro_render',
		'wp_client_reports_options_page',
		'wp_client_reports_email_section'
    );

    add_settings_section(
		'wp_client_reports_updates_section',
		__( 'Software Updates', 'wp-client-reports' ),
		'wp_client_reports_settings_section_callback',
		'wp_client_reports_options_page'
	);
    
    add_settings_field(
		'wp_client_reports_enable_updates',
		__( 'Enable Update Tracking', 'wp-client-reports' ),
		'wp_client_reports_enable_updates_render',
		'wp_client_reports_options_page',
		'wp_client_reports_updates_section'
    );

    add_settings_section(
		'wp_client_reports_content_stats_section',
		__( 'Site Content', 'wp-client-reports' ),
		'wp_client_reports_settings_section_callback',
		'wp_client_reports_options_page'
	);
    
    add_settings_field(
		'wp_client_reports_enable_content_stats',
		__( 'Enable Site Content Stats', 'wp-client-reports' ),
		'wp_client_reports_enable_content_stats_render',
		'wp_client_reports_options_page',
		'wp_client_reports_content_stats_section'
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
function wp_client_reports_email_section_callback(  ) {
	//Print nothing
}


/**
 * Add default intro field to the options page
 */
function wp_client_reports_enable_updates_render(  ) {
	$option = get_option( 'wp_client_reports_enable_updates' );
	?>
    <label class="wp-client-reports-switch">
        <input type="checkbox" name="wp_client_reports_enable_updates" <?php if ($option == 'on') { echo "checked"; } ?>>
        <span class="wp-client-reports-slider"></span>
    </label>
	<?php
}

/**
 * Add default intro field to the options page
 */
function wp_client_reports_enable_content_stats_render(  ) {
	$option = get_option( 'wp_client_reports_enable_content_stats' );
	?>
    <label class="wp-client-reports-switch">
        <input type="checkbox" name="wp_client_reports_enable_content_stats" <?php if ($option == 'on') { echo "checked"; } ?>>
        <span class="wp-client-reports-slider"></span>
    </label>
	<?php
}


/**
 * Create the basic structure for the options page
 */
function wp_client_reports_options_page(  ) {
	?>
    <div class="wrap" id="wp-client-reports-options">
        <h1><?php _e('WP Client Reports Settings','wp-client-reports'); ?></h1>
        <form action='options.php' method='post' enctype="multipart/form-data">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="postbox-container-1" class="postbox-container">
                        <div id="submitdiv" class="postbox">
                            <h2 class="hndle"><span><?php _e('Publish', 'wp-client-reports'); ?></span></h2>
                            <div class="inside">
                                <div id="major-publishing-actions">
                                    <div id="publishing-action">
                                        <?php submit_button('Save Settings'); ?>
                                    </div><!-- #publishing-action -->
                                    <div class="clear"></div>
                                </div><!-- #major-publishing-actions -->
                            </div><!-- .inside -->
                        </div><!-- #submitdiv -->
                        <div id="wp-client-reports-pro" class="postbox">
                            <div class="inside">
                                <p>
                                    <?php _e('WP Client Reports Pro offers more branding options and additional reports such as:', 'wp-client-reports'); ?>
                                </p>
                                <ul>
                                    <li>Add Logo and Brand Color to Reports</li>
                                    <li>Google Analytics</li>
                                    <li>Pingdom & Uptime Robot</li>
                                    <li>WooCommerce</li>
                                    <li>Gravity Forms & Ninja Forms</li>
                                </ul>
                                <div><a href="https://switchwp.com/plugins/wp-client-reports/?utm_source=wordpress&utm_medium=plugin_settings&utm_campaign=sidebar" class="button" target='_blank'>WP Client Reports Pro</a></div>
                            </div>
                        </div><!-- #wp-client-reports-pro -->
                        <div id="bugs-features" class="postbox">
                            <div class="inside">
                                <p>
                                    <?php
                                        $sitelink = "<a href='https://switchwp.com/plugins/wp-client-reports/?utm_source=wordpress&utm_medium=plugin_settings&utm_campaign=sidebar' target='_blank'>";
                                        $githublink = "<a href='https://github.com/TheJester12/wp-client-reports' target='_blank'>";
                                        $pluginlink = "<a href='https://wordpress.org/plugins/wp-client-reports/' target='_blank'>";
                                        $endlink = "</a>";
                                        printf( __( 'Learn more about the plugin and its capabilities on the %1$sSwitchWP website%2$s. Found a bug or have a feature request? Let me know on the %3$sWP plugin directory%4$s, or send a pull request on %5$sGitHub%6$s.', 'wp-client-reports' ), $sitelink, $endlink, $pluginlink, $endlink, $githublink, $endlink );
                                    ?>
                                </p>
                            </div>
                        </div><!-- #bugs-features -->
                    </div><!-- .postbox-container -->
                    <div id="postbox-container-2" class="postbox-container">
                        <?php
                            settings_fields( 'wp_client_reports_options_page' );

                            global $wp_settings_sections;

                            foreach ( $wp_settings_sections['wp_client_reports_options_page'] as $section ) {

                                echo '<div class="postbox wp-client-reports-settings-postbox">';
                                    if ( $section['title'] ) {
                                        echo '<h2 class="hndle"><span>' . $section['title'] . '</span></h2>';
                                    }
                                echo '<div class="inside">';
                                echo '<table class="form-table" role="presentation">';
                                do_settings_fields( 'wp_client_reports_options_page', $section['id'] );
                                echo '</table>';
                                echo '</div><!-- .inside -->';
                                echo '</div><!-- .postbox -->';

                            }
                            //do_settings_sections( 'wp_client_reports_options_page' );
                        ?>
                    </div><!-- .postbox-container -->
                </div><!-- #post-body -->
                <br class="clear">
            </div><!-- #poststuff -->
        </form>
    </div><!-- .wrap -->
	<?php
}

/**
 * Get brand color
 */
function wp_client_reports_get_brand_color() {
    if ( is_plugin_active( 'wp-client-reports-pro/wp_client_reports_pro.php' ) ) {
        return get_option( 'wp_client_reports_pro_color', '#007cba' );
    } else {
        return '#007cba';
    }
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