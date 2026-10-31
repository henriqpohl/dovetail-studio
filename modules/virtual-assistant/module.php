<?php
defined( 'ABSPATH' ) || exit;

return array(
	'slug'          => 'virtual-assistant',
	'name'          => 'Virtual Assistant',
	'description'   => 'Dovetail AI Assistant with REST endpoints, knowledge base, enquiries, spam controls and front-end interface.',
	'version'       => '1.33.1',
	'bootstrap'     => __DIR__ . '/src/Module.php',
	'class'         => 'DovetailStudio\\VirtualAssistant\\Module',
	'admin_url'     => 'edit.php?post_type=dt_enquiry&page=dt-va-settings',
	// Screens styled by the Studio and kept free of WordPress and third-party notices.
	'admin_screens' => array(
		'pages'      => array( 'dt-va-settings', 'dt-va-company-notes' ),
		'post_types' => array( 'dt_enquiry' ),
	),
	// Cleared when the module is disabled or removed and when the plugin is deactivated.
	'cron_hooks'    => array( 'dt_va_selfcheck', 'dt_va_weekly', 'dt_va_daily', 'dt_va_flush_events' ),
);
