# ITpings - '_Just Give Me The Data_'

ITpings can be used by both beginner and advanced developers.

* ITpings Is the PHP [HTTP Integration](https://www.thethingsnetwork.org/docs/applications/http/) from The Things Network to your own MySQL database
* ITpings Creates the (normalized) MySQL Database Schema for you
* ITpings Is the (simple) Dashboard API for your Web Front-end (written in ES6, so Chrome only for now) 

**Alternative technologies:**

* [Store data in MySQL using MQTT and Node-Red](https://ictoblog.nl/2017/04/15/ttn-mqtt-node-red-mysql-local-backup-of-your-lorawan-data)  
This stores your data in **one** Database Table, ITpings uses a more advanced Database Schema
* [MQTT-NodeJS-MySQL](https://github.com/Kaasfabriek/TTN-MQTT-To-MYSQL-AND-PHP-To-CSV)  
also a single MySQL Table 
* [Visualize and push your IoT data](https://www.thethingsnetwork.org/forum/t/visualize-and-push-your-iot-data/1788)  
A long.. long.. list of IoT related tools

## Use ITpings HTTP Integration and Dashboard in (under) 5 minutes

Reading this README (before diving head in first) will give you more insight in technical/technology choices.

If you still want to get started now,  
### Install and Configure ITpings in 5 steps: 

If you want screenshots, see [the 5 steps in detail](./documentation/ITpings_5_step_configuration.md)

1. Create a 'ITpings' Database in your MySQL server
2. Edit the file ``ITpings_access_database.php``
    * update MySQL Account & Password
    * update your Private Key (so others can not abuse your HTTP Integration)
3. Upload all ``ITpings_*.*`` files to your WebServer
4. Create a HTTP Integration in the The Things Network Application console  
pointing to the ``YOURWEBSERVER/ITpings_connector.php?key=YOURKEY``    
  **Yes! That is it!**  
ITpings will create the Database Schema once it receives the first (HTTP Integration) Ping

5. Now open ``ITpings_dashboard.html`` on your WebServer

## Why ITpings was created

My [(TTN) The Things Network](https://www.thethingsnetwork.org/) [Gateway](https://www.thethingsnetwork.org/docs/gateways/) and 2 handfull of [TTN Nodes](https://www.thethingsnetwork.org/docs/devices/node/) arrived late december 2017.  
I am not a Hardware Guy, UART is nothing but a 4 letter acronym to me, so I spent the Holidays playing with all Software related technologies.  

I have been a script kiddie since learning BASIC in the late 70s, hardware just is not my _**thing**_  
For me, every new project is a challenge to [think different](https://www.youtube.com/watch?v=4HsGAc0_Y5c).

myDevices, Amazon Web Services, IBM's Node-Red and several more are by themselves great technologies to get started.

But did not meet my growing list of requirements:

* plug & play in any new environment, without registering any Node info
* Track **all** my Nodes (=Sensors) in one View
* In a generic dataformat, ready for any future application (or conversion to AWS, Cayenne or other SAAS providers)
* Get (almost) live updates of new information 
* Issue an alarm when a device was not seen for over an hour 

From a simple SPA page it evolved into a labour of love, especially since I always try to develop by the _'Use only necessary dependencies'_ rule.
So I ditched React and Angular, considered Vue, and eventually went with Native ES6 WebComponents 
(Hey! I am in charge now, using ES6, so **my** Dashboard does **not** work in crappy old-fashioned browsers )
For simplicity sake I didn't go fancy with NodeJS, socket.io and loads more dependencies;  
ITpings is just PHP,SQL and HTML5 (ES6) So runs on any xxMP stack. 

I hereby donate her with an MIT License to the Open Source community,

_**... be gentle but just with her**_   

## (My)SQL Database schema

Normalized for usability 

![](https://i.imgur.com/R4qTVPu.jpg)

All **Tables** start with lowercase letters, ITpings creates **Views** with Capitals:


### Future enhancements


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

## Development comments

Before using ITpings in a production environment, you may want to read [Why Build a Time Series Data Platform?](https://db-engines.com/en/blog_post/71)
### MomentJS versus Date-Fns

ChartJS works on top of MomentJS, otherwise switch to more modern Date-fns

## Todo

* one-click Drop/Create database
* JS console CLI?