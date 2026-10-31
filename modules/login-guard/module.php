<?php
defined( 'ABSPATH' ) || exit;

return array(
	'slug'          => 'login-guard',
	'name'          => 'Login Guard',
	'description'   => 'Brute force protection, two-factor authentication, reCAPTCHA or Turnstile on the login, email alerts for sign-ins and blocks, a sign-in activity log, password policies, hardening and a block list where blocked IP addresses can be reviewed and unblocked.',
	'version'       => '1.0.0',
	'bootstrap'     => __DIR__ . '/src/Module.php',
	'class'         => 'DovetailStudio\\LoginGuard\\Module',
	'admin_url'     => 'admin.php?page=dovetail-login-guard',
	// Screens styled by the Studio and kept free of WordPress and third-party notices.
	'admin_screens' => array( 'pages' => array( 'dovetail-login-guard' ) ),
	// Cleared when the module is disabled or removed and when the plugin is deactivated.
	'cron_hooks'    => array( 'dovetail_login_guard_cleanup', 'dovetail_login_guard_summary' ),
);
