<?php
/**
 * Plugin Name:       Dovetail Studio
 * Description:       Central toolkit for Dovetail Consultancy WordPress modules.
 * Version:           2.2.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Dovetail Consultancy
 * License:           GPL-2.0-or-later
 * Text Domain:       dovetail-studio
 */

defined( 'ABSPATH' ) || exit;

define( 'DOVETAIL_STUDIO_VERSION', '2.2.0' );
define( 'DOVETAIL_STUDIO_FILE', __FILE__ );
define( 'DOVETAIL_STUDIO_PATH', plugin_dir_path( __FILE__ ) );
define( 'DOVETAIL_STUDIO_URL', plugin_dir_url( __FILE__ ) );

require_once DOVETAIL_STUDIO_PATH . 'src/Core/Seo/Environment.php';
require_once DOVETAIL_STUDIO_PATH . 'src/Core/Seo/SchemaGraph.php';
require_once DOVETAIL_STUDIO_PATH . 'src/Core/Loader.php';

// Shared schema graph used by every schema module; it prints nothing until a module adds pieces.
\DovetailStudio\Core\Seo\SchemaGraph::instance()->register_hooks();

\DovetailStudio\Core\Loader::instance()->boot();
