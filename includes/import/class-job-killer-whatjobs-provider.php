<?php
/**
 * Job Killer WhatJobs Provider
 *
 * @package Job_Killer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WhatJobs API Provider
 */
class Job_Killer_WhatJobs_Provider {
    
    /**
     * Provider ID
     */
    const PROVIDER_ID = 'whatjobs';
    
    /**
     * API Base URL
     */
    const API_BASE_URL = 'https://api.whatjobs.com/api/v1/jobs.xml';
    
    /**
     * Helper instance
     */
    private $helper;
    
    /**
     * Constructor
     */
    public function __construct() {
        if (class_exists('Job_Killer_Helper')) {
            $this->helper = new Job_Killer_Helper();
        }
    }
    
    /**
     * Get provider information
     */
    public function get_provider_info() {
        return array(
            'id' => self::PROVIDER_ID,
            'name' => 'WhatJobs',
            'description' => __('Import jobs from WhatJobs API with advanced filtering and mapping.', 'job-killer'),
            'requires_auth' => true,
            'auth_fields' => array(
                'publisher_id' => array(
                    'label' => __('Publisher ID', 'job-killer'),
                    'type' => 'text',
                    'required' => true,
                    'description' => __('Your WhatJobs Publisher ID (required for API access)', 'job-killer')
                )
            ),
            'parameters' => array(
                'keyword' => array(
                    'label' => __('Keywords', 'job-killer'),
                    'type' => 'text',
                    'description' => __('Job search keywords (optional)', 'job-killer')
                ),
                'location' => array(
                    'label' => __('Location', 'job-killer'),
                    'type' => 'text',
                    'description' => __('Job location (city, state, or country)', 'job-killer')
                ),
                'limit' => array(
                    'label' => __('Results Limit', 'job-killer'),
                    'type' => 'number',
                    'default' => 50,
                    'min' => 1,
                    'max' => 100,
                    'description' => __('Maximum number of jobs to import per request', 'job-killer')
                ),
                'page' => array(
                    'label' => __('Page', 'job-killer'),
                    'type' => 'number',
                    'default' => 1,
                    'min' => 1,
                    'description' => __('Page number for pagination', 'job-killer')
                ),
                'age_days' => array(
                    'label' => __('Age in Days', 'job-killer'),
                    'type' => 'number',
                    'default' => 0,
                    'min' => 0,
                    'max' => 30,
                    'description' => __('Maximum age of jobs in days (0 = today only)', 'job-killer')
                )
            ),
            'cron_intervals' => array(
                'every_30_minutes' => __('Every 30 Minutes', 'job-killer'),
                'hourly' => __('Every Hour', 'job-killer'),
                'every_2_hours' => __('Every 2 Hours', 'job-killer'),
                'every_6_hours' => __('Every 6 Hours', 'job-killer'),
                'twicedaily' => __('Twice Daily', 'job-killer'),
                'daily' => __('Daily', 'job-killer')
            ),
            'field_mapping' => array(
                'title' => 'title',
                'company' => 'company',
                'location' => 'location',
                'description' => 'description',
                'url' => 'url',
                'job_type' => 'job_type',
                'salary' => 'salary',
                'logo' => 'logo',
                'age_days' => 'age_days',
                'date' => 'date'
            )
        );
    }
    
    /**
     * Build API URL with proper parameters
     */
    public function build_api_url($config) {
        $publisher_id = $config['auth']['publisher_id'] ?? '';
        
        if (empty($publisher_id)) {
            throw new Exception(__('Publisher ID is required for WhatJobs API', 'job-killer'));
        }
        
        // Build base parameters with required fields
        $params = array(
            'publisher'  => $publisher_id,
            'user_ip'    => $this->get_user_ip(),
            'user_agent' => urlencode($this->get_user_agent()),
            'snippet'    => 'full'
        );
        
        // Add optional parameters
        if (!empty($config['parameters']['keyword'])) {
            $params['keyword'] = sanitize_text_field($config['parameters']['keyword']);
        }
        
        if (!empty($config['parameters']['location'])) {
            $params['location'] = sanitize_text_field($config['parameters']['location']);
        }
        
        if (!empty($config['parameters']['limit'])) {
            $params['limit'] = min(100, max(1, intval($config['parameters']['limit'])));
        }
        
        if (!empty($config['parameters']['page'])) {
            $params['page'] = max(1, intval($config['parameters']['page']));
        }
        
        // Age filter - 0 means today only
        $age_days = isset($config['parameters']['age_days']) ? intval($config['parameters']['age_days']) : 0;
        $params['age_days'] = max(0, min(30, $age_days));
        
        return add_query_arg($params, self::API_BASE_URL);
    }
    
    /**
     * Get user IP address with fallback
     */
    private function get_user_ip() {
        // Try to get real IP from various headers (for proxy/CDN scenarios)
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'HTTP_X_FORWARDED',          // Proxy header
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy header
            'HTTP_FORWARDED',            // Proxy header
            'REMOTE_ADDR'                // Standard server variable
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback for cron jobs or when no valid IP is found
        return '127.0.0.1';
    }
    
    /**
     * Get user agent with fallback
     */
    private function get_user_agent() {
        // Use server user agent if available
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            return $_SERVER['HTTP_USER_AGENT'];
        }
        
        // Fallback for cron jobs or missing user agent
        return 'JobKillerBot/1.0 (WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ')';
    }
    
    /**
     * Test API connection
     */
    public function test_connection($config) {
        try {
            $url = $this->build_api_url($config);
            
            if ($this->helper) {
                $this->helper->log('info', 'whatjobs', 
                    'Testing WhatJobs API connection',
                    array('url' => $url, 'config' => $config)
                );
            }
            
            $response = $this->fetch_api_data($url);
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }
            
            $jobs = $this->parse_xml_response($response);
            
            return array(
                'success' => true,
                'message' => sprintf(__('Connection successful! Found %d jobs.', 'job-killer'), count($jobs)),
                'jobs_found' => count($jobs),
                'sample_jobs' => array_slice($jobs, 0, 3),
                'api_url' => $url // Include URL for debugging
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Fetch data from API with multiple fallback methods (adapted from GoFetch)
     */
    private function fetch_api_data($url) {
        $timeout = 30;
        
        // Method 1: Standard wp_remote_get
        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
            'user-agent' => $this->get_user_agent(),
            'headers' => array(
                'Accept' => 'application/xml, text/xml, */*'
            )
        ));
        
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 200) {
                $body = wp_remote_retrieve_body($response);
                if (!empty($body)) {
                    return $body;
                }
            }
        }
        
        // Method 2: Try with different headers
        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'headers' => array(
                'Accept' => 'application/xml, text/xml, */*',
                'Accept-Language' => 'en-US,en;q=0.9'
            )
        ));
        
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 200) {
                $body = wp_remote_retrieve_body($response);
                if (!empty($body)) {
                    return $body;
                }
            }
        }
        
        // Method 3: Try with file_get_contents as fallback
        if (function_exists('file_get_contents') && ini_get('allow_url_fopen')) {
            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => $timeout,
                    'user_agent' => $this->get_user_agent(),
                    'header' => 'Accept: application/xml, text/xml\r\n'
                )
            ));
            
            $body = @file_get_contents($url, false, $context);
            if (!empty($body)) {
                return $body;
            }
        }
        
        return new WP_Error('fetch_failed', __('Failed to fetch data from WhatJobs API', 'job-killer'));
    }
    
    /**
     * Import jobs from API
     */
    public function import_jobs($config) {
        if ($this->helper) {
            $this->helper->log('info', 'whatjobs', 'Starting WhatJobs import', array('config' => $config));
        }
        
        try {
            $url = $this->build_api_url($config);
            
            $xml_content = $this->fetch_api_data($url);
            
            if (is_wp_error($xml_content)) {
                throw new Exception('Failed to fetch API data: ' . $xml_content->get_error_message());
            }
            
            $jobs = $this->parse_xml_response($xml_content);
            
            if ($this->helper) {
                $this->helper->log('info', 'whatjobs', 
                    sprintf('Found %d jobs from WhatJobs API', count($jobs)),
                    array('jobs_count' => count($jobs))
                );
            }
            
            // Filter and import jobs
            $imported_count = 0;
            foreach ($jobs as $job_data) {
                if ($this->should_import_job($job_data)) {
                    if ($this->import_single_job($job_data, $config)) {
                        $imported_count++;
                    }
                }
            }
            
            if ($this->helper) {
                $this->helper->log('success', 'whatjobs', 
                    sprintf('WhatJobs import completed. Imported %d jobs.', $imported_count),
                    array('imported' => $imported_count, 'total_found' => count($jobs))
                );
            }
            
            return $imported_count;
            
        } catch (Exception $e) {
            if ($this->helper) {
                $this->helper->log('error', 'whatjobs', 
                    'WhatJobs import failed: ' . $e->getMessage(),
                    array('error' => $e->getMessage())
                );
            }
            throw $e;
        }
    }
    
    /**
     * Parse XML response (adapted from GoFetch logic)
     */
    private function parse_xml_response($xml_content) {
        // Suppress XML errors
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($xml_content, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOERROR);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = array();
            foreach ($errors as $error) {
                $error_messages[] = trim($error->message);
            }
            throw new Exception('XML parsing failed: ' . implode(', ', $error_messages));
        }
        
        $jobs = array();
        
        // Check if XML uses Field tag names structure (like GoFetch handles)
        if ($this->uses_field_tag_names($xml)) {
            $items = $this->get_items_from_field_tag_names($xml);
            
            foreach ($items as $item) {
                $job = $this->parse_field_tag_job($item);
                if (!empty($job)) {
                    $jobs[] = $job;
                }
            }
        } else {
            // Standard XML structure
            if (isset($xml->job)) {
                foreach ($xml->job as $job_xml) {
                    $job = $this->parse_job_xml($job_xml);
                    if (!empty($job)) {
                        $jobs[] = $job;
                    }
                }
            }
        }
        
        return $jobs;
    }
    
    /**
     * Check if XML uses Field tag names structure (from GoFetch)
     */
    private function uses_field_tag_names($xml) {
        if (!empty($xml->job)) {
            $root = $xml->job;
        } elseif (!empty($xml->item)) {
            $root = $xml->item;
        } elseif (!empty($xml->channel->item)) {
            $root = $xml->channel->item;
        } else {
            return false;
        }
        
        return !empty($root[0]->Field);
    }
    
    /**
     * Get items from Field tag names structure (from GoFetch)
     */
    private function get_items_from_field_tag_names($xml) {
        $items = array();
        
        $jobs = $xml->job ?? $xml->item ?? array();
        
        foreach ($jobs as $job) {
            $flat_job = (array) $job;
            $item = array();
            $index = 0;
            
            if (isset($job->Field)) {
                foreach ($job->Field as $field) {
                    $value = isset($flat_job['Field'][$index]) ? $flat_job['Field'][$index] : '';
                    $attributes = $field->attributes();
                    
                    if (isset($attributes['name'])) {
                        $item[(string)$attributes['name']] = (string)$value;
                    }
                    
                    $index++;
                }
            }
            
            if (!empty($item)) {
                $items[] = $item;
            }
        }
        
        return $items;
    }
    
    /**
     * Parse job from Field tag structure
     */
    private function parse_field_tag_job($item) {
        return array(
            'title' => $this->clean_field_value($item['title'] ?? ''),
            'company' => $this->clean_field_value($item['company'] ?? ''),
            'location' => $this->clean_field_value($item['location'] ?? ''),
            'description' => $this->clean_description($item['description'] ?? ''),
            'url' => esc_url_raw($item['url'] ?? ''),
            'job_type' => $this->clean_field_value($item['job_type'] ?? ''),
            'salary' => $this->clean_field_value($item['salary'] ?? ''),
            'logo' => esc_url_raw($item['logo'] ?? ''),
            'age_days' => intval($item['age_days'] ?? 0),
            'date' => $this->clean_field_value($item['date'] ?? ''),
            'category' => $this->clean_field_value($item['category'] ?? ''),
            'subcategory' => $this->clean_field_value($item['subcategory'] ?? ''),
            'country' => $this->clean_field_value($item['country'] ?? ''),
            'state' => $this->clean_field_value($item['state'] ?? ''),
            'city' => $this->clean_field_value($item['city'] ?? ''),
            'postal_code' => $this->clean_field_value($item['postal_code'] ?? ''),
            'employment_type' => $this->normalize_employment_type($item['job_type'] ?? ''),
            'remote_work' => $this->detect_remote_work($item)
        );
    }
    
    /**
     * Parse individual job XML (standard structure)
     */
    private function parse_job_xml($job_xml) {
        $job = array(
            'title' => $this->clean_field_value((string) $job_xml->title),
            'company' => $this->clean_field_value((string) $job_xml->company),
            'location' => $this->clean_field_value((string) $job_xml->location),
            'description' => $this->clean_description((string) $job_xml->description),
            'url' => esc_url_raw((string) $job_xml->url),
            'job_type' => $this->clean_field_value((string) $job_xml->job_type),
            'salary' => $this->clean_field_value((string) $job_xml->salary),
            'logo' => esc_url_raw((string) $job_xml->logo),
            'age_days' => intval($job_xml->age_days),
            'date' => $this->clean_field_value((string) $job_xml->date),
            'category' => $this->clean_field_value((string) $job_xml->category),
            'subcategory' => $this->clean_field_value((string) $job_xml->subcategory),
            'country' => $this->clean_field_value((string) $job_xml->country),
            'state' => $this->clean_field_value((string) $job_xml->state),
            'city' => $this->clean_field_value((string) $job_xml->city),
            'postal_code' => $this->clean_field_value((string) $job_xml->postal_code)
        );
        
        // Additional processing
        $job['employment_type'] = $this->normalize_employment_type($job['job_type']);
        $job['remote_work'] = $this->detect_remote_work($job);
        
        return $job;
    }
    
    /**
     * Clean field value (from GoFetch logic)
     */
    private function clean_field_value($value) {
        if (is_array($value)) {
            $value = implode(' ', $value);
        }
        
        // Remove CDATA
        $value = preg_replace('/<\!\[CDATA\[(.*?)\]\]>/s', '$1', $value);
        
        // Clean HTML entities
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        
        return trim($value);
    }
    
    /**
     * Clean and format job description
     */
    private function clean_description($description) {
        if (empty($description)) {
            return '';
        }
        
        // Remove CDATA
        $description = preg_replace('/<\!\[CDATA\[(.*?)\]\]>/s', '$1', $description);
        
        // Clean HTML entities
        $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
        
        // Remove excessive whitespace
        $description = preg_replace('/\s+/', ' ', $description);
        
        // Convert line breaks to proper HTML
        $description = nl2br($description);
        
        // Clean up HTML but preserve structure
        $allowed_tags = array(
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'b' => array(),
            'em' => array(),
            'i' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'div' => array('class' => array()),
            'span' => array('class' => array())
        );
        
        $description = wp_kses($description, $allowed_tags);
        
        // Fix common formatting issues
        $description = preg_replace('/<br\s*\/?>\s*<br\s*\/?>/i', '</p><p>', $description);
        $description = '<p>' . $description . '</p>';
        $description = preg_replace('/<p>\s*<\/p>/', '', $description);
        
        return trim($description);
    }
    
    /**
     * Normalize employment type
     */
    private function normalize_employment_type($job_type) {
        $type = strtolower(trim($job_type));
        
        $type_mapping = array(
            'full time' => 'FULL_TIME',
            'full-time' => 'FULL_TIME',
            'tempo integral' => 'FULL_TIME',
            'part time' => 'PART_TIME',
            'part-time' => 'PART_TIME',
            'meio período' => 'PART_TIME',
            'contract' => 'CONTRACTOR',
            'contractor' => 'CONTRACTOR',
            'freelance' => 'CONTRACTOR',
            'temporary' => 'TEMPORARY',
            'temporário' => 'TEMPORARY',
            'internship' => 'INTERN',
            'estágio' => 'INTERN'
        );
        
        return isset($type_mapping[$type]) ? $type_mapping[$type] : 'FULL_TIME';
    }
    
    /**
     * Detect remote work
     */
    private function detect_remote_work($job_data) {
        $remote_keywords = array(
            'remoto', 'remote', 'home office', 'trabalho remoto', 
            'teletrabalho', 'work from home', 'wfh'
        );
        
        $search_text = strtolower(
            ($job_data['title'] ?? '') . ' ' . 
            ($job_data['description'] ?? '') . ' ' . 
            ($job_data['location'] ?? '')
        );
        
        foreach ($remote_keywords as $keyword) {
            if (strpos($search_text, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if job should be imported
     */
    private function should_import_job($job_data) {
        // Skip if no title
        if (empty($job_data['title'])) {
            return false;
        }
        
        // Skip if description is empty or too short
        $description = strip_tags($job_data['description']);
        $settings = get_option('job_killer_settings', array());
        $min_length = $settings['description_min_length'] ?? 100;
        
        if (strlen($description) < $min_length) {
            return false;
        }
        
        // Check for duplicates
        if ($this->is_duplicate_job($job_data)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if job is duplicate (adapted from GoFetch logic)
     */
    private function is_duplicate_job($job_data) {
        $settings = get_option('job_killer_settings', array());
        
        if (empty($settings['deduplication_enabled'])) {
            return false;
        }
        
        global $wpdb;
        
        $title = sanitize_text_field($job_data['title']);
        $company = sanitize_text_field($job_data['company']);
        $location = sanitize_text_field($job_data['location']);
        
        // Clean and sanitize for comparison (like GoFetch does)
        $clean_title = $this->clean_and_sanitize($title, true);
        
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_company_name'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_job_location'
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND TRIM(LOWER(p.post_title)) LIKE %s
            AND (pm1.meta_value = %s OR %s = '')
            AND (pm2.meta_value = %s OR %s = '')
            LIMIT 1
        ", '%' . strtolower($clean_title) . '%', $company, $company, $location, $location));
        
        return !empty($existing);
    }
    
    /**
     * Clean and sanitize text (from GoFetch)
     */
    private function clean_and_sanitize($text, $alphanumeric_only = false) {
        // Remove all whitespace (including tabs and line ends)
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        if ($alphanumeric_only) {
            // Keep only alphanumeric characters and spaces
            $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
        }
        
        return trim($text);
    }
    
    /**
     * Import single job
     */
    private function import_single_job($job_data, $config) {
        try {
            // Prepare post data
            $post_data = array(
                'post_title' => sanitize_text_field($job_data['title']),
                'post_content' => wp_kses_post($job_data['description']),
                'post_status' => 'publish',
                'post_type' => 'job_listing',
                'post_author' => 1,
                'post_date' => $this->get_post_date($job_data),
                'meta_input' => $this->prepare_job_meta($job_data, $config)
            );
            
            // Insert post
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create job post: ' . $post_id->get_error_message());
            }
            
            // Set taxonomies
            $this->set_job_taxonomies($post_id, $job_data);
            
            // Handle company logo
            if (!empty($job_data['logo'])) {
                $this->handle_company_logo($post_id, $job_data['logo'], $job_data['company']);
            }
            
            // Trigger hooks
            do_action('job_killer_after_job_import', $post_id, $job_data, self::PROVIDER_ID);
            
            return true;
            
        } catch (Exception $e) {
            if ($this->helper) {
                $this->helper->log('error', 'whatjobs', 
                    'Failed to import job: ' . $e->getMessage(),
                    array('job_data' => $job_data, 'error' => $e->getMessage())
                );
            }
            return false;
        }
    }
    
    /**
     * Get post date from job data
     */
    private function get_post_date($job_data) {
        if (!empty($job_data['date'])) {
            $date = date('Y-m-d H:i:s', strtotime($job_data['date']));
            
            // Validate date - if invalid, use current time
            if (strtotime($date) < strtotime('2000-01-01')) {
                return current_time('mysql');
            }
            
            return $date;
        }
        
        return current_time('mysql');
    }
    
    /**
     * Prepare job meta data
     */
    private function prepare_job_meta($job_data, $config) {
        $meta = array(
            // Core WP Job Manager fields
            '_job_location' => sanitize_text_field($job_data['location']),
            '_company_name' => sanitize_text_field($job_data['company']),
            '_application' => esc_url_raw($job_data['url']),
            '_job_expires' => $this->calculate_expiry_date($job_data),
            '_filled' => 0,
            '_featured' => 0,
            '_job_salary' => sanitize_text_field($job_data['salary']),
            '_remote_position' => $job_data['remote_work'] ? 1 : 0,
            
            // Job Killer specific
            '_job_killer_provider' => self::PROVIDER_ID,
            '_job_killer_imported' => current_time('mysql'),
            '_job_killer_source_url' => esc_url_raw($job_data['url']),
            '_job_killer_age_days' => intval($job_data['age_days']),
            '_job_killer_feed_id' => $config['id'] ?? '',
            
            // WhatJobs specific
            '_whatjobs_category' => sanitize_text_field($job_data['category']),
            '_whatjobs_subcategory' => sanitize_text_field($job_data['subcategory']),
            '_whatjobs_country' => sanitize_text_field($job_data['country']),
            '_whatjobs_state' => sanitize_text_field($job_data['state']),
            '_whatjobs_city' => sanitize_text_field($job_data['city']),
            '_whatjobs_postal_code' => sanitize_text_field($job_data['postal_code'])
        );
        
        // Add employment type for structured data
        $meta['_employment_type'] = $job_data['employment_type'];
        
        // Set job status for WP Job Manager
        $meta['_job_status'] = 'active';
        
        return $meta;
    }
    
    /**
     * Set job taxonomies
     */
    private function set_job_taxonomies($post_id, $job_data) {
        // Set job type
        if (!empty($job_data['job_type'])) {
            $job_type = $this->normalize_job_type($job_data['job_type']);
            $this->set_or_create_term($post_id, $job_type, 'job_listing_type');
        }
        
        // Set category based on WhatJobs category
        if (!empty($job_data['category'])) {
            $category = sanitize_text_field($job_data['category']);
            $this->set_or_create_term($post_id, $category, 'job_listing_category');
        }
        
        // Set region based on location
        if (!empty($job_data['state'])) {
            $region = sanitize_text_field($job_data['state']);
            $this->set_or_create_term($post_id, $region, 'job_listing_region');
        } elseif (!empty($job_data['city'])) {
            $region = sanitize_text_field($job_data['city']);
            $this->set_or_create_term($post_id, $region, 'job_listing_region');
        }
    }
    
    /**
     * Set or create taxonomy term
     */
    private function set_or_create_term($post_id, $term_name, $taxonomy) {
        if (empty($term_name)) {
            return;
        }
        
        $term = get_term_by('name', $term_name, $taxonomy);
        
        if (!$term) {
            $term_result = wp_insert_term($term_name, $taxonomy);
            if (!is_wp_error($term_result)) {
                $term = get_term($term_result['term_id'], $taxonomy);
            }
        }
        
        if ($term && !is_wp_error($term)) {
            wp_set_post_terms($post_id, array($term->term_id), $taxonomy);
        }
    }
    
    /**
     * Normalize job type for taxonomy
     */
    private function normalize_job_type($job_type) {
        $type = strtolower(trim($job_type));
        
        $type_mapping = array(
            'full time' => 'Tempo Integral',
            'full-time' => 'Tempo Integral',
            'part time' => 'Meio Período',
            'part-time' => 'Meio Período',
            'contract' => 'Contrato',
            'contractor' => 'Contrato',
            'freelance' => 'Freelance',
            'temporary' => 'Temporário',
            'internship' => 'Estágio',
            'intern' => 'Estágio'
        );
        
        return isset($type_mapping[$type]) ? $type_mapping[$type] : ucfirst($job_type);
    }
    
    /**
     * Calculate job expiry date
     */
    private function calculate_expiry_date($job_data) {
        // Default to 30 days from now
        return date('Y-m-d', strtotime('+30 days'));
    }
    
    /**
     * Handle company logo
     */
    private function handle_company_logo($post_id, $logo_url, $company_name) {
        if (empty($logo_url) || !filter_var($logo_url, FILTER_VALIDATE_URL)) {
            return;
        }
        
        // Try to download and attach the logo
        $attachment_id = $this->download_company_logo($logo_url, $post_id, $company_name);
        
        if ($attachment_id) {
            update_post_meta($post_id, '_company_logo', $attachment_id);
        } else {
            // Store URL as fallback
            update_post_meta($post_id, '_company_logo_url', esc_url($logo_url));
        }
    }
    
    /**
     * Download company logo
     */
    private function download_company_logo($url, $post_id, $company_name) {
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        // Download file
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return false;
        }
        
        // Get file extension
        $file_info = pathinfo($url);
        $extension = isset($file_info['extension']) ? $file_info['extension'] : 'jpg';
        
        // Prepare file array
        $file_array = array(
            'name' => sanitize_file_name($company_name . '-logo.' . $extension),
            'tmp_name' => $tmp
        );
        
        // Handle sideload
        $attachment_id = media_handle_sideload($file_array, $post_id, 'Logo da empresa ' . $company_name);
        
        // Clean up temp file
        @unlink($tmp);
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        return $attachment_id;
    }
    
    /**
     * Get provider statistics
     */
    public function get_provider_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total imported jobs
        $stats['total_imported'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_job_killer_provider'
            AND pm.meta_value = %s
        ", self::PROVIDER_ID));
        
        // Jobs imported today
        $stats['today_imported'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_job_killer_provider'
            AND pm.meta_value = %s
            AND DATE(p.post_date) = CURDATE()
        ", self::PROVIDER_ID));
        
        // Active jobs
        $stats['active_jobs'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_job_killer_provider'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_filled'
            WHERE p.post_type = 'job_listing'
            AND p.post_status = 'publish'
            AND pm1.meta_value = %s
            AND (pm2.meta_value IS NULL OR pm2.meta_value = '0')
        ", self::PROVIDER_ID));
        
        return $stats;
    }
}