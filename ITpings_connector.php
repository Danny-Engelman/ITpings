<?php
/* In a decent IDE, press Ctrl+Shift+Minus to collapse all code blocks */

include('ITpings_configuration.php');
include('ITpings_sensor_triggers.php');

//region ===== HELPER FUNCTIONS ===================================================================

/**
 * @param $haystack
 * @param $needle
 * @return bool
 */
function contains($haystack, $needle)
{
    return strpos($haystack, $needle) !== FALSE;
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
$JSON_response['ip'] = PING_ORIGIN;
$JSON_response['mysqlversion'] = mysqli_get_server_info($MySQL_DB_Connection);
$JSON_response['data_default_timezone'] = date_default_timezone_get();
//$JSON_response['timezone'] = ini_get('date.timezone');
$JSON_response['rowcount'] = 0;
$JSON_response[QUERY_PARAMETER_MAXROWS] = FALSE;
$JSON_response['skipcount'] = 0;
$JSON_response['querytime'] = FALSE;
$JSON_response['sql'] = FALSE;
$JSON_response['messages'] = [];
$JSON_response['errors'] = [];
$JSON_response['maxids'] = FALSE;
$JSON_response['result'] = FALSE;

function return_JSON_response()
{
    global $JSON_response;
    $JSON_response['querytime'] = microtime(true) - $JSON_response['querytime'];
    print json_encode($JSON_response);
}

function remove_no_longer_required_keys_from_JSON_response()
{
    global $JSON_response;

    unset($JSON_response['mysqlversion']);
    unset($JSON_response['data_default_timezone']);
    unset($JSON_response['ip']);
    unset($JSON_response['rowcount']);
    unset($JSON_response[QUERY_PARAMETER_MAXROWS]);
    unset($JSON_response['querytime']);
    unset($JSON_response['skipcount']);
    unset($JSON_response['messages']);
    unset($JSON_response['errors']);
    unset($JSON_response['result']);
    unset($JSON_response['sql']);

}

function add_JSON_error_to_JSON_response($error)
{
    global $JSON_response;
    array_push($JSON_response['errors'], $error);
}

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
    $JSON_response['skipcount'] = $skipped_rowcount;

    return $rows;
}

/**
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
 * @param $sql
 * @return bool|mysqli_result
 */
function SQL_DELETE($sql)
{
    global $MySQL_DB_Connection;

    insert_TTN_Event(ENUM_EVENTTYPE_Log, 'SQL', $sql);

    add_JSON_message_to_JSON_response($sql);

    $result = mysqli_query($MySQL_DB_Connection, $sql);
    if (!$result) {
        $error = mysqli_error($MySQL_DB_Connection);
        add_JSON_error_to_JSON_response($error);
    }

    return $result;
}

/**
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

    //$JSON_response['sql'] = shortenTableName($sql);
    $JSON_response['sql'] = $sql;

    $JSON_response['querytime'] = microtime(true);

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
            $JSON_response['rowcount'] = sizeof($rows);

            // http://nitschinger.at/Handling-JSON-like-a-boss-in-PHP/
            //header('Content-type: application/json');

            $JSON_response['result'] = $rows;
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
 * @param $table_name
 * @param $sql
 * @return bool|mysqli_result
 */
function SQL_CREATE_TABLE($table_name, $sql)
{
    global $MySQL_DB_Connection;

    insert_TTN_Event(ENUM_EVENTTYPE_NewTable, $table_name, $sql);

    return mysqli_query($MySQL_DB_Connection, $sql);
}

/**
 * @param $view_name
 * @param $sql
 * @return bool|mysqli_result
 */
function SQL_CREATE_or_REPLACE_VIEW($view_name, $sql)
{
    global $MySQL_DB_Connection;

    //insert_TTN_Event(ENUM_EVENTTYPE_NewView, $view_name, $sql);

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
    global $_DBFIELD_GATEWAY;
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
    global $_DBFIELD_SENSOR_TEMPERATURE_VALUE;

    global $_FOREIGNKEY_APPLICATIONS;
    global $_FOREIGNKEY_DEVICES;
    global $_FOREIGNKEY_APPLICATIONDEVICES;
    global $_FOREIGNKEY_PINGS;
    global $_FOREIGNKEY_GATEWAYS;
    global $_FOREIGNKEY_SENSORS;
    global $_FOREIGNKEY_LOCATIONS;

    /**
     * @param $table_name
     * @param $primary_key_name - can be False to indicate this table has NO primary key
     * @param $primary_key_type
     * @param $fields - array of ['fieldname','fieldtype','fieldcomment']
     * @param $foreignkeys - array of ['foreignkeyname','declaration']
     */
    function create_Table($table_name, $primary_key_name, $primary_key_type, $fields, $foreignkeys)
    {
        $sql = "CREATE TABLE IF NOT EXISTS $table_name";
        $sql .= " (";
        if ($primary_key_name) {
            $sql .= "$primary_key_name $primary_key_type UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT COMMENT 'ITpings Primary Key' , ";
        }
        if (is_array($fields) || is_object($fields)) {
            foreach ($fields as $index => $field) {
                if ($index > 0) $sql .= " , ";
                $sql .= " $field[0] $field[1] COMMENT '$field[2]'";
            }
        }
        if (is_array($foreignkeys) || is_object($foreignkeys)) {
            foreach ($foreignkeys as $index => $key) {
                $sql .= " , FOREIGN KEY ($key[0]) $key[1]";
            }
        }
        if ($primary_key_name) {
            $sql .= " , PRIMARY KEY ($primary_key_name)";
        }
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8;";
//        echo "$sql<HR>";
        SQL_CREATE_TABLE($table_name, $sql);
    }

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
            , $_DBFIELD_GATEWAY
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

    create_Table(TABLE_TEMPERATURE
        , PRIMARYKEY_Ping
        , TYPE_FOREIGNKEY
        , [//Fields
            $_DBFIELD_APPLICATION_DEVICE
            , $_DBFIELD_SENSOR_TEMPERATURE_VALUE
        ]
        , [$_FOREIGNKEY_APPLICATIONDEVICES]
    );

    // Data tables to reduce size of _sensorvalues Table
    create_Table(TABLE_LUMINOSITY
        , PRIMARYKEY_Ping
        , TYPE_FOREIGNKEY
        , [//Fields
            $_DBFIELD_APPLICATION_DEVICE
            , $_DBFIELD_SENSORVALUE
        ]
        , [$_FOREIGNKEY_APPLICATIONDEVICES]
    );
}//end function createTables

//endregion == CREATE ITPINGS DATABASE : TABLES ===================================================

//region ===== CREATE ITPINGS DATABASE : VIEWS ====================================================

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
        case VIEWNAME_TEMPERATURE:
            $view .= " T." . PRIMARYKEY_Ping;
            $view .= " , P." . ITPINGS_CREATED_TIMESTAMP;
            $view .= " , T." . PRIMARYKEY_Device . " , T." . ITPINGS_SENSOR_TEMPERATURE_VALUE;
            $view .= " FROM " . TABLE_TEMPERATURE . " T ";
            $view .= " JOIN " . TABLE_PINGS . " P ON P . " . PRIMARYKEY_Ping . " = T . " . PRIMARYKEY_Ping;
            $view .= " ORDER BY T." . PRIMARYKEY_Ping . " ASC";
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
function process_Lookup($table, $primarykey, $lookup_field, $lookup_value)
{
    global $request;
    // is there a value in the lookup table?
    $key_id = SQL_find_existing_key_id($primarykey, $table, $lookup_field . " = " . $lookup_value);

    if (!$key_id) {
        insert_TTN_Event(ENUM_EVENTTYPE_Log, 'New' . $lookup_field, $lookup_value);

        // create value in lookup table
        $key_id = SQL_INSERT($table, [AUTOINCREMENT_TABLE_PRIMARYKEY, $lookup_value]);
    }
    $request[$primarykey] = $key_id;
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

    if ($lat == "") $lat = "0";
    if ($lon == "") $lon = "0";

    //hardcoded! for now
    if ($location_source === 'registry') {
        $location_source = 1;
    } else {
        insert_TTN_Event(ENUM_EVENTTYPE_Error, 'invalid LocationSource', "$location_source: $lat / $lon");
        $location_source = 1;
        //return 1;   // main gateway
    };

    function find_location_in_TableLocations($where)
    {
        return SQL_find_existing_key_id(PRIMARYKEY_Location, TABLE_LOCATIONS, $where);
    }

    //first check coordinates without height, later append altitude on $where
    $where = ITPINGS_LATITUDE . "=$lat AND " . ITPINGS_LONGITUDE . "=$lon";

    $key_id = find_location_in_TableLocations($where);

    if (!$key_id) {
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
        if (CHECK_THE_ALTITUDE_IN_PING) {
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

        if ($gateway_time === "") {
            //sometimes time values are empty strings
            //
            insert_TTN_Event(
                ENUM_EVENTTYPE_Error
                , "Empty Gateway Time"
                , "$gatewayID - " . $gateway[TTN_gtw_id]
            );
        }

        if (in_array($gatewayID, $processedGateways_InRequest)) {
            insert_TTN_Event(
                ENUM_EVENTTYPE_Error
                , "Duplicate Gateway in Gateways"
                , $gatewayID
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
 * Purge old downlink_raw / timestamp to compress database size
 * @param $latest_ping_id
 */
function purge_expired_Ping_data($latest_ping_id)
{
    // Timestamp is an integer field, so reset to 0 does not save any bytes

    //PURGE_PINGCOUNT (default 180) pings in the past:
    $sql = "UPDATE " . TABLE_PINGS . " SET ";
    $sql .= ITPINGS_DOWNLINKURL . " = '' , " . ITPINGS_PAYLOAD_RAW . " = ''";
    $sql .= " WHERE " . PRIMARYKEY_Ping . " < " . ($latest_ping_id - PURGE_PINGCOUNT);
    SQL_Query($sql);
}

/**
 * Now update the Pings Table with data from the POST request
 */
function post_process_Ping()
{
    global $request;

    //new entry in TABLE_PINGS was created ASAP; now update it with the processes values
    $sql = "UPDATE " . TABLE_PINGS . " SET ";
    $sql .= PRIMARYKEY_ApplicationDevice . " = " . Valued($request[PRIMARYKEY_ApplicationDevice]);
    $sql .= COMMA . ITPINGS_PORT . " = " . Valued($request[TTN_port]);
    $sql .= COMMA . ITPINGS_FRAME_COUNTER . " = " . Valued($request[TTN_counter]);

    // To save database space these fields will be reset to empty values by the purge_expired_Ping_data() function
    $sql .= COMMA . ITPINGS_DOWNLINKURL . " = " . Quoted($request[TTN_downlink_url]);
    $sql .= COMMA . ITPINGS_PAYLOAD_RAW . " = " . Quoted($request[TTN_payload_raw]);

    $metadata = $request[TTN_metadata];

    $sql .= COMMA . ITPINGS_TIME . " = " . Quoted($metadata[TTN_time]);

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

    $sql .= COMMA . PRIMARYKEY_Location . " = "
        . process_Ping_and_Gateway_Location($metadata[TTN_latitude], $metadata[TTN_longitude], $metadata[TTN_altitude], $metadata[TTN_location_source]);

    $sql .= process_Lookup(
        TABLE_ORIGINS, PRIMARYKEY_Origin
        , ITPINGS_ORIGIN, Quoted(PING_ORIGIN));

    $sql .= " WHERE " . PRIMARYKEY_Ping . " = " . $request[PRIMARYKEY_Ping];

    SQL_Query($sql);

    //Only keep PURGE_PINGCOUNT
    purge_expired_Ping_data($request[PRIMARYKEY_Ping]);
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
             * Convert nested objects (like TTN x,y,z movements to a CSV String
             */
            if (is_array($sensor_value)) {
                $sensor_value = implode(",", $sensor_value);
            }

            $sensor_ID = process_Existing_Or_New_Sensor($sensor_name);

            /**
             * see: ITpings_sensor_triggers.php
             **/
            process_SensorValue($sensor_ID, $sensor_name, $sensor_value);

        }
    } else {
        $error = $request[TTN_dev_id] . " " . implode(",", $request);
        insert_TTN_Event(ENUM_EVENTTYPE_Error, "Missing payload_fields", $error);
    }
}

//endregion == PROCESS POST REQUEST , SAVE DATA TO ALL TABLES =================================================

//region ===== PROCESS GET QUERY ==================================================================

/**
 * create a JSON structure with the most recent PrimaryKey value for Tables and Views
 * The Browser polls this endpoint and thus only updates HTML Tables/Graphs when there is NEW data
 */
function attach_Max_IDs_to_JSON_response()
{
    global $JSON_response;

    $JSON_response['maxids'] = array();

    function MAXID($table, $key, $value = false)
    {
        global $JSON_response;

        if ($value === FALSE) {
            $value = SQL_Query("SELECT MAX($key) AS mx FROM $table")['mx'];
        }

        $table = str_replace(TABLE_PREFIX, '', $table);

        if (!isset($JSON_response['maxids'][$table])) $JSON_response['maxids'][$table] = [];
        $JSON_response['maxids'][$table][$key] = (int)$value;

        return $value;
    }

    MAXID(TABLE_APPLICATIONS, PRIMARYKEY_Application);
    MAXID(TABLE_DEVICES, PRIMARYKEY_Device);
    $event_pingid = MAXID(TABLE_EVENTS, PRIMARYKEY_Ping);
    $gtwid = MAXID(TABLE_GATEWAYS, PRIMARYKEY_Gateway);
    MAXID(TABLE_LOCATIONS, PRIMARYKEY_Location);
    $pingid = MAXID(TABLE_PINGS, PRIMARYKEY_Ping);
    MAXID(TABLE_SENSORS, PRIMARYKEY_Sensor);

    //reuse already found ids
    MAXID(VIEWNAME_SENSORVALUES, PRIMARYKEY_Ping, $pingid);
    MAXID(VIEWNAME_PINGEDGATEWAYS, PRIMARYKEY_Ping, $pingid);
    MAXID(VIEWNAME_PINGEDDEVICES, PRIMARYKEY_Ping, $pingid);
    MAXID(VIEWNAME_EVENTS, PRIMARYKEY_Ping, $event_pingid);
    MAXID(VIEWNAME_GATEWAYS, PRIMARYKEY_Gateway, $gtwid);
}

/**
 * Manage Database
 **/

function Delete_By_Key_Value($table, $key, $value)
{
    $sql = "DELETE FROM $table WHERE $key = $value;";
    SQL_DELETE($sql);
}

function Delete_Unreferenced($table, $key, $reference_table)
{
    $sql = "DELETE FROM $table WHERE $key NOT IN(SELECT $key FROM $reference_table);";
    SQL_DELETE($sql);
}

function Delete_Unreferenced_From_All_Tables()
{
    Delete_Unreferenced(TABLE_SENSORS, PRIMARYKEY_Sensor, TABLE_SENSORVALUES);
    Delete_Unreferenced(TABLE_GATEWAYS, PRIMARYKEY_Gateway, TABLE_PINGEDGATEWAYS);
    Delete_Unreferenced(TABLE_APPLICATIONDEVICES, PRIMARYKEY_ApplicationDevice, TABLE_PINGS);
    Delete_Unreferenced(TABLE_APPLICATIONS, PRIMARYKEY_Application, TABLE_APPLICATIONDEVICES);
    Delete_Unreferenced(TABLE_DEVICES, PRIMARYKEY_Device, TABLE_APPLICATIONDEVICES);
}

function Delete_By_Ping_ID($pingID)
{
    Delete_By_Key_Value(TABLE_EVENTS, PRIMARYKEY_Ping, $pingID);
    Delete_By_Key_Value(TABLE_SENSORVALUES, PRIMARYKEY_Ping, $pingID);
    Delete_By_Key_Value(TABLE_PINGEDGATEWAYS, PRIMARYKEY_Ping, $pingID);
    Delete_By_Key_Value(TABLE_PINGS, PRIMARYKEY_Ping, $pingID);
    Delete_Unreferenced(TABLE_LOCATIONS, PRIMARYKEY_ApplicationDevice, TABLE_PINGS);
}

function Delete_By_ApplicationDeviceID($_appdevid)
{
    $sql = "SELECT " . PRIMARYKEY_Ping . " FROM " . TABLE_PINGS;
    $sql .= " WHERE " . PRIMARYKEY_ApplicationDevice;
    $sql .= " IN(SELECT " . PRIMARYKEY_ApplicationDevice . " FROM " . TABLE_APPLICATIONDEVICES . " WHERE " . PRIMARYKEY_ApplicationDevice . " = $_appdevid)";
    $rows = SQL_QUERY_ROWS($sql);
    add_JSON_message_to_JSON_response($sql);
    add_JSON_message_to_JSON_response(json_encode($rows));
    if ($rows) {
        foreach ($rows as $row) {
//            add_JSON_message(json_encode($row));
//            add_JSON_message($row[PRIMARYKEY_Ping]);
            Delete_By_Ping_ID($row[PRIMARYKEY_Ping]);
        }
    }
    Delete_Unreferenced_From_All_Tables();
}

function Delete_POST_Requests_without_Event()
{
    $sql = "DELETE FROM " . TABLE_POSTREQUESTS . " PR WHERE PR." . PRIMARYKEY_Ping;
    $sql .= " IN(SELECT E." . PRIMARYKEY_Ping . " FROM " . TABLE_EVENTS . " E);";
    SQL_DELETE($sql);
}


/**
 * Execute queries defined as ?query=[name] URI parameter
 *
 * @return string - $sql
 */
function process_Predefined_Query()
{
    global $QueryStringParameters;

    $sql = EMPTY_STRING;

    switch ($QueryStringParameters['query']) {

        /** every heartbeat milliseconds The Dashboard polls for a sinle maximum _pingid **/
        case SQL_QUERY_RecentPingID: // query=PingID   // smallest JSON payload as possible, single pingID
            exit(SQL_Query("SELECT MAX(" . PRIMARYKEY_Ping . ") AS ID FROM " . TABLE_PINGS)['ID']);
            break;

        /** when the _pingid has increased the Dashboard polls for all Table/View ID values **/
        case SQL_QUERY_RecentIDs: // query=IDs   // all relevant IDs , smallest JSON payload as possible
            attach_Max_IDs_to_JSON_response();
            $sql = NO_SQL_QUERY;
            break;

        /** return full TTN JSON request from POSTrequests Table **/
        case SQL_QUERY_Ping:
            $pingid = $QueryStringParameters[PRIMARYKEY_Ping];
            $sql = "SELECT " . ITPINGS_POST_body . " from " . TABLE_POSTREQUESTS . " WHERE " . PRIMARYKEY_Ping . "=$pingid";
            $body = SQL_Query($sql)['body'];
            if ($body) {
                print $body;
                exit();
            } else {
                exit("Sorry,  " . PRIMARYKEY_Ping . "=$pingid has already been purged from " . TABLE_POSTREQUESTS);
            }
            break;

        /** ?query=Devices **/
        case SQL_QUERY_ApplicationDevices:
            $sql = "SELECT AD . " . PRIMARYKEY_ApplicationDevice;
            $sql .= " ,AD . " . ITPINGS_APPLICATION_ID;
            $sql .= " ,AD . " . ITPINGS_DESCRIPTION;
            $sql .= " ,AD . " . ITPINGS_DEVICE_ID;
            $sql .= " ,AD . " . ITPINGS_HARDWARE_SERIAL;
            $sql .= " ,LSV . FirstSeen, LSV . LastSeen";
            $sql .= " FROM " . VIEWNAME_APPLICATIONDEVICES . " AD";
            $sql .= " INNER JOIN(";
            $sql .= " SELECT " . PRIMARYKEY_ApplicationDevice;
            $sql .= " , min(" . ITPINGS_CREATED_TIMESTAMP . ") as FirstSeen";
            $sql .= " , max(" . ITPINGS_CREATED_TIMESTAMP . ") as LastSeen";
            $sql .= " FROM " . TABLE_PINGS;
            $sql .= " GROUP BY " . PRIMARYKEY_ApplicationDevice;
            $sql .= " ) LSV";
            $sql .= " WHERE AD . " . PRIMARYKEY_ApplicationDevice . " = LSV . " . PRIMARYKEY_ApplicationDevice;
            if ($QueryStringParameters[QUERY_PARAMETER_FILTER] ?? false) {
                $sql .= process_QueryParameter_Filter('', ' AND AD.', $QueryStringParameters[QUERY_PARAMETER_FILTER]);
            }
            break;

        /** ?query=DBInfo **/
        case SQL_QUERY_DatabaseInfo_ITpings_Tables:
            attach_Max_IDs_to_JSON_response();
            $sql = "SELECT REPLACE(S . TABLE_NAME, '" . TABLE_PREFIX . "', '') AS 'Table'";
            $sql .= ",S . TABLE_ROWS AS Rows";
            $sql .= ",S . AVG_ROW_LENGTH AS RowLength, S . DATA_LENGTH AS DataLength";
            $sql .= ",S . INDEX_LENGTH AS IndexLength,S . DATA_FREE AS Free";
            $sql .= " FROM information_schema . tables S";
            $sql .= " WHERE table_name LIKE '" . TABLE_PREFIX . "%'";
            $sql .= " AND TABLE_TYPE = 'BASE TABLE'";
            $sql .= " ORDER BY DataLength ASC";
            break;


        /** Delete queries, called from Dashboard */

        /** ?query=DeletePing&_pingid=[id] */
        case 'DeletePingID':
            $pingid = $QueryStringParameters[PRIMARYKEY_Ping];
            if ($pingid) {
                Delete_By_Ping_ID($pingid);
            }
            break;

        /** Delete queries, to be called by handcrafting the URI */

        case 'DeleteNullPings':
            $sql = "SELECT * FROM " . TABLE_PINGS . " P";
            $sql .= " JOIN " . TABLE_POSTREQUESTS . " PR ON PR." . PRIMARYKEY_Ping . " = P." . PRIMARYKEY_Ping;
            $sql .= " WHERE " . PRIMARYKEY_ApplicationDevice . " IS null ORDER BY " . PRIMARYKEY_Ping . " DESC";
            $rows = SQL_QUERY_ROWS($sql);
            add_JSON_message_to_JSON_response($sql);
            if ($rows) {
                foreach ($rows as $row) {
//                    $request = json_decode($row[ITPINGS_POST_body], TRUE);
//                    process_Ping_from_JSON_request($request);
//                    add_JSON_message($row[PRIMARYKEY_Ping]);
                    Delete_By_Ping_ID($row[PRIMARYKEY_Ping]);
                }
            }
            break;

        /** ?query=DeleteApplicationByID&appid=9 **/
        case 'DeleteApplicationByID':
            $appid = $QueryStringParameters[PRIMARYKEY_Application];
            if ($appid) Delete_By_ApplicationDeviceID($appid);
            break;

        case 'DeleteProcessedPOSTrequests':
            Delete_POST_Requests_without_Event();
            break;

        // SELECT * FROM ITpings.ITpings__PingedGateways where time='0000-00-00 00:00:00';

    }

    return $sql;
}

/**
 * Process QueryString part for LT and GT like operators; and return a valid SQL $where clause
 * @param $where
 * @param $and
 * @param $parameter_value
 * @return string
 */
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
        elseif ($operator === 'eq') $where .= " = ";
        $where .= $value;
        $and = ' AND ';
    }
    return $where;
}

/**
 * For debugging purposes, add data to the JSON output
 * @param $key
 * @param $value
 */
function QueryTrace($key, $value)
{
    global $JSON_response;
    if (!isset($JSON_response['QueryTrace'])) $JSON_response['QueryTrace'] = [];
    $JSON_response['QueryTrace'][$key] = $value;
}


/**
 *
 */
function process_Query_with_QueryString_Parameters()
{
    global $QueryStringParameters;
    global $JSON_response;
    $sql = EMPTY_STRING;

    $table_name = TABLE_PREFIX . $QueryStringParameters['query'];

    /** User can only request for limitted table/view names, this is the place to deny access to Tables/Views **/
    switch ($table_name) {

        /**!!!!!!!!!!!!!!!!! ALWAYS CREATE OR REPLACE VIEW ON EVERY ENDPOINT CALL !!!!!!!!!!!!!!!!!!!!!!!!!!!!**/

        //case VIEWNAME_TEMPERATURE:
        case 'ALWAYS_CREATE_OR_REPLACE_VIEW'://todo: read VIEW NAME to be updated from querystring parameter
            add_JSON_message_to_JSON_response('ViewUpdate: ' . $table_name);
            Create_Or_Replace_View($table_name);
            break;



        /**  regular View/Table names **/
        case VIEWNAME_TEMPERATURE:
        case VIEWNAME_SENSORVALUES:
        case VIEWNAME_SENSORVALUES_UPDATE:
        case VIEWNAME_EVENTS:
        case VIEWNAME_PINGEDDEVICES:
        case VIEWNAME_PINGEDGATEWAYS:
        case VIEWNAME_GATEWAYS:
        case VIEWNAME_APPLICATIONDEVICES:
            break;

        case TABLE_EVENTS:
        case TABLE_POSTREQUESTS:
        case TABLE_APPLICATIONS:
        case TABLE_DEVICES:
            //case TABLE_APPLICATIONDEVICES:// User can not access this table
        case TABLE_GATEWAYS:
        case TABLE_LOCATIONS:
        case TABLE_PINGS:
        case TABLE_PINGEDGATEWAYS:
        case TABLE_SENSORS:
        case TABLE_SENSORVALUES:

            break;
        default:
            $sql = process_Predefined_Query();
            $table_name = false;
            break;
    }

    post_process_Query($table_name,$sql);

}

/**
 * Continue from previous function(process_Query_with_QueryString_Parameters), built a valid $sql
 * @param $table_name
 * @param $sql
 */
function post_process_Query($table_name, $sql)
{
    global $QueryStringParameters;
    global $JSON_response;
    global $_VALID_QUERY_PARAMETERS;
    global $_QUERY_ALLOWED_INTERVALUNITS;

    if ($table_name) {
        /** process all (by ITpings defined!!!) QueryString parameters , so user can not add roque SQL **/
        $where = EMPTY_STRING;
        $order = EMPTY_STRING;
        $limit = EMPTY_STRING;

        foreach ($_VALID_QUERY_PARAMETERS as $parameter) {

            /** accept safe parameter values only **/
            $parameter_value = SQL_InjectionSave_OneWordString($QueryStringParameters[$parameter] ?? '');

            if (ITPINGS_QUERY_TRACE) QueryTrace($parameter, $parameter_value);
            if ($parameter_value) {
                $PARAMETER_HAS_SEPARATOR = contains($parameter_value, QUERY_PARAMETER_SEPARATOR);
                $and = $where === EMPTY_STRING ? EMPTY_STRING : " AND ";

                switch ($parameter) {

                    case QUERY_PARAMETER_FILTER:
                        //do NOT ADD to $where, the function creates a new $where with previous content prepended
                        $where = process_QueryParameter_Filter($where, $and, $parameter_value);
                        break;

                    case QUERY_PARAMETER_INTERVAL:
                        //https://dev.mysql.com/doc/refman/5.7/en/date-and-time-functions.html#function_date-add
                        $interval_unit = strtoupper($QueryStringParameters[QUERY_PARAMETER_INTERVALUNIT]);
                        if (!in_array($interval_unit, $_QUERY_ALLOWED_INTERVALUNITS)) {
                            $interval_unit = 'HOUR';
                        }
                        $where .= $and . ITPINGS_CREATED_TIMESTAMP . " >= DATE_SUB(NOW(), INTERVAL " . (int)$parameter_value . " " . $interval_unit . ")";
                        break;

                    case QUERY_PARAMETER_INTERVALUNIT:// processed in previous interval case
                        break;

                    case QUERY_PARAMETER_ORDERBY:
                        if ($PARAMETER_HAS_SEPARATOR) {
                            $orderbyfields = [];
                            //accept only valid fieldnames
                            foreach (explode(QUERY_PARAMETER_SEPARATOR, $parameter_value) as $field) {
                                $field = explode(' ', $field);
                                $fieldname = $field[0];
                                $fieldsort = $field[1];
                                if (!$fieldsort) $fieldsort = 'ASC';
                                if (in_array($fieldname, $_VALID_QUERY_PARAMETERS)) {
                                    $orderbyfields[] .= $fieldname . ' ' . $fieldsort;
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
                        if (contains($parameter_value, '%')) {
                            $where .= $and . "$parameter LIKE " . Quoted($parameter_value);
                        } else {
                            if ($PARAMETER_HAS_SEPARATOR) {
                                $where .= $and . "$parameter IN(";
                                function convertValue($value)
                                {
                                    return is_string($value) ? Quoted($value) : $value;
                                }

                                $glue = QUERY_PARAMETER_SEPARATOR;
                                $where .= implode($glue, array_map('convertValue', explode($glue, $parameter_value)));
                                $where .= ")";
                            } else {
                                $parameter_value = (is_numeric($parameter_value) ? Valued($parameter_value) : Quoted($parameter_value));
                                $where .= $and . "$parameter = " . $parameter_value;
                            }
                        }
                        if (ITPINGS_QUERY_TRACE) QueryTrace($parameter, $parameter_value);
                        break;
                }
            }
        }
        $sql = "SELECT * FROM $table_name";

        if ($where !== EMPTY_STRING) $sql .= " WHERE $where";

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
        $JSON_response['errors'] .= "Error: Empty SQL statement";
    } else {
        if ($sql === NO_SQL_QUERY) {
            remove_no_longer_required_keys_from_JSON_response();
            return_JSON_response();
        } else {
            SQL_Query($sql, TRUE);
        }
    }

}

//endregion == PROCESS GET QUERY ==================================================================

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
function trace($txt)
{
    global $request;
    //echo "\n" . $txt . "\n" . implode(",", $request);
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
            process_Query_with_QueryString_Parameters();
    }
}

mysqli_close($MySQL_DB_Connection);

//endregion === ITpings - MAIN CODE ================================================================
