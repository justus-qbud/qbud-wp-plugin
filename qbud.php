<?php
/**
 * Plugin Name: qBud Assistant
 * Plugin URI: https://qbud.ai
 * Description: Easily integrate your qBud AI assistant into WordPress by specifying your assistant ID.
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: qBud
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qbud
 */

if (!defined('ABSPATH')) {
    die;
}

// Optional data-* attributes the user can configure. Value is the input type.
function qbud_option_fields() {
    return array(
        'assistant_id' => array('label' => 'Assistant ID',     'type' => 'text',     'placeholder' => 'e.g., 55da7558-e82f-49fa-9e89-66ddb7d05e16', 'attr' => 'assistant-id'),
        'color'        => array('label' => 'Color',            'type' => 'text',     'placeholder' => 'e.g., #5e5cdb',                              'attr' => 'color'),
        'fullscreen'   => array('label' => 'Fullscreen',       'type' => 'checkbox', 'attr' => 'fullscreen'),
        'w'            => array('label' => 'Width (w)',        'type' => 'text',     'attr' => 'w'),
        'extension'    => array('label' => 'Extension',        'type' => 'text',     'attr' => 'extension'),
        'style'        => array('label' => 'Style',            'type' => 'text',     'attr' => 'style'),
        'rating'       => array('label' => 'Rating',           'type' => 'checkbox', 'attr' => 'rating'),
        'client_type'  => array('label' => 'Client type',      'type' => 'text',     'attr' => 'client-type'),
    );
}

function qbud_option_name($key) {
    return 'qbud_' . $key;
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
        'qBud Assistant Settings',
        'qBud Assistant',
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
                <?php foreach (qbud_option_fields() as $key => $field) :
                    $name  = qbud_option_name($key);
                    $value = get_option($name, '');
                ?>
                    <tr valign="top">
                        <th scope="row"><?php echo esc_html($field['label']); ?></th>
                        <td>
                            <?php if ($field['type'] === 'checkbox') : ?>
                                <input type="checkbox"
                                       name="<?php echo esc_attr($name); ?>"
                                       value="true"
                                       <?php checked($value, 'true'); ?> />
                                <span class="description"><?php
                                    /* translators: %s: data attribute name */
                                    printf(esc_html__('Sets data-%s="true" on the embed.', 'qbud'), esc_html($field['attr']));
                                ?></span>
                            <?php else : ?>
                                <input type="text"
                                       name="<?php echo esc_attr($name); ?>"
                                       value="<?php echo esc_attr($value); ?>"
                                       class="regular-text"
                                       placeholder="<?php echo esc_attr(isset($field['placeholder']) ? $field['placeholder'] : ''); ?>" />
                            <?php endif; ?>
                            <?php if ($key === 'assistant_id') : ?>
                                <p class="description">
                                    <?php esc_html_e('Required. Find this in your qBud dashboard at app.qbud.ai.', 'qbud'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings
function qbud_register_settings() {
    foreach (qbud_option_fields() as $key => $field) {
        register_setting('qbud-settings-group', qbud_option_name($key), 'sanitize_text_field');
    }
}
add_action('admin_init', 'qbud_register_settings');

// Add admin notice if Assistant ID is not set
function qbud_admin_notice() {
    $screen = get_current_screen();
    if (!$screen || $screen->id === 'settings_page_qbud-settings') {
        return;
    }
    if (empty(get_option('qbud_assistant_id'))) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php
                printf(
                    wp_kses(
                        /* translators: %s: Settings page URL */
                        __('qBud Assistant is installed but not configured. Please <a href="%s">set your Assistant ID</a> to enable qBud on your website.', 'qbud'),
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
add_action('admin_notices', 'qbud_admin_notice');

// Enqueue the embed script
function qbud_enqueue_scripts() {
    $assistant_id = get_option('qbud_assistant_id');
    if (empty($assistant_id)) {
        return;
    }

    wp_register_script(
        'qbud-script',
        'https://app.qbud.ai/cdn/qbud.js',
        array(),
        '1.0.0',
        true
    );
    wp_enqueue_script('qbud-script');
}
add_action('wp_enqueue_scripts', 'qbud_enqueue_scripts');

// Rewrite the script tag to match the qBud embed format from demo.html.
// We strip any WP-injected type attribute to avoid a duplicate-type collision
// with our type="module", and inject all configured data-* attributes.
function qbud_filter_script_tag($tag, $handle) {
    if ('qbud-script' !== $handle) {
        return $tag;
    }

    $attrs = 'type="module" crossorigin data-qbud data-host="api.qbud.ai"';
    foreach (qbud_option_fields() as $key => $field) {
        $value = get_option(qbud_option_name($key), '');
        if ($value === '' || $value === false) {
            continue;
        }
        $attrs .= ' data-' . esc_attr($field['attr']) . '="' . esc_attr($value) . '"';
    }

    // Drop any existing type='...' / type="..." WordPress added.
    $tag = preg_replace('/\stype=(["\'])[^"\']*\1/', '', $tag);

    return preg_replace('/<script\s/', '<script ' . $attrs . ' ', $tag, 1);
}
add_filter('script_loader_tag', 'qbud_filter_script_tag', 10, 2);
