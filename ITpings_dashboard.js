/** @license MIT
 * This Dashboard uses hardcoded Databases references
 * If you make changes in the Database Schema, be sure to check those new names in this Dashboard source code
 * */

// Use a decent IDE, like JetBrains, Atom or VSCode

// Learn to collapse regions/code-blocks with Ctrl-Plus and Ctrl-Minus

// press Ctrl-Shift-Minus now to collapse all code

// press Ctrl-Shift-Plus TWICE to uncollapse all code

!(function (window, document) {

        // console log with colors (in Chrome)
        let __log = (label, bgColor, a = '', b = '', c = '', d = '', e = '', f = '', g = '', h = '') => {
            console['log'](`%cWC:${label}:`, 'background:' + bgColor || 'lightcoral', a, b, c, d, e, f, g, h);
        };

        let __TEXT_QUERYMANAGER_CANT_REGISTER = " QueryManager can't register";
        let __TEXT_REGISTER_FOR_DOPULSE = "register for doPulse";
        let __TEXT_LOADING = "Loading:";
        let __TEXT_NOT_A_VALID__SOURCE = " is not a valid ITpings result source";
        let __TEXT_EMPTY_DATABASE = "<h2>Empty Database, check your HTTP Integration setting and reload page</h2>";
        let __TEXT_DOPULSE_TABLE = "doPulse Table";

        let __TEXT_CUSTOM_ELEMENT_ATTRIBUTES = "CustomElement observedAttributes:";
        let __TEXT_CUSTOM_ELEMENT_CONSTRUCTOR = "CustomElement constructor";
        let __TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED = "CustomElement attributeChanged:";
        let __TEXT_CUSTOM_ELEMENT_CONNECTED = "CustomeElement connectedCallback";

        //MATCHING DATABASE FIELDNAMES
        let __APP_TEXT_LASTSEEN = "LastSeen";

        //region ========================================================== I am learning to without jQuery, Underscore or Lodash
        let __isDefined = x => typeof x !== 'undefined';
        let __isString = x => typeof x === 'string';

        let __strReverse = x => [...x].reverse().join``;
        let __CSV2Array = x => x.split`,`;
        let __firstArrayElement = x => x.shift();
        let __lastArrayElement = x => x.pop();

        let __createDocumentFragment = () => document.createDocumentFragment();
        let __createElement = x => document.createElement(x);
        let __createElement__DIV = (x) => {
            let DIV = __createElement('DIV');
            x && (DIV.innerHTML = x);   // x && __setInnerHTML(DIV,x)
            return DIV;
        };
        let __createElement__DIV_Class = (x, y) => {
            let DIV = __createElement__DIV(x);
            DIV.classList.add(y);
            return DIV;
        };
        let __createElement__DIV_id = (x, y) => {
            let DIV = __createElement__DIV(x);
            __setAttribute(DIV, 'id', y);
            return DIV;
        };

        let __setInnerHTML = (x, y) => x.innerHTML = y;

        let __appendChild = (parent, child) => parent.appendChild(child);
        let __insertBefore = (parent, child, referenceNode) => parent.insertBefore(child, referenceNode);

        let __getAttribute = (element, property) => element.getAttribute(property);
        let __setAttribute = (element, property, value) => element.setAttribute(property, value);
        let __setAttributes = (element, arr) => Object.keys(arr).map((property) => element.setAttribute(property, arr[property]));

        let __classList_add = (x, y) => x.classList.add(y);
        // toggle a className for N elements (selected/unselected)
        let __toggleClasses = (elements, selectedElement, className) => elements.map(x => x.classList[x === selectedElement ? 'add' : 'remove'](className));

        let __Object_keys = x => Object.keys(x);


        //get a single (existing!!) STYLE definition from DOM  (dynamically added STYLE tags are not available in the .styleSheets Object!)
        let __getStyleSheetByTitle = x => [...document.styleSheets].filter(y => y.title === x)[0];
        let __addStyleRule = (style, rule) => style.insertRule(rule, 0);


        let __localstorage_Get = (key, value) => {
            let stored = localStorage.getItem(key);
            return stored ? stored : value;
        };

        let __localstorage_Set = (key, value) => localStorage.setItem(key, value);


        let __daysSince = x => Math.floor((new Date(x).getTime() - new Date().getTime()) / 864e5);  // 0=today , negative for past days, positive for future days

        // Chart JS says it depends on momentJS
        // noinspection JSUnresolvedFunction
        let __MOMENT = (date, format) => __isDefined(format) ? moment(date).format(format) : moment(date);
        // noinspection JSUnresolvedFunction
        let __MOMENT_DIFF_MINUTES = x => __MOMENT(x).diff(__MOMENT(new Date()), 'minutes');

        let __fetch = (uri) => {    // not using Async/Await, if you don't know ES6; fetch/promises read better
            return new Promise((resolve, reject) => {
                fetch(uri)
                    .then(response => response.json())
                    .then(json => {
                        __log('Fetched', 'orange', uri, json['sql']);
                        resolve(json);
                    });
            })
        };

        //endregion ======================================================= I am learning to without jQuery, Underscore or Lodash

        let __abbreviated_DeviceID = x => ['', 'Node on desk', 'Node in attic'][Number(x.split`_`.reverse()[0])];
        let __localPath = x => {
            let uri = location.href.split`/`;               // get endpoint from current uri location
            uri.pop();                                      // discard filename
            uri.push('ITpings_connector.php?query=' + x);   // stick on query endpoint
            uri = uri.join`/`;
            return uri;
        };

        //region ========================================================== StyleManager : manage <STYLE> definitions in DOM

        // TTN Node names get a distinct color in Tables and Graphs

        class StyleManager {
            constructor(style_title) {
                let _SM = this;
                _SM.STYLE = __getStyleSheetByTitle(style_title);
                _SM.deviceColor = new Map;
                // 20 Distinct colors: https://sashat.me/2017/01/11/list-of-20-simple-distinct-colors/
                _SM.colors = __CSV2Array("#e6194b,#0082c8,#f58231,#911eb4,#46f0f0,#f032e6,#d2f53c,#fabebe,#008080,#e6beff,#aa6e28,#fffac8,#800000,#aaffc3,#808000,#ffd8b1,#000080,#808080,#ffe119");
            }

            getColor(dev_id) {  // store distinct color PER device
                let _SM = this;
                let color;
                let devices = _SM.deviceColor;
                if (devices.has(dev_id)) {
                    color = devices.get(dev_id);
                } else {
                    color = __firstArrayElement(_SM.colors);
                    devices.set(dev_id, color);
                    __addStyleRule(_SM.STYLE, `TD[data-dev_id='${dev_id}'] {border-bottom: 3px solid ${color};}`);
                }
                return color;
            }
        }

        let DeviceColors = new StyleManager('DynamicDeviceColors'); // id of <STYLE> tag in DOM

        //endregion ======================================================= StyleManager

        //region ========================================================== Application Constants / definitions

        let __DEFAULT_MAXROWS = 225;            // maximum number of rows to return in the JSON result for Graphs

        let __DB_PingID_endpoint = 'PingID';    // Smallest payload 256 Bytes, but only gets max(_pingid)
        let __DB_IDs_endpoint = 'IDs';          // All (new) IDs (only called when there is a new _pingid)

        let __ATTR_data_title = 'title';
        let __ATTR_data_query = 'query';
        let __ATTR_data_sensorname = 'sensorname';
        let __ATTR_data_interval = 'interval';
        let __ATTR_data_refresh = 'pulse';

        let __STR_MINUTE = 'MINUTE';
        let __STR_HOUR = 'HOUR';
        let __STR_DAY = 'DAY';
        let __STR_WEEK = 'WEEK';
        let __STR_MONTH = 'MONTH';
        let __STR_YEAR = 'YEAR';

        //endregion ======================================================= Application Constants / definitions

//region ========================================================== QueryManager for all itpings-table & itpings-chart Custom Elements

        /**
         * It would be loads of XHR request when every Table and Chart polled the backend for new data (most of the time for no new data)
         * So, on display, Tables and Charts register themselves with the QueryManager (IQM) (by Query and PrimaryKey they use)
         * The IQM polls the MySQL backend for new (Primary) Key values
         * If there are new values the Tables and Charts get a doPulse, and (they) retrieve the new Rows from the database themselves
         * This XHR short-polling method works fine for your single client Dashboard, every poll is only 256 Bytes (per second)
         * In a modern web-world this can be done with WebSockets
         * **/
        class ITpings_Query_Manager {
            static _log(a, b, c, d, e, f, g, h) {
                __log('IQM', 'lightcoral', a, b, c, d, e, f, g, h);
            }

            constructor() {
                let _QM = this;
                _QM.maxid = 0;                          // record MAX(_pingid), higher value will cause all registered tables/graphs to update
                _QM[__ATTR_data_refresh] = new Map();   // store all tables/graphs query endpoints AND PrimaryKey fields here
                _QM.doPulse(__DB_PingID_endpoint);      // any new ids in the Database?
            }

            register(WC) {// register a new query
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

                let _QM = this;

                //get 'pulse' dataattribute, record tablename and idfield / value, reference DOM element
                let datasrc, idfield;
                // ** These can be configured as data trribute on the itpings-table tag (don't use that .. too complex to complain)
                // ** So it is the query="xxx" name and the FIRST column name in the table (kinda auto configuration)
                let setting = __getAttribute(WC, __ATTR_data_refresh); // eg: pulse="SensorValues:_pingid"
                if (setting) {
                    setting = setting.split`:`;
                    datasrc = setting[0];
                    idfield = setting[1];
                } else {                                                            // determine from query="..."
                    datasrc = WC.query || __getAttribute(WC, __ATTR_data_query);
                    idfield = WC.idfield || '_pingid';                 // FIRST column in itpings-table
                }
                if (!datasrc) console.error(__TEXT_QUERYMANAGER_CANT_REGISTER, WC);
                let _IQMap = _QM[__ATTR_data_refresh];

                if (!_IQMap.has(datasrc)) _IQMap.set(datasrc, new Map());           // every datasrc gets its own Map (so 'can' store muliple PrimaryKeys
                let datasrcMap = _IQMap.get(datasrc);                               // Sorry... looking back I should have simplified this
                if (!datasrcMap.has(idfield)) datasrcMap.set(idfield, new Set());   // I thought, too soon, about multiple dashboards and hundreds of devices
                datasrcMap.get(idfield).add(WC);
                ITpings_Query_Manager._log(__TEXT_REGISTER_FOR_DOPULSE, datasrc, idfield);
            }

            // ** Get maximum ID values from Database and notify/pulse the (registered) Custom Elements in the page
            doPulse(endpoint) {
                let _QM = this;
                let _NOW = 1;
                let newPulse = (x, y) => window.setTimeout(() => _QM.doPulse(x), y || heartbeat_milliseconds);
                __fetch(__localPath(endpoint))
                    .then(json => {
                            if (endpoint === __DB_PingID_endpoint) {    // endpoint return smallest amount of data = max(pingid)
                                if (json > _QM.maxid) {
                                    _QM.maxid = json;                   // if it is higher
                                    newPulse(__DB_IDs_endpoint, _NOW);  // call immediatly for all ID values
                                } else {
                                    newPulse(__DB_PingID_endpoint);
                                }
                            } else {
                                ITpings_Query_Manager._log(heartbeat_milliseconds, 'Got recent ID values from Database', json);
                                __Object_keys(json.maxids).forEach(datasrc => {
                                    // ** See above, I developed this way too complex, thinking in multiple Dashboard and a shitload of devices
                                    // ** But this works: Read maxids JSON structure from DBInfo (these are just ID values, NOT the new Data!)
                                    // ** walk over JSON structure, match it with the registered tables/graphs
                                    // ** and contact/pulse every registered ITpings Custom Element
                                    // ** The Custom Element itself will check if it needs to call the MySQL DB itself to get the actual data

                                    // noinspection JSUnfilteredForInLoop
                                    let setting = json.maxids[datasrc];
                                    let idfield = __Object_keys(setting)[0];
                                    let idvalue = setting[idfield];
                                    // noinspection JSUnfilteredForInLoop
                                    let datasrcMap = _QM[__ATTR_data_refresh].get(datasrc);
                                    if (datasrcMap) {
                                        let fieldSet = datasrcMap.get(idfield);
                                        if (fieldSet) {
//                                            _QM._log('doPulse', datasrc, idfield, idvalue, datasrcMap, fieldSet);
                                            fieldSet.forEach(ITpings_element => ITpings_element.doPulse(idvalue, idfield));
                                        } else {
                                            // noinspection JSUnfilteredForInLoop
                                            ITpings_Query_Manager._log('No fieldSet', datasrc, idfield, idvalue, datasrcMap, fieldSet);
                                        }
                                    }
                                });
                                newPulse(__DB_PingID_endpoint);
                            }
                        }
                    );
            }
        }

        let _QM = window.i = new ITpings_Query_Manager();

//endregion ======================================================= QueryManager for all itpings-table & itpings-chart Custom Elements


        //region ========================================================== Heartbeat, poll database for new data
        let heartbeat_milliseconds;

        let __setHeartbeat = (x) => {
            heartbeat_milliseconds = x;
            __log('Heartbeat:', 'orange', heartbeat_milliseconds);
            if (__isDefined(_QM)) _QM.doPulse(__DB_PingID_endpoint);
        };

        __setHeartbeat(1000);

        window.onfocus = () => __setHeartbeat(1e3);          // 1 second short-polling MySQL endpoint
        window.onblur = () => __setHeartbeat(1e3 * 60 * 30); // 30 minute polling when window does NOT have focus

        //endregion ======================================================== Heartbeat, poll database for new data


        (function (elementName = 'itpings-table') {

            let _traceCustomElement = true; // for educational purposes, trace specific CustomElement operations to the console
            let _WebComponent_ID;
            let _log = (a, b, c, d, e, f, g, h) => __log(elementName + ':' + _WebComponent_ID, 'lightgreen', a, b, c, d, e, f, g, h);


            window.customElements.define(elementName, class extends HTMLElement {
                // noinspection JSUnusedGlobalSymbols
                static get observedAttributes() {
                    let _observedAttributes = [__ATTR_data_query];          // data attributes (changes) this Custom Element listens to
                    if (_traceCustomElement) _log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTES, _observedAttributes);
                    return _observedAttributes;
                }

                get title() {
                    return __getAttribute(this, __ATTR_data_title);
                }

                set title(newValue) {
                    __setAttribute(this, __ATTR_data_title, newValue);
                }

                // noinspection JSUnusedGlobalSymbols
                constructor() {
                    super();
                    _WebComponent_ID = this.getAttribute(__ATTR_data_query);
                    if (_traceCustomElement) _log(__TEXT_CUSTOM_ELEMENT_CONSTRUCTOR, this);
                    this.maxid = 1;         // maximum pingid/devid/appid etc. this itpings-table has displayed
                }

                prepareTable() {
                    let WC = this;
                    WC.idfield = false;
                    WC.idle = true;                                                                    // no new fetch when still waiting or previous one
                    let title = WC['title'];
                    if (WC.TABLEWRAPPER) WC.TABLEWRAPPER.parentNode.removeChild(WC.TABLEWRAPPER);    // remove existing table

                    let ITPINGS_DIV = WC.TABLEWRAPPER = __createElement__DIV_Class('', 'table-wrapper');
                    WC.TITLE = __appendChild(ITPINGS_DIV, __createElement__DIV_Class(title, 'itpings-div-title'));
                    WC.TABLE = __appendChild(ITPINGS_DIV, __createElement('TABLE'));                   // new TABLE inside DIV with WC.TABLE reference
                    WC.THEAD = __appendChild(WC.TABLE, __createElement('THEAD'));                     // add THEAD, references are used to fill data
                    WC.TBODY = __appendChild(WC.TABLE, __createElement('TBODY'));                     // add (first) TBODY

                    WC.innerHTML = `<DIV class="loading">` + __TEXT_LOADING + `${title}</DIV>`;

                    // ** There is no data in the <TABLE> yet, data arrives in the async fetch Data call
                    // ** So the HTML above will be injected into the DOM in that fetch Data call

                    this.fetchData();  // call ONCE with an empty filter value
                }

                fetchData(filter = '') {
                    // ** The Custom Element connectedCallback function (below) has already executed
                    // ** Initialization for the TABLE HTML struture is done there

                    let WC = this; // WC = WebComponent (aka CustomElement) makes for clear understanding what 'this' is; AND it minifies better :-)
                    let add_TableRow = (row, idx = 'THEAD') => {                     // abusing ES6 default value to check if the func is called to draw the THEAD
                        let isTBODY = idx !== 'THEAD';
                        let TR = (isTBODY ? WC.TBODY : WC.THEAD).insertRow(idx);          // add TR at bottom of THEAD _OR_ bottom/top TBODY  (abusing idx)

                        __Object_keys(row).map(name => {                                    // add Columns .map is shorter, and seems to perform better than .forEach
                            let value = isTBODY ? row[name] : name;                         // add Header Name _OR_ Cell Value

                            __setAttribute(TR, 'data-' + name, value);                      // plenty of attributes on the TR also so we can apply CSS

                            let TD = TR.insertCell();                                       // add TD cell
                            __setAttributes(TD, {
                                'data-column': name,
                                ['data-' + name]: value                                     // ['data'+name] = ES6 Dynamic Object Keys
                            });
                            __classList_add(TD, 'fadeOutCell');                             // color cell, and fade out

                            if (isTBODY && name === __APP_TEXT_LASTSEEN) value = __MOMENT_DIFF_MINUTES(value);

                            if (name === WC.idfield) {
                                if (~~value > WC.maxid) WC.maxid = ~~value;

                                // ** Only execute once PER Row/Table
                                // ** mouseover a pingid and all other tables scroll to the same pingid
                                //
                                //     __setAttribute(WC.TBODY, 'data-' + name, value);
                                //     TR.addEventListener('mouseenter', () => {
                                //         let WC = this;
                                //         let _pingid = TR.getAttribute('data-_pingid');
                                //         let selector = "itpings-table TBODY[data-_pingid='" + _pingid + "']";
                                //         let TBODYs_pingid = [...document.querySelectorAll(selector)];
                                //         if (TBODYs_pingid) {
                                //             TBODYs_pingid.map((tbody) => {
                                //                 if (TR.parentNode !== tbody) {
                                //                     tbody.scrollIntoView();
                                //                     tbody.style.border = '1px solid orange';
                                //                 }
                                //             });
                                //         }
                                //         if (!TR.hasMouseLeaveListener) {
                                //             TR.addEventListener('mouseleave', () => {
                                //                 TBODYs_pingid.map((tbody) => {
                                //                     tbody.parentNode.scrollIntoView();
                                //                     tbody.style.border = '';
                                //                 });
                                //             });
                                //             TR.hasMouseLeaveListener = true;
                                //         }
                                //     });
                            }
                            __setInnerHTML(TD, value);
                        });
                    };

                    if (WC.idle) {                                                         // not waiting for a JSON response already
                        WC.idle = false;                                                   // true again AFTER fetch processing is done
                        __fetch(WC.uri + filter)
                            .then(json => {
                                let rows = json.result;
                                if (rows) {
                                    if (!WC.idfield) {                                     // first draw of TABLE

                                        let headers = rows[0];                            // first row
                                        let first_column_name = __Object_keys(headers)[0];
                                        if (first_column_name[0] !== '_') console.error(first_column_name, 'might not be a correct Key fieldname return from DBInfo endpoint');
                                        WC.idfield = first_column_name;                    // take from attribute _OR_ first JSON row
                                        add_TableRow(headers);                              // first row keys are the THEAD columnheaders
                                        rows.forEach(add_TableRow);                       // add all rows
                                        WC.innerHTML = '';                                 // remove Loading...
                                        __appendChild(WC, WC.TABLEWRAPPER);               // now append that Custom Element to the DOM
                                        _QM.register(WC);                                  // Register my query with the Query Manager, so I get (doPulse) updates

                                    } else if (rows.length) {                             // add new rows in a new TBODY at the top of the TABLE

                                        // append new TBODY, overriding privious WC.TBODY reference
                                        WC.TBODY = __insertBefore(WC.TABLE, __appendChild(WC.TABLE, __createElement('TBODY')), WC.TBODY);
                                        __classList_add(WC.TBODY, 'newPing');              // animate background color of this newPing
                                        rows.map(row => add_TableRow(row, 0));            // add rows at top of TBODY

                                    } else {
                                        console.warn('empty result set from:', WC.uri);
                                    }
                                    WC.idle = true;                                        // processed all rows

                                } else {
                                    let query = WC[__ATTR_data_query];
                                    __appendChild(WC,
                                        __createElement__DIV_Class(
                                            `<b><a href=?query='${query}'>${query}</a></b>` + __TEXT_NOT_A_VALID__SOURCE
                                            , 'itpings-table-error'
                                        )
                                    );
                                }
                            })
                            .catch(error => {
                                console['error'](error);
                                __setInnerHTML(WC, error + __TEXT_EMPTY_DATABASE);
                            });
                    }
                }

                // noinspection JSUnusedGlobalSymbols
                doPulse(_pingid, pulseidfield) {                                            // read Database endpoint, only when there are new ids
                    let WC = this;
                    let maxid = WC.maxid;
                    let idfield = WC.idfield;
                    if (_pingid > maxid) {
                        _log(__TEXT_DOPULSE_TABLE, WC.query, idfield, pulseidfield, maxid, _pingid);
                        this.fetchData('&filter=' + idfield + ' gt ' + maxid); // add filter on uri to get only new values
                    }
                }

                // noinspection JSUnusedGlobalSymbols
                attributeChangedCallback(attr, oldValue, newValue) {
                    // ** called for every change for Data-attributes in the HTML tag
                    // ** So also at first creation in the DOM
                    let WC = this;
                    let isConnected = WC.isConnected;                                     // sparsely document standard property
                    if (attr === __ATTR_data_query) {
                        _WebComponent_ID = newValue;
                        WC.uri = __localPath(newValue);
                        if (_traceCustomElement) _log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED, attr + ' / ' + oldValue + ' / ' + newValue, isConnected ? '' : '►► NOT', 'Connected');
                        this.prepareTable();
                    }
                }

                // noinspection JSUnusedGlobalSymbols
                connectedCallback() {
                    if (_traceCustomElement) _log(__TEXT_CUSTOM_ELEMENT_CONNECTED); // ** Called before Custom Element is added to the DOM
                }
            });
        })(); // function (elementName = 'itpings-table')


        (function (elementName = 'itpings-chart') {
            //return;
            let _traceCustomElement = true; // for educational purposes, trace specific CustomElement operations to the console
            let _WebComponent_ID;
            let _log = (a, b, c, d, e, f, g, h) => __log(elementName, 'lightblue', a, b, c, d, e, f, g, h);

            let __INTERVALS = new Map();
            // ES6 destructuring, parameter names become keys: {interval, unit, xformat}
            let addInterval = (key, interval, unit, xformat) => __INTERVALS.set(key, {interval, unit, xformat});
            addInterval('5m', 5, __STR_MINUTE, 'H:mm');
            addInterval('30m', 30, __STR_MINUTE, 'H:mm');
            addInterval('1H', 1, __STR_HOUR, 'H:mm');
            addInterval('1H', 1, __STR_HOUR, 'H:mm');
            addInterval('2H', 2, __STR_HOUR, 'H:mm');
            addInterval('6H', 6, __STR_HOUR, 'H:mm');
            addInterval('1D', 1, __STR_DAY, 'H:mm');
            addInterval('2D', 2, __STR_DAY, 'D MMM H:mm');
            addInterval('7D', 7, __STR_DAY, 'D MMM H:mm');
            addInterval('2W', 2, __STR_WEEK, 'D MMM H:mm');
            addInterval('1M', 1, __STR_MONTH, 'D MMM');
            addInterval('6M', 6, __STR_MONTH, 'D MMM');
            addInterval('1Y', 1, __STR_YEAR, 'D MMM');

            let __INTERVAL_DEFAULT = __INTERVALS.get('6H');

            window.customElements.define(elementName, class extends HTMLElement {
                //region ========================================================== Custom Element Getters/Setters
                // noinspection JSUnusedGlobalSymbols
                static get observedAttributes() {
                    let _observedAttributes = [__ATTR_data_sensorname, __ATTR_data_interval];
                    _log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTES, _observedAttributes);
                    return _observedAttributes;
                }

                get sensorname() {
                    return __getAttribute(this, __ATTR_data_sensorname);
                }

                set sensorname(newValue) {
                    __setAttribute(this, __ATTR_data_sensorname, newValue);
                }

                get interval() {
                    return __getAttribute(this, __ATTR_data_interval);
                }

                set interval(newValue) {
                    _log('(setter) ►►►', __ATTR_data_interval, ':', newValue);
                    __setAttribute(this, __ATTR_data_interval, newValue);
                    let WC = this;
                    let intervalDIV = WC.INTERVALS.querySelector(`[id='${newValue}']`);
                    let sensorname = WC.sensorname;
                    __localstorage_Set(WC.localStorageKey, newValue);

                    __toggleClasses([...this.INTERVALS.children], intervalDIV, 'selectedInterval'); //loop all interval DIVs , add or remove Class: selectedInterval
                    let intervalDefinition = WC.__INTERVAL = __INTERVALS.has(WC.interval) ? __INTERVALS.get(WC.interval) : __INTERVAL_DEFAULT;

                    //todo use faster SensorValues_Update query   let sensor_ids = (sensorname === 'temperature_5') ? "7,14" : "6,13";

                    WC.query = 'SensorValues';
                    WC.sensorids = [];                             // Array, index number is used to register devices

                    if (WC.ChartJS) WC.ChartJS.destroy();
                    WC.ChartJS = new Chart(WC.CANVAS, {
                        type: 'line',
                        data: {
                            labels: [],
                            datasets: []
                        },
                        options: {
                            maintainAspectRatio: false,
                            title: {
                                display: false,
                                text: ''
                            },
                            showLines: true,
                            elements: {
                                line: {
                                    tension: 0
                                }
                            }
                        }
                    });
                    _QM.register(WC);
                    WC.uri = __localPath(WC.query + '&sensorname=' + sensorname
                        + '&orderby=created&interval=' + intervalDefinition.interval
                        + '&intervalunit=' + intervalDefinition.unit + '&limit=none&maxrows=' + __DEFAULT_MAXROWS);
                    this.fetchData();
                }

                //endregion

                fetchData(filter = '') {
                    let WC = this;
                    WC.TITLE.innerHTML = __TEXT_LOADING + WC.sensorname;
                    WC.idle = false;
                    __fetch(WC.uri + filter)
                        .then(json => {
                            let ChartJS_data = WC.ChartJS.data;
                            _log('add_ChartJS_rows', json.result.length, 'rows');
                            json.result.map(row => {
                                WC._pingid = row._pingid;                                      // keep track of hightest Primary Key value
                                let dataset_idx = WC.sensorids.indexOf(row._sensorid);
                                if (dataset_idx < 0) {                                          // ** add new device
                                    dataset_idx = ChartJS_data.datasets.length;
                                    let deviceColor = DeviceColors.getColor(row.dev_id);        // get dictinct color from Map
                                    ChartJS_data.datasets.push({                                // add one new line/device data to ChartJS
                                        label: __abbreviated_DeviceID(row.dev_id)
                                        , fill: false
                                        //, lineTension: .5
                                        , backgroundColor: deviceColor
                                        , borderColor: deviceColor
                                        , data: []
                                    });
                                    WC.sensorids.push(row._sensorid);                          // unique sensorid per WC
                                }
                                let x_axis_time = __MOMENT(row.created, WC.__INTERVAL.xformat);// format x-axis label with timestamp
                                ChartJS_data.datasets[dataset_idx].data.push({
                                    x: x_axis_time,
                                    y: row.sensorvalue
                                });
                                //prevent duplicate timelables on x-axis
                                if (!ChartJS_data.labels.includes(x_axis_time)) ChartJS_data.labels.push(x_axis_time);
                            });
                            WC.ChartJS.update();
                            this.idle = true;
                            WC.TITLE.innerHTML = WC.sensorname;
                        });
                }

                // noinspection JSUnusedGlobalSymbols
                doPulse(_pingid) {
                    let WC = this;
                    let current_ping_id = ~~WC._pingid;
                    if (current_ping_id && WC.idle) {
                        if (current_ping_id < _pingid) {
                            _log('doPulse Chart JS _pingid:', current_ping_id, 'new:', _pingid);
                            //let uri = __localPath('SensorValues&sensorname=' + WC.sensorname + '&orderby=_pingid%20ASC&limit=none&filter=_pingid%20gt%20' + current_ping_id);
                            this.fetchData(`&filter=_pingid%20gt%20${current_ping_id}`);
                        }
                    } else {
                        _log('►►► No _pingid on Chart JS yet (not drawn yet) ◄◄◄');
                    }
                }

                // noinspection JSUnusedGlobalSymbols
                constructor() {
                    super();
                }

                // noinspection JSUnusedGlobalSymbols
                attributeChangedCallback(attr, oldValue, newValue) {
                    let WC = this;
                    _log('attributeChanged:', attr, ' oldValue:', oldValue, ' newValue:', newValue, ' isConnected:', WC.isConnected ? 'true' : 'false');
                    let isConnected = WC.isConnected;
                    if (_traceCustomElement) _log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED, attr + ' / ' + oldValue + ' / ' + newValue, isConnected ? '' : '►► NOT', 'Connected');
                    switch (attr) {
                        case(__ATTR_data_interval):
                            if (isConnected) {
                                console.error('RELOAD INTERVAL');
                            }
                            break;
                        default:
                            break;
                    }
                }

                // noinspection JSUnusedGlobalSymbols
                connectedCallback() {
                    let WC = this;
                    let sensorname = WC.sensorname;
                    WC.localStorageKey = sensorname + '_interval';
                    _log(__TEXT_CUSTOM_ELEMENT_CONNECTED);

                    let ITPINGS_DIV = __createElement__DIV_Class(undefined, 'chart-wrapper');
                    let append = x => __appendChild(ITPINGS_DIV, x);

                    WC.TITLE = append(__createElement__DIV_Class(sensorname, 'itpings-div-title'));

                    // Add interval UI to Chart DIV
                    WC.INTERVALS = [...__INTERVALS.keys()].reduce((intervals, key) => {
                        let DIV = __appendChild(intervals, __createElement__DIV_id(key, key));
                        DIV.addEventListener('click', () => WC.interval = key);
                        return intervals;
                    }, append(__createElement__DIV_Class('', 'chart_interval')));


                    WC.CANVAS = append(__createElement('CANVAS'));
                    __appendChild(WC, ITPINGS_DIV);      // now append that sucker to the DOM

                    WC.interval = __localstorage_Get(WC.localStorageKey, __INTERVAL_DEFAULT);                // force interval setter so the chart is redrawn
                }

                // noinspection JSUnusedGlobalSymbols
                disconnectedCallback() {
                    _log('disconnected', this.isConnected ? 'connected' : 'NOT connected');
                }
            });
        })(); // function (elementName = 'itpings-chart')

    }
)
(window, document.currentScript.ownerDocument);

