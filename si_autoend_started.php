<?php
include_once 'lib/DB.php';
include_once 'lib/helper.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';
include_once 'lib/config.php';

define("IS_PROD", true);

if (!IS_PROD) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 1000');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

// $now = time();
// $offsetUnix = 15 * 60;
// $hourStart = $now - $offsetUnix;
// $hourEnd =  $now + $offsetUnix;

$DB = new DB();
$STATUS_STARTED = "0_Started";
$STATUS_ENDED = "4_Ended";

$q = "select  p.*,
FROM_UNIXTIME(p.appointment_time),
(NOW())
    from pre_screens p
    where 1=1  
    and p.status = '$STATUS_STARTED' 
    and p.join_url IS NOT NULL
    and p.start_url IS NOT NULL
    and (FROM_UNIXTIME(p.appointment_time) + INTERVAL 1 HOUR) <= NOW()
";

// and FROM_UNIXTIME(p.appointment_time) >= (NOW() )
//    and p.appointment_time >= $hourStart and p.appointment_time <= $hourEnd

    
$data = $DB->query_array($q);
// X($data);
// exit();
    
$qUpdate = "";
foreach ($data as $d) {
    // update status to ended
    $qUpdate .= "UPDATE pre_screens SET status = '$STATUS_ENDED', updated_by = 1 WHERE ID = {$d['ID']}; ";
}

if($qUpdate != ""){
    $DB->multi_query($qUpdate);
}

exit();
