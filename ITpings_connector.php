<?php
include('ITpings_configuration.php');

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


function lastTableEntry( $table , $field , $value , $conditions ){
    $sql  = "SELECT * FROM $table ";
    $sql .= " WHERE $field='$value' ";
    $sql .= " $conditions ";
    $sql .= " ORDER BY ".FIELD_CREATED_TIMESTAMP." DESC LIMIT 1;";
    return SQL_Query( $sql );
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

        //$request values for Application and Device are Primary Keys retrieved in saving the ping first
        $sensorID = processSensor( $request[ TTN_app_id ] , $request[ TTN_dev_id ] , $key , $value );
        $sql = SQL_INSERT_INTO . TABLE_SENSORVALUES;
        $sql .= SQL_VALUES_START;
        $sql .= COMMA . Valued( $sensorID );
        $sql .= COMMA . Quoted( $value  );
        $sql .= SQL_VALUES_CLOSE;
        SQL_Query( $sql );
    }
}


if ( IS_POST ) {

    // get POST content
    $request = json_decode( trim(file_get_contents("php://input")) , true);

    $request = insertPing( $request );  // $request fields for app & device will be updated with keyID value
    processGateways( $request );        // save to 'pinged_gateways' and 'gateways' tables
    processPayloadFields( $request );   // save to 'sensors' and 'sensorvalues' tables

} else { // it is a GET (JSON) request

        $query = $queries[ API_QUERY ];
        if( API_QUERY AND $query ){
            SQL_Query( $query , true ); // true=return JSON object
        }

}

mysqli_close($conn);
?>