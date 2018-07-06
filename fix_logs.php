<?php
include_once 'lib/DB.php';


// get all Approved prescreens that are not in email sent yet
// at the same time check is_update whether the same entity has been sent before

$DB = new DB();
$q = "SELECT count(*) count_click, l.data, l.user_id, u.user_email 
FROM `logs` l left outer join wp_cf_users u on u.ID = l.user_id 
where event = 'click_ads' and data like 'zoom_download' 
group by l.user_id, u.user_email, l.data having count_click > 1 ";

$data = $DB->query_array($q);

foreach ($data as $d) {
    $q = "SELECT * from logs l where l.user_id = {$d["user_id"]}
        and l.event = 'click_ads' and l.data = 'zoom_download' limit 0,1";     

    $temp = $DB->query_array($q);
   
    //X($temp);
    if(!empty($temp)){
        $id = $temp[0]["ID"];

        $q = "UPDATE logs set data = 'talent_corp_next' 
        where ID = $id ;";

        X($q);
    }
}
