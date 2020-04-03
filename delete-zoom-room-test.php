<?php
define("ROOT_URL",  "https://seedsjobfairapp.com/cf");
// define("ROOT_URL", "http://localhost:4000");

//  define("IS_PROD", false);
// if(!IS_PROD){
//     header('Access-Control-Allow-Origin: *');
//     header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
//     header('Access-Control-Max-Age: 1000');
//     header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');  
// }
 
// 1. check is zoom expired
// 2. kalau expired, 
//      a. update status pre_screen to 4_Ended
//      b. update zoom_meeting => 
//             auto_expired_at = <current unix timestamp>
//             is_expired = "1"
//     ps_daily_co_deleted {ID, pre_screens_id, room_name, created_at}
// update prescreen status kepada 4_Ended
include_once 'lib/DB.php';
$ENDED_STATUS = "4_Ended";
$STARTED_STATUS = "0_Started";
$DB = new DB();

// ##################################
function isZoomExpired($data){
    //The url you wish to send the POST request to
    // $RootUrl = (IS_PROD) ? "https://seedsjobfairapp.com/cf" : "http://localhost:4000";
    $url = ROOT_URL . "/zoom/is-expired";
    //X($url);

    //The data you want to send via POST
    $fields = [
        'join_url' => $data["join_url"]
    ];

    //url-ify the data for the POST
    $fields_string = http_build_query($fields);

    //open connection
    $ch = curl_init();

    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_POST, true);
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

    //So that curl_exec returns the contents of the cURL; rather than echoing it
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 

    //execute post
    $result = curl_exec($ch);
    $result = json_decode($result);
    
    if($result == null){
        return false;
    } else {
        return $result->is_expired == "1";
    }
}



// ##################################
// 1. get data pre screens yang appoiment time dia [$OFFSET_MIN] minit lepas
//      - zoom_created_24_hours_ago indicator zoom meeting was created passed 24 hours or not
$OFFSET_MIN = 30;
$offsetAgoUnix = time() - ($OFFSET_MIN * 60);
// SELECT * FROM table WHERE DATE(created_at) = DATE(NOW() - INTERVAL 1 DAY);
$q = "SELECT 
    (CASE WHEN zm.created_at < (NOW() - INTERVAL 24 HOUR) THEN '1' ELSE '0' END) as zoom_created_24_hours_ago,
    p.* 
    FROM pre_screens p, zoom_meetings zm 
    WHERE 1=1
    AND zm.pre_screen_id = p.ID
    AND zm.join_url = p.join_url
    AND p.status = '$STARTED_STATUS' 
    AND p.appointment_time <= $offsetAgoUnix 
    AND p.join_url LIKE '%zoom.us%' ";

$data = $DB->query_array($q);
$total = count($data);

// X($q);
// X($data);

$sqlUpdate = "";
$countExpired = 0;

foreach ($data as $d) {
    $join_url = $d["join_url"];
    $pre_screen_id = $d["ID"];
    $appointment_time = $d["appointment_time"];
    $zoom_created_24_hours_ago = $d["zoom_created_24_hours_ago"];
    $unixNow = time();
    
    if($zoom_created_24_hours_ago == "1" || isZoomExpired($d)){
        $countExpired ++;
        // X("expired suda");    
         $sqlUpdate .= " UPDATE zoom_meetings SET auto_expired_at = $unixNow, is_expired = '1' WHERE pre_screen_id = $pre_screen_id AND join_url = '$join_url'; ";
         $sqlUpdate .= " UPDATE pre_screens SET status = '$ENDED_STATUS', is_expired = '1' WHERE ID = $pre_screen_id; ";
    } else{
        // X("dok expired lagi");
    }

}

// X($sqlUpdate);
// exit();

// ##################################
$result = "";
if($countExpired > 0){
    //$DB->multi_query($sqlUpdate);
    //$DB->close(); 
    $result = "Successfully auto expired $countExpired interview(s)";
}else{
    $result = "No expired interview found";
}

echo "Total of ". $total ." interview(s) fetched.\n";
echo $result."\n";

?>
