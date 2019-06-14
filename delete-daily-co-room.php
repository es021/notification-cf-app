<?php

// delete room daily co
// masuk dalam table baru, yang dh deleted 
//     ps_daily_co_deleted {ID, pre_screens_id, room_name, created_at}
// update prescreen status kepada 4_Ended

include_once 'lib/DB.php';

$DB = new DB();
$q = "SELECT p.* FROM pre_screens p WHERE p.join_url LIKE '%seedsjobfair.daily.co%' ";
$data = $DB->query_array($q);

foreach ($data as $d) {
    $url = $d["join_url"];
    $id = $d["ID"];
    $appointment_time = $d["appointment_time"];

    $arr = explode("daily.co/",$url);
    $roomName = $arr[1];
    X($roomName);
}

$sql = "INSERT INTO ps_daily_co_deleted () VALUES ()";

?>
