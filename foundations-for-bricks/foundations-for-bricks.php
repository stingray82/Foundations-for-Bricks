<?php
/**
 * Plugin Name:       Foundations for Bricks
 * Tested up to:      6.7.2
 * Description:       Automatically loads Bricks Builder settings and templates using Bricks' native structure
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Version:           1.21
 * Author:            reallyusefulplugins.com
 * Author URI:        https://reallyusefulplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       foundations-for-bricks
 * Website:           https://reallyusefulplugins.com
 * */


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class FoundationsForBricks {

    public function __construct() {
        add_action('admin_init', [$this, 'run_tasks']);
        add_action('admin_init', [$this, 'deactivate_and_delete_plugin']);
    }

    public function run_tasks() {
        // Only run tasks once and mark as complete
        if (get_option('foundations_bricks_tasks_complete', false)) {
            return;
        }

        if (!$this->is_bricks_installed()) {
            error_log('Bricks Builder is not installed or activated. Aborting.');
            return;
        }

        $this->import_bricks_theme_styles();
        $this->import_bricks_global_settings();
        $this->import_bricks_templates();
        $this->set_bricks_code_signatures_admin_notice();

        // Mark tasks as complete
        update_option('foundations_bricks_tasks_complete', true);
    }

    private function is_bricks_installed() {
        return class_exists('Bricks') || post_type_exists('bricks_template');
    }

    private function set_bricks_code_signatures_admin_notice() {
        update_option('bricks_code_signatures_admin_notice', 1);
        error_log('Bricks code signatures admin notice set.');
    }

    private function import_bricks_theme_styles() {
        $theme_styles_path = plugin_dir_path(__FILE__) . 'json/bricks-theme-styles.json';

        if (!file_exists($theme_styles_path)) {
            error_log('Theme styles file not found.');
            return;
        }

        $theme_styles_data = file_get_contents($theme_styles_path);
        $theme_styles_array = json_decode($theme_styles_data, true);

        if (!$theme_styles_array || !isset($theme_styles_array['id'], $theme_styles_array['label'], $theme_styles_array['settings'])) {
            error_log('Invalid theme styles JSON structure.');
            return;
        }

        $custom_styles = get_option('bricks_theme_styles', []);
        $style_id = $theme_styles_array['id'];
        $custom_styles[$style_id] = [
            'label' => $theme_styles_array['label'],
            'settings' => $theme_styles_array['settings'],
        ];
        update_option('bricks_theme_styles', $custom_styles);

        if (class_exists('Bricks\Theme_Styles')) {
            \Bricks\Theme_Styles::load_styles();
            \Bricks\Theme_Styles::load_set_styles();
        }

        if (class_exists('Bricks\Cache')) {
            \Bricks\Cache::clear_all();
        }

        error_log('Bricks theme styles imported successfully.');
    }

    private function import_bricks_global_settings() {
        $global_settings_path = plugin_dir_path(__FILE__) . 'json/bricks-global-settings.json';

        if (!file_exists($global_settings_path)) {
            error_log('Global settings file not found.');
            return;
        }

        $global_settings_data = file_get_contents($global_settings_path);
        $global_settings_array = json_decode($global_settings_data, true);

        if (!$global_settings_array || !is_array($global_settings_array)) {
            error_log('Invalid global settings JSON format.');
            return;
        }

        update_option('bricks_global_settings', $global_settings_array);
        do_action('bricks/settings/updated', 'global', $global_settings_array);
        error_log('Bricks global settings imported successfully.');
    }

    private function import_bricks_templates() {
        $templates_folder = plugin_dir_path(__FILE__) . 'json/templates/';

        if (!is_dir($templates_folder)) {
            error_log('Templates folder not found.');
            return;
        }

        $template_files = glob($templates_folder . '*.json');
        foreach ($template_files as $template_file) {
            $template_data = file_get_contents($template_file);
            $template_array = json_decode($template_data, true);

            if (!$template_array) {
                error_log('Invalid template JSON in file: ' . basename($template_file));
                continue;
            }

            $this->process_template($template_array);
        }
    }

    private function process_template($template_data) {
        if (!isset($template_data['title'], $template_data['content'])) {
            error_log('Template data is incomplete.');
            return;
        }

        $template_id = wp_insert_post([
            'post_title' => $template_data['title'],
            'post_status' => 'publish',
            'post_type' => 'bricks_template',
        ]);

        if ($template_id) {
            update_post_meta($template_id, BRICKS_DB_PAGE_CONTENT, $template_data['content'] ?? '');
            update_post_meta($template_id, BRICKS_DB_PAGE_SETTINGS, $template_data['pageSettings'] ?? []);
            update_post_meta($template_id, BRICKS_DB_TEMPLATE_SETTINGS, $template_data['templateSettings'] ?? []);
            error_log('Template imported successfully: ' . $template_data['title']);
        } else {
            error_log('Failed to import template: ' . $template_data['title']);
        }
    }

    public function deactivate_and_delete_plugin() {
        if (!get_option('foundations_bricks_tasks_complete', false)) {
            return; // Skip if tasks are not complete
        }

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins(plugin_basename(__FILE__));
        error_log('Plugin deactivated.');

        $plugin_file = plugin_basename(__FILE__);
        if (delete_plugins([$plugin_file])) {
            error_log('Plugin deleted successfully.');
        } else {
            error_log('Failed to delete plugin.');
        }
    }
}

new FoundationsForBricks();
