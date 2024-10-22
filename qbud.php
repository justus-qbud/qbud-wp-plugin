<?php
/**
 * Plugin Name: qBud
 * Plugin URI: https://qbud.ai
 * Description: Easily integrate your qBud assistant into WordPress by specifying your assistant ID.
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: Your Name
 * Author URI: https://qbud.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qbud
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Add settings link on plugin page
function qbud_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=qbud-settings">' . esc_html__('Settings', 'qbud') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'qbud_settings_link');

// Add admin menu under Settings
function qbud_admin_menu() {
    add_options_page(
        'qBud Settings',
        'qBud',
        'manage_options',
        'qbud-settings',
        'qbud_render_settings_page'
    );
}
add_action('admin_menu', 'qbud_admin_menu');

// Render settings page
function qbud_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('qbud-settings-group');
            do_settings_sections('qbud-settings-group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Assistant ID', 'qbud'); ?></th>
                    <td>
                        <input type="text" 
                               name="qbud_assistant_id" 
                               value="<?php echo esc_attr(get_option('qbud_assistant_id')); ?>" 
                               class="regular-text"
                               placeholder="e.g., 55da7558-e82f-49fa-9e89-66ddb7d05e16"
                        />
                        <p class="description">
                            <?php esc_html_e('Enter your qBud Assistant ID. You can find this in your qBud dashboard.', 'qbud'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings
function qbud_register_settings() {
    register_setting('qbud-settings-group', 'qbud_assistant_id', 'sanitize_text_field');
}
add_action('admin_init', 'qbud_register_settings');

// Add admin notice if Assistant ID is not set
function qbud_admin_notice() {
    $screen = get_current_screen();
    if ($screen->id !== 'settings_page_qbud-settings') {
        $assistant_id = get_option('qbud_assistant_id');
        if (empty($assistant_id)) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php 
                    printf(
                        wp_kses(
                            /* translators: %s: Settings page URL */
                            __('qBud is installed but not configured. Please <a href="%s">set your Assistant ID</a> to enable qBud on your website.', 'qbud'),
                            array('a' => array('href' => array()))
                        ),
                        esc_url(admin_url('options-general.php?page=qbud-settings'))
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }
}
add_action('admin_notices', 'qbud_admin_notice');

// Register and enqueue scripts and styles
function qbud_enqueue_scripts() {
    $assistant_id = get_option('qbud_assistant_id');
    if (!empty($assistant_id)) {
        // Register and enqueue the CSS file
        wp_register_style(
            'qbud-styles',
            'https://app.qbud.ai/cdn/qbud.css',
            array(),
            '1.0.0'
        );
        wp_enqueue_style('qbud-styles');

        // Register and enqueue the JS file
        wp_register_script(
            'qbud-script',
            'https://app.qbud.ai/cdn/qbud.js',
            array(),
            '1.0.0',
            true // Load in footer
        );
        
        // Add custom attributes to the script tag
        add_filter('script_loader_tag', function($tag, $handle) use ($assistant_id) {
            if ('qbud-script' === $handle) {
                $tag = str_replace(
                    ' src=',
                    ' crossorigin defer type="module" data-qbud data-host="api.qbud.ai" ' .
                    'data-assistant-id="' . esc_attr($assistant_id) . '" src=',
                    $tag
                );
            }
            return $tag;
        }, 10, 2);

        wp_enqueue_script('qbud-script');
    }
}
add_action('wp_enqueue_scripts', 'qbud_enqueue_scripts');