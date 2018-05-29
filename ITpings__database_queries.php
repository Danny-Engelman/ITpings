<?php
//PREDEFINED QUERIES
define('NO_SQL_QUERY', 'none');

//region ===== PROCESS GET QUERY ==================================================================

/**
 * Queries can be:
 *  - Predefined Tables
 *  - Predefined Views
 *  - Custom Queries
 *
 * Process URI with ?query=xxxx
 *
 */
function process_Table_or_View_query()
{
    global $QueryStringParameters;
    $sql = EMPTY_STRING;

    $query = TABLE_PREFIX . $QueryStringParameters['query'];

    $updateview = $QueryStringParameters['updateview'];

    if ($updateview) {
        add_JSON_message_to_JSON_response('ViewUpdate: ' . $query);
        Create_Or_Replace_View($query);
    }

    /** User can only request for limitted table/view names, this is the place to deny access to Tables/Views **/
    switch ($query) {
        /**!!!!!!!!!!!!!!!!! ALWAYS CREATE OR REPLACE VIEW ON EVERY ENDPOINT CALL !!!!!!!!!!!!!!!!!!!!!!!!!!!!**/
//        case VIEWNAME_DATA_TEMPERATURE:
//        case VIEWNAME_DATA_LUMINOSITY:
//        case VIEWNAME_DATA_BATTERY:
        case 'ALWAYS_CREATE_OR_REPLACE_VIEW'://todo: read VIEW NAME to be updated from querystring parameter
            add_JSON_message_to_JSON_response('ViewUpdate: ' . $query);
            Create_Or_Replace_View($query);
            break;

        /**  Process regular View/Table names **/
        case VIEWNAME_DATA_TEMPERATURE:
        case VIEWNAME_DATA_LUMINOSITY:
        case VIEWNAME_DATA_BATTERY:
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
            $query = false;
            break;
    }

    post_process_Query($query, $sql);

}


/**
 * Execute queries defined as ?query=[name] URI parameter
 *
 * @return string - $sql
 */
function process_Predefined_Query()
{
    global $QueryStringParameters;
    $query = $QueryStringParameters['query'];
    $sql = EMPTY_STRING;

    switch ($query) {

        /** every heartbeat milliseconds The Dashboard polls for a sinle maximum _pingid **/
        /** ?query=PingID **/
        case 'PingID': // query=PingID   // smallest JSON payload as possible, single pingID
            exit(SQL_Query("SELECT MAX(" . PRIMARYKEY_Ping . ") AS ID FROM " . TABLE_PINGS)['ID']);
            break;

        /** when the _pingid has increased the Dashboard polls for all Table/View ID values **/
        /** ?query=IDs **/
        case 'IDs': // all relevant IDs , smallest JSON payload as possible
            attach_Max_IDs_to_JSON_response();
            $sql = NO_SQL_QUERY;
            break;

        /** return full (original) TTN JSON request from POSTrequests Table **/
        /** ?query=ping **/
        case 'ping':
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
        case 'Devices':
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
        case 'DBInfo':
            attach_Max_IDs_to_JSON_response();
            attach_period_to_JSON_response();
            $sql = "SELECT REPLACE(S . TABLE_NAME, '" . TABLE_PREFIX . "', '') AS 'Table'";
            $sql .= ",S . TABLE_ROWS AS Rows";
            $sql .= ",S . AVG_ROW_LENGTH AS RowLength, S . DATA_LENGTH AS DataLength";
            $sql .= ",S . INDEX_LENGTH AS IndexLength,S . DATA_FREE AS Free";
            $sql .= " FROM information_schema . tables S";
            $sql .= " WHERE table_name LIKE '" . TABLE_PREFIX . "%'";
            $sql .= " AND TABLE_TYPE = 'BASE TABLE'";
            $sql .= " ORDER BY DataLength ASC";
            break;

        /** ?query=Period **/
        case 'Period':
            attach_period_to_JSON_response();
            $sql = NO_SQL_QUERY;
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

        /** ?query=CleanDataTables **/
        case 'CleanDataTables':
            Clean_DataTables();
            break;

        // SELECT * FROM ITpings.ITpings__PingedGateways where time='0000-00-00 00:00:00';

        default:
            http_response_code(404);
            echo "Error query:'$query' does not exist or can not be processed";
            die();
            break;
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
 * @param $table
 * @param $key
 * @param bool $value
 * @return bool|mixed
 */
function get_Maximum_ID($table, $key, $value = false)
{
    global $JSON_response;

    if ($value === FALSE) {
        $value = SQL_Query("SELECT MAX($key) AS mx FROM $table")['mx'];
    }

    $table = str_replace(TABLE_PREFIX, '', $table);

    if (!isset($JSON_response[QUERYKEY_maxids][$table])) $JSON_response['maxids'][$table] = [];
    $JSON_response[QUERYKEY_maxids][$table][$key] = (int)$value;

    return $value;
}

function attach_period_to_JSON_response()
{
    global $JSON_response;
    $created = ITPINGS_CREATED_TIMESTAMP;
    $period = SQL_Query("SELECT MIN($created) AS Start,MAX($created) AS End FROM " . TABLE_PINGS);
    $JSON_response['periodStart'] = $period['Start'];
    $JSON_response['periodEnd'] = $period['End'];
}

/**
 * create a JSON structure with the most recent PrimaryKey value for Tables and Views
 * The Browser polls this endpoint and thus only updates HTML Tables/Graphs when there is NEW data
 */
function attach_Max_IDs_to_JSON_response()
{
    global $JSON_response;

    $JSON_response[QUERYKEY_maxids] = array();

    get_Maximum_ID(TABLE_APPLICATIONS, PRIMARYKEY_Application);
    get_Maximum_ID(TABLE_DEVICES, PRIMARYKEY_Device);
    $event_pingid = get_Maximum_ID(TABLE_EVENTS, PRIMARYKEY_Ping);
    $gtwid = get_Maximum_ID(TABLE_GATEWAYS, PRIMARYKEY_Gateway);
    get_Maximum_ID(TABLE_LOCATIONS, PRIMARYKEY_Location);
    $pingid = get_Maximum_ID(TABLE_PINGS, PRIMARYKEY_Ping);
    get_Maximum_ID(TABLE_DATA_TEMPERATURE, PRIMARYKEY_Ping);
    get_Maximum_ID(TABLE_SENSORS, PRIMARYKEY_Sensor);

    //reuse already found visible_ids
    get_Maximum_ID(VIEWNAME_SENSORVALUES, PRIMARYKEY_Ping, $pingid);
    get_Maximum_ID(VIEWNAME_PINGEDGATEWAYS, PRIMARYKEY_Ping, $pingid);
    get_Maximum_ID(VIEWNAME_PINGEDDEVICES, PRIMARYKEY_Ping, $pingid);
    get_Maximum_ID(VIEWNAME_EVENTS, PRIMARYKEY_Ping, $event_pingid);
    get_Maximum_ID(VIEWNAME_GATEWAYS, PRIMARYKEY_Gateway, $gtwid);
}

/**
 * Manage Database
 **/

/**
 * @param $table
 * @param $key
 * @param $value
 */
function Delete_By_Key_Value($table, $key, $value)
{
    $sql = "DELETE FROM $table WHERE $key = $value;";
    SQL_DELETE($sql);
}

/**
 * @param $table
 * @param $key
 * @param $reference_table
 */
function Delete_Unreferenced($table, $key, $reference_table)
{
    $sql = "DELETE FROM $table WHERE $key NOT IN(SELECT $key FROM $reference_table);";
    SQL_DELETE($sql);
}

/**
 *
 */
function Delete_Unreferenced_From_All_Tables()
{
    Delete_Unreferenced(TABLE_SENSORS, PRIMARYKEY_Sensor, TABLE_SENSORVALUES);
    Delete_Unreferenced(TABLE_GATEWAYS, PRIMARYKEY_Gateway, TABLE_PINGEDGATEWAYS);
    Delete_Unreferenced(TABLE_APPLICATIONDEVICES, PRIMARYKEY_ApplicationDevice, TABLE_PINGS);
    Delete_Unreferenced(TABLE_APPLICATIONS, PRIMARYKEY_Application, TABLE_APPLICATIONDEVICES);
    Delete_Unreferenced(TABLE_DEVICES, PRIMARYKEY_Device, TABLE_APPLICATIONDEVICES);
}

/**
 * @param $pingID
 */
function Delete_By_Ping_ID($pingID)
{
    Delete_By_Key_Value(TABLE_EVENTS, PRIMARYKEY_Ping, $pingID);
    Delete_By_Key_Value(TABLE_SENSORVALUES, PRIMARYKEY_Ping, $pingID);
    Delete_By_Key_Value(TABLE_PINGEDGATEWAYS, PRIMARYKEY_Ping, $pingID);
    Delete_By_Key_Value(TABLE_PINGS, PRIMARYKEY_Ping, $pingID);
    Delete_Unreferenced(TABLE_LOCATIONS, PRIMARYKEY_ApplicationDevice, TABLE_PINGS);
}

/**
 * @param $_appdevid
 */
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
 * Used as CallBack from the Implode
 * @param $value
 * @return string
 */
function convertValue_to_QuotedString_or_Integer($value)
{
    return is_string($value) ? Quoted($value) : $value;
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
        /** process all (by ITpings defined!!!) QueryString parameters , so user can not add roque SQL
         * So usage is very strict with:
         * Query_Parameters have to be:
         * - defined as constant
         * - referenced in $_VALID_QUERY_PARAMETERS
         * - process in switch below
         **/
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

                        $where .= $and . ITPINGS_CREATED_TIMESTAMP . " >= DATE_SUB(NOW(), INTERVAL " . (int)$parameter_value . " $interval_unit)";
                        break;

                    case QUERY_PARAMETER_INTERVALUNIT:// processed in previous interval case
                        break;

                    case QUERY_PARAMETER_BY10MINUTES:
                        $where .= $and . " MINUTE(" . ITPINGS_CREATED_TIMESTAMP . ") IN (0,10,20,30,40,50) ";
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
                                $glue = QUERY_PARAMETER_SEPARATOR;
                                $where .= implode($glue, array_map('convertValue_to_QuotedString_or_Integer', explode($glue, $parameter_value)));
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
        $JSON_response[QUERYKEY_errors] .= "Error: Empty SQL statement";
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
