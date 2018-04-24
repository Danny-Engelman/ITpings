/** @license MIT
 * This Dashboard uses hardcoded Databases references
 * If you make changes in the Database Schema, be sure to check those new names in this Dashboard source code
 * */

// uglify-config.json:
// mangle:{
// "properties": {      "keep_quoted": true    }
// disabled cmd-line parameters "--mangle-props  keep_quoted" in: settings>tools>File Watchers>Uglify command prompt

// Use a decent IDE, like JetBrains, Atom or VSCode
// Learn to collapse regions/code-blocks with Alt-7 (code structure view) Ctrl-Plus and Ctrl-Minus
// press Ctrl-Shift-Minus now to collapse all code
// press Ctrl-Shift-Plus TWICE to uncollapse all code
// Use Ctrl-B to browse code by usage

//todo: debug double gateways - activated event trace
//todo: plot coordinates
//todo: fixed alt in PingedGateways
//todo: detect foreign traffic
//todo: link Gateway to TTNmapper: https://ttnmapper.org/colour-radar/?gateway=ttn_365csi_nl&type=radar
//todo: monitor when last ping was received (dashboard reports TTN server offline status)
//todo: preload other graph intervals (download largest then proces in WebWorker)

//todo audio sounds for (new) events
//todo: (low) remove cached data when deleted from DB

//unoffical TTN endpoints
//https://www.thethingsnetwork.org/gateway-data/location?latitude=52.316&longitude=4.66040850&distance=2000

!(function (window, document, localStorage, emptyString = "") {

        if (!window.customElements) {
            window.setTimeout(function () {
                document.body.innerHTML = "<h1>This Browser does not support <a href=https://developer.mozilla.org/en-US/docs/Web/Web_Components/Using_custom_elements>W3C customElements</a> yet<br>Use Chrome, or FireFox (may 2018)</h1>";
            }, 1000);
            return;
        }

        let __colors_blue = "#99e6ff,#33ccff,#0099cc".split`,`;

        let __custom_element_prefix = "itpings-";
        let __cacheRowCount = 100;  // itpings-tables cache this amount of rows

        let __QueryManager;         // manage all (async) queries from/for multiple tables/charts (if A has just called for data X, then B can use it as well)

        let __ITpings_Router;

        let __$DEF = {// matching definitions in PHP/MySQL

            ID: "_pingid",
            dev_id: "dev_id",
            created: "created",
            modulation: "modulation",
            data_rate: "data_rate",
            coding_rate: "coding_rate",

            ITpings_devid: "_devid",
            ITpings_result: "result",
            ITpings_cached: "cached",
            ITpings_time: "time",

            sensor: "sensor", //todo: rename back to sensorname in database config?

            MINUTE: "MINUTE",
            HOUR: "HOUR",
            DAY: "DAY",
            WEEK: "WEEK",
            MONTH: "MONTH",
            YEAR: "YEAR",

            THEAD: "THEAD",
            TABLE: "TABLE",
            TBODY: "TBODY"
        };

//region ========================================================== $_datetime functions
        /**
         day=864e5
         */
        let $_isDate = x => Object.prototype.toString.call(x) === '[object Date]';
        let $_dateMinutesSince = date => Math.floor((new Date(date).getTime() - new Date().getTime()) / 864e2);  // 0=today , negative for past days, positive for future days

        let $_dateLocale = navigator.language;
        let $_dateFormat_Hmm = "H:mm";
        let $_dateFormat_DMMM = "D MMM";
        let $_dateFormat_DMMMHmm = "D MMM H:mm";
        let $_dateDateDefault = {month: 'short', day: 'numeric'};
        let $_dateTimeDefault = {hour: '2-digit', minute: '2-digit', hour12: false};

        let $_dateEnsureDate = (date = '') => $_isString(date) ? new Date(date) : date;
        let $_dateTimeStr = (date, options = $_dateTimeDefault, locale = $_dateLocale) => $_dateEnsureDate(date).toLocaleTimeString(locale, options);

        function $_dateStr(date, format = $_dateFormat_DMMMHmm, locale = $_dateLocale) {
            date = $_dateEnsureDate(date);
            let localeDate = options => date.toLocaleDateString(locale, options);//https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/toLocaleDateString
            if (format === $_dateFormat_DMMM) {
                return localeDate($_dateDateDefault);
            } else if (format === $_dateFormat_Hmm) {
                return date.getHours() + ":" + date.getMinutes()
            } else if (format === $_dateFormat_DMMMHmm) {
                return $_dateStr(date, $_dateFormat_DMMM) + " " + $_dateTimeStr(date);
            }
        }

        function $_dateDiff(date1, date2, format) {

        }

        // __MOMENT = (date, format) => $_isDefined(format) ? moment(date)["format"](format) : moment(date),
        // __MOMENT_DIFF_MINUTES = x => (__MOMENT(x))["diff"](__MOMENT(new Date()), "minutes"),

//endregion ======================================================= $_datetime functions

//region ========================================================== Application Constants / definitions

        let __DB_PingID_endpoint = "PingID";    // Smallest payload 256 Bytes, but only gets max(_pingid)
        let __DB_IDs_endpoint = "IDs";          // All (new) IDs (only called when there is a new _pingid)


//endregion ======================================================= Application Constants / definitions

        let ITPings_graphable_PingedDevices_Values = "frequency,snr,rssi,channel".split`,`,

            __DEFAULT_MAXROWS = 100,            // maximum number of rows to return in the JSON result for Graphs

            __useLIVE_Data_display_WebComponent = 1,
            __synchronized_pingID_scrolling = false,

            __TEXT_QUERYMANAGER_CANT_REGISTER = " QueryManager can't register",
            __TEXT_REGISTER_FOR_DOPULSE = "register WC for pollServer event",
            __TEXT_NOT_A_VALID__SOURCE = " is not a valid ITpings result source",
            __TEXT_EMPTY_DATABASE = "<h2>Empty Database, check your HTTP Integration setting and reload page</h2>",
            __TEXT_RETREIVING_DB_VALUES = "; retrieving new Database values",

            __TEXT_CUSTOM_ELEMENT_ATTRIBUTES = "CustomElement observedAttributes:",
            __TEXT_CUSTOM_ELEMENT_CONSTRUCTOR = "CustomElement constructor",
            __TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED = "CustomElement attributeChanged:",
            __TEXT_CUSTOM_ELEMENT_CONNECTED = "CustomeElement connectedCallback",

            //MATCHING DATABASE FIELDNAMES
            __APP_TEXT_LASTSEEN = "LastSeen";

//region ========================================================== learning to code without jQuery, Underscore or Lodash
// console log with colors (in Chrome)
        let $_log = (label, logCSS = "lightcoral", a = '', b = '', c = '', d = '', e = '', f = '', g = '', h = '') => {
            console.log(`%c ${label} `, "background:" + logCSS, a, b, c, d, e, f, g, h);
        };

        let $_logEvent = function () { // can NOT use array function, because this scope will be the window, and there are no arguments in arrow function
            let params = Array.from(arguments);
            let label = params.shift();
            label = label.split`.`;
            let css = ";color:white;" + (label[1] ? "background:" + label[1] : "background:green");
            $_log("ITpings %c " + label[0], "lightgreen;padding:0 .5em", css, ...params);//todo: displays svg path for some route icons
        };

        let $_logError = function () {
            $_log("ITpings %c " + label[0], "red;padding:0 .5em", css, ...params);
        };

        //https://developer.mozilla.org/en-US/docs/Web/Events
        ['DOMContentLoaded', 'hashchange', 'load', 'click', 'focus', 'blur'].map(evt => window.addEventListener(evt, () => $_logEvent('Event: ' + evt, event ? event.target : '')));

        // Tiny, recursive autocurry
        const $_curry = (f, arr = []) => (...args) => (a => a.length === f.length ? f(...a) : $_curry(f, a))([...arr, ...args]);
        const $_compose = (...fns) => x => fns.reduceRight((v, f) => f(v), x);
        const $_pipe = (...fns) => x => fns.reduce((y, f) => f(y), x);

        // noinspection CommaExpressionJS
        let $_isDefined = x => typeof x !== "undefined",
            $_isString = x => typeof x === "string",
            $_isNumber = x => typeof x === "number",
            $_isArray = x => Array.isArray(x),

            /** Array **/
            $_ArrayFrom = x => Array.from(x),
            $_lastNelements = (arr, n) => arr.slice(-1 * n),

            __strReverse = x => [...x].reverse().join``,
            $_CSV2Array = x => x.split`,`,
            $_last = array => (array && array.slice(-1)[0]),

            $_newMap = x => new Map(x),
            $_newSet = x => new Set(x),

            /** DOM **/
            $_getElementById = element_id => document.getElementById(element_id),
            $_querySelectorAll = (selector, parent = document) => [...parent.querySelectorAll(selector)],

            $_getAttribute = (element, property) => element.getAttribute(property),
            $_setAttribute = (element, property, value) => element.setAttribute(property, value),
            $_setAttributes = (element, arr) => Object.keys(arr).map((property) => element.setAttribute(property, arr[property])),
            $_removeAttribute = (element, attr) => element.removeAttribute(attr),

            $_createDocumentFragment = () => document.createDocumentFragment(),
            $_createElement = element_type => document.createElement(element_type),
            $_createDIV = (html, element = $_createElement("DIV")) => (html && (element.innerHTML = html), element),
            $_createDIV_withClass = (html, className, element = $_createDIV(html)) => (element.classList.add(className), element),
            $_createDIV_with_id = (html, id, DIV = $_createDIV(html)) => ($_setAttribute(DIV, "id", id), DIV),

            $_innerHTML = (element, html = emptyString) => element && (element.innerHTML = html, element),                 // return element

            $_appendChild = (parent, child) => parent.appendChild(child),
            $_insertBefore = (parent, child, referenceNode) => parent.insertBefore(child, referenceNode),

            $_importNode = (nodeId) => document.importNode($_getElementById(nodeId).content, true),
            $_importTemplate = (templateId, destinationId) => $_getElementById(destinationId).appendChild($_importNode(templateId)),

            $_hideElement = element => element.style.display = 'none',
            $_showElement = (element, displaysetting = 'block') => element.style.display = displaysetting,

            $_classList_add = (element, classStr) => element.classList.add(classStr),
// toggle a className for N elements (selected/unselected)
            $_toggleClass = (element, className) => element.classList.toggle(className),
            $_toggleClasses = (elements, selectedElement, className) => elements.map(x => x.classList[x === selectedElement ? "add" : "remove"](className)),

            $_setCSSproperty = (property, value, el = document.body) => el.style.setProperty(property, value),
            //Chrome CSS Type Object model
            $_setattributeStyleMap = (property, value, el = document.body) => el.attributeStyleMap.set(property, value),


            $_setBackgroundColor = (el, color) => el.style.backgroundColor = color,

            $_Object_keys = x => Object.keys(x),

            $_style_display = (el, value = 'none') => el.style.display = value,

            $_localstorage_Get = (key, defaultvalue = false, stored = localStorage.getItem(key)) => stored ? stored : defaultvalue,
            $_localstorage_Set = (key, value) => {
                try {
                    let arraycount = $_isArray(value) ? value.length : false;
                    if (!$_isString(value)) value = JSON.stringify(value);
                    $_logEvent("localstorageSet.orangered", "store", arraycount ? arraycount + " rows," : "", value.length, "bytes as:", key);
                    return localStorage.setItem(key, value)
                } catch (e) {
                    console.error(e);
                }
            };

// Chart JS says it depends on momentJS, ["method"] references so Uglify does not destroy them

        (function (funcName = "$_ready", baseObj = window) {
                // The public function name defaults to window.docReady
                // but you can modify the last line of this function to pass in a different object or method name
                // if you want to put them in a different namespace and those will be used instead of
                // window.docReady(...)
                let readyList = [];
                let readyFired = false;
                let readyEventHandlersInstalled = false;

                // call this when the document is ready
                // this function protects itself against being called more than once
                function ready() {
                    if (!readyFired) {
                        // this must be set to true before we start calling callbacks
                        readyFired = true;
                        for (let i = 0; i < readyList.length; i++) {
                            // if a callback here happens to add new ready handlers,
                            // the docReady() function will see that it already fired
                            // and will schedule the callback to run right after
                            // this event loop finishes so all handlers will still execute
                            // in order and no new ones will be added to the readyList
                            // while we are processing the list
                            readyList[i].fn.call(window, readyList[i].ctx);
                        }
                        // allow any closures held by these functions to free
                        readyList = [];
                    }
                }

                function readyStateChange() {
                    if (document.readyState === "complete") ready();
                }

                // This is the one public interface: docReady(fn, context);
                // the context argument is optional - if present, it will be passed as an argument to the callback
                baseObj[funcName] = function (callback, context) {
                    if (typeof callback !== "function") throw new TypeError("callback for docReady(fn) must be a function");
                    // if ready has already fired, then just schedule the callback to fire asynchronously, but right away
                    if (readyFired) {
                        setTimeout(function () {
                            callback(context);
                        }, 1);
                        return;
                    } else {
                        // add the function and context to the list
                        readyList.push({fn: callback, ctx: context});
                    }
                    // if document already ready to go, schedule the ready function to run
                    // IE only safe when readyState is "complete", others safe when readyState is "interactive"
                    if (document.readyState === "complete" || (!document.attachEvent && document.readyState === "interactive")) {
                        setTimeout(ready, 1);
                    } else if (!readyEventHandlersInstalled) {
                        // otherwise if we don't have event handlers installed, install them
                        if (document.addEventListener) {
                            // first choice is DOMContentLoaded event
                            document.addEventListener("DOMContentLoaded", ready, false);
                            // backup is window load event
                            window.addEventListener("load", ready, false);
                        } else {
                            // must be IE
                            document.attachEvent("onreadystatechange", readyStateChange);
                            window.attachEvent("onload", ready);
                        }
                        readyEventHandlersInstalled = true;
                    }
                }
            }

        )
        (); // modify this line to pass in your own method name and object for the method to be attached to

        let $_fetch = (uri, cacheKey = false) => {    // Async/Await?
            function _log() {
                $_logEvent("Fetch API.firebrick", ...arguments);
            }

            let shortURI = uri.replace(__localPath(''), '');
            let isSinglePingID = uri.includes('query=' + __DB_PingID_endpoint);
            //if (!isSinglePingID) _log("Fetching from ", cacheKey ? "Cache:" + cacheKey : "Server", "?query=" + shortURI);
            if (!isSinglePingID) _log("Fetching from ", cacheKey ? "Cache:" + cacheKey : "Server", cacheKey ? "" : "\n" + encodeURI(uri));

            return new Promise((resolve, reject) => {                                               // return Promise to calling function
                if (cacheKey) {                                                                     // string
                    let json = JSON.parse($_localstorage_Get(cacheKey, "{}"));                      // get cached data from localstorage
                    if (__hasResultArray(json)) {                                                   // if it has a "result" key
                        _log('Using ', json.result.length, ' results cached data for:', cacheKey);  // it is cached data
                        //_log('first cached row:', json.result[0]);
                        json[__$DEF.ITpings_cached] = new Date();
                        resolve(json);
                    } else {
                        _log('No cache data for:', cacheKey);                                       // no data
                        cacheKey = false;                                                           // continue with fetch in next if block
                    }
                }
                if (!cacheKey) {
                    fetch(uri)                                                                      // async fetch
                        .then(response => {
                            if (response.ok) {
                                return response.json();
                            } else {
                                $_log('$_fetch error ' + response.status + ' ' + response.statusText, "red;color:white", uri, response);
                                return response;
                            }
                        })
                        .then(json => {
                            if (__hasResultArray(json)) {                                           // "result" key in json?
                                _log("Fetched (" + shortURI + ")", json.result.length, "rows , ", JSON.stringify(json).length, 'bytes');
                            } else {
                                // update UI for single ping value
                                if ($_isNumber(json)) $_getElementById('heartbeat_ping').innerHTML = __clickable_pingid(json);
                            }
                            resolve(json)
                        })
                        .catch(error => reject($_log(error, 'red;color:yellow', shortURI)));
                }
            })
        };

//endregion ======================================================= learning to code without jQuery, Underscore or Lodash

//region ========================================================== Application Functions

        let __hasResultArray = json => json && json.hasOwnProperty(__$DEF.ITpings_result);
        let __isCachedJSON = json => json && json.hasOwnProperty(__$DEF.ITpings_cached);
        let __getResultArray = json => json && json[__$DEF.ITpings_result];

        let __clickable_pingid = pingid => `<A target=_blank HREF=ITpings_connector.php?query=ping&_pingid=${pingid}>${pingid}</A><A target=_blank HREF=ITpings_connector.php?query=DeletePingID&_pingid=${pingid}> X </A>`;

        let __localPath = x => {
            let uri = location.href.split('#')[0];          // discard routes
            uri = uri.split`/`;                             // get endpoint from current uri location
            uri.pop();                                      // discard filename
            uri.push("ITpings_connector.php?query=" + x);   // stick on query endpoint
            uri = uri.join`/`;
            return uri;
        };

        let __LoadingTitle = (x, y = "Loading: ", z = emptyString) => `<DIV class="loading">${y} ${x} ${z}</DIV>`;

//endregion ======================================================= Application Functions

//region ========================================================== StyleManager : manage <STYLE> definitions in DOM

// TTN Node names get a distinct color in Tables and Graphs

        class StyleManager {
            // noinspection JSUnusedGlobalSymbols
            static _log() {
                $_log("Router", ...arguments);
            }

            constructor(id, _StyleManager = this) { //cheeky way of omitting let declaration, saving 4 bytes in Uglify
                //let _StyleManager = this;

                // Get a single (existing!!) STYLE definition from DOM  (dynamically added STYLE tags are not available in the .styleSheets Object!)
                // CSSStyleSheet does not have an id
                _StyleManager.STYLE = [...document.styleSheets].find(sheet => sheet.ownerNode["id"] === id);
                _StyleManager.devicesMap = $_newMap(); // _devid -> dev_id
                _StyleManager.deviceColor = $_newMap();
                // 20 Distinct colors: https://sashat.me/2017/01/11/list-of-20-simple-distinct-colors/
                _StyleManager.colors = $_CSV2Array("#e6194b,#0082c8,#f58231,#911eb4,#46f0f0,#f032e6,#d2f53c,#fabebe,#008080,#e6beff,#aa6e28,#fffac8,#800000,#aaffc3,#808000,#ffd8b1,#000080,#808080,#ffe119");
            }

            loadColors_from_localStorage() {
                $_localstorage_Set('StyleColors', this.deviceColor);
            }

            saveColors_to_localStorage() {
                $_localstorage_Get('StyleColors');
            }

            addDevice(device) {
                let _devid = device[__$DEF.ITpings_devid];
                let dev_id = device[__$DEF.dev_id];
                this.devicesMap.set(_devid, dev_id);
                let color = this.getColor(dev_id);
                console.log('%c DeviceColor: ' + _devid + " = " + dev_id, 'background:' + color + ';color:white')
            }

            getColor(dev_id) {  // store distinct color PER device
                let _StyleManager = this,
                    color;
                if (parseInt(dev_id)) dev_id = _StyleManager.devicesMap.get(dev_id);
                if (_StyleManager.deviceColor.has(dev_id)) {
                    color = _StyleManager.deviceColor.get(dev_id);
                } else {
                    color = _StyleManager.colors.shift();
                    _StyleManager.deviceColor.set(dev_id, color);
                    _StyleManager.STYLE.insertRule(`span[data-${__$DEF.dev_id}='${dev_id}']::before{background:${color}}`, 0);
                }
                return color;
            }
        }

//endregion ======================================================= StyleManager

//region ========================================================== QueryManager for all itpings-table & itpings-chart Custom Elements

        // poll backend for a new pingid value
        // lower setting doesn't speed up because the single new pingid triggers a new (slower) request (eg. PingedDevices WHERE pingid GT .. )
        // todo include the whole record, not just the pingid
        let __heartbeat_default = 500;

        let __heartbeat_blurred = 1e3 * 60 * 30;
        let heartbeat_msecs; // number of milliseconds polling the back-end for new data

        let __setHeartbeat = new_heartbeat_msecs => {
            let interval;
            heartbeat_msecs = new_heartbeat_msecs;    // global
            $_log("Change Heartbeat:", "orange;color:black", heartbeat_msecs);
            $_innerHTML($_getElementById('heartbeat'), heartbeat_msecs);
            if ($_isDefined(__QueryManager)) {
                window.clearInterval(interval);
                interval = window.setInterval(() => {
                    //$_log("Heartbeat:", "orange;color:black", heartbeat_msecs);
                    document.getElementById('heartbeat_heart').classList.toggle('heartbeating');
                    $_innerHTML($_getElementById('heartbeat_time'), $_dateStr(new Date()));
                    __QueryManager.pollServer(__DB_PingID_endpoint);
                }, heartbeat_msecs);
            } else {
                console.error("No __QueryManager");
            }
        };

        /**
         * It would be loads of XHR request when every Table and Chart polled the backend for new data (most of the time for no new data)
         * So, on display, Tables and Charts register themselves with the QueryManager (IQM) (by Query and PrimaryKey they use)
         * The IQM polls the Server MySQL backend for new (Primary) Key values
         * If there are new values the IQM tells the Component (Tables and Charts) to .pollServer and (they) retrieve the new Rows from the database themselves
         * This XHR short-polling method works fine for your single client Dashboard, every poll is only 256 Bytes (per second)
         * In a modern web-world this can be done with (more complex) WebSockets
         * **/

        class ITpings_Query_Manager {
            static _log() {
                $_logEvent("QueryManager.purple", ...arguments);
            }

            constructor(_QM = this) {
                _QM.maxid = 0;                          // record MAX(_pingid), higher value will cause all registered tables/graphs to update
                _QM["pulse"] = $_newMap();  // store all tables/graphs query endpoints AND PrimaryKey fields here
            }

            register_for_pollServer(WC) {// register a new query

                /**
                 * For each WebComponent, register which endpoint and db fieldname to poll
                 *
                 * see ?query=IDs endpoint
                 * **/

                    // let example_refresh: {
                    //     "applications": {
                    //         "_appid": "1"
                    //     },
                    //     "devices": {
                    //         "_devid": "2"
                    //     },
                    //     "events": {
                    //         "_pingid": "5417"
                    //     },
                    //     "gateways": {
                    //         "_gtwid": "2"
                    //     },
                    //     "locations": {
                    //         "_locid": "3"
                    //     },
                    //     "pings": {
                    //         "_pingid": "5529"
                    //     },
                    //     "sensors": {
                    //         "_sensorid": "14"
                    //     }
                    // };

                    //get 'pulse' dataattribute, record tablename and idfield / value, reference DOM element
                let _QM = this,
                    datasrc,
                    idfield,
                    setting,
                    _IQMap,
                    datasrcMap;

                setting = $_getAttribute(WC, "pulse"); // eg: pulse="SensorValues:_pingid"
                if (setting) {
                    /**
                     * These CAN be configured as data arribute on the itpings-table tag
                     * <itpings-table query="PingedDevices" pulse="PingedDevices:_pingid">
                     *
                     * **/
                    // setting = setting.split`:`;
                    // datasrc = setting[0];
                    // idfield = setting[1];
                    [datasrc, idfield] = setting.split`:`;                          // ES6 Destructuring

                } else {                                                            // determine from query="..."
                    /**
                     * Easier to (auto configure) query="xxx" name and the FIRST column name in the retrieved table
                     * <itpings-table query="PingedDevices">
                     *
                     * **/
                    datasrc = WC.query || $_getAttribute(WC, "query");
                    idfield = WC.idfield || __$DEF.ID;                 // FIRST column in itpings-table
                }
                if (!datasrc) console.error(__TEXT_QUERYMANAGER_CANT_REGISTER, WC);
                _IQMap = _QM["pulse"];

                if (!_IQMap.has(datasrc)) _IQMap.set(datasrc, $_newMap());          // every datasrc gets its own Map (so 'can' store muliple PrimaryKeys
                datasrcMap = _IQMap.get(datasrc);                                   // Sorry... looking back I should have simplified this
                if (!datasrcMap.has(idfield)) datasrcMap.set(idfield, $_newSet());  // I thought, too soon, about multiple dashboards and hundreds of devices
                datasrcMap.get(idfield).add(WC);
                ITpings_Query_Manager._log(__TEXT_REGISTER_FOR_DOPULSE, "datasrc:" + datasrc, "idfield:" + idfield, _IQMap.get(datasrc));
            }

            /**
             * Get maximum ID values from Database and notify/pulse the (registered) Custom Elements in the page
             */
            pollServer(endpoint) {
                let _QM = this;
                let newPoll = (endpoint, milliseconds = heartbeat_msecs) => window.setTimeout(() => _QM.pollServer(endpoint), milliseconds);

                let poll_Registered_Elements = json => {
                    this["pulse"].forEach((datasrcMap, datasrc) => {
                        //ITpings_Query_Manager._log(datasrcMap, datasrc);
                        datasrcMap.forEach((fieldSet, idfield) => {
                            //ITpings_Query_Manager._log(fieldSet, idfield);
                            fieldSet.forEach(ITpings_element => {
                                let idvalue = json["maxids"]["pings"]["_pingid"]; //todo remove hardcoded _pingid
                                ITpings_Query_Manager._log(datasrc + ".pollServer(" + idvalue + ")");
                                ITpings_element.pollServer(idvalue);// method defined on CustomElement !!!
                            });
                        })
                    })
                };

                let loopAllJSON_IDs = () => {
                    let maxids = json["maxids"]; // declare all variable up-front for better minification
                    let setting;
                    let idfield;
                    let idvalue;
                    let datasrcMap;
                    let fieldSet;
                    $_Object_keys(maxids).map(datasrc => {
                        // ** See above, I developed this way too complex, thinking in multiple Dashboards and a shitload of Devices
                        // ** But this works: Read maxids JSON structure from DBInfo (these are just ID values, NOT the new Data!)
                        // ** walk over JSON structure, match it with the registered tables/graphs
                        // ** and contact/pulse every registered ITpings Custom Element
                        // ** The Custom Element itself will check if it needs to call the MySQL DB itself to get the actual data
                        setting = maxids[datasrc];
                        //idfield = $_Object_keys(setting)[0];
                        [idfield] = $_Object_keys(setting);         // ES6 Destructuring
                        idvalue = setting[idfield];
                        datasrcMap = _QM["pulse"].get(datasrc);
                        if (datasrcMap) {
                            ITpings_Query_Manager._log(21, datasrc, maxids[datasrc]);
                            fieldSet = datasrcMap.get(idfield);
                            if (fieldSet) {
                                fieldSet.forEach(ITpings_element => {
                                    ITpings_Query_Manager._log('say pollServer to', ITpings_element);
                                    ITpings_element.pollServer(idvalue);
                                });
                            } else {
                                ITpings_Query_Manager._log("No fieldSet", datasrc, idfield, idvalue, datasrcMap, fieldSet);
                            }
                        } else {
                            ITpings_Query_Manager._log('no datasrcMap for:', datasrc);
                        }
                    });
                    newPoll(__DB_PingID_endpoint);
                };

                $_fetch(__localPath(endpoint))
                    .then(json => {
                        if (endpoint === __DB_PingID_endpoint) {    // single value max(_pingid)
                            if (json > _QM.maxid) {
                                _QM.maxid = json;                   // if it is higher
                                //newPoll(__DB_IDs_endpoint, 1);      // 1 millisecond = call immediatly for all ID values
                                _QM.pollServer(__DB_IDs_endpoint);
                            } else {
                                //newPoll(__DB_PingID_endpoint);
                            }
                        } else {
                            ITpings_Query_Manager._log("heartbeat:" + heartbeat_msecs, "Got recent ID values from Database. _pingid=", json.maxids.pings._pingid, json);

                            /**
                             * New Temperature/Light graphing does not have matching maxids value in the JSON response
                             * So refactored the code to (better) Loop all registered elements (then assuming everyone uses _pingid)
                             * **/
                            //loopAllJSON_IDs(json);
                            poll_Registered_Elements(json);

                        }
                    }).catch(e => console.error(e));
            }
        }//class ITpings_Query_Manager

//endregion ======================================================= QueryManager for all itpings-table & itpings-chart Custom Elements

        let $WC_log = function () { // can not use array function, because this scope will be the window
            $_logEvent("WebComponent.dodgerblue", ...arguments);
        };

        let $WC_saveCachedData_To_localstorage = (WC, rowCount = 9999) => {
            if (!WC.hasAttribute(["nocache"]) && WC.rows && WC.rows.length > 0) {
                WC.rows = $_lastNelements(WC.rows, rowCount);
                $WC_log('$WC_saveCachedData_To_localstorage: Save', WC.rows.length, "rows as key:" + WC._WebComponent_ID);
                $_localstorage_Set(WC._WebComponent_ID, {//todo catch error when localStorage is full (save less rows)
                    [__$DEF.ITpings_result]: WC.rows
                });
            }
        };

        (function (elementName = __custom_element_prefix + "table") {
                let _traceCustomElement = true; // for educational purposes, trace specific CustomElement operations to the console

                window.customElements.define(elementName, class extends HTMLElement {
                    _log() {
                        $_log(elementName + ":" + (this._WebComponent_ID || 'INIT'), __colors_blue[0], ...arguments);
                    };

                    // noinspection JSUnusedGlobalSymbols
                    static get observedAttributes() {
                        let attributes = ["query"];          // data attributes (changes) this Custom Element listens to
                        //constructor has not run yet, so this scope is not available
                        //this._log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTES, _observedAttributes);
                        return attributes;
                    }

                    get title() {
                        return $_getAttribute(this, "title");
                    }

                    set title(newValue) {
                        $_setAttribute(this, "title", newValue);
                    }

                    // noinspection JSUnusedGlobalSymbols
                    constructor() {
                        super();
                        let WC = this;
                        WC._WebComponent_ID = $_getAttribute(WC, "query");
                        if (_traceCustomElement) this._log(__TEXT_CUSTOM_ELEMENT_CONSTRUCTOR, WC);
                        WC.maxid = 1;         // maximum pingid/devid/appid etc. this itpings-table has displayed
                        WC.rows = [];
                    }

                    setTitle(txt = emptyString) {
                        let WC = this;
                        if (!WC.idle) txt = __LoadingTitle(txt);
                        if (txt === emptyString) txt = WC["title"] || WC["query"];
                        $_innerHTML(WC.CAPTION, txt);
                    }

                    fetchTableData(filter = emptyString, localStorage_cachedData = false) {
                        // ** The Custom Element connectedCallback function (below) has already executed
                        // ** Initialization for the TABLE HTML struture is done there

                        let WC = this; // WC = WebComponent (aka CustomElement) makes for clear understanding what 'this' is; AND it minifies better :-)
                        WC.requiredColumns = $_newSet();
                        this.setTitle();

                        let addCacheRows = (rows) => {
                            if (rows.length > 1 && rows[0][WC.idfield] < rows[1][WC.idfield]) rows = rows.reverse();
                            WC.rows = [...rows, ...WC.rows];
                        };
                        /**
                         * if called as (headers) function idx is not defined, thus gets the THEAD reference
                         * if called as an Iterable, idx is the (array) index value
                         * **/
                        let add_TableRow = (row, idx = __$DEF.THEAD, currentArray) => {                       // abusing ES6 default value to check if the func is called to draw the THEAD
                            let isTBODY = idx !== __$DEF.THEAD;
                            // let insertAtTop = WC.rows[0] && row[WC.idfield] > WC.rows[0][WC.idfield];
                            // //this._log('idx', idx, row[WC.idfield], insertAtTop ? '@Top' : '@Bottom');
                            // if (insertAtTop) idx = 0;
                            let TR = (isTBODY ? WC.TBODY : WC.THEAD).insertRow(idx);            // add TR at bottom of THEAD _OR_ bottom/top TBODY  (abusing idx)

                            let value;  // column value or header title
                            let TD;     // newly inserted TD Cell

                            let add_TableColumns_for_One_Row = name => {
                                value = isTBODY ? row[name] : name;                             // add Header Name _OR_ Cell Value

                                TD = TR.insertCell();                                           // add TD cell
                                $_setAttribute(TR, "data-" + name, value);                      // plenty of attributes on the TR also so we can apply CSS
                                if (WC.hasCachedData) $_setAttribute(TR, "data-cached", "true");
                                $_setAttribute(TD, "data-column", name);
                                $_setAttribute(TD, "data-" + name, value);
                                $_classList_add(TD, ($_dateMinutesSince(row[__$DEF.ITpings_time]) > 1 ? "Historic" : "New") + "Cell");                             // color cell, and fade out

                                // only display a column if there are non-standard values
                                let checkRequiredColumn = (columnName, standardValue) => {
                                    if (isTBODY && name === columnName && value !== standardValue) WC.requiredColumns.add(name);
                                };
                                checkRequiredColumn(__$DEF.modulation, "LORA");                // display Column only if there are non-standard values
                                checkRequiredColumn(__$DEF.coding_rate, "4/5");
                                checkRequiredColumn(__$DEF.data_rate, "SF7BW125");

                                if (isTBODY && name === __APP_TEXT_LASTSEEN) value = $_dateMinutesSince(value);

                                if (isTBODY && name === __$DEF.dev_id) value = `<SPAN data-column="${name}" data-${name}="${value}">${value}</SPAN>`;

                                if (isTBODY && name === WC.idfield) {
                                    if (~~value > WC.maxid) WC.maxid = ~~value;

                                    if (name = __$DEF.ID) value = __clickable_pingid(value);

                                    // ** Only execute once PER Row/Table
                                    // ** mouseover a pingid and all other tables scroll to the same pingid
                                    if (name === __$DEF.ID && __synchronized_pingID_scrolling) {
                                        $_setAttribute(WC.TBODY, "data-" + name, value);
                                        TR.addEventListener("mouseenter", () => {
                                            let _pingid = $_getAttribute(TR, "data-_pingid");                          // get pingid for this row
                                            let selector = "itpings-table .data-table TR[data-_pingid='" + _pingid + "']";   // find TBODY with this pingid
                                            let TRs = $_querySelectorAll(selector);
                                            TR.backtotop = false;
                                            if (TRs) {
                                                this._log("mouseenter", _pingid, TRs, TRs.length);
                                                TRs.map(TRwithPingID => {
                                                    if (TRwithPingID !== TR) {
                                                        TRwithPingID.scrollIntoView({
                                                            "block": "center", "inline": "nearest"
                                                        });
                                                        //TR.parentNode.parentNode.style.paddingTop = "2em";
                                                    }
                                                    $_setBackgroundColor(TRwithPingID, "chartreuse");
                                                });
                                            }
                                            if (!TR.hasMouseLeaveListener) {
                                                TR.addEventListener("mouseleave", () => {
                                                    TRs.map(TRwithPingID => {
                                                        if (TRwithPingID !== TR) {
                                                            //TRwithPingID.scrollIntoView();
                                                            //TR.parentNode.parentNode.style.paddingTop = "initial";
                                                        }
                                                        $_setBackgroundColor(TRwithPingID, "initial");
                                                        window.setTimeout(() => {
                                                            //                       TRwithPingID.parentNode.parentNode.parentNode.scrollTo(0, 0);
                                                        }, 200);
                                                    });
                                                });
                                                TR.hasMouseLeaveListener = true;
                                            }
                                        });
                                    }

                                }

                                $_innerHTML(TD, value);

                                return {name, value, TR, TD}
                            };

                            return $_Object_keys(row).map(add_TableColumns_for_One_Row);
                        };
                        this._log('fetchTableData', localStorage_cachedData ? "cache:" + localStorage_cachedData : "from Server");
                        if (WC.idle) {                                                         // not waiting for a JSON response already
                            WC.idle = false;                                                   // true again AFTER fetch processing is done
                            $_fetch(WC.uri + filter, localStorage_cachedData)
                                .then(json => {
                                    WC.hasCachedData = __isCachedJSON(json);
                                    let rows = __getResultArray(json);
                                    try {
                                        if (rows) {
                                            if (localStorage_cachedData === false && WC.hasCachedData) this._log('add ', rows.length, 'new rows from Database', rows && rows[0]);

                                            let headers, first_column_name, dataNewRows;
                                            this.setTitle(WC["title"] + "; processing data");

                                            if (!WC.idfield && rows.length === 0) {
                                                $_innerHTML(WC, `<div class='nodata'>No data: ${$_getAttribute(WC, "query")}</div>`);
                                                WC.idle = true;                                        // processed all rows
                                            } else {
                                                if (!WC.idfield) {                                      // first draw of TABLE

                                                    // ** ES6 Destructuring, set headers and first_column_name VARiables
                                                    //headers = rows[0];                                // ES5 style get first row
                                                    [headers] = rows;                                   // ES6 Destructuring, rows is an array, headers becomes first element
                                                    //first_column_name = $_Object_keys(headers)[0];    // ES5 style get first column key/name
                                                    [first_column_name] = $_Object_keys(headers);       // ES6 Destructuring
                                                    // can NOT do this ES6 desctructuring, because headers is only available AFTER the execution
                                                    // [[headers], [first_column_name]] = [rows, $_Object_keys(headers)];

                                                    if (first_column_name[0] !== "_") console.error(first_column_name, "might not be a correct Key fieldname return from DBInfo endpoint");
                                                    WC.idfield = first_column_name;                     // take from attribute _OR_ first JSON row
                                                    add_TableRow(headers);                              // first row keys are the THEAD columnheaders

                                                    WC.columns = $_newSet(rows.forEach(add_TableRow));   // add all rows, register columns
                                                    addCacheRows(rows);

//                                        $_appendChild(WC.HEADER, WC.THEAD.cloneNode(true));

                                                    $_innerHTML(WC);                                    // remove Loading...
                                                    $_appendChild(WC, WC.WRAPDIV);                      // now append that Custom Element to the DOM
                                                    __QueryManager.register_for_pollServer(WC);         // Register my -table query with the Query Manager, so I get (pollServer) updates

                                                } else if (rows.length) {                               // add new rows in a new TBODY at the top of the TABLE
                                                    // append new TBODY, overriding privious WC.TBODY reference
                                                    WC.TBODY = $_insertBefore(WC.TABLE, $_appendChild(WC.TABLE, $_createElement(__$DEF.TBODY)), WC.TBODY);
                                                    $_classList_add(WC.TBODY, "newPing");               // animate background color of this newPing

                                                    dataNewRows = rows.map(add_TableRow);               // add rows at top of TBODY
                                                    this._log(WC.query, dataNewRows.length, 'rows added to table');

                                                    addCacheRows(rows);
                                                } else {
                                                    console.warn("empty result set from:", WC.uri);
                                                }
                                                $WC_saveCachedData_To_localstorage(WC, __cacheRowCount);
                                                WC.idle = true;                                        // processed all rows
                                                this.setTitle(WC["title"] || WC["query"]);
                                            }


                                        } else {
                                            console.error(json);
                                            msg = json.statusText;
                                            if (json.status === 500) msg = json.url + "Script exceeded PHP execution time, make the query faster or reduce the amount of data in the MySQL database";
                                            $_appendChild(WC,
                                                $_createDIV_withClass(
                                                    `${json.status} ${msg}`
                                                    , "itpings-table-error"
                                                )
                                            );
                                        }
                                    } catch (e) {
                                        console.error(e);
                                    }

                                    //make sure required columns are always displayed
                                    WC.requiredColumns.forEach(
                                        columnName => $_setCSSproperty(
                                            "--CSSdisplay_" + columnName
                                            , "table-cell"
                                            , WC.TABLE) // set on this itpings-table (versus document.body) so the column is only displayed in this table
                                    );

                                    if (__isCachedJSON(json)) {
                                        this._log("Cached end", WC.maxid);
                                        WC.hasCachedData = false;
                                    }

                                })
                                .catch(e => {
                                    console.error(e);
                                });
                        }
                    }

                    // noinspection JSUnusedGlobalSymbols
                    pollServer(pingID) {                                            // read Database endpoint, only when there are new ids
                        let WC = this;
                        let maxid = WC.maxid;
                        let idfield = WC.idfield;
                        if (pingID > maxid) {
                            this._log('pollServer from element:', elementName, ", idfield:" + idfield, ", maxid:" + maxid, ", _pingid:" + pingID);
                            this.fetchTableData("&filter=" + idfield + " gt " + maxid + "&limit=" + (pingID - maxid)); // add filter on uri to get only new values
                        }
                    }

                    // noinspection JSUnusedGlobalSymbols
                    attributeChangedCallback(attr, oldValue, newValue) {
                        // ** called for every change for (ObservedDataAttributes only) Data-attributes on the HTML tag
                        // ** So also at first creation in the DOM
                        let WC = this;
                        let isConnected = WC.isConnected;                                     // sparsely documented standard property
                        if (attr === "query") {
                            if (!__useLIVE_Data_display_WebComponent) return void($_innerHTML(WC, newValue));

                            WC.uri = __localPath(newValue);
                            if (_traceCustomElement) this._log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED, ", attr:" + attr, ", oldValue:" + oldValue, ", newValue" + newValue, isConnected ? emptyString : "►► NOT", "Connected");
                            WC.idfield = false;
                            WC.idle = true;                                                                    // no new fetch when still waiting or previous one
                            if (WC.WRAPDIV) WC.WRAPDIV.parentNode.removeChild(WC.WRAPDIV);    // remove existing table

                            /**
                             * Create HTML structure:
                             *  DIV .table-wrapper
                             *      CAPTION (title)
                             *      TABLE .sticky-header
                             *          THEAD
                             *      TABLE
                             *          TBODY (newest) (injected by fetchChartData)
                             *          TBODY (newer)  (injected by fetchChartData)
                             *          TBODY (older)  (created below)
                             * **/
                            let ITPINGS_DIV = WC.WRAPDIV = $_createDIV_withClass(emptyString, "table-wrapper");

                            WC.HEADER = $_appendChild(ITPINGS_DIV, $_createElement(__$DEF.TABLE));                   // new TABLE inside DIV with WC.TABLE reference
                            $_classList_add(WC.HEADER, "sticky-header");

                            WC.CAPTION = $_appendChild(WC.HEADER, $_createElement("CAPTION"));                    // CAPTION tag inside TABLE
                            $_classList_add(WC.CAPTION, "itpings-div-title");                                     // sticky position

                            let title = WC["title"] || WC["query"];
                            $_innerHTML(WC.CAPTION, title);
                            $_innerHTML(WC, __LoadingTitle(title));

                            WC.THEAD = $_appendChild(WC.HEADER, $_createElement(__$DEF.THEAD));                     // add THEAD, references are used to fill data

                            WC.TABLE = $_appendChild(ITPINGS_DIV, $_createElement(__$DEF.TABLE));                   // new TABLE inside DIV with WC.TABLE reference
                            $_classList_add(WC.TABLE, "data-table");
                            // no THEAD in data-table, the Header row is in the HEADER Table
                            WC.TBODY = $_appendChild(WC.TABLE, $_createElement(__$DEF.TBODY));                     // add (first) TBODY

                            /**
                             * There is no data in the <TABLE> yet, data arrives in the async fetch Data call
                             * So the Custom Element HTML above will be injected into the DOM in that fetch Data call **/

                            let cachedDataKey = WC.hasAttribute(["nocache"]) ? false : WC._WebComponent_ID;
                            this.fetchTableData(emptyString, cachedDataKey);  // call ONCE with an empty filter value
                        }
                    }

                    // noinspection JSUnusedGlobalSymbols
                    connectedCallback() {
                        if (_traceCustomElement) this._log(__TEXT_CUSTOM_ELEMENT_CONNECTED); // ** Called before Custom Element is added to the DOM
                    }
                });
            }

        )()
        ; // function (elementName = "itpings-table")


        (

            function (elementName = __custom_element_prefix + "chart") {
                let _traceCustomElement = true; // for educational purposes, trace specific CustomElement operations to the console

                let __INTERVALS = $_newMap();
                // ES6 Destructuring, parameter names become keys: {interval:interval, unit:unit, xformat:xformat}
                let addInterval = (key, interval, maxrows, unit, xformat) => __INTERVALS.set(key, {
                    interval,
                    maxrows,
                    unit,
                    xformat
                });
                addInterval("5m", 5, 20, __$DEF.MINUTE, $_dateFormat_Hmm);
                addInterval("30m", 30, 60, __$DEF.MINUTE, $_dateFormat_Hmm);
                addInterval("1H", 1, 120, __$DEF.HOUR, $_dateFormat_Hmm);
                addInterval("2H", 2, 240, __$DEF.HOUR, $_dateFormat_Hmm);
                addInterval("6H", 6, 380, __$DEF.HOUR, $_dateFormat_Hmm);
                addInterval("1D", 1, 380, __$DEF.DAY, $_dateFormat_Hmm);
                addInterval("2D", 2, 380, __$DEF.DAY, $_dateFormat_DMMMHmm);
                addInterval("7D", 7, 380, __$DEF.DAY, $_dateFormat_DMMMHmm);
                addInterval("2W", 2, 500, __$DEF.WEEK, $_dateFormat_DMMMHmm);
                addInterval("1M", 1, 4000, __$DEF.MONTH, $_dateFormat_DMMM);
                addInterval("6M", 6, 1000, __$DEF.MONTH, $_dateFormat_DMMM);
//            addInterval("1Y", 1, __$DEF.YEAR, $_dateFormat_DMMM);

                //let __INTERVAL_DEFAULT = __INTERVALS.get("6H");
                let __INTERVAL_DEFAULT = "6H";

                window.customElements.define(elementName, class extends HTMLElement {
                    _log() {
                        $_log(elementName + ":" + (this._WebComponent_ID || 'INIT'), __colors_blue[1], ...arguments);
                    };

//region ========================================================== Custom Element Getters/Setters
                    // noinspection JSUnusedGlobalSymbols
                    static get observedAttributes() {
                        let _observedAttributes = [__$DEF.sensor, "interval"];
                        //constructor has not run yet, so this scope is not available
                        //this._log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTES, _observedAttributes);
                        return _observedAttributes;
                    }

                    get title() {
                        return $_getAttribute(this, "title");
                    }

                    set title(newValue) {
                        $_setAttribute(this, "title", newValue);
                    }

                    get sensor() {
                        return $_getAttribute(this, __$DEF.sensor);
                    }

                    set sensor(newValue) {
                        $_setAttribute(this, __$DEF.sensor, newValue);
                    }

                    get interval() {
                        return $_getAttribute(this, "interval");
                    }

                    set interval(newValue) {
                        let WC = this;
                        $_setAttribute(WC, "interval", newValue);
                        this._log("(setter)", "interval=", "(" + (typeof newValue) + ")", newValue);
                        WC.isMouseClick = event && event.type === 'click';
                        let sensor = WC.sensor;
                        let intervalDefinition = WC.__INTERVAL = __INTERVALS.has(WC.interval) ? __INTERVALS.get(WC.interval) : __INTERVAL_DEFAULT;

                        WC.idle = false;    // now busy getting data to be graphed

                        $_toggleClasses([...this.INTERVALS.children]
                            , WC.INTERVALS.querySelector(`[id="${newValue}"]`)
                            , "selectedInterval"); //loop all interval DIVs , add or remove Class: selectedInterval

                        //todo use faster SensorValues_Update query   let sensor_ids = (sensor === "temperature_5") ? "7,14" : "6,13";
                        if (ITPings_graphable_PingedDevices_Values.includes(sensor)) {
                            WC.query = "PingedDevices";
                            WC.uri = __localPath(WC.query);
                            WC.value_field_name = sensor;
                            WC.deviceid_field_name = __$DEF.dev_id;
                        } else {
                            if (['Temperature'].includes(sensor)) {
                                WC.query = sensor;
                                WC.uri = __localPath(sensor);
                                WC.value_field_name = "value";
                                WC.deviceid_field_name = __$DEF.ITpings_devid;
                            } else {
                                WC.query = "SensorValues";
                                WC.uri = __localPath(`${WC.query}&sensorname=${sensor}`);
                                WC.value_field_name = "sensorvalue";
                                WC.deviceid_field_name = __$DEF.dev_id;
                            }
                        }
                        WC.uri += `&orderby=created&interval=${intervalDefinition.interval}`;
                        WC.uri += `&intervalunit=${intervalDefinition.unit}&limit=none&maxrows=${intervalDefinition.maxrows}`;

                        WC._WebComponent_ID = (WC.title || WC.query) + "_" + newValue;
                        $_localstorage_Set(WC.localStorageKey, newValue);

                        // this.fetchChartData(emptyString, isMouseClick ? false : WC._WebComponent_ID);       // do not use cache when interval is reset by mouse click
                        this.fetchChartData(emptyString, WC._WebComponent_ID);       // do not use cache when interval is reset by mouse click
                    }//set interval

                    //endregion

                    initChartJS() {
                        let WC = this;
                        WC.ChartJS_Lines = [];                              // Array, index number is used to register devices

                        this._log(WC.ChartJS ? "Chart.js.destroy()" : "new ChartJS()");
                        if (WC.ChartJS) WC.ChartJS["destroy"]();            // Uglify Mangle protection
                        WC.ChartJS = new Chart(WC.CANVAS, {// todo? replace with D3
                            "type": "line",
                            "data": {
                                "labels": [],
                                "datasets": []
                            },
                            "options": {
                                "maintainAspectRatio": false,
                                "title": {
                                    "display": false,
                                    "text": emptyString
                                },
                                "tooltips": {
                                    "mode": "index",
                                    "intersect": false
                                },
                                "hover": {
                                    "mode": "nearest",
                                    "intersect": true,
                                },
                                "legend": false,
                                "showLines": true,
                                "elements": {
                                    "line": {
                                        "tension": 0
                                    }

                                },
                                // scales: {
                                //     xAxes: [{
                                //         display: false,
                                //         scaleLabel: {
                                //             display: false,
                                //             labelString: "Month"
                                //         }
                                //     }],
                                //     yAxes: [{
                                //         display: true,
                                //         scaleLabel: {
                                //             display: true,
                                //             labelString: sensor
                                //         },
                                //     }]
                                // }
                            }
                        });
                    }

                    drawChartJS(rows = false) {
                        let WC = this;
                        rows = rows || WC.rows;
                        WC._log('has', WC.rows.length, 'rows. Draw ', rows.length, 'new rows with ChartJS');
                        let ChartJS_datasets = WC.ChartJS["data"]["datasets"];
                        let ChartJS_labels = WC.ChartJS["data"]["labels"];
                        rows.forEach((row, index) => {
                            let x_time = $_dateStr(row[__$DEF.created], WC.__INTERVAL.xformat);         // format x-axis label with timestamp
                            if (!ChartJS_labels.includes(x_time)) {                                     // prevent duplicate timelables on x-axis
                                let lineID = row[WC.deviceid_field_name];                               // one graphed line per device
                                let dataset_idx = WC.ChartJS_Lines.indexOf(lineID);                     // find existing device
                                if (dataset_idx < 0) {                                                  // ** add new device
                                    dataset_idx = ChartJS_datasets.length;
                                    let deviceColor = DeviceColors.getColor(lineID);                    // get distinct color from Map
                                    console.log(`%c newDevice: (${WC.deviceid_field_name}) = ${lineID}  `, "color:white;background:" + deviceColor);
                                    ChartJS_datasets.push({                                             // add one new line/device data to ChartJS
                                        "label": lineID
                                        , "fill": false
                                        //, "hidden": true
                                        //, "lineTension": .5
                                        , "backgroundColor": deviceColor, "borderColor": deviceColor
                                        , "data": []
                                    });
                                    WC.ChartJS_Lines.push(lineID);                                      // unique sensorid per device
                                }
                                let use_datapoint = index === 0 || $_lastNelements(x_time, 1) === "0";
                                //use_datapoint = true;
                                if (use_datapoint) {
                                    ChartJS_labels.push(x_time);
                                    ChartJS_datasets[dataset_idx]["data"].push({
                                        "x": x_time,
                                        "y": row[WC.value_field_name]
                                    });
                                }
                            }
                        });
                        WC.ChartJS.update();
                    }

                    setTitle(txt = emptyString) {
                        let WC = this;
                        txt = (WC.title || WC.query) + txt;
                        if (!WC.idle) txt = __LoadingTitle(txt);
                        $_innerHTML(WC.CAPTION, txt);
                    }

                    fetchChartData(filter = emptyString, localStorage_cachedData = false) {
                        let WC = this;
                        WC.idle = false;
                        if (this.isMouseClick || WC.rows.length === 0) {                            // only when manual Interval selection OR first init
                            this.setTitle(__TEXT_RETREIVING_DB_VALUES);                             // set title to busy indicator
                            $_style_display(WC.INTERVALS);// style.display='none';                  // hide Intervals
                        }
                        $_fetch(WC.uri + filter, localStorage_cachedData)
                            .then(json => {
                                let rows = __getResultArray(json);
                                try {
                                    let _lastID = array => ($_last(array)[WC.idfield]);             // get (highest) id field (from last row)
                                    let interval = WC.__INTERVAL;
                                    this._log(" has", WC.rows.length, "rows (max:" + interval.maxrows + "), now got", rows.length, WC.hasCachedData ? 'Cached!' : 'New', "rows", filter ? "from filter:" + filter : "");
                                    if (WC.hasCachedData && _lastID(rows) === _lastID(WC.rows)) {   // do not redraw chart, cached data is the same
                                        this._log("new data is same as cached data!");
                                        WC.hasCachedData = false;
                                        WC.idle = true;
                                        this.setTitle();
                                    } else {
                                        this.setTitle("; processing data");
                                        if (WC.rows.length === 0) {                                 // first init
                                            __QueryManager.register_for_pollServer(WC);
                                            this.initChartJS();
                                        }
                                        if (WC.hasCachedData) WC.resetCache = true;                 // order: display cached data > get newest data > clear Chart > redisplay new data

                                        WC.hasCachedData = __isCachedJSON(json);

                                        if (rows[0] === null) {
                                            console.error("WTF! Why did we get no rows from the database?");
                                            __setHeartbeat(__heartbeat_blurred);                    // give developer time to analyze console
                                        } else {
                                            if (WC.isMouseClick || WC.resetCache) {                 // re-init with Interval data read from Database
                                                this.initChartJS();
                                                WC.rows = [];
                                            }
                                            rows.map(row => {                                       // process (new) rows
                                                WC[__$DEF.ID] = row[__$DEF.ID];                     // keep track of hightest Primary Key value (presuming always getting a higher pingid)
                                                WC.rows.push(row);
                                            });
                                            WC.rows = $_lastNelements(WC.rows, interval.maxrows);   // keep most recent rows, defined by current interval
                                            this.drawChartJS(WC.hasCachedData ? false : rows);      // draw WC.rows or New rows
                                        }

                                        WC.idle = true;                                             // no longer waiting for database fetch
                                        $_style_display(WC.INTERVALS, 'initial');                   // display Intervals again
                                        this.setTitle();                                            // reset title to query/sensor name

                                        if (WC.hasCachedData) {                                     // if this data came from cache
                                            this.resetCache = true;                                 // then clear the graph cached data
                                            this.fetchChartData(filter, false);                     // AFTER fetching all Interval data
                                        } else {
                                            $WC_saveCachedData_To_localstorage(WC, interval.maxrows);   // if data came from DB, then cache new data
                                        }
                                        WC.resetCache = false;
                                        WC.isMouseClick = false;
                                    }
                                } catch (e) {
                                    console.error('fetchChartData', e);
//                                console.trace();
                                }
                            });
                    }

                    // noinspection JSUnusedGlobalSymbols
                    pollServer(pingID) {
                        let WC = this;
                        let current_ping_id = ~~WC[__$DEF.ID];
                        this._log('pingID:', pingID, 'current_ping_id', WC[__$DEF.ID], WC.idle ? 'idle' : 'waiting for data');
                        if (current_ping_id && WC.idle) {
                            if (current_ping_id < pingID) {
                                this._log("pollServer Chart JS current_ping_id:", current_ping_id, "new:", pingID);
                                this.fetchChartData(`&filter=${__$DEF.ID}%20gt%20${current_ping_id}`);
                            }
                        } else {
                            this._log("►►► No pingid on Chart JS yet (not drawn yet) ◄◄◄");
                        }
                    }

                    // noinspection JSUnusedGlobalSymbols
                    constructor() {
                        super();
                        this.rows = []; // cache data
                        this.resetCache = false;
                    }

                    // noinspection JSUnusedGlobalSymbols
                    attributeChangedCallback(attr, oldValue, newValue) {
                        let WC = this;
                        let isConnected = WC.isConnected;
                        switch (attr) {
                            case("interval"):
                                if (isConnected) {
                                    if (_traceCustomElement) this._log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED, ", attr:" + attr, ", oldValue:" + oldValue, ", newValue" + newValue, isConnected ? emptyString : "►► NOT", "Connected");

                                    /** interval updates are done with the Setter method!! **/

                                }
                                break;
                            default:
                                break;
                        }
                    }

                    // noinspection JSUnusedGlobalSymbols
                    connectedCallback() {
                        let WC = this;
                        let sensor = WC.sensor;
                        if (!__useLIVE_Data_display_WebComponent) return void($_innerHTML(this, this.sensor));

                        WC.localStorageKey = sensor + "_interval";
                        this._log(__TEXT_CUSTOM_ELEMENT_CONNECTED);

                        let ITPINGS_DIV = $_createDIV_withClass("<!-- DIV created in connectedCallback -->", "chart-wrapper");
                        let _append = childNode => $_appendChild(ITPINGS_DIV, childNode);

                        WC.CAPTION = _append($_createDIV_withClass(sensor, "itpings-div-title"));

                        /** Add interval UI to Chart DIV **/
                        WC.INTERVALS = [...__INTERVALS.keys()].reduce((intervals, key) => {         // loop all intervals, starting with parent DIV chart_interval
                            let DIV = $_appendChild(intervals, $_createDIV_with_id(key, key));      //      add one interval DIV
                            DIV.addEventListener("click", () => WC.interval = key);                 //      add click event
                            return intervals;                                                       //      all (new) intervals
                        }, _append($_createDIV_withClass(emptyString, "chart_interval")));          // start reduce with parent DIV

                        /** append CANVAS to ITPINGS_DIV **/
                        WC.CANVAS = _append($_createElement("CANVAS"));

                        $_appendChild(WC, ITPINGS_DIV);      // now _append that sucker to the DOM

                        WC.interval = $_localstorage_Get(WC.localStorageKey, __INTERVAL_DEFAULT);                // force interval setter so the chart is redrawn
                    }

                    // noinspection JSUnusedGlobalSymbols
                    disconnectedCallback() {
                        this._log("disconnected", this.isConnected ? "connected" : "NOT connected");
                    }
                });
            }

        )()
        ; // function (elementName = "itpings-chart")

        (

            function (elementName = __custom_element_prefix + "json") {
                let _traceCustomElement = true; // for educational purposes, trace specific CustomElement operations to the console

                window.customElements.define(elementName, class extends HTMLElement {
                    _log() {
                        $_log(elementName + ":" + (this._WebComponent_ID || 'INIT'), __colors_blue[2], ...arguments);
                    };

                    //region ========================================================== Custom Element Getters/Setters
                    // noinspection JSUnusedGlobalSymbols
                    static get observedAttributes() {
                        let _observedAttributes = [__$DEF.sensor, "interval"];
                        //constructor has not run yet, so this scope is not available
                        //this._log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTES, _observedAttributes);
                        return _observedAttributes;
                    }

                    //endregion

                    // noinspection JSUnusedGlobalSymbols
                    constructor() {
                        super();
                    }

                    // noinspection JSUnusedGlobalSymbols
                    attributeChangedCallback(attr, oldValue, newValue) {
                        let WC = this;
                        let isConnected = WC.isConnected;
                        switch (attr) {
                            case("interval"):
                                if (isConnected) {
                                }
                                break;
                            default:
                                break;
                        }
                    }

                    // noinspection JSUnusedGlobalSymbols
                    connectedCallback() {
                        let WC = this;
                    }

                    // noinspection JSUnusedGlobalSymbols
                    disconnectedCallback() {
                        this._log("disconnected", this.isConnected ? "connected" : "NOT connected");
                    }
                });
            }

        )()
        ; // function (elementName = "itpings-json")

//region ========================================================== Basic Router with Templates

        class Router {
            static _log() {
                $_logEvent("Router.teal", ...arguments);
            }

            static routeId(route) {
                return 'article_' + (route === '' ? 'dashboard' : route);
            }

            static toggleIcon(routeObj) {
                return void($_toggleClass(routeObj.icon_element, 'sidebar_icon_selected'));
            }

            constructor(routerConfig) {
                this.preload = [];
                this.routerConfig = routerConfig;
                this.routes = new Map();
                this.previousRoute = false;
                this.currentRoute = false;

                Router._log('Init Router');

                $_querySelectorAll(".sidebar_icon a").map(this.initRoute.bind(this));

                ['hashchange'].map(evt => window.addEventListener(evt, () => {
                        Router._log('event:', evt, this.currentRoute, this);
                        this.goRoute();
                    }
                ));

                return this;
            }

            goRoute(route = Router.routeId(location.hash.slice(1))) {
                Router._log('goRoute:', route);
                if (this.routes.has(route)) {
                    // Router._log('went Route:', route);
                    [this.previousRoute, this.currentRoute] = [this.currentRoute, this.routes.get(route)];
                    this.currentRoute.load().show();
                    if (this.previousRoute) this.previousRoute.hide();
                }
            }

            initRoute(icon_element) {
                let route = Router.routeId(icon_element.href.split('#')[1]);
                let placeholder_element = $_getElementById('placeholder_' + route);
                let updateDOM = () => window.setTimeout(void(false), 1);
                let trace = false;

                $_hideElement(placeholder_element);
                if (trace) Router._log('initRoute', route);
                this.routes.set(route, {
                    route, icon_element, placeholder_element,   // ES6 Object key+value init
                    load() {
                        let placeholder = this.placeholder_element;
                        if (trace) Router._log('loadroute', this.route);
                        // if the placeholder is empty, copy the TEMPLATE into it
                        if (placeholder.childElementCount < 1) placeholder.appendChild($_importNode(this.route));
                        return this; // make .load().show() chaining possible
                    },
                    show() {
                        Router.toggleIcon(this);
                        $_showElement(this.placeholder_element, 'grid');
                        updateDOM();
                        return this;
                    },
                    hide() {
                        Router.toggleIcon(this);
                        $_hideElement(this.placeholder_element);
                        updateDOM();
                        return this;
                    }

                });
                let preload = this.routerConfig.preload || placeholder_element.getAttribute('preload');
                if (preload) {
                    this.preload.push(this.routes.get(route));
                }
            }

            preloadAll() {
                Router._log('preloadAll', this.preload);
                this.preload.map(thisRoute => thisRoute.load.call(thisRoute));
            }
        }

//endregion ======================================================= Basic Router with Templates
        let DeviceColors;
        // try {
        __QueryManager = new ITpings_Query_Manager();

        DeviceColors = new StyleManager("DynamicDeviceColors"); // id of <STYLE> tag in DOM

        //__ITpings_Router = new Router({"preload": true});

        $_fetch(__localPath('ApplicationDevices'))
            .then(json => {
                __getResultArray(json).map(device => {
                    DeviceColors.addDevice(device);
                });

                __setHeartbeat(__heartbeat_default);
                window.addEventListener('focus', () => __setHeartbeat(__heartbeat_default));             // 1 second short-polling MySQL endpoint
                window.addEventListener('blur', () => __setHeartbeat(__heartbeat_blurred));    // 30 minute polling when window does NOT have focus

                window.$_ready(() => {
                    __ITpings_Router = new Router({"preload": true});
                    __ITpings_Router.preloadAll();
                    __ITpings_Router.goRoute();
                });

            });
        // }
        // catch (e) {
        //     console.error(e);
        // }
    }
)(window, document.currentScript.ownerDocument, localStorage);
