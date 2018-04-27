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

        call_IFTTT_Webhook('ttn_button_clicked', 'cwmYqAicGxSfDWLOO6MZSa', "Button $dev_id was clicked, at $date");

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
            /** When returning true the value is NOT stored in Table_SensorValues (below) **/
            //return true;
            break;

        default:
            break;
    }

    check_Button_Event_Trigger_For_Sensor($sensor_name, $sensor_value);

    SQL_INSERT(TABLE_SENSORVALUES, [$pingID, Valued($sensor_ID), Quoted($sensor_value)]);

    return $sensor_value;
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
