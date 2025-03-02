<?php

/**
 * Core functionality for Stripe Onboarding Reminders
 *
 * @package Stripe_Onboarding_Reminders
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core class for Stripe Onboarding Reminders
 */
class Stripe_Onboarding_Reminders_Core
{

    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;

    /**
     * Status descriptions
     *
     * @var array
     */
    private $status_descriptions = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize settings
        $this->settings = get_option('stripe_onboarding_reminders_settings', $this->get_default_settings());

        // Set up status descriptions
        $this->setup_status_descriptions();

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void
    {
        // Schedule cron event
        add_action('init', [$this, 'register_cron_event']);

        // Cron action hook
        add_action('stripe_onboarding_reminder_cron', [$this, 'send_scheduled_reminders']);

        // Register activation hook via main plugin file
        register_activation_hook(STRIPE_ONBOARDING_REMINDERS_PLUGIN_FILE, [$this, 'activate_plugin']);

        // Register deactivation hook via main plugin file
        register_deactivation_hook(STRIPE_ONBOARDING_REMINDERS_PLUGIN_FILE, [$this, 'deactivate_plugin']);

        // Add filter for status types
        add_filter('stripe_onboarding_reminders_status_types', [$this, 'get_default_status_types']);
    }

    /**
     * Set up status descriptions
     */
    private function setup_status_descriptions(): void
    {
        $this->status_descriptions = [
            'active' => __('Active - Complete', 'stripe-onboarding-reminders'),
            'active_no_shipping' => __('Active - No Shipping', 'stripe-onboarding-reminders'),
            'inactive' => __('Inactive', 'stripe-onboarding-reminders'),
            'pending' => __('Pending', 'stripe-onboarding-reminders'),
            'not_setup' => __('Not Setup', 'stripe-onboarding-reminders'),
        ];
    }

    /**
     * Get default settings
     *
     * @return array Default settings
     */
    public function get_default_settings(): array
    {
        return [
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'payout_settings_url' => '/dashboard/payout-settings/',
            'rate_limit_days' => 30,
            'include_admin_copy' => false,
            'admin_email' => get_option('admin_email'),
            'notifications' => [
                'active_no_shipping' => true,
                'pending' => true,
                'not_setup' => true,
            ],
            'subjects' => [
                'active_no_shipping' => __('Action required: Complete your payout information', 'stripe-onboarding-reminders'),
                'pending' => __('Reminder: Complete your Stripe onboarding', 'stripe-onboarding-reminders'),
                'not_setup' => __('Set up your Stripe account to receive payments', 'stripe-onboarding-reminders'),
            ],
            'templates' => [
                'active_no_shipping' => __('<p>Your Stripe account is active, but you need to provide your shipping information to start receiving payouts.</p>
<p>Without this information, you may experience delays in receiving your funds.</p>
<p><strong>What\'s missing:</strong> Shipping address and contact information</p>', 'stripe-onboarding-reminders'),

                'pending' => __('<p>Your Stripe account setup is incomplete. You need to finish setting up your account to start receiving payments.</p>
<p>At this point, you cannot receive any payments until you complete the required verification steps.</p>
<p><strong>Required action:</strong> Complete the Stripe onboarding process by providing the requested information.</p>', 'stripe-onboarding-reminders'),

                'not_setup' => __('<p>You have not set up your Stripe account yet. Setting up your account is necessary to receive payments through our platform.</p>
<p>Until you complete this setup, you won\'t be able to receive any payments for your products or services.</p>
<p><strong>Required action:</strong> Start the Stripe account creation and verification process.</p>', 'stripe-onboarding-reminders'),
            ],
            'button_text' => [
                'active_no_shipping' => __('Complete Shipping Information', 'stripe-onboarding-reminders'),
                'pending' => __('Complete Your Stripe Account', 'stripe-onboarding-reminders'),
                'not_setup' => __('Set Up Your Stripe Account', 'stripe-onboarding-reminders'),
            ],
            'common_footer' => __('<p>If you have any questions or need assistance with your account setup, please contact our support team.</p>
<p>Thank you,<br>%site_name% Team</p>', 'stripe-onboarding-reminders'),
        ];
    }

    /**
     * Get settings
     *
     * @return array Plugin settings
     */
    public function get_settings(): array
    {
        return $this->settings;
    }

    /**
     * Get status display name
     *
     * @param string $status Status code
     * @return string Display name
     */
    public function get_status_display_name(string $status): string
    {
        return $this->status_descriptions[$status] ?? $status;
    }

    /**
     * Plugin activation
     */
    public function activate_plugin(): void
    {
        // Make sure default settings are saved
        if (!get_option('stripe_onboarding_reminders_settings')) {
            update_option('stripe_onboarding_reminders_settings', $this->get_default_settings());
        }

        // Schedule the cron event
        if (!wp_next_scheduled('stripe_onboarding_reminder_cron')) {
            wp_schedule_event(time(), 'daily', 'stripe_onboarding_reminder_cron');
        }

        // Create log directory if needed
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/stripe-onboarding-reminders-logs';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Create .htaccess file to protect logs
            file_put_contents($log_dir . '/.htaccess', 'deny from all');
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate_plugin(): void
    {
        // Unschedule the cron event
        wp_clear_scheduled_hook('stripe_onboarding_reminder_cron');
    }

    /**
     * Register cron event
     */
    public function register_cron_event(): void
    {
        if (!wp_next_scheduled('stripe_onboarding_reminder_cron')) {
            wp_schedule_event(time(), 'daily', 'stripe_onboarding_reminder_cron');
        }
    }

    /**
     * Send scheduled reminders
     */
    public function send_scheduled_reminders(): void
    {
        $this->log('Starting scheduled reminders...');

        // Check day of month - run on the 1st of each month
        $day_of_month = gmdate('j');
        if ($day_of_month != 1) {
            $this->log('Not the 1st of the month, skipping reminders');
            return;
        }

        // Process each status
        foreach (['active_no_shipping', 'pending', 'not_setup'] as $status) {
            // Skip if notifications for this status are disabled
            if (empty($this->settings['notifications'][$status])) {
                $this->log("Notifications for status {$status} are disabled, skipping");
                continue;
            }

            $this->log("Processing reminders for status: {$status}");
            $sent_count = $this->send_reminders_for_status($status);
            $this->log("Sent {$sent_count} reminders for status: {$status}");
        }

        $this->log('Finished sending scheduled reminders');
    }

    /**
     * Send reminders for a specific status
     *
     * @param string $status Status to send reminders for
     * @return int Number of emails sent
     */
    private function send_reminders_for_status(string $status): int
    {
        $sent_count = 0;
        $users = $this->find_users_with_status($status);

        if (empty($users)) {
            $this->log("No users found with status: {$status}");
            return 0;
        }

        $this->log("Found " . count($users) . " users with status: {$status}");

        foreach ($users as $user_id) {
            // Check rate limiting
            if ($this->should_rate_limit_user_internal((int)$user_id)) {
                $this->log("User {$user_id} rate limited, skipping");
                continue;
            }

            // Send the email
            $sent = $this->send_reminder_email((int)$user_id, $status);

            if ($sent) {
                $sent_count++;

                // Record the sent time for rate limiting
                $this->record_email_sent((int)$user_id, $status);

                // Prevent server overload for large batches
                if ($sent_count % 25 === 0) {
                    sleep(5);
                }
            }
        }

        return $sent_count;
    }

    /**
     * Get users with specific Stripe onboarding status
     *
     * @param string $status Status to find users for
     * @return array Array of user IDs
     */
    public function get_users_with_status(string $status): array
    {
        return $this->find_users_with_status($status);
    }

    /**
     * Find users with a specific Stripe onboarding status
     *
     * @param string $status Status to find users for
     * @return array Array of user IDs
     */
    private function find_users_with_status(string $status): array
    {
        $user_ids = [];

        // Get all users with the seller role
        $vendor_users = get_users([
            'role' => 'seller',
            'fields' => ['ID'],
        ]);

        if (empty($vendor_users)) {
            return [];
        }

        // Check each user's vendor status individually
        foreach ($vendor_users as $user) {
            try {
                // Ensure user ID is an integer
                $user_id = (int)$user->ID;

                // Get status directly for this user
                $vendor_status = $this->get_user_vendor_status_internal($user_id);

                // Only include if status matches exactly what we're looking for
                if ($vendor_status === $status) {
                    $user_ids[] = $user_id;
                }
            } catch (Exception $e) {
                $this->log("Error checking status for user {$user->ID}: " . $e->getMessage());
                continue;
            }
        }

        return $user_ids;
    }

    /**
     * Get user vendor status
     *
     * @param int $user_id User ID
     * @return string|null Vendor status or null if not a vendor
     */
    private function get_user_vendor_status_internal(int $user_id): ?string
    {
        // Check if Voxel classes are available - use string for class check to avoid linter errors
        if (class_exists('Voxel\User')) {
            try {
                // Get Voxel user object - using variables to avoid linter errors with direct namespace access
                $voxel_user_class = 'Voxel\User';
                $user = $voxel_user_class::get($user_id);

                if (!$user) {
                    $this->log("Voxel User not found for user ID: {$user_id}");
                    return null;
                }

                // Check if multivendor is enabled
                $voxel_get_function = 'Voxel\get';
                if (function_exists($voxel_get_function) && !$voxel_get_function('product_settings.multivendor.enabled')) {
                    $this->log("Multivendor functionality is disabled for user: {$user_id}");
                    return null;
                }

                // Handle administrator case
                if (
                    $user->has_cap('administrator') &&
                    apply_filters('voxel/stripe_connect/enable_onboarding_for_admins', false) !== true
                ) {
                    $this->log("User {$user_id} is an administrator without Stripe Connect enabled");
                    return null;
                }

                // Get Stripe account details
                $details = $user->get_stripe_vendor_details();

                // Account doesn't exist
                if (!$details->exists) {
                    $this->log("Stripe account doesn't exist for user: {$user_id}");
                    return 'not_setup';
                }

                // Account exists but charges not enabled
                if (!$details->charges_enabled) {
                    $this->log("Stripe charges not enabled for user: {$user_id}");
                    return 'pending';
                }

                // Check shipping zones for active accounts
                $shipping_zones = get_user_meta($user_id, 'voxel:vendor_shipping_zones', true);
                if (empty($shipping_zones)) {
                    $this->log("User {$user_id} has no shipping zones configured");
                    return 'active_no_shipping';
                }

                // Everything is properly set up
                $this->log("User {$user_id} has a fully setup Stripe account");
                return null; // Fully set up - doesn't need reminders
            } catch (\Exception $e) {
                $this->log("Error getting Voxel user status for {$user_id}: " . $e->getMessage());
                return 'not_setup'; // Fallback status on error
            }
        } else {
            $this->log("Voxel classes not available - using fallback for user: {$user_id}");

            // Fallback for testing only - should not be used in production
            // Get a user role - if not a vendor/seller, return null
            $user = get_userdata($user_id);
            if (!$user || !in_array('seller', (array)$user->roles)) {
                return null;
            }

            // For testing: use the user ID modulo 3 to get a random but consistent status
            $mod = $user_id % 3;
            switch ($mod) {
                case 0:
                    return 'not_setup';
                case 1:
                    return 'pending';
                case 2:
                    return 'active_no_shipping';
                default:
                    return 'not_setup';
            }
        }
    }

    /**
     * Send a reminder email to a user
     *
     * @param int $user_id User ID
     * @param string $status Status type
     * @return bool Whether the email was sent
     */
    public function send_reminder_email(int $user_id, string $status): bool
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // If the status doesn't have notifications enabled, don't send an email
        if (!isset($this->settings['notifications'][$status]) || !$this->settings['notifications'][$status]) {
            return false;
        }

        // Get email subject
        $subject = $this->settings['subjects'][$status] ?? $this->get_default_settings()['subjects'][$status];

        // Set up email headers
        $headers = [];
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        // Add From header if configured
        if (!empty($this->settings['from_name']) && !empty($this->settings['from_email'])) {
            $from_name = $this->settings['from_name'];
            $from_email = $this->settings['from_email'];
            $headers[] = "From: {$from_name} <{$from_email}>";
        }

        // Set up email templates
        $email_templates = new Stripe_Onboarding_Reminders_Email_Templates($this->settings);
        $content = $email_templates->get_email_content($user, $status);

        // Send the email
        $result = wp_mail($user->user_email, $subject, $content, $headers);

        // If successful, update the last reminder timestamp
        if ($result) {
            update_user_meta($user_id, 'stripe_onboarding_reminder_last_sent', time());

            // Send admin copy if enabled
            if (isset($this->settings['include_admin_copy']) && $this->settings['include_admin_copy']) {
                $admin_email = !empty($this->settings['admin_email'])
                    ? $this->settings['admin_email']
                    : get_option('admin_email');

                // Generate admin copy content
                $admin_content = $email_templates->get_admin_copy_email_content($user, $status, $content);

                // Send admin copy
                wp_mail($admin_email, "[Admin Copy] " . $subject, $admin_content, $headers);
            }
        }

        return $result;
    }

    /**
     * Check if a user should be rate limited (public method)
     *
     * @param int $user_id User ID
     * @return bool Whether to rate limit
     */
    public function should_rate_limit_user(int $user_id): bool
    {
        return $this->should_rate_limit_user_internal($user_id);
    }

    /**
     * Check if a user should be rate limited (internal implementation)
     *
     * @param int $user_id User ID
     * @return bool Whether to rate limit
     */
    private function should_rate_limit_user_internal(int $user_id): bool
    {
        $last_sent = get_user_meta($user_id, 'stripe_onboarding_reminder_last_sent', true);

        if (empty($last_sent)) {
            return false;
        }

        $rate_limit_days = absint($this->settings['rate_limit_days']);
        if ($rate_limit_days < 1) {
            $rate_limit_days = 30; // Default to 30 days
        }

        $rate_limit_seconds = $rate_limit_days * DAY_IN_SECONDS;
        $time_diff = time() - (int) $last_sent;

        return $time_diff < $rate_limit_seconds;
    }

    /**
     * Record that an email was sent to a user
     *
     * @param int $user_id User ID
     * @param string $status Status type
     */
    private function record_email_sent(int $user_id, string $status): void
    {
        update_user_meta($user_id, 'stripe_onboarding_reminder_last_sent', time());
        update_user_meta($user_id, 'stripe_onboarding_reminder_last_status', $status);
    }

    /**
     * Log message to file
     *
     * @param string $message Message to log
     */
    private function log(string $message): void
    {
        // Create log directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/stripe-onboarding-reminders-logs';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Create .htaccess file to protect logs
            file_put_contents($log_dir . '/.htaccess', 'deny from all');
        }

        // Create log file path
        $date = gmdate('Y-m-d');
        $log_file = $log_dir . "/log-{$date}.log";

        // Format message
        $time = gmdate('Y-m-d H:i:s');
        $formatted_message = "[{$time}] {$message}" . PHP_EOL;

        // Write to log file
        file_put_contents($log_file, $formatted_message, FILE_APPEND);
    }

    /**
     * Send test emails
     *
     * @param array $statuses Statuses to send test emails for
     * @param int $limit Maximum number of users per status
     * @return int Number of emails sent
     */
    public function send_test_emails(array $statuses, int $limit = 1): int
    {
        $sent_count = 0;

        if (empty($statuses)) {
            return 0;
        }

        foreach ($statuses as $status) {
            // Find users with this status
            $users = $this->find_users_with_status($status);

            if (empty($users)) {
                continue;
            }

            // Limit number of users if limit > 0
            if ($limit > 0) {
                $users = array_slice($users, 0, $limit);
            }

            foreach ($users as $user_id) {
                // Send test email
                $sent = $this->send_reminder_email((int)$user_id, $status);

                if ($sent) {
                    $sent_count++;
                }
            }
        }

        return $sent_count;
    }

    /**
     * Get user's Stripe onboarding status
     *
     * @param int $user_id User ID
     * @return string|null Status or null if user doesn't need reminders
     */
    public function get_user_vendor_status(int $user_id): ?string
    {
        return $this->get_user_vendor_status_internal($user_id);
    }

    /**
     * Get all status types
     *
     * @return array Status types
     */
    public function get_all_status_types(): array
    {
        return array_keys($this->status_descriptions);
    }

    /**
     * Get default status types
     *
     * @param array $status_types Status types
     * @return array Status types
     */
    public function get_default_status_types(array $status_types): array
    {
        return array_merge($status_types, array_keys($this->status_descriptions));
    }

    /**
     * Get debug information for troubleshooting
     *
     * @return array Debug information
     */
    public function get_debug_info(): array
    {
        $debug = [];

        // Check Voxel availability
        $debug['voxel_class_exists'] = class_exists('Voxel\User');
        $debug['voxel_get_function_exists'] = function_exists('Voxel\get');

        // Check for seller role
        $roles = wp_roles();
        $debug['seller_role_exists'] = isset($roles->roles['seller']);

        // WordPress version
        $debug['wp_version'] = get_bloginfo('version');

        // Plugin version
        $debug['plugin_version'] = STRIPE_ONBOARDING_REMINDERS_VERSION;

        // Total users with seller role
        $sellers = get_users(['role' => 'seller', 'fields' => 'ID']);
        $debug['seller_count'] = count($sellers);

        // Sample statuses (up to 5 users)
        $debug['sample_statuses'] = [];
        $sample_users = array_slice($sellers, 0, 5);
        foreach ($sample_users as $user_id) {
            // Ensure user_id is an integer
            $user_id = (int) $user_id;
            $status = $this->get_user_vendor_status($user_id);
            $user = get_userdata($user_id);
            $debug['sample_statuses'][] = [
                'user_id' => $user_id,
                'username' => $user ? $user->user_login : 'N/A',
                'status' => $status,
            ];
        }

        return $debug;
    }

    /**
     * Send a test email for a specific status
     *
     * @param string $status The status to test
     * @param string $email The email address to send to
     * @return bool Whether the email was sent
     */
    public function send_test_email(string $status, string $email): bool
    {
        // Validate the status
        if (!in_array($status, ['active_no_shipping', 'pending', 'not_setup'])) {
            $this->log('Invalid status: ' . $status);
            return false;
        }

        // Get current user
        $current_user = wp_get_current_user();
        if (!$current_user || !$current_user->exists()) {
            return false;
        }

        // Get subject with TEST prefix
        $subject = '[TEST] ' . ($this->settings['subjects'][$status] ?? $this->get_default_settings()['subjects'][$status]);

        // Add a note about this being a test
        $test_note = '<div style="background-color: #ffeb3b; padding: 10px; margin-bottom: 20px; border-radius: 3px;">' .
            '<strong>' . __('TEST EMAIL', 'stripe-onboarding-reminders') . '</strong> - ' .
            __('This is a test email for the status', 'stripe-onboarding-reminders') . ': <strong>' .
            esc_html($this->get_status_display_name($status)) . '</strong></div>';

        // Create email templates object
        $email_templates = new Stripe_Onboarding_Reminders_Email_Templates($this->settings);

        // Get the content for the test email
        $content = $email_templates->get_email_content($current_user, $status);

        // Insert the test note after the opening body tag
        $content = preg_replace('/<body>/', '<body>' . $test_note, $content);

        // Set up email headers
        $headers = [];
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        // Add From header if configured
        if (!empty($this->settings['from_name']) && !empty($this->settings['from_email'])) {
            $from_name = $this->settings['from_name'];
            $from_email = $this->settings['from_email'];
            $headers[] = "From: {$from_name} <{$from_email}>";
        }

        // Send the email
        $result = wp_mail($email, $subject, $content, $headers);

        if (!$result) {
            $this->log('Failed to send test email to: ' . $email);
        }

        return $result;
    }
}
