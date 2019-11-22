<?php
define("IS_PROD", true);

if(!IS_PROD){
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 1000');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');  
}
 
// delete room daily co
// masuk dalam table baru, yang dh deleted 
//     ps_daily_co_deleted {ID, pre_screens_id, room_name, created_at}
// update prescreen status kepada 4_Ended
include_once '../lib/DB.php';
$ENDED_STATUS = "4_Ended";
$OFFSET_HOUR = 24;
$DB = new DB();

// ##################################
// 1. get data pre screens yang appoiment time dia 
// kurang daripada <OFFSET_HOUR> jam lepas
$currentUnix = time() ;
$currentUnix -= $OFFSET_HOUR * 60 * 60;
$_where = isset($_POST["ID"]) 
    ? "p.ID = $_POST[ID]" 
    : " p.appointment_time <= $currentUnix AND p.join_url LIKE '%seedsjobfair.daily.co%' ";

$q = "SELECT p.* FROM pre_screens p WHERE 1=1
    AND p.status != '$ENDED_STATUS' 
    AND $_where";

$data = $DB->query_array($q);

// ##################################
// 2. create insert ps_daily_co_deletedstatement
// 3. create update pre_screens statement
function sqlPreScreensUpdateStatus($id){
    global $ENDED_STATUS;
    $ret = " UPDATE pre_screens SET status = '$ENDED_STATUS' WHERE ID = $id; ";
    return $ret;
}

function deleteRequestToDailyCo($name){
    //The url you wish to send the POST request to
    $RootUrl = (IS_PROD) ? "https://seedsjobfairapp.com/cf" : "http://localhost:4000";
    $url = $RootUrl . "/daily-co/delete-room";
    //X($url);

    //The data you want to send via POST
    $fields = [
        'name' => $name
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
    $isSucess = true;

    if(json_decode($result) == null){
        $isSucess = false;
    } 

    if($result == "not-found"){
        $isSucess = true;
    }
    return $isSucess;
}

$sqlInsertToDelete = "INSERT INTO ps_daily_co_deleted (pre_screens_id, room_name) VALUES ";
$sqlUpdateStatus = "";
$doExec = false;
foreach ($data as $d) {
    $doExec = true;
    $url = $d["join_url"];
    $pre_screens_id = $d["ID"];
    $appointment_time = $d["appointment_time"];

    $arr = explode("daily.co/",$url);
    $room_name = $arr[1];
    
    $isSucess = deleteRequestToDailyCo($room_name);
    if($isSucess){
        $sqlInsertToDelete .= " ('$pre_screens_id','$room_name'), ";
        $sqlUpdateStatus .= sqlPreScreensUpdateStatus($pre_screens_id);
    }
}

$sqlInsertToDelete = trim($sqlInsertToDelete,", ");

// ##################################
// 4. execute insert ps_daily_co_deleted statement
// 5. execute update pre_screens statement
$result = "";
if($doExec){
    // X($sqlInsertToDelete);
    // X($sqlUpdateStatus);
    // multi query kena buat last
    $DB->query($sqlInsertToDelete);
    $DB->multi_query($sqlUpdateStatus);
    $DB->close();
    $result = "success";
}else{
    $result = "Session Not Found";
}

echo $result;

?>
