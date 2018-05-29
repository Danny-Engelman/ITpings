<?php
/* In a decent IDE, press Ctrl+Shift+Minus to collapse all code blocks */

include('ITpings_configuration.php');
include('ITpings_sensor_triggers.php');
include('ITpings__database_views.php');
include('ITpings__database_queries.php');
include('ITpings__database_maintenance.php');

//region ===== HELPER FUNCTIONS ===================================================================

/**
 * Check if $haystack contains $needle
 * @param $haystack
 * @param $needle
 * @return bool
 */
function contains($haystack, $needle)
{
    return strpos($haystack, $needle) !== FALSE;
}

/**
 * brutal approach against SQL injection attempts in QueryString Parameters
 * Return first element after split on 'illegal' SQL characters
 * @param $str
 * @return mixed
 */
function SQL_InjectionSave_OneWordString($str)
{
    return preg_split("/[&=:;]/", $str)[0];
}

/**
 * Test if a Lat/Lon location has decimals, to filter (fake) entries like 10,20
 * @param $lat
 * @param $lon
 * @return bool - TRUE when $lat and $lon have no decimals (most likely a fake location)
 */
function is_Location_without_Decimals($lat, $lon)
{
    return (floor($lat) == $lat AND floor($lon) == $lon);
}

//endregion == HELPER FUNCTIONS ===================================================================

//region ===== MYSQL DATABASE ACCESS ==============================================================

/**
 * Global MySQL connection with settings from IT_pings_configuration.php
 **/
$MySQL_DB_Connection = mysqli_connect(DBHOST, DBUSERNAME, DBPASSWORD, DBNAME);

if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

/**
 * Building a single JSON response structure
 * so we can easily return any info we need (like ALL the max(_pingid) count from multiple tables
 */
$JSON_response = array();
$JSON_response[QUERYKEY_ip] = PING_ORIGIN;
$JSON_response[QUERYKEY_mysqlversion] = mysqli_get_server_info($MySQL_DB_Connection);
$JSON_response[QUERYKEY_timezone] = date_default_timezone_get();//ini_get('date.timezone');
$JSON_response[QUERYKEY_rowcount] = 0;
$JSON_response[QUERY_PARAMETER_MAXROWS] = FALSE;
$JSON_response[QUERYKEY_skipcount] = 0;
$JSON_response[QUERYKEY_querytime] = FALSE;
$JSON_response[QUERYKEY_sql] = FALSE;
$JSON_response[QUERYKEY_messages] = [];
$JSON_response[QUERYKEY_errors] = [];
$JSON_response[QUERYKEY_maxids] = FALSE;
$JSON_response[QUERYKEY_result] = FALSE;

/**
 * Record how long the PHP processing took
 * Return JSON data to the document output
 */
function return_JSON_response()
{
    global $JSON_response;
    $JSON_response[QUERYKEY_querytime] = microtime(true) - $JSON_response[QUERYKEY_querytime];
    print json_encode($JSON_response);
}

/**
 * Clean up JSON structure when the data isn't required
 * The declarations were added to the Top of the document before, so the user sees them at the Top of the document (and does not have to scroll down)
 */
function remove_no_longer_required_keys_from_JSON_response()
{
    global $JSON_response;

    unset($JSON_response[QUERYKEY_mysqlversion]);
    unset($JSON_response[QUERYKEY_timezone]);
    unset($JSON_response[QUERYKEY_ip]);
    unset($JSON_response[QUERYKEY_rowcount]);
    unset($JSON_response[QUERY_PARAMETER_MAXROWS]);
    unset($JSON_response[QUERYKEY_querytime]);
    unset($JSON_response[QUERYKEY_skipcount]);
    unset($JSON_response[QUERYKEY_messages]);
    unset($JSON_response[QUERYKEY_errors]);
    unset($JSON_response[QUERYKEY_result]);
    unset($JSON_response[$JSON_response[QUERYKEY_sql]]);
}

/**
 * add every error to the error Array
 * @param $error - String
 */
function add_JSON_error_to_JSON_response($error)
{
    global $JSON_response;
    array_push($JSON_response[QUERYKEY_errors], $error);
}

/**
 * Add every text msg to the messages array
 * @param $msg - String
 */
function add_JSON_message_to_JSON_response($msg)
{
    global $JSON_response;
    array_push($JSON_response['messages'], $msg);
}

/**
 * Loop over MySQL result removing rows so the total result is $maxrows
 *
 * @param $result
 * @param $maxrows
 * @return array
 */
function skip_every_Nth_row_From_SQL_result($result, $maxrows)
{
    global $JSON_response;
    /**
     * It is faster to filter with PHP for the SQL result on every Nth row,
     * doing it with SQL takes 20% to 100%+  longer
     **/
//    SELECT *
//    FROM (
//        SELECT
//            @row := @row +1 AS rownum, _pingid
//        FROM (
//            SELECT @row :=0) r, ITpings__SensorValues
//        ) ranked
//    WHERE rownum % 4 = 1

    $rows = array();

    $rowcount = mysqli_num_rows($result);

    $JSON_response[QUERY_PARAMETER_MAXROWS] = (int)$maxrows;
    $Nth_row = ceil($rowcount / $maxrows);// to do: offset error, maxrows=200 returns 199, make sure first and last row element are always added
    $rowcount = 0;
    $skipped_rowcount = 0;
    $index = 0;
    $lastrow = false;
    while ($row = mysqli_fetch_assoc($result)) {
        $this_is_Nth_row = ($index % $Nth_row) === 0;
        if ($this_is_Nth_row OR $index === 0) {
            $rows[] = $row;
            $rowcount++;
        } else {
            $skipped_rowcount++;
        }
        $lastrow = $row;    // bug: rowcount and skipped_rowcount is 1 off when the lastrow === Nth row
        $index++;
    }
    if ($lastrow) $rows[] = $lastrow;
    $JSON_response['QUERYKEY_skipcount'] = $skipped_rowcount;
    add_JSON_message_to_JSON_response("PHP Function skip_every_Nth_row_From_SQL_result() Skipped $skipped_rowcount rows from the result, so the JSON response returns $maxrows rows");

    return $rows;
}

/**
 * Execute $sql query, returning rows or undefined
 * @param $sql
 * @return bool|mysqli_result
 */
function SQL_QUERY_ROWS($sql)
{
    global $MySQL_DB_Connection;

    insert_TTN_Event(ENUM_EVENTTYPE_Log, 'SQL', $sql);

    $result = mysqli_query($MySQL_DB_Connection, $sql);
    if (!$result) {
        $error = mysqli_error($MySQL_DB_Connection);
        add_JSON_error_to_JSON_response($error);
    }

    return $result;
}

/**
 * Execute a SQL delete statement,
 * adding SQL statement to Event and the JSON Output (always handy for enduser)
 * @param $sql
 * @return bool|mysqli_result
 */
function SQL_DELETE($sql, $addEvent = true)
{
    global $MySQL_DB_Connection;

    if ($addEvent) insert_TTN_Event(ENUM_EVENTTYPE_Log, 'SQL', $sql);

    add_JSON_message_to_JSON_response($sql);

    $result = mysqli_query($MySQL_DB_Connection, $sql);
    if (!$result) {
        $error = mysqli_error($MySQL_DB_Connection);
        add_JSON_error_to_JSON_response($error);
    }

    return $result;
}

function SQL_MULTI_QUERY($sql, $addEvent = true)
{
    global $MySQL_DB_Connection;

    if ($addEvent) insert_TTN_Event(ENUM_EVENTTYPE_Log, 'SQL', $sql);

    add_JSON_message_to_JSON_response($sql);

    $result = mysqli_multi_query($MySQL_DB_Connection, $sql);
    if (!$result) {
        $error = mysqli_error($MySQL_DB_Connection);
        add_JSON_error_to_JSON_response($error);
    }

    return $result;
}

/**
 * Main SQL query function
 *
 * RETURNS FIRST!!!! ROW OR JSON ENCODED OUTPUT
 *
 * @param $sql
 * @param bool $returnJSON
 * @return array|int|null|string
 *
 */
function SQL_Query($sql, $returnJSON = FALSE)
{
    global $MySQL_DB_Connection;
    global $JSON_response;
    global $QueryStringParameters;
    global $_ITPINGS_VIEWNAMES;

    //$JSON_response[QUERYKEY_sql] = shortenTableName($sql);
    $JSON_response[QUERYKEY_sql] = $sql;

    $JSON_response[QUERYKEY_querytime] = microtime(true);

    $result = mysqli_query($MySQL_DB_Connection, $sql);
    if ($result) {
        if ($returnJSON) {
            $maxrows = $QueryStringParameters[QUERY_PARAMETER_MAXROWS] ?? 0;
            $rows = array();
            if ($maxrows) {
                $rows = skip_every_Nth_row_From_SQL_result($result, $maxrows);
            } else {
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                }
            }
            $JSON_response[QUERYKEY_rowcount] = sizeof($rows);

            // http://nitschinger.at/Handling-JSON-like-a-boss-in-PHP/
            //header('Content-type: application/json');

            $JSON_response[QUERYKEY_result] = $rows;
            return_JSON_response();
        } else {
            return (gettype($result) === 'boolean') ? $result : mysqli_fetch_assoc($result); // return boolean OR first $row
        }
    } else {
        $error = mysqli_error($MySQL_DB_Connection);
        add_JSON_error_to_JSON_response($error);

        if (contains($error, "doesn't exist")) {
            $queryName = TABLE_PREFIX . $QueryStringParameters['query'];
            if (in_array($queryName, $_ITPINGS_VIEWNAMES)) {
                $JSON_response['createorreplaceview'] = Create_Or_Replace_View($queryName);
            }
        }
        return_JSON_response();
    }
    return false;
}

/**
 * @param $view_name
 * @param $sql
 * @return bool|mysqli_result
 */
function SQL_CREATE_or_REPLACE_VIEW($view_name, $sql)
{
    global $MySQL_DB_Connection;

    insert_TTN_Event(ENUM_EVENTTYPE_NewView, $view_name, $sql);

    $result = mysqli_query($MySQL_DB_Connection, $sql);
    if (!$result) {
        $error = mysqli_error($MySQL_DB_Connection);
        add_JSON_error_to_JSON_response($error);
    }

    return $result;
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

/**
 * @param $str
 * @return string
 */
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

/**
 * @param $table_name
 * @param $primary_key_name - can be False to indicate this table has NO primary key
 * @param $primary_key_type
 * @param $fields - array of ['fieldname','fieldtype','fieldcomment']
 * @param $foreignkeys - array of ['foreignkeyname','declaration']
 * @return bool|mysqli_result
 */
function create_Table($table_name, $primary_key_name, $primary_key_type, $fields, $foreignkeys)
{
    global $MySQL_DB_Connection;

    $sql = "CREATE TABLE IF NOT EXISTS $table_name";
    $sql .= " (";
    // add Primary Key field (not all tables have Primary Keys)
    if ($primary_key_name) {
        $sql .= "$primary_key_name $primary_key_type UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT COMMENT 'ITpings Primary Key' , ";
    }
    //add Table fields
    if (is_array($fields) || is_object($fields)) {
        foreach ($fields as $index => $field) {
            if ($index > 0) $sql .= " , ";
            $sql .= " $field[0] $field[1] COMMENT '$field[2]'";
        }
    }
    // add Foreign Keys
    if (is_array($foreignkeys) || is_object($foreignkeys)) {
        foreach ($foreignkeys as $index => $key) {
            $sql .= " , FOREIGN KEY ($key[0]) $key[1]";
        }
    }
    // add Primary Key
    if ($primary_key_name) {
        $sql .= " , PRIMARY KEY ($primary_key_name)";
    }
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8;";

    insert_TTN_Event(ENUM_EVENTTYPE_NewTable, $table_name, $sql);
    return mysqli_query($MySQL_DB_Connection, $sql);
}

/**
 * create Lookup Table (Frequencies,Moldulations,Datarates,Codingrates,Location)
 * @param $table
 * @param $primary_key_name
 * @param $field_name
 * @param $field_datatype
 * @return array
 */
function create_LookupTable($table, $primary_key_name, $field_name, $field_datatype)
{
    create_Table($table
        , $primary_key_name
        , TYPE_FOREIGNKEY_LOOKUPTABLE
        , [[$field_name, $field_datatype, "TTN " . $field_name]]
        , NO_FOREIGNKEYS
    );

    // return declaration for Lookup table
    return [
        $primary_key_name
        , TYPE_FOREIGNKEY_LOOKUPTABLE
        , " REFERENCES " . $table];
}

/**
 * Create dedicated data table to store Sensor values (Temperature, Light, Battery)
 * @param $table
 */
function create_DataTable($table, $valuefieldDefinition)
{
    global $_DBFIELD_APPLICATION_DEVICE;
    global $_FOREIGNKEY_APPLICATIONDEVICES;

    create_Table($table
        , PRIMARYKEY_Ping
        , TYPE_FOREIGNKEY
        , [//Fields
            $_DBFIELD_APPLICATION_DEVICE
            , $valuefieldDefinition
        ]
        , [$_FOREIGNKEY_APPLICATIONDEVICES]
    );
}

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
    //bit dirty, in strict PHP defines can not be Arrays, so they (arrays) are declared as Global variables
    global $_DBFIELD_EVENTTYPE;
    global $_DBFIELD_EVENTLABEL;
    global $_DBFIELD_EVENTVALUE;
    global $_DBFIELD_PRIMARYKEY_APPLICATION;
    global $_DBFIELD_PRIMARYKEY_DEVICE;
    global $_DBFIELD_POST_BODY;
    global $_DBFIELD_APPLICATION_ID;
    global $_DBFIELD_APPLICATION_DESCRIPTION;
    global $_DBFIELD_DEVICE_ID;
    global $_DBFIELD_HARDWARE_SERIAL;
    global $_DBFIELD_LATITUDE;
    global $_DBFIELD_LONGITUDE;
    global $_DBFIELD_ALTITUDE;
    global $_DBFIELD_LOCATION_SOURCE;
    global $_DBFIELD_GATEWAY_ID;
    global $_DBFIELD_TRUSTED_GATEWAY;
    global $_DBFIELD_LOCATION;
    global $_DBFIELD_CREATED_TIMESTAMP;
    global $_DBFIELD_APPLICATION_DEVICE;
    global $_DBFIELD_PORT;
    global $_DBFIELD_FRAME_COUNTER;
    global $_DBFIELD_DOWNLINKURL;
    global $_DBFIELD_PAYLOAD_RAW;
    global $_DBFIELD_PINGED_GATEWAY_TIMESTAMP;
    global $_DBFIELD_ITPINGS_TIME;
    global $_DBFIELD_CHANNEL;
    global $_DBFIELD_RSSI;
    global $_DBFIELD_SNR;
    global $_DBFIELD_RFCHAIN;
    global $_DBFIELD_SENSORNAME;
    global $_DBFIELD_PRIMARYKEY_PING;
    global $_DBFIELD_PRIMARYKEY_SENSOR;
    global $_DBFIELD_SENSORVALUE;

    global $_FOREIGNKEY_APPLICATIONS;
    global $_FOREIGNKEY_DEVICES;
    global $_FOREIGNKEY_APPLICATIONDEVICES;
    global $_FOREIGNKEY_PINGS;
    global $_FOREIGNKEY_GATEWAYS;
    global $_FOREIGNKEY_SENSORS;
    global $_FOREIGNKEY_LOCATIONS;

    create_Table(TABLE_EVENTS
        , NO_PRIMARYKEY
        , FALSE
        , [//Fields
            $_DBFIELD_PRIMARYKEY_PING
            , $_DBFIELD_EVENTTYPE
            , $_DBFIELD_EVENTLABEL
            , $_DBFIELD_EVENTVALUE
        ]
        , NO_FOREIGNKEYS/** No Foreign Key so the Events table can be used for any entry **/
    );

    if (SAVE_POST_AS_ONE_STRING) {
        create_Table(TABLE_POSTREQUESTS
            , PRIMARYKEY_POSTrequests
            , TYPE_FOREIGNKEY
            , [//Fields
                PRIMARYKEY_Ping,
                $_DBFIELD_POST_BODY
            ]
            , [$_FOREIGNKEY_PINGS]
        );
    }

    create_Table(TABLE_APPLICATIONS
        , PRIMARYKEY_Application
        , TYPE_FOREIGNKEY
        , [$_DBFIELD_APPLICATION_ID
            , $_DBFIELD_APPLICATION_DESCRIPTION]
        , NO_FOREIGNKEYS
    );

    create_Table(TABLE_DEVICES
        , PRIMARYKEY_Device
        , TYPE_FOREIGNKEY
        , [$_DBFIELD_DEVICE_ID
            , $_DBFIELD_HARDWARE_SERIAL
        ]
        , NO_FOREIGNKEYS
    );

    create_Table(TABLE_APPLICATIONDEVICES
        , PRIMARYKEY_ApplicationDevice
        , TYPE_FOREIGNKEY
        , [//Fields
            $_DBFIELD_PRIMARYKEY_APPLICATION
            , $_DBFIELD_PRIMARYKEY_DEVICE
        ]
        , [$_FOREIGNKEY_APPLICATIONS, $_FOREIGNKEY_DEVICES]
    );

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
        , [$_DBFIELD_LATITUDE
            , $_DBFIELD_LONGITUDE
            , $_DBFIELD_ALTITUDE
            //, $_DBFIELD_HDOP
            , $_DBFIELD_LOCATION_SOURCE
        ]
        , NO_FOREIGNKEYS
    );

    create_Table(TABLE_GATEWAYS
        , PRIMARYKEY_Gateway
        , TYPE_FOREIGNKEY
        , [$_DBFIELD_GATEWAY_ID
            , $_DBFIELD_TRUSTED_GATEWAY
            , $_DBFIELD_LOCATION
        ]
        , [$_FOREIGNKEY_LOCATIONS]
    );

    create_Table(TABLE_PINGS
        , PRIMARYKEY_Ping
        , TYPE_FOREIGNKEY
        , [//Fields
            $_DBFIELD_CREATED_TIMESTAMP
            , $_DBFIELD_APPLICATION_DEVICE

            , $_DBFIELD_PORT
            , $_DBFIELD_FRAME_COUNTER
            , $_DBFIELD_DOWNLINKURL
            , $_DBFIELD_PAYLOAD_RAW

            , $_DBFIELD_ITPINGS_TIME

            , $frequency_declaration
            , $modulation_declaration
            , $datarate_declaration
            , $codingrate_declaration

            , $_DBFIELD_LOCATION

            , $origin_declaration
        ]
        , [$_FOREIGNKEY_APPLICATIONDEVICES]
    );

    create_Table(TABLE_PINGEDGATEWAYS
        , NO_PRIMARYKEY
        , FALSE
        , [//Fields
            $_DBFIELD_PRIMARYKEY_PING
            , [PRIMARYKEY_Gateway, TYPE_FOREIGNKEY, 'PrimaryKey:' . TABLE_GATEWAYS]
            , $_DBFIELD_PINGED_GATEWAY_TIMESTAMP
            , $_DBFIELD_ITPINGS_TIME
            , $_DBFIELD_CHANNEL
            , $_DBFIELD_RSSI
            , $_DBFIELD_SNR
            , $_DBFIELD_RFCHAIN
        ]
        , [$_FOREIGNKEY_PINGS, $_FOREIGNKEY_GATEWAYS]
    );

    create_Table(TABLE_SENSORS
        , PRIMARYKEY_Sensor
        , TYPE_FOREIGNKEY
        , [//Fields
            $_DBFIELD_APPLICATION_DEVICE
            , $_DBFIELD_SENSORNAME
        ]
        , [$_FOREIGNKEY_APPLICATIONDEVICES]
    );

    create_Table(TABLE_SENSORVALUES
        , NO_PRIMARYKEY
        , FALSE
        , [//Fields
            $_DBFIELD_PRIMARYKEY_PING
            , $_DBFIELD_PRIMARYKEY_SENSOR
            , $_DBFIELD_SENSORVALUE
        ]
        , [$_FOREIGNKEY_PINGS, $_FOREIGNKEY_SENSORS]
    );

    /** Data tables to reduce size of _sensorvalues Table **/

    create_DataTable(TABLE_DATA_TEMPERATURE, [ITPINGS_SENSOR_VALUE, 'DECIMAL(3,1)', 'Temperature value']);

    create_DataTable(TABLE_DATA_LUMINOSITY, [ITPINGS_SENSOR_VALUE, 'SMALLINT(2)', 'Luminosity/Light value']);

    create_DataTable(TABLE_DATA_BATTERY, [ITPINGS_SENSOR_VALUE, 'DECIMAL(2)', 'Battery level value']);

    create_Table(TABLE_DATA_ACCELEROMETER
        , PRIMARYKEY_Ping
        , TYPE_FOREIGNKEY
        , [//Fields
            $_DBFIELD_APPLICATION_DEVICE
            , ['x', 'FLOAT(4,3)', 'x movement']
            , ['y', 'FLOAT(4,3)', 'y movement']
            , ['z', 'FLOAT(4,3)', 'z movement']
        ]
        , [$_FOREIGNKEY_APPLICATIONDEVICES]
    );

}//end function createTables


//endregion == CREATE ITPINGS DATABASE : TABLES ===================================================

/** PHP_include ITpings__database_views **/

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
 * @param $lookup_field
 * @param $lookup_value
 * @return string
 */
function process_Lookup_Insert_New_value($table, $primarykey, $lookup_field, $lookup_value)
{
    global $request;
    /** is there a value in the lookup $table? **/
    $key_id = SQL_find_existing_key_id($primarykey, $table, $lookup_field . " = " . $lookup_value);

    /** If there is no existing value, insert this new lookup_value in the corresponding Lookup $table **/
    if (!$key_id) {
        insert_TTN_Event(ENUM_EVENTTYPE_Log, 'New' . $lookup_field, $lookup_value);
        $key_id = SQL_INSERT($table, [AUTOINCREMENT_TABLE_PRIMARYKEY, $lookup_value]);
    }

    /**  record the existing OR new id in the global $request **/
    $request[$primarykey] = $key_id;

    /** return a string for a SQL Insert statement **/
    return COMMA . $primarykey . " = " . $key_id;
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
        , ITPINGS_APPLICATION_ID . " = " . Quoted($request_Application_ID)

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
        , ITPINGS_DEVICE_ID . " = " . Quoted($request_Device_ID)
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
        , PRIMARYKEY_Application . " = " . $request[PRIMARYKEY_Application] . " AND " . PRIMARYKEY_Device . " = " . $request[PRIMARYKEY_Device]
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
 * @param $where
 * @return array|null
 */
function find_location_in_TableLocations($where)
{
    return SQL_find_existing_key_id(PRIMARYKEY_Location, TABLE_LOCATIONS, $where);
}

/**
 * find an existing GEO location / create a new Location
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

    $valid_location = true;

    if ($lat == "") {
        $valid_location = false;
        $lat = "0";
    }
    if ($lon == "") $lon = "0";

    //hardcoded! for now. todo: proces other (non 'registry') location_source settings
    if ($location_source === 'registry') {
        $location_source = 1;
    } else {
        $valid_location = false;
        insert_TTN_Event(ENUM_EVENTTYPE_Error, 'invalid LocationSource', "$location_source: $lat / $lon");
        $location_source = 1;
        //return 1;   // main gateway
    };

    //first check coordinates without height, later append altitude on $where
    $where = ITPINGS_LATITUDE . "=$lat AND " . ITPINGS_LONGITUDE . "=$lon";

    $key_id = find_location_in_TableLocations($where);

    if ($valid_location AND !$key_id) {
        // New Location
        $key_id = SQL_INSERT(
            TABLE_LOCATIONS
            , [AUTOINCREMENT_TABLE_PRIMARYKEY
            , $lat
            , $lon
            , $alt
            //, $HDOP
            , $location_source
        ]);
        insert_TTN_Event(ENUM_EVENTTYPE_NewLocation, $lat, $lon);
    } else { //existing location, now check Height
        if ($valid_location AND CHECK_THE_ALTITUDE_IN_PING) {
            if ($alt) {
                $height_key_id = find_location_in_TableLocations($where . " AND " . ITPINGS_ALTITUDE . "=$alt");
                if ($height_key_id) {
                    $key_id = $height_key_id;
                } else {
                    insert_TTN_Event(ENUM_EVENTTYPE_Log,
                        'Height change for Device',
                        "(" . PRIMARYKEY_Location . "=$key_id) " . $request[TTN_dev_id]);
                    SQL_Query("UPDATE " . TABLE_LOCATIONS . " SET " . ITPINGS_ALTITUDE . "=$alt WHERE " . PRIMARYKEY_Location . "=" . $key_id);
                    //returns the key_id for the lat/lon (excluding alt) is used
                }
            } else {
                insert_TTN_Event(ENUM_EVENTTYPE_Log, 'No altitude for Device', $request[TTN_dev_id]);
                //returns the key_id for the lat/lon (excluding alt) is used
            }
        }
    }
    $request[PRIMARYKEY_Location] = $key_id;
    return $key_id;
}


/**
 * Find matching Gateway ID, OVER x metres distance, the Gateway is recorded again (as being moved)
 *
 * @param $gtw_id
 * @param $latitude
 * @param $longitude
 * @return array|null
 */
function find_Nearest_Gateway_With_Same_ID($gtw_id, $latitude, $longitude)
{
    $sql = "SELECT * ";

    if ($latitude) {
        $GTWradLAT = deg2rad($latitude);
        $GTWradLON = deg2rad($longitude);
        $radius = 6371;// 6371 for Kilometers, 3959 for Miles
        $sql .= " , ($radius * acos(cos($GTWradLAT) * cos(radians(" . ITPINGS_LATITUDE . "))";
        $sql .= " * cos(radians(" . ITPINGS_LONGITUDE . ") - $GTWradLON) + sin($GTWradLAT)";
        $sql .= " * sin(radians(" . ITPINGS_LATITUDE . ")))) AS distance";
    }

    $sql .= " FROM " . VIEWNAME_GATEWAYS . " WHERE " . ITPINGS_GATEWAY_ID . " = " . Quoted($gtw_id);

    if ($latitude) { // GPS has inaccurate fixes, to prevent 'moving' Gateway recordings a tolerance for lat/lon is calculated
        $sql .= " HAVING distance < " . GATEWAY_POSITION_TOLERANCE . " ORDER BY distance;"; // default 0.02 = 20 meter
    } else {
        $sql .= " ORDER BY " . PRIMARYKEY_Gateway . " DESC;";
    }
    return SQL_Query($sql);
}

/**
 * Find a Gateway or create a new Gateway
 *
 * @param $gateway Object from the POST request
 * @return number ID value of existing/new Gateway
 */
function get_Gateway_ID_by_processing_one_Gateway($gateway)
{
    global $request;
    // GPS has inaccurate fixes,
    // to prevent 'moving' Gateway recordings a tolerance for lat/lon is calculated

    $request_GateWay_ID = $gateway[TTN_gtw_id];
    $device_location = $request[TTN_dev_id] . " / " . $request_GateWay_ID;
    $lat = $gateway[TTN_latitude];
    $lon = $gateway[TTN_longitude];
    $alt = $gateway[TTN_altitude];

    if (!$lat or !$lon) {
        insert_TTN_Event(ENUM_EVENTTYPE_Error, "Gateway without location", $device_location);
        $key_id = 1;
    } else {

        $table_row = find_Nearest_Gateway_With_Same_ID($request_GateWay_ID, $lat, $lon);

        if ($table_row) {// found a Gateway
            $key_id = $table_row[PRIMARYKEY_Gateway];

            if (is_Location_without_Decimals($lat, $lon)) {
                insert_TTN_Event(ENUM_EVENTTYPE_Log, "Suspicious location: $lat / $lon", $device_location);
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

        $gatewayID = get_Gateway_ID_by_processing_one_Gateway($gateway); // Find known Gateway, else save new Gateway

        $gateway_time = $gateway[TTN_time];

        $gateway_info = "$gatewayID - $gateway[PRIMARYKEY_Gateway]";

        if ($gateway_time === "") {
            //sometimes time values are empty strings
            //
            insert_TTN_Event(
                ENUM_EVENTTYPE_Error
                , "Empty Gateway Time"
                , $gateway_info
            );
        }

        if (in_array($gatewayID, $processedGateways_InRequest)) {
            insert_TTN_Event(
                ENUM_EVENTTYPE_Error
                , "Duplicate Gateway in Gateways"
                , $gateway_info
            );
        } else {
            array_push($processedGateways_InRequest, $gatewayID);

            SQL_INSERT(
                TABLE_PINGEDGATEWAYS
                , [//values
                Valued($request[PRIMARYKEY_Ping])
                , Valued($gatewayID)
                , Valued($gateway[TTN_timestamp])
                , Quoted($gateway_time)
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

    //new entry in TABLE_PINGS was created ASAP; now update it with the processed values
    $sql = "UPDATE " . TABLE_PINGS . " SET ";
    $sql .= PRIMARYKEY_ApplicationDevice . " = " . Valued($request[PRIMARYKEY_ApplicationDevice]);
    $sql .= COMMA . ITPINGS_PORT . " = " . Valued($request[TTN_port]);
    $sql .= COMMA . ITPINGS_FRAME_COUNTER . " = " . Valued($request[TTN_counter]);

    // To save database space these fields IN OLDER PINGS will be reset to empty values at the end of this function
    $sql .= COMMA . ITPINGS_DOWNLINKURL . " = " . Quoted($request[TTN_downlink_url]);
    $sql .= COMMA . ITPINGS_PAYLOAD_RAW . " = " . Quoted($request[TTN_payload_raw]);

    $metadata = $request[TTN_metadata];

    $sql .= COMMA . ITPINGS_TIME . " = " . Quoted($metadata[TTN_time]);

    $sql .= process_Lookup_Insert_New_value(
        TABLE_FREQUENCIES, PRIMARYKEY_Frequency
        , ITPINGS_FREQUENCY, Valued($metadata[TTN_frequency]));
    $sql .= process_Lookup_Insert_New_value(
        TABLE_MODULATIONS, PRIMARYKEY_Modulation
        , ITPINGS_MODULATION, Quoted($metadata[TTN_modulation]));
    $sql .= process_Lookup_Insert_New_value(
        TABLE_DATARATES, PRIMARYKEY_Datarate
        , ITPINGS_DATA_RATE, Quoted($metadata[TTN_data_rate]));
    $sql .= process_Lookup_Insert_New_value(
        TABLE_CODINGRATES, PRIMARYKEY_Codingrate
        , ITPINGS_CODING_RATE, Quoted($metadata[TTN_coding_rate]));

    $sql .= COMMA . PRIMARYKEY_Location . " = "
        . process_Ping_and_Gateway_Location($metadata[TTN_latitude], $metadata[TTN_longitude], $metadata[TTN_altitude], $metadata[TTN_location_source]);

    /** record IP address **/
    $sql .= process_Lookup_Insert_New_value(
        TABLE_ORIGINS, PRIMARYKEY_Origin
        , ITPINGS_ORIGIN, Quoted(PING_ORIGIN));

    $sql .= " WHERE " . PRIMARYKEY_Ping . " = " . $request[PRIMARYKEY_Ping];

    SQL_Query($sql);


    /** Purge(empty) Fields that have no meaning after a time period **/
    $sql = "UPDATE " . TABLE_PINGS . " SET ";
    $sql .= ITPINGS_DOWNLINKURL . "=" . EMPTY_STRING;
    $sql .= ",";
    $sql .= ITPINGS_PAYLOAD_RAW . "=" . EMPTY_STRING;
    $sql .= " WHERE " . PRIMARYKEY_Ping . " < " . ($request[PRIMARYKEY_Ping] - PURGE_PINGCOUNT);
    SQL_Query($sql);
}

/**
 * Find an existing ApplicationDevice Sensor OR create a new Sensor
 *
 * @param $sensor_name
 * @return array ID value of existing/new Sensor
 */
function process_Existing_Or_New_Sensor($sensor_name)
{
    global $request;

    $app_dev_id = $request[PRIMARYKEY_ApplicationDevice];

    $key_id = SQL_find_existing_key_id(
        PRIMARYKEY_Sensor
        , TABLE_SENSORS
        , PRIMARYKEY_ApplicationDevice . " = $app_dev_id AND " . ITPINGS_SENSORNAME . " = " . Quoted($sensor_name)
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
 * Process TTN POST PayLoad Object: payload_fields
 */
function process_Sensors_From_PayloadFields()
{
    global $request;

    $payload_fields = $request[TTN_payload_fields];
    if ($payload_fields) {
        foreach ($payload_fields as $sensor_name => $sensor_value) {
            /**
             * see: ITpings_sensor_triggers.php
             **/
            process_One_Sensor_Value($sensor_name, $sensor_value);
        }
    } else {
        insert_TTN_Event(
            ENUM_EVENTTYPE_Error,
            "Missing payload_fields",
            $request[TTN_dev_id]
        );
    }
}

//endregion == PROCESS POST REQUEST , SAVE DATA TO ALL TABLES =================================================

//region ===== ITpings - MAIN CODE ================================================================

//Process QueryString variables
$QueryStringParameters = array();
parse_str($_SERVER['QUERY_STRING'], $QueryStringParameters);


if (CREATE_DATABASE_ON_FIRST_PING) {
    $sql = "SELECT * FROM information_schema . tables WHERE table_name LIKE '" . TABLE_PREFIX . "%' AND table_type = 'BASE TABLE' ORDER BY TABLE_TYPE ASC";
    $ITpings_DatabaseInfo = SQL_Query($sql);
    if (!$ITpings_DatabaseInfo) {
        create_ITpings_Tables();
        create_ITpings_Views();
        echo "<A HREF=ITpings_dashboard.html ><h1> Created ITpings Database Schema . Continue with ITpings Dashboard </h1></A> ";
    }
}

/**
 * Optional trace, to get trace output in Endpoint or Postman call
 * @param $txt
 */
function trace($txt = '')
{
    global $request;
    if ($request && $txt) {
        //echo "\n" . $txt . "\n" . implode(",", $request);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (YOUR_ITPINGS_KEY !== $QueryStringParameters['key']) {
        echo "Invalid key" . $QueryStringParameters['key'] . ", 21 edit your ITpings_access_database . php file; " . YOUR_ITPINGS_KEY;
    } else {

        $POST_body = trim(file_get_contents("php://input"));

        /** global $request object is processed by all above functions **/
        $request = json_decode($POST_body, TRUE); // TRUE returns as Associative Array
        if ($request) {
            trace("start");
            /** Create Ping DB entry asap, _pingid can then be used in all other Tables (primarly ttn__events) **/
            $request[PRIMARYKEY_Ping] = SQL_INSERT(TABLE_PINGS, array());

            if (SAVE_POST_AS_ONE_STRING) {
                $sql = "INSERT INTO " . TABLE_POSTREQUESTS . "(" . PRIMARYKEY_Ping . "," . ITPINGS_POST_body . ") VALUES(" . $request[PRIMARYKEY_Ping] . "," . Quoted($POST_body) . ")";
                SQL_Query($sql);
            }

            process_AllGateways();                  // get key_id or insert into 'pinged_gateways' and 'gateways' tables
            trace("Done Gateways");
            process_ApplicationDevice_Information();// get key_id or insert into 'applications, Devices, ApplicationDevices' tables
            trace("Done ApplicationDevice");
            process_Sensors_From_PayloadFields();   // get key_id or insert into 'sensors' and 'sensorvalues' tables
            trace("Done Sensors");
            post_process_Ping();                    // update $request info in main 'pings' Table
            trace("Done postProcess");

//        insert_TTN_Event(ENUM_EVENTTYPE_Log, 'requestheader', $ip);

            echo "ITpings recorded a ping: " . $request[PRIMARYKEY_Ping];
        } else {
            echo "Invalid JSON in POST";
        }
    }
} else { // GET (JSON) request
    switch ($QueryStringParameters['action'] ?? '') {
        case 'drop':
            if (ALLOW_DATABASE_CHANGES) {
                if (YOUR_ITPINGS_KEY === $QueryStringParameters['key']) {
                    foreach ($_ITPINGS_TABLES as $index => $table_name) {
                        $sql = "DROP TABLE IF EXISTS $table_name;";
                        echo $sql;
                        SQL_Query($sql);
                    }
                    foreach ($_ITPINGS_VIEWNAMES as $index => $view) {
                        $sql = "DROP VIEW IF EXISTS $view;";
                        echo $sql;
                        SQL_Query($sql);
                    }
                }
            }
            break;
        case 'create':
            if (ALLOW_DATABASE_CHANGES) {
                create_ITpings_Tables();    // Tables are NOT re-created with CREATE TABLE IF NOT EXIST
            }
            create_ITpings_Views();     // Views are RE-created with CREATE OR REPLACE VIEW
            break;
        default:
            process_Table_or_View_query();
    }
}

mysqli_close($MySQL_DB_Connection);

//endregion === ITpings - MAIN CODE ================================================================
