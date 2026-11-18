<?php
// Functional catalog tests with a small WordPress adapter. Run with PHP CLI.
error_reporting(E_ALL);
set_error_handler(function($n,$s,$f,$l){throw new Exception("$s at $f:$l");});
$fixture = sys_get_temp_dir() . '/irl-catalog-test-' . bin2hex(random_bytes(8));
mkdir($fixture); mkdir($fixture.'/media'); mkdir($fixture.'/player'); mkdir($fixture.'/player/content');
mkdir($fixture.'/wp-admin',0777,true);mkdir($fixture.'/wp-admin/includes');
file_put_contents($fixture.'/wp-admin/includes/media.php','<?php');
copy(__DIR__.'/../../web/catalog.php',$fixture.'/catalog.php');
file_put_contents($fixture.'/media/Song.mp3','existing song');file_put_contents($fixture.'/player/content/cover.jpg','cover');
define('ABSPATH',$fixture.'/');define('IRL_VERSION','test');
$options=[];$actions=[];$routes=[];$allowed=true;
function get_option($key,$default=null){global $options;return $options[$key]??$default;}
function update_option($key,$value,$autoload=false){global $options;if(!empty($GLOBALS['fail_save'])||($options[$key]??null)===$value)return false;$options[$key]=$value;return true;}
function do_action($name,...$args){global $actions;if($name==='irl_catalog_saved'){$GLOBALS['catalog_events'][]=$args;if(get_option('irl_catalog')!==$args[0])throw new Exception('Hook ran before persistence');}foreach($actions[$name]??[] as $callback)$callback(...$args);}
function remove_accents($s){return $s;}
function home_url($p){return 'https://example.test'.$p;}
function wp_json_encode($v){return json_encode($v);}
function add_action($name,$callback){global $actions;$actions[$name][]=$callback;}
function register_rest_route($ns,$path,$args){global $routes;$routes[$path]=$args;}
function current_user_can($cap){global $allowed;return $allowed&&$cap==='manage_options';}
function irl_media(){global $fixture;return ['path'=>$fixture.'/media','url'=>'https://example.test/media'];}
function irl_base_album(){return ['schemaVersion'=>1,'title'=>'Root Pass','artwork'=>'content/cover.jpg','tracks'=>[['id'=>'original','title'=>'Original','file'=>'media/Song.mp3','artwork'=>'content/cover.jpg','lyrics'=>'one\ntwo']],'secret'=>['revealDuringTrack'=>'original','track'=>['id'=>'naked-billy-b-side-remix','title'=>'Bonus','file'=>'media/Song.mp3']]];}
function sanitize_text_field($s){return trim(strip_tags((string)$s));}
function sanitize_textarea_field($s){return trim(strip_tags((string)$s));}
function absint($s){return abs((int)$s);}
function wp_attachment_is_image($id){return $id===42;}
function wp_get_attachment_image_url($id,$size){return $id===42?'https://example.test/cover.jpg':false;}
function sanitize_file_name($s){return basename($s);}
function wp_mkdir_p($p){if(!is_dir($p))mkdir($p,0777,true);}
function wp_delete_file($p){unlink($p);}
function wp_read_audio_metadata($p){return str_starts_with(file_get_contents($p),'ID3')?['length'=>2,'fileformat'=>'mp3','title'=>'Uploaded song']:false;}
class WP_Error {public $code,$message,$data;function __construct($c,$m,$d){$this->code=$c;$this->message=$m;$this->data=$d;}}
class WP_REST_Request {private $body,$files;function __construct($b,$f=[]){$this->body=$b;$this->files=$f;}function get_json_params(){return $this->body;}function get_param($k){return $this->body[$k]??null;}function get_file_params(){return $this->files;}}
function check($test,$label){if(!$test)throw new Exception('FAIL: '.$label);echo "PASS: $label\n";}
function save_releases($releases,$revision=null){return irl_save_catalog(new WP_REST_Request(['revision'=>$revision??irl_catalog_revision(irl_catalog()),'releases'=>$releases]));}
require __DIR__.'/../../web/songs.php';
require $fixture.'/catalog.php';
foreach($actions['rest_api_init'] as $callback)$callback();
$allowed=false;check(!$routes['/catalog'][0]['permission_callback']()&&!$routes['/catalog'][1]['permission_callback']()&&!$routes['/catalog-upload']['permission_callback'](),'all catalog routes require administrator');$allowed=true;
$initial=irl_catalog();check(count($initial['releases'])===1&&$initial['releases'][0]['title']==='Root Pass','legacy album migration');
check($initial['releases'][0]['slug']==='RootPass'&&$initial['releases'][0]['tracks'][0]['slug']==='Original','legacy URL segments prefill separate slugs');
// Simulate an already published catalog from before slug fields existed.
$legacy=$initial;unset($legacy['releases'][0]['slug'],$legacy['releases'][0]['tracks'][0]['slug']);
$legacy['releases'][0]['tracks'][]=array_merge($legacy['releases'][0]['tracks'][0],['id'=>'duplicate']);
$legacy['releases'][]=array_merge($legacy['releases'][0],['id'=>'duplicate-release','tracks'=>[array_merge($legacy['releases'][0]['tracks'][0],['id'=>'third'])]]);
$options['irl_catalog']=$legacy;
$migrated=irl_catalog();
check($migrated['releases'][0]['tracks'][1]['slug']==='Original-duplicate'&&$migrated['releases'][1]['slug']==='RootPass-duplicate-release','migration preserves old duplicate-name URL suffixes');
$old_urls=array_column(irl_song_entries(irl_album()),'pageUrl');
$renamed=$legacy['releases'];$renamed[0]['title']='New Album Name';$renamed[0]['tracks'][0]['title']='New Song Name';
$result=save_releases($renamed);
check(!($result instanceof WP_Error)&&array_column(irl_song_entries(irl_album()),'pageUrl')===$old_urls,'rename without slug fields preserves legacy song and release URLs');
$reordered=array_reverse($result['catalog']['releases']);$reordered[1]['tracks']=array_reverse($reordered[1]['tracks']);save_releases($reordered);
check(array_column(irl_song_entries(irl_album()),'pageUrl','id')===array_reverse(array_combine(['original','duplicate','third'],$old_urls),true),'reordering duplicate display names keeps URLs attached to IDs');
$edit=irl_catalog()['releases'];$edit[1]['tracks'][1]['slug']='custom-song';$edit[1]['slug']='custom-release';
check(!(save_releases($edit) instanceof WP_Error)&&str_ends_with(irl_song_entries(irl_album())[2]['pageUrl'],'/custom-release/custom-song/'),'explicit slug edits change the URL');
foreach(['../escape','two words','bad?query','bad#fragment','bad/path',[],str_repeat('x',401)] as $invalid){
 $bad=$edit;$bad[1]['tracks'][1]['slug']=$invalid;check(save_releases($bad) instanceof WP_Error,'reject invalid song slug '.json_encode($invalid));
 $bad=$edit;$bad[1]['slug']=$invalid;check(save_releases($bad) instanceof WP_Error,'reject invalid release slug');
}
$bad=$edit;$bad[1]['slug']=strtoupper($bad[0]['slug']);check(save_releases($bad) instanceof WP_Error,'reject case-insensitive duplicate release slugs');
$bad=$edit;$bad[1]['tracks'][1]['slug']=strtoupper($bad[1]['tracks'][0]['slug']);check(save_releases($bad) instanceof WP_Error,'reject case-insensitive duplicate song slugs');
$blank=$edit;$blank[1]['slug']='';$blank[1]['tracks'][1]['slug']='';
check(!(save_releases($blank) instanceof WP_Error)&&irl_catalog()['releases'][1]['slug']==='custom-release'&&irl_catalog()['releases'][1]['tracks'][1]['slug']==='custom-song','clearing existing slug fields retains saved URLs');
$zero=$edit;$zero[1]['slug']='0';$zero[1]['tracks'][1]['slug']='0';
check(!(save_releases($zero) instanceof WP_Error)&&str_ends_with(irl_song_entries(irl_album())[2]['pageUrl'],'/0/0/'),'zero is a valid explicit slug');
unset($options['irl_catalog']);
$r=$initial['releases'];$r[]=['id'=>'single-test','type'=>'single','title'=>'New Single','artworkId'=>42,'artwork'=>'ignored URL','tracks'=>[['id'=>'new-song','title'=>'New Song','file'=>'media/Song.mp3']]];
$old_revision=irl_catalog_revision($initial);$saved=save_releases($r);check(!($saved instanceof WP_Error),'save album plus single and attachment artwork');
$a=irl_album();check(count($a['tracks'])===2&&count($a['releases'])===2&&$a['tracks'][1]['releaseTitle']==='New Single'&&$a['tracks'][1]['releaseArtwork']==='https://example.test/cover.jpg','flatten releases and propagate covers');
check($a['secret']['track']['releaseId']==='root-pass'&&$a['tracks'][0]['lyrics']==='one\ntwo','preserve bonus and existing track fields');
check(save_releases($r,$old_revision) instanceof WP_Error,'reject stale save');
$reverse=array_reverse($r);check(!(save_releases($reverse) instanceof WP_Error)&&irl_album()['tracks'][0]['id']==='new-song','release ordering persists');
$bad=$r;$bad[1]['tracks'][0]['id']='original';check(save_releases($bad) instanceof WP_Error,'reject duplicate song IDs');
$bad=$r;$bad[1]['tracks'][0]['file']='media/../catalog.php';check(save_releases($bad) instanceof WP_Error,'reject path traversal');
$bad=$r;$bad[1]['tracks'][0]['file']='https://example.test/song.mp3';check(save_releases($bad) instanceof WP_Error,'reject external song paths');
$bad=$r;$bad[1]['tracks'][0]['file']='media/missing.mp3';check(save_releases($bad) instanceof WP_Error,'reject missing MP3');
$bad=$r;$bad[1]['title']='';check(save_releases($bad) instanceof WP_Error,'reject unnamed release');
$bad=$r;$bad[1]['artworkId']=99;check(save_releases($bad) instanceof WP_Error,'reject non-image attachment');
$bad=$r;$bad[1]['tracks']=[];check(save_releases($bad) instanceof WP_Error,'reject empty release');
file_put_contents($fixture.'/media/Song.wav','master');
$with_wav=$r;$with_wav[0]['tracks'][0]['wav']='media/Song.wav';
$wav_saved=save_releases($with_wav);check(!($wav_saved instanceof WP_Error)&&$wav_saved['catalog']['releases'][0]['tracks'][0]['wav']==='media/Song.wav','preserve explicitly attached WAV');
$with_wav[0]['tracks'][0]['wav']='media/missing.wav';check(save_releases($with_wav) instanceof WP_Error,'reject missing WAV');
$with_wav[0]['tracks'][0]['wav']='media/../secret.wav';check(save_releases($with_wav) instanceof WP_Error,'reject WAV traversal');
$before=irl_catalog_revision(irl_catalog());
$tmp=$fixture.'/chunk';file_put_contents($tmp,'ID3'.str_repeat('a',1024*1024-3));$params=['name'=>'same-name.mp3','token'=>'12345678-1234-1234-1234-123456789abc','index'=>1,'total'=>2];
$files=['chunk'=>['tmp_name'=>$tmp]];
check(irl_catalog_upload(new WP_REST_Request($params,$files)) instanceof WP_Error,'reject out-of-order chunk');
$params['index']=0;check(isset(irl_catalog_upload(new WP_REST_Request($params,$files))['received']),'first upload chunk accepted');
check(irl_catalog_revision(irl_catalog())===$before,'upload does not publish catalog');
file_put_contents($tmp,'last chunk');$params['index']=1;$upload=irl_catalog_upload(new WP_REST_Request($params,$files));check(is_array($upload)&&is_file($fixture.'/media/'.basename($upload['file'])),'complete chunk upload with unique media name');
$params['index']=0;$params['total']=1;check(irl_catalog_upload(new WP_REST_Request($params,$files)) instanceof WP_Error,'completed upload cannot overwrite another file');
$params['token']='abcdef12-1234-1234-1234-123456789abc';check(irl_catalog_upload(new WP_REST_Request($params,$files)) instanceof WP_Error,'reject unreadable audio');
check(!(save_releases([$r[1]]) instanceof WP_Error)&&!isset(irl_album()['secret']),'remove Root Pass without leaving orphan bonus');
// Exercise the real Notifications listener with an isolated announcement store.
class AnnouncementStore {
 public $rows_affected=0,$insert_id=0,$drafts=[];
 function prepare($sql,...$args){return $args;}
 function query($args){$key=$args[0].':'.$args[1];$this->rows_affected=isset($this->drafts[$key])?0:1;if($this->rows_affected){$this->drafts[$key]=$args;$this->insert_id++;}}
}
function irn_table($name){return 'test_'.$name;}
function irn_now(){return '2026-10-10 00:00:00';}
function plugins_url($path){return 'https://example.test/plugins/'.$path;}
$wpdb=new AnnouncementStore();
require __DIR__.'/../../../Notifications/src/ian-raymond-notifications/includes/announcements.php';
$GLOBALS['catalog_events']=[];
$before=irl_catalog();$with_new=$before['releases'];
$with_new[]=['id'=>'hook-release','title'=>'Hook Release','type'=>'single','artwork'=>'','tracks'=>[['id'=>'hook-song','title'=>'Hook Song','file'=>'media/Song.mp3']]];
$hook_saved=save_releases($with_new);
check(!($hook_saved instanceof WP_Error)&&count($GLOBALS['catalog_events'])===1&&$GLOBALS['catalog_events'][0][1]===$before,'successful save fires hook after persistence with previous catalog');
check(count($wpdb->drafts)===1&&$wpdb->drafts['release:hook-release'][9]==='awaiting','new release creates one real Notifications announcement draft awaiting approval');
$edited=$hook_saved['catalog']['releases'];$edited[1]['title']='Renamed';$edited[1]['slug']='another-slug';save_releases(array_reverse($edited));
check(count($wpdb->drafts)===1,'rename, slug edit and reorder do not create announcements');
$event_count=count($GLOBALS['catalog_events']);save_releases(irl_catalog()['releases']);
check(count($GLOBALS['catalog_events'])===$event_count,'unchanged save does not fire hook');
$bad=irl_catalog()['releases'];$bad[0]['title']='';save_releases($bad);
save_releases(irl_catalog()['releases'],'stale');
check(count($GLOBALS['catalog_events'])===$event_count,'invalid and stale saves do not fire hook');
$GLOBALS['fail_save']=true;$bad=irl_catalog()['releases'];$bad[0]['title']='Cannot save';
check(save_releases($bad) instanceof WP_Error&&count($GLOBALS['catalog_events'])===$event_count,'storage failure does not fire hook or report success');
$GLOBALS['fail_save']=false;
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $p){$p->isDir()?rmdir($p):unlink($p);}rmdir($fixture);
echo "Catalog tests complete.\n";
