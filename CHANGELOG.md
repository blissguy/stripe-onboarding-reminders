# Changelog

All notable changes to the Stripe Onboarding Reminders plugin will be documented in this file.

## [1.0.1] - 2025-03-03

### Fixed

- Security improvements with proper nonce verification throughout the admin interface
- Fixed type errors when handling user IDs with strict type checking
- Improved input sanitization for all form submissions
- Enhanced security by properly handling $\_REQUEST parameters
- Fixed issues with status filter and search functionality in the Users tab
- Added proper tab parameter persistence to maintain tab selection after form submission

### Improved

- Centralized status label management for consistency across the plugin
- Enhanced error handling for user status checks
- Improved helper methods for nonce verification
- Updated admin UI styling for better usability

## [1.0.0] - 2025-03-02

### Added

- Initial release
- Automated reminder emails for vendors with incomplete Stripe onboarding
- Support for three vendor statuses: Not Setup, Pending, and Active - No Shipping
- Customizable email templates for each status
- Email template customization interface
- Support for custom button text per status
- Common email footer section
- Rate limiting to prevent excessive emails
- Manual email triggering interface
- Manual reminder sending for individual users
- Debug email testing tool
- Rate limit bypass option for manual sends
- Last reminder date tracking
- User management table with status filters
- Top-level admin menu for easier access
- Modern UI with toggle switches
- Admin notification feature to receive copies of vendor emails
