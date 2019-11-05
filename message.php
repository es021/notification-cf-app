<?php

include_once 'lib/DB.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';
include_once 'lib/config.php';

// send email notification to STUDENT only

function createKeyId($id, $in_query = false)
{
    if ($in_query) {
        return "CONCAT('message_count_' , $id)";
    } else {
        return "message_count_{$id}";
    }
}

$DB = new DB();
$queryKeyId = createKeyId("mc.ID", true);
$q = " 
    SELECT 
        mc.id, 
        m.id_message_number,
        m.from_user_id,
        m.message,
        ss.support_id

    FROM message_count mc inner join messages m on m.id_message_number = CONCAT(mc.id, ':1')
        left outer join send_emails e on e.key_id = $queryKeyId 
        left outer join support_sessions ss  on ss.message_count_id = mc.id

    WHERE 1=1 
        and mc.created_at >= '2018-11-01 00:00:00'
        and e.status IS NULL
";
$data = $DB->query_array($q);



foreach ($data as $d) {
    $isSenderSupportOrCompany = $d["support_id"] == $d["from_user_id"];
    $message = $d["message"];
    $support_id = $d["support_id"];

    // from info
    $fromId = $d["from_user_id"];
    $fromEntity =  $isSenderSupportOrCompany ? "company" : "user";
    $fromKey = $fromEntity.$fromId;
    $fromQuery = getQueryEntity($fromEntity, $fromId, "from");

    $id = $d["id"];
    $arrId = explode(":", $id);
    $toIndex = $fromKey == $arrId[0] ? 1 : 0;
    
    // to info
    $toKey = $arrId[$toIndex];
    $toEntity = strpos($toKey, 'company') !== false ? 'company' : 'user';
    $toId = str_replace($toEntity,"",$toKey);
    $toQuery = getQueryEntity($toEntity, $toId, "to");

    // akan skip sume message yang dihantar ke company or support
    if($support_id == $toId){
        continue;
    }

    $entityData = $DB->query_array($fromQuery." UNION ALL ".$toQuery);
    X($d);
    X($entityData);

    $fromData = $entityData[0];
    $toData = $entityData[1];

    X($fromData);
    X($toData);
    // X("fromKey ".$fromKey." | "."fromEntity ".$fromEntity." | "."fromId ".$fromId);
    // X("toKey ".$toKey." | "."toEntity ".$toEntity." | "."toId ".$toId);
    X("--------------------------------------------------------------");
    $keyId = createKeyId($id);

    $to = $toData["email"];
    $name = $toData["name"];

    $title = "You've got new message from {$fromData["name"]}";
   
    // debug
    $to = "zulsarhan.shaari@gmail.com";

    $body = createMessageNotificationEmail($fromData, $toData, $message);
    $res = sendMail($title, $body, $to, $name, true);
    SendEmail::insert($keyId, "new_message", json_encode($d), $res, $DB);
}


////////////////////////////////////////////////////////////////////////////////////////
// function starts
function getQueryEntity($entity, $entity_id, $type){
    if($entity == "user"){
        return " select
        '$entity' as entity,
        '$type' as type,
        u.ID as ID,
        u.user_email as email,
        (select s.val from single_input s where s.entity = 'user' and s.entity_id = u.ID and s.key_input = 'first_name') as name,
        (select m.meta_value from wp_cf_usermeta m where m.user_id = u.ID and m.meta_key = 'reg_profile_image_url') as img_url,
        (select m.meta_value from wp_cf_usermeta m where m.user_id = u.ID and m.meta_key = 'profile_image_position') as img_pos,
        (select m.meta_value from wp_cf_usermeta m where m.user_id = u.ID and m.meta_key = 'profile_image_size') as img_size
        from wp_cf_users u
        where u.ID = $entity_id ";
    } else if($entity == "company"){
        return "select 
            '$entity' as entity,
            '$type' as type,
            c.ID as ID,
            '' as email,
            c.name as name,
            c.img_url as img_url,
            c.img_position as img_pos,
            c.img_size as img_size
            from companies c
            where c.ID = $entity_id
        ";
    }
}

function createMessageNotificationEmail($fromData, $toData, $message)
{
    ob_clean();
    ob_start();

    ?>
        <span>
            <i>Hi <?= $toData["name"] ?>,</i>
            <br><br>
            You've got new message from <?= $fromData["name"] ?>
            <br>
            <b style="font-size:20px;">"<?= $message ?>"</b>
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