<?php
include_once 'lib/DB.php';
include_once 'lib/helper.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';
include_once 'lib/config.php';

//define("APP_URL", "http://seedsjobfair.com/cf/app");
// define("APP_URL", "http://seedsjobfairapp.com/cf/app");
//define("APP_AUTH_URL", "http://localhost:8080/auth");
// define("APP_AUTH_URL", "http://seedsjobfairapp.com/cf/auth");
// define("APP_NAME", "SeedsJobFair.com");


// action email
define("ACCEPT_INTERVIEW", "acceptInterview");
define("REJECT_INTERVIEW", "rejectInterview");

// get all Approved prescreens that are not in email sent yet
// at the same time check is_update whether the same entity has been sent before


//, (select m.meta_value from wp_cf_usermeta m where m.user_id = p.student_id and m.meta_key = 'first_name' ) as first_name
//, (select m.meta_value from wp_cf_usermeta m where m.user_id = p.student_id and m.meta_key = 'last_name' ) as last_name
$fname = getUserMetaQuery("gu.user_id","first_name");
$lname = getUserMetaQuery("gu.user_id","last_name");
$DB = new DB();
$queryKeyId = createKeyId("g.ID", "gu.user_id", "g.appointment_time", "g.is_canceled", true);
$q = "select  
    g.ID
    , g.name as group_call_name
    , (select e.ID from send_emails e where e.key_id like CONCAT('group_call_' , g.ID , '_' , gu.user_id , '_%')) as is_update
    , g.is_canceled
    , g.company_id
    , g.appointment_time
    , gu.user_id
    , (select u.user_email from wp_cf_users u where u.ID = gu.user_id) as email
    , $fname
    , $lname
    , (select c.name from companies c where c.ID = g.company_id) as company

    FROM group_call g 
    INNER JOIN group_call_user gu ON g.ID = gu.group_call_id
    LEFT OUTER JOIN send_emails e on e.key_id = $queryKeyId

    WHERE 1=1
    and e.status IS NULL ";

// X($q);

$data = $DB->query_array($q);

// X($data);
// exit();

foreach ($data as $d) {
    $isUpdate = $d["is_update"] > 0 ? true : false;
    $isCanceled = $d["is_canceled"] == 1 || $d["is_canceled"] == "1";
    $body = createSIEmail($d, $isUpdate, $isCanceled);
    $to = $d["email"];
    $name = $d["first_name"] . " " . $d["last_name"];

    if($isCanceled){
        $title = "[Canceled] Group call with {$d["company"]} has been canceled";
    } else if($isUpdate){
        $title = "[Time Updated] Group Call with {$d["company"]}";
    } else{
        $title = "You have been invited to join a group call with {$d["company"]}";
    }
   
    // debug
    // $to = "zulsarhan.shaari@gmail.com";

    $res = sendMail($title, $body, $to, $name, true);

    $keyId = createKeyId($d["ID"], $d["user_id"], $d["appointment_time"], $d["is_canceled"]);

    // debug - return untuk skip masuk dalam function
    //return;

    if (!$isUpdate) {
        SendEmail::insert($keyId, "scheduled_session", json_encode($d), $res, $DB);
    } else {
        SendEmail::update($d["is_update"], $keyId, json_encode($d), $res, $DB);
    }

}

////////////////////////////////////////////////////////////////////////////////////////
// function starts

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
    return "<a href='$url' style='$style'>$text</a>";
}

function createKeyId($id, $user_id, $appmnt_time, $is_canceled, $in_query = false)
{
    if ($in_query) {
        return "CONCAT('group_call_' , $id , '_', $user_id, '_', $appmnt_time, '_', $is_canceled )";
    } else {
        return "group_call_{$id}_{$user_id}_{$appmnt_time}_{$is_canceled}";
    }
}

function createEmailBody($d, $isUpdate, $isCanceled){

    ob_clean();
    ob_start();

    //$timezone = "NZT";
    $timezone = "MYT";
    $dateStr = SendEmail::getTimeByTimezone($d["appointment_time"], $timezone);

    //$urlConvert = "http://www.convert-unix-time.com/?t=" . $d["appointment_time"];
    $urlConvert = CONVERT_UNIX_URL . $d["appointment_time"];

    $group_call_name = $d["group_call_name"];
    $company = $d["company"];

    // CALL CANCELED
    if($isCanceled) { 
    ?>
        <span>
            We are sorry to inform you that group call '<b><?=$group_call_name?></b>' with <b><?=$company?></b>
            has been canceled. 
        </span>
    <?php 
    } 
    // TIME UPDATED
    else if($isUpdate) { 
    ?>
        <span>
            Kindly be informed that the group call '<b><?=$group_call_name?></b>' with <b><?=$company?></b>
            has been rescheduled to the following time:<br>
            <b><?= $dateStr ?></b><br>
            <a href="<?=$urlConvert ?>" target="_blank">Convert to your local time</a>
        </span>
    <?php 
    } 
    // NEWLY ADDED
    else {
    ?>
        <span>
            Recruiter from <b><?=$company?></b> has invited you to join group call '<b><?=$group_call_name?></b>'.
            <br><br>
            The time of the call is as follows:<br>
            <b><?= $dateStr ?></b><br>
            <a href="<?=$urlConvert ?>" target="_blank">Convert to your local time</a>
        </span>
    <?php
    }

    $output_string = ob_get_contents();
    ob_end_clean();
    return $output_string;
}

function createSIEmail($d, $isUpdate, $isCanceled)
{
    $name = $d["first_name"] . " " . $d["last_name"];
   
    ob_clean();
    ob_start();

    ?>

        <span>
            <i>Hi <?=$name ?>,</i>
            <br><br>
            <?= createEmailBody($d, $isUpdate, $isCanceled) ?>
            <br><br>
            Access the call by logging in at <a href="<?=APP_URL ?>"><?=APP_NAME ?></a>.
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