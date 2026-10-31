<?php
defined( 'ABSPATH' ) || exit;

return array(
	'slug'          => 'newsletter',
	'name'          => 'Newsletter',
	'description'   => 'Newsletter forms with Contact Form 7 and Mailchimp integration, encrypted credentials and double opt-in support.',
	'version'       => '3.4.0',
	'bootstrap'     => __DIR__ . '/src/Module.php',
	'class'         => 'DovetailStudio\\Newsletter\\Module',
	'admin_url'     => 'admin.php?page=dovetail-newsletter',
	// Screens styled by the Studio and kept free of WordPress and third-party notices.
	'admin_screens' => array( 'pages' => array( 'dovetail-newsletter' ) ),
);
