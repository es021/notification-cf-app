<?php
include_once 'lib/DB.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';
include_once 'lib/config.php';

//define("APP_URL", "http://seedsjobfair.com/cf/app");
//define("APP_URL", "http://seedsjobfairapp.com/cf/app");
// define("APP_NAME", "SeedsJobFair.com");

define("REMIND_HOUR", 1);

$now = time();
$offsetUnix = 15 * 60;
$hourStart = $now + (REMIND_HOUR * 60 * 60) - $offsetUnix;
$hourEnd =  $now + ((REMIND_HOUR + 1) * 60 * 60) + $offsetUnix;

// get all group_session join that are not in email sent yet
$DB = new DB();
$queryKeyId = createKeyId("p.ID", "p.group_session_id", true);
$q = "select  p.ID
    , p.group_session_id
    , (select u.user_email from wp_cf_users u where u.ID = p.user_id) as email
    , (select m.meta_value from wp_cf_usermeta m where m.user_id = p.user_id and m.meta_key = 'first_name' ) as first_name
    , (select m.meta_value from wp_cf_usermeta m where m.user_id = p.user_id and m.meta_key = 'last_name' ) as last_name
    , (select c.name from companies c where c.ID = gs.company_id) as company
    , gs.start_time 
   
    from group_session gs,
    group_session_join p 
    left outer join send_emails e on e.key_id = $queryKeyId
    
    where p.is_canceled != 1 
    and gs.is_canceled != 1
    and gs.is_expired != 1
    and gs.ID = p.group_session_id
    and gs.start_time >= $hourStart and gs.start_time <= $hourEnd
    and e.status IS NULL ";

    X($q);

$data = $DB->query_array($q);

foreach ($data as $d) {
    $isUpdate = $d["is_update"] > 0 ? true : false;
    $body = createSIEmail($d, $isUpdate);
    $to = $d["email"];
    $name = $d["first_name"] . " " . $d["last_name"];

    $title = "[Reminder] Group Session with {$d["company"]}";
   
    // debug
    //$to = "zulsarhan.shaari@gmail.com";

    $res = sendMail($title, $body, $to, $name, true);
    $keyId = createKeyId($d["ID"], $d["group_session_id"]);
    SendEmail::insert($keyId, "remind_group_session", json_encode($d), $res, $DB);
}

function createKeyId($id, $gs_id, $in_query = false) {
    if ($in_query) {
        return "CONCAT('remind_gs_' , $id , '_', $gs_id )";
    } else {
        return "remind_gs_{$id}_$gs_id";
    }
}

function createSIEmail($d, $isUpdate) {
    $name = $d["first_name"] . " " . $d["last_name"];
    $timezone = "MYT";
    $dateStr = SendEmail::getTimeByTimezone($d["start_time"], $timezone);

    //$urlConvert = "http://www.convert-unix-time.com/?t=".$d["start_time"];
    $urlConvert = CONVERT_UNIX_URL.$d["start_time"];

    ob_clean();
    ob_start();

    ?>
        <span>
            <i>Hi <?= $name ?>,</i>
            <br><br>
            Your group session with <b><?= $d["company"] ?></b> will start soon at <b><?= $dateStr ?></b>
            <a href="<?= $urlConvert ?>" target="_blank">Convert to your local time</a>
            <br><br>
            Access group session video call by logging in at <b><a href="<?= APP_URL ?>"><?= APP_NAME ?></a></b>. Good luck!
            <br><br>
            <i>Regards,</i>
            <br>
            Innovaseeds Solutions
        </span>
    <?php
    $output_string = ob_get_contents();
    ob_end_clean();
    return $output_string;
}
?>