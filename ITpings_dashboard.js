/**
 * @license MIT (C) ITpings.nl - Danny Engelman
 *
 * */

//todo: Do not not a graph line between two time points if there where no measurements for an N timeperiod
//todo: debug double gateways - activated event trace
//todo: plot coordinates
//todo: fixed alt in PingedGateways
//todo: link Gateway to TTNmapper: https://ttnmapper.org/colour-radar/?gateway=ttn_365csi_nl&type=radar
//todo: monitor when last ping was received (dashboard reports TTN server offline status)
//todo: preload other graph intervals (download largest then proces in WebWorker)

//todo: add Kalman filter : https://www.wouterbulten.nl/blog/tech/lightweight-javascript-library-for-noise-filtering/

//todo audio sounds for (new) events
//todo: (low) remove cached data when deleted from DB
//todo: (low) detect foreign traffic (wait for TTN V3)

//unoffical TTN endpoints
//https://www.thethingsnetwork.org/gateway-data/location?latitude=52.316&longitude=4.66040850&distance=2000
//http://noc.thethingsnetwork.org:8085/api/v2/gateways/ttn_365csi_nl

/**
 * Functions and Variables starting with $_ are globals (replacing the need for jQuery, Lodash or Underscode)
 *
 *   Use a decent IDE, like JetBrains, Atom or VSCode
 *   Learn to collapse regions/code-blocks with Alt-7 (code structure view) Ctrl-Plus and Ctrl-Minus
 *   press Ctrl-Shift-Minus now to collapse all code
 *   press Ctrl-Shift-Plus TWICE to uncollapse all code
 *   Use Ctrl-B to browse code by reference
 *
 *   This Dashboard uses hardcoded Databases references
 *   If you make changes in the Database Schema, be sure to check those new names in this Dashboard source code
 *
 * **/

//region ========================================================== F12 Console traces with lots of colors

(console.log(function () {
    window.$l = function () {// development code
        console.log(...arguments);
    };
    return 'Initialized ITpings debugging functions';
}()));

/**
 * See F12 console when running ITpings Dashboard
 *
 * eg: $_log( "mySection.red", data )
 * **/
$_log = function () { // NO arrow function, then this scope will be the window, and there are no arguments in arrow function
    let params = Array.from(arguments);                                     // get all parameters and proces first parameter as label/CSS
    let label = String(params.shift()).split('.');                          // cast first parameter to String, then split on period
                                                                            // define optional second backgroundcolor after dot (.)
    // first %c gets second param CSS, next %c gets third param CSS, etc
    let firstCSS = "background:lightgreen;";
    let labelCSS_background = "background:" + (label[1] || "green");
    let labelCSS_color = ";color:" + (label[2] || "white");
    let labelCSS_fontweight = (label[2] === 'black' || label[3] === 'bold') ? ";font-weight:bold" : "";
    let secondCSS = labelCSS_background + labelCSS_color + labelCSS_fontweight;
    params = ["%c ITpings %c " + label[0] + " ", firstCSS, secondCSS, ...params];
    console.log(...params);
};

$_logerror = function (err) {
    // remove empty function: https://github.com/mishoo/UglifyJS2/issues/506
    console.log("%c Error : %c " + (((new Error().stack).split("at ")[2]).split('(https')[0])
        , "background:red;color:yellow;font-weight:bold", "background:teal;color:white;"
        , function () {
            return err;
        }());
};

$_log.rows = function (rows, idfield = $_DEF.ID) {
    // remove empty function: https://github.com/mishoo/UglifyJS2/issues/506
    console.log("%c ITpings Rows : %c " + (((new Error().stack).split("at ")[2]).split('(https')[0])
        , "background:gold;font-weight:bold", "background:teal;color:white;"
        , function () {
            let firstrow = rows[0];
            let lastrow = rows[rows.length - 1];

            return rows.length + " rows , ids: " + firstrow[idfield] + " - " + lastrow[idfield];
        }());
};

//endregion ======================================================= F12 Console traces with lots of colors

//region ========================================================== learning to code without jQuery, Underscore or Lodash

$_emptyString = '';

// Tiny, recursive autocurry
const $_curry = (f, arr = []) => (...args) => (a => a.length === f.length ? f(...a) : $_curry(f, a))([...arr, ...args]);
// noinspection JSUnusedGlobalSymbols
const $_compose = (...fns) => x => fns.reduceRight((v, f) => f(v), x);
// noinspection JSUnusedGlobalSymbols
const $_pipe = (...fns) => x => fns.reduce((y, f) => f(y), x);

let $_isDefined = x => typeof x !== "undefined";

// noinspection JSUnusedGlobalSymbols
/** String **/
let $_StrPad = (str, len, char) => (char.repeat(len) + str).slice(-1 * len);
// noinspection JSUnusedGlobalSymbols
let $_StrReverse = x => [...x].reverse().join``;
let $_isString = x => typeof x === "string";

/** Number **/
let $_isNumber = x => typeof x === "number";
let $_absolute = x => (x ^ (x >> 31)) - (x >> 31);  // faster than Math.abs or unary expresssion

// noinspection JSUnusedGlobalSymbols
/** Array **/
let $_ArrayFrom = x => Array.from(x);
let $_isArray = x => Array.isArray(x);
let $_last = array => (array && array.slice(-1)[0]);
let $_lastNelements = (arr, n) => arr.slice(-1 * n);
let $_length = x => $_isArray(x) ? x.length : false;
let $_isEmptyArray = x => $_length(x) === 0;
let $_isNotEmptyArray = x => $_length(x) > 0;
let $_hasValue = (x, y) => x.indexOf(y) > -1;

// noinspection JSUnusedGlobalSymbols
let $_ArrayPushEnd = (arr, val) => arr.push(val);
// noinspection JSUnusedGlobalSymbols
let $_ArrayPushStart = (arr, val) => {
    arr.unshift(val);
    return arr;
}

/** CSV **/
let $_CSV2Array = x => x.split`,`;

/**
 * Convert a CSV string -> array  OR Array -> CSV string
 * @return {Array || string}
 */
$_CSV_convert = function (csv, newline = '\n', keys = false, comma = ',') {
    let Objkeys = x => Object.keys(x);
    //let commaLine = x => x.join(comma);
    if (Array.isArray(csv)) {
        /** return String with {} Objects as Lines **/
        csv = [
            Objkeys(csv[0]).join(comma)                                         // first array element = Object keys
            , ...csv.map(row => Objkeys(row).map(key => row[key]).join(comma))  // spread all lines
        ].join(newline);
    } else {
        /** return Array with {} Objects **/
        if (typeof csv === 'string' && csv.includes(newline))                   // do not process single line Strings
            csv = csv.split(newline).reduce((csv_arr, line, linenr) => {
                line = line.split(comma);
                if (linenr < 1 && !keys) keys = line;                           // first line are the header keys, or use keys parameter
                else csv_arr.push(line.reduce(                                  // convert Array to Object
                    (obj, val, idx) => {
                        let key = keys[idx];
                        //obj[key] = val;                                       // CSV val is a string!
                        if (key === '1created' || key === '1time') obj[key] = new Date(val);
                        else obj[key] = isNaN(val) ? val : Number(val);              // save Numbers as Numbers!
                        return obj;
                    }
                    , {}));                                                     // start with empty {} Object
                return csv_arr;
            }, []);
    }
    return csv;
};


/** Object **/
let $_hasOwnProperty = (obj, key) => obj && obj.hasOwnProperty(key);


/** Map **/
let $_newMap = x => new Map(x);

/** Set **/
let $_newSet = x => new Set(x);

let $_JSONstringify = x => JSON.stringify(x);

/** window methods **/
let $_setTimeout = (func, delay) => window.setTimeout(func(), delay);

/** DOM methods **/
let $_forceDOMupdate = () => window.setTimeout(void(false), 1);

let $_getElementById = element_id => document.getElementById(element_id);
let $_querySelectorAll = (selector, parent = document) => [...parent.querySelectorAll(selector)];

/** Attributes **/
let $_getAttribute = (element, property) => element.getAttribute(property);
let $_setAttribute = (element, property, value) => element.setAttribute(property, value);
// noinspection JSUnusedGlobalSymbols
let $_removeAttribute = (element, attr) => element.removeAttribute(attr);
// noinspection JSUnusedGlobalSymbols
let $_setAttributes = (element, arr) => $_Object_keys(arr).map((property) => element.setAttribute(property, arr[property]));

// noinspection JSUnusedGlobalSymbols
/** create DOM elements **/
let $_createDocumentFragment = () => document.createDocumentFragment();
let $_createElement = element_type => document.createElement(element_type);
// noinspection CommaExpressionJS
let $_createDIV = (html, element = $_createElement("DIV")) => (html && (element.innerHTML = html), element);
// noinspection CommaExpressionJS
let $_createDIV_withClass = (html, className, element = $_createDIV(html)) => (element.classList.add(className), element);
// noinspection CommaExpressionJS
let $_createDIV_with_id = (html, id, DIV = $_createDIV(html)) => ($_setAttribute(DIV, "id", id), DIV);

// noinspection CommaExpressionJS
let $_innerHTML = (element, html = $_emptyString) => element && (element.innerHTML = html, element);                 // return element
let $_innerHTMLById = (element_id, html) => {
    let element = $_getElementById(element_id);
    $_innerHTML(element, html);
    return element;                 // return element
};
// noinspection JSUnusedGlobalSymbols
let $_replaceInnerHTML = (oldDiv, html) => {
    let newDiv = oldDiv.cloneNode(false);
    newDiv.innerHTML = html;
    oldDiv.parentNode.replaceChild(newDiv, oldDiv);
};

// noinspection JSUnusedGlobalSymbols
let $_childElementCount = element => element && element.childElementCount;

let $_appendChild = (parent, child) => parent.appendChild(child);
// noinspection JSUnusedGlobalSymbols
let $_insertBefore = (parent, child, referenceNode) => parent.insertBefore(child, referenceNode);
// noinspection JSUnusedGlobalSymbols
let $_removeLastChild = parent => parent.removeChild(parent.lastElementChild);

let $_importNode = (nodeId) => document.importNode($_getElementById(nodeId).content, true);

let $_hideElement = element => element.style.display = 'none';
let $_showElement = (element, displaysetting = 'block') => element.style.display = displaysetting;

let $_classList = element => element.classList;
// noinspection JSUnusedGlobalSymbols
let $_classList_add = (element, classStr) => $_classList(element).add(classStr);
// toggle a className for N elements (selected/unselected)
let $_toggleClass = (element, className) => $_classList(element).toggle(className);
let $_toggleClasses = (elements, selectedElement, className) => elements.map(x => x.classList[x === selectedElement ? "add" : "remove"](className));

let $_setCSSproperty = (property, value, el = document.body) => el.style.setProperty(property, value);
//Chrome CSS Type Object model
// noinspection JSUnusedGlobalSymbols
let $_setattributeStyleMap = (property, value, el = document.body) => el.attributeStyleMap.set(property, value);

let $_setBackgroundColor = (el, color) => el.style.backgroundColor = color;

let $_Object_keys = x => Object.keys(x);

let $_style_display = (el, value = 'none') => el.style.display = value;

let $_addEventListener = (event, func) => window.addEventListener(event, func);

let $_localstorage_Get = (key, defaultvalue = false, stored = localStorage.getItem(key)) => stored ? stored : defaultvalue;
let $_localstorage_Set = (key, value) => localStorage.setItem(key, $_isString(value) ? value : $_JSONstringify(value));

// let $_colors: {
//     "blue" : "#0000ff,#3232ff,#6666ff,#9999ff,#ccccff".split()
// };

//localstorage
class LocalStorageManager {
    // noinspection JSUnusedGlobalSymbols
    /**
     *
     * **/
    static _log() {
        $_log && $_log("LocalStorageManager.orangered", ...arguments);
    }

    static item(value) {                        // Map entry storage object
        return {value, timestamp: new Date()}
    }

    static JSONconvert(value) {/** .parse or .stringify **/
        return JSON[typeof value === 'string' ? 'parse' : 'stringify'](value);
    }

    constructor() {
        this.memory_storage = new Map();
        $_Object_keys(localStorage).forEach(key => this.set_memory_storage(key));

        let size = 0;
        $_Object_keys(localStorage).forEach(key => size += localStorage.getItem(key).length);
        LocalStorageManager._log('total localStorage size:', size, 'bytes')

    }

    has(key) {
        return this.memory_storage.has(key);   // Map.get
    }

    set_memory_storage(key, value) {
        return this.memory_storage.set(key, value ? LocalStorageManager.item(value) : localStorage.getItem(key));
    }

    delete(key) {
        localStorage.removeItem(key);
        return this.memory_storage.delete(key);
    }

    set(key, value, datatype = false, isString = typeof value === 'string') {
        try {
            let temp__savedPercentage = false;
            let temp__rowcount = false;
            if (!datatype && !isString) datatype = 'JSN';

            switch (datatype) {
                case 'JSN':
                    value = datatype + ":" + LocalStorageManager.JSONconvert(value);
                    break;
                case 'CSV':
                    let csv = $_CSV_convert(value);
                    let JSONlength = LocalStorageManager.JSONconvert(value).length;
                    temp__savedPercentage = ~~((csv.length / JSONlength) * 100);
                    temp__rowcount = ~~value.length;
                    value = datatype + ":" + csv;
                    break;
                default:
                    value = isString ? value : LocalStorageManager.JSONconvert(value);
                    break;
            }
            localStorage.setItem(key, value);
            this.set_memory_storage(key, value);
            LocalStorageManager._log("set:", key, temp__rowcount ? temp__rowcount + " rows," : "", value.length, "Bytes ", temp__savedPercentage ? `CSV = ${temp__savedPercentage}% reduction` : "");
        } catch (e) {
            console.error("set", key, value.length, '\n', e); // todo: catch QuotaExceeded Error
        }
    }

    get(key, defaultvalue = false) { // localStorage.getItem
        let stored = this.memory_storage.get(key) && this.memory_storage.get(key).value;
        let value = localStorage.getItem(key);
        if (value) {
            switch (value.slice(0, 3)) {
                case 'JSN':
                    value = JSON.parse(value.slice(4));
                    return value;
                    break;
                case 'CSV':
                    value = $_CSV_convert(value.slice(4));
                    return value;
                    break;
                default:
                    LocalStorageManager._log(key, stored, value);
            }
            return value;
        }
        //if (localstored) return localstored;
        if (stored) return stored;
        return defaultvalue;
    }

    getJSON(key) {
        return LocalStorageManager.JSONconvert(this.get(key));
    }

    setJSON(key, value) {
        thi.set(key, LocalStorageManager.JSONconvert(value));
    }
}

$_LSM = new LocalStorageManager();
// var managed = $_LSM.storage.get(key);
// var get = $_LSM.get(key);

let $_localPath = (x, basePath = 'ITpings_connector.php?query=') => {
    let uri = location.href.split('#')[0];          // discard routes
    uri = uri.split`/`;                             // get endpoint from current uri location
    uri.pop();                                      // discard filename
    uri.push(basePath + x);                         // stick on basePath
    uri = uri.join`/`;
    return uri;
};

//region ========================================================== Application Constants / definitions

let $_app_custom_element_Namespace = "itpings-";                                // W3C standard Custom Elements need their own Namespace

let $_app__QueryManager;                                                         // manage all (async) queries from/for multiple tables/charts (if A has just called for data X, then B can use it as well)
let $_app__Router;                                                       // Simple SPA router

let $_app_ITpings_data_tables = ['Temperature', 'Luminosity', 'Battery'];

let $_DEF = {                                                               // Single global, so properties can be mangled by Uglify

    // matching definitions in PHP/MySQL
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

    sensor: "sensor",
    LastSeen: "LastSeen",

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

/**
 * Endpoints query=[endpoint]
 * See F12 console 'Fetch API' references for clickable URIs
 * **/
let $_app_single_PingID_endpoint = "PingID";    // Smallest payload 256 Bytes, but only gets max(_pingid)
let $_app_DB_IDs_endpoint = "IDs";          // All (new) IDs (only called when there is a new _pingid)

let ITpings_last_pingid = false;        // last received _pingid

/**
 * sensor names that can query data from PingedDevices
 * **/
let ITPings_graphable_PingedDevices_Values = "frequency,snr,rssi,channel".split`,`;

/**
 * With multiple tables displayed, auto scroll all tables to the same _pingid
 * **/
let __synchronized_pingID_table_scrolling = false;


let __TEXT_QUERYMANAGER_CANT_REGISTER = " QueryManager can't register";

let __TEXT_CUSTOM_ELEMENT_CONSTRUCTOR = "CustomElement constructor";
let __TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED = "CustomElement attributeChanged:";
let __TEXT_CUSTOM_ELEMENT_CONNECTED = "CustomeElement connectedCallback";

//endregion ======================================================= Application Constants / definitions

//region ========================================================== Application Functions

let $_hasResultArray = json => json && json.hasOwnProperty($_DEF.ITpings_result);
let $_isCachedJSON = json => json && json.hasOwnProperty($_DEF.ITpings_cached);
let $_getResultArray = json => json && json[$_DEF.ITpings_result];

let $_HTML_clickable_pingid = pingid => `<A target=_blank HREF=ITpings_connector.php?query=ping&_pingid=${pingid}>${pingid}</A><A target=_blank HREF=ITpings_connector.php?query=DeletePingID&_pingid=${pingid}> X </A>`;
let $_HTML_LoadingTitle = (x, y = "Loading: ", z = $_emptyString) => `<DIV class="loading">${y} ${x} ${z}</DIV>`;

//endregion ======================================================= Application Functions


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
                window.setTimeout(ready, 1);
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

)(); // modify this line to pass in your own method name and object for the method to be attached to

/**
 * Fetch API wrapper, can handle cached (localstorage) data
 * will fetch data if no cached data is available
 *
 * uri - full uri, see F12 console for clickable links
 * cacheKey - string localstorage Key
 * **/
let $_fetch = (uri, cacheKey = false) => {    // Async/Await?
    function _log() {                                                                       // ES6 arrow functions can not do ...arguments!
        $_log("Fetch API.firebrick", ...arguments);
    }

    let shortURI = uri.replace($_localPath($_emptyString), $_emptyString);                  // truncate local path

    let isSinglePingID = uri.includes('query=' + $_app_single_PingID_endpoint);
    let encodedURI = uri.replace(/ /g, '%20');                                              // can't use encodeURI(), it will encode existing %
    if (!isSinglePingID) _log("Fetching from ", cacheKey ? "Cache:" + cacheKey : "Server", cacheKey ? "" : "\n" + encodedURI);

    return new Promise((resolve, reject) => {                                               // return Promise to calling function
        if (cacheKey) {                                                                     // localstorage string key
            let json = $_LSM.get(cacheKey, {});                                             // get cached data from localstorage

            // CSV from localStorage does not have result structure yet. todo: move this into localeStorageManager
            let has_pingid = $_hasOwnProperty(json[0], $_DEF.ID);
            if (!$_hasResultArray(json) && json.length > 0 && json[0] && has_pingid) {   // first row has a _pingid key
                _log('converting to ITpings json with result array');
                json = {
                    result: json
                }
            }

            if ($_hasResultArray(json)) {                                                   // if it has a "result" key
                _log('Using ', $_length(json.result), ' results cached data for:', cacheKey);  // it is cached data
                //_log('first cached row:', json.result[0]);
                resolve(json);
            } else {
                //todo get data from static CSV file
                _log('No cache data for:', cacheKey);                                       // no data
                cacheKey = false;                                                           // continue with fetch in next if block
            }
        }
        if (!cacheKey) {
            fetch(uri)                                                                      // async fetch
                .then(response => {
                    if (response.ok) {
                        //todo check if data is CSV encoded data
                        return response.json();
                    } else {
                        $_log('$_fetch error ' + response.status + ' ' + response.statusText + ".red.white", uri, response);
                        return response;
                    }
                })
                .then(json => {
                    if ($_hasResultArray(json)) {                                           // "result" key in json?
                        if (json.result) {
                            let JSONsize = $_JSONstringify(json).length;
                            $_log("Fetched (" + shortURI + ").firebrick", $_length(json.result), "rows , ", JSONsize, 'bytes', "\n", encodedURI);
                        } else {
                            $_log("Fetch Error.red", json.sql);
                            console.error(json.errors[0]);
                            console.error(json.errors);
                        }
                    } else {
                        // update UI for single ping value
                        if ($_isNumber(json)) {
                            ITpings_last_pingid = json;
                            $_getElementById('heartbeat_ping').innerHTML = $_HTML_clickable_pingid(json);
                        }
                    }
                    resolve(json)
                })
                .catch(error => {
                    reject($_log(error + '.red.yellow', shortURI));
                    console.error(error);
                });
        }
    })
};

//endregion ======================================================= learning to code without jQuery, Underscore or Lodash

//region ========================================================== $_datetime functions, no need for Moment or Date-Fns

// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/toLocaleDateString
// noinspection JSCheckFunctionSignatures
let $_Date = (x = false) => x ? new Date(x) : new Date();
let $_Date_getTime = (x) => $_Date(x).getTime();
// noinspection JSUnusedGlobalSymbols
let $_isDate = x => Object.prototype.toString.call(x) === '[object Date]';
let $_dateLocale = navigator.language;                                              // get browser(system) language
let $_dateFormat_Hmm = "H:mm";
let $_dateFormat_DMMM = "D MMM";
let $_dateFormat_DMMMHmm = "D MMM H:mm";
let $_date_Default_ShortDate = {month: 'short', day: 'numeric'};                    // native JS .toLocaleDateString options
let $_date_Default_LongDate = {month: 'long', day: 'numeric', year: 'numeric'};     // according to locale: DD MMMM YYYY or MMMM DD YYYY
let $_dateTimeDefault = {hour: '2-digit', minute: '2-digit', hour12: false};        // 23:21

let $_dateEnsureDate = (date = $_emptyString) => $_isString(date) ? $_Date(date) : date;
let $_dateTimeStr = (date, options = $_dateTimeDefault, locale = $_dateLocale) => $_dateEnsureDate(date).toLocaleTimeString(locale, options);

/**
 * date - date string or Date object
 * format - (optional) format
 * locale - (optional) language locale
 * returns formatted string
 * **/
function $_dateStr(date, format = $_dateFormat_DMMMHmm, locale = $_dateLocale) {
    date = $_dateEnsureDate(date);
    let localeDate = options => date.toLocaleDateString(locale, options);       // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/toLocaleDateString
    let padded = date => ("0" + date).slice(-2);                                // pad 9 minutes to 09 minutes
    if (format === $_dateFormat_DMMM) {
        return localeDate($_date_Default_ShortDate);
    } else if (format === $_dateFormat_Hmm) {
        return padded(date.getHours()) + ":" + padded(date.getMinutes());
    } else if (format === $_dateFormat_DMMMHmm) {
        return $_dateStr(date, $_dateFormat_DMMM) + " " + $_dateTimeStr(date);
    } else {
        return localeDate(format) + " " + $_dateTimeStr(date);
    }
}

/**
 * date - date string or Date Object
 * interval - (optional) interval in minutes (default) (day = * 1000 = 864e5)
 * returns offset/interval
 * **/
let $_dateSince_Interval = (toDate, fromDate = $_Date_getTime(), interval = 864e2) => Math.floor(($_Date_getTime(toDate) - fromDate) / interval);  // 0=today , negative for past days, positive for future days
let $_dateMinutesSince = date => $_dateSince_Interval(date);
// noinspection JSUnusedGlobalSymbols
let $_dateDayssSince = (fromDate, toDate = null) => $_dateSince_Interval(fromDate, null, 864e5);

//endregion ======================================================= $_datetime functions, no need for Moment or Date-Fns


!(function (window, document, localStorage) {                     // minify globals inside IIFE

    if (!window.customElements) {                                               // detect Browser that do not support
        window.setTimeout(function () {
            document.body.innerHTML = "<h1>This Browser does not support <a href=https://developer.mozilla.org/en-US/docs/Web/Web_Components/Using_custom_elements>W3C customElements</a> yet<br>Use Chrome, or FireFox (may 2018)</h1>";
        }, 1000);
        return;
    }

    // create extra Event listeners for debugging purposes
    //https://developer.mozilla.org/en-US/docs/Web/Events
    ['DOMContentLoaded', 'hashchange', 'load', 'click', 'focus', 'blur'].map(evt => window.addEventListener(evt, () => $_log('Event: ' + evt, event ? event.target : $_emptyString)));


//region ========================================================== GEO functions

    // nearbyGateways(52.4, 4.87);  // latitude, longitude

    function nearbyGateways(lat, lon, meters = 15000) {
        fetch(`https://www.thethingsnetwork.org/gateway-data/location?latitude=${lat}&longitude=${lon}&distance=${meters}`)
            .then(response => response.json())
            .then(json => {
                let $_Distance = (lat1, lon1, lat2, lon2, accuracy = 1e3) => {  // Haversine distance
                    let M = Math, C = M.cos, P = M.PI / 180,
                        a = 0.5 - C((lat2 - lat1) * P) / 2 + C(lat1 * P) * C(lat2 * P) * (1 - C((lon2 - lon1) * P)) / 2;
                    return M.round(M.asin(M.sqrt(a)) * 12742 * accuracy) / accuracy;// 12742 = Earth radius KMs * 2
                };
                console.table(Object.keys(json).map(id => {
                    let gtw = json[id];
                    gtw.distance = $_Distance(lat, lon, gtw.lat = gtw.location.latitude, gtw.lon = gtw.location.longitude);
                    return gtw;
                }), ["id", "owner", "description", "last_seen", "lat", "lon", "distance"]);
            });
    }

//endregion ======================================================= GEO functions

//region ========================================================== StyleManager : manage <STYLE> definitions in DOM

// TTN Node names get a distinct color in Tables and Graphs

    class StyleManager {
        // noinspection JSUnusedGlobalSymbols
        static _log() {
            $_log("Router.seagreen", ...arguments);
        }

        constructor(id) { //cheeky way of omitting let declaration, saving 4 bytes in Uglify
            //let _StyleManager = this;

            // Get a single (existing!!) STYLE definition from DOM  (dynamically added STYLE tags are not available in the .styleSheets Object!)
            // CSSStyleSheet does not have an id
            this.STYLE = [...document.styleSheets].find(sheet => sheet.ownerNode["id"] === id);
            this.devicesMap = $_newMap(); // _devid -> dev_id
            this.deviceColor = $_newMap();
            // 20 Distinct colors: https://sashat.me/2017/01/11/list-of-20-simple-distinct-colors/
            this.colors = $_CSV2Array("#e6194b,#0082c8,#f58231,#911eb4,#46f0f0,#f032e6,#d2f53c,#fabebe,#008080,#e6beff,#aa6e28,#fffac8,#800000,#aaffc3,#808000,#ffd8b1,#000080,#808080,#ffe119");
        }

        addDevice(device) {
            let _devid = device[$_DEF.ITpings_devid];
            let dev_id = device[$_DEF.dev_id];
            this.devicesMap.set(_devid, dev_id);
            let color = this.getColor(dev_id);
            $_log('DeviceColor: ' + _devid + " = " + dev_id + '.' + color);
        }

        getColor(dev_id) {  // store distinct color PER device
            let _StyleManager = this;
            let color;
            let deviceColor = _StyleManager.deviceColor;
            if (parseInt(dev_id)) dev_id = _StyleManager.devicesMap.get(String(dev_id));    // Map key is a string, not a number
            if (deviceColor.has(dev_id)) {
                color = deviceColor.get(dev_id);
            } else {
                color = _StyleManager.colors.shift();
                deviceColor.set(dev_id, color);
                _StyleManager.STYLE.insertRule(`span[data-${$_DEF.dev_id}='${dev_id}']::before{background:${color}}`, 0);
            }
            return color;
        }
    }

//endregion ======================================================= StyleManager

//region ========================================================== QueryManager for all itpings-table & itpings-chart Custom Elements

    /** display date/time **/
    window.setInterval(() => {
        $_innerHTMLById('heartbeat_time', $_dateStr($_Date(), $_date_Default_LongDate));
    }, 5000); // half a minute update

    /**
     * Heartbeat - Polling Server for new data,
     *  first a single pingid, then each table/chart polls the server for new data (if required!)
     *
     * lower setting doesn't speed up because the single new pingid triggers a second (slower) request (eg. PingedDevices WHERE pingid > currentid )
     * could be enhanced if the endpoint returns the whole _pingid record, but that requires refactoring of tables/chart
     * **/
    let __heartbeat_default = 500;              // 0.5 second
    let __heartbeat_blurred = 1000 * 60 * 5;    // 5 minutes
    let __heartbeat_msecs;                      // current heartbeat, polling the back-end for new data
    let __heartbeat_windowInterval = 0;

    let __setHeartbeat = new_heartbeat_msecs => {
        __heartbeat_msecs = new_heartbeat_msecs;                                                    // global

        let heartbeat_display = __heartbeat_msecs / 1000 + "s";                                     // display time in seconds or minutes
        if (__heartbeat_msecs > 6000) heartbeat_display = __heartbeat_msecs / 60000 + "m";
        $_log("Change Heartbeat:.orange.black", heartbeat_display);

        $_innerHTMLById('heartbeat', heartbeat_display);

        window.clearInterval(__heartbeat_windowInterval);
        __heartbeat_windowInterval = window.setInterval(() => {
            $_toggleClass($_getElementById('heartbeat_heart'), 'heartbeating');
            $_app__QueryManager.pollServer($_app_single_PingID_endpoint);                                        // Query Manager notifies all tables/charts
        }, __heartbeat_msecs);

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
            $_log("QueryManager.purple", ...arguments);
        }

        constructor(_QM = this) {
            _QM.maxid = 0;                          // record MAX(_pingid), higher value will cause all registered tables/graphs to update
            _QM["pulse"] = $_newMap();  // store all tables/graphs query endpoints AND PrimaryKey fields here
        }

        register_for_pollServer(WC) {// register a new query
            /**
             * For each WebComponent, register which endpoint and db fieldname to poll
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
            let _QM = this, datasrc, idfield, setting, _IQMap, datasrcMap;
            setting = $_getAttribute(WC, "pulse"); // eg: pulse="SensorValues:_pingid"
            if (setting) {
                /**
                 * These CAN be configured as data arribute on the itpings-table tag
                 * <itpings-table query="PingedDevices" pulse="PingedDevices:_pingid">
                 * **/
                [datasrc, idfield] = setting.split`:`;                          // ES6 Destructuring
            } else {                                                            // determine from query="..."
                /**
                 * Easier to (auto configure) query="xxx" name and the FIRST column name in the retrieved table
                 * <itpings-table query="PingedDevices">
                 * **/
                datasrc = WC.query || $_getAttribute(WC, "query");
                idfield = $_DEF.ID;                 // FIRST column in itpings-table
            }
            if (!datasrc) console.error(__TEXT_QUERYMANAGER_CANT_REGISTER, WC);
            _IQMap = _QM["pulse"];
            if (!_IQMap.has(datasrc)) _IQMap.set(datasrc, $_newMap());          // every datasrc gets its own Map (so 'can' store muliple PrimaryKeys
            datasrcMap = _IQMap.get(datasrc);                                   // Sorry... looking back I should have simplified this
            if (!datasrcMap.has(idfield)) datasrcMap.set(idfield, $_newSet());  // I thought, too soon, about multiple dashboards and hundreds of devices
            datasrcMap.get(idfield).add(WC);
            ITpings_Query_Manager._log("register WC for pollServer event", "datasrc:" + datasrc, "idfield:" + idfield, _IQMap.get(datasrc));
            return true;
        }

        /**
         * Get maximum ID values from Database and notify/pulse the (registered) Custom Elements in the page
         */
        pollServer(endpoint) {
            let _QM = this;
            $_fetch($_localPath(endpoint))                          // QueryManager Poll an endpoint
                .then(json => {
                    if (endpoint === $_app_single_PingID_endpoint) {       // single value max(_pingid)
                        if (json > _QM.maxid) {
                            _QM.maxid = json;                       // if it is higher
                            _QM.pollServer($_app_DB_IDs_endpoint);  //
                        } else {
                            //
                        }
                    } else {
                        ITpings_Query_Manager._log("heartbeat:" + __heartbeat_msecs, "Got highest _pingid values from Database: ►►►", json.maxids.pings._pingid, json);
                        _QM["pulse"].forEach((datasrcMap, datasrc) => {
                            datasrcMap.forEach(fieldSet => {
                                fieldSet.forEach(ITpings_element => {
                                    let idvalue = json["maxids"]["pings"]["_pingid"]; //todo remove hardcoded _pingid
                                    ITpings_Query_Manager._log(datasrc + ".pollServer(" + idvalue + ")");
                                    ITpings_element.pollServer(idvalue);// method defined on CustomElement !!!
                                });
                            })
                        })
                    }
                }).catch(e => console.error("pollServer", e));
        }
    }//class ITpings_Query_Manager

//endregion ======================================================= QueryManager for all itpings-table & itpings-chart Custom Elements

    class TableRow {
        constructor(row, columns = Object.keys(row), isTHEAD = false) {
            this.data = row;
            this.columns = columns;
            this.element = this.DOMelement(row, isTHEAD);
            this.requiredColumns = $_newSet();
        }

        DOMelement(row, isTHEAD = false) {
            let TR = document.createElement('TR');
            this.columns.forEach(name => {
                try {
                    let TD = TR.insertCell();
                    let data_name = "data-" + name;
                    let value = row[name];
                    TR.setAttribute(data_name, value);      // stick all values on the TR too
                    TD.setAttribute("data-column", name);
                    TD.setAttribute(data_name, value);
                    // only display a column if there are non-standard values
                    if (name === $_DEF.modulation && value !== "LORA") this.requiredColumns.add($_DEF.modulation);
                    if (name === $_DEF.coding_rate && value !== "4/5") this.requiredColumns.add($_DEF.coding_rate);
                    if (name === $_DEF.data_rate && value !== "SF7BW125") this.requiredColumns.add($_DEF.data_rate);

                    if (name === $_DEF.LastSeen) value = $_dateMinutesSince(value);
                    if (name === $_DEF.dev_id) value = `<SPAN data-column="${name}" data-${name}="${value}">${value}</SPAN>`;
                    if (name === $_DEF.ID) {
                        value = $_HTML_clickable_pingid(value);

                        // ** Only execute once PER Row/Table
                        // ** mouseover a pingid and all other tables scroll to the same pingid
                        if (__synchronized_pingID_table_scrolling) {
                            TR.addEventListener("mouseenter", function () {
                                let dataname = "data-" + $_DEF.ID;                      // _pingid
                                let _pingid = $_getAttribute(TR, dataname);
                                let saved_backgroundColor = TR.style.backgroundColor;
                                let selector = "itpings-table .data-table TR[" + dataname + "='" + _pingid + "']";
                                let TRs = $_querySelectorAll(selector);                 // get all TRs with the same _pingid defined
                                let hasMatchingTRs = $_length(TRs) > 1;
                                let TRcolor = hasMatchingTRs ? "chartreuse" : "lightcoral";
                                if (TRs) TRs.map(otherTR => {                      // scroll all Other TRs in Tables to the same _pingid
                                    if (otherTR !== TR) {                          // for all other TRs: scroll TR into view
                                        otherTR.savedScrollTop = otherTR.parentNode.parentNode.parentNode.scrollTop;
                                        otherTR.scrollIntoView({"block": "center", "inline": "nearest"});
                                    }
                                    $_setBackgroundColor(otherTR, TRcolor);   // highlight all matching _pingids
                                });
                                if (!TR.hasMouseLeaveListener) {                        // no eventlistner defined yet
                                    TR.addEventListener("mouseleave", () =>
                                        TRs.map(otherTR => {
                                            $_setBackgroundColor(otherTR, saved_backgroundColor);
                                            // window.setTimeout(() => {
                                            //     let parentDIV = otherTR.parentNode.parentNode.parentNode;
                                            //     let savedScrollTop = hasMatchingTRs ? otherTR.savedScrollTop : 0;
                                            //     parentDIV.scrollTop = savedScrollTop;
                                            // }, 200)
                                        }));
                                    TR.hasMouseLeaveListener = true;
                                }
                            });
                        }
                    }
                    TD.innerHTML = isTHEAD ? name : value;
                } catch (e) {
                    $_logerror(e);
                }
            });
            return TR;
        }

        ageColor() {
            try {
                let colors = $_CSV2Array("#00FF00,#33FF66,#66FF99,#99FFCC,#CCFFFF,#FFFFFF");
                let color_count = colors.length;
                let idx = 0;
                let row = this.rows[idx];
                let property = x => $_hasOwnProperty(row, x) ? x : false;
                /**
                 * time is the TTN Gateway UTC time, created is the MySQL server time. todo: calculate correct ping time
                 * for now we return the timestamp the DOM element was created
                 * **/
                let timestamp = property('created') || property('time');
                let minutes_since;
                if (timestamp) {
                    do {
                        let row = this.rows[idx++];
                        if (row.TR) {
                            minutes_since = $_absolute($_dateMinutesSince(row[timestamp]));
                            row.TR.style.backgroundColor = colors[minutes_since];
                        }
                    } while (row.TR && minutes_since <= color_count);
                }
            } catch (e) {
                $_logerror(e);
            }
        }
    }

    class TableManager {
        // or pay 800$ for https://www.ag-grid.com/ag-grid-8-performance-hacks-for-javascript/

        static _log() {
            $_log("TableManager.gold.black", ...arguments);
        }

        constructor(customelement, rows) {
            window.tm = this; // debugging
            /** init columns to be displayed in TR row **/

            let rowKeys = $_Object_keys(rows[0]);                       // keys from first object (row)
            this.columns = [...rowKeys];                                // add extra columns here
            this.requiredColumns = $_newSet();

            this.cachedRows = [];

            this.WC = customelement;
            customelement.appendChild($_importNode('itpings-table'));
            //$_getElementById(destinationId).appendChild($_importNode(templateId))

            /** Initialize DOM **/
            let querySelector = x => customelement.querySelector(x);
            this.TABLE = querySelector("TABLE");
            this.CAPTION = querySelector("CAPTION");
            this.TBODY = querySelector("TBODY");
            /** fill THEAD with columnnames from newest row **/
            let headerTR = new TableRow(rows[0], this.columns, true);   // true sets column name instead of column value
            this.THEAD = querySelector("THEAD").appendChild(headerTR.element);

            this.addRows(rows);
        }

        title(str) {
            this.CAPTION.innerHTML = str;
        }

        addRows(rows) {
            TableManager._log('addRows:', rows.length, 'new rows');
            $_log.rows(rows);
            let newRows = rows.map(row => {
                this.cachedRows.unshift(row);
                return new TableRow(row, this.columns)
            });
            this.rows = newRows.concat(this.rows);
            this.showNewRows(0, rows.length);                  // add new rows to DOM
        }

        colorDOMnodes_byAge() {
        }

        showNewRows(idx = 0, count = 100) {
            try {
                /** create a DocumentFragment of n TRs (from idx (High) to end_idx (=idx-count)) **/
                let rowsFragment = document.createDocumentFragment();
                let end_idx = idx + count;
                //TableManager._log('show DOM', 'idx:' + idx, 'count:' + count, 'end_idx:' + end_idx, 'rows:' + this.rows.length);
                while (idx < end_idx) rowsFragment.appendChild(this.rows[idx++].element);

                let fragmentTRcount = rowsFragment.childElementCount;
                //TableManager._log(fragmentTRcount, 'new rows before fragment:', this.TBODY.children[0]);
                this.TBODY.insertBefore(rowsFragment, this.TBODY.firstElementChild);      // insert at top of TBODY

                this.colorDOMnodes_byAge();

                /** keep a maximum of 100 rows in the TBODY DOM **/
                while (this.TBODY.childElementCount > 100) this.TBODY.removeChild(this.TBODY.lastElementChild);
                /** keep 500 rows in rows array **/
                let maxlength = 500;
                if (this.rows.length > maxlength) this.rows = this.rows.slice(0, maxlength);

                /** show required columns by forcing an existing CSS custom property from 'none' to 'table-cell' **/
                this.requiredColumns.forEach(             //make sure required columns are always displayed
                    columnName => $_setCSSproperty(
                        "--CSSdisplay_" + columnName
                        , "table-cell"
                        , this.TABLE) // set on this itpings-table (versus document.body) so the column is only displayed in this table
                );

                this.set_localStorage();
            } catch (e) {
                $_logerror(e);
            }
        }

        set_localStorage(count = 200) {
            let WC = this.WC;
            if (!WC.hasAttribute("nocache")) {
                this.cachedRows = this.cachedRows.slice(0, count);
                $_LSM.set(WC._WC_ID, this.cachedRows, 'CSV');   // save cachedRows as CSV string
            }
        }

        get_localStorage() {
            let WC = this;
            let csv = $_LSM.get(WC._WC_ID);
        }
    }

//region ========================================================== Custom Element: itpings-table

    (function (elementName = $_app_custom_element_Namespace + "table") {
        let _traceCustomElement = true; // for educational purposes, trace specific CustomElement operations to the console

        window.customElements.define(elementName, class extends HTMLElement {
            _log() {
                $_log(elementName + ":" + (this._WC_ID || 'INIT') + ".#99e6ff.black", ...arguments);
            };

            // noinspection JSUnusedGlobalSymbols
            static get observedAttributes() {
                // data attributes (changes) this Custom Element listens to
                // constructor has not run yet, so this scope is not available
                return ["query"];
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
                WC.TableManager = false;
                WC._WC_ID = $_getAttribute(WC, "query");
                if (_traceCustomElement) this._log(__TEXT_CUSTOM_ELEMENT_CONSTRUCTOR, WC);
            }

            setTitle(txt = $_emptyString) {
                try {
                    let WC = this;
                    if (!WC.idle) txt = $_HTML_LoadingTitle(txt);
                    if (txt === $_emptyString) txt = WC["title"] || WC["query"];
                    WC.TableManager && WC.TableManager.title(txt);
                } catch (e) {
                    console.error(e);
                }
            }

            fetchTableData(filter = $_emptyString, cacheKey = false) {
                // ** The Custom Element connectedCallback function (below) has already executed
                // ** Initialization for the TABLE HTML struture is done there

                let WC = this; // WC = WebComponent (aka CustomElement) makes for clear understanding what 'this' is; AND it minifies better :-)
                let query = $_getAttribute(WC, "query");
                WC.tabletitle = WC["title"] || query;
                this.setTitle(WC.tabletitle + "; processing data");

                //this._log('fetchTableData', cacheKey ? "Cache:" + cacheKey : "from Server, filter:", filter);
                if (WC.idle) {                                                         // not waiting for a JSON response already
                    WC.idle = false;                                                   // true again AFTER fetch processing is done
                    $_fetch(WC.uri + filter, cacheKey)
                        .then(json => {
                            let rows = $_getResultArray(json);
                            let rows_len = rows.length;
                            this._log('add ', rows_len, 'new rows from ', WC.hasCachedData ? "Cache" : "Database");
                            WC.hasCachedData = $_isCachedJSON(json);
                            if (rows && rows_len > 0) {
                                this.maxid = rows[0][$_DEF.ID];//max value _pingid
                                $_log.rows(rows);
                                if (!WC.TableManager) {
                                    WC.innerHTML = $_emptyString;                                  // clear spinner
                                    WC.TableManager = new TableManager(WC, rows);
                                    $_app__QueryManager.register_for_pollServer(WC);         // Register my -table query with the Query Manager, so I get (pollServer) updates
                                } else {
                                    WC.TableManager.addRows(rows);
                                    //WC.TableManager.set_localStorage();
                                    if (WC.hasCachedData) {
                                        this._log('catchup with new data');
                                        WC.idle = true;
                                        this.pollServer(false, false);                  // get recent values
                                    }
                                }
                            } else {
                                if ($_hasOwnProperty(json, "status")) {
                                    console.error("No rows", json);
                                    let msg = json.statusText;
                                    if (json.status === 500) msg = json.url + "Script exceeded PHP execution time, make the query faster or reduce the amount of data in the MySQL database";
                                    $_appendChild(WC, $_createDIV_withClass(`${json.status} ${msg}`, "itpings-table-error"));
                                }
                            }
                            if ($_isCachedJSON(json)) WC.hasCachedData = false;
                            WC.idle = true;                                        // processed all rows
                            this.setTitle(WC.tabletitle);
                        })
                        .catch(e => {
                            console.error("fetch", e);
                            console.trace();
                        });
                } else {
                    console.warn(elementName, 'is busy');
                    this.idle = true;
                    $_setTimeout(() => {
                        this._log('re-fecth', filter);
                        this.fetchTableData(filter, cacheKey);
                    }, 200);
                }
            }

            // noinspection JSUnusedGlobalSymbols
            pollServer(pingID = false, limit = false) {                                            // read Database endpoint, only when there are new visible_ids
                if ((pingID > this.maxid) || !pingID) {
                    let filter = (pingID ? "&filter=" + $_DEF.ID + " gt " + this.maxid : "") + (limit ? "&limit=" + (pingID - this.maxid) : "");
                    this._log(elementName + '.pollServer(', pingID ? pingID : 'false', ',', limit ? limit : 'false', ')', filter, "(newest _pingid: " + pingID + ")");
                    this.fetchTableData(filter, false);        // fetch data from server without cacheKey
                } else {
                    this._log(elementName, 'is up to date:', this.maxid, '===', pingID);
                }
            }

            // noinspection JSUnusedGlobalSymbols
            attributeChangedCallback(attr, oldValue, newValue) {
                // ** called for every change for (ObservedDataAttributes only) Data-attributes on the HTML tag
                // ** So also at first creation in the DOM
                let WC = this;
                let isConnected = WC.isConnected;                                     // sparsely documented standard property on DOM elements
                if (attr === "query") {
                    WC.uri = $_localPath(newValue);
                    if (_traceCustomElement) this._log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED, ", attr:" + attr, ", oldValue:" + oldValue, ", newValue" + newValue, isConnected ? $_emptyString : "►► NOT", "Connected");
                    WC.idle = true;                                                                    // no new fetch when still waiting or previous one
                    $_innerHTML(WC, "<IMG src='data:image/gif;base64,R0lGODlhEAAQAPQAAP///wAAAPj4+Dg4OISEhAYGBiYmJtbW1qioqBYWFnZ2dmZmZuTk5JiYmMbGxkhISFZWVgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH+GkNyZWF0ZWQgd2l0aCBhamF4bG9hZC5pbmZvACH5BAAKAAAAIf8LTkVUU0NBUEUyLjADAQAAACwAAAAAEAAQAAAFUCAgjmRpnqUwFGwhKoRgqq2YFMaRGjWA8AbZiIBbjQQ8AmmFUJEQhQGJhaKOrCksgEla+KIkYvC6SJKQOISoNSYdeIk1ayA8ExTyeR3F749CACH5BAAKAAEALAAAAAAQABAAAAVoICCKR9KMaCoaxeCoqEAkRX3AwMHWxQIIjJSAZWgUEgzBwCBAEQpMwIDwY1FHgwJCtOW2UDWYIDyqNVVkUbYr6CK+o2eUMKgWrqKhj0FrEM8jQQALPFA3MAc8CQSAMA5ZBjgqDQmHIyEAIfkEAAoAAgAsAAAAABAAEAAABWAgII4j85Ao2hRIKgrEUBQJLaSHMe8zgQo6Q8sxS7RIhILhBkgumCTZsXkACBC+0cwF2GoLLoFXREDcDlkAojBICRaFLDCOQtQKjmsQSubtDFU/NXcDBHwkaw1cKQ8MiyEAIfkEAAoAAwAsAAAAABAAEAAABVIgII5kaZ6AIJQCMRTFQKiDQx4GrBfGa4uCnAEhQuRgPwCBtwK+kCNFgjh6QlFYgGO7baJ2CxIioSDpwqNggWCGDVVGphly3BkOpXDrKfNm/4AhACH5BAAKAAQALAAAAAAQABAAAAVgICCOZGmeqEAMRTEQwskYbV0Yx7kYSIzQhtgoBxCKBDQCIOcoLBimRiFhSABYU5gIgW01pLUBYkRItAYAqrlhYiwKjiWAcDMWY8QjsCf4DewiBzQ2N1AmKlgvgCiMjSQhACH5BAAKAAUALAAAAAAQABAAAAVfICCOZGmeqEgUxUAIpkA0AMKyxkEiSZEIsJqhYAg+boUFSTAkiBiNHks3sg1ILAfBiS10gyqCg0UaFBCkwy3RYKiIYMAC+RAxiQgYsJdAjw5DN2gILzEEZgVcKYuMJiEAOwAAAAAAAAAAAA=='>");
                    let cachedDataKey = WC.hasAttribute("nocache") ? false : WC._WC_ID;
                    let limit_attribute = $_getAttribute(WC, "limit");
                    let filter = limit_attribute ? "&limit=" + limit_attribute : "";
                    this.fetchTableData(filter, cachedDataKey);  // call ONCE with an empty filter value
                }
            }

            // noinspection JSUnusedGlobalSymbols
            connectedCallback() {
                if (_traceCustomElement) this._log(__TEXT_CUSTOM_ELEMENT_CONNECTED); // ** Called before Custom Element is added to the DOM
            }
        }); // window.customElement()
    })(); // function (elementName = "itpings-table")

//endregion ========================================================== Custom Element: itpings-table

//region ========================================================== Custom Element: itpings-chart

    (function (elementName = $_app_custom_element_Namespace + "chart") {
        let _traceCustomElement = true; // for educational purposes, trace specific CustomElement operations to the console

        let __INTERVAL_DEFAULT = "1D";
        let __INTERVALS = $_newMap();
        // ES6 Destructuring, parameter names become keys: {interval:interval, unit:unit, xformat:xformat}
        let addInterval = (key, interval, maxrows, unit, xformat, by10minutes) => __INTERVALS.set(key, {
            interval,
            maxrows,
            unit,
            xformat,
            by10minutes
        });
        let by10minutes = true;//select only whole 0,10,20,30,40,60 minutes (created) data
        /** seperate calls; easier to disable than an Array structure **/
        addInterval("5m", 5, 20, $_DEF.MINUTE, $_dateFormat_Hmm, false);
        addInterval("30m", 30, 60, $_DEF.MINUTE, $_dateFormat_Hmm, false);
        addInterval("1H", 1, 120, $_DEF.HOUR, $_dateFormat_Hmm, false);
        addInterval("2H", 2, 240, $_DEF.HOUR, $_dateFormat_Hmm, by10minutes);
        addInterval("6H", 6, 380, $_DEF.HOUR, $_dateFormat_Hmm, by10minutes);
        addInterval("1D", 1, 380, $_DEF.DAY, $_dateFormat_Hmm, by10minutes);
        addInterval("2D", 2, 380, $_DEF.DAY, $_dateFormat_DMMMHmm, by10minutes);
        addInterval("7D", 7, 380, $_DEF.DAY, $_dateFormat_DMMMHmm, by10minutes);
        addInterval("2W", 2, 500, $_DEF.WEEK, $_dateFormat_DMMMHmm, by10minutes);
        addInterval("1M", 1, 1000, $_DEF.MONTH, $_dateFormat_DMMM, by10minutes);
        addInterval("6M", 6, 1000, $_DEF.MONTH, $_dateFormat_DMMM, by10minutes);
//            addInterval("1Y", 1, $_DEF.YEAR, $_dateFormat_DMMM);


        window.customElements.define(elementName, class extends HTMLElement {
            _log() {
                $_log(elementName + ":" + (this._WC_ID || 'INIT') + ".#3cf.black", ...arguments);
            };

//region ========================================================== Custom Element Getters/Setters for itpings-chart
            // noinspection JSUnusedGlobalSymbols
            static get observedAttributes() {
                //constructor has not run yet, so this scope is not available
                //this._log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTES, _observedAttributes);
                return [$_DEF.sensor, "interval"];
            }

            get title() {
                return $_getAttribute(this, "title");
            }

            set title(newValue) {
                $_setAttribute(this, "title", newValue);
            }

            get sensor() {
                return $_getAttribute(this, $_DEF.sensor);
            }

            set sensor(newValue) {
                $_setAttribute(this, $_DEF.sensor, newValue);
            }

            // noinspection JSUnusedGlobalSymbols
            get interval() {
                return $_getAttribute(this, "interval");
            }

            /**
             * Main Setter
             * this.interval=6H
             * **/
            set interval(newValue) {
                let WC = this;
                if (WC.current_interval === __INTERVALS.get(newValue)) {
                    let a = document.createElement("a");
                    a.target = "_blank";
                    a.href = WC.uri;
                    a.click();
                    return;
                }
                $_setAttribute(WC, "interval", newValue);
                this._log("(setter)", "interval=", "(" + (typeof newValue) + ")", newValue);
                WC.isOldData = event && event.type === 'click';
                WC.idle = false;
                let sensor = WC.sensor;
                let intervalDefinition = WC.current_interval = __INTERVALS.has(newValue) ? __INTERVALS.get(newValue) : __INTERVAL_DEFAULT;

                this.setChartTitle("; retrieving values: " + WC.current_interval.interval + WC.current_interval.unit);                             // set title to busy indicator
                this.showIntervals(false);

                $_toggleClasses([...this.INTERVALS.children]
                    , WC.INTERVALS.querySelector(`[id="${newValue}"]`)
                    , "selectedInterval"); //loop all interval DIVs , add or remove Class: selectedInterval

                //todo use faster SensorValues_Update query   let sensor_ids = (sensor === "temperature_5") ? "7,14" : "6,13";
                if (ITPings_graphable_PingedDevices_Values.includes(sensor)) {
                    WC.query = "PingedDevices";
                    WC.uri = $_localPath(WC.query);
                    WC.deviceid_field_name = $_DEF.dev_id;
                    WC.value_field_name = sensor;
                } else {
                    if ($_app_ITpings_data_tables.includes(sensor)) {
                        WC.query = sensor;
                        WC.uri = $_localPath(sensor);
                        WC.deviceid_field_name = $_DEF.ITpings_devid;
                        WC.value_field_name = "value";
                    }
                    else {
                        WC.query = "SensorValues";
                        WC.uri = $_localPath(`${WC.query}&sensorname=${sensor}`);
                        WC.deviceid_field_name = $_DEF.dev_id;
                        WC.value_field_name = "sensorvalue";
                    }
                }
                WC.uri += `&orderby=created&interval=${intervalDefinition.interval}`;
                WC.uri += `&intervalunit=${intervalDefinition.unit}&limit=none&maxrows=${intervalDefinition.maxrows}`;

                WC._WC_ID = (WC.title || WC.query) + "_" + newValue;
                $_localstorage_Set(WC.storageKey, newValue);

                this.fetchChartData($_emptyString, WC._WC_ID);       // do not use cache when interval is reset by mouse click
            }//set interval

//endregion ======================================================= Custom Element Getters/Setters for itpings-chart

            initChartJS() {
                let WC = this;
                WC.ChartJS_Lines = [];                              // Array, index number is used to register devices

                this._log(WC.ChartJS ? "ChartJS.destroy()" : "new ChartJS()");
                if (WC.ChartJS) WC.ChartJS["destroy"]();            // Uglify Mangle protection

                // resources axes:
                // https://code.tutsplus.com/tutorials/getting-started-with-chartjs-scales--cms-28477
                // https://stackoverflow.com/questions/37250456/chart-js-evenly-distribute-ticks-when-using-maxtickslimit/37257056
                WC.ChartJS = new Chart(WC.CANVAS, {
                    "type": "line",
                    "data": {
                        "labels": [],
                        "datasets": []
                    },
                    "options": {
                        "maintainAspectRatio": false,
                        "title": {
                            "display": false,
                            "text": $_emptyString
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
                        scales: {
                            xAxes: [{
                                // type: 'time',    // requires MomentJS !!
                                ticks: {
                                    autoSkip: true,
                                    maxRotation: 45,
                                    maxTicksLimit: 10
                                },
                            }]
                        }
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
                });//ChartJS json config

                // custom x-axis labels:
                //https://stackoverflow.com/questions/37250456/chart-js-evenly-distribute-ticks-when-using-maxtickslimit/37257056

                //axis docs: https://www.chartjs.org/docs/latest/axes/
            }

            drawChartJS(rows = false) {
                let WC = this;
                rows = rows || WC.rows;
                WC._log('ChartJS.update() ', $_length(rows), 'new rows.', ' Now', $_length(WC.rows), 'rows');
                let ChartJS_datasets = WC.ChartJS["data"]["datasets"];
                let ChartJS_labels = WC.ChartJS["data"]["labels"];
                rows.forEach(row => {
                    let x_time = $_dateStr(row[$_DEF.created], WC.current_interval.xformat);         // format x-axis label with timestamp
                    //this._log(WC.query, x_time, row[WC.value_field_name]);
                    if (!ChartJS_labels.includes(x_time)) {                                     // prevent duplicate timelables on x-axis
                        let lineID = row[WC.deviceid_field_name];                               // one graphed line per device
                        let dataset_idx = WC.ChartJS_Lines.indexOf(lineID);                     // find existing device
                        if (dataset_idx < 0) {                                                  // ** add new device
                            dataset_idx = $_length(ChartJS_datasets);
                            let deviceColor = DeviceColors.getColor(lineID);                    // get distinct color from Map
                            $_log(`ChartJS newDevice: (${WC.deviceid_field_name}) = ${lineID} .${deviceColor}`);
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
                        //this._log(WC.query, x_time, row[WC.value_field_name]);
                        ChartJS_labels.push(x_time);
                        ChartJS_datasets[dataset_idx]["data"].push({
                            "x": x_time,
                            "y": row[WC.value_field_name]
                        });
                    }
                });
                WC.ChartJS.update();
            }

            setChartTitle(txt = $_emptyString, prefix) {
                let WC = this;
                txt = (WC.title || WC.query) + txt;
                if (WC.idle) txt += "";//<span class=refreshChart> &#x21bb; </span>";// needs global clickhandler or other DOM layout for fixed eventhandler
                else txt = $_HTML_LoadingTitle(txt, prefix);
                $_innerHTML(WC.CAPTION, txt);
            }

            showIntervals(show = false) {
                $_style_display(this.INTERVALS, show ? 'initial' : 'none');                 // display/none Intervals DIV
            }

            fetchChartData(filter = $_emptyString, cacheKey = false) {
                let WC = this;
                let interval = WC.current_interval;
                WC.idle = false;
                if (filter === $_emptyString && WC.current_interval.by10minutes && !filter.includes('by10minutes')) filter += '&by10minutes=true';
                WC._log('fetchChartData', filter === $_emptyString ? 'Empty filter' : filter, cacheKey ? "cacheKey:" + cacheKey : "No Cache Key");
                $_fetch(WC.uri + filter, cacheKey)
                    .then(json => {
                        let rows = $_getResultArray(json);
                        if (!rows) console.error(json);
                        else {
                            this.setChartTitle("; processing data");
                            let isCachedData = $_isCachedJSON(json);                    // did the data come from cache?
                            if (isCachedData || WC.isOldData) WC.rows = [];             // clear recorded rows
                            /** on first init or refresh chart data **/
                            if ($_isEmptyArray(WC.rows)) {
                                this.initTime = $_Date();

                                //if (!WC.isRegistered_in_QueryManager) WC.isRegistered_in_QueryManager = $_app__QueryManager.register_for_pollServer(WC);
                                WC.isRegistered_in_QueryManager = WC.isRegistered_in_QueryManager || $_app__QueryManager.register_for_pollServer(WC);// savebytes: 3

                                this.initChartJS();                                     // reset all ChartJS data
                            }
                            this._log("has", $_length(WC.rows), "rows (max:" + interval.maxrows + "), now got", $_length(rows), isCachedData ? 'Cached!' : 'New', "rows", filter ? "from filter:" + filter : "");
                            rows.map(row => {                                           // process (new) rows
                                WC[$_DEF.ID] = row[$_DEF.ID];                           // WC._pingid ; record hightest PKvalue (presuming always getting a higher pingid)
                                WC.rows.push(row);                                      // add at end of .rows
                            });
                            WC.rows = $_lastNelements(WC.rows, interval.maxrows);       // keep most recent rows, defined by current interval.maxrows

                            /** Draw all WC.rows OR add only New rows **/
                            this.drawChartJS(isCachedData ? false : rows);

                            /** if data came from DB, then cache new data **/
                            if (!isCachedData) $_LSM.set(WC._WC_ID, $_lastNelements(WC.rows, interval.maxrows), 'CSV');

                            WC.idle = true;
                            this.setChartTitle();                                       // WC.idle resets title
                            this.showIntervals(true);

                            /** if displayed data is over 10 minutes, fetch all new data, otherwise the graphs are going to look out of sync **/
                            WC.isOldData = isCachedData || $_dateMinutesSince(this.initTime) < -10;    // 10 minutes
                            if (WC.isOldData) {
                                this._log("ChartJS refresh all");
                                WC.isOldData = true;
                                WC.idle = false;
                                this.setChartTitle(' ', 'Updating ');
                                this.fetchChartData($_emptyString, false);
                            }
                        }
                    });
            }

            // noinspection JSUnusedGlobalSymbols
            pollServer(pingID) {
                let WC = this;
                let current_ping_id = ~~WC[$_DEF.ID];
                this._log('pingID:', pingID, 'current_ping_id', WC[$_DEF.ID], WC.idle ? 'idle' : 'waiting for data');
                if (current_ping_id && WC.idle) {
                    if (current_ping_id < pingID) {
                        this._log("pollServer Chart JS current_ping_id:", current_ping_id, "new:", pingID);
                        this.fetchChartData(`&filter=${$_DEF.ID}%20gt%20${current_ping_id}`);
                    }
                } else {
                    //this._log(WC.idle ? "waiting for previous fetch" : "Not drawn yet");
                }
            }

            // noinspection JSUnusedGlobalSymbols
            constructor() {
                super();
                this.rows = []; // cache data
                this.isRegistered_in_QueryManager = false;
                this.isOldData = false;
            }

            // noinspection JSUnusedGlobalSymbols
            attributeChangedCallback(attr, oldValue, newValue) {
                let WC = this;
                let isConnected = WC.isConnected;
                switch (attr) {
                    case("interval"):
                        if (isConnected) {
                            if (_traceCustomElement) this._log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTECHANGED, ", attr:" + attr, ", oldValue:" + oldValue, ", newValue" + newValue, isConnected ? $_emptyString : "►► NOT", "Connected");

                            /** interval updates are done with the Setter method!! **/

                        }
                        break;
                    default:
                        break;
                }
            }

            // noinspection JSUnusedGlobalSymbols
            connectedCallback() {
                this._log(__TEXT_CUSTOM_ELEMENT_CONNECTED);

                let WC = this;
                let sensor = WC.sensor;

                WC.storageKey = sensor + "_interval";

                let ITPINGS_DIV = $_createDIV_withClass("<!-- DIV created in connectedCallback -->", "chart-wrapper");
                let _append = childNode => $_appendChild(ITPINGS_DIV, childNode);

                /** now built DOM structure:
                 *
                 * DIV .chart-wrapper>
                 *     DIV .chartjs-size-monitor (inserted by ChartJS)
                 *     DIV .itpings-div-title
                 *     DIV .chart_interval
                 *     CANVAS
                 * **/

                WC.CAPTION = _append($_createDIV_withClass(sensor, "itpings-div-title"));

                /** Add interval UI to Chart DIV **/
                WC.INTERVALS = [...__INTERVALS.keys()].reduce((intervals, key) => {         // loop all intervals, starting with parent DIV chart_interval
                    let DIV = $_appendChild(intervals, $_createDIV_with_id(key, key));      //      add one interval DIV
                    DIV.addEventListener("click", () => WC.interval = key);                 //      add click event
                    return intervals;                                                       //      all (new) intervals
                }, _append($_createDIV_withClass($_emptyString, "chart_interval")));          // start reduce with parent DIV

                /** append CANVAS to ITPINGS_DIV **/
                WC.CANVAS = _append($_createElement("CANVAS"));

                $_appendChild(WC, ITPINGS_DIV);                                             // now _append that sucker to the DOM

                WC.interval = $_localstorage_Get(WC.storageKey, __INTERVAL_DEFAULT);   // force interval setter so the chart is redrawn
            }

            // noinspection JSUnusedGlobalSymbols
            disconnectedCallback() {
                this._log("disconnected", this.isConnected ? "connected" : "NOT connected");
            }
        }); // window.customElement()
    })(); // function (elementName = "itpings-chart")
//endregion ======================================================= Custom Element: itpings-table

//region ========================================================== Custom Element: itpings-map
    /**
     * attributes:
     *      query : SQL query
     *
     * **/
    (function (elementName = $_app_custom_element_Namespace + "map") {
        let _traceCustomElement = true; // for educational purposes, trace specific CustomElement operations to the console

        window.customElements.define(elementName, class extends HTMLElement {
                _log() {
                    if (_traceCustomElement) $_log(elementName + ":" + (this._WC_ID || 'INIT') + ".#8888ff.black", ...arguments);
                };

//region ========================================================== Custom Element Getters/Setters for itpings-chart
                // noinspection JSUnusedGlobalSymbols
                static get observedAttributes() {
                    //constructor has not run yet, so this scope is not available
                    //this._log(__TEXT_CUSTOM_ELEMENT_ATTRIBUTES, _observedAttributes);
                    return ['query', 'api-key', 'zoom', 'latitude', 'longitude', 'map-options'];
                }

//endregion ======================================================= Custom Element Getters/Setters for itpings-chart

                addMarker(marker) {
                    let labels = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    let gmarker = new google.maps.Marker({
                        position: new google.maps.LatLng(marker.lat, marker.lng),
                        label: labels[$_length(this.markers) % $_length(labels)]
                    });
                    gmarker.visible = true;
                    gmarker.clickable = true;
                    gmarker.title = gmarker.name;
                    // gmarker.icon = {
                    //     url: 'motorbikeswapcount' + gmarker.icon + '.png',
                    //     //image = "http://chart.apis.google.com/chart?cht=mm&chs=24x32&chco=FFFFFF,008CFF,000000&ext=.png";
                    //     size: new google.maps.Size(32, 37), // This marker is 20 pixels wide by 32 pixels high.
                    //     origin: new google.maps.Point(0, 0), // The origin for this image is (0, 0).
                    //     anchor: new google.maps.Point(16, 37) // The anchor for this image is the base of the flagpole
                    // };
                    google.maps.event.addListener(gmarker, 'click', () => {
                        this.map.setZoom(6);
                        let markerPosition = gmarker.getPosition();
                        this.map.panToWithOffset(markerPosition, 200, 0);
                        this.map.setCenter(markerPosition);
                        //this.markerInfo.show();
                    });
                    this.markers.push(gmarker);
                }

                addMarkers(markers) {
                    markers.forEach(marker => this.addMarker(marker));
                }

                LatLng(lat, lon) {
                    if (typeof lat === "object") {
                        if (lat.hasOwnProperty('lon')) { //swapper or other object with lat,lon
                            //noinspection JSUnresolvedVariable
                            lon = lat.lon;
                            //noinspection JSUnresolvedVariable
                            lat = lat.lat;
                        } else {
                            if (lat.length === 2) { //array
                                lon = lat[1];
                                lat = lat[0];
                            } else { //GMap Object
                                //this._log( 'GMap object: '+lat.lat() );
                                if (lat.hasOwnProperty('latLng')) { //swapper or other object with lat,lon
                                    lat = lat.latLng;
                                }
                                let position = lat.position;
                                //noinspection JSUnresolvedFunction
                                lon = position.lat(); //memory leak when using minified .A .B or .k (property names change!)
                                //noinspection JSUnresolvedFunction
                                lat = position.lng();
                            }
                        }
                    }
                    return (new google.maps.LatLng(lat, lon));
                }

                // noinspection JSUnusedGlobalSymbols
                setPosition(lat, lon, zoomlevel, smoothzoom) {
                    this.map.setCenter(this.LatLng(lat, lon));
                    //noinspection JSUnresolvedFunction
                    this.map.panTo(this.LatLng(lat, lon));
                    this.zoom(zoomlevel, smoothzoom);
                }

                zoom(zoomlevel) {//smoothzoom
                    if (this.smoothzoom) {
                        //noinspection JSUnresolvedFunction
                        let currentzoom = this.map.getZoom();
                        if (currentzoom >= zoomlevel) return;
                        let z = google.maps.event.addListener(this.map, 'zoom_changed', () => {//event
                            google.maps.event.removeListener(z);
                            this.zoom(currentzoom + 1, true);
                        });
                        setTimeout(function () {
                            //noinspection JSUnresolvedFunction
                            this.map.setZoom(currentzoom);
                        }, 80); // 80ms is what I found to work well on my system -- it might not work well on all systems
                    } else {
                        this.map.set('zoom', zoomlevel);
                    }
                };

                zoomToMarkerBounds(markers) {
                    this._log('zoomToMarkerBounds');
                    let bounds = new google.maps.LatLngBounds();
                    (markers || this.markers).forEach(marker => {
                        return bounds.extend(marker.getPosition());
                    });
                    this.map.fitBounds(bounds);
                };

                fetchMapData(filter = $_emptyString, localStorage_cachedData = false) {
                    let WC = this;
                    WC.idle = false;
                    WC._log('fetchChartData', filter, WC.current_interval);
                    $_fetch(WC.uri + filter, localStorage_cachedData)
                        .then(json => {
                            let rows = $_getResultArray(json);
                            if (rows) {
                                this.addMarkers(rows);
                            } else {
                                console.error(json);
                            }
                        });
                }

                // noinspection JSUnusedGlobalSymbols
                pollServer(pingID) {
                    let WC = this;
                    let current_ping_id = ~~WC[$_DEF.ID];
                    this._log('pingID:', pingID, 'current_ping_id', WC[$_DEF.ID], WC.idle ? 'idle' : 'waiting for data');
                    if (current_ping_id && WC.idle) {
                        if (current_ping_id < pingID) {
                            this._log("pollServer Chart JS current_ping_id:", current_ping_id, "new:", pingID);
                            this.fetchMapData(`&filter=${$_DEF.ID}%20gt%20${current_ping_id}`);
                        }
                    } else {
                        this._log("►►► No pingid on Chart JS yet (not drawn yet) ◄◄◄");
                    }
                }

                // noinspection JSUnusedGlobalSymbols
                bouncemarker(GMap_Marker) {
                    if (GMap_Marker) {
                        //noinspection JSUnresolvedFunction,JSUnresolvedVariable
                        GMap_Marker.setAnimation(google.maps.Animation.BOUNCE);
                    } else {
                        this.markers.forEach(function (GMap_Marker) { //unbounce all markers
                            //noinspection JSUnresolvedFunction
                            GMap_Marker.setAnimation(null);
                        });
                    }
                };

                clusterMarkers() {
                    // noinspection JSUnusedGlobalSymbols
                    this.markerCluster = new MarkerClusterer(this.map, this.markers,
                        {imagePath: 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m'});
                    // let clusterStyle = {
                    //     url: './motorbikeswapcount_count.png',
                    //     width: 32,
                    //     height: 37,
                    //     anchor: [0, 0],
                    //     textColor: 'brown',
                    //     textSize: 21,
                    // };
                    // clusterStyle = [clusterStyle, clusterStyle, clusterStyle];
                    // this.markerClusterer = new MarkerClusterer(map, this.markers, {
                    //     gridSize: 10,
                    //     maxZoom: 15,
                    //     styles: clusterStyle,
                    // });
                }

                // noinspection JSUnusedGlobalSymbols
                constructor() {
                    super();
                    this.rows = []; // cache data

                    this.map = null;
                    this.zoom = null;
                    this.latitude = null;
                    this.longitude = null;

                    this.markers = [];

                    let locations = [
                        {lat: -31.563910, lng: 147.154312},
                        {lat: -33.718234, lng: 150.363181},
                        {lat: -33.727111, lng: 150.371124},
                        {lat: -33.848588, lng: 151.209834},
                        {lat: -33.851702, lng: 151.216968},
                        {lat: -34.671264, lng: 150.863657},
                        {lat: -35.304724, lng: 148.662905},
                        {lat: -36.817685, lng: 175.699196},
                        {lat: -36.828611, lng: 175.790222},
                        {lat: -37.750000, lng: 145.116667},
                        {lat: -37.759859, lng: 145.128708},
                        {lat: -37.765015, lng: 145.133858},
                        {lat: -37.770104, lng: 145.143299},
                        {lat: -37.773700, lng: 145.145187},
                        {lat: -37.774785, lng: 145.137978},
                        {lat: -37.819616, lng: 144.968119},
                        {lat: -38.330766, lng: 144.695692},
                        {lat: -39.927193, lng: 175.053218},
                        {lat: -41.330162, lng: 174.865694},
                        {lat: -42.734358, lng: 147.439506},
                        {lat: -42.734358, lng: 147.501315},
                        {lat: -42.735258, lng: 147.438000},
                        {lat: -43.999792, lng: 170.463352}
                    ];
                    this.map = new google.maps.Map(this, {
                        zoom: this.zoom || 3,
                        center: {lat: -28.024, lng: 140.887}
                    });
                    this.dispatchEvent(new CustomEvent('google-map-ready', {detail: this.map}));

                    this.addMarkers(locations);
                    this.clusterMarkers();
                    this.zoomToMarkerBounds();
                }

                // noinspection JSUnusedGlobalSymbols
                attributeChangedCallback(attr, oldValue, newValue) {
                    switch (attr) {
                        case 'zoom':
                        case 'latitude':
                        case 'longitude':
                            this[attr] = parseFloat(newValue);
                            break;
                        default:
                            break;
                    }
                }

                // noinspection JSUnusedGlobalSymbols
                connectedCallback() {
                    this._log(__TEXT_CUSTOM_ELEMENT_CONNECTED);
                }

                // noinspection JSUnusedGlobalSymbols
                disconnectedCallback() {
                }
            }
        )
        ; // window.customElement()
    })(); // function (elementName = "itpings-map")
//endregion ======================================================= Custom Element: itpings-map

    let intersectionObserver = new IntersectionObserver(function (entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) console.log('io ', entry.target.parentNode.parentNode.parentNode.computedName, entry.intersectionRatio, entry);
        });
    });


//region ========================================================== Basic Router with Templates

    class Router {
        static _log() {
            $_log("Router.teal", ...arguments);
        }

        static title(element, loading = false) {
            let title = $_getAttribute(element, 'title');
            if (title !== $_emptyString) $_innerHTMLById('itpings_article_title', " - " + (loading ? " Loading: " : "") + title);
            $_forceDOMupdate();
        }

        static routeId(route) {
            return 'article_' + (route === $_emptyString ? 'dashboard' : route);
        }

        static toggleIcon(routeObj) {
            return void($_toggleClass(routeObj.icon_element, 'sidebar_icon_selected'));
        }

        constructor(routerConfig) {
            this.preload = [];
            this.routerConfig = routerConfig;
            this.routes = new Map();
            this.prevRoute = false;
            this.Route = false;

            Router._log('Init Router');

            $_querySelectorAll(".sidebar_icon a").map(this.initRoute.bind(this));

            window.addEventListener('hashchange', () => {
                Router._log('hashchange', this.Route, this);
                this.goRoute();
            });

            return this;
        }

        goRoute(route = Router.routeId(location.hash.slice(1))) {                       // get unique routeId
            Router._log('goRoute:', route);
            if (this.routes.has(route)) {                                               // if this routeId exists
                // Router._log('went Route:', route);
                [this.prevRoute, this.Route] = [this.Route, this.routes.get(route)];    // ES6 decomposition
                this.Route.load().show();                                               // load and show this route
                if (this.prevRoute) this.prevRoute.hide();                              // hide previous route
            }
        }

        initRoute(icon_element) {
            let route = Router.routeId(icon_element.href.split('#')[1]);                // get unique routeId
            let element = $_getElementById('placeholder_' + route);
            let trace = false;                                                          // true for detailed console trace

            $_hideElement(element);                                                     // hide Router page on first initRoute
            if (trace) Router._log('initRoute', route);
            this.routes.set(route, {
                route, icon_element, element,                                           // ES6 Object key+value init
                load() {
                    let placeholder = this.element;
                    Router.title(placeholder, true);                                    // display section title as Loading:
                    if (trace) Router._log('loadroute', this.route);

                    /** if the placeholder is empty, copy the TEMPLATE into it **/
                    Router._log(this.route);
                    if (placeholder.childElementCount < 1) placeholder.appendChild($_importNode(this.route));

                    return this;                                                        // make .load().show() chaining possible
                },
                show() {
                    let element = this.element;
                    Router.toggleIcon(this);
                    $_showElement(element, 'grid');
                    Router.title(element);
                    return this;
                },
                hide() {
                    Router.toggleIcon(this);
                    $_hideElement(this.element);    // hide Router page
                    $_forceDOMupdate();
                    return this;
                }

            });
            let preload = this.routerConfig.preload || $_getAttribute(element, 'preload');
            if (preload) this.preload.push(this.routes.get(route));
        }

        // noinspection JSUnusedGlobalSymbols
        preloadAll() {
            Router._log('preloadAll', this.preload);//todo: use ServiceWorker to preload all routes
            this.preload.map(thisRoute => thisRoute.load.call(thisRoute));
        }
    }

//endregion ======================================================= Basic Router with Templates
    $_app__QueryManager = new ITpings_Query_Manager();

    let DeviceColors = new StyleManager("DynamicDeviceColors"); // id of <STYLE> tag in DOM

    $_fetch($_localPath('ApplicationDevices'))
        .then(json => {
            $_getResultArray(json).map(device => DeviceColors.addDevice(device));

            __setHeartbeat(__heartbeat_default);
            $_addEventListener('focus', () => __setHeartbeat(__heartbeat_default)); // .5 second short-polling MySQL endpoint
            $_addEventListener('blur', () => __setHeartbeat(__heartbeat_blurred));  // 5 minute polling when window does NOT have focus

            window.$_ready(() => {
                $_app__Router = new Router({"preload": true});
                //$_app__Router.preloadAll();
                $_app__Router.goRoute();
            });

        });
})
(window, document.currentScript.ownerDocument, localStorage);
