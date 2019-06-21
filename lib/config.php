<?php

define("APP_URL", "http://seedsjobfairapp.com/cf/app");
define("APP_AUTH_URL", "http://seedsjobfairapp.com/cf/auth");
define("APP_NAME", "SeedsJobFairApp.com");
define("CONVERT_UNIX_URL", APP_AUTH_URL."/my-local-time/?unix=");


echo CONVERT_UNIX_URL;

?>