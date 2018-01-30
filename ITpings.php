<?php
/*
    ITpings is a PHP script for the HTTP integration between your The Things Network application and your own MySQL Server

    Create a database and access account in your MYSQL/PHPMyAdmin console (or MySQLWorkbench if you have it installed)
    No need to create Tables by hand

    See www.ITpings.nl for detail configuration instructions

*/
/* WARNING! Change to a value of your own choice! secret key gives admin access */
/* CONFIG --> */ define('SECRETKEY'  , 'EDITME'     );

/* Database access settings, if you don't know what to use, contact your Webserver Administrator */
/* CONFIG --> */ define( 'DBHOST'    , 'localhost'  );//Typically the Web and Database server run on the same server: localhost
/* CONFIG --> */ define( 'DBNAME'    , 'ITpings'    );

/* update with your own Database user account */
/* CONFIG --> */ define( 'DBUSER'    , 'engelmanDB' );
/* CONFIG --> */ define( 'DBPASSWORD', 'nDnSCrTjaFMewDst' );




/* ------------------------------------------------------------------------------------------------------- */
/* OPTIONAL CONFIG , Nothing required for standard usage , No support given when you change anything below */

//Production configuration
define( 'ALLOW_DROPTABLE'           , TRUE  );// WARNING! use TRUE value for development only! it will erase all your existing data in tables!
define( 'SINGLE_TABLE_REQUESTS'     , FALSE );// set to TRUE to work with one single Database Table "Requests"
define( 'USE_SENSOR_TABLES'         , TRUE  );// set to TRUE to work with 'sensor' and 'sensorvalues' tables

//Database Table names
define( 'TABLE_PREFIX' , 'ttn__' ); // optional double underscore groupes table list in PHPMyAdmin
define( 'TABLE_APPLICATIONS'        , TABLE_PREFIX.'applications'           );
define( 'TABLE_GATEWAYS'            , TABLE_PREFIX.'gateways'               );
define( 'TABLE_DEVICES'             , TABLE_PREFIX.'devices'                );
define( 'TABLE_PINGS'               , TABLE_PREFIX.'pings'                  );
define( 'TABLE_PINGEDGATEWAYS'      , TABLE_PREFIX.'pinged_gateways'        );

//tables where sensor values are stored
define( 'TABLE_SENSORS'             , TABLE_PREFIX.'sensors'                );
define( 'TABLE_SENSORVALUES'        , TABLE_PREFIX.'sensorvalues'           );
define( 'TABLE_PINGPAYLOADFIELDS'   , TABLE_PREFIX.'ping_payload_fields'    );


// when SINGLE_TABLE_REQUESTS=true , store whole request in a single table
define( 'TABLE_REQUEST'             , TABLE_PREFIX.'requests'               );

define( 'ITPINGS_TABLES'            , array(
                                             TABLE_PINGS
                                            ,TABLE_PINGPAYLOADFIELDS
                                            ,TABLE_PINGEDGATEWAYS

                                            ,TABLE_GATEWAYS
                                            ,TABLE_DEVICES
                                            ,TABLE_APPLICATIONS
                                            ,TABLE_SENSORS
                                            ,TABLE_SENSORVALUES

                                            ) );



//ITpings Table field keys, beware they don't collide with the TTN JSON fieldname definitions below
define( 'PRIMARYKEY_PREFIX'         , '_'                                   );
define( 'PRIMARYKEY_Application'    , PRIMARYKEY_PREFIX . 'appid'           );
define( 'PRIMARYKEY_Device'         , PRIMARYKEY_PREFIX . 'devid'           );
define( 'PRIMARYKEY_Gateway'        , PRIMARYKEY_PREFIX . 'gtwid'           );
define( 'PRIMARYKEY_Ping'           , PRIMARYKEY_PREFIX . 'pingid'          );
define( 'PRIMARYKEY_PingedGateway'  , PRIMARYKEY_PREFIX . 'pingedgtwid'     );
define( 'PRIMARYKEY_PingPayload'    , PRIMARYKEY_PREFIX . 'pingpayloadid'   );
define( 'PRIMARYKEY_Sensor'         , PRIMARYKEY_PREFIX . 'sensorid'        );
define( 'PRIMARYKEY_SensorValue'    , PRIMARYKEY_PREFIX . 'sensorvalueid'   );

//second column in every ITpings table is a current timestamp
define( 'FIELD_CREATED_TIMESTAMP'   , 'created'                             );
define( 'FIELD_DESCRIPTION'         , 'description'                         );

//TTN JSON fieldname definitions, defined in the TTN HTTP integration (Cayenne style)
define( 'TTN_app_id'                , 'app_id'          );
define( 'TTN_dev_id'                , 'dev_id'          );
define( 'TTN_gtw_id'                , 'gtw_id'          );

define( 'TTN_hardware_serial'       , 'hardware_serial' );
define( 'TTN_latitude'              , 'latitude'        );
define( 'TTN_longitude'             , 'longitude'       );
define( 'TTN_altitude'              , 'altitude'        );
define( 'TTN_gtw_trusted'           , 'gtw_trusted'     );
define( 'TTN_location_source'       , 'location_source' );
define( 'TTN_timestamp'             , 'timestamp'       );
define( 'TTN_time'                  , 'time'            );
define( 'TTN_channel'               , 'channel'         );
define( 'TTN_rssi'                  , 'rssi'            );
define( 'TTN_snr'                   , 'snr'             );
define( 'TTN_rf_chain'              , 'rf_chain'        );
define( 'TTN_port'                  , 'port'            );
define( 'TTN_downlink_url'          , 'downlink_url'    );
define( 'TTN_counter'               , 'counter'         );
define( 'TTN_payload_raw'           , 'payload_raw'     );
define( 'TTN_frequency'             , 'frequency'       );
define( 'TTN_modulation'            , 'modulation'      );
define( 'TTN_data_rate'             , 'data_rate'       );
define( 'TTN_coding_rate'           , 'coding_rate'     );

define( 'TTN_metadata'              , 'metadata'        );
define( 'TTN_gateways'              , 'gateways'        );
define( 'TTN_payload_fields'        , 'payload_fields'  );



// the single Request table uses these; ITping reads the keys(=fieldnames) from the Payload and stores them in TABLE_PINGPAYLOADFIELDS or TABLE_SENSORS
define( 'TTN_Cayenne_accelerometer' , 'accelerometer_7' );
define( 'TTN_Cayenne_analog_in'     , 'analog_in_4'     );
define( 'TTN_Cayenne_digital_in_1'  , 'digital_in_1'    );
define( 'TTN_Cayenne_digital_in_2'  , 'digital_in_2'    );
define( 'TTN_Cayenne_digital_in_3'  , 'digital_in_3'    );
define( 'TTN_Cayenne_luminosity'    , 'luminosity_6'    );
define( 'TTN_Cayenne_temperature'   , 'temperature_5'   );

// ITpings SQL schema
define( 'ITPINGS_SENSORNAME'        , 'name'                                );
define( 'ITPINGS_SENSORVALUE'       , 'value'                               );

define( 'ITPINGS_PREFIX'            , 'metadata_'                           ); // TODO no prefix in production
define( 'ITPINGS_TIME'              , ITPINGS_PREFIX . TTN_time             );
define( 'ITPINGS_FREQUENCY'         , ITPINGS_PREFIX . TTN_frequency        );
define( 'ITPINGS_MODULATION'        , ITPINGS_PREFIX . TTN_modulation       );
define( 'ITPINGS_DATA_RATE'         , ITPINGS_PREFIX . TTN_data_rate        );
define( 'ITPINGS_CODING_RATE'       , ITPINGS_PREFIX . TTN_coding_rate      );
define( 'ITPINGS_LATITUDE'          , ITPINGS_PREFIX . TTN_latitude         );
define( 'ITPINGS_LONGITUDE'         , ITPINGS_PREFIX . TTN_longitude        );
define( 'ITPINGS_ALTITUDE'          , ITPINGS_PREFIX . TTN_altitude         );
define( 'ITPINGS_LOCATIONSOURCE'    , ITPINGS_PREFIX . TTN_location_source  );

//Database Schema standards, change with care
define( 'TYPE_TTN_TIMESTRING'       , 'CHAR(30)'        ); // "2018-01-25T11:40:43.427237826Z" = 30 characters
define( 'TYPE_TYPE_TIMESTAMP'       , 'INT UNSIGNED'    ); // ?? Timestamp when the gateway received the message

define( 'TYPE_TTN_APP_ID'           , 'VARCHAR(1024)'   ); // ?? what are the TTN maximums?
define( 'TYPE_TTN_APP_DESCRIPTION'  , 'VARCHAR(1024)'   ); // ??
define( 'TYPE_TTN_GTW_ID'           , 'VARCHAR(1024)'   ); // ??
define( 'TYPE_TTN_DEVICE_ID'        , 'VARCHAR(1024)'   ); // ??

define( 'TYPE_TTN_TRUSTED_GTW'      , 'TINYINT UNSIGNED NOT NULL DEFAULT 0' ); // boolean

define( 'TYPE_TTN_FRAME_COUNTER'    , 'INT UNSIGNED'    ); // Frame Counter

define( 'TYPE_TTN_DOWNLINK'         , 'VARCHAR(1024)'   ); // ?? save Web URL = 2000
define( 'TYPE_TTN_PAYLOAD_RAW'      , 'VARCHAR(1024)'   ); // ?? 256 enough?

define( 'TYPE_TTN_CHANNEL'          , 'INT UNSIGNED'    ); // ?? 3
define( 'TYPE_TTN_RSSI'             , 'INT SIGNED'      ); // ?? -54 dBm
define( 'TYPE_TTN_SNR'              , 'DECIMAL'         ); // ?? 8.25 Decibels
define( 'TYPE_TTN_RFCHAIN'          , 'INT UNSIGNED'    ); // ?? 0

define( 'TYPE_TTN_HARDWARE_SERIAL'  , 'VARCHAR(16)'     ); // LoraWan: Device EUI

define( 'TYPE_TTN_PORT'             , 'INT'             ); // ?? 1

define( 'TYPE_TTN_FREQUENCY'        , 'DECIMAL(5,2)'    ); // ?? 867.1  is DDD,dd enough?
define( 'TYPE_TTN_MODULATION'       , 'VARCHAR(16)'     ); // ?? "LORA" or anything else?
define( 'TYPE_TTN_DATA_RATE'        , 'VARCHAR(16)'     ); // ?? "SF7BW125" or what else?
define( 'TYPE_TTN_CODING_RATE'      , 'VARCHAR(16)'     ); // ?? "4/5"

define( 'LATITUDE_ACCURACY'         , 'DECIMAL(10,8)'   ); // -90 to 90 with 8 decimals (TTN does 7 decimals)
define( 'LONGITUDE_ACCURACY'        , 'DECIMAL(11,8)'   ); // -180 to 180 with 8 decimals (TTN does 7 decimals)
define( 'ALTITUDE_ACCURACY'         , 'DECIMAL(5,2)'    ); // centimeter accuracy up to 999,99
define( 'TYPE_LOCATION_SOURCE'      , 'VARCHAR(16)'     ); // ?? "registry"

define( 'TYPE_PAYLOAD_KEY'          , 'VARCHAR(256)'    );
define( 'TYPE_PAYLOAD_VALUE'        , 'VARCHAR(1024)'   );


//CONSTANTS, no need to change
define( 'TYPE_FOREIGNKEY'    , 'INT UNSIGNED'       );
define( 'SQL_INSERT_INTO'    , "INSERT INTO "       );
define( 'SQL_VALUES_START'   , " VALUES (null,null" ); // 2x null are _id (autonumber) and created (Current timestamp)
define( 'SQL_VALUES_CLOSE'   , ");"                 );
define( 'NO_FOREIGNKEYS'     , FALSE                );
define( 'IS_A_FOREIGNKEY_IN' , "REFERENCES "        );
define( 'COMMA'              , ','                  );
define( 'EMPTYSTRING'        , ''                   );
define( 'NO_CONDITIONS'      , ''                   );
define( 'IS_POST'            , $_SERVER['REQUEST_METHOD'] === 'POST' );


//process QueryString variables
$urlVars = array();
parse_str($_SERVER['QUERY_STRING'], $urlVars);

define( 'IS_ADMIN_ACCESS'   , $urlVars['key'] === SECRETKEY );
define( 'ADMIN_ACTION'      , $urlVars['action'] );

define( 'API_QUERY'         , $urlVars['query'] );


//API queries
define( 'TABLE_DEFAULT_SORT' , ' ORDER BY '.FIELD_CREATED_TIMESTAMP.' DESC' );
$queries = array(
    "devices"           => "SELECT * FROM " . TABLE_DEVICES         . TABLE_DEFAULT_SORT,
    "applications"      => "SELECT * FROM " . TABLE_APPLICATIONS    . TABLE_DEFAULT_SORT,
    "gateways"          => "SELECT * FROM " . TABLE_GATEWAYS        . TABLE_DEFAULT_SORT,
    "pingedgateways"    => "SELECT * FROM " . TABLE_PINGEDGATEWAYS  . TABLE_DEFAULT_SORT,
    "sensors"           => "SELECT * FROM " . TABLE_SENSORS         . TABLE_DEFAULT_SORT,
    "sensorvalues"      => "SELECT * FROM " . TABLE_SENSORVALUES    . TABLE_DEFAULT_SORT,
    "pings"             => "SELECT * FROM " . TABLE_PINGS           . TABLE_DEFAULT_SORT,
);

//TODO
//$schemaDocumentation = array(
//    TABLE_SENSORS => "";
//);

//PHP error reporting
if ( !IS_POST ) {
    $ebits = ini_get( 'error_reporting' );
    error_reporting( $ebits ^ E_ALL );
}

$conn = mysqli_connect( DBHOST , DBUSER , DBPASSWORD , DBNAME ) or die("Could not connect database");


$sqlLog = array();              // log all SQL statements
function add_QueryLog( $str ){
    global $sqlLog;
    $sqlLog[] = $str;
}
function showSQL_QueryLog(){
    global $sqlLog;
    foreach( $sqlLog as $key => $value ){
        echo $value;
    }
}


function SQL_Query( $sql , $returnJSON = FALSE ){
    global $conn;
    global $sqlLog;

    add_QueryLog( $sql );

    $result = mysqli_query( $conn, $sql) or die("incorrect SQL: ".$sql);
    if ( $result ) {
        if( strpos( $sql , 'INSERT' ) !== false){
            return mysqli_insert_id( $conn);
        } else {
            if( $returnJSON ){
                $rows = array();
                while($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                }
                print json_encode($rows);
            } else {
                return mysqli_fetch_assoc( $result); // return first $row
            }
        }
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error( $conn);
    }
}

function Quoted( $val ){
    return (isset($val) OR $val==0 ) ? "'" . $val . "'" : "NULL";
}
function Valued( $val ){
    return (isset($val) OR $val==0 ) ? $val : "NULL";
}
function Boolean( $val ){
    return $val ? 1 : 0;
    return gettype( $val );
    return ($val='true' OR $val='TRUE' OR $val==1 ) ? 1 : 0;
}

//Dump every request in a single table, great for testing
function insertRequest( $request ){
        $sql = SQL_INSERT_INTO . TABLE_REQUESTS . SQL_VALUES_START;
        $sql .= COMMA . Quoted( $request[ TTN_app_id            ] );
        $sql .= COMMA . Quoted( $request[ TTN_dev_id            ] );
        $sql .= COMMA . Quoted( $request[ TTN_hardware_serial   ] );
        $sql .= COMMA . Valued( $request[ TTN_port              ] );
        $sql .= COMMA . Valued( $request[ TTN_counter           ] );
        $sql .= COMMA . Quoted( $request[ TTN_payload_raw       ] );

    //Cayenne style payload
    $payload = $request[ TTN_payload_fields ];
        $sql .= COMMA . Valued  ( $payload[ TTN_Cayenne_accelerometer    ]['x'] );
        $sql .= COMMA . Valued  ( $payload[ TTN_Cayenne_accelerometer    ]['y'] );
        $sql .= COMMA . Valued  ( $payload[ TTN_Cayenne_accelerometer    ]['z'] );
        $sql .= COMMA . Valued  ( $payload[ TTN_Cayenne_analog_in        ] );
        $sql .= COMMA . Valued  ( $payload[ TTN_Cayenne_digital_in_1     ] );
        $sql .= COMMA . Valued  ( $payload[ TTN_Cayenne_digital_in_2     ] );
        $sql .= COMMA . Valued  ( $payload[ TTN_Cayenne_digital_in_3     ] );
        $sql .= COMMA . Valued  ( $payload[ TTN_Cayenne_luminosity       ] );
        $sql .= COMMA . Valued  ( $payload[ TTN_Cayenne_temperature      ] );

    $metadata = $request[ TTN_metadata ];
        $sql .= COMMA . Quoted  ( $metadata[ TTN_time             ] );
        $sql .= COMMA . Valued  ( $metadata[ TTN_frequency        ] );
        $sql .= COMMA . Quoted  ( $metadata[ TTN_modulation       ] );
        $sql .= COMMA . Quoted  ( $metadata[ TTN_data_rate        ] );
        $sql .= COMMA . Quoted  ( $metadata[ TTN_coding_rate      ] );
    // a single request table can only save 1 gateway !!!!
    $gateway = $request[ TTN_metadata ][ TTN_gateways ][0];
        $sql .= COMMA . Quoted  ( $gateway[ TTN_gtw_id            ] );
        $sql .= COMMA . Boolean ( $gateway[ TTN_gtw_trusted       ] );
        $sql .= COMMA . Valued  ( $gateway[ TTN_timestamp         ] );
        $sql .= COMMA . Quoted  ( $gateway[ TTN_time              ] );
        $sql .= COMMA . Valued  ( $gateway[ TTN_channel           ] );
        $sql .= COMMA . Valued  ( $gateway[ TTN_rssi              ] );
        $sql .= COMMA . Valued  ( $gateway[ TTN_snr               ] );
        $sql .= COMMA . Valued  ( $gateway[ TTN_rf_chain          ] );
        $sql .= COMMA . Valued  ( $gateway[ TTN_latitude          ] );
        $sql .= COMMA . Valued  ( $gateway[ TTN_longitude         ] );
        $sql .= COMMA . Valued  ( $gateway[ TTN_altitude          ] );
        $sql .= COMMA . Quoted  ( $gateway[ TTN_location_source   ] );
    
        $sql .= COMMA . Valued  ( $metadata[ TTN_latitude         ] );
        $sql .= COMMA . Valued  ( $metadata[ TTN_longitude        ] );
        $sql .= COMMA . Valued  ( $metadata[ TTN_altitude         ] );
        $sql .= COMMA . Quoted  ( $metadata[ TTN_location_source  ] );
        $sql .= COMMA . Quoted  ( $request[ TTN_downlink_url      ] );
        $sql .= SQL_VALUES_CLOSE;

    SQL_Query( $sql );
}

//  ====================================================================================== Functions for Multiple tables

function lastTableEntry( $table , $field , $value , $conditions ){
    return SQL_Query( "SELECT * FROM $table WHERE $field='$value' $conditions ORDER BY ".FIELD_CREATED_TIMESTAMP." DESC LIMIT 1;" );
}

function processApp( $request ){
    $existing_row = lastTableEntry( TABLE_APPLICATIONS 
                                    , TTN_app_id 
                                    , $request[ TTN_app_id] 
                                    , NO_CONDITIONS );
    if( $existing_row ){
        return $existing_row[ PRIMARYKEY_Application ];
    } else {
        $sql  = SQL_INSERT_INTO . TABLE_APPLICATIONS;
        $sql .= SQL_VALUES_START;
        $sql .= COMMA . Quoted( $request[ TTN_app_id ]      );
        $sql .= COMMA . Quoted( 'Get description from TTN'  );
        $sql .= SQL_VALUES_CLOSE;
        return SQL_Query( $sql );
    }
}

function processDevice( $request ){
    $existing_row = lastTableEntry( TABLE_DEVICES 
                                    , TTN_dev_id 
                                    , $request[ TTN_dev_id] 
                                    , NO_CONDITIONS );
    if( $existing_row ){
        return $existing_row[ PRIMARYKEY_Device ];
    } else {
        $sql  = SQL_INSERT_INTO . TABLE_DEVICES;
        $sql .= SQL_VALUES_START;
        $sql .= COMMA . Quoted( $request[ TTN_dev_id            ] );
        $sql .= COMMA . Quoted( $request[ TTN_hardware_serial   ] );
        $sql .= SQL_VALUES_CLOSE;
        return SQL_Query( $sql );
    }
}

function processGateway( $gateway ){
    $request_gtw_id = $gateway[ TTN_gtw_id      ];
    $latitude       = $gateway[ TTN_latitude    ];
    $longitude      = $gateway[ TTN_longitude   ];
    
    $existing_row = lastTableEntry( TABLE_GATEWAYS 
                                    , TTN_gtw_id 
                                    , $request_gtw_id 
                                    , "AND latitude=$latitude AND longitude=$longitude" );
    if( $existing_row ){
        return $existing_row[ PRIMARYKEY_Gateway ];
    } else {
        $sql  = SQL_INSERT_INTO . TABLE_GATEWAYS;
        $sql .= SQL_VALUES_START;
        $sql .= COMMA . Quoted( $request_gtw_id );
        $sql .= COMMA . Quoted( $gateway[ TTN_gtw_trusted ] );
        $sql .= COMMA . Valued( $latitude );
        $sql .= COMMA . Valued( $longitude );
        $sql .= COMMA . Valued( $gateway[ TTN_altitude          ] );
        $sql .= COMMA . Quoted( $gateway[ TTN_location_source   ] );
        $sql .= SQL_VALUES_CLOSE;
        return SQL_Query( $sql );
    }
}

function processGateways( $request ){
    $gatewaysArray = $request[ TTN_metadata ][ TTN_gateways ];
    foreach ( $gatewaysArray as $gateway ){
        
        $gatewayID = processGateway( $gateway ); // Find known Gateway, else save new Gateway
    
        $sql = SQL_INSERT_INTO . TABLE_PINGEDGATEWAYS;
        $sql .= SQL_VALUES_START;
        $sql .= COMMA . Valued( $request[ PRIMARYKEY_Ping   ] );
        $sql .= COMMA . Valued( $gatewayID                    ); 
        $sql .= COMMA . Valued( $gateway[ TTN_timestamp     ] );
        $sql .= COMMA . Quoted( $gateway[ TTN_time          ] );
        $sql .= COMMA . Valued( $gateway[ TTN_channel       ] );
        $sql .= COMMA . Valued( $gateway[ TTN_rssi          ] );
        $sql .= COMMA . Valued( $gateway[ TTN_snr           ] );
        $sql .= COMMA . Valued( $gateway[ TTN_rf_chain      ] );
        $sql .= SQL_VALUES_CLOSE;
        SQL_Query( $sql );
    }
}

function insertPing( $request ){
    $request[ TTN_app_id ] = processApp( $request );    // Find known Application, else save new App
    $request[ TTN_dev_id ] = processDevice( $request ); // Find known Device, else save as new Device

    $sql = SQL_INSERT_INTO . TABLE_PINGS;
    $sql .= SQL_VALUES_START;
    $sql .= COMMA . Valued( $request[ TTN_app_id        ] );
    $sql .= COMMA . Valued( $request[ TTN_dev_id        ] );
    $sql .= COMMA . Valued( $request[ TTN_port          ] );
    $sql .= COMMA . Valued( $request[ TTN_counter       ] );
    $sql .= COMMA . Quoted( $request[ TTN_downlink_url  ] );
    $sql .= COMMA . Quoted( $request[ TTN_payload_raw   ] );

    $metadata = $request[ TTN_metadata ];
    $sql .= COMMA . Quoted( $metadata[ TTN_time             ] );
    $sql .= COMMA . Valued( $metadata[ TTN_frequency        ] );
    $sql .= COMMA . Quoted( $metadata[ TTN_modulation       ] );
    $sql .= COMMA . Quoted( $metadata[ TTN_data_rate        ] );
    $sql .= COMMA . Quoted( $metadata[ TTN_coding_rate      ] );
    $sql .= COMMA . Valued( $metadata[ TTN_latitude         ] );
    $sql .= COMMA . Valued( $metadata[ TTN_longitude        ] );
    $sql .= COMMA . Valued( $metadata[ TTN_altitude         ] );
    $sql .= COMMA . Quoted( $metadata[ TTN_location_source  ] );
    $sql .= SQL_VALUES_CLOSE;

    $request[ PRIMARYKEY_Ping ] = SQL_Query( $sql );

    return $request;
}

function processSensor( $app_id , $dev_id , $key ){
    $existing_row = lastTableEntry( TABLE_SENSORS
                                    , PRIMARYKEY_Application
                                    , $app_id
                                    ,    " AND ".PRIMARYKEY_Device."='".$dev_id."'"
                                       . " AND ".ITPINGS_SENSORNAME."='".$key."'"
                                    );
    if( $existing_row ){
        return $existing_row[ PRIMARYKEY_Sensor ];
    } else {
        $sql  = SQL_INSERT_INTO . TABLE_SENSORS;
        $sql .= SQL_VALUES_START;
        $sql .= COMMA . Quoted( $app_id );
        $sql .= COMMA . Quoted( $dev_id );
        $sql .= COMMA . Quoted( $key    );
        $sql .= SQL_VALUES_CLOSE;
        return SQL_Query( $sql );
    }
}

function processPayloadFields( $request ){
    foreach ( $request[ TTN_payload_fields ] as $key => $value ) {

        if ( is_array( $value ) ){
            $value = implode( "," , $value );
        }

        if( USE_SENSOR_TABLES ){
            //$request values for Application and Device are Primary Keys retrieved in saving the ping first
            $sensorID = processSensor( $request[ TTN_app_id ] , $request[ TTN_dev_id ] , $key , $value );
            $sql = SQL_INSERT_INTO . TABLE_SENSORVALUES;
            $sql .= SQL_VALUES_START;
            $sql .= COMMA . Valued( $sensorID );
            $sql .= COMMA . Quoted( $value  );
            $sql .= SQL_VALUES_CLOSE;
            SQL_Query( $sql );
        } else {
            $sql = SQL_INSERT_INTO . TABLE_PINGPAYLOADFIELDS;
            $sql .= SQL_VALUES_START;
            $sql .= COMMA . Valued( $request[PRIMARYKEY_Ping] );
            $sql .= COMMA . Quoted( $key    );
            $sql .= COMMA . Quoted( $value  );
            $sql .= SQL_VALUES_CLOSE;
            SQL_Query( $sql );
        }
    }
}

/*  ================================================================================================

    Create Database Schema with tables:
        applications
        devices
        gateways
        pings
        pingedgateways
        payloadfields
*/

function truncateTable( $table ){
    if( ALLOW_DROPTABLE ) {
        add_QueryLog( "<h2>Truncate Table: <b>$table</b></h2>" );

        SQL_Query( "TRUNCATE TABLE `$table`;" );
    }
}

function dropTable( $table ){
    if( ALLOW_DROPTABLE ) {
        add_QueryLog( "<h2>Drop Table: <b>$table</b></h2>" );

        SQL_Query( "DROP TABLE IF EXISTS `$table`;" );
    }
}

function createTable( $table , $idfield , $fields , $foreignkeys ){

    add_QueryLog( "<h2>Create Table: <b>$table</b></h2>" );

    $comment = " COMMENT 'Created by ITpings.nl script'";

    $sql ="CREATE TABLE IF NOT EXISTS `$table` (";
        if( $idfield ){
            $sql .= "`$idfield` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT " . $comment . ",";
            $sql .= "`".FIELD_CREATED_TIMESTAMP."` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP " . $comment . ",";
        }
        foreach ( $fields as $field ){
//            $fieldcomment =
            $sql .= "`$field[0]` $field[1]" . $comment . ",";
        }
        foreach ( $foreignkeys as $key ){
            $sql .= "	FOREIGN KEY (`$key[0]`) $key[1],";
        }
        $sql .= "PRIMARY KEY (`$idfield`)";
    $sql .= ")";
    $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8";
    $sql .= $comment;
    $sql .= ";";
    $result = SQL_Query( $sql );
}

function createTables(){

    createTable( TABLE_APPLICATIONS , PRIMARYKEY_Application

        ,[[ TTN_app_id              , TYPE_TTN_APP_ID           ]
        ,[ FIELD_DESCRIPTION        , TYPE_TTN_APP_DESCRIPTION  ]
        ], NO_FOREIGNKEYS );


    createTable( TABLE_DEVICES      , PRIMARYKEY_Device

        ,[[ TTN_dev_id              , TYPE_TTN_DEVICE_ID        ]
        ,[ TTN_hardware_serial      , TYPE_TTN_HARDWARE_SERIAL  ]
        ], NO_FOREIGNKEYS );


    createTable( TABLE_GATEWAYS     , PRIMARYKEY_Gateway

        ,[[ TTN_gtw_id              , TYPE_TTN_GTW_ID       ]
        ,[ TTN_gtw_trusted          , TYPE_TTN_TRUSTED_GTW  ]
        ,[ TTN_latitude             , LATITUDE_ACCURACY     ]
        ,[ TTN_longitude            , LONGITUDE_ACCURACY    ]
        ,[ TTN_altitude             , ALTITUDE_ACCURACY     ]
        ,[ TTN_location_source      , TYPE_LOCATION_SOURCE  ]
        ], NO_FOREIGNKEYS );


    function OnKey($str){
        return "(" . $str . ")";
    }


    createTable( TABLE_PINGS        , PRIMARYKEY_Ping

        ,[[ PRIMARYKEY_Application  , TYPE_FOREIGNKEY       ]
        ,[  PRIMARYKEY_Device       , TYPE_FOREIGNKEY       ]
        ,[ TTN_port                 , TYPE_TTN_PORT         ]
        ,[ TTN_counter              , TYPE_TTN_FRAME_COUNTER]
        ,[ TTN_downlink_url         , TYPE_TTN_DOWNLINK     ]
        ,[ TTN_payload_raw          , TYPE_TTN_PAYLOAD_RAW  ]

        ,[ ITPINGS_TIME             , TYPE_TTN_TIMESTRING   ]
        ,[ ITPINGS_FREQUENCY        , TYPE_TTN_FREQUENCY    ]
        ,[ ITPINGS_MODULATION       , TYPE_TTN_MODULATION   ]
        ,[ ITPINGS_DATA_RATE        , TYPE_TTN_DATA_RATE    ]
        ,[ ITPINGS_CODING_RATE      , TYPE_TTN_CODING_RATE  ]
        ,[ ITPINGS_LATITUDE         , LATITUDE_ACCURACY     ]
        ,[ ITPINGS_LONGITUDE        , LONGITUDE_ACCURACY    ]
        ,[ ITPINGS_ALTITUDE         , ALTITUDE_ACCURACY     ]
        ,[ ITPINGS_LOCATIONSOURCE   , TYPE_LOCATION_SOURCE  ]
        ]
        ,[[ PRIMARYKEY_Application  , IS_A_FOREIGNKEY_IN . TABLE_APPLICATIONS   . OnKey( PRIMARYKEY_Application )  ]
        ,[ PRIMARYKEY_Device        , IS_A_FOREIGNKEY_IN . TABLE_DEVICES        . OnKey( PRIMARYKEY_Device )  ]]
        );


    createTable( TABLE_PINGEDGATEWAYS , PRIMARYKEY_PingedGateway

        ,[[ PRIMARYKEY_Ping         , TYPE_FOREIGNKEY       ]
        ,[  PRIMARYKEY_Gateway      , TYPE_FOREIGNKEY       ]
        ,[ TTN_timestamp            , TYPE_TYPE_TIMESTAMP   ]
        ,[ TTN_time                 , TYPE_TTN_TIMESTRING   ]
        ,[ TTN_channel              , TYPE_TTN_CHANNEL      ]
        ,[ TTN_rssi                 , TYPE_TTN_RSSI         ]
        ,[ TTN_snr                  , TYPE_TTN_SNR          ]
        ,[ TTN_rf_chain             , TYPE_TTN_RFCHAIN      ]
        ]
        ,[[ PRIMARYKEY_Ping         , IS_A_FOREIGNKEY_IN . TABLE_PINGS      . OnKey( PRIMARYKEY_Ping )  ]
        ,[  PRIMARYKEY_Gateway      , IS_A_FOREIGNKEY_IN . TABLE_GATEWAYS   . OnKey( PRIMARYKEY_Gateway )  ]]
        );


    if( USE_SENSOR_TABLES ){


        createTable( TABLE_SENSORS , PRIMARYKEY_Sensor

            ,[[ PRIMARYKEY_Application  , TYPE_FOREIGNKEY       ]
            ,[  PRIMARYKEY_Device       , TYPE_FOREIGNKEY       ]
            ,[  ITPINGS_SENSORNAME      , TYPE_PAYLOAD_KEY      ]
            ]
            ,[[ PRIMARYKEY_Application  , IS_A_FOREIGNKEY_IN . TABLE_APPLICATIONS   . OnKey( PRIMARYKEY_Application )  ]
            ,[ PRIMARYKEY_Device        , IS_A_FOREIGNKEY_IN . TABLE_DEVICES        . OnKey( PRIMARYKEY_Device )  ]]
            );

        createTable( TABLE_SENSORVALUES , PRIMARYKEY_SensorValue

            ,[[ PRIMARYKEY_Sensor       , TYPE_FOREIGNKEY       ]
            ,[ ITPINGS_SENSORVALUE      , TYPE_PAYLOAD_VALUE    ]
            ]
            ,[[ PRIMARYKEY_Sensor       , IS_A_FOREIGNKEY_IN . TABLE_SENSORS . OnKey( PRIMARYKEY_Sensor )  ]]
            );

    } else {

        createTable( TABLE_PINGPAYLOADFIELDS , PRIMARYKEY_PingPayload

            ,[[ PRIMARYKEY_Ping         , TYPE_FOREIGNKEY       ]
            ,[  ITPINGS_SENSORNAME      , TYPE_PAYLOAD_KEY      ]
            ,[  ITPINGS_SENSORVALUE     , TYPE_PAYLOAD_VALUE    ]
            ]
            ,[[ PRIMARYKEY_Ping         , IS_A_FOREIGNKEY_IN . TABLE_PINGS . OnKey( PRIMARYKEY_Ping )  ]]
            );

    }

}//end function createTables


if ( IS_POST ) {

    // get POST content
    $request = json_decode( trim(file_get_contents("php://input")) , true);

    if( SINGLE_TABLE_REQUESTS ){
        insertRequest( $request );
    } else {
        $request = insertPing( $request );  // $request fields for app & device will be updated with keyID value
        processGateways( $request );        // save to 'pinged_gateways' and 'gateways' tables
        processPayloadFields( $request );   // save to 'sensors' and 'sensorvalues' tables
    }

} else { // it is an Admin or GET (JSON) request

    if( IS_ADMIN_ACCESS ){

        switch ( ADMIN_ACTION ) {
            case 'createtables':
                createTables();
                break;
            case 'truncatetables':
                foreach( ITPINGS_TABLES as $index => $tableName ){ // TODO get order right Referential Int. prevents deletions
                    truncateTable( $tableName );
                }
                break;
            case 'droptables':
                foreach( ITPINGS_TABLES as $index => $tableName ){
                    dropTable( $tableName );
                }
                break;
            default:
               echo "incorrect action" . $urlVars['action'];
        }
        showSQL_QueryLog();

    } else {

        $query = $queries[ API_QUERY ];
        if( API_QUERY AND $query ){
            SQL_Query( $query , true ); // true=return JSON object
        }

    }
}

mysqli_close($conn);

?>