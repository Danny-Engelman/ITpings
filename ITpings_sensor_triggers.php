<?php
/**
 * process sensor values
**/
//region ===== CUSTOMIZABLE SENSOR TRIGGERS =======================================================

function sendEmail($to, $subject, $message)
{
    mail($to, $subject, $message);
}

//function xxmail($to, $subject, $body, $headers)
//{
//    $smtp = stream_socket_client('tcp://smtp.yourmail.com:25', $eno, $estr, 30);
//
//    $B = 8192;
//    $c = "\r\n";
//    $s = 'myapp@someserver.com';
//
//    fwrite($smtp, 'helo ' . $_ENV['HOSTNAME'] . $c);
//    $junk = fgets($smtp, $B);
//
//// Envelope
//    fwrite($smtp, 'mail from: ' . $s . $c);
//    $junk = fgets($smtp, $B);
//    fwrite($smtp, 'rcpt to: ' . $to . $c);
//    $junk = fgets($smtp, $B);
//    fwrite($smtp, 'data' . $c);
//    $junk = fgets($smtp, $B);
//
//// Header
//    fwrite($smtp, 'To: ' . $to . $c);
//    if (strlen($subject)) fwrite($smtp, 'Subject: ' . $subject . $c);
//    if (strlen($headers)) fwrite($smtp, $headers); // Must be \r\n (delimited)
//    fwrite($smtp, $headers . $c);
//
//// Body
//    if (strlen($body)) fwrite($smtp, $body . $c);
//    fwrite($smtp, $c . '.' . $c);
//    $junk = fgets($smtp, $B);
//
//// Close
//    fwrite($smtp, 'quit' . $c);
//    $junk = fgets($smtp, $B);
//    fclose($smtp);
//}

/**
 * For every Sensor reading process custom Triggers (like a Button clock)
 * @param $sensor_name
 * @param $sensor_value
 **/
function check_Button_Event_Trigger_For_Sensor($sensor_name, $sensor_value)
{
    global $request;
    $dev_id = $request[TTN_dev_id];

    $IS_CAYENNE_BUTTON_CLICKED = ($sensor_name === TTN_Cayenne_digital_in_1 && $sensor_value === 1);
    $IS_TTNNODE_BUTTON_CLICKED = ($sensor_name === 'event' && $sensor_value === 'button');

    $IS_BUTTON_CLICKED = ($IS_CAYENNE_BUTTON_CLICKED OR $IS_TTNNODE_BUTTON_CLICKED);

    if ($IS_BUTTON_CLICKED) {

        $date = $request[ITPINGS_TIME];
        //$format = 'Y-m-d H:i:s';
        //$date = DateTime::createFromFormat($format, $request[ITPINGS_TIME]);

        //$msg = "Button $dev_id was clicked, at $date";
        //sendEmail("someone@world.com", $msg, $msg);
        insert_TTN_Event(ENUM_EVENTTYPE_Trigger, 'ButtonClicked', $dev_id);
    }
}

/**
 * convert different sensor outputs to same values
 * eg: For Battery powerlevel Cayenne gives us 4.16 and TTN 4160
 *
 * @param $sensor_ID
 * @param $sensor_name
 * @param $sensor_value
 * @return float|int
 */
function process_SensorValue($sensor_ID, $sensor_name, $sensor_value)
{
    global $request;
    $pingID = $request[PRIMARYKEY_Ping];

    switch ($sensor_name) {

        case 'battery': // standard TTN (NON-Cayenne) Sketch/encoding from NNNN number to decimal X.yyy
            $batteryPower = (int)$sensor_value;
            if ($batteryPower > 1000) $sensor_value = $batteryPower / 1000;
            break;

        case 'event':
            if ($sensor_value === 'interval') return false; // do not record event/interval from TTN Node
            break;

        case 'temperature':
        case TTN_Cayenne_temperature:
            SQL_INSERT(TABLE_TEMPERATURE, [$pingID, $request[PRIMARYKEY_Device], $sensor_value]);
            //return true;// todo do not store value in other table (sensorvalues) also
            break;

        default:
            break;
    }

    check_Button_Event_Trigger_For_Sensor($sensor_name, $sensor_value);

    SQL_INSERT(TABLE_SENSORVALUES, [$pingID, Valued($sensor_ID), Quoted($sensor_value)]);

    return $sensor_value;
}

//endregion == CUSTOMIZABLE SENSOR TRIGGERS =======================================================

?>