<?php
//region ===== REQUIRED CONFIGURATION =============================================================
/** Database access settings, if you don't know what to use, contact your Webserver Administrator **/

define('DBHOST', 'localhost');      // Typically the Web and Database server run on the same server: localhost
define('DBNAME', 'ITpings');        // The database name given by your Database Administrator

// update with your own Database user account
define('DBUSERNAME', 'YOURUSERNAME');
define('DBPASSWORD', 'YOURPASSWORD');

// this key protects your webhook from being abused by others
define('YOUR_ITPINGS_KEY','__ENTER_WHATEVER_YOU WANT_');

// Now define a HTTP integration in your TTN Application Console
// pointing to webhook: YOUR_WEBSERVER/ITpings_connector.php?key=YOUR_ITPINGS_KEY

// The whole Database Schema will be created by ITpings when the connector is first executed

//endregion == REQUIRED CONFIGURATION =============================================================
?>
