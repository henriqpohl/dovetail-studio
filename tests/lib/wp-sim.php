<?php
/**
 * Minimal WordPress simulator for the Dovetail Studio tests. It is NOT WordPress: just enough of the
 * API (hooks, options, transients, posts, REST, mail, a SQLite-backed $wpdb) to load the plugin and
 * exercise its code paths without a server. tests/run.php prepares DT_TEST_ROOT for every test file.
 */
error_reporting(E_ALL); ini_set('display_errors','1');
$__root = rtrim((string) getenv('DT_TEST_ROOT'), '/');
if ($__root === '') { fwrite(STDERR, "Run the tests with: php tests/run.php\n"); exit(2); }
set_error_handler(function($no,$str,$file,$line){ if(strpos($file,'/wp-content/plugins/dovetail-studio/')!==false){ $GLOBALS['__warnings'][]="$str @ ".basename($file).":$line"; } return true; });
define('ABSPATH', $__root . '/'); define('WP_CONTENT_DIR', $__root . '/wp-content');
define('DT_PLUGIN_MAIN', WP_CONTENT_DIR . '/plugins/dovetail-studio/dovetail-studio.php');
foreach(['HOUR_IN_SECONDS'=>3600,'MINUTE_IN_SECONDS'=>60,'DAY_IN_SECONDS'=>86400,'WEEK_IN_SECONDS'=>604800,'ARRAY_A'=>'ARRAY_A','OBJECT'=>'OBJECT'] as $k=>$v) define($k,$v);
@mkdir(WP_CONTENT_DIR.'/uploads',0777,true);
$GLOBALS['__hooks']=[]; $GLOBALS['__done']=[]; $GLOBALS['__opts']=['admin_email'=>'admin@example.com','active_plugins'=>[]]; $GLOBALS['__meta']=[]; $GLOBALS['__posts']=[]; $GLOBALS['__mail']=[]; $GLOBALS['__routes']=[]; $GLOBALS['__cron']=[]; $GLOBALS['__warnings']=[]; $GLOBALS['__user']=['id'=>0,'caps'=>[]]; $GLOBALS['__q']=[]; $GLOBALS['__out_headers']=[]; $GLOBALS['__enq']=[];
function add_filter($h,$cb,$p=10,$a=1){ $GLOBALS['__hooks'][$h][$p][]=[$cb,$a]; return true;}
function add_action($h,$cb,$p=10,$a=1){ return add_filter($h,$cb,$p,$a);}
function has_filter($h,$cb=false){ return !empty($GLOBALS['__hooks'][$h]);}
function remove_filter($h,$cb,$p=10){ if(empty($GLOBALS['__hooks'][$h][$p])) return false; foreach($GLOBALS['__hooks'][$h][$p] as $i=>$e) if($e[0]===$cb) unset($GLOBALS['__hooks'][$h][$p][$i]); return true;}
function remove_all_actions($h){ unset($GLOBALS['__hooks'][$h]); }
function apply_filters($h,$v,...$args){ if(empty($GLOBALS['__hooks'][$h])) return $v; $l=$GLOBALS['__hooks'][$h]; ksort($l); foreach($l as $es) foreach($es as $e){ $v=call_user_func_array($e[0],array_slice(array_merge([$v],$args),0,max(1,$e[1]))); } return $v;}
function do_action($h,...$args){ $GLOBALS['__done'][$h]=($GLOBALS['__done'][$h]??0)+1; if(empty($GLOBALS['__hooks'][$h])) return; $l=$GLOBALS['__hooks'][$h]; ksort($l); foreach($l as $es) foreach($es as $e){ call_user_func_array($e[0],array_slice($args,0,$e[1])); } }
function do_action_ref_array($h,$args){ do_action($h,...$args);}
function did_action($h){ return $GLOBALS['__done'][$h]??0;}
function get_option($k,$d=false){ return array_key_exists($k,$GLOBALS['__opts'])?$GLOBALS['__opts'][$k]:$d;}
function update_option($k,$v,$a=null){ $had=array_key_exists($k,$GLOBALS['__opts']); $old=$had?$GLOBALS['__opts'][$k]:null; if($had&&$old===$v) return false; $GLOBALS['__opts'][$k]=$v; if(function_exists('do_action')) $had?do_action('updated_option',$k,$old,$v):do_action('added_option',$k,$v); return true;}
function add_option($k,$v){ return update_option($k,$v);}
function delete_option($k){ if(!array_key_exists($k,$GLOBALS['__opts'])) return false; unset($GLOBALS['__opts'][$k]); if(function_exists('do_action')) do_action('deleted_option',$k); return true;}
function get_site_option($k,$d=false){return $d;}
function wp_rand($a=0,$b=0){ return random_int($a,$b); } function wp_using_ext_object_cache(){ return !empty($GLOBALS['__ext_cache']); } function wp_cache_add($k,$v,$g='',$e=0){ if(isset($GLOBALS['__cache'][$g][$k])) return false; $GLOBALS['__cache'][$g][$k]=$v; return true; } function wp_cache_incr($k,$o=1,$g=''){ if(!isset($GLOBALS['__cache'][$g][$k])) return false; return $GLOBALS['__cache'][$g][$k]+=$o; }
function get_transient($k){ $t=$GLOBALS['__opts']['_transient_'.$k]??null; return $t===null?false:$t;}
function set_transient($k,$v,$e=0){ $GLOBALS['__opts']['_transient_'.$k]=$v; return true;}
function delete_transient($k){ unset($GLOBALS['__opts']['_transient_'.$k]); return true;}
function wp_salt($s='auth'){return "salt-$s-xyz";}
function wp_hash($d){return hash_hmac('md5',$d,'h');}
/* $GLOBALS['__tr'] = array(msgid => translation) simulates a loaded .mo file. */
function __($s,$d=null){ if(empty($GLOBALS['__done']['init'])){ $b=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,3); $f=$b[1]['function']??''; $c=in_array($f,array('esc_html__','esc_attr__','esc_html_e','esc_attr_e','_e','_n','_x'),true)?($b[2]??$b[1]):$b[1]; $GLOBALS['__early_i18n'][]=basename($c['file']??'?').':'.($c['line']??0).' '.$s; } return $GLOBALS['__tr'][$s] ?? $s;} function esc_html__($s,$d=null){return htmlspecialchars(__($s));} function esc_attr__($s,$d=null){return htmlspecialchars(__($s),ENT_QUOTES);} function esc_html_e($s,$d=null){echo htmlspecialchars(__($s));} function esc_attr_e($s,$d=null){echo htmlspecialchars(__($s),ENT_QUOTES);} function _e($s,$d=null){echo __($s);}
function __return_true(){return true;} function __return_false(){return false;} function __return_empty_string(){return '';} function __return_empty_array(){return array();}
function _n($a,$b,$n,$d=null){ return __($n==1?$a:$b); } function _x($s,$c,$d=null){ return __($s); }
function wp_kses_post($s){ return (string) $s; } function get_locale(){ return $GLOBALS['__locale'] ?? 'en_US'; } function load_plugin_textdomain(){ return true; }
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES);} function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES);} function esc_textarea($s){return htmlspecialchars((string)$s);} function esc_js($s){return addslashes((string)$s);}
function esc_url($u){ $u=(string)$u; if($u!==''&&!preg_match('~^(https?:|/|#|mailto:|tel:|\?)~i',$u)) return ''; return htmlspecialchars($u,ENT_QUOTES);} function esc_url_raw($u){ return (string)$u;}
function sanitize_key($k){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$k));}
function sanitize_text_field($s){ return trim(preg_replace('/[\r\n\t ]+/',' ',strip_tags((string)$s)));}
function sanitize_textarea_field($s){ return trim(strip_tags((string)$s));}
function sanitize_email($e){ return filter_var(trim((string)$e),FILTER_SANITIZE_EMAIL);}
function is_email($e){ return filter_var((string)$e,FILTER_VALIDATE_EMAIL)?$e:false;}
function sanitize_file_name($f){ return preg_replace('/[^A-Za-z0-9._-]/','-',(string)$f);}
function absint($n){return abs((int)$n);}
function wp_unslash($v){return $v;}
function wp_strip_all_tags($s,$rb=false){ $s=strip_tags((string)$s); if($rb) $s=preg_replace('/[\r\n\t ]+/',' ',$s); return trim($s);}
function wp_specialchars_decode($s,$q=0){return htmlspecialchars_decode((string)$s,ENT_QUOTES);}
function wp_json_encode($d,$o=0){return json_encode($d,$o);}
function wp_parse_args($a,$d=[]){ return array_merge($d,is_array($a)?$a:[]);}
function wp_parse_url($u,$c=-1){return parse_url($u,$c);}
function home_url($p=''){return 'https://example.com'.($p?'/'.ltrim($p,'/'):'');}
function admin_url($p=''){return 'https://example.com/wp-admin/'.ltrim($p,'/');}
function rest_url($p=''){return 'https://example.com/wp-json/'.ltrim($p,'/');}
function content_url($p=''){return 'https://example.com/wp-content'.$p;}
function plugin_dir_path($f){return dirname($f).'/';} function plugin_dir_url($f){return 'https://example.com/wp-content/plugins/'.basename(dirname($f)).'/';} function plugin_basename($f){return basename(dirname($f)).'/'.basename($f);}
function trailingslashit($s){return rtrim($s,'/\\').'/';} function untrailingslashit($s){return rtrim($s,'/\\');} function user_trailingslashit($s){return trailingslashit($s);}
function wp_normalize_path($p){return str_replace('\\','/',$p);}
function add_query_arg(...$a){ if(is_array($a[0])){$q=$a[0];$u=$a[1]??'';}else{$q=[$a[0]=>$a[1]];$u=$a[2]??'';} return $u.(strpos($u,'?')===false?'?':'&').http_build_query($q);}
function wp_nonce_url($u,$a=-1){return add_query_arg('_wpnonce',wp_create_nonce($a),$u);}
function wp_create_nonce($a=-1){return substr(md5('n'.$a.$GLOBALS['__user']['id']),0,10);}
function wp_verify_nonce($n,$a=-1){return $n===wp_create_nonce($a)?1:false;}
function check_admin_referer($a=-1,$q='_wpnonce'){ if(!wp_verify_nonce($_REQUEST[$q]??'',$a)) throw new Exception('nonce fail '.$a); return 1;}
function check_ajax_referer($a=-1,$q=false){ return check_admin_referer($a,$q?:'_wpnonce');}
function wp_nonce_field($a=-1,$n='_wpnonce'){ echo '<input type="hidden" name="'.$n.'" value="'.wp_create_nonce($a).'">';}
function current_user_can($c,...$a){ $caps=$GLOBALS['__user']['caps']; if(in_array($c,['edit_post','delete_post','read_post'],true)) return in_array('manage_options',$caps,true); return in_array($c,$caps,true);}
function user_can($u,$c){ if(is_object($u)&&isset($u->roles)){ foreach((array)$u->roles as $r){ $role=get_role($r); if($role&&$role->has_cap($c)) return true; } return false; } return current_user_can($c);}
function is_user_logged_in(){return $GLOBALS['__user']['id']>0;}
function wp_validate_auth_cookie($c='',$s=''){return $GLOBALS['__user']['id']?:false;}
function auth_redirect(){ throw new Exception('auth_redirect');}
function wp_die($m='',$t='',$a=[]){ throw new Exception('wp_die: '.(is_string($m)?$m:'')); }
function wp_safe_redirect($u){ $GLOBALS["__redirect"]=$u; if(!empty($GLOBALS["__redirect_throws"])) throw new Exception("redirect:".urldecode(urldecode($u))); return true;}
function nocache_headers(){}
function is_admin(){return !empty($GLOBALS['__q']['admin']);}
foreach(['is_front_page','is_home','is_singular','is_404','is_search','is_archive','is_category','is_tag','is_tax','is_author','is_date','is_day','is_month','is_year','is_post_type_archive','is_product','is_shop','is_cart','is_checkout','is_account_page','is_product_taxonomy','is_woocommerce','is_customize_preview','is_multisite'] as $__f) eval("function $__f(...\$a){ return !empty(\$GLOBALS['__q']['$__f']); }");
function get_queried_object_id(){return $GLOBALS['__q']['id']??0;} function get_queried_object(){ $id=get_queried_object_id(); return $id?get_post($id):null;}
function get_query_var($v,$d=''){return $GLOBALS['__q']['vars'][$v]??$d;}
class WP_Post{ public $ID; public $post_type='post'; public $post_title=''; public $post_content=''; public $post_excerpt=''; public $post_author=1; public $post_date_gmt='2026-10-01 10:00:00'; public $post_modified_gmt='2026-10-02 10:00:00'; public $post_status='publish'; public $post_parent=0; public $post_name=''; function __construct($a=[]){ foreach($a as $k=>$v) $this->$k=$v; } }
class WP_Term{} class WP_User{} class WP_Rewrite{ function using_permalinks(){return true;} public $pagination_base='page'; }
function get_post($id){ return $GLOBALS['__posts'][$id]??null;}
function wp_insert_post($a){ $id=max(100,$GLOBALS['__posts']?max(array_keys($GLOBALS['__posts'])):0)+1; $p=new WP_Post(['ID'=>$id,'post_type'=>$a['post_type']??'post','post_title'=>$a['post_title']??'','post_content'=>$a['post_content']??'','post_status'=>$a['post_status']??'draft','post_date_gmt'=>gmdate('Y-m-d H:i:s')]); $GLOBALS['__posts'][$id]=$p; foreach(($a['meta_input']??[]) as $k=>$v) update_post_meta($id,$k,$v); return $id;}
function get_the_date($f='',$id=0){ $p=$GLOBALS['__posts'][$id]??null; return $p?substr($p->post_date_gmt,0,10):''; } function get_the_time($f='',$id=0){ $p=$GLOBALS['__posts'][$id]??null; return $p?substr($p->post_date_gmt,11,5):''; }
function get_post_field($f,$id){ $p=$GLOBALS['__posts'][$id]??null; return $p?(string)$p->$f:''; } function wp_add_privacy_policy_content($n,$t){ $GLOBALS['__privacy'][$n]=$t; }
function wp_delete_post($id,$f=false){ do_action('before_delete_post',$id); unset($GLOBALS['__posts'][$id]); return true;}
function get_post_type($id=null){ $p=get_post(is_object($id)?$id->ID:$id); return $p?$p->post_type:false;}
function get_post_status($id=null){ $p=get_post($id); return $p?$p->post_status:false;}
function get_post_meta($id,$k='',$s=false){ $v=$GLOBALS['__meta'][$id][$k]??''; return $s?$v:[$v];}
function update_post_meta($id,$k,$v){ $GLOBALS['__meta'][$id][$k]=$v; return true;}
function delete_post_meta($id,$k){ unset($GLOBALS['__meta'][$id][$k]); return true;}
function register_post_type($t,$a){ $GLOBALS['__cpt'][$t]=$a; return (object)$a;}
function get_post_type_object($t){ return isset($GLOBALS['__cpt'][$t])?(object)$GLOBALS['__cpt'][$t]:null;}
function is_post_type_hierarchical($t){return $t==='page';}
function register_rest_route($ns,$r,$a){ $GLOBALS['__routes']["/$ns$r"]=$a; return true;}
class WP_Error{ public $code; public $msg; public $data; function __construct($c='',$m='',$d=''){ $this->code=$c;$this->msg=$m;$this->data=$d;} function get_error_message(){return $this->msg;} function add($c,$m='',$d=''){ if($this->code===''){ $this->code=$c; } $this->msg=trim($this->msg.' '.$m); } function get_error_code(){return $this->code;} }
function is_wp_error($x){return $x instanceof WP_Error;}
class WP_REST_Request{ public $p=[]; public $body=''; public $files=[]; function __construct($p=[],$body='',$files=[]){$this->p=$p;$this->body=$body;$this->files=$files;} function get_param($k){return $this->p[$k]??null;} function get_body(){return $this->body;} function get_file_params(){return $this->files;} function get_json_params(){return json_decode($this->body,true);} }
class WP_REST_Response{ public $data; public $status; public $headers=[]; function __construct($d=null,$s=200){$this->data=$d;$this->status=$s;} function header($k,$v){$this->headers[$k]=$v;} function get_status(){return $this->status;} function get_data(){return $this->data;} }
function wp_mail($to,$s,$b,$h=[],$a=[]){ $GLOBALS['__mail'][]=compact('to','s','b','h','a'); return true;}
function wp_next_scheduled($h){ return $GLOBALS['__cron'][$h]['t']??false;}
function wp_get_schedules(){ return array_merge(array('hourly'=>array('interval'=>3600,'display'=>'Once Hourly'),'daily'=>array('interval'=>86400,'display'=>'Once Daily'),'weekly'=>array('interval'=>604800,'display'=>'Once Weekly')), (array) apply_filters('cron_schedules', array())); }
function wp_schedule_event($t,$r,$h){ if(!isset(wp_get_schedules()[$r])) return false; $GLOBALS['__cron'][$h]=['t'=>$t,'r'=>$r]; return true;}
function wp_clear_scheduled_hook($h){ unset($GLOBALS['__cron'][$h]); return 0;}
function current_time($t='mysql'){ return $t==='timestamp'?time():($t==='c'?date('c'):date($t==='mysql'?'Y-m-d H:i:s':$t));}
function date_i18n($f,$t=null){return date($f,$t??time());}
function wp_timezone_string(){ return $GLOBALS['__tz'] ?? 'Europe/Dublin'; }
function wp_timezone(){ return new DateTimeZone(wp_timezone_string()); }
function wp_date($f,$t=null,$tz=null){ $d=new DateTime('@'.($t??time())); $d->setTimezone($tz?:wp_timezone()); return $d->format($f);}
function get_settings_errors($s=''){ return $GLOBALS['__settings_errors_out'] ?? []; }
function get_date_from_gmt($d,$f='Y-m-d H:i:s'){return date($f,strtotime($d));}
function wp_generate_password($l=12,$s=true,$x=false){ return substr(bin2hex(random_bytes($l)),0,$l);}
function wp_upload_dir($t=null,$c=true){ $b=WP_CONTENT_DIR.'/uploads'; $d=['basedir'=>$b,'baseurl'=>'https://example.com/wp-content/uploads','subdir'=>'/2026/10','path'=>$b.'/2026/10','url'=>'https://example.com/wp-content/uploads/2026/10','error'=>false]; return apply_filters('upload_dir',$d);}
function wp_mkdir_p($d){ return is_dir($d)||@mkdir($d,0777,true);}
function wp_handle_upload($f,$o){ $u=wp_upload_dir(); wp_mkdir_p($u['path']); $ext='.'.strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)); $ok=false; foreach(array_keys($o['mimes']) as $m) if(in_array(ltrim($ext,'.'),explode('|',$m),true)) $ok=true; if(!$ok) return ['error'=>'type']; $name=isset($o['unique_filename_callback'])?call_user_func($o['unique_filename_callback'],$u['path'],$f['name'],$ext):$f['name']; $dest=$u['path'].'/'.$name; copy($f['tmp_name'],$dest); return ['file'=>$dest,'url'=>$u['url'].'/'.$name];}
function wp_is_file_mod_allowed($c){ return !defined('DISALLOW_FILE_MODS')||!DISALLOW_FILE_MODS;}
function get_bloginfo($k=''){ return ['name'=>'Example Co','description'=>'Tagline','language'=>'en-IE','charset'=>'UTF-8','version'=>'7.0'][$k]??'';}
function wp_remote_post($u,$a=[]){ $GLOBALS['__http'][]=['POST',$u,$a]; return $GLOBALS['__http_reply']??['response'=>['code'=>500],'body'=>''];}
function wp_remote_request($u,$a=[]){ $m=$a['method']??'GET'; $GLOBALS['__http'][]=[$m,$u,$a]; if(!empty($GLOBALS['__http_router'])) return call_user_func($GLOBALS['__http_router'],$m,$u,$a); return $GLOBALS['__http_reply']??['response'=>['code'=>200],'body'=>'{}'];}
function wp_remote_head($u,$a=[]){ $GLOBALS['__http'][]=['HEAD',$u,$a]; if(!empty($GLOBALS['__http_router'])) return call_user_func($GLOBALS['__http_router'],'HEAD',$u,$a); return ['response'=>['code'=>404],'body'=>''];}
function site_url($p=''){ return 'https://example.com'.($p?'/'.ltrim($p,'/'):''); }
function wp_safe_remote_get($u,$a=[]){ return ['response'=>['code'=>200],'body'=>''];}
function wp_remote_retrieve_body($r){return $r['body']??'';} function wp_remote_retrieve_response_code($r){return $r['response']['code']??0;}
function wp_send_json_error($d=null,$s=null){ throw new Exception('json_error '.json_encode($d));} function wp_send_json_success($d=null){ throw new Exception('json_ok');}
function get_current_screen(){ return $GLOBALS['__screen']??null;}
function add_menu_page(...$a){ $GLOBALS['__menu'][]=$a[3]; return 'toplevel_page_'.$a[3];} function add_submenu_page(...$a){ $GLOBALS['__menu'][]=$a[4]; return 'sub_'.$a[4];}
function add_meta_box(...$a){ $GLOBALS['__metaboxes'][]=$a;}
function register_setting($g,$n,$a=[]){ $GLOBALS['__settings'][$n]=$a;}
function settings_fields($g){} function settings_errors($s=''){ echo '<!--settings_errors-->';} function add_settings_error($s,$c,$m,$t='error'){ $GLOBALS['__settings_errors'][]=[$s,$c,$m];}
function submit_button($t='Save',$c='primary',$n='submit',$w=true){ echo "<button>$t</button>";}
function checked($a,$b=true,$e=true){ $r=$a==$b?' checked="checked"':''; if($e) echo $r; return $r;} function selected($a,$b=true,$e=true){ $r=(string)$a===(string)$b?' selected="selected"':''; if($e) echo $r; return $r;}
function wp_enqueue_style(...$a){ $GLOBALS['__enq'][]=$a[0];} function wp_enqueue_script(...$a){ $GLOBALS['__enq'][]=$a[0];} function wp_localize_script(){} function wp_add_inline_script($h,$d,$p="after"){ $GLOBALS["__inline"][$h][]=$d; return true; } function wp_add_inline_style($h,$d){ $GLOBALS["__inline_css"][$h][]=$d; return true; } function wp_enqueue_media(){}
function register_deactivation_hook($f,$cb){ $GLOBALS['__deact']=$cb;}
function shortcode_exists($t){return false;} function do_shortcode($s){return $s;}
function get_permalink($p=0){ $id=is_object($p)?$p->ID:$p; return 'https://example.com/?p='.$id;} function wp_get_canonical_url($p=0){ return get_permalink($p);}
function get_the_title($p=0){ $p=get_post(is_object($p)?$p->ID:$p); return $p?$p->post_title:'';}
function get_post_thumbnail_id($p=0){return 0;} function wp_get_attachment_image_src(){return false;} function wp_get_attachment_url(){return false;}
function get_the_excerpt($p=0){return '';} function has_excerpt($p=0){return false;}
function get_post_time($f='U',$g=false,$p=null){ return date($f,strtotime($p?$p->post_date_gmt:'now'));} function get_post_modified_time($f='U',$g=false,$p=null){ return date($f,strtotime($p?$p->post_modified_gmt:'now'));}
function get_userdata($id){ foreach(($GLOBALS['__users']??array()) as $login=>$d){ if((int)$d['ID']===(int)$id){ $u=new WP_User(); $u->ID=$id; $u->user_login=$login; $u->user_email=$d['email']??($login.'@example.org'); $u->roles=$d['roles']??array(); $u->display_name=$login; return $u; } } $u=new WP_User(); $u->ID=$id; $u->display_name='Author One'; $u->user_url=''; $u->description=''; return $u;}
function get_the_author_meta($f,$id=0){ return $f==='display_name'?'Author One':'';}
function get_author_posts_url($id){return 'https://example.com/author/one/';} function get_avatar_url($id){return 'https://example.com/a.png';}
function get_the_terms($p,$t){return false;} function get_object_taxonomies($t){return [];} function get_term_link($t){return 'https://example.com/t/';} function get_term($t){return null;} function get_taxonomy($t){return null;} function get_ancestors(){return [];} function get_post_ancestors($p){return [];} function term_description(){return '';}
function get_comments_number($p=0){return 0;}
function get_post_type_archive_link($t){return 'https://example.com/'.$t.'/';} function post_type_archive_title($p='',$e=true){return 'Archive';}
function get_search_link(){return 'https://example.com/?s=x';} function get_search_query(){return 'x';}
function get_day_link(){return home_url('/d/');} function get_month_link(){return home_url('/m/');} function get_year_link(){return home_url('/y/');}
function wp_get_document_title(){return 'Doc Title';}
function get_posts($a){ $out=[]; foreach($GLOBALS['__posts'] as $p){ if(($a['post_type']??'post')!==$p->post_type) continue;
    foreach((array)($a['meta_query']??[]) as $mq){ if(is_array($mq)&&isset($mq['key'],$mq['value'])&&(string)get_post_meta($p->ID,$mq['key'],true)!==(string)$mq['value']) continue 2; }
    foreach((array)($a['date_query']??[]) as $dq){ if(!is_array($dq)) continue; $col=$dq['column']??'post_date_gmt'; if(isset($dq['before'])&&!($p->$col<$dq['before'])) continue 2; if(isset($dq['after'])&&!($p->$col>$dq['after'])) continue 2; }
    $out[]=($a['fields']??'')==='ids'?$p->ID:$p;} $n=(int)($a['posts_per_page']??-1); return $n>0?array_slice($out,0,$n):$out;}
class WP_Query{ public $posts=[]; function __construct($a){ $this->posts=get_posts($a);} function have_posts(){return count($this->posts)>0;} }
if(!function_exists('wc')){ function wc(){return null;} }
if(!function_exists('wc_get_product')){ function wc_get_product($id){return null;} }
if(!function_exists('wc_get_attribute_taxonomies')){ function wc_get_attribute_taxonomies(){return [];} }
if(!function_exists('wc_attribute_taxonomy_name')){ function wc_attribute_taxonomy_name($n){return 'pa_'.$n;} }
function yoastseo(){return null;}
function get_settings_errors_dummy(){}
/* wpdb on SQLite, translating the few MySQL statements the plugin uses. */
class FakeWpdb{ public $options="wp_options"; public $postmeta="wp_postmeta"; public $prefix="wp_"; public $pdo; public $log=[];
 function __construct(){ $this->pdo=new PDO('sqlite::memory:'); $this->pdo->exec("CREATE TABLE wp_options (option_id INTEGER PRIMARY KEY AUTOINCREMENT, option_name TEXT UNIQUE, option_value TEXT, autoload TEXT)"); $this->pdo->exec("CREATE TABLE wp_postmeta (meta_id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, meta_key TEXT, meta_value TEXT)"); }
 function esc_like($t){ return addcslashes($t,'_%\\');}
 function prepare($q,...$a){ if(isset($a[0])&&is_array($a[0])) $a=$a[0]; $i=0; return preg_replace_callback('/%[sd]/',function($m)use(&$i,$a){ $v=$a[$i++]; return $m[0]==='%d'?(string)(int)$v:"'".str_replace("'","''",(string)$v)."'"; },$q);}
 function tr($q){ $this->log[]=$q;
  $q=preg_replace('/INSERT IGNORE INTO/','INSERT OR IGNORE INTO',$q);
  $q=preg_replace('/ON DUPLICATE KEY UPDATE option_value = option_value \+ 1/','ON CONFLICT(option_name) DO UPDATE SET option_value = CAST(option_value AS INTEGER) + 1',$q);
  $q=preg_replace("/LIKE '([^']*)'/","LIKE '$1' ESCAPE '\\'",$q);
  $q=str_replace('CONCAT(\'dt_va_evl_\', SUBSTRING(c.option_name, 11))',"('dt_va_evl_' || SUBSTR(c.option_name, 11))",$q);
  $q=str_replace('CONCAT(\'dt_va_evc_\', SUBSTRING(l.option_name, 11))',"('dt_va_evc_' || SUBSTR(l.option_name, 11))",$q);
  if(preg_match('/^DELETE l FROM (\w+) l LEFT JOIN \w+ c ON (.*?) WHERE (.*?) AND c\.option_id IS NULL$/s',$q,$m)){ $q="DELETE FROM {$m[1]} WHERE option_name IN (SELECT l.option_name FROM {$m[1]} l LEFT JOIN {$m[1]} c ON {$m[2]} WHERE {$m[3]} AND c.option_id IS NULL)"; }
  $q=str_replace('CAST(option_value AS SIGNED)','CAST(option_value AS INTEGER)',$q);
  $q=str_replace('option_value = option_value -','option_value = CAST(option_value AS INTEGER) -',$q);
  return $q; }
 function query($q){ $r=$this->pdo->exec($this->tr($q)); if($r===false) throw new Exception('SQL: '.$this->tr($q).' '.json_encode($this->pdo->errorInfo())); return $r;}
 function get_results($q,$o=OBJECT){ $s=$this->pdo->query($this->tr($q)); if(!$s) throw new Exception('SQL: '.$q); return $s->fetchAll(PDO::FETCH_ASSOC);}
 function get_var($q){ $s=$this->pdo->query($this->tr($q)); if(!$s) return null; $r=$s->fetch(PDO::FETCH_NUM); return $r===false?null:$r[0]; }
 function get_charset_collate(){ return ''; }
 function get_col($q){ return array_keys(array_filter($GLOBALS['__opts'],fn($k)=>strpos($k,'dt_va_stats_')===0,ARRAY_FILTER_USE_KEY)); }
}
/* Users, roles and the bits of wp-login.php Login Guard calls. $GLOBALS['__users'] = array(login => array('ID' => 1, 'roles' => array('administrator'))). */
function username_exists($u){ return isset($GLOBALS['__users'][strtolower((string)$u)]) ? $GLOBALS['__users'][strtolower((string)$u)]['ID'] : false; }
function sanitize_user($u,$strict=false){ $u=strip_tags((string)$u); return trim($strict?preg_replace('|[^a-z0-9 _.\-@]|i','',$u):$u); }
function wpautop($t){ return '<p>'.str_replace("\n",'<br>',trim((string)$t)).'</p>'; }
function wp_login_url($r=''){ return 'https://example.com/wp-login.php'; }
function wp_lostpassword_url($r=''){ return 'https://example.com/wp-login.php?action=lostpassword'; }
class DT_Role{ public $caps; function __construct($c){ $this->caps=$c; } function has_cap($c){ return !empty($this->caps[$c]); } }
function get_role($r){ $map=array('administrator'=>array('manage_options'=>1,'publish_posts'=>1),'editor'=>array('publish_posts'=>1),'author'=>array('publish_posts'=>1),'subscriber'=>array('read'=>1)); return isset($map[$r])?new DT_Role($map[$r]):null; }
function wp_remote_get($u,$a=[]){ $GLOBALS['__http'][]=['GET',$u,$a]; if(!empty($GLOBALS['__http_router'])) return call_user_func($GLOBALS['__http_router'],'GET',$u,$a); return $GLOBALS['__http_reply']??['response'=>['code'=>200],'body'=>''];}
class DT_Roles{ function get_names(){ return array('administrator'=>'Administrator','editor'=>'Editor','author'=>'Author','subscriber'=>'Subscriber'); } }
function wp_roles(){ return new DT_Roles(); } function translate_user_role($n){ return $n; }
/* Current user, user meta and the sign-in helpers Login Guard calls. */
function get_current_user_id(){ return (int) $GLOBALS['__user']['id']; }
function wp_get_current_user(){ $u=new WP_User(); $u->ID=(int)$GLOBALS['__user']['id']; $u->roles=$GLOBALS['__user']['roles']??array(); $u->user_login=$GLOBALS['__user']['login']??''; return $u; }
function get_user_meta($id,$k='',$single=false){ $v=$GLOBALS['__umeta'][$id][$k]??''; return $single?$v:array($v); }
function update_user_meta($id,$k,$v){ $GLOBALS['__umeta'][$id][$k]=$v; return true; }
function delete_user_meta($id,$k){ unset($GLOBALS['__umeta'][$id][$k]); return true; }
function wp_doing_ajax(){ return false; } function is_ssl(){ return true; }
function wp_clear_auth_cookie(){ $GLOBALS['__auth']=null; } function wp_set_auth_cookie($id,$remember=false){ $GLOBALS['__auth']=array($id,$remember); }
function wp_set_current_user($id){ $GLOBALS['__user']['id']=$id; }
function wp_validate_redirect($l,$d=''){ return strpos((string)$l,'https://example.com')===0?$l:$d; }
function login_header($t='',$m='',$e=null){ echo '<h1>'.$t.'</h1>'; if($e&&!empty($e->msg)) echo $e->msg; } function login_footer($f=''){ echo '<!--footer-->'; }
if(!defined('COOKIEPATH')) define('COOKIEPATH','/'); if(!defined('WP_PLUGIN_DIR')) define('WP_PLUGIN_DIR', WP_CONTENT_DIR.'/plugins'); if(!defined('COOKIE_DOMAIN')) define('COOKIE_DOMAIN','');
/* dbDelta for the simulator: the CREATE TABLE statements of a plugin, rewritten for SQLite. */
function dbDelta($sql){ foreach(array_filter(array_map('trim',explode(';',(string)$sql))) as $q){ if(!preg_match('/^CREATE TABLE (\w+) \((.*)\)[^)]*$/s',$q,$m)) continue; $cols=array();
  foreach(preg_split('/,\s*\n/',trim($m[2])) as $line){ $line=trim($line); if($line===''||preg_match('/^(PRIMARY KEY|KEY)\b/',$line)) continue;
   $line=preg_replace('/^UNIQUE KEY \w+ /','UNIQUE ',$line); $line=preg_replace('/bigint\(20\) unsigned NOT NULL AUTO_INCREMENT/','INTEGER PRIMARY KEY AUTOINCREMENT',$line); $line=str_replace(' unsigned','',$line); $cols[]=$line; }
  $GLOBALS['wpdb']->pdo->exec("CREATE TABLE IF NOT EXISTS {$m[1]} (".implode(', ',$cols).")"); } return array(); }
$GLOBALS['wpdb']=new FakeWpdb();
function _doing_it_wrong($f,$m,$v){ $GLOBALS['__warnings'][]='doing_it_wrong: '.$f.' '.$m; }
