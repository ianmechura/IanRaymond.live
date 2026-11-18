<?php
/** Run with PHP CLI; the WordPress adapter isolates public page rendering. */
error_reporting(E_ALL);
set_error_handler(function($n,$s,$f,$l){throw new Exception("$s at $f:$l");});
define('ABSPATH', __DIR__); define('IRL_VERSION', 'test');
function remove_accents($s){return $s;}
function home_url($p){return 'https://example.test'.$p;}
function plugin_dir_url($p){return 'https://example.test/plugin/';}
function irl_media(){return ['url'=>'https://example.test/media'];}
function irl_catalog_path($p,$ext){return $p==='media/Josephine.wav' ? $p : false;}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}
function esc_url($s){return esc_html($s);}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function status_header($n){$GLOBALS['status']=$n;}
function nocache_headers(){}
function irl_album(){return $GLOBALS['album'];}
function add_action($n,$c,$p=10){$GLOBALS['actions'][$n][]=$c;}
function add_filter($n,$c,$p=10){$GLOBALS['filters'][$n][]=$c;}
require __DIR__.'/../../web/songs.php';
$base=['id'=>'josephine','title'=>'Josephine','slug'=>'Josephine','releaseSlug'=>'RootPass','releaseId'=>'root-pass','releaseTitle'=>'Root Pass','file'=>'media/Josephine.mp3','artwork'=>'','releaseArtwork'=>'content/cover.jpg'];
$album=['tracks'=>[$base,array_merge($base,['id'=>'other','title'=>'Other Song','slug'=>'OtherSong','file'=>'media/Other.mp3'])]];
$results=[];
function check($value,$label){if(!$value)throw new Exception('FAIL: '.$label);$GLOBALS['results'][]='PASS: '.$label;}
$entries=irl_song_entries($album);
check($entries[0]['pageUrl']==='https://example.test/IanRaymond/Music/RootPass/Josephine/','requested readable URL');
$renamed=$album;$renamed['tracks'][0]['title']='A Different Display Name';$renamed['tracks'][0]['releaseTitle']='Renamed Album';
check(irl_song_entries($renamed)[0]['pageUrl']===$entries[0]['pageUrl'],'display names do not define URLs');
ob_start();irl_song_render('RootPass/Josephine');$html=ob_get_clean();
check($status===200 && substr_count($html,'<audio ')===1,'one-song page responds successfully');
check(strpos($html,'Download MP3')!==false && strpos($html,'Download WAV')!==false,'available downloads');
check(strpos($html,'Next song: Other Song')!==false && strpos($html,'rel="canonical"')!==false && strpos($html,'MusicRecording')!==false,'next link and SEO metadata');
ob_start();irl_song_render('RootPass/OtherSong');$html=ob_get_clean();
check(strpos($html,'WAV coming soon')!==false && strpos($html,'Download WAV')===false,'missing master does not create a broken download');
check(strpos($html,'Next song: Josephine')!==false,'last song wraps');
ob_start();irl_song_render('RootPass/Missing');$html=ob_get_clean();
check($status===404,'unknown song returns 404');
$album['tracks'][0]['story']='<script>alert(1)</script>';
ob_start();irl_song_render('RootPass/Josephine');$html=ob_get_clean();
check(strpos($html,'<script>alert(1)')===false && strpos($html,'&lt;script&gt;')!==false,'catalog text is escaped');
echo implode("\n",$results)."\n";
