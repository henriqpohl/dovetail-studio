<?php
/** Core\View: templates in views/, values through $args, safe names. */
require __DIR__ . '/lib/harness.php';

use DovetailStudio\Core\View;

boot_plugin();

$dir = WP_CONTENT_DIR . '/view-test';
@mkdir("$dir/views/partials", 0777, true);
file_put_contents("$dir/views/list.php", '<?php foreach ( $args["rows"] as $row ) : ?><li><?php echo esc_html( $row ); ?></li><?php endforeach; ?><?php \\DovetailStudio\\Core\\View::render( $args["dir"], "partials/note", array( "text" => "done" ) ); ?>');
file_put_contents("$dir/views/partials/note.php", '<p><?php echo esc_html( $args["text"] ); ?></p>');
file_put_contents("$dir/views/scope.php", '<?php echo isset( $secret ) ? "leak" : "isolated"; ?>');
file_put_contents("$dir/secret.php", '<?php echo "outside";');

t('renders with $args and partials', View::capture($dir, 'list', array('rows' => array('a', '<b>'), 'dir' => $dir)) === '<li>a</li><li>&lt;b&gt;</li><p>done</p>');
$secret = 'x';
t('template does not see the caller\'s variables', View::capture($dir, 'scope') === 'isolated');
ob_start(); View::render($dir, 'partials/note', array('text' => 'printed')); $o = ob_get_clean();
t('render prints', $o === '<p>printed</p>');

$before = count($GLOBALS['__warnings']);
t('names outside views/ are refused', View::capture($dir, '../secret') === '' && View::capture($dir, 'List') === '' && View::capture($dir, 'partials/../list') === '');
t('missing template prints nothing and reports it', View::capture($dir, 'missing') === '' && count($GLOBALS['__warnings']) > $before);
$GLOBALS['__warnings'] = array_slice($GLOBALS['__warnings'], 0, $before);
