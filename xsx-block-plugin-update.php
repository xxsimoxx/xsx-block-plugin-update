<?php
/**
 * Plugin Name: Block plugin updates
 * Plugin URI: https://software.gieffeedizioni.it
 * Description: Prevent specific plugins from updating.
 * Version: 1.0.0-rc1
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Gieffe edizioni srl
 * Author URI: https://www.gieffeedizioni.it
 * Text Domain: xsx-bpu
 * Domain Path: /languages
 */

namespace XXSimoXX\bpu;

if (!defined('ABSPATH')) {
	die('-1');
}

require_once('classes/UpdateClient.class.php');

class BlockPluginUpdates{

	public $options_cache = false;

	public function __construct() {

		// Load text domain
		add_action('plugins_loaded', [$this, 'text_domain']);

		// Add toggle to plugin page
		add_filter('plugin_action_links', [$this, 'action_link'], PHP_INT_MAX - 10, 2);

		// Script and style
		add_action('admin_enqueue_scripts', [$this, 'script']);

		// AJAX
		add_action('wp_ajax_xsx_bpu_toggle', [$this, 'ajax_toggle']);

		// Disable plugin updates
		add_filter('site_transient_update_plugins', [$this, 'disable_plugin_updates']);

		// Uninstall.
		register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);

	}

	public function text_domain() {
		load_plugin_textdomain('xsx-bpu', false, basename(dirname(__FILE__)).'/languages');
	}

	public function disable_plugin_updates($value) {

		$disable = $this->options();

		if (!isset($value) || !is_object($value)) {
			return $value;
		}

		foreach ($disable as $plugin) {

			if (!isset($value->response[$plugin])) {
				continue;
			}

			$value->no_update[$plugin] = $value->response[$plugin];
			unset($value->response[$plugin]);

		}

		return $value;

	}

	private function warn($message, $line = false, $file = false) {

		if (!defined('WP_DEBUG') || WP_DEBUG !== true) {
			return;
		}

		$caller = debug_backtrace();
		if ($line === false) {
			$line = $caller[0]['line'];
		}
		if ($file === false) {
			$file = $caller[0]['file'];
		}

		if (function_exists('codepotent_php_error_log_viewer_log')) {
			return codepotent_php_error_log_viewer_log($message, 'notice', $file, $line);
		}

		$codepotent_file = plugin_dir_path(__DIR__).'codepotent-php-error-log-viewer/includes/functions.php';
		if (file_exists($codepotent_file)) {
			require_once($codepotent_file);
			return codepotent_php_error_log_viewer_log($message, 'notice');
		}

		trigger_error(print_r($x, true), E_USER_WARNING);

	}

	function toggle($slug) {

		$options = $this->options();

		if (in_array($slug, $options)) {
			$options = array_diff($options, [$slug]);
			update_option('xsx-bpu', $options);
			$this->options_cache = $options;
			return false;
		}

		$options[] = $slug;
		update_option('xsx-bpu', $options);
		$this->options_cache = $options;
		return true;

	}

	function aria($lock) {
		return $lock ? esc_html__('Resume updates for this plugin', 'xsx-bpu') : esc_html__('Block updates for this plugin', 'xsx-bpu');
	}

	public function ajax_toggle() {

		if (!check_ajax_referer('xsx-bpu-nonce', 'security', false)) {
			wp_send_json_error(esc_html__('Error in AJAX request: Invalid security token sent.', 'xsx-bpu'), '401');
			exit();
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error(esc_html__('Error in AJAX request: You do not have permission to do that.', 'xsx-bpu'), '401');
			exit();
		}

		if (!isset($_POST['plugin'])) {
			wp_send_json_error(esc_html__('Error in AJAX request: Plugin slug not found.', 'xsx-bpu'), '401');
			exit();
		}

		$slug = $_POST['plugin'];
		$status = $this->toggle($slug);
		$icon = $status ? 'dashicons-lock' : 'dashicons-unlock';
		$aria = $this->aria($status);

		wp_send_json_success([
								'plugin'	=> $slug,
								'icon'		=> $icon,
								'aria'		=> $aria,
							]);

		exit();

	}

	public function script($hook) {

		if ($hook !== 'plugins.php') {
			return;
		}

		wp_enqueue_script('xsx-bpu-script', plugin_dir_url(__FILE__).'/scripts/toggle.js', ['jquery'], false, true);
		wp_localize_script('xsx-bpu-script', 'xsx_bpu_localized', [
																		'ajax_url'  => admin_url('admin-ajax.php'),
																		'security'  => wp_create_nonce('xsx-bpu-nonce'),
																	]);

	}

	function options() {

		if ($this->options_cache !== false) {
			return $this->options_cache;
		}

		$this->options_cache = get_option('xsx-bpu', []);

		if (!is_array($this->options_cache)) {
			return [];
		}

		return $this->options_cache;

	}

	public function action_link($actions, $plugin_file) {

		$lock = in_array($plugin_file, $this->options()) ? 'dashicons-lock' : 'dashicons-unlock';
		$aria = $this->aria(in_array($plugin_file, $this->options()));
		$icon = '<a href="#" aria-label="'.$aria.'"><i class="dashicon '.$lock.' xsx-bpu-trigger" data-file="'.$plugin_file.'"></i><span class="spinner"></span></a>';

		array_unshift($actions, $icon);
		return $actions;

	}

	public static function uninstall() {

		if (!current_user_can('delete_plugins')) {
			return;
		}

		delete_option('xsx-bpu');

	}

}

new BlockPluginUpdates;