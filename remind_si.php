<?php
include_once 'lib/DB.php';
include_once 'lib/helper.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';
include_once 'lib/config.php';

define("IS_PROD", true);
define("REMIND_MINUTE", 30);
define("SMS_TYPE", "INTERVIEW_REMINDER");

if(!IS_PROD){
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 1000');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');  
}

$now = time();
$offsetUnix = 15 * 60;
$hourStart = $now + (REMIND_MINUTE * 60) - $offsetUnix;
$hourEnd =  $now + ((REMIND_MINUTE * 2) * 60) + $offsetUnix;

// echo SendEmail::getTimeByTimezone($hourStart, "MYT");
// echo "<br>";
// echo SendEmail::getTimeByTimezone($hourEnd, "MYT");
// echo "<br>";

// get all group_session join that are not in email sent yet
$DB = new DB();
$queryKeyId = createKeyId("p.ID", "p.appointment_time", true);
$STATUS_APPROVED = "2_Approved";

$q = "select  p.*
   
    from pre_screens p
    left outer join send_emails e on e.key_id = $queryKeyId
    
    where 1=1  
    and e.status IS NULL 
    and p.status = '$STATUS_APPROVED' 
    and p.appointment_time >= $hourStart and p.appointment_time <= $hourEnd";

//X($q);

$data = $DB->query_array($q);

// X($data);
// exit();

foreach ($data as $d) {
    sendSms($d["student_id"], SMS_TYPE, $d);
    $keyId = createKeyId($d["ID"], $d["appointment_time"]);
    SendEmail::insert($keyId, "remind_scheduled_interview", json_encode($d), "SMS", $DB);
}

function sendSms($user_id, $type, $d){
    $RootUrl = (IS_PROD) ? "https://seedsjobfairapp.com/cf" : "http://localhost:4000";
    $url = $RootUrl . "/nexmo/send-sms";
 
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
    // X($fields_string);

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

    // echo "<hr>";
    // echo $result;
}

function createKeyId($id, $appointment_time, $in_query = false) {
    if ($in_query) {
        return "CONCAT('remind_si_' , $id, '_', $appointment_time)";
    } else {
        return "remind_si_{$id}_{$appointment_time}";
    }
}

?>