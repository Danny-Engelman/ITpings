<?php

function Execute_Multiple_Queries($sql)
{
    echo "<PRE>" . str_replace(';', '<BR/>', $sql) . "</PRE>";
    SQL_MULTI_QUERY($sql);
}

/**
 * KEEP all sensorvalues that are in: minutes ending on: 0,5,10,15,20,25,30,35,40,45,50,55
 * UP TO the values reveived over 1 day ago (so last 24 hour entries are All received sensor readings)
 * @param $orgTable - TABLE-nnnnn constant
 * @return string
 */
function Clean_DataTable_SQL($orgTable)
{
    $newTable = $orgTable . "_new";
    $oldTable = $orgTable . "_old";
    $PKey = PRIMARYKEY_Ping;
    $created = ITPINGS_CREATED_TIMESTAMP;

    $sql = "DROP TABLE IF EXISTS $newTable;";
    $sql .= "CREATE TABLE $newTable LIKE $orgTable;";
    $sql .= "INSERT INTO $newTable";
    $sql .= " (SELECT T.* FROM " . TABLE_PINGS . " P";
    $sql .= " JOIN $orgTable T ON P.$PKey = T.$PKey";
    $sql .= " WHERE MINUTE(P.$created) IN (0,5,10,15,20,25,30,35,40,45,50,55)";
    $sql .= " AND P.$created < DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $sql .= " LIMIT 999999);";
    $sql .= "DROP TABLE IF EXISTS $oldTable;";
    $sql .= "RENAME TABLE $orgTable TO $oldTable;";
    $sql .= "RENAME TABLE $newTable TO $orgTable;";
    $sql .= "DROP TABLE IF EXISTS $oldTable;";

    return $sql;
}

function Clean_DataTables()
{
    $sql = Clean_DataTable_SQL(TABLE_DATA_TEMPERATURE);
    $sql .= Clean_DataTable_SQL(TABLE_DATA_LUMINOSITY);
    $sql .= Clean_DataTable_SQL(TABLE_DATA_BATTERY);

    Execute_Multiple_Queries($sql);
}

function Clean_SensorValues()
{
    // delete Cayenne Digital-3 sensorvalues
    $sql = "DELETE FROM " . TABLE_SENSORVALUES . " WHERE " . PRIMARYKEY_Sensor . " IN (5,12);";

    Execute_Multiple_Queries($sql);
}