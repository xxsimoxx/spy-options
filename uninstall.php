<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
	die;
}

$spy_options_options = [
	'spy-options-options',
];

foreach ($spy_options_options as $option) {
	delete_option($option);
}
