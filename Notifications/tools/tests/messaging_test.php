<?php
/** Isolated functional tests. Requires PHP CLI with sqlite3; no real provider messages. */
error_reporting(E_ALL);
set_error_handler(function($n,$s,$f,$l){throw new Exception("$s at $f:$l");});
define('ABSPATH', __DIR__); define('DAY_IN_SECONDS',86400);
$options=['irn_settings'=>['twilio_sid'=>'AC'.str_repeat('a',32),'twilio_token'=>'test-token','sms_from'=>'+15551234567']];
$allowed=true;$calls=[];$scheduled=[];
function add_action(...$a){} function add_filter(...$a){}
function get_option($k,$d=null){global $options;return $options[$k]??$d;}
function update_option($k,$v,$a=false){global $options;$options[$k]=$v;}
function delete_option($k){global $options;unset($options[$k]);}
function current_user_can($c){global $allowed;return $allowed;}
function get_current_user_id(){return 1;}
function sanitize_text_field($v){return trim(strip_tags($v));}
function sanitize_textarea_field($v){return trim(strip_tags($v));}
function wp_json_encode($v){return json_encode($v);}
function rest_url($p){return 'https://example.test/wp-json/'.$p;}
function irn_now($o=0){return gmdate('Y-m-d H:i:s',time()+$o);}
function irn_table($n){return 'wp_irn_'.$n;}
function wp_next_scheduled($n){return false;}
function wp_schedule_single_event($t,$n){global $scheduled;$scheduled[]=$n;}
function wp_remote_post($url,$args){global $calls,$response;$calls[]=[$url,$args];return $response;}
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_remote_retrieve_response_code($r){return $r['status'];}
function wp_remote_retrieve_body($r){return json_encode($r['body']);}
class WP_Error{public $code,$message,$data;function __construct($c,$m,$d=[]){$this->code=$c;$this->message=$m;$this->data=$d;}function get_error_message(){return $this->message;}}
class WP_REST_Response{public $data,$status;function __construct($d,$s){$this->data=$d;$this->status=$s;}}
class WP_REST_Request{public $params,$signature;function __construct($p,$s){$this->params=$p;$this->signature=$s;}function get_body_params(){return $this->params;}function get_header($k){return $this->signature;}}
class DB {
 public $db,$prefix='wp_',$rows_affected=0,$fail=false;
 function __construct(){$this->db=new SQLite3(':memory:');}
 function prepare($sql,...$args){$i=0;return preg_replace_callback('/%[sd]/',function($m)use(&$i,$args){$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:"'".SQLite3::escapeString((string)$v)."'";},$sql);}
 function query($sql){if($this->fail)return false;$sql=str_replace('INSERT IGNORE','INSERT OR IGNORE',$sql);$this->db->exec($sql);return $this->rows_affected=$this->db->changes();}
 function get_var($sql){if(str_contains($sql,'GET_LOCK')||str_contains($sql,'RELEASE_LOCK'))return '1';return $this->db->querySingle($sql);}
 function get_row($sql){$r=$this->db->querySingle($sql,true);return $r?(object)$r:null;}
 function get_results($sql){$r=$this->db->query($sql);$rows=[];while($v=$r->fetchArray(SQLITE3_ASSOC))$rows[]=(object)$v;return $rows;}
}
$wpdb=new DB;
$wpdb->query("CREATE TABLE wp_irn_replies(id INTEGER PRIMARY KEY AUTOINCREMENT,channel TEXT,provider_id TEXT UNIQUE,sender TEXT,subject TEXT,body TEXT,received_at TEXT,recipient TEXT DEFAULT '',opt_out TEXT DEFAULT '')");
$wpdb->query("CREATE TABLE wp_irn_outbox(id INTEGER PRIMARY KEY AUTOINCREMENT,kind TEXT,announcement_id INTEGER,subscriber_id INTEGER NULL,request_key TEXT UNIQUE,channel TEXT,destination TEXT,payload TEXT,state TEXT,attempts INTEGER,next_attempt_at TEXT,created_at TEXT,provider_id TEXT,last_result TEXT,completed_at TEXT,UNIQUE(announcement_id,subscriber_id))");
$wpdb->query('CREATE TABLE wp_irn_subscribers(id INTEGER PRIMARY KEY,channel TEXT,destination TEXT)');
$wpdb->query('CREATE TABLE wp_irn_announcements(id INTEGER PRIMARY KEY,state TEXT,sent_at TEXT)');
foreach(['settings','providers','queue','inbound','conversations'] as $file)require __DIR__.'/../../src/ian-raymond-notifications/includes/'.$file.'.php';
function check($v,$label){if(!$v)throw new Exception('FAIL: '.$label);echo "PASS: $label\n";}
function incoming($body,$n,$opt=''){
 $p=['AccountSid'=>irn_setting('twilio_sid'),'MessageSid'=>'SM'.str_pad(dechex($n),32,'0',STR_PAD_LEFT),'From'=>'+15557654321','To'=>'+15551234567','Body'=>$body,'OptOutType'=>$opt];
 return new WP_REST_Request($p,irn_twilio_signature(irn_twilio_webhook_url(),$p,irn_setting('twilio_token')));
}
function key_for($n){return 'aaaaaaaa-aaaa-aaaa-aaaa-'.str_pad((string)$n,12,'0',STR_PAD_LEFT);}
$r=incoming('Love the song!',1);$bad=clone $r;$bad->signature='bad';
check(is_wp_error(irn_twilio_inbound($bad)),'reject forged webhook');
check(irn_twilio_inbound($r)->status===200,'accept authenticated incoming text');
irn_twilio_inbound($r);check($wpdb->get_var('SELECT COUNT(*) FROM wp_irn_replies')===1,'deduplicate webhook retries');
$wpdb->fail=true;check(irn_twilio_inbound(incoming('hello',2))->data['status']===503,'return retryable error on storage failure');$wpdb->fail=false;
$allowed=false;check(is_wp_error(irn_queue_reply(1,'Hello',key_for(1))),'only administrators can reply');$allowed=true;
check(is_wp_error(irn_queue_reply(999,'Hello',key_for(1))),'cannot send to an arbitrary destination');
check(is_wp_error(irn_queue_reply(1,' ',key_for(1))),'reject empty reply');
check(is_wp_error(irn_queue_reply(1,str_repeat('a',1601),key_for(1))),'reject oversized reply');
check(!is_wp_error(irn_queue_reply(1,'Thanks for listening!',key_for(1))),'queue admin reply');
check(count($calls)===0,'queueing does not call provider');
irn_queue_reply(1,'Thanks for listening!',key_for(1));
check($wpdb->get_var('SELECT COUNT(*) FROM wp_irn_outbox')===1,'duplicate submission adds no second message');
irn_queue_reply(1,'See you at the show!',key_for(2));
check($wpdb->get_var('SELECT COUNT(*) FROM wp_irn_outbox')===2,'independent replies do not collide with outbox uniqueness');
$wpdb->query("INSERT INTO wp_irn_subscribers VALUES(1,'sms','+15557654321')");
irn_twilio_inbound(incoming('STOP',3,'STOP'));
check(irn_sms_blocked('+15557654321')&&$wpdb->get_var('SELECT COUNT(*) FROM wp_irn_subscribers')===0,'STOP blocks replies and removes subscriber');
check(is_wp_error(irn_queue_reply(1,'Hello',key_for(3))),'STOP prevents new admin reply');
irn_run_worker();
check(count($calls)===0&&$wpdb->get_var("SELECT COUNT(*) FROM wp_irn_outbox WHERE state='failed'")===2,'STOP blocks replies already waiting in queue');
irn_twilio_inbound(incoming('START',4,'START'));
check(!irn_sms_blocked('+15557654321'),'START unblocks personal replies');
check($wpdb->get_var('SELECT COUNT(*) FROM wp_irn_subscribers')===0,'START does not silently resubscribe to announcements');
irn_queue_reply(1,'Welcome back',key_for(4));
$response=['status'=>401,'body'=>['code'=>20003,'message'=>'Authentication failed']];irn_run_worker();
check(irn_paused()&&$wpdb->get_var("SELECT attempts FROM wp_irn_outbox WHERE state='pending'")===0,'account error pauses queue without consuming attempt');
$response=['status'=>201,'body'=>['sid'=>'SM'.str_repeat('b',32)]];irn_resume();irn_run_worker();
check($wpdb->get_var("SELECT COUNT(*) FROM wp_irn_outbox WHERE state='submitted'")===1,'worker submits resumed reply');
check(end($calls)[1]['body']['From']==='+15551234567','reply comes from original receiving number');
$n=count($calls);irn_run_worker();check(count($calls)===$n,'submitted messages never send again');
check(irn_classify_twilio(['status'=>201,'body'=>[]])['status']==='retry','missing provider ID is not accepted as success');
check(irn_classify_twilio(['status'=>400,'body'=>['code'=>21610]])['status']==='unsubscribed','provider opt-out is terminal');
check(irn_classify_twilio(['status'=>429,'body'=>[]])['status']==='retry','rate limit is retryable');
check(irn_classify_twilio(['status'=>400,'body'=>['code'=>21608]])['status']==='fail','trial restriction is a failure');
$options['irn_settings']['sms_from']='invalid';check(count(irn_sms_setup_issues())===1,'settings identify invalid sender');
$wrong=incoming('wrong account',8);$wrong->params['AccountSid']='AC'.str_repeat('f',32);$wrong->signature=irn_twilio_signature(irn_twilio_webhook_url(),$wrong->params,irn_setting('twilio_token'));
check(irn_twilio_inbound($wrong)->data['status']===400,'reject message from a different account');
$options['irn_settings']['sms_from']='+15551234567';
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function esc_attr($s){return esc_html($s);}
function esc_textarea($s){return esc_html($s);}
function esc_url($s){return esc_html($s);}
function irn_admin_url($tab,$args=[]){return 'https://example.test/admin?'.http_build_query(['tab'=>$tab]+$args);}
function irn_local_time($s){return $s;}
function admin_url($s){return 'https://example.test/'.$s;}
function wp_nonce_field($s){echo '<input name="_wpnonce" value="test">';}
function wp_create_nonce($s){return 'nonce';}
function wp_generate_uuid4(){return key_for(99);}
function get_transient($s){return false;}
$wpdb->query("UPDATE wp_irn_replies SET body='<script>alert(1)</script>' WHERE id=1");
ob_start();irn_render_conversation(1);$html=ob_get_clean();
check(str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>'),'conversation safely escapes listener text');
check(str_contains($html,'Send reply')&&str_contains($html,'Accepted by Twilio')&&str_contains($html,'request_signature'),'conversation renders composer and honest submission status');
echo "Messaging tests complete.\n";
