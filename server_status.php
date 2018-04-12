<?php
//echo "Checking Server Status";
$res = shell_exec("service mysql status | grep running");

if(strpos($res,"Active: active (running)") == false){
    include_once './lib/EmailNotification.php';
    if($res == ""){
        $BODY = "Message Body Is Empty";
    }else{
        $BODY = $res;
    }
    sendMail("MySQL Is Down", $BODY, "zulsarhan.shaari@gmail.com", "Zul");
}

exit();


/*

  $date = ("Y/m/d - H:i:s");
  //send email notification
	$email_data = array(
                "to"=> "zulsarhan.shaari@gmail.com",
                "params"=>array(
                    "title" => "Mysql Down",
                    "content" => $res 
                ),
                "type"=> "CUSTOM_EMAIL"
            );

$params = array(
    "action" => "app_send_email",
    "data" => $email_data
);

$url ="https://seedsjobfair.com/career-fair/wp-admin/admin-ajax.php";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,$url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

// receive server response ...
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$server_output = curl_exec ($ch);

curl_close ($ch);
*/

?>

