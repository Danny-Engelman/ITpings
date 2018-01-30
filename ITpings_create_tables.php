<?php
include('ITpings_configuration.php');


/*  ================================================================================================

    Create Database Schema with tables:
                                        applications
                                        devices
                                        gateways
                                        pings
                                        pingedgateways
                                        sensors
                                        sensorvalues
*/

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

}//end function createTables


switch ( ADMIN_ACTION ) {
    case 'createtables':
        createTables();
        break;
    case 'truncatetables':
        foreach( ITPINGS_TABLES as $index => $table ){ // TODO get order right Referential Int. prevents deletions
            add_QueryLog( "<h2>Truncate Table: <b>$table</b></h2>" );
            SQL_Query( "TRUNCATE TABLE `$table`;" );
        }
        break;
    case 'droptables':
        foreach( ITPINGS_TABLES as $index => $table ){
            add_QueryLog( "<h2>Drop Table: <b>$table</b></h2>" );
            SQL_Query( "DROP TABLE IF EXISTS `$table`;" );
        }
        break;
    default:
       echo "incorrect action" . $urlVars['action'];
}
showSQL_QueryLog();


?>