<?php
include('ITpings_access_database.php');

//region ===== APPLICATION CONFIGURATION ==========================================================

// IP address:port where Ping came from
define('PING_ORIGIN', ($_SERVER['HTTP_CLIENT_IP'] ?: ($_SERVER['HTTP_X_FORWARDE‌​D_FOR'] ?: $_SERVER['REMOTE_ADDR'])) . ':' . $_SERVER['REMOTE_PORT']);

// CREATE Complete ITpings Database Schema IF it does not exist, set to FALSE to disable the check
define('CREATE_DATABASE_ON_FIRST_PING', TRUE);

// set to FALSE in Production to save lots of database resources, can be used for debugging purposes
// after first run, in an existing database you have to add/delete the POSTrequest Table by hand
define('SAVE_POST_AS_ONE_STRING', TRUE);

// GPS has inaccurate fixes, to prevent 'moving' Gateway recordings a tolerance for lat/lon is calculated
// Gateways outside this tolerance will be recorded as new (moved) Gateway
define('GATEWAY_POSITION_TOLERANCE', '0.02');      // 20 Meter

//default LIMIT when none is specified
define('SQL_LIMIT_DEFAULT', 10);

// field `downlink_url` is of no use once it can't be used anymore to downlink data to a Node
// To save database space these fields can be reset to empty values
// eg. 60 means 60th Ping in the past will be reset to empty values
define('PURGE_PINGCOUNT', 60);

// MySQL version 5.6.4 with DATETIME(6) is required to save ping.meta_time in Time with milliseconds
// https://dev.mysql.com/doc/refman/5.6/en/fractional-seconds.html
// See TYPE_TTN_TIME_DATETIME below


// Not quite sure yet how to deal with Altitude
// ping metadata location info is stored in a separate Table __locations
// to supress reports for different Altitude for a given lat/lon location set to FALSE
define('CHECK_HEIGHT_FOR_PING', TRUE);

//endregion == APPLICATION CONFIGURATION ==========================================================

//region ===== DATABASE SCHEMA AND CONFIGURATION ==================================================

/** ==> Creation of Tables and Views is in the ITpings_connector.php file **/

define('USE_REFERENTIAL_INTEGRITY', TRUE);  // FALSE will NOT create Indexes and Foreign Keys

// Prefix for Tables and Views
// optional double underscore groups table list in PHPMyAdmin
define('TABLE_PREFIX', 'ITpings__');

// Lookup Tables
define('TABLE_FREQUENCIES', TABLE_PREFIX . 'frequencies');
define('TABLE_MODULATIONS', TABLE_PREFIX . 'modulations');
define('TABLE_DATARATES', TABLE_PREFIX . 'datarates');
define('TABLE_CODINGRATES', TABLE_PREFIX . 'codingrates');

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

// All Tables, order complies with referential integrity, so DROP TABLE is executed in correct order
define('ITPINGS_TABLES', array(
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
));

// ITpings Table field keys, beware they don't collide with the TTN JSON fieldname definitions below
define('PRIMARYKEY_PREFIX', '_');
define('PRIMARYKEY_POSTrequests', PRIMARYKEY_PREFIX . 'postid');
define('PRIMARYKEY_Origin', PRIMARYKEY_PREFIX . 'originid');
define('PRIMARYKEY_Application', PRIMARYKEY_PREFIX . 'appid');
define('PRIMARYKEY_Device', PRIMARYKEY_PREFIX . 'devid');
define('PRIMARYKEY_ApplicationDevice', PRIMARYKEY_PREFIX . 'appdevid');
define('PRIMARYKEY_Gateway', PRIMARYKEY_PREFIX . 'gtwid');
define('PRIMARYKEY_Frequency', PRIMARYKEY_PREFIX . 'frqid');
define('PRIMARYKEY_Modulation', PRIMARYKEY_PREFIX . 'modid');
define('PRIMARYKEY_Datarate', PRIMARYKEY_PREFIX . 'drid');
define('PRIMARYKEY_Codingrate', PRIMARYKEY_PREFIX . 'crid');
define('PRIMARYKEY_Location', PRIMARYKEY_PREFIX . 'locid');
define('PRIMARYKEY_Ping', PRIMARYKEY_PREFIX . 'pingid');
define('PRIMARYKEY_Sensor', PRIMARYKEY_PREFIX . 'sensorid');

//region ===== ITPINGS AND (TTN) THE THINGS NETWORK JSON FIELDNAMES ===============================

//region ===== DATE TIME CONVERSIONS ==============================================================

// under development
define('CONVERT_DATESTRINGS_TO_DATETIME', TRUE);

define('TYPE_TTN_TIME_STRING', 'VARCHAR(30)');       // "2018-01-25T11:40:43.427237826Z" = 30 characters

// standard DATETIME does not store microseconds, DATETIME(6) does, but is not supported in older MySQL versions
define('TYPE_TTN_TIME_DATETIME', 'DATETIME');
define('TYPE_TTN_TIME_COMMENT', 'converted TTN time WITHOUT FRACTION IN OLDER MySQL server!');

define('TYPE_PINGED_GATEWAY_TIMESTAMP', 'INT UNSIGNED');      // ?? Timestamp when the gateway received the message

//endregion == DATE TIME CONVERSIONS ==============================================================

/**
 * Database Schema TYPE standards MATCHING the TTN JSON fieldnames, CHANGE with CARE!
 **/

define('TYPE_TTN_ID_FIELD', 'VARCHAR(512)');       // ?? what are the TTN maximums?

define('TTN_app_id', 'app_id');
define('TYPE_TTN_APP_ID', TYPE_TTN_ID_FIELD);
define('TYPE_TTN_APP_DESCRIPTION', TYPE_TTN_ID_FIELD);

define('TTN_dev_id', 'dev_id');
define('TYPE_TTN_DEVICE_ID', TYPE_TTN_ID_FIELD);

define('TTN_gtw_id', 'gtw_id');
define('TYPE_TTN_GTW_ID', TYPE_TTN_ID_FIELD);

define('TTN_hardware_serial', 'hardware_serial');
define('TYPE_TTN_HARDWARE_SERIAL', 'VARCHAR(16)');  // LoraWan: Device EUI

define('TTN_latitude', 'latitude');
define('ITPINGS_LATITUDE', 'lat');      // used in ITpings Tables and JSON output
define('LATITUDE_ACCURACY', 'DECIMAL(10,8)');       // -90 to 90 with 8 decimals (TTN does 7 decimals)

define('TTN_longitude', 'longitude');
define('ITPINGS_LONGITUDE', 'lon');
define('LONGITUDE_ACCURACY', 'DECIMAL(11,8)');      // -180 to 180 with 8 decimals (TTN does 7 decimals)

define('TTN_altitude', 'altitude');
define('ITPINGS_ALTITUDE', 'alt');
define('ALTITUDE_ACCURACY', 'DECIMAL(5,2)');        // centimeter accuracy up to 999,99

define('TTN_gtw_trusted', 'gtw_trusted');
define('TYPE_TTN_TRUSTED_GTW', 'TINYINT UNSIGNED NOT NULL DEFAULT 0'); // boolean

define('TTN_location_source', 'location_source');
// Requires ITPINGS declaration for TTN references
// check where TTN references are used!
// define('TTN_location_source', 'location_source');
// define('TTN_location_source', 'src'); // BREAKS!
define('TYPE_LOCATION_SOURCE', 'TINYINT UNSIGNED'); // ?? "registry" what else? // hardcoded as 1 in SQL code !!

define('TTN_timestamp', 'timestamp');

define('TTN_time', 'time');
define('ITPINGS_TIME', TTN_time);

define('TTN_channel', 'channel');
define('TYPE_TTN_CHANNEL', 'TINYINT UNSIGNED');     // ?? 0 - 7

define('TTN_rssi', 'rssi');
define('TYPE_TTN_RSSI', 'TINYINT SIGNED');          // ?? -85 dBm to -45dBm

define('TTN_snr', 'snr');
define('TYPE_TTN_SNR', 'DECIMAL(4,2)');             // ?? 8.25 Decibels ?? DD.dd

define('TTN_rf_chain', 'rf_chain');
define('TYPE_TTN_RFCHAIN', 'TINYINT UNSIGNED');     // ?? 0 or 1

define('TTN_port', 'port');
define('TYPE_TTN_PORT', 'TINYINT UNSIGNED');        // ?? always 1 ??

define('TTN_downlink_url', 'downlink_url');
define('TYPE_TTN_DOWNLINK', 'VARCHAR(1024)');       // ?? save Web URL = 2000

define('TTN_counter', 'counter');
define('TYPE_TTN_FRAME_COUNTER', 'INT UNSIGNED');   // Frame Counter

define('TTN_payload_raw', 'payload_raw');
define('TYPE_TTN_PAYLOAD_RAW', 'VARCHAR(256)');    // ?? 256 enough?

define('TTN_frequency', 'frequency');
define('ITPINGS_FREQUENCY', TTN_frequency);
//https://www.thethingsnetwork.org/wiki/LoRaWAN/Frequencies/Frequency-Plans
define('TYPE_TTN_FREQUENCY', 'DECIMAL(4,1)');       // ?? 867.1  is DDD,d enough?

define('TTN_modulation', 'modulation');
define('ITPINGS_MODULATION', TTN_modulation);
define('TYPE_TTN_MODULATION', 'VARCHAR(16)');       // ?? "LORA" or anything else?

define('TTN_data_rate', 'data_rate');
define('ITPINGS_DATA_RATE', TTN_data_rate);
define('TYPE_TTN_DATA_RATE', 'VARCHAR(9)');         // ?? "SF7BW125" to "SF12BW500"

define('TTN_coding_rate', 'coding_rate');
define('ITPINGS_CODING_RATE', TTN_coding_rate);
define('TYPE_TTN_CODING_RATE', 'VARCHAR(16)');      // ?? "4/5"

define('ITPINGS_ORIGIN', 'origin');
define('TYPE_ITPINGS_ORIGIN', 'VARCHAR(16)');

//used to reference TTN (Cayenne?) JSON structure; better not change!
define('TTN_metadata', 'metadata');
define('TTN_gateways', 'gateways');
define('TTN_payload_fields', 'payload_fields');

//Sensor names and values
define('TYPE_PAYLOAD_KEY', 'VARCHAR(256)');         // key name in JSON payload
define('TYPE_PAYLOAD_VALUE', 'VARCHAR(1024)');      // key value in JSON payload


//Cayenne LPP Sensor names
define('TTN_Cayenne_accelerometer', 'accelerometer_7');
define('TTN_Cayenne_analog_in', 'analog_in_4');
define('TTN_Cayenne_digital_in_1', 'digital_in_1');
define('TTN_Cayenne_digital_in_2', 'digital_in_2');
define('TTN_Cayenne_digital_in_3', 'digital_in_3');
define('TTN_Cayenne_luminosity', 'luminosity_6');
define('TTN_Cayenne_temperature', 'temperature_5');

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
//convert array to quoted string as SQL ENUM definition
define('TYPE_EVENTTYPE', sprintf("ENUM('%s')", implode("','", array(
    ENUM_EVENTTYPE_NewApp
, ENUM_EVENTTYPE_NewDevice
, ENUM_EVENTTYPE_NewGateway
, ENUM_EVENTTYPE_NewLocation
, ENUM_EVENTTYPE_NewSensor
, ENUM_EVENTTYPE_NewTable
, ENUM_EVENTTYPE_NewView
, ENUM_EVENTTYPE_Log
, ENUM_EVENTTYPE_Trigger
, ENUM_EVENTTYPE_Error))));

define('ITPINGS_EVENTTYPE', 'eventtype');
define('ITPINGS_EVENTLABEL', 'eventlabel');
define('ITPINGS_EVENTVALUE', 'eventvalue');
define('TYPE_EVENTLABEL', 'VARCHAR(256)');
define('TYPE_EVENTVALUE', 'VARCHAR(4096)');

//Table 'sensorvalues'
define('ITPINGS_SENSORNAME', 'sensorname');
define('ITPINGS_SENSORVALUE', 'sensorvalue');

define('ITPINGS_CREATED_TIMESTAMP', 'created');
define('ITPINGS_DESCRIPTION', 'description');

//for debugging; single table to record whole POST as TEXT blob
define('ITPINGS_POST_body', 'body');
define('TYPE_POST_BODY', 'VARCHAR(4048)');

//endregion == ITPINGS AND (TTN) THE THINGS NETWORK JSON FIELDNAMES ===============================


/** CONSTANTS, no need to change, results in better readable PHP $sql building code **/

define('TYPE_FOREIGNKEY', 'INT UNSIGNED');
define('TYPE_FOREIGNKEY_LOOKUPTABLE', 'TINYINT UNSIGNED');

define('AUTOINCREMENT_TABLE_PRIMARYKEY', 'NULL');   // default null, database autoincrements key id
define('NO_FOREIGNKEYS', FALSE);                    // always False to incicate  Table does not have Foreign Keys
define('NO_PRIMARYKEY', FALSE);                     // always False to indicate a Table does not have a primary key
define('IS_A_FOREIGNKEY_IN', "REFERENCES ");
define('COMMA', ',');
define('ITpings_PrimaryKey_In_Table', 'ITpings PrimaryKey in ');

//region ===== VIEW AND QUERY CONFIGURATION =======================================================

//VIEWS
//names match with create_VIEW_[name] functiondefinitions
define('VIEWNAME_EVENTS', TABLE_PREFIX . 'Events');
define('VIEWNAME_APPLICATIONDEVICES', TABLE_PREFIX . 'ApplicationDevices');
define('VIEWNAME_SENSORVALUES', TABLE_PREFIX . 'SensorValues');
define('VIEWNAME_GATEWAYS', TABLE_PREFIX . 'Gateways');
define('VIEWNAME_PINGEDGATEWAYS', TABLE_PREFIX . 'PingedGateways');

define('ITPINGS_VIEWNAMES', [
    VIEWNAME_EVENTS
    , VIEWNAME_APPLICATIONDEVICES
    , VIEWNAME_SENSORVALUES
    , VIEWNAME_PINGEDGATEWAYS]);

define('EXPAND_FOREIGN_KEYS', TRUE); // expand Foreign Keys, JSON will include more information

//QUERY_DEFINITIONS
/**
 * Parameters that can be used in GET/URL queries
 **/
define('QUERY_PARAMETER_SEPARATOR', ','); // for making IN (a,b,c) queries
define('QUERY_PARAMETER_FILTER', 'filter');
define('QUERY_PARAMETER_ORDERBY', 'orderby');
define('QUERY_PARAMETER_ORDERSORT', 'ordersort');
define('QUERY_PARAMETER_LIMIT', 'limit');
define('QUERY_PARAMETER_INTERVAL', 'interval');
define('QUERY_PARAMETER_INTERVALUNIT', 'intervalunit');

define('INTERVALUNIT_SECOND', 'SECOND');
define('INTERVALUNIT_MINUTE', 'MINUTE');
define('INTERVALUNIT_HOUR', 'HOUR');
define('INTERVALUNIT_DAY', 'DAY');
define('INTERVALUNIT_WEEK', 'WEEK');
define('INTERVALUNIT_MONTH', 'MONTH');
define('INTERVALUNIT_QUARTER', 'QUARTER');
define('INTERVALUNIT_YEAR', 'YEAR');
define('INTERVALUNIT_DEFAULT', INTERVALUNIT_DAY);
define('QUERY_ALLOWED_INTERVALUNITS', [INTERVALUNIT_SECOND
    , INTERVALUNIT_MINUTE
    , INTERVALUNIT_HOUR
    , INTERVALUNIT_DAY
    , INTERVALUNIT_WEEK
    , INTERVALUNIT_MONTH
    , INTERVALUNIT_QUARTER
    , INTERVALUNIT_YEAR]);

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
    QUERY_PARAMETER_FILTER,
    QUERY_PARAMETER_ORDERBY,
    QUERY_PARAMETER_ORDERSORT,
    QUERY_PARAMETER_INTERVAL,
    QUERY_PARAMETER_INTERVALUNIT,
    QUERY_PARAMETER_LIMIT
]);


//PREDEFINED QUERIES
define('SQL_QUERY_ApplicationDevices', 'Devices');


//endregion == VIEW AND QUERY CONFIGURATION =======================================================


//endregion == DATABASE SCHEMA AND CONFIGURATION ==================================================

define('EMPTY_STRING', '');