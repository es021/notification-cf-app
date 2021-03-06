<?php

class SendEmail {

    const TABLE = "send_emails";
    const STS_SKIP = "SKIP";
    const STS_SENT = "SENT";
    const STS_ERROR = "ERROR";
    const STS_SMS = "SMS";

    public static function insert($key_id, $type, $data, $emailRes, $DB = null) {
        if ($DB == null) {
            include_once './DB.php';
            $DB = new DB();
        }

        $toDB = array(
            "key_id" => $key_id,
            "type" => $type,
            "data" => $data
        );

        if($emailRes === "SKIP"){
            $toDB["status"] = self::STS_SKIP;
        }else if ($emailRes === true) {
            $toDB["status"] = self::STS_SENT;
        }else if($emailRes === "SMS"){
            $toDB["status"] = self::STS_SMS;
        }
        // has error
        else {
            $toDB["status"] = self::STS_ERROR;
        }

        $DB->query_insert(SendEmail::TABLE, $toDB);
    }

    public static function update($ID, $key_id, $data, $emailRes, $DB = null) {
        if ($DB == null) {
            include_once './DB.php';
            $DB = new DB();
        }

        $toDB = array(
            "key_id" => $key_id,
            "data" => $data
        );

        if ($emailRes === true) {
            $toDB["status"] = self::STS_SENT;
        }
        // has error
        else {
            $toDB["status"] = self::STS_ERROR;
        }

        $DB->query_update(SendEmail::TABLE, "where ID = $ID ", $toDB);
    }

    public static function getTimeByTimezone($unix, $timezone) {
        $TZ = array(
            "EST" => 'America/New_York',
            "NZT" => 'Pacific/Auckland',
            "MYT" => 'Asia/Kuala_Lumpur'
        );

        $dt = new DateTime();
        $dt->setTimestamp($unix);
        $dt->setTimezone(new DateTimeZone($TZ[$timezone])); // eastern
        $datetime = $dt->format("M d, Y - g:i A");
        return "$datetime ($timezone)";
    }

}
