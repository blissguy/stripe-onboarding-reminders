=== Stripe Onboarding Reminders ===
Contributors: yourname
Tags: stripe, reminders, emails, onboarding, payments
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically send reminder emails to users who haven't completed their Stripe onboarding process.

== Description ==

Stripe Onboarding Reminders helps marketplace and multi-vendor site owners ensure their vendors complete the Stripe onboarding process to receive payments. The plugin automatically sends customizable reminder emails to users with incomplete Stripe account setup.

= Key Features =

* **Automated Monthly Reminders**: Send emails on the 1st of each month to users with incomplete Stripe onboarding.
* **Status-Based Targeting**: Target users based on specific Stripe account status:
  * **Not Setup**: Users who haven't started the Stripe onboarding process.
  * **Pending**: Users who have started but not completed their Stripe account setup.
  * **Active without Shipping Info**: Users with active accounts missing shipping information.
* **Rate Limiting**: Prevent email fatigue with configurable sending frequency.
* **Customizable Templates**: Personalize email content for each status type.
* **Admin Copies**: Optionally receive copies of all reminder emails.
* **Test Functionality**: Send test emails to specific users from the admin interface.
* **Comprehensive Logging**: Track all email activity for troubleshooting.

= Voxel Theme Integration =

The plugin has built-in integration with the Voxel theme's Stripe functionality. For non-Voxel themes, it provides a fallback implementation that can be customized to match your specific setup.

= Advanced Customization =

Developers can extend the plugin through WordPress filters and actions to customize email content, user targeting, and functionality.

== Installation ==

1. Upload the `stripe-onboarding-reminders` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings > Stripe Reminders to configure the plugin.

== Frequently Asked Questions ==

= When are reminder emails sent? =

By default, reminder emails are sent on the 1st day of each month to ensure regular but not excessive communication with your users.

= Can I customize the email templates? =

Yes, the plugin provides settings to customize email subjects for each status type. The email content is designed to be professional and effective based on each status.

= How do I prevent sending too many emails to the same user? =

The plugin includes a rate-limiting feature that prevents sending emails to the same user more frequently than your configured setting (default is 30 days).

= Does this work with any Stripe integration? =

The plugin is designed to work with the Voxel theme by default, but includes a fallback implementation for other Stripe integrations. You may need to customize the `get_user_vendor_status` method in the core class to match your specific implementation.

= How can I test the email functionality? =

The plugin includes a test feature in the admin interface that allows you to send test emails to users with specific status types without waiting for the scheduled time.

== Screenshots ==

1. Admin settings page with email configuration options
2. Test email functionality
3. Sample email template

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of Stripe Onboarding Reminders.

== Developer Notes ==

= Customizing Email Templates =

Developers can modify the email templates by extending the `Stripe_Onboarding_Reminders_Email_Templates` class and using the `stripe_onboarding_reminders_email_template_class` filter:

```php
add_filter('stripe_onboarding_reminders_email_template_class', function($class_name) {
    return 'My_Custom_Email_Templates';
});
```

= Adding Custom Status Types =

To add custom status types, use the `stripe_onboarding_reminders_status_types` filter:

```php
add_filter('stripe_onboarding_reminders_status_types', function($statuses) {
    $statuses['my_custom_status'] = 'My Custom Status Display Name';
    return $statuses;
});
```

= Logs Location =

Email sending logs are stored in the `wp-content/uploads/stripe-onboarding-reminders-logs/` directory, with a separate log file for each day. 