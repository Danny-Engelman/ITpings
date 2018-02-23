!(function (window, document) {
    let backgroundColors = "#e6194b,#3cb44b,#ffe119,#0082c8,#f58231,#911eb4,#46f0f0,#f032e6,#d2f53c,#fabebe,#008080,#e6beff,#aa6e28,#fffac8,#800000,#aaffc3,#808000,#ffd8b1,#000080,#808080".split(",");

    let __createElement = (x) => document.createElement(x);
    let __createDocumentFragment = (x) => document.createDocumentFragment();
    let __createElement__DIV = (x) => __createElement('DIV');
    let __createElement__TABLE = (x) => __createElement('TABLE');

    let __appendChild = (parent, child) => parent.appendChild(child);
    let __insertBefore = (parent, child, referenceNode) => parent.insertBefore(child, referenceNode);

    let __getAttribute = (element, property) => element.getAttribute(property);
    let __setAttribute = (element, property, value) => element.setAttribute(property, value);

    let __classList_add = (x, y) => x.classList.add(y);
    let __Object_keys = x => Object.keys(x);

    let __setAttributes = (element, propertyarray) => __Object_keys(propertyarray).map((property) => __setAttribute(element, property, propertyarray[property]));

    let __localPath = x => {
        let uri = location.href.split('/');                                                 // get endpoint from current uri location
        uri.pop();                                                                          // discard filename
        uri.push('ITpings_connector.php?query=' + x);                                // stick on query endpoint
        uri = uri.join('/');
        return uri;
    };
    let __ATTR_data_query = 'query';
    let __ATTR_data_chartid = 'chartid';
    let __ATTR_data_sensorname = 'sensorname';
    let __ATTR_data_interval = 'interval';
    let __ATTR_data_intervalunit = 'intervalunit';

    let __STR_MINUTE = 'MINUTE';
    let __STR_HOUR = 'HOUR';
    let __STR_DAY = 'DAY';
    let __STR_WEEK = 'WEEK';
    let __STR_MONTH = 'MONTH';
    let __STR_YEAR = 'YEAR';
    let __PROPERTY_innerHTML = 'innerHTML';

    (function (elementName = 'itpings-table') {
        //let __log = (a, b, c) => console.log(`%cWC:${elementName}:`, 'background:lightgreen', a || '', b || '', c || '');
        let __log = () => false;

        window.customElements.define(elementName, class extends HTMLElement {
            static get observedAttributes() {
                __log('observedAttributes');
                return [__ATTR_data_query];
            }

            constructor() {
                super();
                __log('constructor');
            }

            attributeChangedCallback(attr, oldValue, newValue) {
                __log('attributeChanged', attr, oldValue, newValue);
                let _wc = this;
                _wc[attr] = newValue;
                if (attr === __ATTR_data_query) {
                    let uri = __localPath(newValue);
                    let filter = '';
                    let idle = true;                                                                    // no new fetch when still waiting or previous one
                    let TABLEWRAPPER = __createElement__DIV();
                    let TABLE = __appendChild(__createDocumentFragment(), __createElement__TABLE());        // new TABLE (as fragment)
                    let section = x => __appendChild(TABLE, __createElement(x));
                    let THEAD = section('THEAD');                                                       // add THEAD
                    let TBODY = section('TBODY');                                                       // add (first) TBODY
                    let _HEADmarker = 'h';
                    __appendChild(TABLEWRAPPER, TABLE);
                    __classList_add(TABLEWRAPPER, 'table-wrapper');
                    /** Table Sorter with CSS: http://kizu.ru/en/blog/variable-order/ **/
                    // let addsort = (id, name = 'sort', checked = false) => {
                    //     let INPUT = appendChild(TABLEWRAPPER,__createElement('INPUT'));
                    //     __setAttribute(INPUT,'type', 'radio');
                    //     __setAttribute(INPUT,'name', name);
                    //     __setAttribute(INPUT,'id', id);
                    //     if (checked) __setAttribute(INPUT,'checked', 'checked');
                    // };
                    // addsort('sort-by-name');
                    // addsort('sort-by-published');
                    // addsort('sort-by-views');
                    // addsort('sort-descending', 'sort-order', true);
                    // addsort('sort-ascending', 'sort-order');
                    TABLE.maxid = false;
                    TABLE.hiddenfields = new Set(['timestamp', 'created']);

                    let addRow = (row, idx = _HEADmarker) => {                                               // function
                        let TR = (idx === _HEADmarker ? THEAD : TBODY).insertRow(idx);                       // add TR at bottom of THEAD _OR_ bottom/top TBODY
                        __classList_add(TR, 'table-row');
                        __Object_keys(row).map(name => {                                                  // add Columns
                            if (!TABLE.hiddenfields.has(name)) {
                                let TD = TR.insertCell();
                                let value = idx === _HEADmarker ? name : row[name];                              // add Header Name _OR_ Cell Value
                                if (name === TABLE.idfield && Number(value) > TABLE.maxid) TABLE.maxid = Number(value);
                                __setAttribute(TR, 'data-' + name, value);                                     // plenty of attributes so we can apply CSS
                                __setAttributes(TD, {
                                    ['data-' + name]: value,
                                    'data-column': name
                                });
                                //__classList_add(TD,'table-cell');                                            // color cell
                                __classList_add(TD, 'fadeOutCell');                                            // color cell
                                TD[__PROPERTY_innerHTML] = value;
                            }
                        });
                    };

                    (function fetchTableData() {                                                        // IIFE execution, after that by setTimeout
                        if (idle) {
                            idle = false;                                                               // true again AFTER fetch processing is done
                            __log(uri + filter);
                            fetch(uri + filter)
                                .then(response => response.json())
                                .then(json => {
                                    if (!TABLE.idfield) {                                               // initialize TABLE
                                        addRow(json[0]);                                                // first row keys are the THEAD columnheaders
                                        TABLE.idfield = _wc.idfield || __Object_keys(json[0])[0];         // take from attribute _OR_ first JSON row
                                        json.map(addRow);                                               // add all rows

                                        __appendChild(_wc, TABLEWRAPPER);                                         // now append that sucker to the DOM
                                    } else if (json.length) {                                           // add rows
                                        TBODY = __insertBefore(TABLE, section('TBODY'), TBODY);                    // in a new TBODY at the top of the TABLE
                                        __classList_add(TBODY, 'updatedTBODY');
                                        json.map(row => addRow(row, 0));                                // add rows at top of TBODY
                                    }
                                    idle = true;
                                    filter = '&filter=' + TABLE.idfield + ' gt ' + TABLE.maxid;         // add filter on uri to get only new values
                                    if (heartbeat) setTimeout(fetchTableData, 1000);
                                })
                        }
                    })();
                }
            }

            connectedCallback() {
                __log('connected');
            }
        });
    })();


    (function (elementName = 'itpings-chart') {

        let __INTERVALS = [{
            label: '5m',
            interval: 5,
            intervalunit: __STR_MINUTE
        }, {
            label: '30m',
            interval: 30,
            intervalunit: __STR_MINUTE
        }, {
            label: '2H',
            interval: 2,
            intervalunit: __STR_HOUR
        }, {
            label: '6H',
            interval: 6,
            intervalunit: __STR_HOUR
        }, {
            label: '1D',
            interval: 1,
            intervalunit: __STR_DAY
        }, {
            label: '2D',
            interval: 2,
            intervalunit: __STR_DAY
        }, {
            label: '2W',
            interval: 2,
            intervalunit: __STR_WEEK
        }, {
            label: '1M',
            interval: 1,
            intervalunit: __STR_MONTH
        }, {
            label: '6M',
            interval: 6,
            intervalunit: __STR_MONTH
        }, {
            label: '1Y',
            interval: 1,
            intervalunit: __STR_YEAR
        }];

        let __log = (a, b, c) => console.log(`%cWC:${elementName}:`, 'background:lightblue', a || '', b || '', c || '');

        window.customElements.define(elementName, class extends HTMLElement {
            //region Custom Element Getters/Setters
            static get observedAttributes() {
                __log('initiated observedAttributes');
                return [__ATTR_data_sensorname, __ATTR_data_interval, __ATTR_data_intervalunit];
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
                __log('set interval:', newValue);
                __setAttribute(this, __ATTR_data_interval, newValue);
            }

            get intervalunit() {
                return __getAttribute(this, __ATTR_data_intervalunit);
            }

            set intervalunit(newValue) {
                __log('set intervalunit:', newValue);
                __setAttribute(this, __ATTR_data_intervalunit, newValue);
            }

            //endregion

            displayChart(localStorage_interval = false) {
                let _wc = this;
                let sensorname = _wc.sensorname;
                let interval, intervalunit;
                if (localStorage_interval) {
                    interval = Number(localStorage_interval.match(/\d+/g)[0]);
                    intervalunit = localStorage_interval.match(/[a-zA-Z]+/g)[0];
                    let intervalDIV = _wc.querySelector("[interval='" + interval + "'][intervalunit='" + intervalunit + "']");
                    _wc.markChartInterval(intervalDIV);
                    _wc.interval = interval;
                    _wc.intervalunit = intervalunit;
                } else {
                    interval = _wc.interval;
                    intervalunit = _wc.intervalunit;
                }
                //let uri = 'http://365csi.nl/itpings.nl/ITpings_connector.php?query=SensorValues&sensorname=' + sensorname + '&orderby=created&interval=' + interval + '&intervalunit=' + intervalunit;
                let uri = __localPath('SensorValues&sensorname=' + sensorname + '&orderby=created&interval=' + interval + '&intervalunit=' + intervalunit);
                // __log('displayChart', uri);
                // __log(sensorname, interval, intervalunit);

                let chart = new Chart(_wc.CANVAS, {
                    type: 'line',
                    data: [],
                    options: {
                        maintainAspectRatio: false,
                        title: {
                            display: true,
                            text: sensorname
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
                        let charttitle = '';
                        __log('chartdata');
                        chartdata = json.reduce(function (chartdata, value) {
                                charttitle = value.sensorname;
                                let dataset_index = chartdata.sensorids.indexOf(value._sensorid);
                                if (dataset_index < 0) {
                                    dataset_index = chartdata.datasets.length;

                                    chartdata.datasets.push({
                                        label: value.dev_id
                                        , fill: false
                                        , lineTension: .5
                                        , backgroundColor: backgroundColors[dataset_index]
                                        , borderColor: backgroundColors[dataset_index]
                                        , data: []
                                    });
                                    chartdata.sensorids.push(value._sensorid);
                                }
                                let x_label = value.created;//moment(value.created).format("ddd, M-D hA");
                                //let x_label = moment(value.created).format("D MMM H:mm");
                                //__log(value.created, x_label, value);
                                chartdata.datasets[dataset_index].data.push({
                                    x: x_label,
                                    y: value.sensorvalue
                                });
                                chartdata.labels.push(x_label);
                                lastpingid = value._pingid;
                                return chartdata;
                            }
                            , chartdata);
                        chart.data.labels = chartdata.labels;
                        chart.data.datasets = chartdata.datasets;
                        chart.update();
                    });
            }

            constructor() {
                super();
                this.updateChart = false;
                this.chartID = false;
            }

            attributeChangedCallback(attr, oldValue, newValue) {
                let _wc = this;
                //__log('attributeChanged:', attr + ' / ' + oldValue + ' / ' + newValue);
                if (_wc.isConnected && _wc.updateChart) {
                    __log('attributeChanged (Connected):', attr + ' / ' + oldValue + ' / ' + newValue);
                    _wc.displayChart();
                }
            }

            markChartInterval(intervalDIV) {
                [...this.INTERVALS.children].map(E => E.classList[E == intervalDIV ? 'add' : 'remove']('selectedInterval'));
            }

            setChartInterval(intervalDIV) {
                let interval, intervalunit;
                let _wc = this;
                _wc.updateChart = false;
                if (typeof intervalDIV === 'string') intervalDIV = _wc.INTERVALS.querySelector("[id='" + intervalDIV + "']");
                localStorage.setItem(_wc.chartid + '_interval', intervalDIV.id);
                _wc.interval = intervalDIV.getAttribute('interval');
                _wc.updateChart = true;
                _wc.intervalunit = intervalDIV.getAttribute('intervalunit');
                _wc.markChartInterval(intervalDIV);
            }

            connectedCallback() {
                let _wc = this;
                __log('connectedCallback', _wc.isConnected ? 'connected' : 'NOT connected');
                __log('id',);

                let DIV = __createElement__DIV();
                let section = x => __appendChild(DIV, __createElement(x));
                __classList_add(DIV, 'chart');
                __setAttribute(DIV, 'style', 'position:relative;height:inherit;');
                _wc.CANVAS = section('CANVAS');
                let INTERVAL = _wc.INTERVALS = section('DIV');
                __classList_add(INTERVAL, 'chart_interval');
                __appendChild(INTERVAL, __INTERVALS.reduce((fragment, interval) => {
                    let DIV = __createElement__DIV();
                    DIV[__PROPERTY_innerHTML] = interval.label;
                    __setAttributes(DIV, {
                        'id': interval.intervalunit + interval.interval,
                        'interval': interval.interval,
                        'intervalunit': interval.intervalunit
                    });
                    // __setAttribute(DIV, 'id', interval.intervalunit + interval.interval);
                    // __setAttribute(DIV, 'interval', interval.interval);
                    // __setAttribute(DIV, 'intervalunit', interval.intervalunit);
                    DIV.addEventListener('click', _ => _wc.setChartInterval(DIV));
                    __appendChild(fragment, DIV);
                    return fragment;
                }, __createDocumentFragment()));

                __appendChild(_wc, DIV);

                let chartid = _wc[__ATTR_data_chartid];
                let storedInterval = false;
                if (chartid) {
                    storedInterval = localStorage.getItem(chartid + '_interval');
                    __log('stored', storedInterval);
                } else {
                    console.error('no id on chart', _wc);
                }

                _wc.displayChart(storedInterval);

            }

            disconnectedCallback() {
                __log('disconnected', this.isConnected ? 'connected' : 'NOT connected');
            }
        });
    })();

    let heartbeat = 1;
}(window, document.currentScript.ownerDocument));

