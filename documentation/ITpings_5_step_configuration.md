# Configure ITpings in 5 steps

## 1. Create a 'ITpings' Database in your MySQL server

Register the Database Host, Database Name, Username and Password

![](https://i.imgur.com/n5tseaQ.jpg)

## 2. Edit the file ``ITpings_access_database.php``
  * update MySQL Account & Password
  * update your Private Key (so others can not abuse your HTTP Integration)
  
  ![](https://i.imgur.com/W0GeWYj.jpg)
  
  
## 3. Upload all ``ITpings_*.*`` files to your WebServer

## 4. Create a HTTP Integration in the The Things Network Application console  
   
pointing to the ``YOURWEBSERVER/ITpings_connector.php?key=YOURKEY``

![](https://i.imgur.com/g00KBos.jpg)