<?php
include_once 'lib/DB.php';
include_once 'lib/helper.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';
include_once 'lib/config.php';

$fname = getUserMetaQuery("p.student_id", "first_name", "student_first_name");
$lname = getUserMetaQuery("p.student_id", "last_name", "student_last_name");

define("STATUS_RESCHEDULE", "1b_RESCHEDULE");
define("STATUS_APPROVED", "2_Approved");
define("STATUS_REJECTED", "3_Rejected");


// only consider appointment time that is later that current time (offset by 1 hour)
$now = time();
$offsetUnix = 60 * 60;
$minimumTimestamp = $now - $offsetUnix;

$DB = new DB();
$queryKeyId = createKeyId("p.ID", "com_email.email", "p.status", true);

$q = "select  p.ID
    , (select e.ID from send_emails e where e.key_id like 
        CONCAT('company_notification_' , p.ID , '_' , com_email.email , '_%')
    ) as is_update
    , com_email.email as company_email
    , (select c.name from companies c where c.ID = p.company_id) as company_name
    , $fname
    , $lname
    , p.company_id
    , p.student_id
    , p.appointment_time
    , p.reschedule_time
    , p.status

    FROM pre_screens p INNER JOIN company_emails com_email ON p.company_id = com_email.company_id
    LEFT OUTER JOIN send_emails e on e.key_id = $queryKeyId

    where 1=1
    and p.appointment_time >= $minimumTimestamp
    and p.status in (
        '".STATUS_RESCHEDULE."','".STATUS_APPROVED."', '".STATUS_REJECTED."'
    )
    and e.status IS NULL ";

$data = $DB->query_array($q);
// X($q);
// X($data);
// exit();

foreach ($data as $d) {
    $isUpdate = $d["is_update"] > 0 ? true : false;
    $status = $d["status"];
    $to = $d["company_email"];
    $studentName = $d["student_first_name"] . " " . $d["student_last_name"];
    $companyName = $d["company_name"];
    
    $studentAction = "";
    if ($status == STATUS_RESCHEDULE) {
        $studentAction = "has requested for reschedule";
    }
    if ($status == STATUS_APPROVED) {
        $studentAction = "has accepted the scheduled call";
    }
    if ($status == STATUS_REJECTED) {
        $studentAction = "has rejected the scheduled call";
    }

    $title = "{$studentName} {$studentAction}";
    $body = createSIEmail($studentName, $studentAction, $d, $isUpdate);

    // debug
    // $to = "zulsarhan.shaari@gmail.com";

    $res = sendMail($title, $body, $to, $name, true);

    $keyId = createKeyId($d["ID"], $to, $d["status"]);

    // debug - return untuk skip masuk dalam function
    //return;

    if (!$isUpdate) {
        SendEmail::insert($keyId, "company_notification", json_encode($d), $res, $DB);
    } else {
        SendEmail::update($d["is_update"], $keyId, json_encode($d), $res, $DB);
    }
}

////////////////////////////////////////////////////////////////////////////////////////
// function starts

// function createBtnHtml($url, $text, $color)
// {
//     $style = "";
//     return "<a href='$url' style='$style'>$text</a>";
// }
// echo createBtnHtml(createActionLink(ACCEPT_INTERVIEW), "Accept Interview", "#2d8f2d");
// echo "<br>";
// echo "<br>";
// echo "<br>";
// echo createBtnHtml(createActionLink(REJECT_INTERVIEW), "Reject Interview", "#d72a2a");

// exit();

function createKeyId($id, $to_email, $status, $in_query = false)
{
    if ($in_query) {
        return "CONCAT('company_notification_' , $id , '_', $to_email , '_', $status )";
    } else {
        return "company_notification_{$id}_{$to_email}_$status";
    }
}

function createSIEmail($studentName, $studentAction, $d, $isUpdate)
{
    $companyName = $d["company_name"];
    $status = $d["status"];
    //$timezone = "NZT";
    
    $timezone = "MYT";
    
    
    //$urlConvert = "http://www.convert-unix-time.com/?t=" . $d["appointment_time"];
    $dateStr = SendEmail::getTimeByTimezone($d["appointment_time"], $timezone);
    $urlConvert = CONVERT_UNIX_URL . $d["appointment_time"];

    $rescheduleStr = "";
    $urlConvertReschedule = "";
    
    if ($status == STATUS_RESCHEDULE && $d["reschedule_time"] != "" && $d["reschedule_time"] != null) {
        $rescheduleStr = SendEmail::getTimeByTimezone($d["reschedule_time"], $timezone);
        $urlConvertReschedule = CONVERT_UNIX_URL . $d["reschedule_time"];
    }
    

    ob_clean();
    ob_start(); ?>
        <span>
            <i>Hi <?= $companyName ?>,</i>
            <br><br>
            <b><?=$studentName ?></b> <?= $studentAction ?>.
            <br><br>
            
            <?= $rescheduleStr != "" ? "Original" : "" ?> Call Time : <b><?=$dateStr ?></b>
            <br><a href="<?=$urlConvert ?>" target="_blank">Convert to your local time</a>
            <br><br>
            
            <?php if($rescheduleStr != "") { ?>
                Requested Schedule Time : <b><?=$rescheduleStr ?></b>
                <br><a href="<?=$urlConvertReschedule ?>" target="_blank">Convert to your local time</a>
                <br><br>
            <?php } ?>

            Access call by logging in at <a href="<?=APP_URL ?>"><?=APP_NAME ?></a>.
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
?>