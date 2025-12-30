<?php
/**
 * API-Football Client - Core HTTP and Authentication Handler
 * Handles all requests to api-football.com (formerly rapidapi.com/api-sports)
 * 
 * @package GoalV
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_API_Football_Client
{
    private $api_base_url = 'https://v3.football.api-sports.io/';
    private $api_key;
    private $daily_limit = 75000; // API-Football Ultra plan
    private $requests_made_today = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api_key = get_option('goalv_api_football_key', '');
        $this->load_request_count();
    }

    /**
     * Make authenticated API request
     * 
     * @param string $endpoint API endpoint (e.g., 'fixtures', 'teams')
     * @param array $params Query parameters
     * @param bool $force_fresh Skip cache
     * @return array Response data or error
     */
    public function request($endpoint, $params = array(), $force_fresh = false)
    {
        // Validate API key
        if (empty($this->api_key)) {
            return $this->error_response('API key not configured');
        }

        // Check rate limits
        if (!$this->can_make_request()) {
            return $this->error_response('Daily API limit reached. Resets at midnight UTC.');
        }

        // Build cache key
        $cache_key = $this->build_cache_key($endpoint, $params);

        // Try cache first (unless forced fresh)
        if (!$force_fresh) {
            $cached = $this->get_cached($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Build URL
        $url = $this->build_url($endpoint, $params);

        // Make request
        $response = $this->make_http_request($url);

        // Handle errors
        if (isset($response['error'])) {
            $this->log_error($endpoint, $response['error'], $params);
            return $response;
        }

        // Validate response structure
        if (!isset($response['response'])) {
            return $this->error_response('Invalid API response structure');
        }

        // Increment request counter
        $this->increment_request_count();

        // Cache successful response
        $cache_duration = $this->get_cache_duration($endpoint);
        $this->set_cached($cache_key, $response, $cache_duration);

        return $response;
    }

    /**
     * Make HTTP request with proper headers
     */
    private function make_http_request($url)
    {
        $args = array(
            'headers' => array(
                'x-apisports-key' => $this->api_key,
                'x-rapidapi-host' => 'v3.football.api-sports.io'
            ),
            'timeout' => 30,
            'sslverify' => true
        );

        $response = wp_remote_get($url, $args);

        // Check for WP errors
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        // Check HTTP status
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 429) {
            return array('error' => 'Rate limit exceeded');
        }

        if ($status_code !== 200) {
            return array('error' => 'HTTP ' . $status_code . ': ' . wp_remote_retrieve_response_message($response));
        }

        // Parse JSON
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('error' => 'Invalid JSON response: ' . json_last_error_msg());
        }

        // Check API errors
        if (isset($data['errors']) && !empty($data['errors'])) {
            return array('error' => 'API Error: ' . print_r($data['errors'], true));
        }

        return $data;
    }

    /**
     * Build full URL with parameters
     */
    private function build_url($endpoint, $params)
    {
        $url = rtrim($this->api_base_url, '/') . '/' . ltrim($endpoint, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Build cache key from endpoint and params
     */
    private function build_cache_key($endpoint, $params)
    {
        return 'goalv_api_' . md5($endpoint . serialize($params));
    }

    /**
     * Get cached response
     */
    private function get_cached($cache_key)
    {
        return get_transient($cache_key);
    }

    /**
     * Set cached response
     */
    private function set_cached($cache_key, $data, $duration)
    {
        set_transient($cache_key, $data, $duration);
    }

    /**
     * Determine cache duration based on endpoint type
     */
    private function get_cache_duration($endpoint)
    {
        // Live scores - 30 seconds
        if (strpos($endpoint, 'fixtures/live') !== false) {
            return 30;
        }

        // Today's matches - 5 minutes
        if (strpos($endpoint, 'fixtures') !== false) {
            return 300;
        }

        // Teams and competitions - 1 day
        if (strpos($endpoint, 'teams') !== false || strpos($endpoint, 'leagues') !== false) {
            return 86400;
        }

        // Default - 15 minutes
        return 900;
    }

    /**
     * Check if we can make another API request today
     */
    private function can_make_request()
    {
        // Always allow if under 90% of limit (safety margin)
        return $this->requests_made_today < ($this->daily_limit * 0.9);
    }

    /**
     * Load today's request count from database
     */
    private function load_request_count()
    {
        $count_data = get_option('goalv_api_requests_today', array(
            'count' => 0,
            'date' => current_time('Y-m-d')
        ));

        // Reset if it's a new day
        $today = current_time('Y-m-d');
        if ($count_data['date'] !== $today) {
            $count_data = array('count' => 0, 'date' => $today);
            update_option('goalv_api_requests_today', $count_data);
        }

        $this->requests_made_today = $count_data['count'];
    }

    /**
     * Increment request counter
     */
    private function increment_request_count()
    {
        $this->requests_made_today++;
        
        update_option('goalv_api_requests_today', array(
            'count' => $this->requests_made_today,
            'date' => current_time('Y-m-d')
        ));
    }

    /**
     * Get current request statistics
     */
    public function get_request_stats()
    {
        $this->load_request_count();

        return array(
            'requests_today' => $this->requests_made_today,
            'daily_limit' => $this->daily_limit,
            'remaining' => max(0, $this->daily_limit - $this->requests_made_today),
            'percentage_used' => round(($this->requests_made_today / $this->daily_limit) * 100, 2),
            'reset_time' => strtotime('tomorrow 00:00:00 UTC')
        );
    }

    /**
     * Test API connection
     */
    public function test_connection()
    {
        $response = $this->request('status', array(), true);

        if (isset($response['error'])) {
            return array(
                'success' => false,
                'message' => $response['error']
            );
        }

        if (isset($response['response'])) {
            return array(
                'success' => true,
                'message' => 'API connection successful',
                'account' => $response['response']['account'] ?? array(),
                'requests' => $response['response']['requests'] ?? array()
            );
        }

        return array(
            'success' => false,
            'message' => 'Unexpected response format'
        );
    }

    /**
     * Clear all API cache
     */
    public function clear_cache()
    {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_goalv_api_%' 
             OR option_name LIKE '_transient_timeout_goalv_api_%'"
        );

        return true;
    }

    /**
     * Create standardized error response
     */
    private function error_response($message)
    {
        return array(
            'error' => $message,
            'response' => array(),
            'timestamp' => current_time('mysql')
        );
    }

    /**
     * Log API errors
     */
    private function log_error($endpoint, $error, $params)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'GoalV API Error [%s]: %s | Params: %s',
                $endpoint,
                $error,
                json_encode($params)
            ));
        }

        // Store recent errors in option for admin dashboard
        $recent_errors = get_option('goalv_api_recent_errors', array());
        
        array_unshift($recent_errors, array(
            'endpoint' => $endpoint,
            'error' => $error,
            'params' => $params,
            'timestamp' => current_time('mysql')
        ));

        // Keep only last 20 errors
        $recent_errors = array_slice($recent_errors, 0, 20);
        
        update_option('goalv_api_recent_errors', $recent_errors);
    }

    /**
     * Get recent API errors for debugging
     */
    public function get_recent_errors($limit = 10)
    {
        $errors = get_option('goalv_api_recent_errors', array());
        return array_slice($errors, 0, $limit);
    }
}