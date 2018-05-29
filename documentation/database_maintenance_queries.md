#Enhance Database

### Add new ITpings__data_xxx Table

* add Table definition:
    ``define('TABLE_BATTERY', TABLE_PREFIX . 'data_battery');``
* Add Viewname in configuration
    ``define('VIEWNAME_DATA_BATTERY', TABLE_PREFIX . 'Battery');``
* add case in function: process_Query_with_QueryString_Parameters()
    * (for testing) add same case at top for re-creation of View in every call
* add case in function: Create_Or_Replace_View($view_name) 

* Insert data from SensorValues
    ``INSERT INTO ITpings__data_battery SELECT _pingid,_devid, sensorvalue as value FROM ITpings.ITpings__SensorValues WHERE _sensorid IN(a,b,c) LIMIT 999999;``

#Clean Database

#### Clean Temperatures; KEEP MINUTE(P.created) IN (0,5,10,15,20,25,30,35,40,45,50,55) 

```sql
DROP TABLE IF EXISTS ITpings__data_temperature_new;
CREATE TABLE ITpings__data_temperature_new LIKE ITpings__data_temperature;
INSERT INTO ITpings__data_temperature_new 
(SELECT T.* FROM ITpings__pings P 
JOIN ITpings.ITpings__data_temperature T ON P._pingid = T._pingid
	WHERE MINUTE(P.created) IN (0,5,10,15,20,25,30,35,40,45,50,55)
		AND P.created < DATE_SUB(NOW(), INTERVAL 6 HOUR)
LIMIT 999999);

DROP TABLE IF EXISTS ITpings__data_temperature_old;
RENAME TABLE ITpings__data_temperature TO ITpings__data_temperature_old;
RENAME TABLE ITpings__data_temperature_new TO ITpings__data_temperature
```

#### Delete TTN & Cayenne Temperature readings sensorvalues table

They are stored in ITpings__data_xxx Tables

```sql
DELETE FROM ITpings.ITpings__sensorvalues WHERE _sensorid IN (7,39) LIMIT 999999;
```