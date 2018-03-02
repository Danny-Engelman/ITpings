!(function (window, document) {

        let __log = (elementName, background, a = '', b = '', c = '', d = '', e = '', f = '', g = '', h = '') => {
            console.log(`%cWC:${elementName}:`, 'background:' + background, a, b, c, d, e, f, g, h);
        };

        let heartbeat_seconds = 0;

        let __ITpings_SQL_result = 'result';

        //with hardcoded Device IDs these always get the same color coding
        let myDeviceIDs = "ttn_node_365csi_nl_001,ttn_node_365csi_nl_002".split`,`;

        class StyleSheetManager {
            constructor(style_title) {
                let _dcm = this;
                let __YELLOW = '#ffe119';
                _dcm.devices = {};
                _dcm.colors = "#e6194b,#0082c8,#f58231,#911eb4,#46f0f0,#f032e6,#d2f53c,#fabebe,#008080,#e6beff,#aa6e28,#fffac8,#800000,#aaffc3,#808000,#ffd8b1,#000080,#808080".split(",");
                _dcm.styles = [...document.styleSheets].filter(x => x.title === style_title)[0];
            }

            getColor(deviceName) {
                let _dcm = this;
                let color;
                let devices = _dcm.devices;
                if (devices.hasOwnProperty(deviceName)) {
                    color = devices[deviceName];
                } else {
                    color = _dcm.colors.shift();
                    devices[deviceName] = color;
                    let styleRule = "TD[data-dev_id='" + deviceName + "'] {border-bottom: 3px solid " + color + ";}";
                    _dcm.styles.insertRule(styleRule, 0);
                }
                return color;
            }
        }

        let DeviceColors = new StyleSheetManager('DynamicDeviceColors');
        myDeviceIDs.map(x => DeviceColors.getColor(x));

        let __strReverse = x => [...x].reverse().join``;
        let __daysSince = x => Math.floor((new Date(x).getTime() - new Date().getTime()) / 864e5);

        let __createElement = x => document.createElement(x);
        let __createDocumentFragment = () => document.createDocumentFragment();
        let __createElement__DIV = (x) => {
            let DIV = __createElement('DIV');
            x && (DIV.innerHTML = x);
            return DIV;
        };
        let __createElement__TABLE = () => __createElement('TABLE');
        let __createElement__TBODY = () => __createElement('TBODY');

        let __appendChild = (parent, child) => parent.appendChild(child);
        let __insertBefore = (parent, child, referenceNode) => parent.insertBefore(child, referenceNode);

        let __getAttribute = (element, property) => element.getAttribute(property);
        let __setAttribute = (element, property, value) => element.setAttribute(property, value);

        let __classList_add = (x, y) => x.classList.add(y);
        let __Object_keys = x => Object.keys(x);

        let __setAttributes = (element, propertyarray) => __Object_keys(propertyarray).map((property) => __setAttribute(element, property, propertyarray[property]));

        let __localPath = x => {
            let uri = location.href.split`/`;                                                 // get endpoint from current uri location
            uri.pop();                                                                          // discard filename
            uri.push('ITpings_connector.php?query=' + x);                                // stick on query endpoint
            uri = uri.join`/`;
            return uri;
        };

        let __abbreviated_DeviceID = x => x.split`_`.reverse()[0];

        let __ATTR_data_pulse = 'pulse';
        let __ATTR_data_query = 'query';
        let __ATTR_data_chartid = 'chartid';
        let __ATTR_data_sensorname = 'sensorname';
        let __ATTR_data_interval = 'interval';

        let __STR_MINUTE = 'MINUTE';
        let __STR_HOUR = 'HOUR';
        let __STR_DAY = 'DAY';
        let __STR_WEEK = 'WEEK';
        let __STR_MONTH = 'MONTH';
        let __STR_YEAR = 'YEAR';
        let __PROPERTY_innerHTML = 'innerHTML';

        (function (elementName = 'itpings-table') {
            let _log = (a, b, c, d, e, f, g, h) => __log(elementName, 'lightgreen', a, b, c, d, e, f, g, h);

            let _HEADmarker = 'h';

            window.customElements.define(elementName, class extends HTMLElement {
                static get observedAttributes() {
                    _log('observedAttributes');
                    return [__ATTR_data_query, __ATTR_data_pulse];
                }

                constructor() {
                    super();
                    _log('constructor');
                    this.maxid = 1;
                }

                addRow(row, idx = _HEADmarker) {                                               // function
                    let _wc = this;
                    let isTBODY = idx !== _HEADmarker;
                    let TR = (isTBODY ? _wc.TBODY : _wc.THEAD).insertRow(idx);                       // add TR at bottom of THEAD _OR_ bottom/top TBODY

                    __Object_keys(row).map(name => {                                                  // add Columns
                        if (!_wc.hiddenfields.has(name)) {
                            let TD = TR.insertCell();
                            let value = idx === _HEADmarker ? name : row[name];                              // add Header Name _OR_ Cell Value

                            __setAttribute(TR, 'data-' + name, value);                                     // plenty of attributes so we can apply CSS
                            __setAttributes(TD, {
                                ['data-' + name]: value,
                                'data-column': name
                            });
                            __classList_add(TD, 'fadeOutCell');                                            // color cell
                            if (isTBODY && name === 'LastSeen') {
                                let lastseen = moment(value);
                                let now = moment();
                                value = lastseen.diff(now, 'minutes');
                            }
                            if (name === _wc.idfield) {
                                if (Number(value) > _wc.maxid) _wc.maxid = Number(value);

                                //     __setAttribute(_wc.TBODY, 'data-' + name, value);                                     // plenty of attributes so we can apply CSS
                                //     TR.addEventListener('mouseenter', () => {
                                //         let _wc = this;
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
                                //                 //console.info('mouseleave', this, TBODYs_pingid);
                                //                 TBODYs_pingid.map((tbody) => {
                                //                     tbody.parentNode.scrollIntoView();
                                //                     tbody.style.border = '';
                                //                 });
                                //             });
                                //             TR.hasMouseLeaveListener = true;
                                //         }
                                //     });
                            }
                            /** set Cell innerHTML **/
                            TD[__PROPERTY_innerHTML] = value;
                        }
                    });
                };

                fetchData(filter) {
                    let _wc = this;
                    let _addRowFunc = _wc.addRow.bind(_wc);
                    if (_wc.idle) {
                        _wc.idle = false;                                                               // true again AFTER fetch processing is done
                        _log(filter);
                        fetch(_wc.uri + filter)
                            .then(response => response.json())
                            .then(json => {
                                _log(json.sql);
                                let result = json[__ITpings_SQL_result];
                                if (result) {
                                    if (!_wc.idfield) {                                               // initialize TABLE
                                        let headers = result[0];
                                        _addRowFunc(headers);                                                // first row keys are the THEAD columnheaders
                                        _wc.idfield = __Object_keys(headers)[0];         // take from attribute _OR_ first JSON row
                                        result.map(_addRowFunc);                                               // add all rows
                                        __appendChild(_wc, _wc.TABLEWRAPPER);                                         // now append that sucker to the DOM
                                    } else if (result.length) {                                           // add rows
                                        // new values read from Database
                                        // append new TBODY
                                        _wc.TBODY = __insertBefore(_wc.TABLE, __appendChild(_wc.TABLE, __createElement__TBODY()), _wc.TBODY);                    // in a new TBODY at the top of the TABLE
                                        // animate background color of this newPing
                                        __classList_add(_wc.TBODY, 'newPing');
                                        result.map(row => _addRowFunc(row, 0));                                // add rows at top of TBODY
                                        _wc.notifyOthers();
                                    }
                                    _wc.idle = true;
                                    _wc.timeout_id = setTimeout(() => {
                                        _wc.doPulse();
                                    }, heartbeat_seconds);
                                } else {
                                    let query = _wc[__ATTR_data_query];
                                    let src = `<b><a href=?query='${query}'>${query}</a></b>`;
                                    let DIV = __createElement__DIV(src + ' is not a valid ITpings result source');
                                    __classList_add(DIV, 'itpings-table-error');
                                    __appendChild(_wc, DIV);
                                }
                            })
                            .catch(error => {
                                console.error(error);
                                _wc.innerHTML = error;
                            });
                    }
                }

                notifyOthers() {
                    let _wc = this;
                    if (!_wc.isPulsed) [...document.querySelectorAll("itpings-table")].map(table => {// tell every other table we received data
                            let table_query = table.getAttribute('query');
                            _log('send Pulse to:', table_query);
                            if (table_query !== _wc.query) table.setAttribute('pulse', _wc.query);
                        }
                    );
                    _wc.isPulsed = false;
                }

                doPulse() {
                    let _wc = this;
                    if (heartbeat_seconds) {
                        clearTimeout(_wc.timeout_id);
                        _log('doPulse NOW', heartbeat_seconds, _wc.idfield, _wc.maxid, _wc.query);
                        _wc.fetchData('&filter=' + _wc.idfield + ' gt ' + _wc.maxid); // add filter on uri to get only new values
                    }
                }

                attributeChangedCallback(attr, oldValue, newValue) {
                    //_log('attributeChanged', attr, oldValue, newValue);
                    let _wc = this;
                    _wc[attr] = newValue;
                    if (attr === __ATTR_data_query) {
                        _wc.uri = __localPath(newValue);
                        _wc.filter = '';
                        _wc.idle = true;                                                                    // no new fetch when still waiting or previous one
                    } else if (attr === __ATTR_data_pulse) {
                        _log('doPulse from', attr, oldValue, newValue, _wc.query);
                        _wc.isPulsed = true;
                        _wc.doPulse();
                        //if (oldValue !== newValue && newValue !== _wc.query) _wc.doPulse();
                    }
                }

                connectedCallback() {
                    //_log('connectedCallback');
                    let _wc = this;
                    _wc.TABLEWRAPPER = __createElement__DIV();
                    _wc.TABLE = __appendChild(__createDocumentFragment(), __createElement__TABLE());        // new TABLE (as fragment)
                    let section = x => __appendChild(_wc.TABLE, __createElement(x));
                    _wc.THEAD = section('THEAD');                                                       // add THEAD
                    _wc.TBODY = section('TBODY');                                                       // add (first) TBODY
                    let TITLE = __appendChild(_wc.TABLEWRAPPER, __createElement__DIV());
                    TITLE.innerHTML = _wc['title'];
                    __classList_add(TITLE, 'table-title');
                    __appendChild(_wc.TABLEWRAPPER, _wc.TABLE);
                    __classList_add(_wc.TABLEWRAPPER, 'table-wrapper');

                    _wc.hiddenfields = new Set(['timestamp', 'created']);
                    _log('connectedCallback END');
                    _wc.fetchData('');
                }
            })
            ;
        })(); // function (elementName = 'itpings-table')


        (function (elementName = 'itpings-chart') {
            let _log = (a, b, c, d, e, f, g, h) => __log(elementName, 'lightblue', a, b, c, d, e, f, g, h);

            let __INTERVALS = {
                /** keys can not start with numbers , so keys are labels in reverse **/
                "m5": {
                    interval: 5,
                    unit: __STR_MINUTE,
                    xformat: "H:mm"
                }, "m03": { // label = 30m
                    interval: 30,
                    unit: __STR_MINUTE,
                    xformat: "H:mm"
                }, "H1": {
                    interval: 1,
                    unit: __STR_HOUR,
                    xformat: "H:mm"
                }, "H2": {
                    interval: 2,
                    unit: __STR_HOUR,
                    xformat: "H:mm"
                }, "H6": {
                    interval: 6,
                    unit: __STR_HOUR,
                    xformat: "H:mm"
                }, "D1": {
                    interval: 1,
                    unit: __STR_DAY,
                    xformat: "D MMM H:mm"
                }, "D2": {
                    interval: 2,
                    unit: __STR_DAY,
                    xformat: "D MMM H:mm"
                }, "W2": {
                    interval: 2,
                    unit: __STR_WEEK,
                    xformat: "D MMM H:mm"
                }, "M1": {
                    interval: 1,
                    unit: __STR_MONTH,
                    xformat: "D MMM"
                }, "M6": {
                    interval: 6,
                    unit: __STR_MONTH,
                    xformat: "D MMM"
                }, "Y1": {
                    interval: 1,
                    unit: __STR_YEAR,
                    xformat: "D MMM"
                }
            };

            window.customElements.define(elementName, class extends HTMLElement {
                //region ========================================================== Custom Element Getters/Setters
                static get observedAttributes() {
                    _log('initiated observedAttributes');
                    return [__ATTR_data_sensorname, __ATTR_data_interval];
                }

                get chartid() {
                    return __getAttribute(this, __ATTR_data_chartid);
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
                    _log('►►► set interval:', newValue);
                    let _wc = this;
                    let intervalDIV = _wc.INTERVALS.querySelector("[id='" + newValue + "']");
                    _log(intervalDIV);
                    localStorage.setItem(_wc.chartid + '_interval', newValue);

                    //loop all interval DIVs , add or remove Class: selectedInterval
                    [...this.INTERVALS.children].map(E => E.classList[E === intervalDIV ? 'add' : 'remove']('selectedInterval'));

                    __setAttribute(this, __ATTR_data_interval, newValue);
                    _wc.displayChart(false);
                }

                //endregion

                displayChart(localStorage_interval = false) {
                    let _wc = this;
                    let sensorname = _wc.sensorname;
                    if (localStorage_interval) {
                        // interval = Number(localStorage_interval.match(/\d+/g)[0]);
                        // intervalunit = localStorage_interval.match(/[a-zA-Z]+/g)[0];
                        _wc.interval = localStorage_interval;
                    }
                    let _interval_key = _wc.interval;
                    let _interval = __INTERVALS[_interval_key];
                    if (!_interval) _interval = __INTERVALS['H6'];
                    _log('displayChart _interval', _interval_key, _interval);
                    let uri = __localPath('SensorValues&sensorname=' + sensorname
                        + '&orderby=created&interval=' + _interval.interval
                        + '&intervalunit=' + _interval.unit + '&limit=none');
                    // _log('displayChart', uri);

                    if (_wc.chart) _wc.chart.destroy();
                    let chart = _wc.chart = new Chart(_wc.CANVAS, {
                        type: 'line',
                        data: [],
                        options: {
                            maintainAspectRatio: false,
                            title: {
                                display: true,
                                text: sensorname
                            },
                            showLines: true,
                            elements: {
                                line: {
                                    tension: 0
                                }
                            }
                        }
                    });
                    fetch(uri)
                        .then(response => response.json())
                        .then(json => {
                            let lastpingid = 0;
                            let chartdata = {
                                labels: []
                                , datasets: []
                                , sensorids: []
                            };
                            let result = json[__ITpings_SQL_result];
                            let charttitle = '';
                            let oldest_date = false;
                            let ratio = Math.ceil(result.length / 200);
                            _log('Chart:', result.length, _interval);
                            chartdata = result.reduce(function (chartdata, value, result_idx) {
                                    let device_id = value.dev_id;
                                    let sensorid = value._sensorid;
                                    let sensorvalue = value.sensorvalue;
                                    let dataset_index = chartdata.sensorids.indexOf(sensorid);
                                    let x_axis_time = value.created;
                                    //_log(device_id, sensorid, sensorvalue, x_axis_time);
                                    //_log(result_idx, ratio, x_axis_time, sensorid, sensorvalue);
                                    oldest_date = oldest_date || x_axis_time;
                                    x_axis_time = moment(x_axis_time).format(_interval.xformat);
                                    lastpingid = value._pingid;
                                    charttitle = value.sensorname;
                                    if (dataset_index < 0) {                                    // new sensor
                                        dataset_index = chartdata.datasets.length;
                                        let deviceColor = DeviceColors.getColor(device_id);

                                        chartdata.datasets.push({
                                            label: __abbreviated_DeviceID(device_id)
                                            , fill: false
                                            //, lineTension: .5
                                            , backgroundColor: deviceColor
                                            , borderColor: deviceColor
                                            , data: []
                                        });
                                        chartdata.sensorids.push(sensorid);
                                    }
                                    let keep_value = result_idx % ratio === 0;
                                    if (keep_value) {
                                        chartdata.datasets[dataset_index].data.push({
                                            x: x_axis_time,
                                            y: sensorvalue
                                        });
                                    }
                                    if (!chartdata.labels.includes(x_axis_time)) chartdata.labels.push(x_axis_time);
                                    return chartdata;
                                }
                                , chartdata);

                            _log('oldest date', new Date(oldest_date), __daysSince(oldest_date));
                            chart.data.labels = chartdata.labels;
                            chart.data.datasets = chartdata.datasets;
                            chart.update();
                        });
                }

                constructor() {
                    super();
                    this.chartID = false;
                }

                attributeChangedCallback(attr, oldValue, newValue) {
                    let _wc = this;
                    _log('attributeChanged:', attr + ' / ' + oldValue + ' / ' + newValue, _wc.isConnected ? 'connected!' : 'not! connected');
                    switch (attr) {
                        case(__ATTR_data_interval):
                            if (_wc.isConnected) {
                                _log('attributeChanged (Connected):', attr + ' / ' + oldValue + ' / ' + newValue);
                                //_wc.displayChart(false);
                            }
                            break;
                        default:
                            break;
                    }
                }

                connectedCallback() {
                    let _wc = this;
                    _log('connectedCallback');

                    //region ====================================================== create Chart DIV and Interval UI
                    let Chart_DIV = __createElement__DIV();
                    let section = x => __appendChild(Chart_DIV, __createElement(x));
                    let _addClass = x => __classList_add(Chart_DIV, x);

                    _addClass('chart');
                    __setAttribute(Chart_DIV, 'style', 'position:relative;height:inherit;');
                    _wc.CANVAS = section('CANVAS');


                    // Add interval UI to Chart DIV
                    let _intervals_wrapper = _wc.INTERVALS = section('DIV');
                    let _interval_DIV = interval_key => {
                        let DIV = __createElement__DIV(__strReverse(interval_key));         // because object keys need to start with a letter
                        __setAttribute(DIV, 'id', interval_key);
                        DIV.addEventListener('click', () => _wc.interval = interval_key);
                        return DIV;
                    };
                    let _append_Interval = x => __appendChild(_intervals_wrapper, _interval_DIV(x));
                    __Object_keys(__INTERVALS).map(interval_key => _append_Interval(interval_key));
                    __classList_add(_intervals_wrapper, 'chart_interval');


                    __appendChild(_wc, Chart_DIV);      // now append that sucker to the DOM
                    //endregion

                    let chartid = _wc[__ATTR_data_chartid];
                    let storedInterval = false;
                    if (chartid) {
                        storedInterval = localStorage.getItem(chartid + '_interval');
                        _log('stored:', chartid, storedInterval);
                        if (storedInterval) _wc.interval = storedInterval;
                        else _wc.interval = _wc.interval;   // force interval setter
                    } else {
                        console.error('no id on chart', _wc);
                    }

                    // if (heartbeat_seconds) setTimeout(function () {
                    //     _log('Update Chart');
                    // }, 1000);
                }

                disconnectedCallback() {
                    _log('disconnected', this.isConnected ? 'connected' : 'NOT connected');
                }
            });
        })(); // function (elementName = 'itpings-chart')

    }
)
(window, document.currentScript.ownerDocument);

