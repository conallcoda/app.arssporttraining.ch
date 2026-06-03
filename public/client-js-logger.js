(function () {
    'use strict';

    if (window.__clientJsLoggerInstalled) {
        return;
    }

    window.__clientJsLoggerInstalled = true;

    var endpoint = '/client-js-log';
    var queue = [];
    var flushTimer = null;
    var maxQueuedEntries = 50;

    function stringify(value) {
        var type = typeof value;

        if (value instanceof Error) {
            return {
                name: value.name,
                message: value.message,
                stack: value.stack || null
            };
        }

        if (value === null || type === 'string' || type === 'number' || type === 'boolean' || type === 'undefined') {
            return String(value);
        }

        try {
            return JSON.stringify(value);
        } catch (error) {
            return Object.prototype.toString.call(value);
        }
    }

    function metadata() {
        return {
            url: window.location.href,
            userAgent: window.navigator.userAgent,
            language: window.navigator.language || null,
            viewport: {
                width: window.innerWidth || null,
                height: window.innerHeight || null
            },
            at: new Date().toISOString()
        };
    }

    function sendNow(entries) {
        var body;

        try {
            body = JSON.stringify({
                entries: entries,
                meta: metadata()
            });
        } catch (error) {
            return;
        }

        if (window.navigator.sendBeacon) {
            try {
                if (window.navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/json' }))) {
                    return;
                }
            } catch (error) {}
        }

        if (window.fetch) {
            try {
                window.fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: body,
                    credentials: 'same-origin',
                    keepalive: true
                });
            } catch (error) {}
        }
    }

    function flush() {
        var entries = queue.slice();

        queue = [];
        flushTimer = null;

        if (entries.length) {
            sendNow(entries);
        }
    }

    function capture(type, payload) {
        queue.push({
            type: type,
            payload: payload,
            at: new Date().toISOString()
        });

        if (queue.length > maxQueuedEntries) {
            queue.shift();
        }

        if (!flushTimer) {
            flushTimer = window.setTimeout(flush, 250);
        }
    }

    function captureConsole(level) {
        var original = window.console && window.console[level];

        if (!original) {
            return;
        }

        window.console[level] = function () {
            var args = Array.prototype.slice.call(arguments);

            capture('console.' + level, args.map(stringify));
            return original.apply(window.console, arguments);
        };
    }

    captureConsole('debug');
    captureConsole('error');
    captureConsole('info');
    captureConsole('log');
    captureConsole('warn');

    window.addEventListener('error', function (event) {
        capture('window.error', {
            message: event.message || null,
            source: event.filename || null,
            line: event.lineno || null,
            column: event.colno || null,
            error: event.error ? stringify(event.error) : null
        });

        flush();
    }, true);

    window.addEventListener('unhandledrejection', function (event) {
        capture('unhandledrejection', {
            reason: stringify(event.reason)
        });

        flush();
    });

    window.addEventListener('pagehide', flush);
})();
