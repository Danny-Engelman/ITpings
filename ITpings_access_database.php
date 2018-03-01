<?php
//region ===== REQUIRED CONFIGURATION =============================================================
/** Database access settings, if you don't know what to use, contact your Webserver Administrator **/

// Typically the Web and Database server run on the same server: localhost
// If your ISP has given you a hostname, first try without the :port number
define('DBHOST', 'localhost');
//define('DBHOST', 'fdb19.awardspace.net');
//define('DBHOST', 'ourownserver.com:3306');

// The database name given by your Database Administrator
define('DBNAME', 'ITpings');

// update with your own Database user account
define('DBUSERNAME', 'YOURUSERNAME');
define('DBPASSWORD', 'YOURPASSWORD');

// this key protects your webhook from being abused by others
define('YOUR_ITPINGS_KEY', '__ENTER_YOUR_PRIVATE_KEY_');

// [optional] open the ITpings_connector.php in your WebBrowser
// and see ITpings create the Database Schema

// Now define a HTTP integration in your TTN Application Console
// pointing to webhook: YOUR_WEBSERVER/ITpings_connector.php?key=YOUR_ITPINGS_KEY

// If no Tables exist yet,
// The whole Database Schema will be created by ITpings when the connector is first executed

//endregion == REQUIRED CONFIGURATION =============================================================
?>
