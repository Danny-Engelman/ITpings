<?php
include('ITpings_configuration.php');

//region ===== MYSQL DATABASE ACCESS ==============================================================

$conn = mysqli_connect(DBHOST, DBUSERNAME, DBPASSWORD, DBNAME);

if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

/**
 * @param $str
 * @return mixed
 *
 * brutal approach against SQL injection attempts
 * Return first element after split on 'illegal' SQL characters
 */
function SQL_InjectionSave_OneWordString($str)
{
    return preg_split("/[&=:;]/", $str)[0];
}

/**
 * @param $sql
 * @param bool $returnJSON
 * @return array|int|null|string
 *
 */
function SQL_Query($sql, $returnJSON = FALSE)
{
    global $conn;

    $result = mysqli_query($conn, $sql);
    if ($result) {
        if (strpos($sql, 'INSERT') !== false) {
            return mysqli_insert_id($conn);
        } else {
            if ($returnJSON) {
                $rows = array();
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                }
                print json_encode($rows);
            } else {
                return mysqli_fetch_assoc($result); // return first $row
            }
        }
    } else {
        die($sql . "<br>" . mysqli_error($conn));
    }
    return false;
}

/**
 * @param $table_name
 * @param $fields_values
 *
 * @return number = Primary key for new Table entry
 */
function SQL_insert($table_name, $fields_values)
{
    $fields = '';
    foreach ($fields_values as $index => $value) {
        if ($index > 0) $fields .= COMMA;
        $fields .= $value;
    }
    return SQL_Query("INSERT INTO " . $table_name . " VALUES ( " . $fields . ");");
}


/**
 * @param $val
 * @param string $quote
 * @return string
 * Quote a given $val
 */
function Quoted($val, $quote = "'")
{
    global $conn;
    //https://www.npmjs.com/package/mysql#escaping-query-values
    return (isset($val) OR $val == 0) ? $quote . mysqli_real_escape_string($conn, $val) . $quote : "NULL";
}

/**
 * @param $val
 * @return string
 * (not) quote numbers
 */
function Valued($val)
{
    return Quoted($val, EMPTY_STRING); // No quotes
}

//endregion == MYSQL DATABASE ACCESS ==============================================================

//region ===== CREATE ITPINGS DATABASE TABLES AND VIEWS ===========================================

/** Create Database Schema with tables:
 * applications
 * devices
 * gateways
 * pings
 * pingedgateways
 * sensors
 * sensorvalues
 * POSTrequests
 * events
 **/
function create_ITpings_Tables_And_Views()
{
    //region ===== CREATE ITPINGS DATABASE : TABLES ===================================================

    function create_ITpings_Tables()
    {
        function OnKey($str)
        {// Make Foreign Key definitions better readable
            return "(" . $str . ")";
        }

        /**
         * @param $table_name
         * @param $idfield - can be False to indicate this table has NO primary key
         * @param $fields - array of ['fieldname','fieldtype','fieldcomment']
         * @param $foreignkeys - array of ['foreignkeyname','declaration']
         */
        function create_Table($table_name, $idfield, $fields, $foreignkeys)
        {
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (";
            if ($idfield) {
                $sql .= "$idfield INT(10) UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT COMMENT 'ITpings Primary Key' , ";
            }
            foreach ($fields as $index => $field) {
                if ($index > 0) $sql .= " , ";
                $sql .= " $field[0] $field[1] COMMENT '$field[2]'";
            }
            if (USE_REFERENTIAL_INTEGRITY) {
                foreach ($foreignkeys as $index => $key) {
                    $sql .= " , FOREIGN KEY ($key[0]) $key[1]";
                }
            }
            if ($idfield) {
                $sql .= " , PRIMARY KEY ($idfield)";
            }
            $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            SQL_Query($sql);

            insert_TTN_Event(ENUM_EVENTTYPE_NewTable, $table_name, $sql);
        }

        create_Table(TABLE_EVENTS
            , NO_PRIMARYKEY
            , [//Fields
                [PRIMARYKEY_Ping
                    , TYPE_FOREIGNKEY
                    , ITpings_PrimaryKey_In_Table . TABLE_PINGS]
                , [ITPINGS_EVENTTYPE
                    , TYPE_EVENTTYPE . " DEFAULT '" . ENUM_EVENTTYPE_Log . "'"
                    , "Event ENUM_EVENTTYPE values"]
                , [ITPINGS_EVENTLABEL
                    , TYPE_EVENTLABEL
                    , "Event label"]
                , [ITPINGS_EVENTVALUE
                    , TYPE_EVENTVALUE
                    , "Event text, can include POST BODY"]
            ]
            , NO_FOREIGNKEYS
        /** No Foreign Key so the Events table can be used for any entry **/
//            , [
//                [PRIMARYKEY_Ping
//                    , IS_A_FOREIGNKEY_IN . TABLE_PINGS . OnKey(PRIMARYKEY_Ping)]
//            ]
        );

        create_Table(TABLE_POSTREQUESTS
            , PRIMARYKEY_POSTrequests
            , [//Fields
                [ITPINGS_POST_body
                    , TYPE_POST_BODY
                    , "Bare POST body"]
            ]
            , NO_FOREIGNKEYS
        );

        create_Table(TABLE_APPLICATIONS
            , PRIMARYKEY_Application
            , [//Fields
                [TTN_app_id
                    , TYPE_TTN_APP_ID
                    , "TTN Application ID"]
                , [ITPINGS_DESCRIPTION
                    , TYPE_TTN_APP_DESCRIPTION
                    , "Description "]
            ]
            , NO_FOREIGNKEYS
        );

        create_Table(TABLE_DEVICES
            , PRIMARYKEY_Device
            , [//Fields
                [TTN_dev_id
                    , TYPE_TTN_DEVICE_ID
                    , "TTN Device ID"]
                , [TTN_hardware_serial
                    , TYPE_TTN_HARDWARE_SERIAL
                    , "TTN Application ID"]
            ], NO_FOREIGNKEYS
        );

        create_Table(TABLE_APPLICATIONDEVICES
            , PRIMARYKEY_ApplicationDevice
            , [//Fields
                [PRIMARYKEY_Application
                    , TYPE_FOREIGNKEY
                    , ITpings_PrimaryKey_In_Table . TABLE_APPLICATIONS]
                , [PRIMARYKEY_Device
                    , TYPE_FOREIGNKEY
                    , ITpings_PrimaryKey_In_Table . TABLE_DEVICES]
            ]
            , [
                [PRIMARYKEY_Application
                    , IS_A_FOREIGNKEY_IN . TABLE_APPLICATIONS . OnKey(PRIMARYKEY_Application)]

                , [PRIMARYKEY_Device
                    , IS_A_FOREIGNKEY_IN . TABLE_DEVICES . OnKey(PRIMARYKEY_Device)]
            ]
        );

        create_Table(TABLE_GATEWAYS
            , PRIMARYKEY_Gateway
            , [//Fields
                [TTN_gtw_id
                    , TYPE_TTN_GTW_ID
                    , "TTN Gateway ID"]
                , [TTN_gtw_trusted
                    , TYPE_TTN_TRUSTED_GTW
                    , "TTN Gateway Trusted"]
                , [ITPINGS_LATITUDE
                    , LATITUDE_ACCURACY
                    , "TTN Gateway Latitude"]
                , [ITPINGS_LONGITUDE
                    , LONGITUDE_ACCURACY
                    , "TTN Gateway Longitude"]
                , [ITPINGS_ALTITUDE
                    , ALTITUDE_ACCURACY
                    , "TTN Gateway Altitude"]
                , [ITPINGS_LOCATIONSOURCE
                    , TYPE_LOCATION_SOURCE
                    , "TTN (registry)"]
            ]
            , NO_FOREIGNKEYS
        );

//    create_Table(TABLE_FREQUENCIES, PRIMARYKEY_Frequency
//        , [//Fields
//            [TTN_frequency
//                , TYPE_TTN_FREQUENCY
//                , "TTN Frequency"]
//        ]
//        , NO_FOREIGNKEYS
//    );

        create_Table(TABLE_PINGS
            , PRIMARYKEY_Ping
            , [//Fields
                [ITPINGS_CREATED_TIMESTAMP
                    , " TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP "
                    , "Time Ping was Created in ITpings database"]
                , [PRIMARYKEY_ApplicationDevice
                    , TYPE_FOREIGNKEY
                    , ITpings_PrimaryKey_In_Table . TABLE_APPLICATIONDEVICES]
                , [TTN_port
                    , TYPE_TTN_PORT
                    , "TTN port number"]
                , [TTN_counter
                    , TYPE_TTN_FRAME_COUNTER
                    , "TTN Frame Counter"]
                , [TTN_downlink_url
                    , TYPE_TTN_DOWNLINK
                    , "TTN Downlink URI"]
                , [TTN_payload_raw
                    , TYPE_TTN_PAYLOAD_RAW
                    , "TTN Payload Raw format"]

                , [ITPINGS_TIME
                    , TYPE_TTN_TIMESTRING
                    , "TTN time"]

                //future: create lookup tables to reduce the size of pings Table by 16 bytes for each entry
                , [ITPINGS_FREQUENCY
                    , TYPE_TTN_FREQUENCY
                    , "TTN frequency"]//5 bytes save 4
                , [ITPINGS_MODULATION
                    , TYPE_TTN_MODULATION
                    , "TTN modulation"]//4 save 3
                , [ITPINGS_DATA_RATE
                    , TYPE_TTN_DATA_RATE
                    , "TTN data rate"]//8 save 7
                , [ITPINGS_CODING_RATE
                    , TYPE_TTN_CODING_RATE
                    , "TTN coding rate"]//3 save 2

                , [ITPINGS_LATITUDE
                    , LATITUDE_ACCURACY
                    , "TTN Ping Latitude"]
                , [ITPINGS_LONGITUDE
                    , LONGITUDE_ACCURACY
                    , "TTN Ping Longitude"]
                , [ITPINGS_ALTITUDE
                    , ALTITUDE_ACCURACY
                    , "TTN Ping Altitude"]
                , [ITPINGS_LOCATIONSOURCE
                    , TYPE_LOCATION_SOURCE
                    , "TTN (registry)"]
            ]
            , [
                [PRIMARYKEY_ApplicationDevice
                    , IS_A_FOREIGNKEY_IN . TABLE_APPLICATIONDEVICES . OnKey(PRIMARYKEY_ApplicationDevice)]
            ]
        );

        create_Table(TABLE_PINGEDGATEWAYS
            , NO_PRIMARYKEY
            , [//Fields
                [PRIMARYKEY_Ping
                    , TYPE_FOREIGNKEY
                    , ITpings_PrimaryKey_In_Table . TABLE_PINGS]
                , [PRIMARYKEY_Gateway
                    , TYPE_FOREIGNKEY
                    , ITpings_PrimaryKey_In_Table . TABLE_GATEWAYS]
                , [TTN_timestamp
                    , TYPE_TYPE_TIMESTAMP
                    , "TTN GatewayPing Timestamp"]
                , [TTN_time
                    , TYPE_TTN_TIMESTRING
                    , "TTN GatewayPing Time"]

                , [TTN_channel
                    , TYPE_TTN_CHANNEL
                    , "TTN GatewayPing Channel"]
                , [TTN_rssi
                    , TYPE_TTN_RSSI
                    , "TTN GatewayPing RSSI"]
                , [TTN_snr
                    , TYPE_TTN_SNR
                    , "TTN GatewayPing SNR"]
                , [TTN_rf_chain
                    , TYPE_TTN_RFCHAIN
                    , "TTN GatewayPing RFChain"]
            ]
            , [
                [PRIMARYKEY_Ping
                    , IS_A_FOREIGNKEY_IN . TABLE_PINGS . OnKey(PRIMARYKEY_Ping)]
                , [PRIMARYKEY_Gateway
                    , IS_A_FOREIGNKEY_IN . TABLE_GATEWAYS . OnKey(PRIMARYKEY_Gateway)]
            ]
        );


        create_Table(TABLE_SENSORS
            , PRIMARYKEY_Sensor
            , [//Fields
                [PRIMARYKEY_ApplicationDevice
                    , TYPE_FOREIGNKEY
                    , ITpings_PrimaryKey_In_Table . TABLE_APPLICATIONDEVICES]
                , [ITPINGS_SENSORNAME
                    , TYPE_PAYLOAD_KEY
                    , "TTN Payload key"]
            ]
            , [
                [PRIMARYKEY_ApplicationDevice
                    , IS_A_FOREIGNKEY_IN . TABLE_APPLICATIONDEVICES . OnKey(PRIMARYKEY_ApplicationDevice)]
            ]
        );

        create_Table(TABLE_SENSORVALUES
            , NO_PRIMARYKEY
            , [//Fields
                [PRIMARYKEY_Ping
                    , TYPE_FOREIGNKEY
                    , ITpings_PrimaryKey_In_Table . TABLE_PINGS]
                , [PRIMARYKEY_Sensor
                    , TYPE_FOREIGNKEY
                    , ITpings_PrimaryKey_In_Table . TABLE_SENSORS]
                , [ITPINGS_SENSORVALUE
                    , TYPE_PAYLOAD_VALUE
                    , "TTN Payload value"]
            ]
            , [//Foreign Keys
                [PRIMARYKEY_Ping
                    , IS_A_FOREIGNKEY_IN . TABLE_PINGS . OnKey(PRIMARYKEY_Ping)],
                [PRIMARYKEY_Sensor
                    , IS_A_FOREIGNKEY_IN . TABLE_SENSORS . OnKey(PRIMARYKEY_Sensor)]
            ]
        );

    }//end function createTables

    //endregion == CREATE ITPINGS DATABASE : TABLES ===================================================

    //region ===== CREATE ITPINGS DATABASE : VIEWS ====================================================

    function create_ITpings_Views()
    {
        foreach (ITPINGS_VIEWNAMES as $view_name) {

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
                    $view .= " , A." . TTN_app_id . " , A." . ITPINGS_DESCRIPTION;
                    $view .= " , D." . TTN_dev_id . " , D." . TTN_hardware_serial;
                    $view .= " FROM " . TABLE_APPLICATIONDEVICES . " AD ";
                    $view .= " JOIN " . TABLE_APPLICATIONS . " A ON A." . PRIMARYKEY_Application . " = AD." . PRIMARYKEY_Application;
                    $view .= " JOIN " . TABLE_DEVICES . " D ON D." . PRIMARYKEY_Device . " = AD." . PRIMARYKEY_Device;
                    $view .= " ORDER BY A." . TTN_app_id . " ASC, D." . TTN_dev_id . " ASC";
                    break;
                case VIEWNAME_SENSORVALUES:
                    $view .= " P." . PRIMARYKEY_Ping . " , P." . ITPINGS_CREATED_TIMESTAMP;
                    $view .= " , S." . PRIMARYKEY_Sensor;
                    if (VIEWS_WITH_EXPANDED_KEYS) {
                        $view .= " , AD." . PRIMARYKEY_ApplicationDevice . " , AD." . TTN_app_id . " , AD." . TTN_dev_id . " , AD." . TTN_hardware_serial;
                        $view .= " , S." . ITPINGS_SENSORNAME;
                    }
                    $view .= " , SV." . ITPINGS_SENSORVALUE;
                    $view .= " FROM " . TABLE_SENSORVALUES . " SV ";
                    $view .= " JOIN " . TABLE_SENSORS . " S ON S." . PRIMARYKEY_Sensor . " = SV." . PRIMARYKEY_Sensor;
                    $view .= " JOIN " . TABLE_PINGS . " P ON P." . PRIMARYKEY_Ping . " = SV." . PRIMARYKEY_Ping;
                    if (VIEWS_WITH_EXPANDED_KEYS) {
                        $view .= " JOIN " . VIEWNAME_APPLICATIONDEVICES . " AD ON AD." . PRIMARYKEY_ApplicationDevice . " = S." . PRIMARYKEY_ApplicationDevice;
                    }
                    $view .= " ORDER BY " . ITPINGS_CREATED_TIMESTAMP . " DESC, SV." . PRIMARYKEY_Sensor;
                    break;
                case VIEWNAME_PINGEDGATEWAYS:
                    $view .= " P." . PRIMARYKEY_Ping . ",P." . ITPINGS_CREATED_TIMESTAMP;
                    $view .= " , PG." . TTN_timestamp . ",PG." . TTN_time . ",PG." . TTN_channel . ",PG." . TTN_rssi . ",PG." . TTN_snr . ",PG." . TTN_rf_chain;
                    $view .= " , G.* ";
                    $view .= " FROM " . TABLE_PINGEDGATEWAYS . " PG ";
                    $view .= " JOIN " . TABLE_PINGS . " P ON P." . PRIMARYKEY_Ping . " = PG." . PRIMARYKEY_Ping;
                    $view .= " JOIN " . TABLE_GATEWAYS . " G ON G." . PRIMARYKEY_Gateway . " = PG." . PRIMARYKEY_Gateway;
                    $view .= " ORDER BY " . ITPINGS_CREATED_TIMESTAMP . " DESC";
                    break;
            }

            $sql = "CREATE OR REPLACE VIEW $view_name AS SELECT " . $view;
            SQL_Query($sql);

            insert_TTN_Event(ENUM_EVENTTYPE_NewView, $view_name, $sql);

        }
    }

    //region ===== CREATE ITPINGS DATABASE : VIEWS ====================================================

    create_ITpings_Tables();
    create_ITpings_Views();
}

//endregion == CREATE ITPINGS DATABASE TABLES AND VIEWS ===========================================

//region ===== PROCESSING POST REQUEST SQL FUNCTIONS ==============================================

/**
 * @param $event_type
 * @param $event_label
 * @param string $event_value
 */
function insert_TTN_Event($event_type, $event_label, $event_value = '')
{
    global $request;

    $pingid = $request[PRIMARYKEY_Ping];
    if (!$pingid) $pingid = 0;

    SQL_insert(TABLE_EVENTS
        , [//values
            $pingid
            , Quoted($event_type)
            , Quoted($event_label)
            , Quoted($event_value)
        ]);
}

/**
 * @param $table_name
 * @param $where_clause
 * @param $key_field
 *
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
function post_process_Ping()
{
    global $request;

    $sql = "UPDATE " . TABLE_PINGS . " SET ";
    $sql .= PRIMARYKEY_ApplicationDevice . "=" . Valued($request[PRIMARYKEY_ApplicationDevice]);
    $sql .= COMMA . TTN_port . "=" . Valued($request[TTN_port]);
    $sql .= COMMA . TTN_counter . "=" . Valued($request[TTN_counter]);

    //create a shorter string by removing the baseparh to save Database bytes
    $downlink_url = str_replace(TTN_DOWNLINKROOT, EMPTY_STRING, $request[TTN_downlink_url]);
    $sql .= COMMA . TTN_downlink_url . "=" . Quoted($downlink_url);
    $sql .= COMMA . TTN_payload_raw . "=" . Quoted($request[TTN_payload_raw]);

    $metadata = $request[TTN_metadata];
    $sql .= COMMA . ITPINGS_TIME . "=" . Quoted($metadata[TTN_time]);//str_replace(array('-',':','/'),)
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

//endregion == PROCESSING POST REQUEST SQL FUNCTIONS ==============================================

//region ===== PROCESS ITPINGS GET QUERY ==========================================================
/**
 *
 */
function process_Query_with_QueryString_Parameters()
{
    global $urlVars;
    $sql = EMPTY_STRING;

    $table_name = TABLE_PREFIX . $urlVars['query'];

    /** User can only request for limitted table/view names, this is the place to deny access to some Tables **/
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
        /** Built a safe SQL query **/
        $where = EMPTY_STRING;
        $order = EMPTY_STRING;
        $limit = EMPTY_STRING;
        foreach (VALID_QUERY_PARAMETERS as $parameter) {
            $parameter_value = SQL_InjectionSave_OneWordString($urlVars[$parameter]);
            if ($parameter_value) {
                $PARAMETER_HAS_SEPARATOR = strpos($parameter_value, QUERY_PARAMETER_SEPARATOR) !== FALSE;
                $and = $where === EMPTY_STRING ? EMPTY_STRING : " AND ";
                switch ($parameter) {

                    case QUERY_PARAMETER_FILTER:
                        /** Process: ' ... &filter=_devid ge 1,_appid lt 2'     **/
                        // Documentation OData Filters: https://msdn.microsoft.com/en-us/library/hh169248(v=nav.90).aspx
                        // MySQL: https://dev.mysql.com/doc/refman/5.7/en/non-typed-operators.html
                        foreach (explode(QUERY_PARAMETER_SEPARATOR, $parameter_value) as $filter) {
                            $where_filter = explode(' ', $filter);
                            $where .= $and . $where_filter[0];              // fieldname
                            $operator = strtolower($where_filter[1]);       // lt gt ge le
                            $value = $where_filter[2];
                            if ($operator === 'lt') $where .= " < ";
                            elseif ($operator === 'gt') $where .= " > ";
                            elseif ($operator === 'ge') $where .= " >= ";
                            elseif ($operator === 'le') $where .= " <= ";
                            $where .= $value;
                        }
                        break;

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
        $sql .= $where === EMPTY_STRING ? $where : " WHERE " . $where;
        $sql .= $order . ($limit === EMPTY_STRING ? " LIMIT " . SQL_LIMIT_DEFAULT : $limit);
    }

    SQL_Query($sql, TRUE);

}

//endregion == PROCESS ITPINGS GET QUERY ==========================================================

//region ===== ITpings - MAIN CODE ================================================================

//Process QueryString variables
$urlVars = array();
parse_str($_SERVER['QUERY_STRING'], $urlVars);

//** CREATE ITpings Database Schema IF it does not exist, can be commented out if your happy with your Database
$ITpings_DatabaseInfo = SQL_Query("SELECT * FROM information_schema.tables WHERE table_name LIKE 'ITpings%' ORDER BY TABLE_TYPE ASC");
if (!$ITpings_DatabaseInfo) {
    create_ITpings_Tables_And_Views();
    echo "Created ITpings Database Schema";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' AND YOUR_ITPINGS_KEY === $urlVars['key']) {

    $POST_body = trim(file_get_contents("php://input"));

    if (SAVE_POST_AS_STRING_TO_A_SINGLE_POSTrequests_TABLE) {
        SQL_Query("INSERT INTO " . TABLE_POSTREQUESTS . "(" . ITPINGS_POST_body . ") VALUES(" . Quoted($POST_body) . ")");
    }

    /** global $request object is processed by all above functions **/
    $request = json_decode($POST_body, TRUE); // TRUE returns as Associative Array

    /** Create Ping DB entry asap, _pingid can then be used in all other Tables (primarly ttn__events) **/
    $request[PRIMARYKEY_Ping] = SQL_Query("INSERT INTO " . TABLE_PINGS . " VALUES();");
    process_AllGateways();                  // get key_id or insert into 'pinged_gateways' and 'gateways' tables
    process_ApplicationDevice_Information();// get key_id or insert into 'applications, Devices, ApplicationDevices' tables
    process_Sensors_From_PayloadFields();   // get key_id or insert into 'sensors' and 'sensorvalues' tables
    post_process_Ping();                    // update $request info in main 'pings' Table

    echo "ITpings recorded a ping: " . $request[PRIMARYKEY_Ping];

} else { // GET (JSON) request
    switch ($urlVars['action']) {
        case 'droptables':
            foreach (ITPINGS_TABLES as $index => $table_name) {
                SQL_Query("DROP TABLE IF EXISTS $table_name;");
            }
            foreach (ITPINGS_VIEWNAMES as $index => $view) {
                SQL_Query("DROP VIEW IF EXISTS $view;");
            }
            break;
        default:
            process_Query_with_QueryString_Parameters();
    }
}

mysqli_close($conn);

//endregion === ITpings - MAIN CODE ================================================================