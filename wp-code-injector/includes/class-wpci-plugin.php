<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots and holds every other piece of the plugin. No business logic lives here.
 */
class Wpci_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new Wpci_Cpt();
		new Wpci_Admin_Menu();
		new Wpci_Plugin_Links();
		new Wpci_List_Table();
		new Wpci_Edit_Screen();
		new Wpci_Save_Handler();
		new Wpci_Integrations();
		new Wpci_Contact();
		new Wpci_Docs();
		new Wpci_Settings();
		new Wpci_Rest_Controller();
		new Wpci_Output();
	}
}
