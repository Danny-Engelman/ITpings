<?php
include('ITpings_access_database.php');

//region ===== APPLICATION DEVELOPMENT ============================================================

//to do
// viz.js
// graph RSSI/SNR
// View Pings (use PingedGateways)
// use pings.meta_time instead of created

//endregion == APPLICATION DEVELOPMENT ============================================================

//region ===== APPLICATION CONFIGURATION ==========================================================

// allow front-end to drop Database or create or replace Views
define('ALLOW_DATABASE_CHANGES', TRUE);

// IP address where Ping came from
// Let's record for some period and see if it is usefull at all
// portnumber is different for each call, no need to record
$ip = ($_SERVER['HTTP_CLIENT_IP'] ?: ($_SERVER['HTTP_X_FORWARDE‌​D_FOR'] ?: $_SERVER['REMOTE_ADDR']));
define('PING_ORIGIN', $ip);

// CREATE Complete ITpings Database Schema IF it does not exist, set to FALSE to disable the check
define('CREATE_DATABASE_ON_FIRST_PING', TRUE);

// set to FALSE in Production to save lots of database resources, can be used for debugging purposes
// after first run, in an existing database you have to add/delete the POSTrequest Table by hand
define('SAVE_POST_AS_ONE_STRING', TRUE);

// GPS has inaccurate fixes, to prevent 'moving' Gateway recordings a tolerance for lat/lon is calculated
// Gateways outside this tolerance will be recorded as new (moved) Gateway
define('GATEWAY_POSITION_TOLERANCE', '0.02');      // 20 Meter

//default LIMIT when none is specified
define('SQL_LIMIT_DEFAULT', 30);

// field `downlink_url` is of no use once it can't be used anymore to downlink data to a Node
// To save database space these fields can be reset to empty values
// eg. 3 Devices each pinging every minute = 3*60 = 180 pings (1 hour) will be saved
define('PURGE_PINGCOUNT', 180);

// MySQL version 5.6.4 with DATETIME(6) is required to save ping.meta_time in Time with milliseconds
// https://dev.mysql.com/doc/refman/5.6/en/fractional-seconds.html
// See TYPE_TIME_DATETIME below


// Not quite sure yet how to deal with Altitude
// ping metadata location info is stored in a separate Table __locations
// to supress reports for different Altitude for a given lat/lon location set to FALSE
define('CHECK_THE_ALTITUDE_IN_PING', TRUE);

// add extra JSON output to trace what ITpings connector does
// handy for debugging QueryString Parameters to SQL parser in connector.php
define('ITPINGS_QUERY_TRACE', FALSE);

// NOTE: PHP 5 requires Scalar variables in DEFINE statements, PHP7 accepts arrays
// so $_ global variables can become proper defines in PHP 7

//endregion == APPLICATION CONFIGURATION ==========================================================

//region ===== DATABASE SCHEMA AND CONFIGURATION ==================================================

/** ==> Creation of Tables and Views is in the ITpings_connector.php file **/

// Prefix for Tables and Views
// optional double underscore groups table list in PHPMyAdmin, good for installing ITpings in an existing DB (like WordPress)
define('TABLE_PREFIX', 'ITpings__');


// Lookup Tables (result of normalizing the Database)
define('TABLE_FREQUENCIES', TABLE_PREFIX . 'frequencies');
define('TABLE_MODULATIONS', TABLE_PREFIX . 'modulations');
define('TABLE_DATARATES', TABLE_PREFIX . 'datarates');
define('TABLE_CODINGRATES', TABLE_PREFIX . 'codingrates');

// GPS location, incl. alt and TTN location_source
define('TABLE_LOCATIONS', TABLE_PREFIX . 'locations');

// Main Tables
define('TABLE_ORIGINS', TABLE_PREFIX . 'origins');
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

// Data Tables
define('TABLE_TEMPERATURE', TABLE_PREFIX . 'data_temperature');
define('TABLE_LUMINOSITY', TABLE_PREFIX . 'data_luminosity');

// All Tables, order complies with referential integrity, so DROP TABLE is executed in correct order
$_ITPINGS_TABLES = array(
    TABLE_ORIGINS
, TABLE_EVENTS
, TABLE_SENSORVALUES
, TABLE_SENSORS
, TABLE_PINGEDGATEWAYS
, TABLE_GATEWAYS
, TABLE_FREQUENCIES
, TABLE_MODULATIONS
, TABLE_DATARATES
, TABLE_CODINGRATES
, TABLE_LOCATIONS
, TABLE_PINGS
, TABLE_APPLICATIONDEVICES
, TABLE_DEVICES
, TABLE_APPLICATIONS
, TABLE_POSTREQUESTS
);


/**
 * Database Schema TYPE standards MATCHING the TTN JSON fieldnames, CHANGE with CARE!
 **/

define('TYPE_FOREIGNKEY', 'INT UNSIGNED');
define('TYPE_FOREIGNKEY_LOOKUPTABLE', 'TINYINT UNSIGNED');

define('TYPE_VARCHAR_ID_FIELD', 'VARCHAR(512)');       // ?? what are the TTN maximums?


// ITpings Table field keys, beware they don't collide with the TTN JSON fieldname definitions below
define('PRIMARYKEY_PREFIX', '_');


//for debugging; single table to record whole POST as TEXT blob
define('PRIMARYKEY_POSTrequests', PRIMARYKEY_PREFIX . 'postid');
define('ITPINGS_POST_body', 'body');
$_DBFIELD_POST_BODY = [ITPINGS_POST_body, 'VARCHAR(4048)', 'Bare POST body'];

define('PRIMARYKEY_Origin', PRIMARYKEY_PREFIX . 'originid');

define('PRIMARYKEY_Application', PRIMARYKEY_PREFIX . 'appid');
$_DBFIELD_PRIMARYKEY_APPLICATION = [PRIMARYKEY_Application, TYPE_FOREIGNKEY, 'PrimaryKey:' . TABLE_APPLICATIONS];

define('PRIMARYKEY_Device', PRIMARYKEY_PREFIX . 'devid');
$_DBFIELD_PRIMARYKEY_DEVICE = [PRIMARYKEY_Device, TYPE_FOREIGNKEY, 'PrimaryKey:' . TABLE_DEVICES];

define('PRIMARYKEY_ApplicationDevice', PRIMARYKEY_PREFIX . 'appdevid');
$_DBFIELD_APPLICATION_DEVICE = [PRIMARYKEY_ApplicationDevice, TYPE_FOREIGNKEY, 'PrimaryKey:' . TABLE_APPLICATIONDEVICES];

define('PRIMARYKEY_Gateway', PRIMARYKEY_PREFIX . 'gtwid');
$_DBFIELD_GATEWAY = [PRIMARYKEY_Gateway, TYPE_FOREIGNKEY, 'PrimaryKey:' . TABLE_GATEWAYS];

define('PRIMARYKEY_Frequency', PRIMARYKEY_PREFIX . 'frqid');

define('PRIMARYKEY_Modulation', PRIMARYKEY_PREFIX . 'modid');

define('PRIMARYKEY_Datarate', PRIMARYKEY_PREFIX . 'drid');

define('PRIMARYKEY_Codingrate', PRIMARYKEY_PREFIX . 'crid');

define('PRIMARYKEY_Location', PRIMARYKEY_PREFIX . 'locid');
$_DBFIELD_LOCATION = [PRIMARYKEY_Location, TYPE_FOREIGNKEY, 'PrimaryKey:' . TABLE_LOCATIONS];

define('PRIMARYKEY_Ping', PRIMARYKEY_PREFIX . 'pingid');
$_DBFIELD_PRIMARYKEY_PING = [PRIMARYKEY_Ping, TYPE_FOREIGNKEY, 'PrimaryKey:' . TABLE_PINGS];

define('PRIMARYKEY_Sensor', PRIMARYKEY_PREFIX . 'sensorid');
$_DBFIELD_PRIMARYKEY_SENSOR = [PRIMARYKEY_Sensor, TYPE_FOREIGNKEY, 'PrimaryKey:' . TABLE_SENSORS];

//region ===== ITPINGS AND (TTN) THE THINGS NETWORK JSON FIELDNAMES ===============================

//Cayenne LPP Sensor names
define('TTN_Cayenne_accelerometer', 'accelerometer_7');
define('TTN_Cayenne_analog_in', 'analog_in_4');
define('TTN_Cayenne_digital_in_1', 'digital_in_1');
define('TTN_Cayenne_digital_in_2', 'digital_in_2');
define('TTN_Cayenne_digital_in_3', 'digital_in_3');
define('TTN_Cayenne_luminosity', 'luminosity_6');
define('TTN_Cayenne_temperature', 'temperature_5');


define('TTN_app_id', 'app_id');
define('ITPINGS_APPLICATION_ID', TTN_app_id);
$_DBFIELD_APPLICATION_ID = [ITPINGS_APPLICATION_ID, TYPE_VARCHAR_ID_FIELD, "TTN Application ID (name)"];

$_DBFIELD_APPLICATION_DESCRIPTION = ['description', TYPE_VARCHAR_ID_FIELD, "todo: try and match with TTN database"];

define('TTN_dev_id', 'dev_id');
define('ITPINGS_DEVICE_ID', TTN_dev_id);
$_DBFIELD_DEVICE_ID = [ITPINGS_DEVICE_ID, TYPE_VARCHAR_ID_FIELD, "TTN Device ID (name)"];

define('TTN_gtw_id', 'gtw_id');
define('ITPINGS_GATEWAY_ID', TTN_gtw_id);
$_DBFIELD_GATEWAY_ID = [ITPINGS_GATEWAY_ID, TYPE_VARCHAR_ID_FIELD, 'TTN Gateway ID (name)'];

define('TTN_hardware_serial', 'hardware_serial');
define('ITPINGS_HARDWARE_SERIAL', 'serial');
$_DBFIELD_HARDWARE_SERIAL = [ITPINGS_HARDWARE_SERIAL, 'VARCHAR(16)', 'TTN Hardware Serial'];

define('TTN_latitude', 'latitude');
define('ITPINGS_LATITUDE', 'lat');                  // used in ITpings Tables and JSON output
$_DBFIELD_LATITUDE = [ITPINGS_LATITUDE, 'DECIMAL(10,8)', 'TTN Ping Latitude'];       // -90 to 90 with 8 decimals (TTN does 7 decimals)

define('TTN_longitude', 'longitude');
define('ITPINGS_LONGITUDE', 'lon');
$_DBFIELD_LONGITUDE = [ITPINGS_LONGITUDE, 'DECIMAL(11,8)', 'TTN Ping Longitude'];      // -180 to 180 with 8 decimals (TTN does 7 decimals)

define('TTN_altitude', 'altitude');
define('ITPINGS_ALTITUDE', 'alt');
$_DBFIELD_ALTITUDE = [ITPINGS_ALTITUDE, 'DECIMAL(5,2)', 'TTN Ping Altitude'];        // centimeter accuracy up to 999,99

define('ITPINGS_HDOP', 'HDOP');
$_DBFIELD_HDOP = [ITPINGS_HDOP, 'TINYINT UNSIGNED', 'GPS accuracy'];        // centimeter accuracy up to 999,99

define('TTN_gtw_trusted', 'gtw_trusted');
define('ITPINGS_TRUSTED', 'trusted');
$_DBFIELD_TRUSTED_GATEWAY = [ITPINGS_TRUSTED, 'TINYINT UNSIGNED NOT NULL DEFAULT 0', 'TTN Gateway Trusted']; // boolean

define('TTN_location_source', 'location_source');
define('ITPINGS_LOCATION_SOURCE', 'src');
$_DBFIELD_LOCATION_SOURCE = [ITPINGS_LOCATION_SOURCE, TYPE_FOREIGNKEY_LOOKUPTABLE, 'always registry?']; // ?? "registry" what else? // hardcoded as 1 in SQL code !!

define('TTN_timestamp', 'timestamp');
define('ITPINGS_TIMESTAMP', 'timestamp');
$_DBFIELD_PINGED_GATEWAY_TIMESTAMP = [ITPINGS_TIMESTAMP, 'INT UNSIGNED', 'TTN GatewayPing Timestamp'];      // ?? Timestamp when the gateway received the message

define('TTN_time', 'time');
define('ITPINGS_TIME', TTN_time);

// standard DATETIME does not store microseconds, DATETIME(6) does, but is not supported in pre MySQL 5.6 versions
define('TYPE_TIME_COMMENT', 'converted TTN time WITHOUT FRACTION IN OLDER MySQL server!');
$_DBFIELD_ITPINGS_TIME = [ITPINGS_TIME, 'DATETIME', TYPE_TIME_COMMENT];
//define('TYPE_ITPINGS_TIME', 'VARCHAR(30)');       // "2018-01-25T11:40:43.427237826Z" = 30 characters


define('TTN_channel', 'channel');
define('ITPINGS_CHANNEL', 'channel');
$_DBFIELD_CHANNEL = [ITPINGS_CHANNEL, TYPE_FOREIGNKEY_LOOKUPTABLE, 'TTN GatewayPing Channel'];       // ?? 0 - 7

define('TTN_rssi', 'rssi');
define('ITPINGS_RSSI', TTN_rssi);
$_DBFIELD_RSSI = [ITPINGS_RSSI, 'TINYINT SIGNED', 'TTN GatewayPing RSSI'];          // ?? -85 dBm to -45dBm

define('TTN_snr', 'snr');
define('ITPINGS_SNR', TTN_snr);
$_DBFIELD_SNR = [ITPINGS_SNR, 'DECIMAL(4,2)', 'TTN GatewayPing SNR'];             // ?? 8.25 Decibels ?? DD.dd

define('TTN_rf_chain', 'rf_chain');
define('ITPINGS_RFCHAIN', 'rfchain');
$_DBFIELD_RFCHAIN = [ITPINGS_RFCHAIN, TYPE_FOREIGNKEY_LOOKUPTABLE, '"TTN GatewayPing RFChain"'];     // ?? 0 or 1

define('TTN_port', 'port');
define('ITPINGS_PORT', TTN_port);
$_DBFIELD_PORT = [ITPINGS_PORT, TYPE_FOREIGNKEY_LOOKUPTABLE, ''];        // ?? always 1 ??

define('TTN_downlink_url', 'downlink_url');
define('ITPINGS_DOWNLINKURL', 'downurl');
$_DBFIELD_DOWNLINKURL = [ITPINGS_DOWNLINKURL, 'VARCHAR(1024)', 'TTN Downlink URI'];       // ?? save Web URL = 2000

define('TTN_counter', 'counter');
define('ITPINGS_FRAME_COUNTER', 'count');
$_DBFIELD_FRAME_COUNTER = [ITPINGS_FRAME_COUNTER, 'INT UNSIGNED', 'TTN Frame Counter'];

define('TTN_payload_raw', 'payload_raw');
define('ITPINGS_PAYLOAD_RAW', 'payload');
$_DBFIELD_PAYLOAD_RAW = [ITPINGS_PAYLOAD_RAW, 'VARCHAR(256)', 'Raw payload is purged'];    // ?? 256 enough?

define('TTN_frequency', 'frequency');
define('ITPINGS_FREQUENCY', TTN_frequency);
//https://www.thethingsnetwork.org/wiki/LoRaWAN/Frequencies/Frequency-Plans
define('TYPE_FREQUENCY', 'DECIMAL(4,1)');       // ?? 867.1  is DDD,d enough?

define('TTN_modulation', 'modulation');
define('ITPINGS_MODULATION', TTN_modulation);
define('TYPE_MODULATION', 'VARCHAR(16)');       // ?? "LORA" or anything else?

define('TTN_data_rate', 'data_rate');
define('ITPINGS_DATA_RATE', TTN_data_rate);
define('TYPE_DATA_RATE', 'VARCHAR(9)');         // ?? "SF7BW125" to "SF12BW500"

define('TTN_coding_rate', 'coding_rate');
define('ITPINGS_CODING_RATE', TTN_coding_rate);
define('TYPE_CODING_RATE', 'VARCHAR(16)');      // ?? "4/5"

define('ITPINGS_ORIGIN', 'origin');
define('TYPE_ITPINGS_ORIGIN', 'VARCHAR(16)');

// used to reference TTN (Cayenne?) JSON structure; !!! do not change !!!
define('TTN_metadata', 'metadata');
define('TTN_gateways', 'gateways');
define('TTN_payload_fields', 'payload_fields');

// Sensor names = key name in JSON payload
define('ITPINGS_SENSORNAME', 'sensorname');
$_DBFIELD_SENSORNAME = [ITPINGS_SENSORNAME, 'VARCHAR(256)', "TTN Payload key"];    // ?? 256 enough?

define('ITPINGS_SENSORVALUE', 'sensorvalue');
$_DBFIELD_SENSORVALUE = [ITPINGS_SENSORVALUE, 'VARCHAR(256)', 'TTN Payload value'];      // key value in JSON payload

define('ITPINGS_SENSOR_TEMPERATURE_VALUE', 'value');
$_DBFIELD_SENSOR_TEMPERATURE_VALUE = [ITPINGS_SENSOR_TEMPERATURE_VALUE, 'DECIMAL(3,1)', 'Temperature'];

/**
 * Foreign keys are not for performance,
 * they sure help with debugging,
 * but can also cause headaches when you try to delete data in the wrong order
 * @param $key
 * @param $table
 * @return array
 */
function define_ForeignKey($key, $table)
{
    return [$key, "REFERENCES $table  ( $key )"];
}

$_FOREIGNKEY_APPLICATIONS = define_ForeignKey(PRIMARYKEY_Application, TABLE_APPLICATIONS);
$_FOREIGNKEY_DEVICES = define_ForeignKey(PRIMARYKEY_Device, TABLE_DEVICES);
$_FOREIGNKEY_APPLICATIONDEVICES = define_ForeignKey(PRIMARYKEY_ApplicationDevice, TABLE_APPLICATIONDEVICES);
$_FOREIGNKEY_PINGS = define_ForeignKey(PRIMARYKEY_Ping, TABLE_PINGS);
$_FOREIGNKEY_GATEWAYS = define_ForeignKey(PRIMARYKEY_Gateway, TABLE_GATEWAYS);
$_FOREIGNKEY_SENSORS = define_ForeignKey(PRIMARYKEY_Sensor, TABLE_SENSORS);
$_FOREIGNKEY_LOCATIONS = define_ForeignKey(PRIMARYKEY_Location, TABLE_LOCATIONS);
//$_FOREIGNKEY_APPLICATIONS', FALSE);
//$_FOREIGNKEY_DEVICES', FALSE);
//$_FOREIGNKEY_APPLICATIONDEVICES', FALSE);
//$_FOREIGNKEY_PINGS', FALSE);
//$_FOREIGNKEY_GATEWAYS', FALSE);
//$_FOREIGNKEY_SENSORS', FALSE);
//$_FOREIGNKEY_LOCATIONS', FALSE);

// Table 'events'
define('ENUM_EVENTTYPE_New', 'New');
define('ENUM_EVENTTYPE_NewApp', 'NewApplication');
define('ENUM_EVENTTYPE_NewDevice', 'NewDevice');
define('ENUM_EVENTTYPE_NewGateway', 'NewGateway');
define('ENUM_EVENTTYPE_NewLocation', 'NewLocation');
define('ENUM_EVENTTYPE_NewSensor', 'NewSensor');
define('ENUM_EVENTTYPE_NewTable', 'NewTable');
define('ENUM_EVENTTYPE_NewView', 'NewView');
define('ENUM_EVENTTYPE_Log', 'Log');
define('ENUM_EVENTTYPE_Trigger', 'Trigger');
define('ENUM_EVENTTYPE_Error', 'Error');
//convert array to quoted string as SQL ENUM definition, to be used in Events Table definition below
$_TYPE_EVENTTYPE = sprintf("ENUM('%s')", implode("','", array(
    ENUM_EVENTTYPE_NewApp
, ENUM_EVENTTYPE_NewDevice
, ENUM_EVENTTYPE_NewGateway
, ENUM_EVENTTYPE_NewLocation
, ENUM_EVENTTYPE_NewSensor
, ENUM_EVENTTYPE_NewTable
, ENUM_EVENTTYPE_NewView
, ENUM_EVENTTYPE_Log
, ENUM_EVENTTYPE_Trigger
, ENUM_EVENTTYPE_Error)));

define('ITPINGS_EVENTTYPE', 'eventtype');
$_DBFIELD_EVENTTYPE = [ITPINGS_EVENTTYPE, $_TYPE_EVENTTYPE . " DEFAULT '" . ENUM_EVENTTYPE_Log . "'", "Event ENUM_EVENTTYPE values"];

define('ITPINGS_EVENTLABEL', 'eventlabel');
$_DBFIELD_EVENTLABEL = [ITPINGS_EVENTLABEL, 'VARCHAR(256)', "Event label"];

define('ITPINGS_EVENTVALUE', 'eventvalue');
$_DBFIELD_EVENTVALUE = [ITPINGS_EVENTVALUE, 'VARCHAR(4096)', "Event text, can include POST BODY"];


define('ITPINGS_CREATED_TIMESTAMP', 'created');
$_DBFIELD_CREATED_TIMESTAMP = [ITPINGS_CREATED_TIMESTAMP, ' TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ', 'Time Ping was Created in ITpings database'];


define('ITPINGS_DESCRIPTION', 'description');

//endregion == ITPINGS AND (TTN) THE THINGS NETWORK JSON FIELDNAMES ===============================


/** CONSTANTS, no need to change, results in better readable PHP $sql building code **/

define('AUTOINCREMENT_TABLE_PRIMARYKEY', 'NULL');   // default null, database autoincrements key id
define('NO_FOREIGNKEYS', FALSE);                    // always False to incicate  Table does not have Foreign Keys
define('NO_PRIMARYKEY', FALSE);                     // always False to indicate a Table does not have a primary key
define('COMMA', ',');

//region ===== VIEW AND QUERY CONFIGURATION =======================================================

// Only views referenced in process_Query_with_QueryString_Parameters() are accessible for the Front-end!!
define('VIEWNAME_EVENTS', TABLE_PREFIX . 'Events');
define('VIEWNAME_APPLICATIONDEVICES', TABLE_PREFIX . 'ApplicationDevices');
define('VIEWNAME_SENSORVALUES', TABLE_PREFIX . 'SensorValues');
define('VIEWNAME_SENSORVALUES_UPDATE', TABLE_PREFIX . 'SensorValuesUpdate'); // less JOINs, thus faster response
define('VIEWNAME_GATEWAYS', TABLE_PREFIX . 'Gateways');
define('VIEWNAME_PINGEDDEVICES', TABLE_PREFIX . 'PingedDevices');
define('VIEWNAME_PINGEDGATEWAYS', TABLE_PREFIX . 'PingedGateways');
define('VIEWNAME_TEMPERATURE', TABLE_PREFIX . 'Temperature');
define('VIEWNAME_LUMINOSITY', TABLE_PREFIX . 'Luminosity');

// Loop all names in front-end query check
// and Loop all names in DROP VIEW action
$_ITPINGS_VIEWNAMES = [
    VIEWNAME_EVENTS
    , VIEWNAME_APPLICATIONDEVICES
    , VIEWNAME_SENSORVALUES
    , VIEWNAME_SENSORVALUES_UPDATE
    , VIEWNAME_GATEWAYS
    , VIEWNAME_PINGEDDEVICES
    , VIEWNAME_PINGEDGATEWAYS];

//endregion == VIEW AND QUERY CONFIGURATION =======================================================


//QUERY_DEFINITIONS
// Parameters that can be used in GET/URL queries
define('QUERY_PARAMETER_SEPARATOR', ','); // for making IN (a,b,c) queries
define('QUERY_PARAMETER_FILTER', 'filter');
define('QUERY_PARAMETER_ORDERBY', 'orderby');
define('QUERY_PARAMETER_LIMIT', 'limit');
define('QUERY_PARAMETER_INTERVAL', 'interval');
define('QUERY_PARAMETER_INTERVALUNIT', 'intervalunit');
define('QUERY_PARAMETER_MAXROWS', 'maxrows');

define('INTERVALUNIT_SECOND', 'SECOND');
define('INTERVALUNIT_MINUTE', 'MINUTE');
define('INTERVALUNIT_HOUR', 'HOUR');
define('INTERVALUNIT_DAY', 'DAY');
define('INTERVALUNIT_WEEK', 'WEEK');
define('INTERVALUNIT_MONTH', 'MONTH');
define('INTERVALUNIT_QUARTER', 'QUARTER');
define('INTERVALUNIT_YEAR', 'YEAR');
define('INTERVALUNIT_DEFAULT', INTERVALUNIT_DAY);
$_QUERY_ALLOWED_INTERVALUNITS = [INTERVALUNIT_SECOND
    , INTERVALUNIT_MINUTE
    , INTERVALUNIT_HOUR
    , INTERVALUNIT_DAY
    , INTERVALUNIT_WEEK
    , INTERVALUNIT_MONTH
    , INTERVALUNIT_QUARTER
    , INTERVALUNIT_YEAR];

/**
 * Only these fieldnames can be used as WebService Query URI parameters
 * **/
$_VALID_QUERY_PARAMETERS = [
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
    QUERY_PARAMETER_FILTER,
    QUERY_PARAMETER_ORDERBY,
    QUERY_PARAMETER_INTERVAL,
    QUERY_PARAMETER_INTERVALUNIT,
    QUERY_PARAMETER_LIMIT
];


//PREDEFINED QUERIES
define('NO_SQL_QUERY', 'none');
define('SQL_QUERY_ApplicationDevices', 'Devices');
define('SQL_QUERY_DatabaseInfo_ITpings_Tables', 'DBInfo');
define('SQL_QUERY_RecentIDs', 'IDs'); // smallest JSON payload as possible
define('SQL_QUERY_RecentPingID', 'PingID'); // smallest JSON payload as possible
define('SQL_QUERY_Ping', 'ping'); // display single POST ping

//endregion == DATABASE SCHEMA AND CONFIGURATION ==================================================

define('EMPTY_STRING', '');