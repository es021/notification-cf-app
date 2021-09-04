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

    where p.status in ('2_Approved','1_Waiting')
    and e.status IS NULL ";

$data = $DB->query_array($q);

foreach ($data as $d) {
    $isUpdate = $d["is_update"] > 0 ? true : false;
    $body = createSIEmail($d, $isUpdate);
    $to = $d["email"];
    $name = $d["first_name"] . " " . $d["last_name"];

    if (!$isUpdate) {
        $title = "Scheduled Call with {$d["company"]}";
    } else {
        $title = "[Time Updated] Scheduled Call with {$d["company"]}";
    }

    // debug
    // $to = "zulsarhan.shaari@gmail.com";

    $res = sendMail($title, $body, $to, $name, true);

    $keyId = createKeyId($d["ID"], $d["appointment_time"]);

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

function createKeyId($id, $appmnt_time, $in_query = false)
{
    if ($in_query) {
        return "CONCAT('prescreens_' , $id , '_', $appmnt_time )";
    } else {
        return "prescreens_{$id}_$appmnt_time";
    }
}

function createSIEmail($d, $isUpdate)
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
            Recruiter from <b><?=$d["company"] ?></b> has <?=(!$isUpdate) ? "scheduled" : "rescheduled" ?> a call with you on
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
?>