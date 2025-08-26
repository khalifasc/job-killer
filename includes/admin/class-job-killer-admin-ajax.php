<?php
/**
 * Job Killer Admin AJAX Class
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle admin AJAX requests
 */
class Job_Killer_Admin_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Feed management
        add_action('wp_ajax_job_killer_test_feed', array($this, 'test_feed'));
        add_action('wp_ajax_job_killer_save_feed', array($this, 'save_feed'));
        add_action('wp_ajax_job_killer_delete_feed', array($this, 'delete_feed'));
        add_action('wp_ajax_job_killer_toggle_feed', array($this, 'toggle_feed'));
        add_action('wp_ajax_job_killer_import_feed', array($this, 'import_feed'));
        
        // Scheduling
        add_action('wp_ajax_job_killer_run_import', array($this, 'run_import'));
        add_action('wp_ajax_job_killer_update_schedule', array($this, 'update_schedule'));
        
        // Logs
        add_action('wp_ajax_job_killer_clear_logs', array($this, 'clear_logs'));
        add_action('wp_ajax_job_killer_export_logs', array($this, 'export_logs'));
        
        // Settings
        add_action('wp_ajax_job_killer_reset_settings', array($this, 'reset_settings'));
        add_action('wp_ajax_job_killer_export_settings', array($this, 'export_settings'));
        add_action('wp_ajax_job_killer_import_settings', array($this, 'import_settings'));
        
        // System
        add_action('wp_ajax_job_killer_system_info', array($this, 'system_info'));
        add_action('wp_ajax_job_killer_get_chart_data', array($this, 'get_chart_data'));
    }
    
    /**
     * Test RSS feed
     */
    public function test_feed() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $url = sanitize_url($_POST['url'] ?? '');
        
        if (empty($url)) {
            wp_send_json_error(__('Feed URL is required', 'job-killer'));
        }
        
        $helper = new Job_Killer_Helper();
        $validation = $helper->validate_feed_url($url);
        
        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message());
        }
        
        // Test feed parsing
        $importer = new Job_Killer_Importer();
        $rss_providers = new Job_Killer_Rss_Providers();
        
        $provider_id = $rss_providers->detect_provider($url);
        $provider_config = $rss_providers->get_provider_config($provider_id);
        
        $feed_config = array(
            'url' => $url,
            'field_mapping' => $provider_config['field_mapping'],
            'name' => 'Test Feed'
        );
        
        $result = $importer->test_feed_import($feed_config);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => sprintf(__('Feed test successful! Found %d jobs.', 'job-killer'), $result['jobs_found']),
                'jobs_found' => $result['jobs_found'],
                'sample_jobs' => $result['sample_jobs'],
                'provider' => $provider_id,
                'provider_name' => $provider_config['name']
            ));
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Save RSS feed
     */
    public function save_feed() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $feed_data = $_POST['feed'] ?? array();
        
        if (empty($feed_data['name']) || empty($feed_data['url'])) {
            wp_send_json_error(__('Feed name and URL are required', 'job-killer'));
        }
        
        $helper = new Job_Killer_Helper();
        $feed_config = $helper->sanitize_feed_config($feed_data);
        
        // Validate URL
        $validation = $helper->validate_feed_url($feed_config['url']);
        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message());
        }
        
        // Generate ID if not provided
        if (empty($feed_config['id'])) {
            $feed_config['id'] = sanitize_key($feed_config['name']) . '_' . time();
        }
        
        // Save feed
        $feeds = get_option('job_killer_feeds', array());
        $feeds[$feed_config['id']] = $feed_config;
        update_option('job_killer_feeds', $feeds);
        
        $helper->log('info', 'admin', 
            sprintf('Feed "%s" saved successfully', $feed_config['name']),
            array('feed_id' => $feed_config['id'])
        );
        
        wp_send_json_success(array(
            'message' => __('Feed saved successfully!', 'job-killer'),
            'feed' => $feed_config
        ));
    }
    
    /**
     * Delete RSS feed
     */
    public function delete_feed() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $feed_id = sanitize_key($_POST['feed_id'] ?? '');
        
        if (empty($feed_id)) {
            wp_send_json_error(__('Feed ID is required', 'job-killer'));
        }
        
        $feeds = get_option('job_killer_feeds', array());
        
        if (!isset($feeds[$feed_id])) {
            wp_send_json_error(__('Feed not found', 'job-killer'));
        }
        
        $feed_name = $feeds[$feed_id]['name'];
        unset($feeds[$feed_id]);
        update_option('job_killer_feeds', $feeds);
        
        $helper = new Job_Killer_Helper();
        $helper->log('info', 'admin', 
            sprintf('Feed "%s" deleted', $feed_name),
            array('feed_id' => $feed_id)
        );
        
        wp_send_json_success(array(
            'message' => __('Feed deleted successfully!', 'job-killer')
        ));
    }
    
    /**
     * Toggle feed active status
     */
    public function toggle_feed() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $feed_id = sanitize_key($_POST['feed_id'] ?? '');
        
        if (empty($feed_id)) {
            wp_send_json_error(__('Feed ID is required', 'job-killer'));
        }
        
        $feeds = get_option('job_killer_feeds', array());
        
        if (!isset($feeds[$feed_id])) {
            wp_send_json_error(__('Feed not found', 'job-killer'));
        }
        
        $feeds[$feed_id]['active'] = !empty($feeds[$feed_id]['active']) ? false : true;
        $feeds[$feed_id]['updated_at'] = current_time('mysql');
        
        update_option('job_killer_feeds', $feeds);
        
        $status = $feeds[$feed_id]['active'] ? __('activated', 'job-killer') : __('deactivated', 'job-killer');
        
        $helper = new Job_Killer_Helper();
        $helper->log('info', 'admin', 
            sprintf('Feed "%s" %s', $feeds[$feed_id]['name'], $status),
            array('feed_id' => $feed_id, 'active' => $feeds[$feed_id]['active'])
        );
        
        wp_send_json_success(array(
            'message' => sprintf(__('Feed %s successfully!', 'job-killer'), $status),
            'active' => $feeds[$feed_id]['active']
        ));
    }
    
    /**
     * Import from specific feed
     */
    public function import_feed() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $feed_id = sanitize_key($_POST['feed_id'] ?? '');
        
        if (empty($feed_id)) {
            wp_send_json_error(__('Feed ID is required', 'job-killer'));
        }
        
        $feeds = get_option('job_killer_feeds', array());
        
        if (!isset($feeds[$feed_id])) {
            wp_send_json_error(__('Feed not found', 'job-killer'));
        }
        
        $importer = new Job_Killer_Importer();
        
        try {
            $imported = $importer->import_from_feed($feed_id, $feeds[$feed_id]);
            
            wp_send_json_success(array(
                'message' => sprintf(__('Successfully imported %d jobs!', 'job-killer'), $imported),
                'imported' => $imported
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Run manual import
     */
    public function run_import() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $importer = new Job_Killer_Importer();
        $importer->run_scheduled_import();
        
        wp_send_json_success(array(
            'message' => __('Import completed successfully!', 'job-killer')
        ));
    }
    
    /**
     * Update cron schedule
     */
    public function update_schedule() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $interval = sanitize_text_field($_POST['interval'] ?? '');
        
        $allowed_intervals = array(
            'every_30_minutes', 'hourly', 'every_2_hours', 
            'every_6_hours', 'twicedaily', 'daily'
        );
        
        if (!in_array($interval, $allowed_intervals)) {
            wp_send_json_error(__('Invalid interval', 'job-killer'));
        }
        
        // Clear existing schedule
        wp_clear_scheduled_hook('job_killer_import_jobs');
        
        // Schedule with new interval
        wp_schedule_event(time(), $interval, 'job_killer_import_jobs');
        
        // Update settings
        $settings = get_option('job_killer_settings', array());
        $settings['cron_interval'] = $interval;
        update_option('job_killer_settings', $settings);
        
        $helper = new Job_Killer_Helper();
        $helper->log('info', 'admin', 
            sprintf('Cron schedule updated to %s', $interval),
            array('interval' => $interval)
        );
        
        wp_send_json_success(array(
            'message' => __('Schedule updated successfully!', 'job-killer'),
            'next_run' => wp_next_scheduled('job_killer_import_jobs')
        ));
    }
    
    /**
     * Clear logs
     */
    public function clear_logs() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? '');
        
        global $wpdb;
        $table = $wpdb->prefix . 'job_killer_logs';
        
        if (!empty($type)) {
            $deleted = $wpdb->delete($table, array('type' => $type));
        } else {
            $deleted = $wpdb->query("TRUNCATE TABLE $table");
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cleared %d log entries', 'job-killer'), $deleted)
        ));
    }
    
    /**
     * Export logs
     */
    public function export_logs() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $filters = array(
            'type' => sanitize_text_field($_POST['type'] ?? ''),
            'source' => sanitize_text_field($_POST['source'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        );
        
        $helper = new Job_Killer_Helper();
        $export = $helper->export_logs_csv($filters);
        
        if ($export) {
            wp_send_json_success(array(
                'message' => __('Logs exported successfully!', 'job-killer'),
                'download_url' => $export['url'],
                'filename' => $export['filename']
            ));
        } else {
            wp_send_json_error(__('No logs to export', 'job-killer'));
        }
    }
    
    /**
     * Reset settings
     */
    public function reset_settings() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $settings = new Job_Killer_Admin_Settings();
        $settings->reset_to_defaults();
        
        wp_send_json_success(array(
            'message' => __('Settings reset to defaults successfully!', 'job-killer')
        ));
    }
    
    /**
     * Export settings
     */
    public function export_settings() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $settings = new Job_Killer_Admin_Settings();
        $export_data = $settings->export_settings();
        
        wp_send_json_success(array(
            'data' => $export_data,
            'filename' => 'job-killer-settings-' . date('Y-m-d-H-i-s') . '.json'
        ));
    }
    
    /**
     * Import settings
     */
    public function import_settings() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $json_data = wp_unslash($_POST['data'] ?? '');
        
        if (empty($json_data)) {
            wp_send_json_error(__('No data provided', 'job-killer'));
        }
        
        $settings = new Job_Killer_Admin_Settings();
        $result = $settings->import_settings($json_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Settings imported successfully!', 'job-killer')
        ));
    }
    
    /**
     * Get system info
     */
    public function system_info() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $helper = new Job_Killer_Helper();
        $system_info = $helper->get_system_info();
        
        wp_send_json_success($system_info);
    }
    
    /**
     * Get chart data
     */
    public function get_chart_data() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $days = intval($_POST['days'] ?? 30);
        $days = max(7, min(365, $days)); // Limit between 7 and 365 days
        
        $helper = new Job_Killer_Helper();
        $chart_data = $helper->get_chart_data($days);
        
        wp_send_json_success($chart_data);
    }
}