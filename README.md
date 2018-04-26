# ITpings : _Just Give Me The Data_

For beginner and advanced developers:

* ITpings Is the **PHP [HTTP Integration](https://www.thethingsnetwork.org/docs/applications/http/)** from The Things Network to **your own MySQL database**
* ITpings **Creates the (normalized) MySQL Database Schema for you**
* ITpings Is the **(simple) Dashboard API for your Web Front-end** (written in ES6, so Chrome only for now)
* ITpings has **no** dependencies on any Framework 

## Difference between MQTT and the HTTP Intergration

**MQTT (publish/subscribe)** was developed for low bandwidth, low power communication between devices.
The MQTT Broker **pushes** data to the client on an (always) open TCP connection
[https://www.hivemq.com/blog/how-to-get-started-with-mqtt](https://www.hivemq.com/blog/how-to-get-started-with-mqtt)  
► MQTT requires authentication **per** device

With an **HTTP (request/response) Integration** the (TTN) Broker **pushes** all data to a Webhook/Endpoint.  
► **No** authentication (per device) required 

## Use ITpings HTTP Integration and Dashboard in (under) 5 minutes

Read this README, it will give you more insight in technology choices.
 
### Want to get started asap?!? Install and Configure ITpings in 5 steps: 

If you want screenshots, see **[the 5 steps in detail](./documentation/ITpings_5_step_configuration.md)**

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

> _I have been a script kiddie since learning BASIC in the late 70s, hardware just is not my **thing**_

> -- Danny Engelman

My [(TTN) The Things Network](https://www.thethingsnetwork.org/) [Gateway](https://www.thethingsnetwork.org/docs/gateways/) and 2 handfull of [TTN Nodes](https://www.thethingsnetwork.org/docs/devices/node/) arrived late december 2017.  
I am not a Hardware Guy, UART is nothing but a 4 letter acronym to me, so I spent the Holidays playing with all Software related technologies.  




[myDevices](https://mydevices.com/), [ThingsBoard](https://thingsboard.io/), [Amazon Web Services](https://www.npmjs.com/package/ttn-aws-iot), [IBM's Node-Red](https://www.npmjs.com/package/node-red-contrib-ttn) and several more are by themselves great technologies to get started.  
(_and I do encourage you to at least get acquainted with them_)

But neither met my growing list of requirements (or took too long to build):

* plug & play in any new (pilot project) environment, without registering any Node info
* Track **all** my Nodes (=Sensors) in one View
* In a generic dataformat, ready for any future application (or conversion to AWS, Cayenne or other SAAS providers)
* Get (almost) live updates of new information 
* Issue an alarm when a device was not seen for over an hour 

From a simple SPA page it evolved into a labour of love,  
especially since I develop by the motto: **_Use only necessary dependencies_**

So I ditched Bootstrap, React and Angular, considered Vue, and eventually went with Native ES6 WebComponents   
Hey! **I** am in charge now!  
I spent the last 12 years in the Microsoft Front-End world.. feels good to break free :-)  
Using only CSS4, HTML5 and ES6, so **my** Dashboard does **not** work in crappy old-fashioned browsers.  

For simplicity sake I didn't go fancy with NodeJS, socket.io and loads more dependencies;  

ITpings is just PHP, SQL and HTML5 (ES6) So runs on any xxMP stack; That is about **[83% of ALL websites](https://w3techs.com/technologies/details/pl-php/all/all)** out there. 

I hereby donate her with a **MIT License** to the Open Source community, _**... be gentle but just with her**_

## ITpings - MIT license
**Copyright 2018 Danny Engelman**

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

**► The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software ◄**

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
<hr>   
# ITpings Front-End Dashboard with Live data

The MySQL Database Schema is the major part of this application.

I have included a simple Dashboard so something is shown the moment you install this application.  

To make this work on any LAMP (or xxMP) stack, the Dashboard uses oldskool AJAX short-polling for (almost live) updates.

Instead of using MQTT (requires registration of every device) ITpings Dashboard polls the MySQL database every 500ms (return value is a single ping id, so including Headers is only 257 **Bytes** of traffic)  
Only when the database has new values the IT-pings Table and Chart ([HTML5 WebComponents/Custom Elements](https://developers.google.com/web/fundamentals/web-components/)) are updated

For your own more complex Dashboard I suggest looking into [MQTT](https://www.thethingsnetwork.org/docs/applications/mqtt/) and [WebSockets](http://blog.teamtreehouse.com/an-introduction-to-websockets)

The Dashboard was built **without any Frameworks**  
Using only native CSS4 HTML5 and ES6;  
So (for now) runs in Chrome only (other Browsers do not support new W3C standards yet)

![](https://i.imgur.com/GjLOl5T.jpg)

## Working with JSON data

**Note:** Use Chrome, and install a decent **[JSON Viewer](https://chrome.google.com/webstore/detail/json-viewer/gbmdgpbipfallnflgajpaliibnhdgobh)** extension

ITpings uses a custom API at ``/ITpings_connector.php?query=[TABLE/VIEW NAME]``

Returning JSON data:

![](https://i.imgur.com/QIiTZzw.jpg)



## Tracing the HTML5 Dashboard

Open the F12 Dev Console to see what the Dashboard does:

![](https://i.imgur.com/qVwYMQz.jpg)

<br><br>
<hr>

# ITpings : Under the hood
## (My)SQL Database schema

The [**TTN HTTP Intergration sends a JSON formatted POST request**](https://www.thethingsnetwork.org/docs/applications/http/) about Applications, Devices, Gateways and Sensor data to the PHP script.  
This **is** Relational Data; so a SQL database is a logical choice (Or so I was educated in the late 80s)

I considered NoSQL solutions (have used several in projects)  
But in the end the hardest part with SQL is creating the Schema... which ITpings does for you  

![](https://i.imgur.com/R4qTVPu.jpg)

All these **Tables** start with lowercase letters, but you will hardly use Table references, as ITpings creates **Views** (with Capitals):



### Forget the Tables, Learn to live with Views

Just to scare you with the work that has been done; you do **NOT** have to learn and execute SQL scripts like:

**SensorValues VIEW:**

    SELECT P._pingid AS _pingid
		,P.created AS created
		,S._sensorid AS _sensorid
		,AD._appdevid AS _appdevid
		,AD.app_id AS app_id
		,AD.dev_id AS dev_id
		,S.sensorname AS sensorname
		,SV.sensorvalue AS sensorvalue 
	FROM ITpings__sensorvalues SV 
		JOIN ITpings__sensors S ON S._sensorid = SV._sensorid
		JOIN ITpings__pings P ON P._pingid = SV._pingid
		JOIN ITpings__ApplicationDevices AD ON AD._appdevid = S._appdevid 
	ORDER BY P.created desc,SV._sensorid
 
This SQL script is one (of many) predefined Views, available as: **SensorValues**

Outputting data as:

![](https://i.imgur.com/pqODZnw.jpg)

#### by ITpings predefined Views:

* Devices
* ApplicationDevices
* PingedGateways
* SensorValues
* Gateways
* Events

And you can add your own in the ``ITpings_connector.php`` PHP script.

## Retrieving data from the MySQL database
* The ITpings HTTP integration **Creates** data in your MySQL database
* The ITpings Dashboard **Reads** data

So ITpings does **not** require a RESTfull (CRUD) interface.  
If you really want one for your custom application, I suggest **[RESTer](https://github.com/geekypedia/RESTer)** 

The Client contacts the Server every second to check if there are new (higher) ID values in the Database Tables.  
This "Short Polling" is much easier to implement than WebSockets (which is better for multiple Clients,  
but for this application it is only you and your Dashboard)

### Query URI Examples

**filter** lt, le, eq, ge, gt

* URI: ``/ITpings_connector.php?query=SensorValues&filter=sensorname eq 'temperature_5'``  
SQL: ``SELECT * FROM SensorValues WHERE sensorname='temperature_5'``

* URI: ``/ITpings_connector.php?query=SensorValues&filter=sensorname eq 'luminosity_6',sensorvalue gt 700``  
SQL: ``SELECT * FROM SensorValues WHERE sensorname='luminosity_6' AND sensorvalue > 700``

* URI: ``/ITpings_connector.php?query=SensorValues&_sensorid=6,7``  
SQL: ``SELECT * FROM SensorValues WHERE _sensorid IN(6,7)``

### Paramaterized Queries

To prevent SQL Injection the Database must not accept bare SQL requests.

> **Note**: ITpings is a basic API and very.. very.. strict when processing Query String parameters.

> If ITpings can't execute your (complex) query  
> you can dive into the ``ITpings_connector.PHP`` source-code at function: ``process_Query_with_QueryString_Parameters``


## Display Tables and Graphs using HTML Custom Elements

The Dashboard is built using **[ES6](https://codeburst.io/es6-tutorial-for-beginners-5f3c4e7960be)** and W3C standard **[Custom Elements](https://developers.google.com/web/fundamentals/web-components/customelements)** (supported in Chrome, even FireFox is not up to par yet)  
Meant to be (my) a Developer Dashboard I didn't use **[Polymer](https://www.polymer-project.org/)** to make it work in other browsers.

### Custom Elements (a subset of Web Components)

Make it possible te create new HTML elements, encapsulating all the logic

HTML:

	<itpings-table query="SensorValues"></itpings-table>

Displays the whole Table (and keeps it up to date):

![](https://i.imgur.com/cU9mhcz.jpg)

### Applying CSS

ITpings adds HTML content with loads of data-attributes:

![](https://i.imgur.com/vJAHdEN.jpg)

This makes it possible to use just CSS for extra layout

In the Table screenshot above Columns and Rows are hidden based on data-attributes:

	<style>
        /* Hide Columns */
        itpings-table[query='SensorValues'] [data-column$='sensorid'],
        itpings-table[query='SensorValues'] [data-column$='appdevid'],
        itpings-table[query='SensorValues'] [data-column='app_id'],
		itpings-table[query='SensorValues'] [data-column='hardware_serial'] {
		    display: none;
		}

        /* Hide TR rows*/
        itpings-table[query='SensorValues'] [data-sensorname^='accelerometer'] {
            display: none;
        }
	</style>

More, see: **[30 CSS Selectors you must memorize](https://code.tutsplus.com/tutorials/the-30-css-selectors-you-must-memorize--net-16048)**

<hr>
# Misc

### MySQL Database management alternatives

**These tools can be helpfull but are NOT required; ITpings does all the work for you**

PHPMyAdmin is the default tool for MySQL on the LAMP/WAMP stack.

If you can access your MySQL server remotely (*you may have to ask your ISP to open the (default) 3306 port*)  
**[Oracle's MySQL WorkBench](https://www.mysql.com/products/workbench/)** (GPL license) or [Toad Edge](https://www.toadworld.com/products/toad-edge) ($$$) can be installed on your local machine. 

**[RESTer](https://github.com/geekypedia/RESTer)** (or a fork) (MIT license) Not only adds a RESTfull API (remember: is NOT required to use ITpings), but also provides a fast and good enough Admin interface for managing your MySQL database. (it includes an older version of **[Adminer](https://www.adminer.org/)**)  

### **Alternative technologies:**

* TTNMon [BackEnd](https://github.com/RobinMeis/TTNmon-Backend) (PHP/MySQL) and [FrontEnd](https://github.com/RobinMeis/TTNmon-Frontend) (JQuery,Bootstrap,datatables)  
Does **not** store the payload, so no graphing of Temperature, Luminosity or other sensor values
* [Store data in MySQL using MQTT and Node-Red](https://ictoblog.nl/2017/04/15/ttn-mqtt-node-red-mysql-local-backup-of-your-lorawan-data)  
Stores your data in **one** Database Table, ITpings uses a more advanced Database Schema
* [MQTT-NodeJS-MySQL](https://github.com/Kaasfabriek/TTN-MQTT-To-MYSQL-AND-PHP-To-CSV)  
also a single MySQL Table 
* [Visualize and push your IoT data](https://www.thethingsnetwork.org/forum/t/visualize-and-push-your-iot-data/1788)  
A long.. long.. list of IoT related tools


### Further reading

* https://mongoose-os.com/blog/why-mqtt-is-getting-so-popular-in-iot/

# Forking ITpings Building 

### Uglify-ES options
add ES6 CustomElements to ``../tools/domprops.json`` file:

``
    "customElements",
    "define",
    "observedAttributes",
    "attributeChangedCallback",
    "connectedCallback",
    "isConnected",
    "disconnectedCallback",
    "adoptedCallback"
``

### Adding Triggers to ITpings_sensor_triggers.php

Process your own sensor_name and sensor_value here

todo: document