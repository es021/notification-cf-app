<?php
// define("ROOT_URL",  "https://seedsjobfairapp.com/cf");
define("ROOT_URL", "http://localhost:4000");

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
include_once './lib/DB.php';
$ENDED_STATUS = "4_Ended";
$STARTED_STATUS = "0_Started";
$OFFSET_HOUR = 1;
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
// 1. get data pre screens yang appoiment time dia 
// kurang daripada <OFFSET_HOUR> jam lepas
$currentUnix = time() ;
$currentUnix -= $OFFSET_HOUR * 60 * 60;
$_where = isset($_POST["ID"]) 
    ? "p.ID = $_POST[ID]" 
    : " p.appointment_time <= $currentUnix AND p.join_url LIKE '%zoom.us%' ";

$q = "SELECT p.* FROM pre_screens p WHERE 1=1
    AND p.status = '$STARTED_STATUS' 
    AND $_where";

$data = $DB->query_array($q);

X($q);
X($data);

$sqlUpdate = "";
$countExpired = 0;

foreach ($data as $d) {
    $join_url = $d["join_url"];
    $pre_screen_id = $d["ID"];
    $appointment_time = $d["appointment_time"];
    $unixNow = time();
    // $arr = explode("daily.co/",$url);
    // $room_name = $arr[1];
    
    if(isZoomExpired($d)){
        $countExpired ++;
        // X("expired suda");    
         $sqlUpdate .= " UPDATE zoom_meetings SET auto_expired_at = $unixNow, is_expired = '1' WHERE pre_screen_id = $pre_screen_id AND join_url = '$join_url'; ";
         $sqlUpdate .= " UPDATE pre_screens SET status = '$ENDED_STATUS', is_expired = '1' WHERE ID = $pre_screen_id; ";
    }else{
        // X("dok expired lagi");
    }

}

// X($sqlUpdate);
// exit();

// ##################################
$result = "";
if($countExpired > 0){
    $DB->multi_query($sqlUpdate);
    $DB->close();
    $result = "successfully auto expired $countExpired interview(s)";
}else{
    $result = "no expired interview found";
}

echo $result;

?>
