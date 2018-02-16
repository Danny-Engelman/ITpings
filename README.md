# ITpings

Is the TTN HTTP Integration for storing your TTN Application data in a MySQL database.

ITpings can be used by both beginner and advanced level PHP developers.

* Creates the MySQL Database Tables for you
* Is the HTTP Integration from The Things Network to your MySQL database
* Is the Dashboard API for your Web Front-end 


**Alternative technologies:**

* [Store data in MySQL using MQTT and Node-Red](https://ictoblog.nl/2017/04/15/ttn-mqtt-node-red-mysql-local-backup-of-your-lorawan-data)  
This stores your data in **one** Database Table, ITpings uses a more advanced Database Schema

## Use ITpings HTTP Integration and Dashboard in (under) 5 minutes

Reading this README (before diving head in first) will give you more insight in technical/technology choices.

If you still want to get started now,  
please do: 


1. Create a 'ITpings' Database in your MySQL server
2. Edit the file ``ITpings_access_database.php``
    1. update MySQL Account & Password
    2. update your Private Key (so others can not abuse your HTTP Integration)
3. Upload all ``ITpings_*.*`` files to your WebServer
4. Create a HTTP Integration in the The Things Network Application console  
pointing to the ``YOURWEBSERVER/ITpings_connector.php?key=YOURKEY``    
  **Yes! That is it!**  
ITpings will create the Database Schema once it receives a first Ping

5. Now open ``ITpings_dashboard.html``

## Why ITpings was created

I received my The Things Network (TTN) Gateway and 2 handfull of TTN Nodes late december 2017;  
and spent the Holidays playing with all Software related technologies.  

I have been a script kiddie since learning BASIC in the late 70s, hardware just is not my _**thing**_

myDevices, Amazon Web Services, IBM's Node-Red and several more are by themselves great technologies to get started.

But did not meet my growing list of requirements:

* Track **all** my Nodes (=Sensors) in one View
* 
   

## (My)SQL Database schema

It is not normalized to the Boyce-Codd Form

![](https://i.imgur.com/dTlBzVQ.jpg)

### MySQL Database management alternatives

**These tools can be helpfull but are NOT required; ITpings does all the work for you**

PHPMyAdmin is the default tool for MySQL on the LAMP/WAMP stack.

If you can access your MySQL server remotely (*you may have to ask your ISP to open the (default) 3306 port*)  
[Oracle's MySQL WorkBench](https://www.mysql.com/products/workbench/) (GPL license) or [Toad Edge](https://www.toadworld.com/products/toad-edge) ($$$) can be installed on your local machine. 

[RESTer](https://github.com/geekypedia/RESTer) (or a fork) (MIT license) Not only adds a RESTfull API (remember: is NOT required to use ITpings), but also provides a fast and good enough Admin interface for managing your MySQL database.

### Adding a REST interface (if you really want one)

* The ITpings HTTP integration **Creates** data in your MySQL database
* The ITpings Dashboard **Reads** data

So ITpings does **not** require a RESTfull (CRUD) interface.

If you really want one for your custom application I can suggest [RESTer](https://github.com/geekypedia/RESTer) (or a fork) 

[RESTer](https://github.com/geekypedia/RESTer) is also a great (simple) replacement for PHPMyAdmin;  

## Tips and Tricks