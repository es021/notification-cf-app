<?php
include_once 'lib/DB.php';
include_once 'lib/EmailNotification.php';
include_once 'lib/SendEmail.php';

$res = file_get_contents('https://www.goldpriceticker.com/gold-rates/malaysia/');

$res =  explode("Gold Gram Carat 24", $res);
$res = $res[1];
$res =  explode("Gold Gram Carat 22", $res);
$res = $res[0]; // <td>xxx MYR</td>
$res =  explode("<td>", $res);
$res = $res[1]; // xxx MYR</td>
$res =  explode("</td>", $res);
$res = $res[0];

$goldPrice = $res;

$to = "zulsarhan.shaari@gmail.com";
$name = "Wan Zulsarhan";
$title = "Gold Price Today - " . $goldPrice;
$body = $title;

sendMail($title, $body, $to, $name, true, true);

?>
