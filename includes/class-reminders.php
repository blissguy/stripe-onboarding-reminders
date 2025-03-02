<?php

/**
 * Core reminders functionality
 *
 * @package Stripe_Onboarding_Reminders
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core class to handle Stripe onboarding email reminders
 */
class Stripe_Onboarding_Reminders_Core
{

    /**
     * Configuration settings
     */
    private array $config = [
        // Email sender details
        'from_name'  => '', // Will use site name
        'from_email' => '', // Will use WordPress default if empty

        // Email subjects for different statuses
        'subjects' => [
            'active_no_shipping' => 'Complete your shipping setup to start receiving payments',
            'pending'            => 'Action required: Complete your Stripe verification',
            'not_setup'          => 'Set up your payment account to enable sales',
        ],

        // Enable/disable notifications for each status
        'notifications' => [
            'active_no_shipping' => true,
            'pending'            => true,
            'not_setup'          => true,
        ],

        // Rate limiting: minimum days between emails to the same user
        'rate_limit_days' => 30,

        // Email settings
        'payout_settings_url' => '/dashboard/payout-settings/',
        'include_admin_copy'  => false,
        'admin_email'         => '',
    ];

    /**
     * Status descriptions for email content
     */
    private array $status_descriptions = [
        'active_no_shipping' => [
            'title' => 'Your account is active but missing shipping configuration',
            'description' => 'You\'ve successfully connected your Stripe account, but you haven\'t configured your shipping zones yet. Without shipping configuration, customers cannot calculate shipping costs for your products.',
            'action' => 'Please complete your shipping setup to fully enable transactions for your products.',
        ],
        'pending' => [
            'title' => 'Your Stripe verification is incomplete',
            'description' => 'You\'ve started the Stripe Connect onboarding process but haven\'t completed the verification process. Your account exists, but you cannot receive payments until verification is complete.',
            'action' => 'Please complete the verification process to start receiving payments for your products.',
        ],
        'not_setup' => [
            'title' => 'Your payment account is not set up',
            'description' => 'You haven\'t begun the Stripe Connect onboarding process yet. You need to complete Stripe Connect setup before you can receive any payouts for your products.',
            'action' => 'Please set up your Stripe account to start selling and receiving payments.',
        ],
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize cron hooks
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void
    {
        // Hook into the WordPress cron event for sending reminders
        add_action('stripe_onboarding_monthly_reminder', [$this, 'send_reminder_emails']);
    }

    /**
     * Schedule the monthly cron job
     */
    public function schedule_reminder_cron(): void
    {
        if (!wp_next_scheduled('stripe_onboarding_monthly_reminder')) {
            wp_schedule_event(time(), 'monthly', 'stripe_onboarding_monthly_reminder');

            // If WordPress doesn't have a monthly interval, let's add it
            add_filter('cron_schedules', function ($schedules) {
                if (!isset($schedules['monthly'])) {
                    $schedules['monthly'] = [
                        'interval' => 30 * DAY_IN_SECONDS,
                        'display'  => esc_html__('Once Monthly', 'stripe-onboarding-reminders')
                    ];
                }
                return $schedules;
            });
        }
    }

    /**
     * Unschedule the cron job
     */
    public function unschedule_reminder_cron(): void
    {
        $timestamp = wp_next_scheduled('stripe_onboarding_monthly_reminder');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'stripe_onboarding_monthly_reminder');
        }
    }

    /**
     * Get default settings
     */
    public function get_default_settings(): array
    {
        return [
            'from_name' => get_bloginfo('name'),
            'from_email' => '',
            'payout_settings_url' => $this->config['payout_settings_url'],
            'rate_limit_days' => $this->config['rate_limit_days'],
            'notifications' => $this->config['notifications'],
            'subjects' => $this->config['subjects'],
            'include_admin_copy' => $this->config['include_admin_copy'],
            'admin_email' => $this->config['admin_email'],
        ];
    }

    /**
     * Get settings from options or default
     */
    public function get_settings(): array
    {
        return get_option('stripe_onboarding_reminders_settings', $this->get_default_settings());
    }

    /**
     * Send reminder emails to users with incomplete onboarding
     */
    public function send_reminder_emails(): void
    {
        $settings = $this->get_settings();
        $enabled_statuses = array_keys(array_filter($settings['notifications']));

        if (empty($enabled_statuses)) {
            $this->log('No enabled statuses for email reminders');
            return;
        }

        $this->log('Starting to send reminder emails for statuses: ' . implode(', ', $enabled_statuses));

        $vendor_users = $this->get_users_by_status($enabled_statuses);

        if (empty($vendor_users)) {
            $this->log('No users found with incomplete onboarding');
            return;
        }

        $count = 0;
        foreach ($vendor_users as $user_id => $status) {
            if ($this->send_email_to_user($user_id, $status)) {
                $count++;
            }
        }

        $this->log("Sent $count reminder emails");
    }

    /**
     * Send test emails to users
     * 
     * @param array $statuses Status types to test
     * @param int $limit Maximum number of emails per status
     * @return int Number of emails sent
     */
    public function send_test_emails(array $statuses, int $limit = 1): int
    {
        if (empty($statuses)) {
            return 0;
        }

        $this->log('Sending test emails for statuses: ' . implode(', ', $statuses));

        $vendor_users = $this->get_users_by_status($statuses, $limit);

        if (empty($vendor_users)) {
            $this->log('No users found with the specified statuses for testing');
            return 0;
        }

        $count = 0;
        foreach ($vendor_users as $user_id => $status) {
            if ($this->send_email_to_user($user_id, $status, true)) {
                $count++;
            }
        }

        $this->log("Sent $count test emails");
        return $count;
    }

    /**
     * Get users with specific Stripe onboarding statuses
     * 
     * @param array $statuses Array of status types to find
     * @param int $limit Maximum number of users per status (0 for unlimited)
     * @return array Array of user IDs mapped to their status
     */
    private function get_users_by_status(array $statuses, int $limit = 0): array
    {
        $result = [];
        $settings = $this->get_settings();
        $rate_limit_days = intval($settings['rate_limit_days']);

        // Get cutoff date for rate limiting
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-$rate_limit_days days"));

        foreach ($statuses as $status) {
            // Get users with specific status
            $users = $this->find_users_with_status($status, $limit);

            foreach ($users as $user_id) {
                // Ensure user_id is an integer
                $user_id = (int) $user_id;

                // Check if we've already sent an email to this user recently
                $last_email_date = get_user_meta($user_id, 'stripe_onboarding_email_sent', true);

                if (empty($last_email_date) || $last_email_date < $cutoff_date) {
                    $result[$user_id] = $status;
                }

                // Respect the limit (if set)
                if ($limit > 0 && count(array_filter($result, function ($s) use ($status) {
                    return $s === $status;
                })) >= $limit) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Find users with a specific Stripe onboarding status
     * 
     * This function checks for users with a specific Stripe status by leveraging
     * the existing Voxel theme functions or by direct database query
     * 
     * @param string $status The status to search for
     * @param int $limit Maximum number of users to return (0 for unlimited)
     * @return array Array of user IDs
     */
    private function find_users_with_status(string $status, int $limit = 0): array
    {
        global $wpdb;

        // Initialize the result array
        $user_ids = [];

        // First, try to get users with vendor role
        $vendor_users = get_users([
            'role__in' => ['vendor', 'seller'], // Adjust based on your user roles
            'number' => $limit > 0 ? $limit : -1,
            'fields' => 'ID',
        ]);

        // For each user, check their Stripe status
        foreach ($vendor_users as $user_id) {
            // Cast user_id to integer to avoid type errors
            $user_id = (int) $user_id;
            $user_status = $this->get_user_vendor_status($user_id);

            if ($user_status === $status) {
                $user_ids[] = $user_id;

                // Respect the limit if set
                if ($limit > 0 && count($user_ids) >= $limit) {
                    break;
                }
            }
        }

        return $user_ids;
    }

    /**
     * Get a user's Stripe vendor status
     * 
     * This is a compatibility wrapper that tries to use Voxel's functions
     * if available, or falls back to our own implementation
     * 
     * @param int $user_id The user ID to check
     * @return string|null The vendor status or null if not found
     */
    private function get_user_vendor_status(int $user_id): ?string
    {
        // First try to use Voxel's function if it exists
        if (class_exists('\\Voxel\\User') && method_exists('\\Voxel\\User', 'get')) {
            try {
                // Using fully qualified namespace to avoid linter errors
                $voxel_user_class = '\\Voxel\\User';
                $user = $voxel_user_class::get($user_id);

                if ($user) {
                    // Check if multivendor is enabled - using function_exists with namespaced function
                    $voxel_get_function = '\\Voxel\\get';
                    if (function_exists($voxel_get_function) && !$voxel_get_function('product_settings.multivendor.enabled')) {
                        return 'inactive';
                    }

                    // Check if user is admin
                    if (
                        $user->has_cap('administrator') &&
                        apply_filters('voxel/stripe_connect/enable_onboarding_for_admins', false) !== true
                    ) {
                        return 'inactive';
                    }

                    // Get Stripe details
                    $details = $user->get_stripe_vendor_details();

                    // Determine status
                    if (!$details->exists) {
                        return 'not_setup';
                    }

                    if (!$details->charges_enabled) {
                        return 'pending';
                    }

                    // Check shipping zones
                    if (!$this->has_shipping_zones($user_id)) {
                        return 'active_no_shipping';
                    }

                    return 'active';
                }
            } catch (\Exception $e) {
                $this->log("Error getting user status with Voxel: " . $e->getMessage());
            }
        }

        // Fallback to our own implementation using user meta
        return $this->get_user_vendor_status_fallback($user_id);
    }

    /**
     * Fallback method to get user vendor status
     * 
     * This is used when Voxel functions are not available
     * 
     * @param int $user_id The user ID to check
     * @return string|null The vendor status or null if not found
     */
    private function get_user_vendor_status_fallback(int $user_id): ?string
    {
        // Check for cached status meta
        $status = get_user_meta($user_id, 'stripe_vendor_status', true);
        if (!empty($status)) {
            return $status;
        }

        // Check for common stripe connect meta fields
        $stripe_account_id = get_user_meta($user_id, 'stripe_connect_account_id', true);
        if (empty($stripe_account_id)) {
            return 'not_setup';
        }

        $charges_enabled = get_user_meta($user_id, 'stripe_connect_charges_enabled', true);
        if (empty($charges_enabled) || $charges_enabled !== 'yes') {
            return 'pending';
        }

        // Check shipping zones
        if (!$this->has_shipping_zones($user_id)) {
            return 'active_no_shipping';
        }

        return 'active';
    }

    /**
     * Check if a user has shipping zones configured
     * 
     * @param int $user_id The user ID to check
     * @return bool Whether shipping zones are configured
     */
    private function has_shipping_zones(int $user_id): bool
    {
        // First check for Voxel meta
        $shipping_zones = get_user_meta($user_id, 'voxel:vendor_shipping_zones', true);
        if (!empty($shipping_zones)) {
            return true;
        }

        // Check for WooCommerce shipping zones if WooCommerce is active
        if (function_exists('WC')) {
            // This would need to be customized based on how your WooCommerce integration works
            $shipping_methods = get_user_meta($user_id, 'vendor_shipping_methods', true);
            if (!empty($shipping_methods)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send an email to a specific user
     * 
     * @param int $user_id The user ID to email
     * @param string $status The user's Stripe status
     * @param bool $is_test Whether this is a test email
     * @return bool Whether the email was sent successfully
     */
    private function send_email_to_user(int $user_id, string $status, bool $is_test = false): bool
    {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $settings = $this->get_settings();

        // Prepare email content
        $subject = $settings['subjects'][$status] ?? $this->config['subjects'][$status];
        if ($is_test) {
            $subject = '[TEST] ' . $subject;
        }

        // Get the email template
        $message = $this->get_email_template($user, $status, $settings);

        // Set up email headers
        $headers = [];

        if (!empty($settings['from_email'])) {
            $from_name = !empty($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name');
            $headers[] = 'From: ' . $from_name . ' <' . $settings['from_email'] . '>';
        }

        // Add HTML content type
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        // Send the email
        $success = wp_mail($user->user_email, $subject, $message, $headers);

        // Send copy to admin if enabled
        if ($success && $settings['include_admin_copy']) {
            $admin_email = !empty($settings['admin_email']) ? $settings['admin_email'] : get_option('admin_email');
            $admin_subject = '[Copy] ' . $subject . ' - Sent to ' . $user->user_email;
            wp_mail($admin_email, $admin_subject, $message, $headers);
        }

        if ($success && !$is_test) {
            // Update the last sent timestamp
            update_user_meta($user_id, 'stripe_onboarding_email_sent', current_time('mysql'));
        }

        return $success;
    }

    /**
     * Generate email template for a specific user
     * 
     * @param WP_User $user The user object
     * @param string $status The user's Stripe status
     * @param array $settings Plugin settings
     * @return string The email HTML content
     */
    private function get_email_template($user, string $status, array $settings): string
    {
        // Get site info
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');

        // Get status info
        $status_info = $this->status_descriptions[$status];

        // Build the payout settings URL
        $payout_url = trailingslashit($site_url) . ltrim($settings['payout_settings_url'], '/');

        ob_start();
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>

        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <h1 style="color: #2c3e50;"><?php echo esc_html($site_name); ?></h1>
            </div>

            <div style="background-color: #f8f9fa; border-radius: 5px; padding: 20px; margin-bottom: 20px;">
                <h2 style="color: #3498db; margin-top: 0;"><?php echo esc_html($status_info['title']); ?></h2>

                <p>Hello <?php echo esc_html($user->display_name); ?>,</p>

                <p><?php echo esc_html($status_info['description']); ?></p>

                <p><strong><?php echo esc_html($status_info['action']); ?></strong></p>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="<?php echo esc_url($payout_url); ?>" style="background-color: #3498db; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Complete Your Setup</a>
                </div>

                <p>If you have any questions or need assistance with setting up your payment account, please don't hesitate to contact our support team.</p>
            </div>

            <div style="text-align: center; font-size: 12px; color: #7f8c8d; margin-top: 30px;">
                <p>This is an automated message from <?php echo esc_html($site_name); ?>.</p>
                <p>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html($site_name); ?>. All rights reserved.</p>
            </div>
        </body>

        </html>
<?php
        return ob_get_clean();
    }

    /**
     * Log a message to WordPress debug log
     * 
     * @param string $message The message to log
     */
    private function log(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG === true && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
            // Use sanitized error_log with WordPress debug constants
            error_log(sanitize_text_field('[Stripe Onboarding Reminders] ' . $message));
        }
    }

    /**
     * Get user-friendly status name
     * 
     * @param string $status The internal status key
     * @return string User-friendly status name
     */
    public function get_status_display_name(string $status): string
    {
        $names = [
            'active_no_shipping' => 'Active (No Shipping)',
            'pending' => 'Pending Verification',
            'not_setup' => 'Not Set Up',
        ];

        return $names[$status] ?? ucfirst($status);
    }
}
