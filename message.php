<?php

include_once 'lib/DB.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';
include_once 'lib/config.php';

// send email notification to STUDENT only
// masa query dia akan amik yang first message je
// so dia notify the starter of the message je

// define("APP_URL", "https://seedsjobfairapp.com/cf/app");
// define("ASSET_URL", "https://seedsjobfairapp.com/public/asset");
define("SUPPORT_USER_ID", 681);

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
        and mc.created_at >= '2019-11-01 00:00:00'
        and e.status IS NULL
";
$data = $DB->query_array($q);
// X($data);

function getCompanyEmails($DB, $company_id){
    $q = "
        SELECT email FROM company_emails WHERE company_id = $company_id
    ";
    $data = $DB->query_array($q);

    $ret = array();

    foreach($data as $d){
        array_push($ret, $d["email"]);
    }
    return $ret;
}

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
    $keyId = createKeyId($id);
    $arrId = explode(":", $id);
    $toIndex = $fromKey == $arrId[0] ? 1 : 0;
    
    // to info
    $toKey = $arrId[$toIndex];
    $toEntity = strpos($toKey, 'company') !== false ? 'company' : 'user';
    $toId = str_replace($toEntity,"",$toKey);
    $toQuery = getQueryEntity($toEntity, $toId, "to");

    // akan skip sume message yang dihantar ke support
    $isSendToCompany = false;
    if($support_id == $toId){
        $isSendToCompany = true;
        if($toId == SUPPORT_USER_ID){
            SendEmail::insert($keyId, "new_message", json_encode($d), "SKIP", $DB);
            continue;
        }
    }

    $entityData = $DB->query_array($fromQuery." UNION ALL ".$toQuery);
    // X($d);
    // X($entityData);

    $fromData = $entityData[0];
    $toData = $entityData[1];

    // X($fromData);
    // X($toData);
    // X("fromKey ".$fromKey." | "."fromEntity ".$fromEntity." | "."fromId ".$fromId);
    // X("toKey ".$toKey." | "."toEntity ".$toEntity." | "."toId ".$toId);
    // X("--------------------------------------------------------------");

    $name = $toData["name"];
    $title = "{$fromData["name"]} sent you a new message";
    
    // debug
    //$to = "zulsarhan.shaari@gmail.com";
    
    // get to emails
    
    if($isSendToCompany){
        $toEmails = getCompanyEmails($DB, $toId);
    } else{
        $toEmails = [$toData["email"]];
    }

    $res = "";
    foreach ($toEmails as $to) {
        $body = createMessageNotificationEmail($fromData, $toData, $message);
        $isHTML = true;
        $isTestSender = false;
        $from_name = "{$fromData["name"]} via SeedsJobFair";

        // echo $from_name;
        // echo "<hr>";
        // echo $title;
        // echo "<hr>";
        // echo $body;
    
        $res = sendMail($title, $body, $to, $name, $isHTML, $isTestSender, $from_name);
    }
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

function createViewMessageBtn()
{
    $url = APP_URL . "/my-inbox";
    return createBtnHtml("View Message", $url, "#20ae29");
}

function createAvatarHtml($img_url, $img_pos, $img_size, $entity){
    if($img_url == ""){
        if($entity == "company"){
            $img_url = ASSET_URL . "/image/default-company.jpg";
        } else if($entity == "user"){
            $img_url = ASSET_URL . "/image/default-user.png";
        }
    }
    $img_pos = $img_pos == "" ? "0px 0px" : $img_pos;
    $img_size = $img_size == "" ? "cover" : $img_size;

    ob_clean();
    ob_start();
    ?>
    <table border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center" style="border-radius: 100%; height:100px; width:100px; background-image: url('<?= $img_url ?>'); background-position: <?= $img_pos ?>; background-size: <?= $img_size ?>; background-repeat: no-repeat; "></td>
        </tr>
    </table>
    <?php
    $output_string = ob_get_contents();
    ob_end_clean();
    return $output_string;
}

function createBtnHtml($text, $url, $color){
    ob_clean();
    ob_start();
    ?>
    
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td>
            <table border="0" cellspacing="0" cellpadding="0">
                <tr>
                <td bgcolor="<?= $color ?>" style="padding: 12px 18px 12px 18px; border-radius:3px" align="center">
                    <a href="<?= $url ?>" target="_blank" style="font-size: 16px; font-family: Helvetica, Arial, sans-serif; font-weight: normal; color: #ffffff; text-decoration: none; display: inline-block;">
                        <?= $text ?> &rarr;
                    </a></td>
                </tr>
            </table>
            </td>
        </tr>
    </table>

    <?php
    $output_string = ob_get_contents();
    ob_end_clean();
    return $output_string;
}


function createMessageNotificationEmail($fromData, $toData, $message)
{
    $btnAction = createViewMessageBtn();
    $fromAvatar = createAvatarHtml($fromData["img_url"], $fromData["img_pos"], $fromData["img_size"], $fromData["entity"]);
    //$toAvatar = createAvatarHtml($toData["img_url"], $toData["img_pos"], $toData["img_size"]);

    ob_clean();
    ob_start();
    
    //  <b style="font-size:20px;">"<?= $message </b>

    ?>
        <span>
            <i>Hi <?= $toData["name"] ?>,</i>
            <br><br>
            <span style="font-size:15px;">
            You've got new message from <b><?= $fromData["name"] ?></b>
            <br>
            <br>
            <?= $fromAvatar ?>
            <br>
            <?= $btnAction ?>
            <br>    
            </span>            
        </span>
    <?php

    $output_string = ob_get_contents();
    ob_end_clean();
    return $output_string;
}
?>