<?php
include_once 'lib/DB.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';

define("APP_URL", "http://seedsjobfair.com/cf/app");
define("APP_NAME", "SeedsJobFair.com");

// get all Approved prescreens that are not in email sent yet
$DB = new DB();

$q = "select  p.ID
    ,(select u.user_email from wp_cf_users u where u.ID = p.student_id) as email
    ,(select m.meta_value from wp_cf_usermeta m where m.user_id = p.student_id and m.meta_key = 'first_name' ) as first_name
    ,(select m.meta_value from wp_cf_usermeta m where m.user_id = p.student_id and m.meta_key = 'last_name' ) as last_name
    ,(select c.name from companies c where c.ID = p.company_id) as company
    ,p.appointment_time 
    
    from pre_screens p
    left outer join send_emails e on e.entity = 'pre_screens' and e.entity_id = p.ID
    
    where p.status = 'Approved' 
    and e.status IS NULL ";

$data = $DB->query_array($q);

foreach ($data as $d) {
    $body = createSIEmail($d);
    $to = $d["email"];
    $name = $d["first_name"] . " " . $d["last_name"];
    $title = "Scheduled Interview With {$d["company"]}";

    // debug
    $to = "zulsarhan.shaari@gmail.com";
    $res = sendMail($title, $body, $to, $name, true);
    SendEmail::insert("scheduled_interview", "pre_screens", $d["ID"], json_encode($d), $res, $DB);
    return;
}

function createSIEmail($d) {
    $name = $d["first_name"] . " " . $d["last_name"];
    $dateEst = SendEmail::getTimeByTimezone($d["appointment_time"], 'EST');
    ob_clean();
    ob_start();
    ?>
    <span>
        <i>Dear <?= $name ?>,</i>
        <br>
        <h3>Congratulations!</h3>
        You have a scheduled interview with <b><?= $d["company"] ?></b> on <u><?= $dateEst ?></u>
        <br><br>
        For further details visit <b><a href="<?= APP_URL ?>"><?= APP_NAME ?></a></b>
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