<?php
include_once 'lib/DB.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';

define("APP_URL", "http://seedsjobfair.com/cf/app");
define("APP_NAME", "SeedsJobFair.com");

// get all Approved prescreens that are not in email sent yet
// at the same time check is_update whether the same entity has been sent before

$DB = new DB();
$queryKeyId = createKeyId("p.ID", "p.appointment_time", true);
$q = "select  p.ID
    , (select e.ID from send_emails e where e.key_id like CONCAT('prescreens_' , p.ID , '_%')) as is_update
    , (select u.user_email from wp_cf_users u where u.ID = p.student_id) as email
    , (select m.meta_value from wp_cf_usermeta m where m.user_id = p.student_id and m.meta_key = 'first_name' ) as first_name
    , (select m.meta_value from wp_cf_usermeta m where m.user_id = p.student_id and m.meta_key = 'last_name' ) as last_name
    , (select c.name from companies c where c.ID = p.company_id) as company
    , p.appointment_time 
   
    from pre_screens p
    left outer join send_emails e on e.key_id = $queryKeyId
    
    where p.status = 'Approved' 
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
    $to = "zulsarhan.shaari@gmail.com";


    $res = sendMail($title, $body, $to, $name, true);

    $keyId = createKeyId($d["ID"], $d["appointment_time"]);
    if (!$isUpdate) {
        SendEmail::insert($keyId, "scheduled_session", json_encode($d), $res, $DB);
    } else {
        SendEmail::update($d["is_update"], $keyId, json_encode($d), $res, $DB);
    }

}

function createKeyId($id, $appmnt_time, $in_query = false) {
    if ($in_query) {
        return "CONCAT('prescreens_' , $id , '_', $appmnt_time )";
    } else {
        return "prescreens_{$id}_$appmnt_time";
    }
}

function createSIEmail($d, $isUpdate) {
    $name = $d["first_name"] . " " . $d["last_name"];
    $timezone = "NZT";
    $dateLocal = SendEmail::getTimeByTimezone($d["appointment_time"], 'NZT');
    ob_clean();
    ob_start();

    if (!$isUpdate) {
        ?>
        <span>
            <i>Hi <?= $name ?>,</i>
            <br>
            Recruiter from <b><?= $d["company"] ?></b> has scheduled a call
            <br>with you on <u><?= $dateLocal ?> (<?= $timezone ?>)</u>.
            <br><br>
            Please reply to this email if this time doesn't work or you would like to cancel this call
            <br><br>
            Access call by logging in at <b><a href="<?= APP_URL ?>"><?= APP_NAME ?></a></b>. Good luck!
            <br><br>
            <i>Regards,</i>
            <br>
            Innovaseeds Solutions
        </span>
        <?php
    } else {
        ?>
       <span>
            <i>Hi <?= $name ?>,</i>
            <br>
            Recruiter from <b><?= $d["company"] ?></b> has rescheduled a call
            <br>with you on <u><?= $dateLocal ?> (<?= $timezone ?>)</u>.
            <br><br>
            Please reply to this email if this time doesn't work or you would like to cancel this call
            <br><br>
            Access call by logging in at <b><a href="<?= APP_URL ?>"><?= APP_NAME ?></a></b>. Good luck!
            <br><br>
            <i>Regards,</i>
            <br>
            Innovaseeds Solutions
        </span>
        <?php
    }
    $output_string = ob_get_contents();
    ob_end_clean();
    return $output_string;
}
?>