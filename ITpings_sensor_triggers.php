<?php
/**
 * process sensor values
 **/
//region ===== CUSTOMIZABLE SENSOR TRIGGERS =======================================================

/**
 * @param $to
 * @param $subject
 * @param $message
 */
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
function process_Sensor_ButtonClick($sensor_name, $sensor_value)
{
    global $request;
    $dev_id = $request[TTN_dev_id];

    $IS_CAYENNE_BUTTON_CLICKED = ($sensor_name === TTN_Cayenne_digital_in_1 && $sensor_value === 1);
    $IS_TTNNODE_BUTTON_CLICKED = ($sensor_name === TTN_TTNnode_event && $sensor_value === TTN_TTNnode_event_button);

    $IS_BUTTON_CLICKED = ($IS_CAYENNE_BUTTON_CLICKED OR $IS_TTNNODE_BUTTON_CLICKED);

    if ($IS_BUTTON_CLICKED) {

        $date = $request[ITPINGS_TIME];
        //$format = 'Y-m-d H:i:s';
        //$date = DateTime::createFromFormat($format, $request[ITPINGS_TIME]);

        //$msg = "Button $dev_id was clicked, at $date";
        //sendEmail("someone@world.com", $msg, $msg);

        call_IFTTT_Webhook('ttn_button_clicked', IFTTT_KEY, "Button $dev_id was clicked, at $date");

        insert_TTN_Event(ENUM_EVENTTYPE_Trigger, 'ButtonClicked', $dev_id);
    }
}

/**
 * process Cayenne or TTN style accelerometer,
 * Cayenne is x,y,z displacement
 * TTN only says event=motion
 *
 * @param $sensor_name
 * @param $sensor_value
 */
function process_Sensor_Accelerometer($sensor_name, $sensor_value)
{
    global $request;
    $pingID = $request[PRIMARYKEY_Ping];
    $dev_id = $request[TTN_dev_id];

    $moved_x = 0;
    $moved_y = 0;
    $moved_z = 0;
    /** Convert nested objects (like TTN x,y,z movements to a CSV String **/
    $sensor_value_String = '';
    if (is_array($sensor_value)) $sensor_value_String = implode(",", $sensor_value);

    switch ($sensor_name) {
        case TTN_Cayenne_accelerometer:
            $moved_x = $sensor_value["x"];
            $moved_y = $sensor_value["y"];
            $moved_z = $sensor_value["z"];
            insert_TTN_Event(ENUM_EVENTTYPE_Trigger, 'Cayenne Device Moved: ' . $dev_id, $sensor_value_String);
            break;
        case TTN_TTNnode_event:
            insert_TTN_Event(ENUM_EVENTTYPE_Trigger, 'TTN Motion: ' . $dev_id, $sensor_value);
            $moved_x = 1;
            break;
        default:
            $moved_x = 21;
            break;

    }
    SQL_INSERT(TABLE_DATA_ACCELEROMETER, [$pingID, $request[PRIMARYKEY_Device], $moved_x, $moved_y, $moved_z]);
    insert_TTN_Event(ENUM_EVENTTYPE_Trigger, 'Move: ' . $sensor_name . ' dev:' . $dev_id, $sensor_value_String);
}


/**
 * process Battery, save to dedicated DATA_BATTERY table
 * @param $sensor_name
 * @param $sensor_value
 */
function process_Sensor_Battery($sensor_name, $sensor_value)
{
    global $request;
    $pingID = $request[PRIMARYKEY_Ping];
    $dev_id = $request[TTN_dev_id];

    $batteryPower = (int)$sensor_value;

    /** standard TTN (NON-Cayenne) Sketch/encoding from NNNN number to decimal X.yyy **/
    if ($sensor_name === TTN_TTNnode_battery and $batteryPower > 1000) $sensor_value = $batteryPower / 1000;

    SQL_INSERT(TABLE_DATA_BATTERY, [$pingID, $request[PRIMARYKEY_Device], $sensor_value]);
}

/**
 * process Temperature, save to dedicated DATA_TEMPERATURE table
 * @param $sensor_name
 * @param $sensor_value
 */
function process_Sensor_Temperature($sensor_name, $sensor_value)
{
    global $request;
    $pingID = $request[PRIMARYKEY_Ping];
    $dev_id = $request[TTN_dev_id];

    SQL_INSERT(TABLE_DATA_TEMPERATURE, [$pingID, $request[PRIMARYKEY_Device], $sensor_value]);
}

/**
 * process Light, save to dedicated DATA_LUMINOSITY table
 * @param $sensor_name
 * @param $sensor_value
 */
function process_Sensor_Light($sensor_name, $sensor_value)
{
    global $request;
    $pingID = $request[PRIMARYKEY_Ping];
    $dev_id = $request[TTN_dev_id];

    if ($sensor_name === TTN_TTNnode_light) $sensor_value = $sensor_value * 10;

    SQL_INSERT(TABLE_DATA_LUMINOSITY, [$pingID, $request[PRIMARYKEY_Device], $sensor_value]);
}

/**
 * convert different sensor outputs to same values
 * eg: For Battery powerlevel Cayenne gives us 4.16 and TTN 4160
 *
 * @param $sensor_name
 * @param $sensor_value
 */
function process_One_Sensor_Value($sensor_name, $sensor_value)
{
    global $request;
    $pingID = $request[PRIMARYKEY_Ping];
    $dev_id = $request[TTN_dev_id];

    $STORE_AS_GENERIC_SENSOR = FALSE;

    switch ($sensor_name) {
        /** SENSOR: BATTERY **/
        case TTN_Cayenne_analog_in_4_Battery:
        case TTN_TTNnode_battery:
            process_Sensor_Battery($sensor_name, $sensor_value);
            break;

        /** SENSOR: TEMPERATURE **/
        case TTN_TTNnode_temperature:
        case TTN_Cayenne_temperature:
            process_Sensor_Temperature($sensor_name, $sensor_value);
            break;

        /** SENSOR: LIGHT **/
        case TTN_TTNnode_light:
        case TTN_Cayenne_luminosity:
            process_Sensor_Light($sensor_name, $sensor_value);
            break;

        /** SENSOR: ACCELEROMETER **/
        case TTN_Cayenne_accelerometer:
            process_Sensor_Accelerometer($sensor_name, $sensor_value);
            break;

        /** SENSOR: BUTTON **/
        case TTN_Cayenne_digital_in_1:
            process_Sensor_ButtonClick($sensor_name, $sensor_value);
            break;

        /** SENSOR: TTN NODE EVENTS **/
        case TTN_TTNnode_event:
            switch ($sensor_value) {
                case /** INTERVAL **/
                TTN_TTNnode_event_interval:
                    break;
                case  /** MOTION **/
                TTN_TTNnode_event_motion:
                    process_Sensor_Accelerometer($sensor_name, $sensor_value);
                    /** When returning true the value is NOT stored in Table_SensorValues (below) **/
                    break;
                case  /** BUTTON **/
                TTN_TTNnode_event_button:
                    process_Sensor_ButtonClick($sensor_name, $sensor_value);
                    /** When returning true the value is NOT stored in Table_SensorValues (below) **/
                    break;
                default:
                    insert_TTN_Event(ENUM_EVENTTYPE_Log, 'TTN Event:' . $sensor_value, $dev_id);
                    $STORE_AS_GENERIC_SENSOR = TRUE;
                    break;
            }

        default:
            $STORE_AS_GENERIC_SENSOR = TRUE;
            break;
    }

    IF ($STORE_AS_GENERIC_SENSOR) {
        $sensor_ID = process_Existing_Or_New_Sensor($sensor_name);
        SQL_INSERT(TABLE_SENSORVALUES, [$pingID, Valued($sensor_ID), Quoted($sensor_value)]);
    }
}

//endregion == CUSTOMIZABLE SENSOR TRIGGERS =======================================================

function call_endpoint($endpoint, $data)
{
    $curl = curl_init();                                           // $curl = curl_init('http://localhost/echoservice');
    curl_setopt($curl, CURLOPT_POST, 1);             // We POST the data
    curl_setopt($curl, CURLOPT_URL, $endpoint);             // Set the url path we want to call
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);// Make it so the data coming back is put into a string
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Insert the data
    $result = curl_exec($curl);                                     // Send the request

    $info = curl_getinfo($curl);                                // Get some cURL session information back
    echo 'content type: ' . $info['content_type'] . '<br />';
    echo 'http code: ' . $info['http_code'] . '<br />';

    curl_close($curl);  // Free up the resources $curl is using

    return $result;
}

/**
 * @param $event
 * @param $key
 * @param string $value1
 * @param string $value2
 * @param string $value3
 */
function call_IFTTT_Webhook($event, $key, $value1 = '', $value2 = '', $value3 = '')
{
    $endpoint = 'https://maker.ifttt.com/trigger/' . $event . '/with/key/' . $key;

    $data = array(                                     // Here is the data we will be sending to the service
        'value1' => $value1,
        'value2' => $value2,
        'value3' => $value3,
    );
    if ($key) echo call_endpoint($endpoint, $data);
}
