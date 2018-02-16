<?php
include('ITpings_access_configuration.php');

//region ===== APPLICATION CONFIGURATION ==========================================================

// shorten the downlink URI going into the database, strip this first string from entry
define('TTN_DOWNLINKROOT', 'https://integrations.thethingsnetwork.org/ttn-eu/api/v2/down/');

// GPS has inaccurate fixes, to prevent 'moving' Gateway recordings a tolerance for lat/lon is calculated
// Gateways outside this tolerance will be recorded as new (moved) Gateway
define('GATEWAY_POSITION_TOLERANCE', '0.015');      // 15 meters

// set to FALSE in Production, used for debugging purposes only
define('SAVE_BARE_POST_REQUESTS', TRUE);

//endregion == APPLICATION CONFIGURATION ==========================================================


define('USE_REFERENTIAL_INTEGRITY', TRUE);  // FALSE will NOT create Indexes and enforce Foreign Keys

/**
 * Database Table names
 * **/
define('TABLE_PREFIX', 'ttn__');    // optional double underscore groups table list as 'ttn' in PHPMyAdmin

define('TABLE_EVENTS', TABLE_PREFIX . 'events');
define('TABLE_POSTREQUESTS', TABLE_PREFIX . 'POSTrequests');
define('TABLE_APPLICATIONS', TABLE_PREFIX . 'applications');
define('TABLE_DEVICES', TABLE_PREFIX . 'devices');
define('TABLE_APPLICATIONDEVICES', TABLE_PREFIX . 'application_devices');
define('TABLE_GATEWAYS', TABLE_PREFIX . 'gateways');
define('TABLE_PINGS', TABLE_PREFIX . 'pings');
define('TABLE_PINGEDGATEWAYS', TABLE_PREFIX . 'pinged_gateways');
define('TABLE_SENSORS', TABLE_PREFIX . 'sensors');
define('TABLE_SENSORVALUES', TABLE_PREFIX . 'sensorvalues');

// All Tables, specific order that complies with referential integrity, so DROP TABLE is executed in correct order
define('ITPINGS_TABLES', array(
    TABLE_EVENTS
, TABLE_SENSORVALUES
, TABLE_SENSORS
, TABLE_PINGEDGATEWAYS
, TABLE_GATEWAYS
, TABLE_PINGS
, TABLE_APPLICATIONDEVICES
, TABLE_DEVICES
, TABLE_APPLICATIONS
, TABLE_POSTREQUESTS
));

//ITpings Table field keys, beware they don't collide with the TTN JSON fieldname definitions below
define('PRIMARYKEY_PREFIX', '_');
define('PRIMARYKEY_POSTrequests', PRIMARYKEY_PREFIX . 'postid');
define('PRIMARYKEY_Application', PRIMARYKEY_PREFIX . 'appid');
define('PRIMARYKEY_Device', PRIMARYKEY_PREFIX . 'devid');
define('PRIMARYKEY_ApplicationDevice', PRIMARYKEY_PREFIX . 'appdevid');
define('PRIMARYKEY_Gateway', PRIMARYKEY_PREFIX . 'gtwid');
define('PRIMARYKEY_Ping', PRIMARYKEY_PREFIX . 'pingid');
define('PRIMARYKEY_Sensor', PRIMARYKEY_PREFIX . 'sensorid');

//region ===== (TTN) THE THINGS NETWORK JSON FIELDNAMES ===========================================

//TTN JSON fieldname definitions, defined in the TTN HTTP integration (Cayenne style)
define('TTN_app_id', 'app_id');
define('TTN_dev_id', 'dev_id');
define('TTN_gtw_id', 'gtw_id');

define('TTN_hardware_serial', 'hardware_serial');

define('TTN_latitude', 'latitude');
define('TTN_longitude', 'longitude');
define('TTN_altitude', 'altitude');

//fieldnames in TTN JSON format
define('TTN_gtw_trusted', 'gtw_trusted');
define('TTN_location_source', 'location_source');
define('TTN_timestamp', 'timestamp');
define('TTN_time', 'time');
define('TTN_channel', 'channel');
define('TTN_rssi', 'rssi');
define('TTN_snr', 'snr');
define('TTN_rf_chain', 'rf_chain');
define('TTN_port', 'port');
define('TTN_downlink_url', 'downlink_url');
define('TTN_counter', 'counter');
define('TTN_payload_raw', 'payload_raw');
define('TTN_frequency', 'frequency');
define('TTN_airtime', 'airtime');
define('TTN_modulation', 'modulation');
define('TTN_data_rate', 'data_rate');
define('TTN_coding_rate', 'coding_rate');
//used to reference TTN JSON structure
define('TTN_metadata', 'metadata');
define('TTN_gateways', 'gateways');
define('TTN_payload_fields', 'payload_fields');

//Sensor names
define('TTN_Cayenne_accelerometer', 'accelerometer_7');
define('TTN_Cayenne_analog_in', 'analog_in_4');
define('TTN_Cayenne_digital_in_1', 'digital_in_1');
define('TTN_Cayenne_digital_in_2', 'digital_in_2');
define('TTN_Cayenne_digital_in_3', 'digital_in_3');
define('TTN_Cayenne_luminosity', 'luminosity_6');
define('TTN_Cayenne_temperature', 'temperature_5');

//endregion == (TTN) THE THINGS NETWORK JSON FIELDNAMES ===========================================


// ITpings SQL schema
define('ENUM_EVENTTYPE_NewApp', 'NewApp');
define('ENUM_EVENTTYPE_NewDevice', 'NewDevice');
define('ENUM_EVENTTYPE_NewGateway', 'NewGateway');
define('ENUM_EVENTTYPE_NewSensor', 'NewSensor');
define('ENUM_EVENTTYPE_Log', 'Log');
define('ENUM_EVENTTYPE_Trigger', 'Trigger');
define('ENUM_EVENTTYPE_Error', 'Error');
//array of above types
define('ENUM_EVENTTYPES', array(ENUM_EVENTTYPE_NewApp, ENUM_EVENTTYPE_NewDevice, ENUM_EVENTTYPE_NewGateway, ENUM_EVENTTYPE_NewSensor, ENUM_EVENTTYPE_Log, ENUM_EVENTTYPE_Trigger, ENUM_EVENTTYPE_Error));
define('ITPINGS_EVENTTYPE', 'eventtype');
define('ITPINGS_EVENTLABEL', 'eventlabel');
define('ITPINGS_EVENTVALUE', 'eventvalue');
define('TYPE_EVENTLABEL', 'VARCHAR(256)');
define('TYPE_EVENTVALUE', 'VARCHAR(4096)');

define('ITPINGS_SENSORNAME', 'sensorname');
define('ITPINGS_SENSORVALUE', 'sensorvalue');

define('ITPINGS_CREATED_TIMESTAMP', 'created');
define('ITPINGS_DESCRIPTION', 'description');

//for debugging; single table to record whole POST as TEXT blob
define('ITPINGS_POST_body', 'body');
define('TYPE_POST_BODY', 'VARCHAR(4048)');


define('ITPINGS_METADATA_PREFIX', 'meta_');  // for clear SQL building make sure all fieldnames in all tables are unique
define('ITPINGS_TIME', ITPINGS_METADATA_PREFIX . TTN_time);
define('ITPINGS_FREQUENCY', ITPINGS_METADATA_PREFIX . TTN_frequency);
define('ITPINGS_MODULATION', ITPINGS_METADATA_PREFIX . TTN_modulation);
define('ITPINGS_DATA_RATE', ITPINGS_METADATA_PREFIX . TTN_data_rate);
define('ITPINGS_CODING_RATE', ITPINGS_METADATA_PREFIX . TTN_coding_rate);
define('ITPINGS_LATITUDE', ITPINGS_METADATA_PREFIX . TTN_latitude);
define('ITPINGS_LONGITUDE', ITPINGS_METADATA_PREFIX . TTN_longitude);
define('ITPINGS_ALTITUDE', ITPINGS_METADATA_PREFIX . TTN_altitude);
define('ITPINGS_LOCATIONSOURCE', ITPINGS_METADATA_PREFIX . TTN_location_source);

/**
 * Database Schema standards MATCHING the TTN JSON fieldnames, CHANGE with CARE!
 **/
define('TYPE_TTN_TIMESTRING', 'VARCHAR(30)');       // "2018-01-25T11:40:43.427237826Z" = 30 characters
define('TYPE_TYPE_TIMESTAMP', 'INT UNSIGNED');      // ?? Timestamp when the gateway received the message

define('TYPE_TTN_ID_FIELD', 'VARCHAR(1024)');       // ?? what are the TTN maximums?
define('TYPE_TTN_APP_ID', TYPE_TTN_ID_FIELD);
define('TYPE_TTN_APP_DESCRIPTION', TYPE_TTN_ID_FIELD);
define('TYPE_TTN_GTW_ID', TYPE_TTN_ID_FIELD);
define('TYPE_TTN_DEVICE_ID', TYPE_TTN_ID_FIELD);

define('TYPE_TTN_TRUSTED_GTW', 'TINYINT UNSIGNED NOT NULL DEFAULT 0'); // boolean

define('TYPE_TTN_FRAME_COUNTER', 'INT UNSIGNED');   // Frame Counter

define('TYPE_TTN_DOWNLINK', 'VARCHAR(1024)');       // ?? save Web URL = 2000
define('TYPE_TTN_PAYLOAD_RAW', 'VARCHAR(1024)');    // ?? 256 enough?

define('TYPE_TTN_CHANNEL', 'INT UNSIGNED');         // ?? 0 - 7
define('TYPE_TTN_RSSI', 'INT SIGNED');              // ?? -85 dBm to -45dBm
//define('TYPE_TTN_SNR', 'DECIMAL');                // ?? 8.25 Decibels ?? DD.dd
define('TYPE_TTN_SNR', 'TINYINT UNSIGNED');         // ?? 5 - 13
define('TYPE_TTN_RFCHAIN', 'INT UNSIGNED');         // ?? 0

define('TYPE_TTN_HARDWARE_SERIAL', 'VARCHAR(16)');  // LoraWan: Device EUI

define('TYPE_TTN_PORT', 'INT'); // ?? 1

//https://www.thethingsnetwork.org/wiki/LoRaWAN/Frequencies/Frequency-Plans
define('TYPE_TTN_FREQUENCY', 'DECIMAL(4,1)');   // ?? 867.1  is DDD,d enough?
define('TYPE_TTN_MODULATION', 'VARCHAR(16)');   // ?? "LORA" or anything else?
define('TYPE_TTN_DATA_RATE', 'VARCHAR(9)');     // ?? "SF7BW125" to "SF12BW500"
define('TYPE_TTN_CODING_RATE', 'VARCHAR(16)');  // ?? "4/5"

define('LATITUDE_ACCURACY', 'DECIMAL(10,8)');   // -90 to 90 with 8 decimals (TTN does 7 decimals)
define('LONGITUDE_ACCURACY', 'DECIMAL(11,8)');  // -180 to 180 with 8 decimals (TTN does 7 decimals)
define('ALTITUDE_ACCURACY', 'DECIMAL(5,2)');    // centimeter accuracy up to 999,99
define('TYPE_LOCATION_SOURCE', 'VARCHAR(16)');  // ?? "registry" what else?

define('TYPE_PAYLOAD_KEY', 'VARCHAR(256)');
define('TYPE_PAYLOAD_VALUE', 'VARCHAR(1024)');


//QUERY_DEFINITIONS
/**
 * Parameters that can be used in GET/URL queries
 **/
define('QUERY_PARAMETER_INTERVAL', 'interval');
define('QUERY_PARAMETER_INTERVALUNIT', 'intervalunit');
define('QUERY_ALLOWED_INTERVALUNITS', ['SECOND', 'MINUTE', 'HOUR', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR']);
define('QUERY_PARAMETER_ORDERBY', 'orderby');
define('QUERY_PARAMETER_ORDERSORT', 'ordersort');
define('QUERY_PARAMETER_LIMIT', 'limit');
define('QUERY_PARAMETER_SEPARATOR', ','); // for making IN (a,b,c) queries

/**
 * Only these fieldnames can be used as WebService Query URI parameters
 * **/
define('VALID_QUERY_PARAMETERS', [
    PRIMARYKEY_Application,
    PRIMARYKEY_Device,
    PRIMARYKEY_Sensor,
    ITPINGS_SENSORNAME,
    ITPINGS_SENSORVALUE,
    PRIMARYKEY_Ping,
    ITPINGS_CREATED_TIMESTAMP,
    PRIMARYKEY_Gateway,
    ITPINGS_EVENTTYPE,
    ITPINGS_EVENTLABEL,
    ITPINGS_EVENTVALUE,
    QUERY_PARAMETER_INTERVAL,
    QUERY_PARAMETER_INTERVALUNIT,
    QUERY_PARAMETER_ORDERBY,
    QUERY_PARAMETER_ORDERSORT,
    QUERY_PARAMETER_LIMIT
]);


//CONSTANTS, no need to change, results in better readable PHP $sql building code
define('TYPE_FOREIGNKEY', 'INT UNSIGNED');
define('SQL_INSERT_INTO', "INSERT INTO" . " ");
define('SQL_VALUES_START', " VALUES ( ");  // one null is _id (autonumber)
define('SQL_VALUES_CLOSE', ");");
define('AUTOINCREMENT_TABLE_PRIMARYKEY', 'NULL');
define('NO_FOREIGNKEYS', FALSE);    // always False to incicate  Table does not have Foreign Keys
define('NO_PRIMARYKEY', FALSE); // always False to indicate a Table does not have a primary key
define('IS_A_FOREIGNKEY_IN', "REFERENCES ");
define('COMMA', ',');
define('EMPTYSTRING', '');
define('NO_CONDITIONS', '');
define('IS_POST', $_SERVER['REQUEST_METHOD'] === 'POST');
define('LASTENTRY', " ORDER BY " . ITPINGS_CREATED_TIMESTAMP . " DESC LIMIT 1;");
define('ITpings_PrimaryKey_In_Table', 'ITpings PrimaryKey in ');


//process QueryString variables
$urlVars = array();
parse_str($_SERVER['QUERY_STRING'], $urlVars);
define('ADMIN_ACTION', $urlVars['action']);
define('API_QUERY', $urlVars['query']);


//region Database usage

$conn = mysqli_connect(DBHOST, DBUSERNAME, DBPASSWORD, DBNAME);

if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

$sqlLog = array();              // log all SQL statements
/**
 * @param $str
 *
 * add $str to global $sqlLog array
 */
function add_QueryLog($str)
{
    global $sqlLog;
    $sqlLog[] = $str;
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

    add_QueryLog($sql);

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

//endregion

//PHP error reporting
if (!IS_POST) {
    $ebits = ini_get('error_reporting');
    error_reporting($ebits ^ E_ALL);
}

/**
 * @param $str
 * @return mixed
 *
 * brutal approach on SQL injection attempts
 * Return first element after split on 'illegal' SQL characters
 */
function SQL_InjectionSave_OneWordString($str)
{
    return preg_split("/[&=:;]/", $str)[0];
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
    return Quoted($val, ""); // No quotes
}

/**
 * @param $val
 * @return int
 * return T/F
 */
function Boolean($val)
{
    return $val ? 1 : 0;
}

//names match with create_VIEW_[name] functiondefinitions
define('VIEWPREFIX', TABLE_PREFIX);
define('VIEWNAME_EVENTS', VIEWPREFIX . 'Events');
define('VIEWNAME_APPLICATIONDEVICES', VIEWPREFIX . 'ApplicationDevices');
define('VIEWNAME_SENSORVALUES', VIEWPREFIX . 'SensorValues');
define('VIEWNAME_PINGEDGATEWAYS', VIEWPREFIX . 'PingedGateways');
define('VIEWNAMES', [
    VIEWNAME_EVENTS
    , VIEWNAME_APPLICATIONDEVICES
    , VIEWNAME_SENSORVALUES
    , VIEWNAME_PINGEDGATEWAYS]);

define('VIEWS_WITH_EXPANDED_KEYS', TRUE); // expand Foreign Keys, JSON will include more information