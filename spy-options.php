<?php
/**
 * Plugin Name:  Spy options
 * Description:  Get a list of which plugin use which option and delete unused ones.
 * Version:      1.1.0
 * License:      GPL2
 * Requires CP:  2.5
 * Requires PHP: 8.0
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Author:       Simone Fioravanti
 * Author URI:   https://www.simonefioravanti.it
 */

namespace XXSimoXX\SpyOptions;

if (!defined('ABSPATH')) {
	die('-1');
}

class SpyOptions {

	private $screen      = '';
	private $core_options = [];

	const SLUG           = 'spy_options';

	public function __construct() {
		require_once __DIR__.'/includes/core-options.php';
		$this->core_options = get_core_options();

		add_action('update_option',         [$this, 'spy']);
		add_action('add_option',            [$this, 'spy']);

		add_action('admin_menu',            [$this, 'create_menu'], 100);
		add_action('admin_enqueue_scripts', [$this, 'backend_css']);
	}

	public function spy($option) {
		if ($option === 'spy-options-options') {
			return;
		}
		if (str_starts_with($option, '_transient_')) {
			return;
		}
		// Without debug_backtrace this plugin just can't work.
		$bts = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		foreach ($bts as $bt) {
			if (!str_contains($bt['file'] ?? '', WP_CONTENT_DIR.'/plugins')) {
				continue;
			}
			$slug = str_replace(WP_CONTENT_DIR.'/plugins/', '', $bt['file']);
			$slug = substr($slug, 0, strpos($slug, '/'));
			$list = get_option('spy-options-options', []);
			if (array_key_exists($slug, $list) && in_array($option, $list[$slug])) {
				continue;
			}
			$list[$slug][] = $option;
			update_option('spy-options-options', $list);
			return;
		}
	}

	private function options_list($options) {
		$output = '';
		foreach ($options as $option) {
			$class = in_array($option, $this->core_options) ? ' class="spy-core-option"' : '';
			$output .= '<code'.$class.'>'.$option.'</code>, ';
		}
		$output = substr($output, 0, -2).'.';
		return $output;
	}

	public function render_page() {
		echo '<div class="wrap"><h1>'.esc_html(get_admin_page_title()).'</h1>';

		$this->display_notices(self::SLUG.'_notices');
		$list = get_option('spy-options-options', []);
		if ($list === []) {
			echo 'Nothing to show yet.';
			echo '</div>';
			return;
		}

		echo '<form action="'.esc_url_raw(add_query_arg(['action' => 'delete'], admin_url('admin.php?page='.self::SLUG))).'" method="POST">';
		wp_nonce_field('delete', '_'.self::SLUG);

		foreach ($list as $plugin_name => $options) {
			echo '<div class="item"><input type="checkbox" id="'.esc_attr($plugin_name).'" name="'.esc_attr($plugin_name).'">';
			echo '<label for="'.esc_attr($plugin_name).'">'.esc_attr($plugin_name).'</label><br>';
			echo wp_kses_post($this->options_list($options));
			echo '</div>';
		}
		echo '<input type="submit" class="button button-danger button-primary" id="submit_button" value="Delete"></input>';
		echo '</form>';
		echo '</div>';
	}

	public function create_menu() {
		if (!current_user_can('manage_options')) {
			return;
		}
		$this->screen = add_menu_page(
			'Spy options',
			'Spy options',
			'manage_options',
			self::SLUG,
			[$this, 'render_page'],
			'dashicons-search'
		);
		add_action('load-'.$this->screen, [$this, 'delete_action']);
		add_action('load-'.$this->screen, [$this, 'help']);
	}

	public function help() {
		$general_content = '<p><b>Get a list of which plugin use which option and delete unused ones.</b></p>
<p>This plugin catches other plugins adding/updating options and log options for each plugin.</p>
<p><b>This plugin is not intended to run in production.</b></p>
<p>When creating a new website, it is common to experiment with multiple plugins to achieve the desired functionality.<br>
When the site is finished, this plugin helps to clean up the database from options that are no longer necessary, allowing you to delete all the options of one or more plugins.<br>
The longer it remains active, the more options will be listed on this page.</p>
<p><b>By selecting the plugins and pressing delete all the options relating to those plugins will be deleted.</b><br>Options displayed in <code class="spy-core-option">darker gray</code> are core options, and will not be deleted.</p>';


		$screen = get_current_screen();
		$screen->add_help_tab(
			[
				'id'	  => 'spy_options_help_tab_general',
				'title'	  => 'Usage',
				'content' => '<p>'.$general_content.'</p>',
			]
		);
	}

	public function delete_action() {
		if ($this->before_action_checks('delete') !== true) {
			return;
		}

		$list    = get_option('spy-options-options', []);
		// Nonce is checked by before_action_checks
		$request = wp_unslash($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$delete  = [];
		foreach ($list as $plugin => $options) {
			if (!isset($request[$plugin])) {
				continue;
			}
			$delete = array_merge($delete, $options);
			unset($list[$plugin]);
			foreach ($options as $option) {
				if (in_array($option, $this->core_options)) {
					continue;
				}
				delete_option($option);
			}
		}

		update_option('spy-options-options', $list);

		$count = count($delete);
		$this->add_notice(self::SLUG.'_notices', $count.' option(s) deleted.', !$count);
		$sendback = remove_query_arg(['action', '_'.self::SLUG], wp_get_referer());
		wp_safe_redirect($sendback);
		exit;
	}

	public function backend_css($hook) {
		if ($hook !== $this->screen) {
			return;
		}
		wp_enqueue_style('spy_options_backend', plugin_dir_url(__FILE__).'css/backend.css', [], '1.1.0');
	}

	function before_action_checks($action) {
		if (!isset($_GET['action'])) {
			return false;
		}
		if ($_GET['action'] !== $action) {
			return false;
		}
		if (!check_admin_referer($action, '_'.self::SLUG)) {
			return false;
		}
		if (!current_user_can('manage_options')) {
			return false;
		}
		return true;
	}

	function add_notice($transient, $message, $failure = false) {
		$kses_allowed = [
			'br' => [],
			'i'  => [],
			'b'  => [],
		];
		$other_notices = get_transient($transient);
		$notice = $other_notices === false ? '' : $other_notices;
		$failure_style = $failure ? 'notice-error ' : 'notice-success ';
		$notice .= '<div class="notice '.$failure_style.'is-dismissible">';
		$notice .= '    <p>'.wp_kses($message, $kses_allowed).'</p>';
		$notice .= '</div>';
		set_transient($transient, $notice, \HOUR_IN_SECONDS);
	}

	function display_notices($transient) {
		$notices = get_transient($transient);
		if ($notices === false) {
			return;
		}
		// This contains html formatted from 'add_notice' function that uses 'wp_kses'.
		echo $notices; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		delete_transient($transient);
	}

}

new SpyOptions;
