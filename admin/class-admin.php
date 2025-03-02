<?php

/**
 * Admin functionality for Stripe Onboarding Reminders
 *
 * @package Stripe_Onboarding_Reminders
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for Stripe Onboarding Reminders
 */
class Stripe_Onboarding_Reminders_Admin
{

    /**
     * Core class instance
     *
     * @var Stripe_Onboarding_Reminders_Core
     */
    private $core;

    /**
     * Constructor
     *
     * @param Stripe_Onboarding_Reminders_Core $core Core class instance
     */
    public function __construct(Stripe_Onboarding_Reminders_Core $core = null)
    {
        // Initialize core class - either use the passed instance or create a new one
        $this->core = $core ?? new Stripe_Onboarding_Reminders_Core();

        // Load users table class
        require_once STRIPE_ONBOARDING_REMINDERS_PLUGIN_DIR . 'admin/class-users-table.php';

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void
    {
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Register admin settings
        add_action('admin_init', [$this, 'register_settings']);

        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // AJAX handler for sending test emails
        add_action('wp_ajax_sor_send_test_emails', [$this, 'ajax_send_test_emails']);

        // AJAX handler for sending manual reminders
        add_action('wp_ajax_sor_send_manual_reminder', [$this, 'ajax_send_manual_reminder']);

        // AJAX handler for sending debug emails
        add_action('wp_ajax_sor_send_debug_email', [$this, 'ajax_send_debug_email']);

        // Admin notices
        add_action('admin_notices', [$this, 'admin_notices']);

        // Add link to GitHub repository in plugin actions
        add_filter('plugin_action_links_' . STRIPE_ONBOARDING_REMINDERS_BASENAME, [$this, 'add_plugin_action_links']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu(): void
    {
        add_menu_page(
            __('Stripe Onboarding Reminders', 'stripe-onboarding-reminders'),
            __('Stripe Reminders', 'stripe-onboarding-reminders'),
            'manage_options',
            'stripe_onboarding_reminders',
            [$this, 'render_settings_page'],
            'dashicons-money-alt',
            58
        );
    }

    /**
     * Register settings
     */
    public function register_settings(): void
    {
        register_setting(
            'stripe_onboarding_reminders',
            'stripe_onboarding_reminders_settings',
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->core->get_default_settings(),
            ]
        );

        // General settings section
        add_settings_section(
            'stripe_onboarding_reminders_general',
            __('General Settings', 'stripe-onboarding-reminders'),
            [$this, 'render_general_section'],
            'stripe_onboarding_reminders'
        );

        // Email Settings section
        add_settings_section(
            'stripe_onboarding_reminders_email',
            __('Email Settings', 'stripe-onboarding-reminders'),
            [$this, 'render_email_section'],
            'stripe_onboarding_reminders'
        );

        // Notification Settings section
        add_settings_section(
            'stripe_onboarding_reminders_notifications',
            __('Notification Settings', 'stripe-onboarding-reminders'),
            [$this, 'render_notifications_section'],
            'stripe_onboarding_reminders'
        );

        // From name field
        add_settings_field(
            'from_name',
            __('From Name', 'stripe-onboarding-reminders'),
            [$this, 'render_text_field'],
            'stripe_onboarding_reminders',
            'stripe_onboarding_reminders_email',
            [
                'label_for' => 'from_name',
                'description' => __('Name that will appear in the from field of the email', 'stripe-onboarding-reminders'),
            ]
        );

        // From email field
        add_settings_field(
            'from_email',
            __('From Email', 'stripe-onboarding-reminders'),
            [$this, 'render_text_field'],
            'stripe_onboarding_reminders',
            'stripe_onboarding_reminders_email',
            [
                'label_for' => 'from_email',
                'description' => __('Email address that will be used as the sender (leave blank to use WordPress default)', 'stripe-onboarding-reminders'),
            ]
        );

        // Payout settings URL
        add_settings_field(
            'payout_settings_url',
            __('Payout Settings URL', 'stripe-onboarding-reminders'),
            [$this, 'render_text_field'],
            'stripe_onboarding_reminders',
            'stripe_onboarding_reminders_general',
            [
                'label_for' => 'payout_settings_url',
                'description' => __('URL to the payout settings page (e.g., /dashboard/payout-settings/)', 'stripe-onboarding-reminders'),
            ]
        );

        // Rate limit field
        add_settings_field(
            'rate_limit_days',
            __('Email Frequency (days)', 'stripe-onboarding-reminders'),
            [$this, 'render_number_field'],
            'stripe_onboarding_reminders',
            'stripe_onboarding_reminders_general',
            [
                'label_for' => 'rate_limit_days',
                'description' => __('Minimum number of days between emails to the same user', 'stripe-onboarding-reminders'),
                'min' => 1,
                'max' => 90,
            ]
        );

        // Status toggles - one for each status
        foreach (['active_no_shipping', 'pending', 'not_setup'] as $status) {
            // Get status display text that matches the badge
            $status_text = match ($status) {
                'active_no_shipping' => 'Active - No Shipping',
                'pending' => 'Pending',
                'not_setup' => 'Not Setup',
                default => $this->core->get_status_display_name($status)
            };

            add_settings_field(
                "enable_{$status}",
                sprintf(__('%s Reminders', 'stripe-onboarding-reminders'), $status_text),
                [$this, 'render_toggle_field'],
                'stripe_onboarding_reminders',
                'stripe_onboarding_reminders_notifications',
                [
                    'label_for' => "notifications_{$status}",
                    'description' => sprintf(__('Send reminder emails to users with %s status', 'stripe-onboarding-reminders'), $status_text),
                ]
            );
        }

        // Admin copy settings
        add_settings_field(
            'include_admin_copy',
            __('Send Admin Copies', 'stripe-onboarding-reminders'),
            [$this, 'render_toggle_field'],
            'stripe_onboarding_reminders',
            'stripe_onboarding_reminders_email',
            [
                'label_for' => 'include_admin_copy',
                'description' => __('Send a copy of all reminder emails to the admin', 'stripe-onboarding-reminders'),
            ]
        );

        add_settings_field(
            'admin_email',
            __('Admin Email', 'stripe-onboarding-reminders'),
            [$this, 'render_text_field'],
            'stripe_onboarding_reminders',
            'stripe_onboarding_reminders_email',
            [
                'label_for' => 'admin_email',
                'description' => __('Email address for admin copies (leave blank to use site admin email)', 'stripe-onboarding-reminders'),
                'class' => 'admin-email-field',
            ]
        );
    }

    /**
     * Render General section
     */
    public function render_general_section(): void
    {
        echo '<p>';
        esc_html_e('Configure general settings for the Stripe onboarding reminder emails.', 'stripe-onboarding-reminders');
        echo '</p>';
    }

    /**
     * Render Email section
     */
    public function render_email_section(): void
    {
        echo '<p>';
        esc_html_e('Configure email sender details and admin notification options.', 'stripe-onboarding-reminders');
        echo '</p>';
        echo '<p class="description">';
        esc_html_e('Email templates and content can be customized in the Email Templates tab.', 'stripe-onboarding-reminders');
        echo '</p>';
    }

    /**
     * Render Notifications section
     */
    public function render_notifications_section(): void
    {
        echo '<p>';
        esc_html_e('Enable or disable reminder emails for each type of Stripe account status.', 'stripe-onboarding-reminders');
        echo '</p>';
        echo '<p class="description">';
        esc_html_e('Email templates and subject lines can be customized in the Email Templates tab.', 'stripe-onboarding-reminders');
        echo '</p>';
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input): array
    {
        $sanitized = [];

        // Text fields
        $sanitized['from_name'] = sanitize_text_field($input['from_name'] ?? '');
        $sanitized['from_email'] = sanitize_email($input['from_email'] ?? '');
        $sanitized['payout_settings_url'] = esc_url_raw($input['payout_settings_url'] ?? '');
        $sanitized['admin_email'] = sanitize_email($input['admin_email'] ?? '');

        // Number fields
        $sanitized['rate_limit_days'] = absint($input['rate_limit_days'] ?? 30);
        if ($sanitized['rate_limit_days'] < 1) {
            $sanitized['rate_limit_days'] = 1;
        } elseif ($sanitized['rate_limit_days'] > 90) {
            $sanitized['rate_limit_days'] = 90;
        }

        // Checkboxes
        $sanitized['include_admin_copy'] = isset($input['include_admin_copy']) && $input['include_admin_copy'] === 'on';

        // Status notifications
        $sanitized['notifications'] = [];
        foreach (['active_no_shipping', 'pending', 'not_setup'] as $status) {
            $key = "notifications_{$status}";
            $sanitized['notifications'][$status] = isset($input[$key]) && $input[$key] === 'on';
        }

        // Email subjects
        $sanitized['subjects'] = [];
        foreach (['active_no_shipping', 'pending', 'not_setup'] as $status) {
            // Look both in nested structure (from templates tab) and old structure (from settings tab)
            if (isset($input['subjects'][$status])) {
                $sanitized['subjects'][$status] = sanitize_text_field($input['subjects'][$status]);
            } else {
                $key = "subjects_{$status}";
                $default = $this->core->get_default_settings()['subjects'][$status];
                $sanitized['subjects'][$status] = sanitize_text_field($input[$key] ?? $default);
            }
        }

        // Email templates
        $sanitized['templates'] = [];
        if (isset($input['templates']) && is_array($input['templates'])) {
            foreach (['active_no_shipping', 'pending', 'not_setup'] as $status) {
                $default = $this->core->get_default_settings()['templates'][$status];
                $content = isset($input['templates'][$status]) ? $input['templates'][$status] : $default;
                $sanitized['templates'][$status] = wp_kses_post($content);
            }
        } else {
            // Use defaults if not set
            $sanitized['templates'] = $this->core->get_default_settings()['templates'];
        }

        // Button text
        $sanitized['button_text'] = [];
        if (isset($input['button_text']) && is_array($input['button_text'])) {
            foreach (['active_no_shipping', 'pending', 'not_setup'] as $status) {
                $default = $this->core->get_default_settings()['button_text'][$status];
                $sanitized['button_text'][$status] = sanitize_text_field($input['button_text'][$status] ?? $default);
            }
        } else {
            // Use defaults if not set
            $sanitized['button_text'] = $this->core->get_default_settings()['button_text'];
        }

        // Common footer
        if (isset($input['common_footer'])) {
            $sanitized['common_footer'] = wp_kses_post($input['common_footer']);
        } else {
            $sanitized['common_footer'] = $this->core->get_default_settings()['common_footer'];
        }

        return $sanitized;
    }

    /**
     * Render text field
     */
    public function render_text_field($args): void
    {
        $settings = $this->core->get_settings();

        // Handle nested array structure
        $value = $this->get_setting_value($settings, $args['label_for']);

        $class = isset($args['class']) ? $args['class'] : '';
        $data_attrs = '';

        if (isset($args['data-status'])) {
            $data_attrs = 'data-status="' . esc_attr($args['data-status']) . '"';
        }

        printf(
            '<input type="text" id="%1$s" name="stripe_onboarding_reminders_settings[%1$s]" value="%2$s" class="regular-text %3$s" %4$s />',
            esc_attr($args['label_for']),
            esc_attr($value),
            esc_attr($class),
            $data_attrs
        );

        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Render number field
     */
    public function render_number_field($args): void
    {
        $settings = $this->core->get_settings();
        $value = $this->get_setting_value($settings, $args['label_for']);

        printf(
            '<input type="number" id="%1$s" name="stripe_onboarding_reminders_settings[%1$s]" value="%2$s" min="%3$s" max="%4$s" class="small-text" />',
            esc_attr($args['label_for']),
            esc_attr($value),
            esc_attr($args['min']),
            esc_attr($args['max'])
        );

        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Render toggle field - modern toggle switch instead of checkbox
     */
    public function render_toggle_field($args): void
    {
        $settings = $this->core->get_settings();
        $checked = $this->get_setting_value($settings, $args['label_for']) ? 'checked' : '';

        echo '<div class="sor-toggle-switch-container">';
        printf(
            '<label class="sor-toggle-switch">
                <input type="checkbox" id="%1$s" name="stripe_onboarding_reminders_settings[%1$s]" %2$s />
                <span class="sor-toggle-slider"></span>
            </label>',
            esc_attr($args['label_for']),
            esc_attr($checked)
        );
        echo '</div>';

        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Helper to get setting value from possibly nested array
     */
    private function get_setting_value($settings, $key)
    {
        // Check if this is a nested setting (e.g., notifications_active_no_shipping)
        if (strpos($key, '_') !== false) {
            $parts = explode('_', $key, 2);

            // Handle the subjects_status and notifications_status format
            if (in_array($parts[0], ['subjects', 'notifications']) && isset($settings[$parts[0]][$parts[1]])) {
                return $settings[$parts[0]][$parts[1]];
            }
        }

        // Regular top-level setting
        return $settings[$key] ?? '';
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page
     */
    public function enqueue_admin_assets(string $hook): void
    {
        // Only load on our settings page
        if ($hook !== 'toplevel_page_stripe_onboarding_reminders') {
            return;
        }

        // Admin CSS
        wp_enqueue_style(
            'stripe-onboarding-reminders-admin',
            STRIPE_ONBOARDING_REMINDERS_PLUGIN_URL . 'admin/css/admin.css',
            [],
            STRIPE_ONBOARDING_REMINDERS_VERSION
        );

        // Admin JS
        wp_enqueue_script(
            'stripe-onboarding-reminders-admin',
            STRIPE_ONBOARDING_REMINDERS_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            STRIPE_ONBOARDING_REMINDERS_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'stripe-onboarding-reminders-admin',
            'sorSettings',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sor_admin_nonce'),
                'sending' => __('Sending...', 'stripe-onboarding-reminders'),
                'sent' => __('Sent!', 'stripe-onboarding-reminders'),
                'error' => __('Error', 'stripe-onboarding-reminders'),
                'sendReminder' => __('Send Reminder', 'stripe-onboarding-reminders'),
                'send' => __('Send Reminder Emails', 'stripe-onboarding-reminders'),
                'sendDebug' => __('Send Debug Email', 'stripe-onboarding-reminders'),
            ]
        );
    }

    /**
     * Admin notices
     */
    public function admin_notices(): void
    {
        $screen = get_current_screen();

        // Settings updated notice
        if (isset($screen->id) && $screen->id === 'toplevel_page_stripe_onboarding_reminders') {
            if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved.', 'stripe-onboarding-reminders'); ?></p>
                </div>
            <?php
            }

            // Display bulk action results if any
            $notice = get_transient('sor_bulk_action_notice');
            if ($notice) {
                delete_transient('sor_bulk_action_notice');
            ?>
                <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
        <?php
            }
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

        ?>
        <div class="wrap sor-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=stripe_onboarding_reminders&tab=settings')); ?>" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Settings', 'stripe-onboarding-reminders'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=stripe_onboarding_reminders&tab=templates')); ?>" class="nav-tab <?php echo $current_tab === 'templates' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Email Templates', 'stripe-onboarding-reminders'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=stripe_onboarding_reminders&tab=users')); ?>" class="nav-tab <?php echo $current_tab === 'users' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Users', 'stripe-onboarding-reminders'); ?></a>
            </nav>

            <div class="sor-admin-container">
                <?php if ($current_tab === 'settings') : ?>
                    <div class="sor-admin-main">
                        <div class="sor-settings-card">
                            <form id="sor-settings-form" action="options.php" method="post">
                                <?php
                                settings_fields('stripe_onboarding_reminders');
                                do_settings_sections('stripe_onboarding_reminders');
                                submit_button(__('Save Settings', 'stripe-onboarding-reminders'));
                                ?>
                            </form>
                        </div>
                    </div>

                    <div class="sor-admin-sidebar">
                        <div class="sor-settings-card">
                            <h2><?php esc_html_e('Send Emails Manually', 'stripe-onboarding-reminders'); ?></h2>
                            <p><?php esc_html_e('Send reminder emails to all users with incomplete Stripe onboarding matching the selected statuses.', 'stripe-onboarding-reminders'); ?></p>

                            <div id="sor-test-form">
                                <div class="sor-form-group">
                                    <label><?php esc_html_e('User Status', 'stripe-onboarding-reminders'); ?></label>
                                    <div class="sor-checkbox-group">
                                        <?php foreach (['active_no_shipping', 'pending', 'not_setup'] as $status) : ?>
                                            <label class="sor-checkbox">
                                                <input type="checkbox" name="test_statuses[]" value="<?php echo esc_attr($status); ?>" checked>
                                                <span class="sor-checkbox-label"><?php echo esc_html($this->core->get_status_display_name($status)); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="sor-form-group">
                                    <label class="sor-checkbox">
                                        <input type="checkbox" name="bypass_rate_limit" id="bypass_rate_limit">
                                        <span class="sor-checkbox-label"><?php esc_html_e('Bypass rate limit', 'stripe-onboarding-reminders'); ?></span>
                                    </label>
                                    <p class="description"><?php esc_html_e('Send emails even if users received a reminder recently', 'stripe-onboarding-reminders'); ?></p>
                                </div>

                                <div class="sor-form-group">
                                    <button type="button" id="sor-send-test" class="button button-primary"><?php esc_html_e('Send Reminder Emails', 'stripe-onboarding-reminders'); ?></button>
                                </div>

                                <?php
                                // Display last manual trigger timestamp if available
                                $last_manual_trigger = get_option('sor_last_manual_trigger');
                                if ($last_manual_trigger) :
                                ?>
                                    <div class="sor-last-trigger">
                                        <p><strong><?php esc_html_e('Last manual trigger:', 'stripe-onboarding-reminders'); ?></strong>
                                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_manual_trigger)); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div id="sor-test-response"></div>
                            </div>
                        </div>

                        <div class="sor-settings-card">
                            <h3><?php esc_html_e('Email Debugging', 'stripe-onboarding-reminders'); ?></h3>
                            <p><?php esc_html_e('Test how emails appear to users with different statuses.', 'stripe-onboarding-reminders'); ?></p>

                            <div id="sor-debug-form">
                                <div class="sor-form-group">
                                    <label for="debug_status"><?php esc_html_e('Status to simulate', 'stripe-onboarding-reminders'); ?></label>
                                    <select id="debug_status" name="debug_status" class="regular-text">
                                        <?php
                                        // Status display names - matching the users table
                                        $status_texts = [
                                            'not_setup' => 'Not Setup',
                                            'pending' => 'Pending',
                                            'active_no_shipping' => 'Active - No Shipping',
                                        ];

                                        foreach (['active_no_shipping', 'pending', 'not_setup'] as $status) : ?>
                                            <option value="<?php echo esc_attr($status); ?>"><?php echo esc_html($status_texts[$status]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="sor-form-group">
                                    <label for="debug_email"><?php esc_html_e('Send to email', 'stripe-onboarding-reminders'); ?></label>
                                    <input type="email" id="debug_email" name="debug_email" class="regular-text" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
                                    <p class="description"><?php esc_html_e('Email address to receive the test email', 'stripe-onboarding-reminders'); ?></p>
                                </div>

                                <div class="sor-form-group">
                                    <button type="button" id="sor-send-debug" class="button button-secondary"><?php esc_html_e('Send Debug Email', 'stripe-onboarding-reminders'); ?></button>
                                </div>

                                <div id="sor-debug-response"></div>
                            </div>
                        </div>

                        <div class="sor-settings-card">
                            <h3><?php esc_html_e('About This Plugin', 'stripe-onboarding-reminders'); ?></h3>
                            <p><?php esc_html_e('This plugin automatically sends reminder emails to users who have not completed their Stripe onboarding process.', 'stripe-onboarding-reminders'); ?></p>
                            <p><?php esc_html_e('Emails are sent based on monthly schedule with rate limiting to prevent excessive notifications.', 'stripe-onboarding-reminders'); ?></p>
                            <p>
                                <a href="https://github.com/blissguy/stripe-onboarding-reminders" target="_blank"><?php esc_html_e('View Documentation', 'stripe-onboarding-reminders'); ?></a> |
                                <a href="https://github.com/blissguy/stripe-onboarding-reminders/issues" target="_blank"><?php esc_html_e('Report Issues', 'stripe-onboarding-reminders'); ?></a>
                            </p>
                            <p class="description"><?php esc_html_e('Version', 'stripe-onboarding-reminders');
                                                    echo ' ' . esc_html(STRIPE_ONBOARDING_REMINDERS_VERSION); ?></p>
                        </div>
                    </div>
                <?php elseif ($current_tab === 'templates') : ?>
                    <div class="sor-admin-main full-width">
                        <div class="sor-settings-card">
                            <h2><?php esc_html_e('Email Templates', 'stripe-onboarding-reminders'); ?></h2>
                            <p><?php esc_html_e('Customize the email templates sent to users with incomplete Stripe onboarding.', 'stripe-onboarding-reminders'); ?></p>

                            <form id="sor-templates-form" action="options.php" method="post">
                                <?php settings_fields('stripe_onboarding_reminders'); ?>

                                <h3><?php esc_html_e('Available Placeholders', 'stripe-onboarding-reminders'); ?></h3>
                                <p class="description"><?php esc_html_e('You can use these placeholders in your email templates:', 'stripe-onboarding-reminders'); ?></p>
                                <ul class="sor-placeholders-list">
                                    <li><code>%user_name%</code> - <?php esc_html_e('The recipient\'s display name', 'stripe-onboarding-reminders'); ?></li>
                                    <li><code>%site_name%</code> - <?php esc_html_e('Your website name', 'stripe-onboarding-reminders'); ?></li>
                                    <li><code>%payout_url%</code> - <?php esc_html_e('Link to the payout settings page', 'stripe-onboarding-reminders'); ?></li>
                                    <li><code>%admin_email%</code> - <?php esc_html_e('The admin email address', 'stripe-onboarding-reminders'); ?></li>
                                </ul>

                                <hr>

                                <?php
                                // Status display names - matching the users table
                                $status_texts = [
                                    'not_setup' => __('Not Setup', 'stripe-onboarding-reminders'),
                                    'pending' => __('Pending', 'stripe-onboarding-reminders'),
                                    'active_no_shipping' => __('Active - No Shipping', 'stripe-onboarding-reminders'),
                                ];

                                // Get settings
                                $settings = $this->core->get_settings();
                                $default_settings = $this->core->get_default_settings();

                                // Loop through each status to create template editors
                                foreach (['active_no_shipping', 'pending', 'not_setup'] as $status) :
                                    $status_label = $status_texts[$status] ?? $status;
                                    $template_content = $settings['templates'][$status] ?? $default_settings['templates'][$status];
                                    $subject_value = $settings['subjects'][$status] ?? $default_settings['subjects'][$status];
                                    $button_text = $settings['button_text'][$status] ?? $default_settings['button_text'][$status];
                                ?>
                                    <div class="sor-template-section" id="template-<?php echo esc_attr($status); ?>">
                                        <h3><?php echo sprintf(__('%s Status Template', 'stripe-onboarding-reminders'), esc_html($status_label)); ?></h3>

                                        <div class="sor-form-group">
                                            <label for="sor_subject_<?php echo esc_attr($status); ?>"><?php esc_html_e('Email Subject', 'stripe-onboarding-reminders'); ?></label>
                                            <input type="text" id="sor_subject_<?php echo esc_attr($status); ?>"
                                                name="stripe_onboarding_reminders_settings[subjects][<?php echo esc_attr($status); ?>]"
                                                value="<?php echo esc_attr($subject_value); ?>" class="regular-text">
                                        </div>

                                        <div class="sor-form-group">
                                            <label for="sor_button_<?php echo esc_attr($status); ?>"><?php esc_html_e('Button Text', 'stripe-onboarding-reminders'); ?></label>
                                            <input type="text" id="sor_button_<?php echo esc_attr($status); ?>"
                                                name="stripe_onboarding_reminders_settings[button_text][<?php echo esc_attr($status); ?>]"
                                                value="<?php echo esc_attr($button_text); ?>" class="regular-text">
                                        </div>

                                        <div class="sor-form-group">
                                            <label for="sor_template_<?php echo esc_attr($status); ?>"><?php esc_html_e('Email Body', 'stripe-onboarding-reminders'); ?></label>
                                            <?php
                                            $editor_id = 'sor_template_' . $status;
                                            $editor_name = 'stripe_onboarding_reminders_settings[templates][' . $status . ']';
                                            $editor_settings = [
                                                'textarea_name' => $editor_name,
                                                'textarea_rows' => 10,
                                                'teeny' => true,
                                                'media_buttons' => false,
                                                'tinymce' => [
                                                    'plugins' => 'lists,paste,tabfocus,wplink,wordpress',
                                                    'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink',
                                                    'toolbar2' => '',
                                                ],
                                                'quicktags' => [
                                                    'buttons' => 'strong,em,ul,ol,li,link'
                                                ]
                                            ];
                                            wp_editor($template_content, $editor_id, $editor_settings);
                                            ?>
                                            <p class="description"><?php esc_html_e('This content appears in the main body of the email.', 'stripe-onboarding-reminders'); ?></p>
                                        </div>
                                    </div>

                                    <hr>
                                <?php endforeach; ?>

                                <div class="sor-template-section" id="template-footer">
                                    <h3><?php esc_html_e('Common Footer', 'stripe-onboarding-reminders'); ?></h3>

                                    <div class="sor-form-group">
                                        <label for="sor_common_footer"><?php esc_html_e('Footer Content', 'stripe-onboarding-reminders'); ?></label>
                                        <?php
                                        $footer_content = $settings['common_footer'] ?? $default_settings['common_footer'];
                                        $editor_settings = [
                                            'textarea_name' => 'stripe_onboarding_reminders_settings[common_footer]',
                                            'textarea_rows' => 5,
                                            'teeny' => true,
                                            'media_buttons' => false,
                                            'tinymce' => [
                                                'plugins' => 'lists,paste,tabfocus,wplink,wordpress',
                                                'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink',
                                                'toolbar2' => '',
                                            ],
                                            'quicktags' => [
                                                'buttons' => 'strong,em,ul,ol,li,link'
                                            ]
                                        ];
                                        wp_editor($footer_content, 'sor_common_footer', $editor_settings);
                                        ?>
                                        <p class="description"><?php esc_html_e('This content appears at the bottom of all emails.', 'stripe-onboarding-reminders'); ?></p>
                                    </div>
                                </div>

                                <?php submit_button(__('Save Templates', 'stripe-onboarding-reminders')); ?>
                            </form>

                            <div id="sor-template-preview-container"></div>
                        </div>
                    </div>
                <?php elseif ($current_tab === 'users') : ?>
                    <div class="sor-users-table-wrapper">
                        <div class="sor-settings-card">
                            <h2><?php esc_html_e('Users with Incomplete Stripe Onboarding', 'stripe-onboarding-reminders'); ?></h2>
                            <p><?php esc_html_e('This table shows all users who have not completed their Stripe onboarding process.', 'stripe-onboarding-reminders'); ?></p>

                            <div id="sor-users-table-container">
                                <?php
                                // Create and display the table
                                $users_table = new Stripe_Onboarding_Reminders_Users_Table($this->core);
                                $users_table->prepare_items();
                                $users_table->display();
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
<?php
    }

    /**
     * Handle AJAX request to send test emails
     */
    public function ajax_send_test_emails(): void
    {
        // Check nonce and capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sor_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed.', 'stripe-onboarding-reminders')]);
        }

        // Get statuses
        $statuses = isset($_POST['statuses']) && is_array($_POST['statuses'])
            ? array_map('sanitize_text_field', $_POST['statuses'])
            : [];

        if (empty($statuses)) {
            wp_send_json_error(['message' => __('No statuses selected.', 'stripe-onboarding-reminders')]);
        }

        // Check if we should bypass rate limiting
        $bypass_rate_limit = isset($_POST['bypass_rate_limit']) && $_POST['bypass_rate_limit'] === 'true';

        // Send emails to all users with the selected statuses
        $sent_count = 0;
        $total_users = 0;

        foreach ($statuses as $status) {
            // Get all users with the status
            $user_ids = $this->core->get_users_with_status($status);
            $total_users += count($user_ids);

            // Send emails to each user
            foreach ($user_ids as $user_id) {
                // Check rate limiting if not bypassed
                if (!$bypass_rate_limit && $this->core->should_rate_limit_user($user_id)) {
                    continue;
                }

                // Send the email
                if ($this->core->send_reminder_email($user_id, $status)) {
                    $sent_count++;
                }

                // Prevent server overload for large batches
                if ($sent_count > 0 && $sent_count % 25 === 0) {
                    sleep(2);
                }
            }
        }

        // Store the timestamp of this manual trigger
        update_option('sor_last_manual_trigger', time());

        if ($sent_count > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    _n(
                        'Reminder email sent to %1$d user out of %2$d eligible users.',
                        'Reminder emails sent to %1$d users out of %2$d eligible users.',
                        $sent_count,
                        'stripe-onboarding-reminders'
                    ),
                    $sent_count,
                    $total_users
                ),
                'timestamp' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
            ]);
        } else {
            if ($total_users > 0) {
                wp_send_json_error([
                    'message' => __('No emails were sent. All eligible users have received reminders recently. Use "Bypass rate limit" option to send anyway.', 'stripe-onboarding-reminders')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('No eligible users found with the selected statuses.', 'stripe-onboarding-reminders')
                ]);
            }
        }
    }

    /**
     * Handle AJAX request to send manual reminder
     */
    public function ajax_send_manual_reminder(): void
    {
        // Check nonce and capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sor_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed.', 'stripe-onboarding-reminders')]);
        }

        // Get user ID
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('Invalid user ID.', 'stripe-onboarding-reminders')]);
        }

        // Get user status
        $status = $this->core->get_user_vendor_status($user_id);

        if (empty($status)) {
            wp_send_json_error(['message' => __('User does not need reminders.', 'stripe-onboarding-reminders')]);
        }

        // Send reminder
        $result = $this->core->send_reminder_email($user_id, $status);

        if ($result) {
            wp_send_json_success([
                'message' => __('Reminder sent successfully.', 'stripe-onboarding-reminders'),
                'timestamp' => date_i18n(get_option('date_format')),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to send reminder.', 'stripe-onboarding-reminders')]);
        }
    }

    /**
     * Handle AJAX request to send debug emails
     */
    public function ajax_send_debug_email(): void
    {
        // Check nonce and capabilities
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sor_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed.', 'stripe-onboarding-reminders')]);
        }

        // Get status and email
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        // Validate input
        if (empty($status)) {
            wp_send_json_error(['message' => __('No status selected.', 'stripe-onboarding-reminders')]);
            return;
        }

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'stripe-onboarding-reminders')]);
            return;
        }

        // Validate status is one of the expected values
        $valid_statuses = ['active_no_shipping', 'pending', 'not_setup'];
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error(['message' => __('Invalid status selected.', 'stripe-onboarding-reminders')]);
            return;
        }

        // Send test email
        $result = $this->core->send_test_email($status, $email);

        if ($result) {
            wp_send_json_success([
                'message' => __('Test email sent successfully.', 'stripe-onboarding-reminders'),
                'timestamp' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to send test email.', 'stripe-onboarding-reminders')]);
        }
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing plugin action links
     * @return array Modified plugin action links
     */
    public function add_plugin_action_links(array $links): array
    {
        // Add settings link
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=stripe_onboarding_reminders'),
            __('Settings', 'stripe-onboarding-reminders')
        );

        // Add documentation link
        $docs_link = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/blissguy/stripe-onboarding-reminders',
            __('View details', 'stripe-onboarding-reminders')
        );

        // Add links to the beginning of the array
        array_unshift($links, $settings_link, $docs_link);

        return $links;
    }
}
