<?php
defined( 'ABSPATH' ) || exit;

return array(
	'slug'          => 'hide-login-path',
	'name'          => 'Hide Login Path',
	'description'   => 'Moves the WordPress login to an address you choose: wp-login.php and wp-admin no longer open for visitors, who are sent to a page of your choice.',
	'version'       => '1.0.0',
	'bootstrap'     => __DIR__ . '/src/Module.php',
	'class'         => 'DovetailStudio\\HideLoginPath\\Module',
	'admin_url'     => 'admin.php?page=dovetail-hide-login-path',
	// Screens styled by the Studio and kept free of WordPress and third-party notices.
	'admin_screens' => array( 'pages' => array( 'dovetail-hide-login-path' ) ),
);
