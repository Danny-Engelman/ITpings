# SQL Query Examples

## Application devices inclduing activity time

```sql
SELECT AD.* , LSV.FirstSeen, LSV.LastSeen 
FROM ITpings__ApplicationDevices AD 
	INNER JOIN( 
	SELECT _appdevid , min(created) as FirstSeen , max(created) as LastSeen 
	FROM ITpings__pings P
	GROUP BY _appdevid ) LSV 
WHERE AD._appdevid = LSV._appdevid
```

## Sensor readings within a time frame

```sql
SELECT
  P._pingid,
  P.created,
  SV.sensorname,
  SV.sensorvalue,
  PG.frequency,
  PG.rssi,
  PG.snr
FROM
  ITpings.ITpings__pings P
  JOIN ITpings__SensorValues SV ON SV._pingid = P._pingid
  JOIN ITpings__PingedGateways PG ON PG._pingid = P._pingid
WHERE
  SV._sensorid = 7
  AND P._pingid IN (SELECT P2._pingid
                    FROM ITpings.ITpings__pings P2
                    WHERE P2.created < '2018-2-27 23:59'
                          AND P2.created > '2018-2-27 21:00')
```