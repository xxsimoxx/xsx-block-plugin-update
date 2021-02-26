<?php
/**
 * Plugin Name: Block plugin updates
 * Plugin URI: https://software.gieffeedizioni.it
 * Description: Prevent specific plugins from updating.
 * Version: 0.0.1
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

class BlockPluginsUpdate{

	public $options_cache = false;

	public function __construct() {

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

	private function warn($x) {
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

		array_push($options, $slug);
		update_option('xsx-bpu', $options);
		$this->options_cache = $options;
		return true;

	}

	public function ajax_toggle() {

		if (!check_ajax_referer('xsx-bpu-nonce', 'security', false)) {
			wp_send_json_error('Invalid security token sent.', '401');
			exit();
		}

		if (!isset($_POST['plugin'])) {
			wp_send_json_error('Plugin slug not found.', '401');
			exit();
		}

		$slug = $_POST['plugin'];
		$status = $this->toggle($slug);
		$icon = $status ? 'dashicons-lock' : 'dashicons-unlock';

		wp_send_json_success([
								'plugin'	=> $slug,
								'icon'		=> $icon,
							]);

		exit();

	}

	public function script($hook) {

		if ($hook !== 'plugins.php') {
			return;
		}

		wp_enqueue_script('xsx-bpu-script', plugin_dir_url(__FILE__).'/scripts/toggle.js', ['jquery'], false, true);
		wp_localize_script('xsx-bpu-script', 'xsx_bpu_datascript', [
																		'ajax_url'  => admin_url('admin-ajax.php'),
																		'security'  => wp_create_nonce('xsx-bpu-nonce'),
																	]);

	}

	function options() {

		if ($this->options_cache !== false) {
			return $this->options_cache;
		}

		$this->options_cache = get_option('xsx-bpu', []);
		return $this->options_cache;

	}

	public function action_link($actions, $plugin_file) {

		$lock = in_array($plugin_file, $this->options()) ? 'dashicons-lock' : 'dashicons-unlock';
		$icon = '<a href="#"><i class="dashicon '.$lock.' xsx-bpu-trigger" data-file="'.$plugin_file.'"></i><span class="spinner"></span></a>';
		array_unshift($actions, $icon);
		return $actions;

	}

	public static function uninstall() {
		delete_option('xsx-bpu');
	}

}

new BlockPluginsUpdate;