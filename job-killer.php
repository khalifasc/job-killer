<?php
/**
 * Plugin Name: Job Killer
 * Plugin URI: https://github.com/jobkiller/job-killer
 * Description: Plugin completo para automatização de importação de vagas de emprego via RSS e APIs externas com funcionalidades avançadas de gerenciamento e deduplicação.
 * Version: 1.0.0
 * Author: Job Killer Team
 * Author URI: https://jobkiller.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: job-killer
 * Domain Path: /languages
 * Requires at least: 6.8
 * Tested up to: 6.8
 * Requires PHP: 8.1
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('JOB_KILLER_VERSION', '1.0.0');
define('JOB_KILLER_PLUGIN_FILE', __FILE__);
define('JOB_KILLER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JOB_KILLER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JOB_KILLER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'Job_Killer_') !== 0) {
        return;
    }
    
    $class_file = str_replace('_', '-', strtolower($class));
    $file_path = JOB_KILLER_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
    
    if (file_exists($file_path)) {
        require_once $file_path;
    }
});

/**
 * Main plugin class
 */
final class Job_Killer {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Core instance
     */
    public $core;
    
    /**
     * Get plugin instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'), 0);
        add_action('init', array($this, 'load_textdomain'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('Job_Killer', 'uninstall'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check requirements
        if (!$this->check_requirements()) {
            return;
        }
        
        // Initialize core
        $this->core = new Job_Killer_Core();
        $this->core->init();
        
        do_action('job_killer_loaded');
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('job-killer', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo sprintf(
                    __('Job Killer requires PHP 8.1 or higher. You are running PHP %s.', 'job-killer'),
                    PHP_VERSION
                );
                echo '</p></div>';
            });
            return false;
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '6.8', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo sprintf(
                    __('Job Killer requires WordPress 6.8 or higher. You are running WordPress %s.', 'job-killer'),
                    get_bloginfo('version')
                );
                echo '</p></div>';
            });
            return false;
        }
        
        // Check required extensions
        $required_extensions = array('curl', 'json', 'libxml');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                add_action('admin_notices', function() use ($extension) {
                    echo '<div class="notice notice-error"><p>';
                    echo sprintf(
                        __('Job Killer requires the %s PHP extension to be installed.', 'job-killer'),
                        $extension
                    );
                    echo '</p></div>';
                });
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Create default options
        $this->create_default_options();
        
        // Schedule cron events
        $this->schedule_cron_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('job_killer_activated', true);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron events
        wp_clear_scheduled_hook('job_killer_import_jobs');
        wp_clear_scheduled_hook('job_killer_cleanup_logs');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Remove options
        delete_option('job_killer_settings');
        delete_option('job_killer_feeds');
        delete_option('job_killer_activated');
        delete_option('job_killer_version');
        
        // Remove custom tables
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}job_killer_logs");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}job_killer_imports");
        
        // Clear cron events
        wp_clear_scheduled_hook('job_killer_import_jobs');
        wp_clear_scheduled_hook('job_killer_cleanup_logs');
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Logs table
        $logs_table = $wpdb->prefix . 'job_killer_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(20) NOT NULL DEFAULT 'info',
            source varchar(100) NOT NULL,
            message text NOT NULL,
            data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY source (source),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Imports table
        $imports_table = $wpdb->prefix . 'job_killer_imports';
        $imports_sql = "CREATE TABLE $imports_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feed_id varchar(100) NOT NULL,
            job_hash varchar(64) NOT NULL,
            post_id bigint(20) NOT NULL,
            imported_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY job_hash (job_hash),
            KEY feed_id (feed_id),
            KEY post_id (post_id),
            KEY imported_at (imported_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($logs_sql);
        dbDelta($imports_sql);
    }
    
    /**
     * Create default options
     */
    private function create_default_options() {
        $default_settings = array(
            'import_limit' => 50,
            'age_filter' => 0,
            'description_min_length' => 100,
            'deduplication_enabled' => true,
            'auto_taxonomies' => true,
            'cron_interval' => 'twicedaily',
            'timeout' => 30,
            'request_delay' => 1,
            'cache_duration' => 3600,
            'log_retention_days' => 30,
            'email_notifications' => true,
            'notification_email' => get_option('admin_email'),
            'structured_data' => true
        );
        
        add_option('job_killer_settings', $default_settings);
        add_option('job_killer_feeds', array());
        add_option('job_killer_version', JOB_KILLER_VERSION);
    }
    
    /**
     * Schedule cron events
     */
    private function schedule_cron_events() {
        if (!wp_next_scheduled('job_killer_import_jobs')) {
            wp_schedule_event(time(), 'twicedaily', 'job_killer_import_jobs');
        }
        
        if (!wp_next_scheduled('job_killer_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'job_killer_cleanup_logs');
        }
    }
}

/**
 * Initialize plugin
 */
function job_killer() {
    return Job_Killer::instance();
}

// Start the plugin
job_killer();