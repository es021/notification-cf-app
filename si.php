<?php

include_once 'lib/DB.php';
include_once 'lib/EmailNotification.php';


// get all Approved prescreens that are not in email sent yet
$DB = new DB();
$q = "select * from pre_screens where status = 'Approved' ";
$data = $DB->query_array($q);

X($data);



sendMail("MySQL Is Down", $BODY, "zulsarhan.shaari@gmail.com", "Zul");
?>