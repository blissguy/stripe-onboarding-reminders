<?php

/**
 * WP_List_Table implementation for Stripe Onboarding Users
 *
 * @package Stripe_Onboarding_Reminders
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load WP_List_Table if not loaded
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class to display users with incomplete Stripe onboarding
 */
class Stripe_Onboarding_Reminders_Users_Table extends WP_List_Table
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
    public function __construct($core)
    {
        $this->core = $core;

        parent::__construct([
            'singular' => __('User', 'stripe-onboarding-reminders'),
            'plural'   => __('Users', 'stripe-onboarding-reminders'),
            'ajax'     => false,
        ]);
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action(): void
    {
        // Security check
        $nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
        if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
            return;
        }

        // Check if we have an action to perform
        $action = $this->current_action();
        if (!$action) {
            return;
        }

        // Get user IDs from request
        $user_ids = isset($_REQUEST['users']) ? array_map('intval', $_REQUEST['users']) : [];
        if (empty($user_ids)) {
            return;
        }

        // Process send_reminder action
        if ($action === 'send_reminder') {
            $sent_count = 0;

            // Send reminders to selected users
            foreach ($user_ids as $user_id) {
                $status = $this->core->get_user_vendor_status($user_id);
                if (!empty($status)) {
                    if ($this->core->send_reminder_email($user_id, $status)) {
                        $sent_count++;
                    }
                }
            }

            // Add admin notice with results
            if ($sent_count > 0) {
                // Construct result message
                $message = sprintf(
                    _n(
                        'Reminder sent to %d user.',
                        'Reminders sent to %d users.',
                        $sent_count,
                        'stripe-onboarding-reminders'
                    ),
                    $sent_count
                );

                // Store it in transient for display
                set_transient('sor_bulk_action_notice', [
                    'type' => 'success',
                    'message' => $message,
                ], 45);
            }

            // Redirect to refresh the page
            wp_safe_redirect(remove_query_arg(['action', 'action2', 'users', '_wpnonce']));
            exit;
        }
    }

    /**
     * Prepare items for table
     */
    public function prepare_items(): void
    {
        // Process bulk actions if any
        $this->process_bulk_action();

        // Set up columns
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Get all users with incomplete Stripe onboarding
        $all_users = $this->get_incomplete_onboarding_users();

        // Handle sorting
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'user_login';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'asc';
        $all_users = $this->sort_users($all_users, $orderby, $order);

        // Pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($all_users);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        // Slice data for current page
        $this->items = array_slice($all_users, (($current_page - 1) * $per_page), $per_page);
    }

    /**
     * Get all users with incomplete Stripe onboarding
     *
     * @return array Users with incomplete onboarding
     */
    private function get_incomplete_onboarding_users(): array
    {
        $incomplete_users = [];
        $valid_statuses = ['not_setup', 'pending', 'active_no_shipping'];

        // Filter by status if requested
        $filter_status = isset($_REQUEST['status']) && in_array($_REQUEST['status'], $valid_statuses)
            ? $_REQUEST['status']
            : null;

        // Get all users with the seller role
        $seller_users = get_users([
            'role' => 'seller',
            'fields' => ['ID', 'user_login', 'user_email', 'display_name'],
        ]);

        // Check each user's vendor status
        foreach ($seller_users as $user) {
            $user_id = (int) $user->ID;
            $status = $this->core->get_user_vendor_status($user_id);

            // Only include users with one of our tracked statuses
            if ($status && in_array($status, $valid_statuses)) {
                // If filtering by status, only include matching users
                if ($filter_status && $status !== $filter_status) {
                    continue;
                }

                $incomplete_users[] = [
                    'ID' => $user_id,
                    'user_login' => $user->user_login,
                    'display_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'status' => $status,
                    'last_reminder' => $this->get_last_reminder_date($user_id),
                ];
            }
        }

        // Apply search if set
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        if (!empty($search)) {
            $filtered_users = [];
            foreach ($incomplete_users as $user) {
                // Search in username, display name, and email
                if (
                    stripos($user['user_login'], $search) !== false ||
                    stripos($user['display_name'], $search) !== false ||
                    stripos($user['user_email'], $search) !== false
                ) {
                    $filtered_users[] = $user;
                }
            }
            $incomplete_users = $filtered_users;
        }

        return $incomplete_users;
    }

    /**
     * Get the date of the last reminder sent to a user
     *
     * @param int|string $user_id User ID
     * @return string Formatted date or "Never"
     */
    private function get_last_reminder_date($user_id): string
    {
        $user_id = intval($user_id); // Ensure user_id is an integer
        $last_reminder = get_user_meta($user_id, 'stripe_onboarding_reminder_last_sent', true);

        if (empty($last_reminder)) {
            return __('Never', 'stripe-onboarding-reminders');
        }

        return date_i18n(get_option('date_format'), (int) $last_reminder);
    }

    /**
     * Sort users array
     *
     * @param array  $users   Users array
     * @param string $orderby Order by field
     * @param string $order   Order direction (asc or desc)
     * @return array Sorted users
     */
    private function sort_users(array $users, string $orderby, string $order): array
    {
        // Define sorting function
        $sort_function = function ($a, $b) use ($orderby, $order) {
            // Default to user_login if invalid column
            if (!isset($a[$orderby])) {
                $orderby = 'user_login';
            }

            $result = 0;
            if ($a[$orderby] < $b[$orderby]) {
                $result = -1;
            } elseif ($a[$orderby] > $b[$orderby]) {
                $result = 1;
            }

            return $order === 'asc' ? $result : -$result;
        };

        // Sort the array
        usort($users, $sort_function);
        return $users;
    }

    /**
     * Define table columns
     *
     * @return array Columns
     */
    public function get_columns(): array
    {
        return [
            'cb'           => '<input type="checkbox" />',
            'user_login'   => __('Username', 'stripe-onboarding-reminders'),
            'display_name' => __('Name', 'stripe-onboarding-reminders'),
            'user_email'   => __('Email', 'stripe-onboarding-reminders'),
            'status'       => __('Onboarding Status', 'stripe-onboarding-reminders'),
            'last_reminder' => __('Last Reminder', 'stripe-onboarding-reminders'),
            'actions'      => __('Actions', 'stripe-onboarding-reminders'),
        ];
    }

    /**
     * Define sortable columns
     *
     * @return array Sortable columns
     */
    public function get_sortable_columns(): array
    {
        return [
            'user_login'   => ['user_login', true],
            'display_name' => ['display_name', false],
            'user_email'   => ['user_email', false],
            'status'       => ['status', false],
            'last_reminder' => ['last_reminder', false],
        ];
    }

    /**
     * Checkbox column
     *
     * @param array $item Item data
     * @return string HTML content
     */
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="users[]" value="%s" />',
            $item['ID']
        );
    }

    /**
     * Username column
     *
     * @param array $item Item data
     * @return string HTML content
     */
    public function column_user_login($item): string
    {
        $user_edit_link = get_edit_user_link($item['ID']);

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url($user_edit_link),
            esc_html($item['user_login'])
        );
    }

    /**
     * Default column handler
     *
     * @param array  $item       Item data
     * @param string $column_name Column name
     * @return string HTML content
     */
    public function column_default($item, $column_name): string
    {
        switch ($column_name) {
            case 'display_name':
            case 'user_email':
            case 'last_reminder':
                return esc_html($item[$column_name]);

            case 'status':
                return $this->get_formatted_status_badge($item['status']);

            default:
                return print_r($item, true);
        }
    }

    /**
     * Get a formatted status badge with styled appearance
     *
     * @param string $status The status code
     * @return string HTML for the formatted status badge
     */
    private function get_formatted_status_badge(string $status): string
    {
        // Status styling configuration
        $status_styles = [
            'active' => [
                'text'  => 'Active - Complete',
                'style' => 'background-color: #edfcef; color: #0f5132; padding: 4px 8px; border-radius: 4px; font-size: 12px;'
            ],
            'active_no_shipping' => [
                'text'  => 'Active - No Shipping',
                'style' => 'background-color: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 12px;'
            ],
            'inactive' => [
                'text'  => 'Inactive',
                'style' => 'background-color: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 12px;'
            ],
            'pending' => [
                'text'  => 'Pending',
                'style' => 'background-color: #fff7ed; color: #9a3412; padding: 4px 8px; border-radius: 4px; font-size: 12px;'
            ],
            'not_setup' => [
                'text'  => 'Not Setup',
                'style' => 'background-color: #f3f4f6; color: #374151; padding: 4px 8px; border-radius: 4px; font-size: 12px;'
            ]
        ];

        // Use the provided style if available, otherwise use a default style
        if (isset($status_styles[$status])) {
            $badge = $status_styles[$status];
            return sprintf(
                '<span style="%s">%s</span>',
                esc_attr($badge['style']),
                esc_html($badge['text'])
            );
        }

        // Fallback to plain text display name if status not in our styles array
        return esc_html($this->core->get_status_display_name($status));
    }

    /**
     * Actions column
     *
     * @param array $item Item data
     * @return string HTML content
     */
    public function column_actions($item): string
    {
        $send_nonce = wp_create_nonce('sor_send_reminder_' . $item['ID']);

        $actions = sprintf(
            '<a href="#" class="button sor-send-reminder" data-user-id="%d" data-nonce="%s">%s</a>',
            $item['ID'],
            $send_nonce,
            __('Send Reminder', 'stripe-onboarding-reminders')
        );

        return $actions;
    }

    /**
     * Get bulk actions
     *
     * @return array Bulk actions
     */
    public function get_bulk_actions(): array
    {
        return [
            'send_reminder' => __('Send Reminder', 'stripe-onboarding-reminders'),
        ];
    }

    /**
     * Display the table
     */
    public function display(): void
    {
        // Add screen reader text
        echo '<h2 class="screen-reader-text">' . esc_html__('Users With Incomplete Stripe Onboarding', 'stripe-onboarding-reminders') . '</h2>';

        // Prepare table navigation
        $this->prepare_items();

        // Display filter and search box
        echo '<div class="sor-table-filters">';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="', esc_attr($_REQUEST['page'] ?? ''), '" />';

        // Status filter dropdown
        $this->status_filter_dropdown();

        // Add search box
        $this->search_box(__('Search Users', 'stripe-onboarding-reminders'), 'sor-user-search');

        echo '</form>';
        echo '</div>';

        // Display the table
        parent::display();
    }

    /**
     * Status filter dropdown
     */
    private function status_filter_dropdown(): void
    {
        // Status styling configuration - matching the badge display
        $status_texts = [
            'not_setup' => 'Not Setup',
            'pending' => 'Pending',
            'active_no_shipping' => 'Active - No Shipping',
        ];

        $statuses = [
            'all' => __('All Statuses', 'stripe-onboarding-reminders'),
            'not_setup' => $status_texts['not_setup'],
            'pending' => $status_texts['pending'],
            'active_no_shipping' => $status_texts['active_no_shipping'],
        ];

        $current = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : 'all';

        echo '<select name="status">';
        foreach ($statuses as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($value, $current, false),
                esc_html($label)
            );
        }
        echo '</select>';

        submit_button(__('Filter', 'stripe-onboarding-reminders'), 'secondary', 'filter_action', false);
    }

    /**
     * Extra tablenav controls
     *
     * @param string $which Which tablenav (top or bottom)
     */
    public function extra_tablenav($which): void
    {
        if ($which === 'top') {
            echo '<div class="alignleft actions">';
            echo '<a href="#" class="button sor-refresh-table">' . esc_html__('Refresh List', 'stripe-onboarding-reminders') . '</a>';
            echo '</div>';
        }
    }
}
