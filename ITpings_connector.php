<?php
/* In a decent IDE, press Ctrl+Shift+Minus to collapse all code blocks */

include('ITpings_configuration.php');

//region ===== HELPER FUNCTIONS ===================================================================

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
 * returns TRUE when $lat and $lon have no decimals (most likely a fake location)
 * @param $lat
 * @param $lon
 * @return bool
 */
function is_Location_without_Decimals($lat, $lon)
{
    return (floor($lat) == $lat AND floor($lon) == $lon);
}

//endregion == HELPER FUNCTIONS ===================================================================

//region ===== MYSQL DATABASE ACCESS ==============================================================

$MySQL_DB_Connection = mysqli_connect(DBHOST, DBUSERNAME, DBPASSWORD, DBNAME);

if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

$JSON_response = array(
    'mysqlversion' => mysqli_get_server_info($MySQL_DB_Connection)
);
/**
 * @param $sql
 * @param bool $returnJSON
 * @return array|int|null|string
 *
 */
function SQL_Query($sql, $returnJSON = FALSE)
{
    global $MySQL_DB_Connection;
    global $JSON_response;

    $JSON_response['sql'] = str_replace(TABLE_PREFIX, '', $sql);

    $result = mysqli_query($MySQL_DB_Connection, $sql);
    if ($result) {
        if ($returnJSON) {
            $rows = array();
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            // http://nitschinger.at/Handling-JSON-like-a-boss-in-PHP/
            header('Content-type: application/json');
            $JSON_response['result'] = $rows;
            print json_encode($JSON_response);
        } else {
            return mysqli_fetch_assoc($result); // return first $row
        }
    } else {
        $JSON_response += array('error' => mysqli_error($MySQL_DB_Connection));
        print json_encode($JSON_response);
    }
    return false;
}

/**
 * @param $table_name
 * @param $fieldvalues
 *
 * @return number = Primary key for new Table entry
 */
function SQL_INSERT($table_name, $fieldvalues)
{
    global $MySQL_DB_Connection;

    $sql = "INSERT INTO $table_name VALUES ( " . implode(COMMA, $fieldvalues) . ");";
    $result = mysqli_query($MySQL_DB_Connection, $sql);
    return ($result) ? mysqli_insert_id($MySQL_DB_Connection) : 0;
}

function SQL_EscapeString($str)
{
    global $MySQL_DB_Connection;
    //https://www.npmjs.com/package/mysql#escaping-query-values
    return mysqli_real_escape_string($MySQL_DB_Connection, $str);
}

/**
 * @param $val
 * @param string $quote
 * @return string
 * Quote a given $val
 */
function Quoted($val, $quote = "'")
{
    return (isset($val) OR $val == 0) ? $quote . SQL_EscapeString($val) . $quote : "NULL";
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

//region ===== CREATE ITPINGS DATABASE : TABLES ===================================================
/** Create Database Schema with tables:
 * events
 * origins
 * locations
 * frequencies
 * modulations
 * data_rates
 * coding_rates
 * applications
 * devices
 * gateways
 * pings
 * pingedgateways
 * sensors
 * sensorvalues
 * POSTrequests
 **/

function create_ITpings_Tables()
{
    /**
     * @param $table_name
     * @param $primary_key_name - can be False to indicate this table has NO primary key
     * @param $primary_key_type
     * @param $fields - array of ['fieldname','fieldtype','fieldcomment']
     * @param $foreignkeys - array of ['foreignkeyname','declaration']
     */
    function create_Table($table_name, $primary_key_name, $primary_key_type, $fields, $foreignkeys)
    {
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (";
        if ($primary_key_name) {
            $sql .= "$primary_key_name $primary_key_type UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT COMMENT 'ITpings Primary Key' , ";
        }
        foreach ($fields as $index => $field) {
            if ($index > 0) $sql .= " , ";
            $sql .= " $field[0] $field[1] COMMENT '$field[2]'";
        }
        foreach ($foreignkeys as $index => $key) {
            $sql .= " , FOREIGN KEY ($key[0]) $key[1]";
        }
        if ($primary_key_name) {
            $sql .= " , PRIMARY KEY ($primary_key_name)";
        }
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        echo $sql . "<br><br>";
        SQL_Query($sql);

        insert_TTN_Event(ENUM_EVENTTYPE_NewTable, $table_name, $sql);
    }

    create_Table(TABLE_EVENTS
        , NO_PRIMARYKEY
        , FALSE
        , [//Fields
            DBFIELD_PRIMARYKEY_PING
            , DBFIELD_EVENTTYPE
            , DBFIELD_EVENTLABEL
            , DBFIELD_EVENTVALUE
        ]
        , NO_FOREIGNKEYS/** No Foreign Key so the Events table can be used for any entry **/
    );

    if (SAVE_POST_AS_ONE_STRING) {
        create_Table(TABLE_POSTREQUESTS
            , PRIMARYKEY_POSTrequests
            , TYPE_FOREIGNKEY
            , [//Fields
                DBFIELD_POST_BODY
            ]
            , NO_FOREIGNKEYS
        );
    }

    create_Table(TABLE_APPLICATIONS
        , PRIMARYKEY_Application
        , TYPE_FOREIGNKEY
        , [DBFIELD_APPLICATION_ID
            , DBFIELD_APPLICATION_DESCRIPTION]
        , NO_FOREIGNKEYS
    );

    create_Table(TABLE_DEVICES
        , PRIMARYKEY_Device
        , TYPE_FOREIGNKEY
        , [DBFIELD_DEVICE_ID
            , DBFIELD_HARDWARE_SERIAL
        ]
        , NO_FOREIGNKEYS
    );

    create_Table(TABLE_APPLICATIONDEVICES
        , PRIMARYKEY_ApplicationDevice
        , TYPE_FOREIGNKEY
        , [//Fields
            DBFIELD_PRIMARYKEY_APPLICATION
            , DBFIELD_PRIMARYKEY_DEVICE
        ]
        , [FOREIGNKEY_APPLICATIONS, FOREIGNKEY_DEVICES]
    );

    function create_LookupTable($table, $primary_key_name, $field_name, $field_datatype)
    {
        create_Table(
            $table
            , $primary_key_name
            , TYPE_FOREIGNKEY_LOOKUPTABLE
            , [[$field_name, $field_datatype, "TTN " . $field_name]]
            , NO_FOREIGNKEYS
        );

        // return declaration for Lookup table
        return [
            $primary_key_name
            , TYPE_FOREIGNKEY_LOOKUPTABLE
            , PrimaryKey_In . $table];
    }

    $frequency_declaration = create_LookupTable(TABLE_FREQUENCIES
        , PRIMARYKEY_Frequency
        , ITPINGS_FREQUENCY
        , TYPE_FREQUENCY);
    $modulation_declaration = create_LookupTable(TABLE_MODULATIONS
        , PRIMARYKEY_Modulation
        , ITPINGS_MODULATION
        , TYPE_MODULATION);
    $datarate_declaration = create_LookupTable(TABLE_DATARATES
        , PRIMARYKEY_Datarate
        , ITPINGS_DATA_RATE
        , TYPE_DATA_RATE);
    $codingrate_declaration = create_LookupTable(TABLE_CODINGRATES
        , PRIMARYKEY_Codingrate
        , ITPINGS_CODING_RATE
        , TYPE_CODING_RATE);
    $origin_declaration = create_LookupTable(TABLE_ORIGINS
        , PRIMARYKEY_Origin
        , ITPINGS_ORIGIN
        , TYPE_ITPINGS_ORIGIN);

    create_Table(TABLE_LOCATIONS
        , PRIMARYKEY_Location
        , TYPE_FOREIGNKEY
        , [DBFIELD_LATITUDE
            , DBFIELD_LONGITUDE
            , DBFIELD_ALTITUDE
            , DBFIELD_LOCATION_SOURCE
        ]
        , NO_FOREIGNKEYS
    );

    create_Table(TABLE_GATEWAYS
        , PRIMARYKEY_Gateway
        , TYPE_FOREIGNKEY
        , [DBFIELD_GATEWAY_ID
            , DBFIELD_TRUSTED_GATEWAY
            , DBFIELD_LOCATION
        ]
        , [FOREIGNKEY_LOCATIONS]
    );

    create_Table(TABLE_PINGS
        , PRIMARYKEY_Ping
        , TYPE_FOREIGNKEY
        , [//Fields
            DBFIELD_CREATED_TIMESTAMP
            , DBFIELD_APPLICATION_DEVICE

            , DBFIELD_PORT
            , DBFIELD_FRAME_COUNTER
            , DBFIELD_DOWNLINKURL
            , DBFIELD_PAYLOAD_RAW

            , DBFIELD_ITPINGS_TIME

            , $frequency_declaration
            , $modulation_declaration
            , $datarate_declaration
            , $codingrate_declaration

            , DBFIELD_LOCATION

            , $origin_declaration
        ]
        , [FOREIGNKEY_APPLICATIONDEVICES]
    );

    create_Table(TABLE_PINGEDGATEWAYS
        , NO_PRIMARYKEY
        , FALSE
        , [//Fields
            DBFIELD_PRIMARYKEY_PING
            , DBFIELD_GATEWAY
            , DBFIELD_PINGED_GATEWAY_TIMESTAMP
            , DBFIELD_ITPINGS_TIME
            , DBFIELD_CHANNEL
            , DBFIELD_RSSI
            , DBFIELD_SNR
            , DBFIELD_RFCHAIN
        ]
        , [FOREIGNKEY_PINGS, FOREIGNKEY_GATEWAYS]
    );

    create_Table(TABLE_SENSORS
        , PRIMARYKEY_Sensor
        , TYPE_FOREIGNKEY
        , [//Fields
            DBFIELD_APPLICATION_DEVICE
            , DBFIELD_SENSORNAME
        ]
        , [FOREIGNKEY_APPLICATIONDEVICES]
    );

    create_Table(TABLE_SENSORVALUES
        , NO_PRIMARYKEY
        , FALSE
        , [//Fields
            DBFIELD_PRIMARYKEY_PING
            , DBFIELD_PRIMARYKEY_SENSOR
            , DBFIELD_SENSORVALUE
        ]
        , [FOREIGNKEY_PINGS, FOREIGNKEY_SENSORS]
    );

}//end function createTables

//endregion == CREATE ITPINGS DATABASE : TABLES ===================================================

//region ===== CREATE ITPINGS DATABASE : VIEWS ====================================================

function create_ITpings_Views()
{
    function echoView($sql)
    {
        echo "<pre>" . $sql . "</pre>";
    }

    foreach (ITPINGS_VIEWNAMES as $view_name) {
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
                $view .= " , S." . PRIMARYKEY_Sensor;
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
            case VIEWNAME_GATEWAYS:
                $view .= " G." . PRIMARYKEY_Gateway . " , G." . ITPINGS_GATEWAY_ID;
                $view .= " ,G." . ITPINGS_TRUSTED;
                $view .= " ,L." . ITPINGS_LATITUDE . " , L." . ITPINGS_LONGITUDE;
                $view .= " ,L." . ITPINGS_ALTITUDE;
                $view .= " ,L." . ITPINGS_LOCATION_SOURCE;
                $view .= " FROM " . TABLE_GATEWAYS . " G ";
                $view .= " JOIN " . TABLE_LOCATIONS . " L ON L." . PRIMARYKEY_Location . " = G." . PRIMARYKEY_Location;
                $view .= " ORDER BY G." . ITPINGS_GATEWAY_ID . " DESC";
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
        }

        SQL_Query($sql . $view);

        insert_TTN_Event(ENUM_EVENTTYPE_NewView, $view_name, $sql);

    }
}

//endregion == CREATE ITPINGS DATABASE : VIEWS ====================================================


//region ===== CUSTOMIZABLE SENSOR TRIGGERS =======================================================

/**
 * For every Sensor reading process custom Triggers (like a Button clock)
 * @param $sensor_name
 * @param $sensor_value
 **/
function check_Event_Trigger_For_Sensor($sensor_name, $sensor_value)
{
    global $request;

    $IS_BUTTONCLICKED = ($sensor_name === TTN_Cayenne_digital_in_1 && $sensor_value === 1);

    if ($IS_BUTTONCLICKED) {
        insert_TTN_Event(ENUM_EVENTTYPE_Trigger, 'ButtonClicked', $request[TTN_dev_id]);
    }
}

//endregion == CUSTOMIZABLE SENSOR TRIGGERS =======================================================

//region ===== PROCESS POST REQUEST , SAVE DATA TO ALL TABLES =====================================

/**
 * Insert an event in the Events Table
 *
 * @param $event_type
 * @param $event_label
 * @param string $event_value
 */
function insert_TTN_Event($event_type, $event_label, $event_value = '')
{
    global $request;

    $pingid = $request[PRIMARYKEY_Ping];
    if (!$pingid) $pingid = 1;  // force logging of Database creation on ping 1

    SQL_INSERT(TABLE_EVENTS
        , [//values
            $pingid
            , Quoted($event_type)
            , Quoted($event_label)
            , Quoted($event_value)
        ]);
}

/**
 * Find a matching row in the database, or NULL for non found
 *
 * @param $table_name
 * @param $where_clause
 * @param $primary_key_field
 *
 * @return array|null
 */
function SQL_find_existing_key_id($primary_key_field, $table_name, $where_clause)
{
    $sql = "SELECT $primary_key_field FROM $table_name WHERE $where_clause ORDER BY $primary_key_field DESC LIMIT 1;";
    return SQL_Query($sql)[$primary_key_field];
}

/**
 * find existing value in a table, OR create new entry
 * Returns a String for a SQL Insert statement
 *
 * @param $table
 * @param $primarykey
 * @param $db_field
 * @param $lookup_field
 * @param $lookup_value
 * @return string
 */
function process_Lookup($table, $primarykey, $lookup_field, $lookup_value)
{
    global $request;
    // is there a value in the lookup table?
    $key_id = SQL_find_existing_key_id($primarykey, $table, $lookup_field . "=" . $lookup_value);

    if (!$key_id) {
        insert_TTN_Event(ENUM_EVENTTYPE_Log, 'New' . $lookup_field, $lookup_value);

        // create value in lookup table
        $key_id = SQL_INSERT($table, [AUTOINCREMENT_TABLE_PRIMARYKEY, $lookup_value]);
    }
    $request[$primarykey] = $key_id;
    return COMMA . $primarykey . "=" . $key_id;
}

/**
 * find an existing Application or create a new Application
 * sets the found/created ID value in the global $request object
 */
function process_Application()
{
    global $request;
    $request_Application_ID = $request[TTN_app_id];

    $key_id = SQL_find_existing_key_id(
        PRIMARYKEY_Application
        , TABLE_APPLICATIONS
        , ITPINGS_APPLICATION_ID . "=" . Quoted($request_Application_ID)

    );

    if (!$key_id) {
        insert_TTN_Event(
            ENUM_EVENTTYPE_NewApp
            , $request_Application_ID
        );

        $key_id = SQL_INSERT(
            TABLE_APPLICATIONS
            , [//values
            AUTOINCREMENT_TABLE_PRIMARYKEY
            , Quoted($request_Application_ID)
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
    $request_Device_ID = $request[TTN_dev_id];

    $key_id = SQL_find_existing_key_id(
        PRIMARYKEY_Device
        , TABLE_DEVICES
        , ITPINGS_DEVICE_ID . "=" . Quoted($request_Device_ID)
    );

    if (!$key_id) {
        insert_TTN_Event(
            ENUM_EVENTTYPE_NewDevice
            , $request_Device_ID
        );

        $key_id = SQL_INSERT(
            TABLE_DEVICES
            , [//values
            AUTOINCREMENT_TABLE_PRIMARYKEY
            , Quoted($request_Device_ID)
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
        $key_id = SQL_INSERT(
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
 * find an existing GEO location create a new Location
 * sets the found/created ID value in the global $request object
 * returns the existing OR new ID
 * @param $lat
 * @param $lon
 * @param $alt
 * @param $location_source
 * @return array|null|number
 */
function process_Ping_and_Gateway_Location($lat, $lon, $alt, $location_source)
{
    global $request;

    if ($location_source === 'registry') {
        $location_source = 1;
    } else {
        insert_TTN_Event(ENUM_EVENTTYPE_Error, 'invalid LocationSource', $location_source);
        $location_source = 0;
    };

    //first check coordinates without height
    $where = ITPINGS_LATITUDE . "=$lat AND " . ITPINGS_LONGITUDE . "=$lon";
    $key_id = SQL_find_existing_key_id(PRIMARYKEY_Location, TABLE_LOCATIONS, $where);

    if (!$key_id) {
        // New Location
        $key_id = SQL_INSERT(
            TABLE_LOCATIONS
            , [AUTOINCREMENT_TABLE_PRIMARYKEY
            , $lat
            , $lon
            , $alt
            , $location_source
        ]);
        insert_TTN_Event(ENUM_EVENTTYPE_NewLocation, $lat, $lon);

    } else { //existing location, now check Height
        if (CHECK_HEIGHT_FOR_PING) {

            $where .= " AND " . ITPINGS_ALTITUDE . "=$alt";
            $height_key_id = SQL_find_existing_key_id(PRIMARYKEY_Location, TABLE_LOCATIONS, $where);
            if (!$height_key_id) {
                insert_TTN_Event(ENUM_EVENTTYPE_Log, 'Height difference location', $key_id);
                //the key_id for the lat/lon (excluding alt) is used
            } else {
                $key_id = $height_key_id;
            }
        }
    }

    $request[PRIMARYKEY_Location] = $key_id;
    return $key_id;
}

/**
 * Find a Gateway or create a new Gateway
 *
 * @param $gateway Object from the POST request
 * @return number ID value of existing/new Gateway
 */
function process_SingleGateway($gateway)
{
    // GPS has inaccurate fixes,
    // to prevent 'moving' Gateway recordings a tolerance for lat/lon is calculated

    $request_GateWay_ID = $gateway[TTN_gtw_id];
    $lat = $gateway[TTN_latitude];
    $lon = $gateway[TTN_longitude];
    $alt = $gateway[TTN_altitude];

    /**
     * Find matching Gateway ID, OVER x metres distance, the Gateway is recorded again (as being moved)
     *
     * @param $gtwid
     * @param $latitude
     * @param $longitude
     * @return array|null
     */
    function find_Nearest_Gateway_With_Same_ID($gtwid, $latitude, $longitude)
    {
        //  refactor: use __locations table for Gateway location too
        // select from View Gateways, Join on gateways+locations
        // or select from locations, then find id in Gateways

        $sql = "SELECT * ";
        if ($latitude) {
            $GTWradLAT = deg2rad($latitude);
            $GTWradLON = deg2rad($longitude);
            $radius = 6371;// 6371 for Kilometers, 3959 for Miles
            $sql .= " , ($radius*acos(cos($GTWradLAT)*cos(radians(" . ITPINGS_LATITUDE . "))";
            $sql .= "*cos(radians(" . ITPINGS_LONGITUDE . ")-$GTWradLON )+sin($GTWradLAT )";
            $sql .= "*sin(radians(" . ITPINGS_LATITUDE . ")))) AS distance";
        }

        $sql .= " FROM " . VIEWNAME_PINGEDGATEWAYS . " WHERE " . ITPINGS_GATEWAY_ID . "=" . Quoted($gtwid);
        if ($latitude) { // GPS has inaccurate fixes, to prevent 'moving' Gateway recordings a tolerance for lat/lon is calculated
            $sql .= " HAVING distance < " . GATEWAY_POSITION_TOLERANCE . " ORDER BY distance;";
        } else {
            $sql .= " ORDER BY " . PRIMARYKEY_Gateway . " DESC;";
        }

        return SQL_Query($sql);
    }

    $table_row = find_Nearest_Gateway_With_Same_ID($request_GateWay_ID, $lat, $lon);

    if ($table_row) {// found a Gateway
        $key_id = $table_row[PRIMARYKEY_Gateway];
        if (!$lat) {
            insert_TTN_Event(ENUM_EVENTTYPE_Log, "Gateway without location: $request_GateWay_ID", json_encode($gateway));
        } else if (is_Location_without_Decimals($lat, $lon)) {
            insert_TTN_Event(ENUM_EVENTTYPE_Log, "Suspicious lat/lon location: $lat / $lon", json_encode($gateway));
        }
    } else {
        //record a New Gateway
        insert_TTN_Event(ENUM_EVENTTYPE_NewGateway, $request_GateWay_ID, json_encode($gateway));

        $key_id = SQL_INSERT(
            TABLE_GATEWAYS
            , [//values
            AUTOINCREMENT_TABLE_PRIMARYKEY
            , Quoted($request_GateWay_ID)
            , Quoted($gateway[TTN_gtw_trusted])
            , process_Ping_and_Gateway_Location($lat, $lon, $alt, $gateway[TTN_location_source])
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
    $request_Gateways = $request[TTN_metadata][TTN_gateways];

    foreach ($request_Gateways as $gateway) {

        $gatewayID = process_SingleGateway($gateway); // Find known Gateway, else save new Gateway

        if (in_array($gatewayID, $processedGateways_InRequest)) {
            insert_TTN_Event(
                ENUM_EVENTTYPE_Error
                , "Duplicate TTN Gateway" . $gatewayID
                , json_encode($request_Gateways)
            );
        } else {
            array_push($processedGateways_InRequest, $gatewayID);

            SQL_INSERT(
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
 * Purge old downlink_raw / timestamp to compress database size
 * @param $latest_ping_id
 */
function purge_expired_Ping_data($latest_ping_id)
{
    // Timestamp is an integer field, so reset to 0 does not save any bytes

    if ($latest_ping_id > PURGE_PINGCOUNT) {
        $sql = "UPDATE " . TABLE_PINGS . " SET ";
        $sql .= ITPINGS_DOWNLINKURL . "='' , " . ITPINGS_PAYLOAD_RAW . "=''";
        $sql .= " WHERE " . PRIMARYKEY_Ping . "=" . ($latest_ping_id - PURGE_PINGCOUNT);
        SQL_Query($sql);
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
    $sql .= COMMA . ITPINGS_PORT . "=" . Valued($request[TTN_port]);
    $sql .= COMMA . ITPINGS_FRAME_COUNTER . "=" . Valued($request[TTN_counter]);

    // To save database space these fields will be reset to empty values by the purge_expired_Ping_data() function
    $sql .= COMMA . ITPINGS_DOWNLINKURL . "=" . Quoted($request[TTN_downlink_url]);
    $sql .= COMMA . ITPINGS_PAYLOAD_RAW . "=" . Quoted($request[TTN_payload_raw]);


    $metadata = $request[TTN_metadata];

    $sql .= COMMA . ITPINGS_TIME . "=" . Quoted($metadata[TTN_time]);

    $sql .= process_Lookup(
        TABLE_FREQUENCIES, PRIMARYKEY_Frequency
        , ITPINGS_FREQUENCY, Valued($metadata[TTN_frequency]));
    $sql .= process_Lookup(
        TABLE_MODULATIONS, PRIMARYKEY_Modulation
        , ITPINGS_MODULATION, Quoted($metadata[TTN_modulation]));
    $sql .= process_Lookup(
        TABLE_DATARATES, PRIMARYKEY_Datarate
        , ITPINGS_DATA_RATE, Quoted($metadata[TTN_data_rate]));
    $sql .= process_Lookup(
        TABLE_CODINGRATES, PRIMARYKEY_Codingrate
        , ITPINGS_CODING_RATE, Quoted($metadata[TTN_coding_rate]));

    $sql .= COMMA . PRIMARYKEY_Location . "="
        . process_Ping_and_Gateway_Location(
            $metadata[TTN_latitude]
            , $metadata[TTN_longitude]
            , $metadata[TTN_altitude]
            , $metadata[TTN_location_source]);

    $sql .= process_Lookup(
        TABLE_ORIGINS, PRIMARYKEY_Origin
        , ITPINGS_ORIGIN, Quoted(PING_ORIGIN));

    $sql .= " WHERE " . PRIMARYKEY_Ping . "=" . $request[PRIMARYKEY_Ping];

    SQL_Query($sql);

    purge_expired_Ping_data($request[PRIMARYKEY_Ping]);
}

/**
 * Find an existing ApplicationDevice Sensor OR create a new Sensor
 *
 * @param $sensor_name
 * @return array ID value of existing/new Sensor
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

        $key_id = SQL_INSERT(
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

        SQL_INSERT(
            TABLE_SENSORVALUES
            , [//values
            $request[PRIMARYKEY_Ping]
            , Valued($sensor_ID)
            , Quoted($sensor_value)
        ]);

        check_Event_Trigger_For_Sensor($sensor_name, $sensor_value);
    }
}

//endregion == PROCESS POST REQUEST , SAVE DATA TO ALL TABLES =================================================

//region ===== PROCESS GET QUERY ==================================================================

function process_Predefined_Query()
{
    global $urlVars;
    $sql = EMPTY_STRING;

    switch ($urlVars['query']) {
        case SQL_QUERY_ApplicationDevices: // query=Devices
            $sql = "SELECT AD." . PRIMARYKEY_ApplicationDevice;
            $sql .= " ,AD." . ITPINGS_APPLICATION_ID;
            $sql .= " ,AD." . ITPINGS_DESCRIPTION;
            $sql .= " ,AD." . ITPINGS_DEVICE_ID;
            $sql .= " ,AD." . ITPINGS_HARDWARE_SERIAL;
            $sql .= " ,LSV.FirstSeen, LSV.LastSeen";
            $sql .= " FROM " . VIEWNAME_APPLICATIONDEVICES . " AD";
            $sql .= " INNER JOIN(";
            $sql .= " SELECT " . PRIMARYKEY_ApplicationDevice;
            $sql .= " , min(" . ITPINGS_CREATED_TIMESTAMP . ") as FirstSeen";
            $sql .= " , max(" . ITPINGS_CREATED_TIMESTAMP . ") as LastSeen";
            $sql .= " FROM " . TABLE_PINGS;
            $sql .= " GROUP BY " . PRIMARYKEY_ApplicationDevice;
            $sql .= " ) LSV";
            $sql .= " WHERE AD." . PRIMARYKEY_ApplicationDevice . " = LSV." . PRIMARYKEY_ApplicationDevice;
            if ($urlVars[QUERY_PARAMETER_FILTER]) {
                $sql .= process_QueryParameter_Filter('', ' AND AD.', $urlVars[QUERY_PARAMETER_FILTER]);
            }
            break;
    }

    return $sql;
}

function process_QueryParameter_Filter($where, $and, $parameter_value)
{
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
    return $where;
}

/**
 *
 */
function process_Query_with_QueryString_Parameters()
{
    global $urlVars;
    $sql = EMPTY_STRING;

    $queryName = $urlVars['query'];
    $table_name = TABLE_PREFIX . $queryName;

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
        case VIEWNAME_GATEWAYS:
        case VIEWNAME_PINGEDGATEWAYS:
        case VIEWNAME_APPLICATIONDEVICES:
            break;
        default:
            $sql = process_Predefined_Query();
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
                        $where .= process_QueryParameter_Filter($where, $and, $parameter_value);
                        break;

                    case QUERY_PARAMETER_INTERVAL:
                        //https://dev.mysql.com/doc/refman/5.7/en/date-and-time-functions.html#function_date-add
                        $interval_unit = strtoupper($urlVars[QUERY_PARAMETER_INTERVALUNIT]);
                        if (!in_array($interval_unit, QUERY_ALLOWED_INTERVALUNITS)) {
                            $interval_unit = 'HOUR';
                        }
                        $where .= $and . ITPINGS_CREATED_TIMESTAMP . " >= DATE_SUB(NOW(), INTERVAL " . (int)$parameter_value . " " . $interval_unit . ")";
                        break;

                    case QUERY_PARAMETER_INTERVALUNIT:// processed in previous interval case
                        break;

                    case QUERY_PARAMETER_ORDERBY:
                        break;
                        if ($PARAMETER_HAS_SEPARATOR) {
                            $orderbyfields = [];
                            //accept only valid fieldnames
                            foreach (explode(QUERY_PARAMETER_SEPARATOR, $parameter_value) as $field) {
                                $field = explode(' ', $field);
                                $fieldname = $field[0];
                                $fieldsort = $field[1];
                                if (!$fieldsort) $fieldsort = 'ASC';
                                if (in_array($fieldname, VALID_QUERY_PARAMETERS)) {
                                    $orderbyfields[] .= $fieldname . ' ' . $fieldsort;
                                } else {
                                }
                            }
                            $parameter_value = implode(QUERY_PARAMETER_SEPARATOR, $orderbyfields);
                        }
                        $order .= " ORDER BY " . $parameter_value;
                        break;

                    case QUERY_PARAMETER_LIMIT:
                        switch (strtoupper($parameter_value)) {
                            case 'NONE':
                            case 'FALSE':
                            case '':
                                $limit = 'NONE';
                                break;
                            default:
                                $limit = Valued($parameter_value);
                        }
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

        if ($limit === 'NONE') {
            $limit = EMPTY_STRING;
        } else if ($limit === EMPTY_STRING) {
            $limit = " LIMIT " . SQL_LIMIT_DEFAULT;
        } else {
            $limit = " LIMIT " . $limit;
        }

        $sql .= $order . $limit;
    }
    if ($sql === EMPTY_STRING) {
        echo "{\"SQL\":\"Error: Empty SQL statement \"}";
    } else {
        SQL_Query($sql, TRUE);
    }
}

//endregion == PROCESS GET QUERY ==================================================================

//region ===== ITpings - MAIN CODE ================================================================

//Process QueryString variables
$urlVars = array();
parse_str($_SERVER['QUERY_STRING'], $urlVars);

if (CREATE_DATABASE_ON_FIRST_PING) {
    $ITpings_DatabaseInfo = SQL_Query("SELECT * FROM information_schema.tables WHERE table_name LIKE 'ITpings%' ORDER BY TABLE_TYPE ASC");
    if (!$ITpings_DatabaseInfo) {
        create_ITpings_Tables();
        create_ITpings_Views();
        echo "<a href=ITpings_dashboard.html><h1>Created ITpings Database Schema. Continue with ITpings Dashboard</h1></a>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (YOUR_ITPINGS_KEY !== $urlVars['key']) {
        echo "Invalid key";
    } else {

        $POST_body = trim(file_get_contents("php://input"));

        if (SAVE_POST_AS_ONE_STRING) {
            SQL_Query("INSERT INTO " . TABLE_POSTREQUESTS . "(" . ITPINGS_POST_body . ") VALUES(" . Quoted($POST_body) . ")");
        }

        /** global $request object is processed by all above functions **/
        $request = json_decode($POST_body, TRUE); // TRUE returns as Associative Array


        /** Create Ping DB entry asap, _pingid can then be used in all other Tables (primarly ttn__events) **/
        $request[PRIMARYKEY_Ping] = SQL_INSERT(TABLE_PINGS, array());
        process_AllGateways();                  // get key_id or insert into 'pinged_gateways' and 'gateways' tables
        process_ApplicationDevice_Information();// get key_id or insert into 'applications, Devices, ApplicationDevices' tables
        process_Sensors_From_PayloadFields();   // get key_id or insert into 'sensors' and 'sensorvalues' tables
        post_process_Ping();                    // update $request info in main 'pings' Table

//        insert_TTN_Event(ENUM_EVENTTYPE_Log, 'requestheader', $ip);

        echo "ITpings recorded a ping: " . $request[PRIMARYKEY_Ping];
    }
} else { // GET (JSON) request
    switch ($urlVars['action']) {
        case 'drop':
            if (YOUR_ITPINGS_KEY === $urlVars['key']) {
                foreach (ITPINGS_TABLES as $index => $table_name) {
                    $sql = "DROP TABLE IF EXISTS $table_name;";
                    echo $sql;
                    SQL_Query($sql);
                }
                foreach (ITPINGS_VIEWNAMES as $index => $view) {
                    $sql = "DROP VIEW IF EXISTS $view;";
                    echo $sql;
                    SQL_Query($sql);
                }
            }
            break;
        case 'create':
            create_ITpings_Tables();
            create_ITpings_Views();
            break;
        default:
            process_Query_with_QueryString_Parameters();
    }
}

mysqli_close($MySQL_DB_Connection);

//endregion === ITpings - MAIN CODE ================================================================