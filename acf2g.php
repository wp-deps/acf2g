<?php
/**
 * Plugin Name:     Advanced Custom Fields to Gutenberg Blocks
 * Plugin URI:      https://github.com/stefthoen/acf2g
 * Description:     Convert Advanced Custom Fields to Gutenberg blocks.
 * Author:          Stef Thoen
 * Author URI:      https://stef.co
 * Text Domain:     acf2g
 * Domain Path:     /languages
 * Version:         0.1.1
 *
 * @package         acf2g
 */

if (defined('WP_CLI') && WP_CLI) {
    require_once 'wp-cli-commands.php';
}
