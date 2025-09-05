<?php
/**
 * Job Killer Admin Auto Feeds
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle automatic feeds administration
 */
class Job_Killer_Admin_Auto_Feeds {
    
    /**
     * Providers manager
     */
    private $providers_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->providers_manager = new Job_Killer_Providers_Manager();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_job_killer_delete_auto_feed', array($this, 'ajax_delete_auto_feed'));
        add_action('wp_ajax_job_killer_toggle_auto_feed', array($this, 'ajax_toggle_auto_feed'));
        add_action('wp_ajax_job_killer_import_auto_feed', array($this, 'ajax_import_auto_feed'));
    }
    
    /**
     * Render auto feeds page
     */
    public function render_page() {
        $auto_feeds = get_option('job_killer_auto_feeds', array());
        $providers = $this->providers_manager->get_providers();
        
        include JOB_KILLER_PLUGIN_DIR . 'includes/templates/admin/auto-feeds.php';
    }
    
    /**
     * Delete auto feed (AJAX)
     */
    public function ajax_delete_auto_feed() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $feed_id = sanitize_key($_POST['feed_id'] ?? '');
        
        if (empty($feed_id)) {
            wp_send_json_error(__('Feed ID is required', 'job-killer'));
        }
        
        $auto_feeds = get_option('job_killer_auto_feeds', array());
        
        if (!isset($auto_feeds[$feed_id])) {
            wp_send_json_error(__('Feed not found', 'job-killer'));
        }
        
        $feed_name = $auto_feeds[$feed_id]['name'];
        unset($auto_feeds[$feed_id]);
        update_option('job_killer_auto_feeds', $auto_feeds);
        
        $helper = new Job_Killer_Helper();
        $helper->log('info', 'admin', 
            sprintf('Auto feed "%s" deleted', $feed_name),
            array('feed_id' => $feed_id)
        );
        
        wp_send_json_success(array(
            'message' => __('Feed deleted successfully!', 'job-killer')
        ));
    }
    
    /**
     * Toggle auto feed status (AJAX)
     */
    public function ajax_toggle_auto_feed() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $feed_id = sanitize_key($_POST['feed_id'] ?? '');
        
        if (empty($feed_id)) {
            wp_send_json_error(__('Feed ID is required', 'job-killer'));
        }
        
        $auto_feeds = get_option('job_killer_auto_feeds', array());
        
        if (!isset($auto_feeds[$feed_id])) {
            wp_send_json_error(__('Feed not found', 'job-killer'));
        }
        
        $auto_feeds[$feed_id]['active'] = !empty($auto_feeds[$feed_id]['active']) ? false : true;
        $auto_feeds[$feed_id]['updated_at'] = current_time('mysql');
        
        update_option('job_killer_auto_feeds', $auto_feeds);
        
        $status = $auto_feeds[$feed_id]['active'] ? __('activated', 'job-killer') : __('deactivated', 'job-killer');
        
        $helper = new Job_Killer_Helper();
        $helper->log('info', 'admin', 
            sprintf('Auto feed "%s" %s', $auto_feeds[$feed_id]['name'], $status),
            array('feed_id' => $feed_id, 'active' => $auto_feeds[$feed_id]['active'])
        );
        
        wp_send_json_success(array(
            'message' => sprintf(__('Feed %s successfully!', 'job-killer'), $status),
            'active' => $auto_feeds[$feed_id]['active']
        ));
    }
    
    /**
     * Import from auto feed (AJAX)
     */
    public function ajax_import_auto_feed() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $feed_id = sanitize_key($_POST['feed_id'] ?? '');
        
        if (empty($feed_id)) {
            wp_send_json_error(__('Feed ID is required', 'job-killer'));
        }
        
        $auto_feeds = get_option('job_killer_auto_feeds', array());
        
        if (!isset($auto_feeds[$feed_id])) {
            wp_send_json_error(__('Feed not found', 'job-killer'));
        }
        
        // Generate unique ID if not provided
        $feed_id = !empty($feed_data['id']) ? sanitize_key($feed_data['id']) : 'auto_feed_' . uniqid() . '_' . time();
        
        // Generate unique ID if not provided
        $feed_id = !empty($feed_data['id']) ? sanitize_key($feed_data['id']) : 'auto_feed_' . uniqid() . '_' . time();
        
        // Sanitize feed configuration
        $feed_config = $auto_feeds[$feed_id];
            wp_send_json_error(__('Provider not found', 'job-killer'));
        }
        
        try {
            $imported = $provider->import_jobs($feed_config);
            
            // Update last import time
            $auto_feeds[$feed_id]['last_import'] = current_time('mysql');
            update_option('job_killer_auto_feeds', $auto_feeds);
            
            wp_send_json_success(array(
                'message' => sprintf(__('Successfully imported %d jobs!', 'job-killer'), $imported),
                'imported' => $imported
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get providers manager
     */
    /**
     * Delete auto feed (AJAX) - Updated to clear cron
     */
    public function ajax_delete_auto_feed() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $feed_id = sanitize_key($_POST['feed_id'] ?? '');
        
        if (empty($feed_id)) {
            wp_send_json_error(__('Feed ID is required', 'job-killer'));
        }
        
        $auto_feeds = get_option('job_killer_auto_feeds', array());
        
        if (!isset($auto_feeds[$feed_id])) {
            wp_send_json_error(__('Feed not found', 'job-killer'));
        }
        
        $feed_name = $auto_feeds[$feed_id]['name'];
        
        // Clear individual cron for this feed
        $hook_name = 'job_killer_import_auto_feed_' . $feed_id;
        wp_clear_scheduled_hook($hook_name);
        
        unset($auto_feeds[$feed_id]);
        update_option('job_killer_auto_feeds', $auto_feeds);
        
        $helper = new Job_Killer_Helper();
        $helper->log('info', 'admin', 
            sprintf('Auto feed "%s" deleted', $feed_name),
            array('feed_id' => $feed_id)
        );
        
        wp_send_json_success(array(
            'message' => __('Feed deleted successfully!', 'job-killer')
        ));
    }
    
    /**
     * Toggle auto feed status (AJAX) - Updated to handle cron
     */
    public function ajax_toggle_auto_feed() {
        check_ajax_referer('job_killer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'job-killer'));
        }
        
        $feed_id = sanitize_key($_POST['feed_id'] ?? '');
        
        if (empty($feed_id)) {
            wp_send_json_error(__('Feed ID is required', 'job-killer'));
        }
        
        $auto_feeds = get_option('job_killer_auto_feeds', array());
        
        if (!isset($auto_feeds[$feed_id])) {
            wp_send_json_error(__('Feed not found', 'job-killer'));
        }
        
        $auto_feeds[$feed_id]['active'] = !empty($auto_feeds[$feed_id]['active']) ? false : true;
        $auto_feeds[$feed_id]['updated_at'] = current_time('mysql');
        
        update_option('job_killer_auto_feeds', $auto_feeds);
        
        // Update cron schedule
        $this->schedule_feed_cron($auto_feeds[$feed_id]);
        
        $status = $auto_feeds[$feed_id]['active'] ? __('activated', 'job-killer') : __('deactivated', 'job-killer');
        
        $helper = new Job_Killer_Helper();
        $helper->log('info', 'admin', 
            sprintf('Auto feed "%s" %s', $auto_feeds[$feed_id]['name'], $status),
            array('feed_id' => $feed_id, 'active' => $auto_feeds[$feed_id]['active'])
        );
        
        wp_send_json_success(array(
            'message' => sprintf(__('Feed %s successfully!', 'job-killer'), $status),
            'active' => $auto_feeds[$feed_id]['active']
        ));
    }
    
    public function get_providers_manager() {
        return $this->providers_manager;
        // Schedule individual cron for this feed
        $this->schedule_feed_cron($feed_config);
        
    }
}
    /**
     * Schedule individual cron for feed
     */
    private function schedule_feed_cron($feed_config) {
        $hook_name = 'job_killer_import_auto_feed_' . $feed_config['id'];
        
        // Clear existing schedule
        wp_clear_scheduled_hook($hook_name);
        
        // Schedule new cron if feed is active
        if (!empty($feed_config['active'])) {
            $interval = $feed_config['cron_interval'] ?? 'twicedaily';
            wp_schedule_event(time(), $interval, $hook_name, array($feed_config['id']));
            
            // Add action for this specific feed
            add_action($hook_name, array($this, 'run_single_feed_import'));
        }
    }
    
    /**
     * Run import for a single feed
     */
    public function run_single_feed_import($feed_id) {
        $auto_feeds = get_option('job_killer_auto_feeds', array());
        
        if (!isset($auto_feeds[$feed_id]) || empty($auto_feeds[$feed_id]['active'])) {
            return;
        }
        
        $feed_config = $auto_feeds[$feed_id];
        $provider = $this->providers_manager->get_provider($feed_config['provider_id']);
        
        if (!$provider) {
            return;
        }
        
        try {
            $imported = $provider->import_jobs($feed_config);
            
            // Update last import time
            $auto_feeds[$feed_id]['last_import'] = current_time('mysql');
            update_option('job_killer_auto_feeds', $auto_feeds);
            
            $helper = new Job_Killer_Helper();
            $helper->log('success', 'cron', 
                sprintf('Auto feed "%s" imported %d jobs', $feed_config['name'], $imported),
                array('feed_id' => $feed_id, 'imported' => $imported)
            );
            
        } catch (Exception $e) {
            $helper = new Job_Killer_Helper();
            $helper->log('error', 'cron', 
                sprintf('Auto feed "%s" import failed: %s', $feed_config['name'], $e->getMessage()),
                array('feed_id' => $feed_id, 'error' => $e->getMessage())
            );
        }
    }
    