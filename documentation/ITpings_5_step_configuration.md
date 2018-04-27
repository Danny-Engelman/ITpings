# Configure ITpings in 5 steps

## 1. Create a 'ITpings' Database in your MySQL server

* (in PHPMyAdmin) Register the Database Host, Database Name, Username and Password
		
	![](https://i.imgur.com/n5tseaQ.jpg)


## 2. Edit the file ``ITpings_access_database.php``
  * update MySQL Account & Password
  * update your Private Key (so others can not abuse your HTTP Integration)
  
  	![](https://i.imgur.com/W0GeWYj.jpg)
  
  
## 3. Upload all ``ITpings_*.*`` files to your WebServer

* remember: 
	* The ``ITpings_access_database.php`` file is the only required personal configuration file
	* The ``ITpings_configuration`` is for advanced level configuration
	* You can tailor your sensor/triggers in the ``ITpings_sensor_triggers.php`` file

	![](https://i.imgur.com/u0jJ82F.jpg)

## 4. Create a HTTP Integration in the The Things Network Application console  
   
* Enter the full URL to the __``ITpings_connector.php?key=__YOUR_PRIVATE_KEY__``__ file on your webserver

	![](https://i.imgur.com/g00KBos.jpg)

* Do not forget to enter the correct ``key=__YOUR_PRIVATE_KEY__``


* The ITpings Database Schema will be created by the **first call of the HTTP Integration**.  
 
## 5. Now open ``ITpings_dashboard.html`` on your WebServer