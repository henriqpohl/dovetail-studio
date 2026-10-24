<?php

defined( 'ABSPATH' ) || exit;

return array(
    'slug'        => 'virtual-assistant',
    'name'        => 'Virtual Assistant',
    'description' => 'Dovetail AI Assistant with REST endpoints, knowledge base, enquiries, spam controls and front-end interface.',
    'version'     => '1.31.1',
    'bootstrap'   => __DIR__ . '/src/Module.php',
    'class'       => 'DovetailStudio\\VirtualAssistant\\Module',
    'admin_url'   => 'edit.php?post_type=dt_enquiry&page=dt-va-settings',
);
