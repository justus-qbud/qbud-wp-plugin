<?php
// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options from the database
$qbud_options = array(
    'qbud_assistant_id',
    'qbud_color',
    'qbud_fullscreen',
    'qbud_w',
    'qbud_extension',
    'qbud_style',
    'qbud_rating',
    'qbud_client_type',
);
foreach ($qbud_options as $qbud_option) {
    delete_option($qbud_option);
}

?>