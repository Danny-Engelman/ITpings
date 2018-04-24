//region ========================================================== Basic Router with Templates
class Router {
    constructor(routerConfig) {
        this.routes = new Map();
        let _log = function () {
            __log("Router", "yellow", ...arguments);
        };
        _log(routerConfig);
        let previousRoute = false, currentRoute = false;
        let toggleIcon = routeObj => __toggleClass(routeObj.icon_element, 'sidebar_icon_selected');
        let routeId = route => 'article_' + (route === '' ? 'dashboard' : route);
        let initRoute = icon_element => {
            let route = routeId(icon_element.href.split('#')[1]);
            let placeholder_element = __getElementById('placeholder_' + route);
            __hideElement(placeholder_element);
            this.routes.set(route, {
                route, icon_element, placeholder_element,
                load: function () { // can't use arrow function here
                    let placeholder = this.placeholder_element;
                    _log('loadroute', this);
                    if (placeholder.childElementCount < 1) placeholder.appendChild(__importNode(this.route));
                    return this; // make .load().show() chaining possible
                },
                show: function () {
                    __showElement(this.placeholder_element, 'grid');
                    toggleIcon(this);
                }
            });
            let preload = routerConfig.preload || placeholder_element.getAttribute('preload');
            if (preload) {
                let thisRoute = this.routes.get(route);
                // window.setTimeout(thisRoute.load.bind(thisRoute), 2000);
            }
        };
        ['hashchange', 'load'].map(evt => window.addEventListener(evt, () => {
                if (evt === 'load') __querySelectorAll(".sidebar_icon a").map(initRoute);
                let route = routeId(location.hash.slice(1));
                [previousRoute, currentRoute] = [currentRoute, this.routes.get(route)];
                _log('changeRoute:', route, currentRoute);
                currentRoute.load().show();
                if (previousRoute) {
                    __hideElement(previousRoute.placeholder_element);
                    toggleIcon(previousRoute);
                }
            }
        ));
    }
}

//endregion ======================================================= Basic Router with Templates



//region ========================================================== Basic 2008 Router with Templates
// using mr. jQuery John Resig 2008 <script> trick, https://gist.github.com/jhbsk/4443721
// coded for extreme minification, 1300 bytes, 700 bytes GZipped
(function (window, routes = {}, events = [], cache = {}, el
    , __str_refresh = 'refresh'
    , __str_EventListener = 'EventListener'
    , __str_removeEventListener = 'remove' + __str_EventListener
    , __str_addEventListener = 'add' + __str_EventListener) {
    let _log = (a, b, c, d, e, f, g, h, i) => __log("router:", "goldenrod", a, b, c, d, e, f, g, h, i);
    let fn, route, ctrl, __defineProperty,
        __getElementById = x => document.getElementById(x),
        __templated = (str, data) => {// (MIT) Simple Templating, by John Resig : https://johnresig.com/blog/javascript-micro-templating/
            fn = !/\W/.test(str) ? cache[str] = cache[str] || __templated(__getElementById(str).innerHTML)
                : new Function("F", "var p=[];with(F){p.push('" + str.replace(/[\r\t\n]/g, " ")
                        .split("<%").join("\t").replace(/((^|%>)[^\t]*)'/g, "$1\r").replace(/\t=(.*?)%>/g, "',$1,'")
                        .split("\t").join("');").split("%>").join("p.push('").split("\r").join("\\'")
                    + "');}return p.join('');");
            return data ? fn(data) : fn;
        },
        __allEvents = fnName => events.map(evt => [...el.querySelectorAll(evt[0])].map(efunc => efunc[fnName].apply(efunc, evt.slice(1)))),
        __addEventListener = x => window[__str_addEventListener](x, _ => {
            __allEvents(__str_removeEventListener);                                 // Remove current event listeners:
            el = __getElementById('RouterDIV');                                    // Lazy load view element
            events = [];                                                            // clear events
            route = routes[location.hash.slice(1) || '/'] || routes['*'];           // Get route by routeurl or fallback if it does not exist:
            if (route && route.Ctrl && route.id) {                                  // Do we have a Ctrl, and something to render
                ctrl = new route.Ctrl();
                route.onRefresh(_ => {                                             // Listen on route refreshes:
                    __allEvents(__str_removeEventListener);                         // Remove current event listeners:
                    el.innerHTML = __templated(route.id, ctrl);                     // Render route template with John Resig's template engine:
                    __allEvents(__str_addEventListener);                            // addEventListeners
                });
                ctrl[__str_refresh]();                                              // Trigger the first refresh:
            }
        });
    window["route"] = (path, id, Ctrl, listeners = []) => {                         // Defines a route:
        if (typeof id === 'function') [Ctrl, id] = [id, null];
        __defineProperty = (evt, value) => Object.defineProperty(Ctrl.prototype, evt, {value});
        __defineProperty('on', function () {                                        // on Route, can't use Arraw Function because this needs to be the IIFE scope
            events.push([...arguments])
        });
        __defineProperty(__str_refresh, function (listeners) {
            listeners.map(fn => fn())
        }.bind(window, listeners));
        routes[path] = {id, Ctrl, onRefresh: listeners.push.bind(listeners)};
    };
    __addEventListener('hashchange');                                               // Listen on hash change:
    __addEventListener('load');                                                     // Listen on page load:
})(window);
//endregion ======================================================= Basic Router with Templates