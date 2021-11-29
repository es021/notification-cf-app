<?php
include_once 'lib/DB.php';
include_once 'lib/helper.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';
include_once 'lib/config.php';

define("IS_PROD", true);
define("REMIND_MINUTE", 24 * 60);
define("SMS_TYPE", "INTERVIEW_PENDING_REMINDER_1DAY");

if(!IS_PROD){
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 1000');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');  
}

$now = time();
$offsetUnix = 15 * 60;
$hourStart = $now + (REMIND_MINUTE * 60) - $offsetUnix;
$hourEnd =  $hourStart + (60 * 2 * 60); // 2 hours after

// $hourEnd =  $now + ((REMIND_MINUTE * 2) * 60) + $offsetUnix;

// echo SendEmail::getTimeByTimezone($hourStart, "MYT");
// echo "<br>";
// echo SendEmail::getTimeByTimezone($hourEnd, "MYT");
// echo "<br>";

// get all group_session join that are not in email sent yet
$DB = new DB();
$queryKeyId = createKeyId("p.ID", "p.appointment_time", true);
$STATUS_WAITING = "1_Waiting";

$q = "select  p.*, 
    (select cm.cf from cf_map cm where cm.entity = 'user' and cm.entity_id = p.student_id order by cm.created_at desc limit 0,1) as cf
    from pre_screens p
    left outer join send_emails e on e.key_id = $queryKeyId
    
    where 1=1  
    and e.status IS NULL 
    and p.status = '$STATUS_WAITING' 
    and p.appointment_time >= $hourStart and p.appointment_time <= $hourEnd";

    
$data = $DB->query_array($q);
    
// X($q);
// X($data);

foreach ($data as $d) {
    sendSms($d["student_id"], SMS_TYPE, $d);
    $keyId = createKeyId($d["ID"], $d["appointment_time"]);
    SendEmail::insert($keyId, "remind_si_pending_1day", json_encode($d), "SMS", $DB);
}

function sendSms($user_id, $type, $d){
    // echo "<hr>";

    $RootUrl = (IS_PROD) ? "https://seedsjobfairapp.com/cf" : "http://localhost:4000";
    $url = $RootUrl . "/nexmo/send-sms";
 
    $d["appointment_time_str"] = SendEmail::getTimeByTimezone($d["appointment_time"], "MYT");

    //The data you want to send via POST
    $fields = [
        'user_id' => $user_id,
        'type' => $type,
        'param' => $d
    ];


    //url-ify the data for the POST
    $fields_string = http_build_query($fields);

    // X($url);
    // X($fields);

    //open connection
    $ch = curl_init();

    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_POST, true);
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

    //So that curl_exec returns the contents of the cURL; rather than echoing it
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    //execute post
    $result = curl_exec($ch);

    // X($result);
    // return;
}

function createKeyId($id, $appointment_time, $in_query = false) {
    if ($in_query) {
        return "CONCAT('remind_si_pending_1day_' , $id, '_', $appointment_time)";
    } else {
        return "remind_si_pending_1day_{$id}_{$appointment_time}";
    }
}

?>