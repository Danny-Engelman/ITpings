<?php
include('ITpings_access_database.php');

//region ===== APPLICATION CONFIGURATION ==========================================================

// set to FALSE in Production to save lots of database resources, can be used for debugging purposes
define('SAVE_POST_AS_STRING_TO_A_SINGLE_POSTrequests_TABLE', TRUE);

// shorten the downlink URI going into the database, strip this first string from entry
define('TTN_DOWNLINKROOT', 'https://integrations.thethingsnetwork.org/ttn-eu/api/v2/down/');

// GPS has inaccurate fixes, to prevent 'moving' Gateway recordings a tolerance for lat/lon is calculated
// Gateways outside this tolerance will be recorded as new (moved) Gateway
define('GATEWAY_POSITION_TOLERANCE', '0.02');      // 20 Meter

//default LIMIT when none is specified
define('SQL_LIMIT_DEFAULT', 10);
//endregion == APPLICATION CONFIGURATION ==========================================================

//region ===== DATABASE SCHEMA AND CONFIGURATION ==================================================

/** ==> Creation of Tables and Views is in the ITpings_connector.php file **/

define('USE_REFERENTIAL_INTEGRITY', TRUE);  // FALSE will NOT create Indexes and Foreign Keys

define('TABLE_PREFIX', 'ITpings__');    // optional double underscore groups table list in PHPMyAdmin

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

// ITpings Table field keys, beware they don't collide with the TTN JSON fieldname definitions below
define('PRIMARYKEY_PREFIX', '_');
define('PRIMARYKEY_POSTrequests', PRIMARYKEY_PREFIX . 'postid');
define('PRIMARYKEY_Application', PRIMARYKEY_PREFIX . 'appid');
define('PRIMARYKEY_Device', PRIMARYKEY_PREFIX . 'devid');
define('PRIMARYKEY_ApplicationDevice', PRIMARYKEY_PREFIX . 'appdevid');
define('PRIMARYKEY_Gateway', PRIMARYKEY_PREFIX . 'gtwid');
define('PRIMARYKEY_Ping', PRIMARYKEY_PREFIX . 'pingid');
define('PRIMARYKEY_Sensor', PRIMARYKEY_PREFIX . 'sensorid');

//region ===== ITPINGS AND (TTN) THE THINGS NETWORK JSON FIELDNAMES ===============================

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

// Table 'events'
define('ENUM_EVENTTYPE_NewApp', 'NewApp');
define('ENUM_EVENTTYPE_NewDevice', 'NewDevice');
define('ENUM_EVENTTYPE_NewGateway', 'NewGateway');
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

//ITpings fieldnames referencing TTN fields
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

//endregion == ITPINGS AND (TTN) THE THINGS NETWORK JSON FIELDNAMES ===============================

//CONSTANTS, no need to change, results in better readable PHP $sql building code
define('TYPE_FOREIGNKEY', 'INT UNSIGNED');
define('AUTOINCREMENT_TABLE_PRIMARYKEY', 'NULL');   // default null, database autoincrements key id
define('NO_FOREIGNKEYS', FALSE);                    // always False to incicate  Table does not have Foreign Keys
define('NO_PRIMARYKEY', FALSE);                     // always False to indicate a Table does not have a primary key
define('IS_A_FOREIGNKEY_IN', "REFERENCES ");
define('COMMA', ',');
define('ITpings_PrimaryKey_In_Table', 'ITpings PrimaryKey in ');

//region ===== VIEW AND QUERY CONFIGURATION =======================================================

//names match with create_VIEW_[name] functiondefinitions
define('VIEWPREFIX', TABLE_PREFIX);
define('VIEWNAME_EVENTS', VIEWPREFIX . 'Events');
define('VIEWNAME_APPLICATIONDEVICES', VIEWPREFIX . 'ApplicationDevices');
define('VIEWNAME_SENSORVALUES', VIEWPREFIX . 'SensorValues');
define('VIEWNAME_PINGEDGATEWAYS', VIEWPREFIX . 'PingedGateways');
define('ITPINGS_VIEWNAMES', [
    VIEWNAME_EVENTS
    , VIEWNAME_APPLICATIONDEVICES
    , VIEWNAME_SENSORVALUES
    , VIEWNAME_PINGEDGATEWAYS]);

define('VIEWS_WITH_EXPANDED_KEYS', TRUE); // expand Foreign Keys, JSON will include more information

define('ITPINGS_TABLES_VIEWS', array(ITPINGS_TABLES, ITPINGS_VIEWNAMES));

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

//endregion == VIEW AND QUERY CONFIGURATION =======================================================


//endregion == DATABASE SCHEMA AND CONFIGURATION ==================================================

define('EMPTY_STRING', '');