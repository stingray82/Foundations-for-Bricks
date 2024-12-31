<?php
/*
Plugin Name: Foundations for Bricks
Description: Automatically loads Bricks Builder settings and templates using Bricks' native structure.
Version: 1.2
Author: Stingray82
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class AutoLoadSettings {

    public function __construct() {
        if (did_action('activate_' . plugin_basename(__FILE__)) === 0) {
            register_activation_hook(__FILE__, [$this, 'on_activation']);
        }
        add_action('foundations_bricks_deactivation', [$this, 'handle_deactivation']);
    }

    public function on_activation() {
        if (!$this->is_bricks_installed()) {
            error_log('Bricks Builder is not installed or activated. Plugin deactivation aborted.');
            $this->deactivate_plugin();
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
        error_log('Bricks code signatures admin notice set to 1.');
    }

    private function import_bricks_theme_styles() {
        $theme_styles_path = plugin_dir_path(__FILE__) . 'json/bricks-theme-styles.json';

        if (file_exists($theme_styles_path)) {
            $theme_styles_data = file_get_contents($theme_styles_path);
            $theme_styles_array = json_decode($theme_styles_data, true);

            error_log('Decoded Theme Styles: ' . print_r($theme_styles_array, true));

            if ($theme_styles_array && isset($theme_styles_array['id'], $theme_styles_array['label'], $theme_styles_array['settings'])) {
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
                    error_log('Bricks theme styles manually loaded and activated.');
                }

                if (class_exists('Bricks\Cache')) {
                    \Bricks\Cache::clear_all();
                    error_log('Bricks cache cleared to apply theme styles.');
                }

                error_log('Bricks theme styles imported successfully.');
            } else {
                error_log('Invalid Bricks theme styles JSON structure.');
            }
        } else {
            error_log('Bricks theme styles file not found.');
        }
    }

    private function import_bricks_global_settings() {
        $global_settings_path = plugin_dir_path(__FILE__) . 'json/bricks-global-settings.json';

        if (file_exists($global_settings_path)) {
            $global_settings_data = file_get_contents($global_settings_path);
            $global_settings_array = json_decode($global_settings_data, true);

            if ($global_settings_array && is_array($global_settings_array)) {
                update_option('bricks_global_settings', $global_settings_array);
                do_action('bricks/settings/updated', 'global', $global_settings_array);
                error_log('Bricks global settings imported successfully.');
            } else {
                error_log('Invalid Bricks global settings JSON format.');
            }
        } else {
            error_log('Bricks global settings file not found.');
        }
    }

   private function import_bricks_templates() {
        $templates_folder = plugin_dir_path(__FILE__) . 'json/templates/';

        if (is_dir($templates_folder)) {
            $template_files = glob($templates_folder . '*.json');

            foreach ($template_files as $template_file) {
                $template_data = file_get_contents($template_file);
                $template_array = json_decode($template_data, true);

                if ($template_array) {
                    $this->process_template($template_array);
                } else {
                    error_log('Invalid template JSON format in file: ' . basename($template_file));
                }
            }
        } else {
            error_log('Templates folder not found.');
        }
    }

    private function process_template($template_data) {
        if (!isset($template_data['title']) || !isset($template_data['content'])) {
            error_log('Template data is incomplete.');
            return;
        }

        $template_id = wp_insert_post([
            'post_title'   => $template_data['title'],
            'post_status'  => 'publish',
            'post_type'    => 'bricks_template',
        ]);

        if ($template_id) {
            error_log("Template imported successfully: {$template_data['title']} (ID: $template_id)");

            // Save the template content and settings as post meta
            update_post_meta($template_id, BRICKS_DB_PAGE_CONTENT, $template_data['content'] ?? '');
            update_post_meta($template_id, BRICKS_DB_PAGE_SETTINGS, $template_data['pageSettings'] ?? []);
            update_post_meta($template_id, BRICKS_DB_TEMPLATE_SETTINGS, $template_data['templateSettings'] ?? []);
        } else {
            error_log("Failed to import template: {$template_data['title']}");
        }
    }


    private function deactivate_plugin() {
        error_log('Foundations for Bricks plugin deactivating...');

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        wp_schedule_single_event(time(), 'foundations_bricks_deactivation');
        error_log('Deactivation scheduled for Foundations for Bricks plugin.');
    }

    public function handle_deactivation() {
        deactivate_plugins(plugin_basename(__FILE__));

        if (is_plugin_active(plugin_basename(__FILE__))) {
            error_log('Failed to deactivate Foundations for Bricks plugin.');
        } else {
            error_log('Foundations for Bricks plugin deactivated successfully.');
        }
    }
}

new AutoLoadSettings();

function rup_deploy_deactivate_and_delete_this_plugin_FFB() {
    // Check if all tasks are complete
    if (get_option('foundations_bricks_tasks_complete', false)) {
        // Ensure the required functions are available
        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
        }

        // Deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));
        error_log('Plugin deactivated: ' . plugin_basename(__FILE__));

        // Delete the plugin
        $plugin_file = plugin_basename(__FILE__);
        if (delete_plugins([$plugin_file])) {
            error_log('Plugin deleted: ' . $plugin_file);
        } else {
            error_log('Failed to delete plugin: ' . $plugin_file);
        }
    } else {
        error_log('Tasks not complete. Plugin deletion skipped.');
    }
}
add_action('admin_init', 'rup_deploy_deactivate_and_delete_this_plugin_FFB');
