<?php
/**
 * Plugin Name: Zoho SSO Integration
 * Description: Simple Zoho Single Sign-On integration for WordPress
 * Version: 1.0.0
 * Author: Adam
 */

if (!defined('ABSPATH')) exit;

class Zoho_SSO {
    
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    
    public function __construct() {
        $this->client_id = get_option('zoho_sso_client_id');
        $this->client_secret = get_option('zoho_sso_client_secret');
        $this->redirect_uri = home_url('/?zoho_sso=callback');
        
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('login_form', [$this, 'add_login_button']);
        add_action('init', [$this, 'handle_sso_requests']);
    }
    
    public function add_settings_page() {
        add_options_page(
            'Zoho SSO Settings',
            'Zoho SSO',
            'manage_options',
            'zoho-sso',
            [$this, 'settings_page']
        );
    }
    
    public function register_settings() {
        register_setting('zoho_sso_settings', 'zoho_sso_client_id');
        register_setting('zoho_sso_settings', 'zoho_sso_client_secret');
        register_setting('zoho_sso_settings', 'zoho_sso_domain');
        register_setting('zoho_sso_settings', 'zoho_sso_org_id');
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Zoho SSO Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('zoho_sso_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th>Client ID</th>
                        <td><input type="text" name="zoho_sso_client_id" value="<?php echo esc_attr($this->client_id); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Client Secret</th>
                        <td><input type="text" name="zoho_sso_client_secret" value="<?php echo esc_attr($this->client_secret); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Zoho Domain</th>
                        <td>
                            <select name="zoho_sso_domain">
                                <option value="com" <?php selected(get_option('zoho_sso_domain'), 'com'); ?>>zoho.com</option>
                                <option value="eu" <?php selected(get_option('zoho_sso_domain'), 'eu'); ?>>zoho.eu</option>
                                <option value="in" <?php selected(get_option('zoho_sso_domain'), 'in'); ?>>zoho.in</option>
                                <option value="com.cn" <?php selected(get_option('zoho_sso_domain'), 'com.cn'); ?>>zoho.com.cn</option>
                                <option value="com.au" <?php selected(get_option('zoho_sso_domain'), 'com.au'); ?>>zoho.com.au</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Organization ID</th>
                        <td>
                            <input type="text" name="zoho_sso_org_id" value="<?php echo esc_attr(get_option('zoho_sso_org_id')); ?>" class="regular-text">
                            <p class="description">Required for fetching subscription data from Zoho Subscriptions</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Redirect URI</th>
                        <td><code><?php echo esc_url($this->redirect_uri); ?></code><br><small>Use this URL in your Zoho OAuth settings</small></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function add_login_button() {
        if (!$this->client_id || !$this->client_secret) return;
        
        $login_url = home_url('/?zoho_sso=login');
        ?>
        <p style="text-align: center; margin: 20px 0;">
            <a href="<?php echo esc_url($login_url); ?>" class="button button-primary button-large" style="width: 100%;">
                Sign in with Zoho
            </a>
        </p>
        <p style="text-align: center; margin: 10px 0; color: #666;">— OR —</p>
        <?php
    }
    
    public function handle_sso_requests() {
        if (!isset($_GET['zoho_sso'])) return;
        
        if ($_GET['zoho_sso'] === 'login') {
            $this->initiate_login();
        } elseif ($_GET['zoho_sso'] === 'callback') {
            $this->handle_callback();
        }
    }
    
    public function initiate_login() {
        $domain = get_option('zoho_sso_domain', 'com');
        $auth_url = "https://accounts.zoho.{$domain}/oauth/v2/auth";
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'scope' => 'AaaServer.profile.READ,ZohoSubscriptions.subscriptions.READ',
            'redirect_uri' => $this->redirect_uri,
            'access_type' => 'offline',
            'state' => wp_create_nonce('zoho_sso_state')
        ];
        
        $url = $auth_url . '?' . http_build_query($params);
        wp_redirect($url);
        exit;
    }
    
    public function handle_callback() {
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            wp_die('Invalid callback');
        }
        
        if (!wp_verify_nonce($_GET['state'], 'zoho_sso_state')) {
            wp_die('Invalid state');
        }
        
        $domain = get_option('zoho_sso_domain', 'com');
        $token_url = "https://accounts.zoho.{$domain}/oauth/v2/token";
        
        $response = wp_remote_post($token_url, [
            'body' => [
                'grant_type' => 'authorization_code',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $this->redirect_uri,
                'code' => $_GET['code']
            ]
        ]);
        
        if (is_wp_error($response)) {
            wp_die('Error getting access token');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['access_token'])) {
            wp_die('No access token received');
        }
        
        $user_info = $this->get_user_info($body['access_token'], $domain);
        
        if (!$user_info) {
            wp_die('Error getting user info');
        }
        
        // Get subscription data
        $subscription_data = $this->get_subscription_data($body['access_token'], $user_info['Email'], $domain);
        
        $user = $this->create_or_update_user($user_info, $subscription_data);
        
        if ($user) {
            wp_set_auth_cookie($user->ID);
            wp_redirect(admin_url());
            exit;
        }
        
        wp_die('Error creating user');
    }
    
    private function get_user_info($access_token, $domain) {
        $response = wp_remote_get("https://accounts.zoho.{$domain}/oauth/user/info", [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $access_token
            ]
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    private function get_subscription_data($access_token, $email, $domain) {
        $org_id = get_option('zoho_sso_org_id');
        
        if (!$org_id) {
            return null;
        }
        
        $api_domain = $this->get_api_domain($domain);
        $response = wp_remote_get("https://subscriptions.zoho.{$api_domain}/api/v1/customers?email={$email}", [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'X-com-zoho-subscriptions-organizationid' => $org_id
            ]
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($data['customers'][0])) {
            return null;
        }
        
        $customer_id = $data['customers'][0]['customer_id'];
        
        // Get subscriptions for this customer
        $sub_response = wp_remote_get("https://subscriptions.zoho.{$api_domain}/api/v1/subscriptions?customer_id={$customer_id}", [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'X-com-zoho-subscriptions-organizationid' => $org_id
            ]
        ]);
        
        if (is_wp_error($sub_response)) {
            return null;
        }
        
        $sub_data = json_decode(wp_remote_retrieve_body($sub_response), true);
        
        return [
            'customer_id' => $customer_id,
            'subscriptions' => $sub_data['subscriptions'] ?? []
        ];
    }
    
    private function get_api_domain($domain) {
        $map = [
            'com' => 'com',
            'eu' => 'eu',
            'in' => 'in',
            'com.cn' => 'com.cn',
            'com.au' => 'com.au'
        ];
        return $map[$domain] ?? 'com';
    }
    
    private function create_or_update_user($user_info, $subscription_data = null) {
        $email = $user_info['Email'];
        $user = get_user_by('email', $email);
        
        if ($user) {
            // Update subscription data for existing user
            if ($subscription_data) {
                update_user_meta($user->ID, 'zoho_customer_id', $subscription_data['customer_id']);
                update_user_meta($user->ID, 'zoho_subscriptions', $subscription_data['subscriptions']);
                update_user_meta($user->ID, 'zoho_subscription_updated', current_time('mysql'));
            }
            return $user;
        }
        
        $user_id = wp_create_user(
            sanitize_user($user_info['Email']),
            wp_generate_password(),
            $email
        );
        
        if (is_wp_error($user_id)) {
            return false;
        }
        
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $user_info['First_Name'] ?? '',
            'last_name' => $user_info['Last_Name'] ?? '',
            'display_name' => $user_info['Display_Name'] ?? $email
        ]);
        
        // Store subscription data
        if ($subscription_data) {
            update_user_meta($user_id, 'zoho_customer_id', $subscription_data['customer_id']);
            update_user_meta($user_id, 'zoho_subscriptions', $subscription_data['subscriptions']);
            update_user_meta($user_id, 'zoho_subscription_updated', current_time('mysql'));
        }
        
        return get_user_by('id', $user_id);
    }
    
    // Helper function to check if user has active subscription
    public static function has_active_subscription($user_id) {
        $subscriptions = get_user_meta($user_id, 'zoho_subscriptions', true);
        
        if (!$subscriptions) {
            return false;
        }
        
        foreach ($subscriptions as $sub) {
            if (in_array($sub['status'], ['live', 'active', 'non_renewing'])) {
                return true;
            }
        }
        
        return false;
    }
    
    // Helper function to get subscription details
    public static function get_subscription_details($user_id) {
        return [
            'customer_id' => get_user_meta($user_id, 'zoho_customer_id', true),
            'subscriptions' => get_user_meta($user_id, 'zoho_subscriptions', true),
            'last_updated' => get_user_meta($user_id, 'zoho_subscription_updated', true)
        ];
    }
}

new Zoho_SSO();
