<?php
//region ===== CREATE ITPINGS DATABASE : VIEWS ====================================================

/**
 * $view definition for data tables (Temperature, Luminosity)
 * @param $data_table
 * @return string
 */
function Create_Or_Replace_DataTable_View($data_table)
{

    $view = " T." . PRIMARYKEY_Ping;
    $view .= " , P." . ITPINGS_CREATED_TIMESTAMP;
    $view .= " , T." . PRIMARYKEY_Device . " , T." . ITPINGS_SENSOR_VALUE;
    $view .= " FROM $data_table T ";
    $view .= " JOIN " . TABLE_PINGS . " P ON P . " . PRIMARYKEY_Ping . " = T . " . PRIMARYKEY_Ping;
    $view .= " ORDER BY T." . PRIMARYKEY_Ping . " DESC";
    return $view;
}

/**
 * @param $view_name
 * @return string
 */
function Create_Or_Replace_View($view_name)
{
    /**
     * Instructions for creating a new VIEW
     * - define the VIEW name in ITpings_configuration
     * - add the VIEW as CASE in 'create_ITpings_Views() function'
     * - Whitelist the VIEW name in 'process_Query_with_QueryString_Parameters()' function
     **/

    $sql = "CREATE OR REPLACE VIEW $view_name AS SELECT ";

    $view = EMPTY_STRING;

    switch ($view_name) {
        case VIEWNAME_EVENTS:
            $view .= " P." . PRIMARYKEY_Ping . " , P." . ITPINGS_CREATED_TIMESTAMP;
            $view .= " , E." . ITPINGS_EVENTTYPE . ",E." . ITPINGS_EVENTLABEL . ", E." . ITPINGS_EVENTVALUE;
            $view .= " FROM " . TABLE_EVENTS . " E ";
            $view .= " JOIN " . TABLE_PINGS . " P ON P." . PRIMARYKEY_Ping . " = E." . PRIMARYKEY_Ping;
            $view .= " ORDER BY " . ITPINGS_CREATED_TIMESTAMP . " DESC";
            break;
        case VIEWNAME_APPLICATIONDEVICES:
            $view .= " AD." . PRIMARYKEY_ApplicationDevice . " ";
            $view .= " , A." . ITPINGS_APPLICATION_ID . " , A." . ITPINGS_DESCRIPTION;
            $view .= " , D." . ITPINGS_DEVICE_ID . " , D." . ITPINGS_HARDWARE_SERIAL;
            $view .= " FROM " . TABLE_APPLICATIONDEVICES . " AD ";
            $view .= " JOIN " . TABLE_APPLICATIONS . " A ON A." . PRIMARYKEY_Application . " = AD." . PRIMARYKEY_Application;
            $view .= " JOIN " . TABLE_DEVICES . " D ON D." . PRIMARYKEY_Device . " = AD." . PRIMARYKEY_Device;
            $view .= " ORDER BY A." . ITPINGS_APPLICATION_ID . " ASC, D." . ITPINGS_DEVICE_ID . " ASC";
            break;
        case VIEWNAME_SENSORVALUES:
            $view .= " P." . PRIMARYKEY_Ping . " , P." . ITPINGS_CREATED_TIMESTAMP;
            $view .= " , SV." . PRIMARYKEY_Sensor;
            $view .= " , AD." . PRIMARYKEY_ApplicationDevice . " , AD." . ITPINGS_APPLICATION_ID . " , AD." . ITPINGS_DEVICE_ID;
            //$view .= " , AD." . ITPINGS_HARDWARE_SERIAL;
            $view .= " , S." . ITPINGS_SENSORNAME;
            $view .= " , SV." . ITPINGS_SENSORVALUE;
            $view .= " FROM " . TABLE_SENSORVALUES . " SV ";
            $view .= " JOIN " . TABLE_SENSORS . " S ON S." . PRIMARYKEY_Sensor . " = SV." . PRIMARYKEY_Sensor;
            $view .= " JOIN " . TABLE_PINGS . " P ON P." . PRIMARYKEY_Ping . " = SV." . PRIMARYKEY_Ping;
            $view .= " JOIN " . VIEWNAME_APPLICATIONDEVICES . " AD ON AD." . PRIMARYKEY_ApplicationDevice . " = S." . PRIMARYKEY_ApplicationDevice;
            $view .= " ORDER BY " . ITPINGS_CREATED_TIMESTAMP . " DESC, SV." . PRIMARYKEY_Sensor;
            break;
        case VIEWNAME_SENSORVALUES_UPDATE:// less JOINs = faster
            $view .= " P." . PRIMARYKEY_Ping . " , P." . ITPINGS_CREATED_TIMESTAMP;
            $view .= " , SV." . PRIMARYKEY_Sensor;
            //$view .= " , AD." . PRIMARYKEY_ApplicationDevice . " , AD." . ITPINGS_APPLICATION_ID . " , AD." . ITPINGS_DEVICE_ID;
            //$view .= " , AD." . ITPINGS_HARDWARE_SERIAL;
            //$view .= " , S." . ITPINGS_SENSORNAME;
            $view .= " , SV." . ITPINGS_SENSORVALUE;
            $view .= " FROM " . TABLE_SENSORVALUES . " SV ";
            //$view .= " JOIN " . TABLE_SENSORS . " S ON S." . PRIMARYKEY_Sensor . " = SV." . PRIMARYKEY_Sensor;
            $view .= " JOIN " . TABLE_PINGS . " P ON P." . PRIMARYKEY_Ping . " = SV." . PRIMARYKEY_Ping;
            //$view .= " JOIN " . VIEWNAME_APPLICATIONDEVICES . " AD ON AD." . PRIMARYKEY_ApplicationDevice . " = S." . PRIMARYKEY_ApplicationDevice;
            $view .= " ORDER BY " . ITPINGS_CREATED_TIMESTAMP . " DESC, SV." . PRIMARYKEY_Sensor;
            break;
        case VIEWNAME_GATEWAYS:
            $view .= " G." . PRIMARYKEY_Gateway . " , G." . ITPINGS_GATEWAY_ID;
            $view .= " ,G." . ITPINGS_TRUSTED;
            $view .= " ,L." . ITPINGS_LATITUDE . " , L." . ITPINGS_LONGITUDE;
            $view .= " ,L." . ITPINGS_ALTITUDE;
            $view .= " ,L." . ITPINGS_LOCATION_SOURCE;
            $view .= " FROM " . TABLE_GATEWAYS . " G ";
            $view .= " JOIN " . TABLE_LOCATIONS . " L ON L." . PRIMARYKEY_Location . " = G." . PRIMARYKEY_Location;
            $view .= " ORDER BY G." . PRIMARYKEY_Gateway . " ASC";
            break;
        case VIEWNAME_PINGEDDEVICES:
            $view .= " P." . PRIMARYKEY_Ping . ",P." . ITPINGS_CREATED_TIMESTAMP;
            $view .= " ,PG." . ITPINGS_TIMESTAMP . ",PG." . ITPINGS_TIME;
            $view .= " , D." . PRIMARYKEY_Device;
            $view .= " , D." . ITPINGS_DEVICE_ID;
            $view .= " , F." . ITPINGS_FREQUENCY;
            $view .= " , M." . ITPINGS_MODULATION;
            $view .= " ,DR." . ITPINGS_DATA_RATE;
            $view .= " ,CR." . ITPINGS_CODING_RATE;
            $view .= " ,PG." . ITPINGS_CHANNEL . ", PG." . ITPINGS_RSSI . ", PG." . ITPINGS_SNR . ", PG." . ITPINGS_RFCHAIN;
//                $view .= " , G." . PRIMARYKEY_Gateway . " , G." . ITPINGS_GATEWAY_ID;
//                $view .= " , G." . ITPINGS_TRUSTED;
            $view .= " ,L." . ITPINGS_LATITUDE . " , L." . ITPINGS_LONGITUDE;
            $view .= " ,L." . ITPINGS_ALTITUDE;
            //$view .= " , G." . ITPINGS_LOCATIONSOURCE;
            $view .= " FROM " . TABLE_PINGEDGATEWAYS . " PG ";
            $view .= " JOIN " . TABLE_PINGS . " P ON P." . PRIMARYKEY_Ping . " = PG." . PRIMARYKEY_Ping;
//                $view .= " JOIN " . TABLE_GATEWAYS . " G ON G." . PRIMARYKEY_Gateway . " = PG." . PRIMARYKEY_Gateway;
            $view .= " JOIN " . TABLE_APPLICATIONDEVICES . " AD ON AD." . PRIMARYKEY_ApplicationDevice . " = P." . PRIMARYKEY_ApplicationDevice;

//            $view .= JOIN(TABLE_DEVICES, 'D', 'AD', PRIMARYKEY_Device);
            $view .= " JOIN " . TABLE_DEVICES . " D ON D." . PRIMARYKEY_Device . " = AD." . PRIMARYKEY_Device;
            $view .= " JOIN " . TABLE_FREQUENCIES . " F ON F." . PRIMARYKEY_Frequency . " = P." . PRIMARYKEY_Frequency;
            $view .= " JOIN " . TABLE_MODULATIONS . " M ON M." . PRIMARYKEY_Modulation . " = P." . PRIMARYKEY_Modulation;
            $view .= " JOIN " . TABLE_DATARATES . " DR ON DR." . PRIMARYKEY_Datarate . " = P." . PRIMARYKEY_Datarate;
            $view .= " JOIN " . TABLE_CODINGRATES . " CR ON CR." . PRIMARYKEY_Codingrate . " = P." . PRIMARYKEY_Codingrate;
            $view .= " JOIN " . TABLE_LOCATIONS . " L ON L." . PRIMARYKEY_Location . " = P." . PRIMARYKEY_Location;
            $view .= " ORDER BY " . ITPINGS_CREATED_TIMESTAMP . " DESC";
            break;
        case VIEWNAME_PINGEDGATEWAYS:
            $view .= " P." . PRIMARYKEY_Ping . ",P." . ITPINGS_CREATED_TIMESTAMP;
            $view .= " ,PG." . ITPINGS_TIMESTAMP . ",PG." . ITPINGS_TIME;
            $view .= " , F." . ITPINGS_FREQUENCY;
            $view .= " , M." . ITPINGS_MODULATION;
            $view .= " ,DR." . ITPINGS_DATA_RATE;
            $view .= " ,CR." . ITPINGS_CODING_RATE;
            $view .= " ,PG." . ITPINGS_CHANNEL . ", PG." . ITPINGS_RSSI . ", PG." . ITPINGS_SNR . ", PG." . ITPINGS_RFCHAIN;
            $view .= " ,G." . PRIMARYKEY_Gateway . " , G." . ITPINGS_GATEWAY_ID;
            $view .= " ,G." . ITPINGS_TRUSTED;
            $view .= " ,L." . ITPINGS_LATITUDE . " , L." . ITPINGS_LONGITUDE;
            $view .= " ,L." . ITPINGS_ALTITUDE;
            //$view .= " ,G." . ITPINGS_LOCATIONSOURCE;
            $view .= " FROM " . TABLE_PINGEDGATEWAYS . " PG ";
            $view .= " JOIN " . TABLE_PINGS . " P ON P." . PRIMARYKEY_Ping . " = PG." . PRIMARYKEY_Ping;
            $view .= " JOIN " . TABLE_GATEWAYS . " G ON G." . PRIMARYKEY_Gateway . " = PG." . PRIMARYKEY_Gateway;
            $view .= " JOIN " . TABLE_FREQUENCIES . " F ON F." . PRIMARYKEY_Frequency . " = P." . PRIMARYKEY_Frequency;
            $view .= " JOIN " . TABLE_MODULATIONS . " M ON M." . PRIMARYKEY_Modulation . " = P." . PRIMARYKEY_Modulation;
            $view .= " JOIN " . TABLE_DATARATES . " DR ON DR." . PRIMARYKEY_Datarate . " = P." . PRIMARYKEY_Datarate;
            $view .= " JOIN " . TABLE_CODINGRATES . " CR ON CR." . PRIMARYKEY_Codingrate . " = P." . PRIMARYKEY_Codingrate;
            $view .= " JOIN " . TABLE_LOCATIONS . " L ON L." . PRIMARYKEY_Location . " = P." . PRIMARYKEY_Location;
            $view .= " ORDER BY " . ITPINGS_CREATED_TIMESTAMP . " DESC";
            break;
        case VIEWNAME_DATA_TEMPERATURE:
            $view .= Create_Or_Replace_DataTable_View(TABLE_DATA_TEMPERATURE);
            break;
        case VIEWNAME_DATA_LUMINOSITY:
            $view .= Create_Or_Replace_DataTable_View(TABLE_DATA_LUMINOSITY);
            break;
        case VIEWNAME_DATA_BATTERY:
            $view .= Create_Or_Replace_DataTable_View(TABLE_DATA_BATTERY);
            break;
    }

    $sql = $sql . $view;

    SQL_CREATE_or_REPLACE_VIEW($view_name, $sql);
    add_JSON_message_to_JSON_response('CreateOrReplaceView: ' . $sql);

    return $sql;
}


/**
 * Loop all ITpings_configuration.php ViewNames, creating the View
 * Called from 2 source code locations because Views can be REPLACED (makes (live) changes to this PHP code easier)
 */
function create_ITpings_Views()
{
    global $_ITPINGS_VIEWNAMES;
    foreach ($_ITPINGS_VIEWNAMES as $view_name) {
        Create_Or_Replace_View($view_name);
    }
}

//endregion == CREATE ITPINGS DATABASE : VIEWS ====================================================
