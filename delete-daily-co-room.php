<?php
include_once 'lib/DB.php';

$DB = new DB();
$q = "select p.* from pre_screens p where p.join_url like '%seedsjobfair.daily.co%' ";
$data = $DB->query_array($q);

foreach ($data as $d) {
    $url = $d["join_url"];
    $id = $d["ID"];
    $appointment_time = $d["appointment_time"];

    $arr = explode("daily.co/",$url);
    $roomName = $arr[1];
    X($roomName);
}

?>
