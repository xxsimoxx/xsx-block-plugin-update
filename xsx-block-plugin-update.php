<?php
/**
 * Plugin Name: Block plugin update
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
			add_filter('plugin_action_links', [$this, 'action_link'], 1000, 2);
			add_action('admin_enqueue_scripts', [$this, 'script']);
			add_action('wp_ajax_xsx_bpu_toggle', [$this, 'ajax_toggle']);
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

		wp_enqueue_style('xsx-bpu-style', plugin_dir_url(__FILE__).'/css/toggle.css');

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
		$icon = '<a href="#"><i class="dashicon '.$lock.' xsx-bpu-trigger" data-file="'.$plugin_file.'"></i></a>';
		array_unshift($actions, $icon);
		return $actions;
	}

}

new BlockPluginsUpdate;