<?php

class SendEmail {

    const TABLE = "send_emails";
    const STS_SENT = "SENT";
    const STS_ERROR = "ERROR";

    public static function insert($type, $entity, $entity_id, $data, $emailRes, $DB = null) {
        if ($DB == null) {
            include_once './DB.php';
            $DB = new DB();
        }

        $toDB = array(
            "entity" => $entity,
            "entity_id" => $entity_id,
            "type" => $type,
            "data" => $data
        );

        if ($emailRes === true) {
            $toDB["status"] = self::STS_SENT;
        }
        // has error
        else {
            $toDB["status"] = self::STS_ERROR;
        }

        $DB->query_insert(SendEmail::TABLE, $toDB);
    }

    public static function getTimeByTimezone($unix, $timezone) {
        $TZ = array(
            "EST" => 'America/New_York',
            "MYT" => 'Asia/Kuala_Lumpur'
        );

        $dt = new DateTime();
        $dt->setTimestamp($unix);
        $dt->setTimezone(new DateTimeZone($TZ[$timezone])); // eastern
        $datetime = $dt->format("M d, Y - g:i A");
        return "$datetime ($timezone)";
    }

}
