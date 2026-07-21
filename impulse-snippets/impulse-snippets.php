<?php
/**
 * Plugin Name: Impulse Snippets
 * Description: Add unlimited named code snippets (scripts, styles, HTML) to your site's head, body, or footer — manually or with one-click integrations.
 * Version: 1.14.0
 * Author: Oxford Impulse
 * Author URI: https://oxfordimpulse.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: impulse-snippets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPCI_VERSION', '1.14.0' );
define( 'WPCI_PLUGIN_FILE', __FILE__ );
define( 'WPCI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WPCI_PLUGIN_DIR . 'includes/functions-helpers.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-conditions.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-cpt.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-admin-menu.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-plugin-links.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-list-table.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-edit-screen.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-save-handler.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-integrations.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-contact.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-docs.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-settings.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-rest-controller.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-output.php';
require_once WPCI_PLUGIN_DIR . 'includes/class-wpci-plugin.php';

Wpci_Plugin::instance();
