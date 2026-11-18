<?php
// Press kit (EPK) tests with a small WordPress adapter. Run with PHP CLI.
error_reporting(E_ALL);
set_error_handler(function($n,$s,$f,$l){throw new Exception("$s at $f:$l");});
$fixture = sys_get_temp_dir() . '/irl-epk-test-' . bin2hex(random_bytes(8));
mkdir($fixture); mkdir($fixture.'/uploads');
define('ABSPATH',$fixture.'/');
$options=[];$actions=[];$filters=[];$headers=[];$status=0;$redirect=null;$vars=[];
function get_option($k,$d=null){global $options;return $options[$k]??$d;}
function update_option($k,$v,$a=false){global $options;if(!empty($GLOBALS['fail_save'])||($options[$k]??null)===$v)return false;$options[$k]=$v;return true;}
function add_action($n,$c,$p=10){global $actions;$actions[$n][$p][]=$c;}
function add_filter($n,$c){global $filters;$filters[$n][]=$c;}
function add_rewrite_rule($r,$q,$w){$GLOBALS['rules'][$r]=$q;}
function wp_upload_dir(){global $fixture;return ['basedir'=>$fixture.'/uploads','baseurl'=>'https://example.test/wp-content/uploads'];}
function trailingslashit($s){return rtrim($s,'/').'/';}
function wp_max_upload_size(){return $GLOBALS['max_upload']??128*1024*1024;}
function home_url($p=''){return 'https://example.test'.$p;}
function remove_accents($s){return $s;}
function sanitize_text_field($s){return trim(strip_tags((string)$s));}
function sanitize_file_name($s){return preg_replace('/[^A-Za-z0-9._-]/','-',$s);}
function wp_basename($s){return basename($s);}
function size_format($b){return round($b/1048576).' MB';}
function wp_mkdir_p($p){if(!is_dir($p))mkdir($p,0777,true);}
function wp_delete_file($p){unlink($p);}
function is_wp_error($x){return $x instanceof WP_Error;}
function get_query_var($k){return $GLOBALS['vars'][$k]??'';}
function is_admin(){return !empty($GLOBALS['admin']);}
function status_header($n){$GLOBALS['status']=$n;}
function nocache_headers(){}
function wp_redirect($u,$s){$GLOBALS['redirect']=[$u,$s];}
function irl_epk_header($h){$GLOBALS['headers'][]=$h;}
class WP_Error {private $m;function __construct($c,$m){$this->m=$m;}function get_error_message(){return $this->m;}}
function check($test,$label){if(!$test)throw new Exception('FAIL: '.$label);echo "PASS: $label\n";}
require __DIR__.'/../../web/epk.php';
// The adapter cannot create real HTTP uploads, so tests move fixture files instead of move_uploaded_file().
$move='rename';
function pdf($body='press kit'){global $fixture;$f=$fixture.'/tmp-'.bin2hex(random_bytes(4));file_put_contents($f,'%PDF-1.7 '.$body);return $f;}
function files(){return array_values(array_diff(scandir(irl_epk_dir()['path']),['.','..','index.php']));}
function serve($key,$server=[]){global $headers,$status,$redirect;$headers=[];$status=0;$redirect=null;$_SERVER=$server+['REQUEST_METHOD'=>'GET'];ob_start();irl_epk_serve($key);return ob_get_clean();}

foreach($actions['init'][10] as $c)$c();
check($rules['^epk/?$']==='index.php?irl_epk=_main'&&$rules['^epk/([a-z0-9-]+)/?$']==='index.php?irl_epk=$matches[1]','main and per-kit routes');
check(irl_epks()===['main'=>'','items'=>[]]&&!irl_epk_main(),'starts empty with no main');

$full=irl_epk_add('Full press kit','',pdf('v1'),'Ian Raymond EPK.pdf',false,$move);
check(!is_wp_error($full)&&$full['slug']==='full-press-kit'&&irl_epks()['main']===$full['id'],'first kit gets a slug from its name and becomes main');
check(count(files())===1&&file_exists(irl_epk_dir()['path'].'/index.php'),'PDF stored in its own folder with directory index');
$venue=irl_epk_add('Venue / booking','venue',pdf('venue'),'venue.pdf',false,$move);
check(!is_wp_error($venue)&&$venue['slug']==='venue'&&irl_epks()['main']===$full['id'],'second kit keeps the existing main unless asked');
check(irl_epk_url('venue')==='https://example.test/epk/venue/'&&irl_epk_url()==='https://example.test/epk/','shareable URLs');

foreach([['Radio','venue','duplicate slug'],['Radio','VENUE','case-insensitive duplicate'],['','','empty name'],['!!!','','name without letters'],[str_repeat('x',121),'','long name']] as [$n,$s,$label])
 check(is_wp_error(irl_epk_add($n,$s,pdf(),'x.pdf',false,$move)),'reject '.$label);
check(is_wp_error(irl_epk_add('Radio','',pdf(),'radio.docx',false,$move)),'reject non-PDF extension');
$fake=$fixture.'/fake';file_put_contents($fake,'<html>not a pdf');check(is_wp_error(irl_epk_add('Radio','',$fake,'radio.pdf',false,$move)),'reject file without PDF header');
$empty=$fixture.'/empty';touch($empty);check(is_wp_error(irl_epk_add('Radio','',$empty,'radio.pdf',false,$move)),'reject empty file');
$max_upload=10;check(is_wp_error(irl_epk_add('Radio','',pdf('over ten bytes'),'radio.pdf',false,$move)),'reject file over server upload limit');unset($max_upload);
$slugged=irl_epk_slug('x','../Etc/Passwd',[]);check($slugged==='etc-passwd','slug input cannot escape the URL path');
check(count(irl_epks()['items'])===2&&count(files())===2,'rejected uploads leave nothing behind');

$old=irl_epks()['items'][0]['file'];
$v2=irl_epk_replace($full['id'],pdf('version two'),'EPK v2.pdf',$move);
check(!is_wp_error($v2)&&$v2['slug']==='full-press-kit'&&$v2['file']!==$old&&$v2['original']==='EPK-v2.pdf','new version keeps name and URL');
check(!in_array($old,files(),true)&&count(files())===2,'previous version deleted');
check(is_wp_error(irl_epk_replace($full['id'],$fake,'x.pdf',$move))&&irl_epks()['items'][0]['file']===$v2['file'],'invalid new version keeps the current one');
check(is_wp_error(irl_epk_replace('epk-missing',pdf(),'x.pdf',$move)),'replace unknown kit');

$GLOBALS['fail_save']=true;$before=files();
check(is_wp_error(irl_epk_replace($full['id'],pdf('v3'),'v3.pdf',$move))&&files()===$before,'save failure discards the new file and keeps the old');
check(is_wp_error(irl_epk_add('Radio','',pdf(),'r.pdf',false,$move))&&files()===$before,'save failure on add leaves no file');
unset($GLOBALS['fail_save']);

check(!is_wp_error(irl_epk_update($venue['id'],'Venue kit',''))&&irl_epks()['items'][1]['slug']==='venue','renaming with a blank URL name keeps the URL');
check(irl_epk_update($venue['id'],'Venue kit','booking')['slug']==='booking','URL name can be changed');
check(is_wp_error(irl_epk_update($venue['id'],'Venue kit','full-press-kit')),'rename cannot take another kit\'s URL');
check(!is_wp_error(irl_epk_update($venue['id'],'Venue kit','booking')),'a kit can keep its own URL name');

check(irl_epk_set_main($venue['id'])===true&&irl_epk_main()['id']===$venue['id'],'choose main');
check(is_wp_error(irl_epk_set_main('epk-missing'))&&irl_epk_main()['id']===$venue['id'],'unknown main rejected');

// Serving
$body=serve('_main');
check($status===200&&$body==='%PDF-1.7 venue'&&in_array('Content-Type: application/pdf',$headers,true),'/epk/ serves the main kit');
check(in_array('Content-Disposition: inline; filename="Ian-Raymond-EPK-booking.pdf"',$headers,true)&&in_array('Cache-Control: public, max-age=0, must-revalidate',$headers,true),'filename and revalidation headers');
check(serve('full-press-kit')==='%PDF-1.7 version two','/epk/<slug>/ serves that kit');
$etag=substr(array_values(array_filter($headers,fn($h)=>str_starts_with($h,'ETag: ')))[0],6);
check(serve('full-press-kit',['HTTP_IF_NONE_MATCH'=>$etag])===''&&$status===304,'unchanged version returns 304');
irl_epk_replace($full['id'],pdf('version three'),'v3.pdf',$move);
check(serve('full-press-kit',['HTTP_IF_NONE_MATCH'=>$etag])==='%PDF-1.7 version three'&&$status===200,'new version invalidates the old ETag');
check(serve('full-press-kit',['REQUEST_METHOD'=>'HEAD'])===''&&$status===200,'HEAD sends headers only');
check(str_contains(serve('nope'),'not found')&&$status===404,'unknown kit is 404');

// Theme setting (the theme links to ir_settings['press_pdf'])
check(irl_epk_theme_settings(['intro'=>'Hi'])===['intro'=>'Hi','press_pdf'=>'https://example.test/epk/'],'theme press kit setting points to /epk/');
check(irl_epk_theme_settings(false)===['press_pdf'=>'https://example.test/epk/'],'works when the theme has no saved settings');
check(count(array_filter($filters['option_ir_settings']??[]))===1&&count($filters['default_option_ir_settings']??[])===1,'filters saved and default theme settings');
$admin=true;check(irl_epk_theme_settings(['press_pdf'=>'x'])===['press_pdf'=>'x'],'admin screens see the stored theme setting');$admin=false;

// Delete
$venue_file=irl_epks()['items'][1]['file'];
check(irl_epk_delete($venue['id'])===true&&!irl_epk_main()&&!in_array($venue_file,files(),true),'deleting the main kit removes its file and clears main');
serve('_main');check($redirect===['https://example.test/wp-content/themes/ian-raymond/assets/Ian-Raymond-EPK.pdf',302],'/epk/ falls back to the theme PDF without a main');
check(irl_epk_theme_settings(['press_pdf'=>''])===['press_pdf'=>''],'theme falls back to its bundled PDF without a main kit');
check(str_contains(serve('booking'),'not found'),'deleted kit URL stops working');
check(is_wp_error(irl_epk_delete($venue['id'])),'delete unknown kit');
check(irl_epk_set_main('')===true&&irl_epk_set_main($full['id'])===true&&irl_epk_main()['id']===$full['id'],'main can be cleared and set again');
$options['irl_epks']['main']='epk-gone';check(irl_epks()['main']==='','dangling main is ignored');

array_map('unlink',glob($fixture.'/uploads/ian-epk/*'));rmdir($fixture.'/uploads/ian-epk');array_map('unlink',glob($fixture.'/*.*')+glob($fixture.'/tmp-*')+[$fake,$empty]);
echo "EPK tests complete.\n";
