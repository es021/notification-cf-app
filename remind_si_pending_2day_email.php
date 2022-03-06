<?php
include_once 'lib/DB.php';
include_once 'lib/helper.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';
include_once 'lib/config.php';

define("IS_PROD", true);

define("ACCEPT_INTERVIEW", "acceptInterview");
define("REJECT_INTERVIEW", "rejectInterview");
define("REMIND_MINUTE", 48 * 60);
define("REMINDER_TYPE","remind_si_pending_2day_email");

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
$fname = getUserMetaQuery("p.student_id","first_name");
$lname = getUserMetaQuery("p.student_id","last_name");

$q = "
SELECT  
    p.* , 
    (select u.user_email from wp_cf_users u where u.ID = p.student_id) as email ,
    $fname ,
    $lname ,
    (select c.name from companies c where c.ID = p.company_id) as company ,
    (select cm.cf from cf_map cm where cm.entity = 'user' 
        and cm.entity_id = p.student_id order by cm.created_at desc limit 0,1) as cf
    
FROM pre_screens p
    left outer join send_emails e on e.key_id = $queryKeyId
    
WHERE 1=1  
    and e.status IS NULL 
    and p.status = '$STATUS_WAITING' 
    and p.appointment_time >= $hourStart and p.appointment_time <= $hourEnd";

    
$data = $DB->query_array($q);
    
// X($q);
// X($data);


// SEND EMAIL
foreach ($data as $d) {
    $body = createEmailBody($d);
    $to = $d["email"];
    $name = $d["first_name"] . " " . $d["last_name"];
    $title = "[Reminder] Please response to request request for interview from {$d["company"]}";
    
    // debug
    // $to = "zulsarhan.shaari@gmail.com";
    // debug

    $res = sendMail($title, $body, $to, $name, true);
    $keyId = createKeyId($d["ID"], $d["appointment_time"]);
    
    // debug - return untuk skip masuk dalam function
    // return;

    SendEmail::insert($keyId, REMINDER_TYPE, json_encode($d), $res, $DB);
}


function createActionLink($action, $studentId, $interviewId, $companyId)
{
    $param = array(
        "studentId" => (int) $studentId,
        "interviewId" => (int) $interviewId,
        "companyId" => (int) $companyId
    );

    $paramStr = json_encode($param);

    $url = APP_AUTH_URL . "/external-action/$action/$paramStr";
    return $url;
}

function createBtnHtml($url, $text, $color)
{
    $style = "";
    // $style .= "border: 1px $color black;";
    // $style .= "text-decoration : none;";
    // $style .= "padding : 10px 15px;";
    // $style .= "background : $color;";
    // $style .= "color : white;";
    // $style .= "border-radius: 10px;";
    // $style .= "margin : 10px; 15px;";
    // $style .= "";
    // $style .= "";

    return "<a href='$url' style='$style'>$text</a>";
}

function createEmailBody($d)
{

    $name = $d["first_name"] . " " . $d["last_name"];
    //$timezone = "NZT";
    $timezone = "MYT";
    $dateStr = SendEmail::getTimeByTimezone($d["appointment_time"], $timezone);

    //$urlConvert = "http://www.convert-unix-time.com/?t=" . $d["appointment_time"];
    $urlConvert = CONVERT_UNIX_URL . $d["appointment_time"];

    ob_clean();
    ob_start();

    $acceptLink = createActionLink(ACCEPT_INTERVIEW, $d["student_id"], $d["ID"], $d["company_id"]);
    $acceptBtn = createBtnHtml($acceptLink, "Accept Call", "#2d8f2d");

    $rejectLink = createActionLink(REJECT_INTERVIEW, $d["student_id"], $d["ID"], $d["company_id"]);
    $rejectBtn = createBtnHtml($rejectLink, "Reject Call", "#d72a2a");

    //or you would like to cancel this call
    //Please reply to this email if this time doesn't work.

    ?>
        <span>
            <i>Hi <?=$name ?>,</i>
            <br><br>
            This is a reminder that recruiter from <b><?=$d["company"] ?></b> has scheduled a call with you on
            <br><b><?=$dateStr ?></b>.
            <br><a href="<?=$urlConvert ?>" target="_blank">Convert to your local time</a>
            <br><br>
            Kindly respond by clicking on one of the followings :
            <br>
            <b><?=$acceptBtn ?></b>
            <?="    |    " ?>
            <b><?=$rejectBtn ?></b>
            <br><br>
            Access call by logging in at <a href="<?=APP_URL ?>"><?=APP_NAME ?></a>. Good luck!
            <br><br>
            <i>Regards,</i>
            <br>
            Seeds Job Fair
        </span>
    <?php

    $output_string = ob_get_contents();
    ob_end_clean();
    return $output_string;
}

function createKeyId($id, $appointment_time, $in_query = false) {
    if ($in_query) {
        return "CONCAT('".REMINDER_TYPE."_' , $id, '_', $appointment_time)";
    } else {
        return REMINDER_TYPE."_{$id}_{$appointment_time}";
    }
}

?>