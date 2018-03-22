/** @license MIT
 * This Dashboard uses hardcoded Databases references
 * If you make changes in the Database Schema, be sure to check those new names in this Dashboard source code
 * */

// Use a decent IDE, like JetBrains, Atom or VSCode

// Learn to collapse regions/code-blocks with Alt-7 (code structure view) Ctrl-Plus and Ctrl-Minus
// press Ctrl-Shift-Minus now to collapse all code
// press Ctrl-Shift-Plus TWICE to uncollapse all code

!(function (window, document) {

        // console log with colors (in Chrome)
        let __log = (label, bgColor = 'lightcoral', a = '', b = '', c = '', d = '', e = '', f = '', g = '', h = '') => {
            console['log'](`%cWC:${label}:`, 'background:' + bgColor, a, b, c, d, e, f, g, h);
        };

        const ITPingsID = '_pingid',    // matching definitions in PHP/MySQL
            ITpings_dev_id = 'dev_id',
            ITpings_modulation = 'modulation',
            ITpings_data_rate = 'data_rate',
            ITpings_coding_rate = 'coding_rate',

            ITPings_graphable_PingedDevices_Values = 'frequency,snr,rssi,channel'.split`,`,

            __DEFAULT_MAXROWS = 100,            // maximum number of rows to return in the JSON result for Graphs

            __useLIVE_Data = 1,
            __synchronized_pingID_scrolling = false,

            __TEXT_QUERYMANAGER_CANT_REGISTER = " QueryManager can't register",
            __TEXT_REGISTER_FOR_DOPULSE = "register for do Pulse",
            __TEXT_NOT_A_VALID__SOURCE = " is not a valid ITpings result source",
            __TEXT_EMPTY_DATABASE = "<h2>Empty Database, check your HTTP Integration setting and reload page</h2>",

            __TEXT_CUSTOM_ELEMENT_ATTRIBUTES = "CustomElement observedAttributes:",
            __TEXT_CUSTOM_ELEMENT_CONSTRUCTOR = "CustomElement constructor",
            __TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED = "CustomElement attributeChanged:",
            __TEXT_CUSTOM_ELEMENT_CONNECTED = "CustomeElement connectedCallback",

            //MATCHING DATABASE FIELDNAMES
            __APP_TEXT_LASTSEEN = "LastSeen";

//region ========================================================== learning to code without jQuery, Underscore or Lodash

        let __isDefined = x => typeof x !== 'undefined',
            __isString = x => typeof x === 'string',

            __strReverse = x => [...x].reverse().join``,
            __CSV2Array = x => x.split`,`,
            __firstArrayElement = x => x.shift(),
            __lastArrayElement = x => x.pop(),

            __createDocumentFragment = () => document.createDocumentFragment(),
            __createElement = x => document.createElement(x),
            // noinspection CommaExpressionJS
            __createElement__DIV = (html, element = __createElement('DIV')) => (html && (element.innerHTML = html), element),
            // noinspection CommaExpressionJS
            __createElement__DIV_Class = (html, className, element = __createElement__DIV(html)) => (element.classList.add(className), element),
            // noinspection CommaExpressionJS
            __createElement__DIV_id = (x, y, DIV = __createElement__DIV(x)) => (__setAttribute(DIV, 'id', y), DIV),

            __setInnerHTML = (x, y = '') => x.innerHTML = y,

            __appendChild = (parent, child) => parent.appendChild(child),
            __insertBefore = (parent, child, referenceNode) => parent.insertBefore(child, referenceNode),

            __getAttribute = (element, property) => element.getAttribute(property),
            __setAttribute = (element, property, value) => element.setAttribute(property, value),
            __setAttributes = (element, arr) => Object.keys(arr).map((property) => element.setAttribute(property, arr[property])),

            __classList_add = (x, y) => x.classList.add(y),
// toggle a className for N elements (selected/unselected)
            __toggleClasses = (elements, selectedElement, className) => elements.map(x => x.classList[x === selectedElement ? 'add' : 'remove'](className)),

            __setCSSproperty = (x, y, el = document.body) => el.style.setProperty(x, y),

            __setBackgroundColor = (el, color) => el.style.backgroundColor = color,

            __Object_keys = x => Object.keys(x),


            __localstorage_Get = (key, value, stored = localStorage.getItem(key)) => stored ? stored : value,
            __localstorage_Set = (key, value) => localStorage.setItem(key, value),

            __daysSince = date => Math.floor((new Date(date).getTime() - new Date().getTime()) / 864e5),  // 0=today , negative for past days, positive for future days

// Chart JS says it depends on momentJS
// noinspection JSUnresolvedFunction
            __MOMENT = (date, format) => __isDefined(format) ? moment(date).format(format) : moment(date),
// noinspection JSUnresolvedFunction
            __MOMENT_DIFF_MINUTES = x => __MOMENT(x).diff(__MOMENT(new Date()), 'minutes'),

            __fetch = (uri) => {    // Async/Await?
                //console.log('Short polling');
                return new Promise((resolve, reject) => {
                    fetch(uri)
                        .then(response => response.json())
                        .then(json => {
                            if (json.hasOwnProperty('result')) {
                                __log('Fetched', 'orange', json.result.length, 'rows from:', uri);
                            }
                            resolve(json);
                        });
                })
            };

//endregion ======================================================= learning to code without jQuery, Underscore or Lodash

//region ========================================================== Application Functions
        let __abbreviated_DeviceID = x => {
            return x;
        };
        let __localPath = x => {
            let uri = location.href.split`/`;               // get endpoint from current uri location
            uri.pop();                                      // discard filename
            uri.push('ITpings_connector.php?query=' + x);   // stick on query endpoint
            uri = uri.join`/`;
            return uri;
        };

        let __LoadingTitle = x => `<DIV class="loading">Loading: ${x}</DIV>`;
//endregion ======================================================= Application Functions

//region ========================================================== StyleManager : manage <STYLE> definitions in DOM

// TTN Node names get a distinct color in Tables and Graphs

        class StyleManager {
            constructor(id) {
                let SM = this;
                // Get a single (existing!!) STYLE definition from DOM  (dynamically added STYLE tags are not available in the .styleSheets Object!)
                // CSSStyleSheet does not have an id
                SM.STYLE = [...document.styleSheets].find(sheet => sheet.ownerNode['id'] === id);
                SM.deviceColor = new Map;
                // 20 Distinct colors: https://sashat.me/2017/01/11/list-of-20-simple-distinct-colors/
                SM.colors = __CSV2Array("#e6194b,#0082c8,#f58231,#911eb4,#46f0f0,#f032e6,#d2f53c,#fabebe,#008080,#e6beff,#aa6e28,#fffac8,#800000,#aaffc3,#808000,#ffd8b1,#000080,#808080,#ffe119");
            }

            getColor(dev_id) {  // store distinct color PER device
                let SM = this;
                let color;
                if (SM.deviceColor.has(dev_id)) {
                    color = SM.deviceColor.get(dev_id);
                } else {
                    color = __firstArrayElement(SM.colors);
                    SM.deviceColor.set(dev_id, color);
                    //let rule=`TD[data-dev_id='${dev_id}'] {border-bottom: 3px solid ${color}}`;   // underline
                    let rule = `span[data-dev_id='${dev_id}']::before{background:${color}}`;          // square
                    SM.STYLE.insertRule(rule, 0);
                }
                return color;
            }
        }

        let DeviceColors = new StyleManager('DynamicDeviceColors'); // id of <STYLE> tag in DOM

//endregion ======================================================= StyleManager

//region ========================================================== Application Constants / definitions

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

        let __DATETIME_TIME = 'H:mm';
        let __DATETIME_DATETIME = 'D MMM H:mm';
        let __DATETIME_DATE = 'D MMM';

//endregion ======================================================= Application Constants / definitions

//region ========================================================== QueryManager for all itpings-table & itpings-chart Custom Elements

        /**
         * It would be loads of XHR request when every Table and Chart polled the backend for new data (most of the time for no new data)
         * So, on display, Tables and Charts register themselves with the QueryManager (IQM) (by Query and PrimaryKey they use)
         * The IQM polls the MySQL backend for new (Primary) Key values
         * If there are new values the Tables and Charts get a do Pulse, and (they) retrieve the new Rows from the database themselves
         * This XHR short-polling method works fine for your single client Dashboard, every poll is only 256 Bytes (per second)
         * In a modern web-world this can be done with WebSockets
         * **/
        class ITpings_Query_Manager {
            static _log() {
                __log('IQM', 'lightcoral', ...arguments);
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
                    idfield = WC.idfield || ITPingsID;                 // FIRST column in itpings-table
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
                                ITpings_Query_Manager._log('heartbeat:', heartbeat_milliseconds, 'Got recent ID values from Database', json);
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
            let _log = (a, b, c, d, e, f, g, h, i) => __log(elementName + ':' + _WebComponent_ID, 'lightgreen', a, b, c, d, e, f, g, h, i);

            // console.log((function () {
            // })());

            window.customElements.define(elementName, class extends HTMLElement {
                // noinspection JSUnusedGlobalSymbols
                static get observedAttributes() {
                    let observedAttributes = [__ATTR_data_query];          // data attributes (changes) this Custom Element listens to
                    if (_traceCustomElement) _log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTES, observedAttributes);
                    return observedAttributes;
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

                fetchData(filter = '') {
                    // ** The Custom Element connectedCallback function (below) has already executed
                    // ** Initialization for the TABLE HTML struture is done there

                    let WC = this; // WC = WebComponent (aka CustomElement) makes for clear understanding what 'this' is; AND it minifies better :-)
                    WC.requiredColumns = new Set();
                    let add_TableRow = (row, idx = 'THEAD') => {                     // abusing ES6 default value to check if the func is called to draw the THEAD
                        let isTBODY = idx !== 'THEAD';
                        let TR = (isTBODY ? WC.TBODY : WC.THEAD).insertRow(idx);          // add TR at bottom of THEAD _OR_ bottom/top TBODY  (abusing idx)

                        let add_TableColumns = name => {
                            let value = isTBODY ? row[name] : name;                         // add Header Name _OR_ Cell Value

                            __setAttribute(TR, 'data-' + name, value);                      // plenty of attributes on the TR also so we can apply CSS

                            let TD = TR.insertCell();                                       // add TD cell
                            __setAttributes(TD, {
                                'data-column': name,
                                ['data-' + name]: value                                     // ['data'+name] = ES6 Dynamic Object Keys
                            });
                            __classList_add(TD, 'fadeOutCell');                             // color cell, and fade out

                            let checkRequiredColumn = (columnName, standardValue) => {
                                if (isTBODY && name === columnName && value !== standardValue) {
                                    WC.requiredColumns.add(name);
                                }
                            };
                            checkRequiredColumn(ITpings_modulation, 'LORA');                // display Column only if there are non-standard values
                            checkRequiredColumn(ITpings_coding_rate, '4/5');
                            checkRequiredColumn(ITpings_data_rate, 'SF7BW125');

                            if (isTBODY && name === __APP_TEXT_LASTSEEN) value = __MOMENT_DIFF_MINUTES(value);

                            if (isTBODY && name === ITpings_dev_id) value = `<SPAN data-column="${name}" data-${name}="${value}">${value}</SPAN>`;

                            if (isTBODY && name === WC.idfield) {
                                if (~~value > WC.maxid) WC.maxid = ~~value;

                                // ** Only execute once PER Row/Table
                                // ** mouseover a pingid and all other tables scroll to the same pingid
                                if (name === ITPingsID && __synchronized_pingID_scrolling) {
                                    __setAttribute(WC.TBODY, 'data-' + name, value);
                                    TR.addEventListener('mouseenter', () => {
                                        let _pingid = TR.getAttribute('data-_pingid');                          // get pingid for this row
                                        let selector = "itpings-table .data-table TR[data-_pingid='" + _pingid + "']";   // find TBODY with this pingid
                                        let TRs = [...document.querySelectorAll(selector)];
                                        TR.backtotop = false;
                                        if (TRs) {
                                            _log('mouseenter', _pingid, TRs, TRs.length);
                                            TRs.map(TRwithPingID => {
                                                if (TRwithPingID !== TR) {
                                                    TRwithPingID.scrollIntoView({
                                                        block: "center", inline: "nearest"
                                                    });
                                                    //TR.parentNode.parentNode.style.paddingTop = '2em';
                                                }
                                                __setBackgroundColor(TRwithPingID, 'chartreuse');
                                            });
                                        }
                                        if (!TR.hasMouseLeaveListener) {
                                            TR.addEventListener('mouseleave', () => {
                                                TRs.map(TRwithPingID => {
                                                    if (TRwithPingID !== TR) {
                                                        //TRwithPingID.scrollIntoView();
                                                        //TR.parentNode.parentNode.style.paddingTop = 'initial';
                                                    }
                                                    __setBackgroundColor(TRwithPingID, 'initial');
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
                            __setInnerHTML(TD, value);

                            return {name, value, TR, TD}
                        };

                        return __Object_keys(row).map(add_TableColumns);
                    };

                    if (WC.idle) {                                                         // not waiting for a JSON response already
                        WC.idle = false;                                                   // true again AFTER fetch processing is done
                        __fetch(WC.uri + filter)
                            .then(json => {
                                let rows = json.result;
                                if (rows) {
                                    if (!WC.idfield) {                                      // first draw of TABLE

                                        let headers = rows[0];                              // first row
                                        let first_column_name = __Object_keys(headers)[0];
                                        if (first_column_name[0] !== '_') console.error(first_column_name, 'might not be a correct Key fieldname return from DBInfo endpoint');
                                        WC.idfield = first_column_name;                     // take from attribute _OR_ first JSON row
                                        add_TableRow(headers);                              // first row keys are the THEAD columnheaders
                                        WC.columns = new Set(rows.forEach(add_TableRow));   // add all rows, register columns

//                                        __appendChild(WC.HEADER, WC.THEAD.cloneNode(true));

                                        __setInnerHTML(WC);                                 // remove Loading...
                                        __appendChild(WC, WC.WRAPDIV);                      // now append that Custom Element to the DOM
                                        _QM.register(WC);                                   // Register my query with the Query Manager, so I get (doPulse) updates

                                    } else if (rows.length) {                             // add new rows in a new TBODY at the top of the TABLE

                                        // append new TBODY, overriding privious WC.TBODY reference
                                        WC.TBODY = __insertBefore(WC.TABLE, __appendChild(WC.TABLE, __createElement('TBODY')), WC.TBODY);
                                        __classList_add(WC.TBODY, 'newPing');              // animate background color of this newPing
                                        let dataNewRows = rows.map(row => add_TableRow(row, 0));            // add rows at top of TBODY
                                        _log(WC.query, 22, dataNewRows);
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
                                WC.requiredColumns.forEach(
                                    columnName => __setCSSproperty(
                                        '--CSSdisplay_' + columnName
                                        , 'table-cell'
                                        , WC.TABLE) // set on this itpings-table (versus document.body) so the column is only displayed in this table
                                );
                            })
                            .catch(error => {
                                console['error'](error);
                            });
                    }
                }

                // noinspection JSUnusedGlobalSymbols
                doPulse(pingID, pulseidfield) {                                            // read Database endpoint, only when there are new ids
                    let WC = this;
                    let maxid = WC.maxid;
                    let idfield = WC.idfield;
                    if (pingID > maxid) {
                        _log(WC.query, 'idfield:', idfield, 'pulseidfield:', pulseidfield, 'maxid:', maxid, '_pingid:', pingID);
                        this.fetchData('&filter=' + idfield + ' gt ' + maxid); // add filter on uri to get only new values
                    }
                }

                // noinspection JSUnusedGlobalSymbols
                attributeChangedCallback(attr, oldValue, newValue) {
                    // ** called for every change for Data-attributes in the HTML tag
                    // ** So also at first creation in the DOM
                    let WC = this;
                    let isConnected = WC.isConnected;                                     // sparsely documented standard property
                    if (attr === __ATTR_data_query) {
                        _WebComponent_ID = newValue;
                        if (!__useLIVE_Data) {
                            this.innerHTML = newValue;
                            return false;
                        }
                        WC.uri = __localPath(newValue);
                        if (_traceCustomElement) _log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED, attr + ' / ' + oldValue + ' / ' + newValue, isConnected ? '' : '►► NOT', 'Connected');
                        WC.idfield = false;
                        WC.idle = true;                                                                    // no new fetch when still waiting or previous one
                        let title = WC['title'];
                        if (WC.WRAPDIV) WC.WRAPDIV.parentNode.removeChild(WC.WRAPDIV);    // remove existing table

                        /**
                         * Create HTML structure:
                         *  DIV .table-wrapper
                         *      CAPTION (title)
                         *      TABLE .sticky-header
                         *          THEAD
                         *      TABLE
                         *          TBODY (newest)
                         *          TBODY (newer)
                         *          TBODY (older)
                         * **/
                        let ITPINGS_DIV = WC.WRAPDIV = __createElement__DIV_Class('', 'table-wrapper');

                        WC.HEADER = __appendChild(ITPINGS_DIV, __createElement('TABLE'));                   // new TABLE inside DIV with WC.TABLE reference
                        __classList_add(WC.HEADER, 'sticky-header');
                        WC.TITLE = __appendChild(WC.HEADER, __createElement('CAPTION'));                    // CAPTION tag inside TABLE
                        __classList_add(WC.TITLE, 'itpings-div-title');                                     // sticky position
                        WC.TITLE.innerHTML = title;
                        WC.THEAD = __appendChild(WC.HEADER, __createElement('THEAD'));                     // add THEAD, references are used to fill data

                        WC.TABLE = __appendChild(ITPINGS_DIV, __createElement('TABLE'));                   // new TABLE inside DIV with WC.TABLE reference
                        __classList_add(WC.TABLE, 'data-table');
                        // no THEAD in data-table, the Header row is in the HEADER Table
                        WC.TBODY = __appendChild(WC.TABLE, __createElement('TBODY'));                     // add (first) TBODY

                        __setInnerHTML(WC, __LoadingTitle(title));

                        // ** There is no data in the <TABLE> yet, data arrives in the async fetch Data call
                        // ** So the Custom Element HTML above will be injected into the DOM in that fetch Data call

                        this.fetchData();  // call ONCE with an empty filter value
                    }
                }

                // noinspection JSUnusedGlobalSymbols
                connectedCallback() {
                    if (_traceCustomElement) _log(__TEXT_CUSTOM_ELEMENT_CONNECTED); // ** Called before Custom Element is added to the DOM
                }
            });
        })(); // function (elementName = 'itpings-table')


        (function (elementName = 'itpings-chart') {
            let _traceCustomElement = true; // for educational purposes, trace specific CustomElement operations to the console
            let _log = (a, b, c, d, e, f, g, h, i) => __log(elementName, 'lightblue', a, b, c, d, e, f, g, h, i);

            let __INTERVALS = new Map();
            // ES6 destructuring, parameter names become keys: {interval, unit, xformat}
            let addInterval = (key, interval, unit, xformat) => __INTERVALS.set(key, {interval, unit, xformat});
            addInterval('5m', 5, __STR_MINUTE, __DATETIME_TIME);
            addInterval('30m', 30, __STR_MINUTE, __DATETIME_TIME);
            addInterval('1H', 1, __STR_HOUR, __DATETIME_TIME);
            addInterval('1H', 1, __STR_HOUR, __DATETIME_TIME);
            addInterval('2H', 2, __STR_HOUR, __DATETIME_TIME);
            addInterval('6H', 6, __STR_HOUR, __DATETIME_TIME);
            addInterval('1D', 1, __STR_DAY, __DATETIME_TIME);
            addInterval('2D', 2, __STR_DAY, __DATETIME_DATETIME);
            addInterval('7D', 7, __STR_DAY, __DATETIME_DATETIME);
            addInterval('2W', 2, __STR_WEEK, __DATETIME_DATETIME);
            addInterval('1M', 1, __STR_MONTH, __DATETIME_DATE);
            addInterval('6M', 6, __STR_MONTH, __DATETIME_DATE);
            addInterval('1Y', 1, __STR_YEAR, __DATETIME_DATE);

            let __INTERVAL_DEFAULT = __INTERVALS.get('6H');

            window.customElements.define(elementName, class extends HTMLElement {
                //region ========================================================== Custom Element Getters/Setters
                // noinspection JSUnusedGlobalSymbols
                static get observedAttributes() {
                    let _observedAttributes = [__ATTR_data_sensorname, __ATTR_data_interval];
                    _log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTES, _observedAttributes);
                    return _observedAttributes;
                }

                get title() {
                    return __getAttribute(this, __ATTR_data_title);
                }

                set title(newValue) {
                    __setAttribute(this, __ATTR_data_title, newValue);
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
                    WC.idle = false;
                    let intervalDIV = WC.INTERVALS.querySelector(`[id='${newValue}']`);
                    let sensorname = WC.sensorname;
                    __localstorage_Set(WC.localStorageKey, newValue);

                    __toggleClasses([...this.INTERVALS.children], intervalDIV, 'selectedInterval'); //loop all interval DIVs , add or remove Class: selectedInterval
                    let intervalDefinition = WC.__INTERVAL = __INTERVALS.has(WC.interval) ? __INTERVALS.get(WC.interval) : __INTERVAL_DEFAULT;

                    //todo use faster SensorValues_Update query   let sensor_ids = (sensorname === 'temperature_5') ? "7,14" : "6,13";
                    if (ITPings_graphable_PingedDevices_Values.includes(sensorname)) {
                        WC.query = 'PingedDevices';
                        WC.uri = __localPath(WC.query);
                        WC.value_field_name = sensorname;
                    }
                    else {
                        WC.query = 'SensorValues';
                        WC.uri = __localPath(WC.query + '&sensorname=' + sensorname);
                        WC.value_field_name = 'sensorvalue';
                    }
                    WC.uri += '&orderby=created&interval=' + intervalDefinition.interval;
                    WC.uri += '&intervalunit=' + intervalDefinition.unit + '&limit=none&maxrows=' + __DEFAULT_MAXROWS;


                    WC.ChartJS_Lines = [];                             // Array, index number is used to register devices

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
                            tooltips: {
                                mode: 'index',
                                intersect: false
                            },
                            hover: {
                                mode: 'nearest',
                                intersect: true,
                            },
                            legend: false,
                            showLines: true,
                            elements: {
                                line: {
                                    tension: 0
                                }
                            },
                            // scales: {
                            //     xAxes: [{
                            //         display: false,
                            //         scaleLabel: {
                            //             display: false,
                            //             labelString: 'Month'
                            //         }
                            //     }],
                            //     yAxes: [{
                            //         display: true,
                            //         scaleLabel: {
                            //             display: true,
                            //             labelString: sensorname
                            //         },
                            //     }]
                            // }
                        }
                    });

                    _QM.register(WC);

                    this.fetchData();
                }

                //endregion

                fetchData(filter = '') {
                    let WC = this;
                    __setInnerHTML(WC.TITLE, __LoadingTitle(WC.title || WC.sensorname));
                    WC.idle = false;
                    WC.INTERVALS.style.display = 'none';
                    __fetch(WC.uri + filter)
                        .then(json => {
                            let rows = json.result;
                            let ChartJS_data = WC.ChartJS.data;
                            let ChartJS_data_datasets = WC.ChartJS.data.datasets;
                            if (rows[0] !== null) { // fixme: Why is the result from the DB [null] sometimes?
                                rows.map(row => {
                                    WC[ITPingsID] = row[ITPingsID];                                     // keep track of hightest Primary Key value
                                    let x_axis_time = __MOMENT(row.created, WC.__INTERVAL.xformat);     // format x-axis label with timestamp
                                    if (!ChartJS_data.labels.includes(x_axis_time)) {                   // prevent duplicate timelables on x-axis
                                        ChartJS_data.labels.push(x_axis_time);

                                        let lineID = row.dev_id;
                                        let dataset_idx = WC.ChartJS_Lines.indexOf(lineID);
                                        if (dataset_idx < 0) {                                          // ** add new device
                                            dataset_idx = ChartJS_data_datasets.length;
                                            let deviceColor = DeviceColors.getColor(lineID);            // get dictinct color from Map
                                            ChartJS_data_datasets.push({                                // add one new line/device data to ChartJS
                                                label: __abbreviated_DeviceID(lineID)
                                                , fill: false
                                                //, hidden: true
                                                //, lineTension: .5
                                                , backgroundColor: deviceColor
                                                , borderColor: deviceColor
                                                , data: []
                                            });
                                            WC.ChartJS_Lines.push(lineID);                              // unique sensorid per WC
                                        }
                                        ChartJS_data_datasets[dataset_idx].data.push({
                                            x: x_axis_time,
                                            y: row[WC.value_field_name]
                                        });

                                    }

                                });
                                WC.ChartJS.update();
                            }
                            this.idle = true;
                            WC.INTERVALS.style.display = 'initial';
                            __setInnerHTML(WC.TITLE, WC.title || WC.sensorname);
                        });
                }

                // noinspection JSUnusedGlobalSymbols
                doPulse(pingID) {
                    let WC = this;
                    let current_ping_id = ~~WC[ITPingsID];
                    if (current_ping_id && WC.idle) {
                        if (current_ping_id < pingID) {
                            _log('doPulse Chart JS current_ping_id:', current_ping_id, 'new:', pingID);
                            this.fetchData(`&filter=${ITPingsID}%20gt%20${current_ping_id}`);
                        }
                    } else {
                        _log('►►► No pingid on Chart JS yet (not drawn yet) ◄◄◄');
                    }
                }

                // noinspection JSUnusedGlobalSymbols
                constructor() {
                    super();
                }

                // noinspection JSUnusedGlobalSymbols
                attributeChangedCallback(attr, oldValue, newValue) {
                    let WC = this;
                    let isConnected = WC.isConnected;
                    switch (attr) {
                        case(__ATTR_data_interval):
                            if (isConnected) {
                                if (_traceCustomElement) _log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED, attr + ' / ' + oldValue + ' / ' + newValue, isConnected ? '' : '►► NOT', 'Connected');

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
                    let sensorname = WC.sensorname;
                    if (!__useLIVE_Data) {
                        this.innerHTML = this.sensorname;
                        return;
                    }

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

