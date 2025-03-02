<?php

/**
 * Plugin Name: Stripe Onboarding Reminders
 * Plugin URI: https://github.com/blissguy/stripe-onboarding-reminders
 * Description: Automatically send reminder emails to users who haven't completed their Stripe onboarding process.
 * Version: 1.0.0
 * Author: Greyweb Consulting
 * Author URI: https://greywebconsulting.com
 * Text Domain: stripe-onboarding-reminders
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package Stripe_Onboarding_Reminders
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('STRIPE_ONBOARDING_REMINDERS_VERSION', '1.0.0');
define('STRIPE_ONBOARDING_REMINDERS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STRIPE_ONBOARDING_REMINDERS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STRIPE_ONBOARDING_REMINDERS_PLUGIN_FILE', __FILE__);
define('STRIPE_ONBOARDING_REMINDERS_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Stripe_Onboarding_Reminders
{

    /**
     * Core class instance
     *
     * @var Stripe_Onboarding_Reminders_Core
     */
    private $core;

    /**
     * Admin class instance
     *
     * @var Stripe_Onboarding_Reminders_Admin
     */
    private $admin;

    /**
     * Singleton instance
     *
     * @var Stripe_Onboarding_Reminders
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Stripe_Onboarding_Reminders Plugin instance
     */
    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Load plugin text domain
        add_action('init', [$this, 'load_textdomain']);

        // Initialize plugin
        add_action('init', [$this, 'init'], 20);
    }

    /**
     * Load text domain
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'stripe-onboarding-reminders',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Initialize plugin
     */
    public function init(): void
    {
        // Load dependencies first
        $this->load_dependencies();

        // Then initialize core functionality
        $this->core = new Stripe_Onboarding_Reminders_Core();

        // Finally initialize admin functionality if in admin area
        if (is_admin()) {
            $this->admin = new Stripe_Onboarding_Reminders_Admin($this->core);
        }
    }

    /**
     * Load dependencies
     */
    private function load_dependencies(): void
    {
        // Core class
        require_once STRIPE_ONBOARDING_REMINDERS_PLUGIN_DIR . 'includes/class-core.php';

        // Email templates class
        require_once STRIPE_ONBOARDING_REMINDERS_PLUGIN_DIR . 'includes/class-email-templates.php';

        // Admin class (only in admin area)
        if (is_admin()) {
            require_once STRIPE_ONBOARDING_REMINDERS_PLUGIN_DIR . 'admin/class-admin.php';
        }
    }
}

/**
 * Initialize plugin
 */
function stripe_onboarding_reminders_init()
{
    return Stripe_Onboarding_Reminders::get_instance();
}

// Start the plugin
stripe_onboarding_reminders_init();
