<?php
/** Song pages inside the site theme: theme header/footer, song title and meta instead of the theme's front-page tags. Run with PHP CLI. */
error_reporting(E_ALL);
set_error_handler(function($n,$s,$f,$l){throw new Exception("$s at $f:$l");});
define('ABSPATH', __DIR__); define('IRL_VERSION', 'test');
$actions=[];$filters=[];$styles=[];$calls=[];
function remove_accents($s){return $s;}
function home_url($p){return 'https://example.test'.$p;}
function plugin_dir_url($p){return 'https://example.test/plugin/';}
function irl_media(){return ['url'=>'https://example.test/media'];}
function irl_catalog_path($p,$ext){return false;}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}
function esc_url($s){return esc_html($s);}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function status_header($n){$GLOBALS['status']=$n;}
function nocache_headers(){}
function irl_album(){return $GLOBALS['album'];}
function add_action($n,$c,$p=10){$GLOBALS['actions'][$n][]=$c;}
function add_filter($n,$c,$p=10){$GLOBALS['filters'][$n][]=$c;}
function apply_filters($n,$v){foreach($GLOBALS['filters'][$n]??[] as $c)$v=is_string($c)&&$c==='__return_false'?false:$c($v);return $v;}
function __return_false(){return false;}
function locate_template($t){return '/theme/'.$t[0];}
function wp_enqueue_style($h,$src){$GLOBALS['styles'][$h]=$src;}
function get_header(){$GLOBALS['calls'][]='header';echo '<html><head>';foreach($GLOBALS['actions']['wp_head']??[] as $c)$c();echo '<title>'.apply_filters('pre_get_document_title','Theme title').'</title></head><body><header class="site-header">NAV</header>';}
function get_footer(){$GLOBALS['calls'][]='footer';echo '<footer class="site-footer">FOOT</footer></body></html>';}
function check($v,$label){if(!$v)throw new Exception("FAIL: $label");$GLOBALS["results"][]="PASS: $label";}
require __DIR__.'/../../web/songs.php';
$album=['tracks'=>[['id'=>'josephine','title'=>'Josephine','slug'=>'Josephine','releaseSlug'=>'RootPass','releaseId'=>'root-pass','releaseTitle'=>'Root Pass','file'=>'media/Josephine.mp3','artwork'=>'','releaseArtwork'=>'content/cover.jpg']]];
// The parse_query hook keeps song URLs from being treated as the blog home (front-page title/canonical).
$q=new class{public $is_home=true;function is_main_query(){return true;}function get($k){return $k==='irl_song'?'RootPass/Josephine':'';}};
foreach($actions['parse_query'] as $c)$c($q);check($q->is_home===false,'song URL is not the blog home');
ob_start();irl_song_render('RootPass/Josephine');$html=ob_get_clean();
check($calls===['header','footer']&&strpos($html,'class="site-header"')<strpos($html,'class="irl-song"')&&strpos($html,'class="irl-song"')<strpos($html,'class="site-footer"'),'song page sits between the theme header and footer');
check(strpos($html,'<title>Josephine — Ian Raymond | Root Pass</title>')!==false,'song title replaces the theme title');
check(apply_filters('ir_head_meta',true)===false,'theme description and share tags are turned off');
check(substr_count($html,'rel="canonical"')===1&&strpos($html,'MusicRecording')!==false&&strpos($html,'og:type" content="music.song')!==false,'song meta is printed once in the theme head');
check(isset($styles['irl-song'])&&strpos($html,'<!doctype')===false,'song CSS enqueued; no standalone document');
check(strpos($html,'data-trip')===false&&strpos($html,'song-trip.js')===false,'ordinary songs have no trip');
$album['secret']=['revealDuringTrack'=>'josephine','psychedelic'=>['bpm'=>140],'track'=>['title'=>'Naked Billy B-Side Remix','file'=>'media/Naked Billy.mp3'],'note'=>['title'=>'Secret Song','paragraphs'=>[['lead'=>'AI disclaimer:','text'=>'<b>x</b>']]]];$styles=[];
ob_start();irl_song_render('RootPass/Josephine');$html=ob_get_clean();
check(strpos($html,'data-trip data-bpm="140"')!==false&&strpos($html,'song-trip.js')!==false&&isset($styles['irl-song-trip']),'the song that reveals the secret gets the trip');
$json=json_decode(preg_replace('#^.*<script type="application/json" id="irl-secret">(.*?)</script>.*$#s','$1',$html),true);
check($json['title']==='Naked Billy B-Side Remix'&&$json['file']==='https://example.test/media/Naked%20Billy.mp3'&&$json['revealAt']==10&&strpos($html,'<b>x</b>')===false,'secret song data is passed to the page, escaped');
echo implode("\n",$results)."\nSong theme tests complete.\n";
