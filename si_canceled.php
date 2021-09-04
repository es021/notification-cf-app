<?php
include_once 'lib/DB.php';
include_once 'lib/helper.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';
include_once 'lib/config.php';

$fname = getUserMetaQuery("p.student_id","first_name");
$lname = getUserMetaQuery("p.student_id","last_name");
$DB = new DB();
$queryKeyId = createKeyId("p.ID", "p.appointment_time", true);
$q = "select  p.ID
    , (select e.ID from send_emails e where e.key_id like CONCAT('prescreens_' , p.ID , '_%')) as is_update
    , (select u.user_email from wp_cf_users u where u.ID = p.student_id) as email
    , $fname
    , $lname
    , (select c.name from companies c where c.ID = p.company_id) as company
    , p.company_id
    , p.student_id
    , p.appointment_time

    from pre_screens p
    left outer join send_emails e on e.key_id = $queryKeyId

    where p.status in ('5_Cancel')
    and e.status IS NULL ";

$data = $DB->query_array($q);

// X($q);
// X($data);
// // exit();

foreach ($data as $d) {
    $body = createSIEmail($d);
    $to = $d["email"];
    $name = $d["first_name"] . " " . $d["last_name"];
    $title = "Your call with {$d["company"]} has been canceled";

    // debug
    // $to = "zulsarhan.shaari@gmail.com";

    $res = sendMail($title, $body, $to, $name, true);

    $keyId = createKeyId($d["ID"], $d["appointment_time"]);

    // debug - return untuk skip masuk dalam function
    //return;

    SendEmail::insert($keyId, "scheduled_session", json_encode($d), $res, $DB);
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
// echo createBtnHtml(createActionLink(ACCEPT_INTERVIEW), "Accept Interview", "#2d8f2d");
// echo "<br>";
// echo "<br>";
// echo "<br>";
// echo createBtnHtml(createActionLink(REJECT_INTERVIEW), "Reject Interview", "#d72a2a");

// exit();

function createKeyId($id, $appointment_time, $in_query = false)
{
    if ($in_query) {
        return "CONCAT('prescreens_cancel_' , $id , '_', $appointment_time )";
    } else {
        return "prescreens_cancel_{$id}_$appointment_time";
    }
}

function createSIEmail($d)
{

    $name = $d["first_name"] . " " . $d["last_name"];
    //$timezone = "NZT";
    $timezone = "MYT";
    $dateStr = SendEmail::getTimeByTimezone($d["appointment_time"], $timezone);

    //$urlConvert = "http://www.convert-unix-time.com/?t=" . $d["appointment_time"];
    //$urlConvert = CONVERT_UNIX_URL . $d["appointment_time"];

    ob_clean();
    ob_start();

    ?>
        <span>
            <i>Hi <?=$name ?>,</i>
            <br><br>
            We are sorry to inform you that your call with <b><?=$d["company"] ?></b> on <b><?=$dateStr ?></b> has been canceled by the recruiter.
            <br>
            <br>
            View more details by logging in at <a href="<?=APP_URL ?>"><?=APP_NAME ?></a>. 
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