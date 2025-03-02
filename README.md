# Stripe Onboarding Reminders for Voxel

A WordPress plugin that automatically sends reminder emails to Voxel marketplace vendors who haven't completed their Stripe onboarding process.

![Plugin Banner](https://github.com/user-attachments/assets/0a8f8594-6ec1-4d28-8a7e-4463e148bd03)

## 🚀 Features

- **Automated Reminders**: Schedule automated email reminders for vendors with incomplete Stripe onboarding
- **Status Targeting**: Target vendors based on their specific Stripe account status:
  - Not Setup
  - Pending
  - Active but missing shipping information
- **Customizable Templates**: Fully customizable email templates for each vendor status
- **Rate Limiting**: Configurable rate limiting to prevent sending too many emails
- **Manual Controls**: Send reminders manually to specific vendors or in bulk
- **Admin Tools**: Debug tools to test email delivery and appearance

## 📋 Requirements

- WordPress 5.6+
- PHP 7.4+
- Voxel Theme 1.6+
- WP Cron enabled on your server

## ⚙️ Installation

1. Download the latest release ZIP file from the [releases page](https://github.com/blissguy/stripe-onboarding-reminders/releases)
2. In your WordPress admin, go to Plugins → Add New → Upload Plugin
3. Choose the downloaded ZIP file and click "Install Now"
4. Activate the plugin through the 'Plugins' menu

## 🔧 Configuration

### Basic Setup

1. Navigate to "Stripe Reminders" in your WordPress admin menu
2. Configure your basic settings:
   - Payout Settings URL: The URL where vendors can complete their Stripe setup
   - Email Frequency: How often reminders can be sent to the same user
   - From Name/Email: Customize the sender information
   - Admin notifications: Optionally receive copies of all reminders

### Email Templates

The plugin allows you to customize email templates for each vendor status:

1. Go to the "Email Templates" tab
2. Edit the subject, body content, and button text for each status type
3. Use available placeholders:
   - `%user_name%` - The recipient's display name
   - `%site_name%` - Your website name
   - `%payout_url%` - Link to the payout settings page
   - `%admin_email%` - The admin email address

### Managing Vendors

The "Users" tab provides a comprehensive view of all vendors who haven't completed their Stripe onboarding:

1. View vendors by status
2. See when the last reminder was sent
3. Send manual reminders to specific vendors

## 📆 Scheduling

Reminder emails are sent automatically on a monthly schedule using WordPress cron. You can also:

- Manually trigger reminders for all eligible vendors
- Send one-off reminders to specific vendors
- Test templates with debug emails

## 🛠️ For Developers

### Filters

Customize the plugin's behavior with these filters:

```php
// Modify email content before sending
add_filter('sor_email_content', function($content, $user_id, $status) {
    // Your customizations here
    return $content;
}, 10, 3);

// Modify eligible users
add_filter('sor_eligible_users', function($user_ids, $status) {
    // Your customizations here
    return $user_ids;
}, 10, 2);
```

### Actions

```php
// Do something after a reminder is sent
add_action('sor_after_send_reminder', function($user_id, $status, $success) {
    // Your code here
}, 10, 3);
```

## 📝 Changelog

See [CHANGELOG.md](./CHANGELOG.md) for version history.

## 🐛 Reporting Issues

Found a bug or have a feature request? Please [open an issue](https://github.com/blissguy/stripe-onboarding-reminders/issues).

## 📜 License

This plugin is licensed under the GPL v2 or later.

---

Made with ❤️ for the Voxel community
/* Force sync: Sun Mar  2 22:03:30 GMT 2025 */
