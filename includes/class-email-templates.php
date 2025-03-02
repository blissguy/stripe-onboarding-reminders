<?php

/**
 * Email Templates for Stripe Onboarding Reminders
 *
 * @package Stripe_Onboarding_Reminders
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email Templates class for Stripe Onboarding Reminders
 */
class Stripe_Onboarding_Reminders_Email_Templates
{

    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     * 
     * @param array $settings Plugin settings
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Generate email content for a specific status
     *
     * @param WP_User $user User object
     * @param string $status Status type
     * @return string HTML email content
     */
    public function get_email_content(WP_User $user, string $status): string
    {
        // Prepare common variables
        $user_name = $user->display_name;
        $site_name = get_bloginfo('name');
        $payout_url = site_url($this->settings['payout_settings_url']);
        $subject = $this->settings['subjects'][$status] ?? '';

        // Get status-specific template content
        $template_content = $this->settings['templates'][$status] ?? '';

        // Get button text
        $button_text = $this->settings['button_text'][$status] ?? $this->get_action_button_text($status);

        // Get footer content
        $footer_content = $this->settings['common_footer'] ?? '';

        // Replace placeholders in the template content
        $template_content = $this->replace_placeholders($template_content, [
            '%user_name%' => $user_name,
            '%site_name%' => $site_name,
            '%payout_url%' => $payout_url,
            '%admin_email%' => $this->settings['admin_email'] ?? get_option('admin_email'),
        ]);

        // Replace placeholders in the footer content
        $footer_content = $this->replace_placeholders($footer_content, [
            '%user_name%' => $user_name,
            '%site_name%' => $site_name,
            '%payout_url%' => $payout_url,
            '%admin_email%' => $this->settings['admin_email'] ?? get_option('admin_email'),
        ]);

        // Start output buffer
        ob_start();

        // Output HTML template
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($subject); ?></title>
            <style type="text/css">
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                    color: #333;
                    line-height: 1.6;
                    margin: 0;
                    padding: 0;
                }

                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }

                .header {
                    text-align: center;
                    margin-bottom: 40px;
                    border-radius: 5px 5px 0 0;
                }

                .content {
                    padding: 20px;
                    background-color: #ffffff;
                    border: 1px solid #e9e9e9;
                    border-radius: 0 0 5px 5px;
                }

                .button {
                    display: inline-block;
                    background-color: #016A17;
                    color: #ffffff !important;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 4px;
                    margin: 20px 0;
                    font-weight: 600;
                }

                .button:hover {
                    background-color: rgb(1, 80, 18);
                }

                .footer {
                    margin-top: 20px;
                    padding: 20px;
                    font-size: 12px;
                    text-align: center;
                    color: #666;
                }

                h1 {
                    color: #333;
                    margin: 0;
                }

                p {
                    margin-bottom: 15px;
                }

                .highlight {
                    background-color: #f8f9fa;
                    padding: 15px;
                    border-left: 4px solid #016A17;
                    margin: 20px 0;
                }

                .site-logo {
                    max-width: 200px;
                    max-height: 80px;
                    height: auto;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="header">
                    <?php
                    // Get custom logo from customizer
                    $custom_logo_id = get_theme_mod('custom_logo');
                    if ($custom_logo_id) {
                        $logo = wp_get_attachment_image_src($custom_logo_id, 'full');
                        if ($logo) {
                            echo '<img src="' . esc_url($logo[0]) . '" alt="' . esc_attr($site_name) . '" class="site-logo">';
                        } else {
                            echo '<h1>' . esc_html($site_name) . '</h1>';
                        }
                    } else {
                        echo '<h1>' . esc_html($site_name) . '</h1>';
                    }
                    ?>
                </div>
                <div class="content">
                    <p><?php echo sprintf(__('Hello %s,', 'stripe-onboarding-reminders'), esc_html($user_name)); ?></p>

                    <?php echo wp_kses_post($template_content); ?>

                    <p><a href="<?php echo esc_url($payout_url); ?>" class="button"><?php
                                                                                    echo esc_html($button_text);
                                                                                    ?></a></p>

                    <div class="highlight">
                        <p><strong><?php esc_html_e('Why is this important?', 'stripe-onboarding-reminders'); ?></strong></p>
                        <p><?php esc_html_e('A complete Stripe account setup ensures that you can receive payments promptly and without disruption. Missing information can delay payouts or prevent you from receiving funds altogether.', 'stripe-onboarding-reminders'); ?></p>
                    </div>

                    <?php echo wp_kses_post($footer_content); ?>
                </div>
                <div class="footer">
                    <p><?php echo sprintf(
                            __('This is an automated reminder from %s. You are receiving this because you have an incomplete Stripe onboarding process.', 'stripe-onboarding-reminders'),
                            esc_html($site_name)
                        ); ?></p>
                </div>
            </div>
        </body>

        </html>
        <?php

        return ob_get_clean();
    }

    /**
     * Get status-specific content
     *
     * @param string $status Status type
     * @return string HTML content for specific status
     */
    private function get_status_specific_content(string $status): string
    {
        ob_start();

        switch ($status) {
            case 'active_no_shipping':
        ?>
                <p><?php esc_html_e('Your Stripe account is active, but you need to provide your shipping information to start receiving payouts.', 'stripe-onboarding-reminders'); ?></p>
                <p><?php esc_html_e('Without this information, you may experience delays in receiving your funds.', 'stripe-onboarding-reminders'); ?></p>
                <p><strong><?php esc_html_e('What\'s missing:', 'stripe-onboarding-reminders'); ?></strong> <?php esc_html_e('Shipping address and contact information', 'stripe-onboarding-reminders'); ?></p>
            <?php
                break;

            case 'pending':
            ?>
                <p><?php esc_html_e('Your Stripe account setup is incomplete. You need to finish setting up your account to start receiving payments.', 'stripe-onboarding-reminders'); ?></p>
                <p><?php esc_html_e('At this point, you cannot receive any payments until you complete the required verification steps.', 'stripe-onboarding-reminders'); ?></p>
                <p><strong><?php esc_html_e('Required action:', 'stripe-onboarding-reminders'); ?></strong> <?php esc_html_e('Complete the Stripe onboarding process by providing the requested information.', 'stripe-onboarding-reminders'); ?></p>
            <?php
                break;

            case 'not_setup':
            ?>
                <p><?php esc_html_e('You have not set up your Stripe account yet. Setting up your account is necessary to receive payments through our platform.', 'stripe-onboarding-reminders'); ?></p>
                <p><?php esc_html_e('Until you complete this setup, you won\'t be able to receive any payments for your products or services.', 'stripe-onboarding-reminders'); ?></p>
                <p><strong><?php esc_html_e('Required action:', 'stripe-onboarding-reminders'); ?></strong> <?php esc_html_e('Start the Stripe account creation and verification process.', 'stripe-onboarding-reminders'); ?></p>
            <?php
                break;

            default:
            ?>
                <p><?php esc_html_e('Your Stripe account requires attention to ensure you can receive payments properly.', 'stripe-onboarding-reminders'); ?></p>
                <p><?php esc_html_e('Please review your account settings to make sure everything is set up correctly.', 'stripe-onboarding-reminders'); ?></p>
        <?php
                break;
        }

        return ob_get_clean();
    }

    /**
     * Get action button text based on status
     *
     * @param string $status Status type
     * @return string Button text
     */
    private function get_action_button_text(string $status): string
    {
        switch ($status) {
            case 'active_no_shipping':
                return __('Complete Shipping Information', 'stripe-onboarding-reminders');

            case 'pending':
                return __('Complete Your Stripe Account', 'stripe-onboarding-reminders');

            case 'not_setup':
                return __('Set Up Your Stripe Account', 'stripe-onboarding-reminders');

            default:
                return __('Review Your Payout Settings', 'stripe-onboarding-reminders');
        }
    }

    /**
     * Generate admin copy email content
     *
     * @param WP_User $user User the original email was sent to
     * @param string $status Status type
     * @param string $original_email Original email content
     * @return string HTML email content for admin
     */
    public function get_admin_copy_content(WP_User $user, string $status, string $original_email): string
    {
        // Start output buffer
        ob_start();

        ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php esc_html_e('[ADMIN COPY] Stripe Onboarding Reminder', 'stripe-onboarding-reminders'); ?></title>
            <style type="text/css">
                .admin-info {
                    background-color: #f0f6fc;
                    border: 1px solid #c3c4c7;
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                }

                .admin-info p {
                    margin: 5px 0;
                }
            </style>
        </head>

        <body>
            <div class="admin-info">
                <p><strong><?php esc_html_e('ADMIN COPY - This is a copy of an email sent to a user', 'stripe-onboarding-reminders'); ?></strong></p>
                <p><strong><?php esc_html_e('User:', 'stripe-onboarding-reminders'); ?></strong> <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)</p>
                <p><strong><?php esc_html_e('User ID:', 'stripe-onboarding-reminders'); ?></strong> <?php echo esc_html((string)$user->ID); ?></p>
                <p><strong><?php esc_html_e('Status:', 'stripe-onboarding-reminders'); ?></strong> <?php echo esc_html($status); ?></p>
                <p><strong><?php esc_html_e('Date:', 'stripe-onboarding-reminders'); ?></strong> <?php echo esc_html(current_time('mysql')); ?></p>
            </div>

            <hr>

            <?php echo $original_email; ?>
        </body>

        </html>
<?php

        return ob_get_clean();
    }

    /**
     * Generate admin copy email content
     *
     * @param WP_User $user User the original email was sent to
     * @param string $status Status type
     * @param string $original_email Original email content
     * @return string HTML email content for admin copy
     */
    public function get_admin_copy_email_content(WP_User $user, string $status, string $original_email): string
    {
        // Create a wrapper around the original email
        $admin_header = '<div style="padding: 15px; background-color: #f0f0f1; border-bottom: 4px solid #2271b1; margin-bottom: 20px;">';
        $admin_header .= '<h2 style="margin: 0; color: #2c3338; font-size: 18px;">' . __('Admin Copy of Email Sent to User', 'stripe-onboarding-reminders') . '</h2>';
        $admin_header .= '<p style="margin: 10px 0 0 0;"><strong>' . __('User:', 'stripe-onboarding-reminders') . '</strong> ' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</p>';
        $admin_header .= '<p style="margin: 5px 0 0 0;"><strong>' . __('Status:', 'stripe-onboarding-reminders') . '</strong> ' . esc_html($this->get_status_display_name($status)) . '</p>';
        $admin_header .= '<p style="margin: 5px 0 0 0;"><strong>' . __('Sent:', 'stripe-onboarding-reminders') . '</strong> ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'))) . '</p>';
        $admin_header .= '</div>';

        // Insert the admin header right after the opening body tag
        $admin_content = preg_replace('/<body>/', '<body>' . $admin_header, $original_email);

        return $admin_content;
    }

    /**
     * Get status display name
     *
     * @param string $status Status key
     * @return string Display name for status
     */
    private function get_status_display_name(string $status): string
    {
        $status_texts = [
            'not_setup' => __('Not Setup', 'stripe-onboarding-reminders'),
            'pending' => __('Pending', 'stripe-onboarding-reminders'),
            'active_no_shipping' => __('Active - No Shipping', 'stripe-onboarding-reminders'),
        ];

        return $status_texts[$status] ?? $status;
    }

    /**
     * Replace placeholders in a template string
     *
     * @param string $content Template content with placeholders
     * @param array $replacements Array of placeholders and their values
     * @return string Content with placeholders replaced
     */
    private function replace_placeholders(string $content, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
