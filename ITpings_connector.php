<?php
include('ITpings_configuration.php');

//region ===== processing POST request SQL functions ==============================================

/**
 * @param $table_name
 * @param $fields_values
 * @return array|null
 */
function SQL_insert($table_name, $fields_values)
{
    $sql = SQL_INSERT_INTO . $table_name;
    $sql .= SQL_VALUES_START;
    foreach ($fields_values as $index => $value) {
        if ($index > 0) $sql .= COMMA;
        $sql .= $value;
    }
    $sql .= SQL_VALUES_CLOSE;
    return SQL_Query($sql);
}

/**
 * @param $event_type
 * @param $event_label
 * @param string $event_value
 */
function insert_TTN_Event($event_type, $event_label, $event_value = '')
{
    global $request;

    SQL_insert(TABLE_EVENTS
        , [//values
            $request[PRIMARYKEY_Ping]
            , Quoted($event_type)
            , Quoted($event_label)
            , Quoted($event_value)
        ]);
}

/**
 * @param $table_name
 * @param $where_clause
 * @param $key_field
 * @return array|null
 *
 * Find a matching row in the database, or NULL for non found
 */
function SQL_find_existing_key_id($key_field, $table_name, $where_clause)
{
    $sql = "SELECT $key_field FROM $table_name WHERE $where_clause ORDER BY $key_field DESC LIMIT 1;";
    return SQL_Query($sql)[$key_field];
}

/**
 * find an existing Application or create a new Application
 * sets the found/created ID value in the global $request object
 */
function process_Application()
{
    global $request;
    $request_TTN_app_id = $request[TTN_app_id];

    $key_id = SQL_find_existing_key_id(
        PRIMARYKEY_Application
        , TABLE_APPLICATIONS
        , TTN_app_id . "=" . Quoted($request_TTN_app_id)

    );

    if (!$key_id) {
        insert_TTN_Event(
            ENUM_EVENTTYPE_NewApp
            , $request_TTN_app_id
        );

        $key_id = SQL_insert(
            TABLE_APPLICATIONS
            , [//values
            AUTOINCREMENT_TABLE_PRIMARYKEY
            , Quoted($request_TTN_app_id)
            , Quoted('Get description from TTN')
        ]);
    }

    $request[PRIMARYKEY_Application] = $key_id;
}

/**
 * find an existing Device or create a new Device
 * sets the found/created ID value in the global $request object
 */
function process_Device()
{
    global $request;
    $request_TTN_dev_id = $request[TTN_dev_id];

    $key_id = SQL_find_existing_key_id(
        PRIMARYKEY_Device
        , TABLE_DEVICES
        , TTN_dev_id . "=" . Quoted($request_TTN_dev_id)
    );

    if (!$key_id) {
        insert_TTN_Event(
            ENUM_EVENTTYPE_NewDevice
            , $request_TTN_dev_id
        );

        $key_id = SQL_insert(
            TABLE_DEVICES
            , [//values
            AUTOINCREMENT_TABLE_PRIMARYKEY
            , Quoted($request_TTN_dev_id)
            , Quoted($request[TTN_hardware_serial])
        ]);
    }
    $request[PRIMARYKEY_Device] = $key_id;
}

/**
 * find an existing Application/Device or create a new Application/Device
 * sets the found/created ID value in the global $request object
 */
function process_ApplicationDevice_Information()
{
    global $request;

    /**
     * process Application and Device information in this request
     *
     * in TTN a Device can only be registered to ONE Application
     * ITpings allows for any combination
     */
    process_Application();    // Find known Application, else save new App
    process_Device(); // Find known Device, else save as new Device

    $key_id = SQL_find_existing_key_id(
        PRIMARYKEY_ApplicationDevice
        , TABLE_APPLICATIONDEVICES
        , PRIMARYKEY_Application . "=" . $request[PRIMARYKEY_Application] . " AND " . PRIMARYKEY_Device . "=" . $request[PRIMARYKEY_Device]
    );

    if (!$key_id) {
        //not Logging NewAppDev to _events Table
        $key_id = SQL_insert(
            TABLE_APPLICATIONDEVICES
            , [//values
            AUTOINCREMENT_TABLE_PRIMARYKEY
            , Valued($request[PRIMARYKEY_Application])
            , Valued($request[PRIMARYKEY_Device])
        ]);
    }

    $request[PRIMARYKEY_ApplicationDevice] = $key_id;
}

/**
 * @param $gtwid
 * @param $latitude
 * @param $longitude
 * @return array|null
 *
 * Find matching Gateway ID
 * OVER 15 metres distance, the Gateway is recorded again (as being moved)
 */
function find_Nearest_Gateway_With_Same_ID($gtwid, $latitude, $longitude)
{
    $sql = "SELECT * ";

    if ($latitude) {
        $GTWradLAT = deg2rad($latitude);
        $GTWradLON = deg2rad($longitude);
        $radius = 6371;// 6371 for Kilometers, 3959 for Miles
        $sql .= " , ($radius*acos(cos($GTWradLAT)*cos(radians(" . ITPINGS_LATITUDE . "))*cos(radians(" . ITPINGS_LONGITUDE . ")-$GTWradLON )+sin($GTWradLAT )*sin(radians(" . ITPINGS_LATITUDE . ")))) AS distance";
    }

    $sql .= " FROM " . TABLE_GATEWAYS;
    $sql .= " WHERE " . TTN_gtw_id . "=" . Quoted($gtwid);

    if ($latitude) {
        // GPS has inaccurate fixes, to prevent 'moving' Gateway recordings a tolerance for lat/lon is calculated
        $sql .= " HAVING distance < " . GATEWAY_POSITION_TOLERANCE . " ORDER BY distance;";
    } else {
        $sql .= " ORDER BY " . PRIMARYKEY_Gateway . " DESC;";
    }

    return SQL_Query($sql);
}

/**
 * @param $gateway Object from the POST request
 * @return number ID value of existing/new Gateway
 *
 * Find a Gateway or create a new Gateway
 */
function process_SingleGateway($gateway)
{
    $request_TTN_gtw_id = $gateway[TTN_gtw_id];
    $latitude = $gateway[TTN_latitude];
    $longitude = $gateway[TTN_longitude];

    // GPS has inaccurate fixes,
    // to prevent 'moving' Gateway recordings a tolerance for lat/lon is calculated
    $table_row = find_Nearest_Gateway_With_Same_ID(
        $request_TTN_gtw_id
        , $latitude
        , $longitude
    );

    if ($table_row) {
        $key_id = $table_row[PRIMARYKEY_Gateway];
        if (!$latitude) {
            insert_TTN_Event(
                ENUM_EVENTTYPE_Log
                , "Gateway without Location"
                , json_encode($gateway)
            );
        }
    } else {
        insert_TTN_Event(
            ENUM_EVENTTYPE_NewGateway
            , $request_TTN_gtw_id
            , json_encode($gateway)
        );

        $key_id = SQL_insert(
            TABLE_GATEWAYS
            , [//values
            AUTOINCREMENT_TABLE_PRIMARYKEY
            , Quoted($request_TTN_gtw_id)
            , Quoted($gateway[TTN_gtw_trusted])
            , Valued($latitude)
            , Valued($longitude)
            , Valued($gateway[TTN_altitude])
            , Quoted($gateway[TTN_location_source])
        ]);
    }

    return $key_id;
}

/**
 * Process all Gateways in the TTN POST Gateway array
 *
 * ONCE noticed duplicate Gateways being created
 * The processed_Gateways Array is a safe guard against duplicate Gateways in the TTN Array
 */
function process_AllGateways()
{
    global $request;
    $processedGateways_InRequest = array();
    $request_TTN_Gateways = $request[TTN_metadata][TTN_gateways];

    foreach ($request_TTN_Gateways as $gateway) {

        $gatewayID = process_SingleGateway($gateway); // Find known Gateway, else save new Gateway

        if (in_array($gatewayID, $processedGateways_InRequest)) {
            insert_TTN_Event(
                ENUM_EVENTTYPE_Error
                , "Duplicate TTN Gateway" . $gatewayID
                , json_encode($request_TTN_Gateways)
            );
        } else {
            array_push($processedGateways_InRequest, $gatewayID);

            SQL_insert(
                TABLE_PINGEDGATEWAYS
                , [//values
                Valued($request[PRIMARYKEY_Ping])
                , Valued($gatewayID)
                , Valued($gateway[TTN_timestamp])
                , Quoted($gateway[TTN_time])
                , Valued($gateway[TTN_channel])
                , Valued($gateway[TTN_rssi])
                , Valued($gateway[TTN_snr])
                , Valued($gateway[TTN_rf_chain])
            ]);
        }
    }
}

/**
 * Now update the Pings Table with data from the POST request
 */
function process_POSTrequest_Update_Ping()
{
    global $request;

    $sql = "UPDATE " . TABLE_PINGS . " SET ";
    $sql .= PRIMARYKEY_ApplicationDevice . "=" . Valued($request[PRIMARYKEY_ApplicationDevice]);
    $sql .= COMMA . TTN_port . "=" . Valued($request[TTN_port]);
    $sql .= COMMA . TTN_counter . "=" . Valued($request[TTN_counter]);

    //create a shorter string by removing the baseparh to save Database bytes
    $downlink_url = str_replace(TTN_DOWNLINKROOT, "", $request[TTN_downlink_url]);
    $sql .= COMMA . TTN_downlink_url . "=" . Quoted($downlink_url);
    $sql .= COMMA . TTN_payload_raw . "=" . Quoted($request[TTN_payload_raw]);

    $metadata = $request[TTN_metadata];
    $sql .= COMMA . ITPINGS_TIME . "=" . Quoted($metadata[TTN_time]);
    $sql .= COMMA . ITPINGS_FREQUENCY . "=" . Valued($metadata[TTN_frequency]);
    $sql .= COMMA . ITPINGS_MODULATION . "=" . Quoted($metadata[TTN_modulation]);
    $sql .= COMMA . ITPINGS_DATA_RATE . "=" . Quoted($metadata[TTN_data_rate]);
    $sql .= COMMA . ITPINGS_CODING_RATE . "=" . Quoted($metadata[TTN_coding_rate]);
    $sql .= COMMA . ITPINGS_LATITUDE . "=" . Valued($metadata[TTN_latitude]);
    $sql .= COMMA . ITPINGS_LONGITUDE . "=" . Valued($metadata[TTN_longitude]);
    $sql .= COMMA . ITPINGS_ALTITUDE . "=" . Valued($metadata[TTN_altitude]);
    $sql .= COMMA . ITPINGS_LOCATIONSOURCE . "=" . Quoted($metadata[TTN_location_source]);
    $sql .= " WHERE " . PRIMARYKEY_Ping . "=" . $request[PRIMARYKEY_Ping];

    SQL_Query($sql);
}

/**
 * @param $sensor_name
 * @return array ID value of existing/new Sensor
 *
 * Find a Sensor or create a new Sensor
 */
function process_OneSensor($sensor_name)
{
    global $request;

    $app_dev_id = $request[PRIMARYKEY_ApplicationDevice];

    $key_id = SQL_find_existing_key_id(
        PRIMARYKEY_Sensor
        , TABLE_SENSORS
        , PRIMARYKEY_ApplicationDevice . "=$app_dev_id AND " . ITPINGS_SENSORNAME . "=" . Quoted($sensor_name)
    );
    if (!$key_id) {
        insert_TTN_Event(
            ENUM_EVENTTYPE_NewSensor
            , $request[TTN_dev_id]
            , $sensor_name
        );

        $key_id = SQL_insert(
            TABLE_SENSORS
            , [//values
            AUTOINCREMENT_TABLE_PRIMARYKEY
            , Valued($app_dev_id)
            , Quoted($sensor_name)
        ]);
    }

    return $key_id;
}


/**
 * @param $sensor_name
 * @param $sensor_value
 */
function check_Event_Trigger_For_Sensor($sensor_name, $sensor_value)
{
    global $request;

    $IS_BUTTONCLICKED = ($sensor_name === TTN_Cayenne_digital_in_1 && $sensor_value === 1);

    if ($IS_BUTTONCLICKED) {
        insert_TTN_Event(
            ENUM_EVENTTYPE_Trigger
            , 'ButtonClicked'
            , $request[TTN_dev_id]);
    }
}

/**
 * Process TTN POST PayLoad Object
 */
function process_Sensors_From_PayloadFields()
{
    global $request;

    foreach ($request[TTN_payload_fields] as $sensor_name => $sensor_value) {

        /**
         * Convert nested objects (like TTN x,y,z movements to a CSV String
         */
        if (is_array($sensor_value)) {
            $sensor_value = implode(",", $sensor_value);
        }

        $sensor_ID = process_OneSensor($sensor_name);

        SQL_insert(
            TABLE_SENSORVALUES
            , [//values
            $request[PRIMARYKEY_Ping]
            , Valued($sensor_ID)
            , Quoted($sensor_value)
        ]);

        check_Event_Trigger_For_Sensor($sensor_name, $sensor_value);
    }
}

//endregion == processing POST request SQL functions ==============================================

function process_Query_with_QueryString_Parameters()
{
    global $urlVars;
    $sql = "";

    $table_name = TABLE_PREFIX . $urlVars['query'];

    /**
     * User can only request for limitted table/view names
     * This is the place to deny access to some Tables
     */
    switch ($table_name) {
        case TABLE_EVENTS:
        case TABLE_POSTREQUESTS:
        case TABLE_APPLICATIONS:
        case TABLE_DEVICES:
            //case TABLE_APPLICATIONDEVICES:// User can not access this table
        case TABLE_GATEWAYS:
        case TABLE_PINGS:
        case TABLE_PINGEDGATEWAYS:
        case TABLE_SENSORS:
        case TABLE_SENSORVALUES:
        case VIEWNAME_EVENTS:
        case VIEWNAME_SENSORVALUES:
        case VIEWNAME_PINGEDGATEWAYS:
        case VIEWNAME_APPLICATIONDEVICES:
            break;
        default:
            $table_name = false;
            break;
    }

    if ($table_name) {
        /**
         * Built a safe SQL query
         * */
        $where = "";
        $order = "";
        $limit = "";
        foreach (VALID_QUERY_PARAMETERS as $parameter) {
            $parameter_value = SQL_InjectionSave_OneWordString($urlVars[$parameter]);
            if ($parameter_value) {
                $PARAMETER_HAS_SEPARATOR = strpos($parameter_value, QUERY_PARAMETER_SEPARATOR) !== FALSE;
                $and = $where === "" ? "" : " AND ";
                switch ($parameter) {
                    case QUERY_PARAMETER_INTERVAL:
                        //https://dev.mysql.com/doc/refman/5.7/en/date-and-time-functions.html#function_date-add
                        $interval_unit = strtoupper($urlVars[QUERY_PARAMETER_INTERVALUNIT]);
                        if (!in_array($interval_unit, QUERY_ALLOWED_INTERVALUNITS)) {
                            $interval_unit = 'HOUR';
                        }
                        $where .= $and . ITPINGS_CREATED_TIMESTAMP . " > DATE_SUB(NOW(), INTERVAL " . (int)$parameter_value . " " . $interval_unit . ")";
                        break;
                    case QUERY_PARAMETER_INTERVALUNIT:// processed in previous interval case
                        break;
                    case QUERY_PARAMETER_ORDERBY:
                        if ($PARAMETER_HAS_SEPARATOR) {
                            $orderbyfields = [];
                            //accept only valid fieldnames
                            foreach (explode(QUERY_PARAMETER_SEPARATOR, $parameter_value) as $fieldname) {
                                if (in_array($fieldname, VALID_QUERY_PARAMETERS)) $orderbyfields[] .= $fieldname;
                            }
                            $parameter_value = implode(QUERY_PARAMETER_SEPARATOR, $orderbyfields);
                        }
                        $order_sort = $urlVars[QUERY_PARAMETER_ORDERSORT] === "DESC" ? "DESC" : "ASC";
                        $order .= " ORDER BY " . $parameter_value . " " . $order_sort;
                        break;
                    case QUERY_PARAMETER_LIMIT:
                        $limit = " LIMIT " . Valued($parameter_value);
                        break;
                    default:
                        if ($PARAMETER_HAS_SEPARATOR) {
                            $where .= $and . "$parameter IN (" . $parameter_value . ")";//todo allow for strings
                        } else {
                            $parameter_value = (is_numeric($parameter_value) ? Valued($parameter_value) : Quoted($parameter_value));
                            $where .= $and . "$parameter=" . $parameter_value;
                        }
                        break;
                }
            }
        }
        $sql = "SELECT * FROM $table_name";
        if ($where !== "") $sql .= " WHERE " . $where;
        $sql .= $order . ($limit === "" ? " LIMIT 1000" : $limit); //default LIMIT
    }
    SQL_Query($sql, TRUE);
}


//region Main code
if (IS_POST) {

    $POST_body = trim(file_get_contents("php://input"));

    if (SAVE_BARE_POST_REQUESTS) {
        SQL_Query("INSERT INTO " . TABLE_POSTREQUESTS . "(" . ITPINGS_POST_body . ") VALUES(" . Quoted($POST_body) . ")");
    }

    //global $request object processed by all above functions
    $request = json_decode($POST_body, TRUE); // TRUE return as Associative Array

    /**
     * Create a Ping entry as early as possible
     * The _pingid can then be used in all other Tables (primarly ttn__events)
     */
    $request[PRIMARYKEY_Ping] = SQL_Query(SQL_INSERT_INTO . TABLE_PINGS . " VALUES();");

    process_AllGateways();                  // get key_id or insert into 'pinged_gateways' and 'gateways' tables
    process_ApplicationDevice_Information();// get key_id or insert into 'applications, Devices, ApplicationDevices' tables
    process_Sensors_From_PayloadFields();   // get key_id or insert into 'sensors' and 'sensorvalues' tables
    process_POSTrequest_Update_Ping();      // update request info in main 'pings' Table

    echo "ITpings recorded a ping: " . $request[PRIMARYKEY_Ping];

} else { // it is a GET (JSON) request
    process_Query_with_QueryString_Parameters();
}
//endregion

mysqli_close($conn);

// lat/lon display
