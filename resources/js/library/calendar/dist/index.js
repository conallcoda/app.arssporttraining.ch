/*!
 * EventCalendar v5.4.1
 * https://github.com/vkurko/calendar
 */
import { untrack, tick, getAbortSignal, getContext, setContext, onMount, mount, unmount } from "svelte";
import * as $ from "svelte/internal/client";
import "svelte/internal/disclose-version";
import { SvelteMap } from "svelte/reactivity";
function keyEnter(fn, _this = void 0) {
  return function(e) {
    return e.key === "Enter" || e.key === " " && !e.preventDefault() ? fn.call(_this, e) : void 0;
  };
}
function contentFrom(content) {
  return (el) => {
    if (typeof content == "string") {
      el.innerText = content;
    } else if (content?.domNodes) {
      el.replaceChildren(...content.domNodes);
    } else if (content?.html) {
      el.innerHTML = content.html;
    }
  };
}
function outsideEvent(type) {
  return (el) => {
    let listener = (jsEvent) => {
      if (el && !el.contains(jsEvent.target)) {
        el.dispatchEvent(
          new CustomEvent(type + "outside", { detail: { jsEvent } })
        );
      }
    };
    document.addEventListener(type, listener, true);
    return () => {
      document.removeEventListener(type, listener, true);
    };
  };
}
function resizeObserver(callback) {
  return (el) => {
    let observer = new ResizeObserver((entries2) => {
      for (let entry of entries2) {
        callback(el, entry);
      }
    });
    observer.observe(el);
    return () => {
      observer.unobserve(el);
    };
  };
}
function intersectionObserver(callback, options) {
  return (el) => {
    let observer = new IntersectionObserver((entries2) => {
      for (let entry of entries2) {
        callback(el, entry);
      }
    }, options);
    observer.observe(el);
    return () => {
      observer.unobserve(el);
    };
  };
}
function assign(...args) {
  return Object.assign(...args);
}
function keys(object) {
  return Object.keys(object);
}
function entries(object) {
  return Object.entries(object);
}
function hasOwn(object, property) {
  return Object.hasOwn(object, property);
}
function floor(value) {
  return Math.floor(value);
}
function ceil(value) {
  return Math.ceil(value);
}
function min(...args) {
  return Math.min(...args);
}
function max(...args) {
  return Math.max(...args);
}
function symbol() {
  return /* @__PURE__ */ Symbol("ec");
}
function length(array) {
  return array.length;
}
function empty(array) {
  return !length(array);
}
function isArray(value) {
  return Array.isArray(value);
}
function isFunction(value) {
  return typeof value === "function";
}
function isPlainObject(value) {
  if (typeof value !== "object" || value === null) {
    return false;
  }
  const prototype = Object.getPrototypeOf(value);
  return prototype === null || prototype === Object.prototype;
}
function isDate(value) {
  return value instanceof Date;
}
function run(fn) {
  return fn();
}
function runAll(fns) {
  fns.forEach(run);
}
function noop() {
}
const identity = (x) => x;
function isRtl() {
  return window.getComputedStyle(document.documentElement).direction === "rtl";
}
const DAY_IN_SECONDS = 86400;
function createDate(input = void 0) {
  if (input !== void 0) {
    return isDate(input) ? _fromLocalDate(input) : _fromISOString(input);
  }
  return _fromLocalDate(/* @__PURE__ */ new Date());
}
function createDuration(input) {
  if (typeof input === "number") {
    input = { seconds: input };
  } else if (typeof input === "string") {
    let seconds = 0, exp = 2;
    for (let part of input.split(":", 3)) {
      seconds += parseInt(part, 10) * Math.pow(60, exp--);
    }
    input = { seconds };
  } else if (isDate(input)) {
    input = { hours: input.getUTCHours(), minutes: input.getUTCMinutes(), seconds: input.getUTCSeconds() };
  }
  let weeks = input.weeks || input.week || 0;
  return {
    years: input.years || input.year || 0,
    months: input.months || input.month || 0,
    days: weeks * 7 + (input.days || input.day || 0),
    seconds: (input.hours || input.hour || 0) * 60 * 60 + (input.minutes || input.minute || 0) * 60 + (input.seconds || input.second || 0),
    inWeeks: !!weeks
  };
}
function cloneDate(date) {
  return new Date(date.getTime());
}
function addDuration(date, duration, x = 1) {
  date.setUTCFullYear(date.getUTCFullYear() + x * duration.years);
  let month = date.getUTCMonth() + x * duration.months;
  date.setUTCMonth(month);
  month %= 12;
  if (month < 0) {
    month += 12;
  }
  while (date.getUTCMonth() !== month) {
    subtractDay(date);
  }
  date.setUTCDate(date.getUTCDate() + x * duration.days);
  date.setUTCSeconds(date.getUTCSeconds() + x * duration.seconds);
  return date;
}
function subtractDuration(date, duration, x = 1) {
  return addDuration(date, duration, -x);
}
function addDay(date, x = 1) {
  date.setUTCDate(date.getUTCDate() + x);
  return date;
}
function subtractDay(date, x = 1) {
  return addDay(date, -x);
}
function setMidnight(date) {
  date.setUTCHours(0, 0, 0, 0);
  return date;
}
function toLocalDate(date) {
  return new Date(
    date.getUTCFullYear(),
    date.getUTCMonth(),
    date.getUTCDate(),
    date.getUTCHours(),
    date.getUTCMinutes(),
    date.getUTCSeconds()
  );
}
function toISOString(date, len = 19) {
  return date.toISOString().substring(0, len);
}
function datesEqual(date1, ...dates2) {
  return dates2.every((date2) => date1.getTime() === date2.getTime());
}
function nextClosestDay(date, day) {
  let diff2 = day - date.getUTCDay();
  date.setUTCDate(date.getUTCDate() + (diff2 >= 0 ? diff2 : diff2 + 7));
  return date;
}
function prevClosestDay(date, day) {
  let diff2 = day - date.getUTCDay();
  date.setUTCDate(date.getUTCDate() + (diff2 <= 0 ? diff2 : diff2 - 7));
  return date;
}
function noTimePart(date) {
  return typeof date === "string" && date.length <= 10;
}
function copyTime(toDate, fromDate) {
  toDate.setUTCHours(fromDate.getUTCHours(), fromDate.getUTCMinutes(), fromDate.getUTCSeconds(), 0);
  return toDate;
}
function toSeconds(duration) {
  return duration.seconds;
}
function nextDate(date, duration, hiddenDays) {
  addDuration(date, duration);
  _skipHiddenDays(date, hiddenDays, addDay);
  return date;
}
function prevDate(date, duration, hiddenDays) {
  subtractDuration(date, duration);
  _skipHiddenDays(date, hiddenDays, subtractDay);
  return date;
}
function _skipHiddenDays(date, hiddenDays, dateFn) {
  if (hiddenDays.length && hiddenDays.length < 7) {
    while (hiddenDays.includes(date.getUTCDay())) {
      dateFn(date);
    }
  }
}
function getWeekNumber(date, firstDay) {
  date = cloneDate(date);
  if (firstDay === 0) {
    date.setUTCDate(date.getUTCDate() + 6 - date.getUTCDay());
  } else {
    date.setUTCDate(date.getUTCDate() + 4 - (date.getUTCDay() || 7));
  }
  let yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
  return Math.ceil(((date - yearStart) / 1e3 / DAY_IN_SECONDS + 1) / 7);
}
function _fromLocalDate(date) {
  return new Date(Date.UTC(
    date.getFullYear(),
    date.getMonth(),
    date.getDate(),
    date.getHours(),
    date.getMinutes(),
    date.getSeconds()
  ));
}
function _fromISOString(str) {
  const parts = str.match(/\d+/g);
  return new Date(Date.UTC(
    Number(parts[0]),
    Number(parts[1]) - 1,
    Number(parts[2]),
    Number(parts[3] || 0),
    Number(parts[4] || 0),
    Number(parts[5] || 0)
  ));
}
let payloadProp = symbol();
function setPayload(obj, payload) {
  obj[payloadProp] = payload;
}
function hasPayload(obj) {
  return !!obj?.[payloadProp];
}
function getPayload(obj) {
  return obj[payloadProp];
}
function createElement(tag, className, content, attrs = []) {
  let el = document.createElement(tag);
  el.className = className;
  if (typeof content == "string") {
    el.innerText = content;
  } else if (content.domNodes) {
    el.replaceChildren(...content.domNodes);
  } else if (content.html) {
    el.innerHTML = content.html;
  }
  for (let attr of attrs) {
    el.setAttribute(...attr);
  }
  return el;
}
function rect(el) {
  return el.getBoundingClientRect();
}
function ancestor(el, up) {
  while (up--) {
    el = el.parentElement;
  }
  return el;
}
function height(el) {
  return rect(el).height;
}
function getElementWithPayload(x, y, root2 = document, processed = []) {
  processed.push(root2);
  for (let el of root2.elementsFromPoint(x, y)) {
    if (hasPayload(el)) {
      return el;
    }
    if (el.shadowRoot && !processed.includes(el.shadowRoot)) {
      let shadowEl = getElementWithPayload(x, y, el.shadowRoot, processed);
      if (shadowEl) {
        return shadowEl;
      }
    }
  }
  return null;
}
function listen(node, event, handler, options) {
  node.addEventListener(event, handler, options);
  return () => node.removeEventListener(event, handler, options);
}
function stopPropagation(fn, _this = void 0) {
  return function(jsEvent) {
    jsEvent.stopPropagation();
    if (fn) {
      fn.call(_this, jsEvent);
    }
  };
}
function createView(view2, _viewTitle, _currentRange, _activeRange) {
  return {
    type: view2,
    title: _viewTitle,
    currentStart: _currentRange.start,
    currentEnd: _currentRange.end,
    activeStart: _activeRange.start,
    activeEnd: _activeRange.end,
    calendar: void 0
  };
}
function toViewWithLocalDates(view2) {
  view2 = assign({}, view2);
  view2.currentStart = toLocalDate(view2.currentStart);
  view2.currentEnd = toLocalDate(view2.currentEnd);
  view2.activeStart = toLocalDate(view2.activeStart);
  view2.activeEnd = toLocalDate(view2.activeEnd);
  return view2;
}
let eventId = 1;
function createEvents(input) {
  return input.map((event) => {
    let result = {
      id: "id" in event ? String(event.id) : `{generated-${eventId++}}`,
      resourceIds: toArrayProp(event, "resourceId").map(String),
      allDay: event.allDay ?? (noTimePart(event.start) && noTimePart(event.end)),
      start: createDate(event.start),
      end: createDate(event.end),
      title: event.title ?? "",
      editable: event.editable,
      startEditable: event.startEditable,
      durationEditable: event.durationEditable,
      display: event.display ?? "auto",
      extendedProps: event.extendedProps ?? {},
      backgroundColor: event.backgroundColor ?? event.color,
      textColor: event.textColor,
      classNames: toArrayProp(event, "className"),
      styles: toArrayProp(event, "style")
    };
    if (result.allDay) {
      setMidnight(result.start);
      let end = cloneDate(result.end);
      setMidnight(result.end);
      if (!datesEqual(result.end, end) || datesEqual(result.end, result.start)) {
        addDay(result.end);
      }
    }
    return result;
  });
}
function toArrayProp(input, propName) {
  let result = input[propName + "s"] ?? input[propName] ?? [];
  return isArray(result) ? result : [result];
}
function createEventSources(input) {
  return input.map((source) => ({
    events: source.events,
    url: source.url && source.url.trimEnd("&") || "",
    method: source.method && source.method.toUpperCase() || "GET",
    extraParams: source.extraParams || {}
  }));
}
function createEventContent(chunk, displayEventEnd, eventContent, theme, _intlEventTime, _view) {
  let timeText = _intlEventTime.formatRange(
    chunk.start,
    displayEventEnd && chunk.event.display !== "pointer" && !chunk.zeroDuration ? copyTime(cloneDate(chunk.start), chunk.end) : chunk.start
  );
  let content;
  if (eventContent) {
    content = isFunction(eventContent) ? eventContent({
      event: toEventWithLocalDates(chunk.event),
      timeText,
      view: toViewWithLocalDates(_view)
    }) : eventContent;
  }
  if (content === void 0) {
    let domNodes;
    switch (chunk.event.display) {
      case "background":
        domNodes = [];
        break;
      case "pointer":
        domNodes = chunk.event.allDay ? [] : [createTimeElement(timeText, chunk, theme)];
        break;
      default:
        domNodes = [
          ...chunk.event.allDay ? [] : [createTimeElement(timeText, chunk, theme)],
          createElement("h4", theme.eventTitle, chunk.event.title)
        ];
    }
    content = { domNodes };
  }
  return [timeText, content];
}
function createTimeElement(timeText, chunk, theme) {
  return createElement(
    "time",
    theme.eventTime,
    timeText,
    [["datetime", toISOString(chunk.start)]]
  );
}
function createEventClasses(eventClassNames, event, _view) {
  let result = event.classNames;
  if (eventClassNames) {
    if (isFunction(eventClassNames)) {
      eventClassNames = eventClassNames({
        event: toEventWithLocalDates(event),
        view: toViewWithLocalDates(_view)
      });
    }
    result = [
      ...isArray(eventClassNames) ? eventClassNames : [eventClassNames],
      ...result
    ];
  }
  return result;
}
function toEventWithLocalDates(event) {
  return _cloneEvent(event, toLocalDate);
}
function cloneEvent(event) {
  return _cloneEvent(event, cloneDate);
}
function _cloneEvent(event, dateFn) {
  event = assign({}, event);
  event.start = dateFn(event.start);
  event.end = dateFn(event.end);
  return event;
}
function runReposition(refs, data) {
  refs.length = data.length;
  for (let ref of refs) {
    ref?.reposition();
  }
}
function eventIntersects(event, start, end, resource = void 0) {
  return (!resource || event.resourceIds.includes(resource.id)) && event.start < end && event.end > start;
}
function helperEvent(display) {
  return previewEvent(display) || ghostEvent(display) || pointerEvent(display);
}
function bgEvent(display) {
  return display === "background";
}
function previewEvent(display) {
  return display === "preview";
}
function ghostEvent(display) {
  return display === "ghost";
}
function pointerEvent(display) {
  return display === "pointer";
}
function createEventChunk(event, start, end) {
  start = event.start > start ? event.start : start;
  end = event.end < end ? event.end : end;
  return {
    start,
    end,
    event,
    zeroDuration: datesEqual(start, end)
  };
}
function createAllDayChunks(event, days, withId = true) {
  let dates = [];
  let lastEnd;
  let gridColumn;
  let gridRow;
  let resource;
  for (let { gridColumn: column, gridRow: row, resource: dayResource, dayStart, dayEnd, disabled } of days) {
    if (!disabled && eventIntersects(event, dayStart, dayEnd, dayResource)) {
      dates.push(dayStart);
      lastEnd = dayEnd;
      if (!gridColumn) {
        gridColumn = column;
        gridRow = row;
        resource = dayResource;
      }
    }
  }
  if (dates.length) {
    let chunk = createEventChunk(event, dates[0], lastEnd);
    assign(chunk, { gridColumn, gridRow, resource, dates });
    if (withId) {
      assignChunkId(chunk);
    }
    return [chunk];
  }
  return [];
}
function prepareAllDayChunks(chunks) {
  let prevChunks = {};
  let longChunks = {};
  for (let chunk of chunks) {
    let { gridColumn, gridRow } = chunk;
    for (let i = 1; i < chunk.dates.length; ++i) {
      let key2 = `${gridRow}_${gridColumn + i}`;
      if (longChunks[key2]) {
        longChunks[key2].chunks.push(chunk);
      } else {
        longChunks[key2] = {
          sorted: false,
          chunks: [chunk]
        };
      }
    }
    let key = `${gridRow}_${gridColumn}`;
    chunk.long = longChunks[key];
    chunk.prev = prevChunks[key];
    prevChunks[key] = chunk;
  }
}
function repositionEvent$1(chunk, height2, top = 1) {
  if (chunk.prev) {
    top = chunk.prev.bottom + 1;
  }
  let bottom = top + height2;
  if (chunk.long) {
    let longChunks = chunk.long;
    if (!longChunks.sorted) {
      longChunks.chunks.sort((a, b) => a.top - b.top);
      longChunks.sorted = true;
    }
    for (let longChunk of longChunks.chunks) {
      if (top < longChunk.bottom && bottom > longChunk.top) {
        let offset = longChunk.bottom - top + 1;
        top += offset;
        bottom += offset;
      }
    }
  }
  assign(chunk, { top, bottom });
  return top;
}
const ids = /* @__PURE__ */ new WeakMap();
let idCounter = 1;
function assignChunkId(chunk) {
  let { event, gridColumn, gridRow } = chunk;
  let id = ids.get(event);
  if (!id) {
    id = idCounter++;
    ids.set(event, id);
  }
  chunk.id = `${id}-${gridColumn}-${gridRow}`;
}
function intl(mainState, option) {
  return () => {
    let { options: { locale } } = mainState;
    let format = mainState.options[option];
    let intl2;
    untrack(() => {
      intl2 = isFunction(format) ? { format } : new Intl.DateTimeFormat(locale, format);
    });
    return {
      format: (date) => intl2.format(toLocalDate(date))
    };
  };
}
function intlRange(mainState, option) {
  return () => {
    let { options: { locale } } = mainState;
    let format = mainState.options[option];
    let formatRange;
    untrack(() => {
      if (isFunction(format)) {
        formatRange = format;
      } else {
        let intl2 = new Intl.DateTimeFormat(locale, format);
        formatRange = (start, end) => {
          if (start <= end) {
            return intl2.formatRange(start, end);
          } else {
            let parts = intl2.formatRangeToParts(end, start);
            let result = "";
            let sources = ["startRange", "endRange"];
            let processed = [false, false];
            for (let part of parts) {
              let i = sources.indexOf(part.source);
              if (i >= 0) {
                if (!processed[i]) {
                  result += _getParts(sources[1 - i], parts);
                  processed[i] = true;
                }
              } else {
                result += part.value;
              }
            }
            return result;
          }
        };
      }
    });
    return {
      formatRange: (start, end) => formatRange(toLocalDate(start), toLocalDate(end))
    };
  };
}
function _getParts(source, parts) {
  let result = "";
  for (let part of parts) {
    if (part.source == source) {
      result += part.value;
    }
  }
  return result;
}
function btnTextDay(text) {
  return btnText(text, "day");
}
function btnTextWeek(text) {
  return btnText(text, "week");
}
function btnTextMonth(text) {
  return btnText(text, "month");
}
function btnTextYear(text) {
  return btnText(text, "year");
}
function btnText(text, period) {
  return {
    ...text,
    next: "Next " + period,
    prev: "Previous " + period
  };
}
function themeView(view2) {
  return (theme) => ({ ...theme, view: view2 });
}
function createDateRange(input) {
  let start, end;
  if (input) {
    ({ start, end } = input);
    if (start) {
      start = setMidnight(createDate(start));
    }
    if (end) {
      end = setMidnight(createDate(end));
    }
  }
  return { start, end };
}
function outsideRange(date, range) {
  return range.start && date < range.start || range.end && date > range.end;
}
function createResources(input) {
  let result = [];
  _createResources(input, 0, false, result);
  return result;
}
function _createResources(input, level, hidden, flat) {
  let result = [];
  for (let item of input) {
    let resource = createResource(item);
    result.push(resource);
    flat.push(resource);
    let payload = {
      level,
      children: [],
      hidden
    };
    setPayload(resource, payload);
    if (item.children) {
      payload.children = _createResources(item.children, level + 1, hidden || !resource.expanded, flat);
    }
  }
  return result;
}
function createResource(input) {
  return {
    id: String(input.id),
    title: input.title || "",
    eventBackgroundColor: eventBackgroundColor(input),
    eventTextColor: eventTextColor(input),
    expanded: input.expanded ?? true,
    extendedProps: input.extendedProps ?? {}
  };
}
function eventBackgroundColor(resource) {
  return resource?.eventBackgroundColor;
}
function eventTextColor(resource) {
  return resource?.eventTextColor;
}
function findFirstResource(event, resources) {
  return empty(event.resourceIds) ? void 0 : resources.find((resource) => event.resourceIds.includes(resource.id));
}
function createSlots(date, slotDuration, slotLabelPeriodicity2, slotTimeLimits2, intlSlotLabel) {
  let slots2 = [];
  date = cloneDate(date);
  let end = cloneDate(date);
  addDuration(date, slotTimeLimits2.min);
  addDuration(end, slotTimeLimits2.max);
  while (date < end) {
    slots2.push([
      toISOString(date),
      intlSlotLabel.format(date)
    ]);
    addDuration(date, slotDuration, slotLabelPeriodicity2);
  }
  let span = floor((date - end) / 1e3 / toSeconds(slotDuration));
  if (span && span !== slotLabelPeriodicity2) {
    slots2.at(-1)[2] = slotLabelPeriodicity2 - span;
  }
  return slots2;
}
function createSlotTimeLimits(slotMinTime, slotMaxTime, flexibleSlotTimeLimits, viewDates2, filteredEvents2) {
  let min$1 = createDuration(slotMinTime);
  let max$1 = createDuration(slotMaxTime);
  if (flexibleSlotTimeLimits) {
    let minMin = createDuration(min(toSeconds(min$1), max(0, toSeconds(max$1) - DAY_IN_SECONDS)));
    let maxMax = createDuration(max(toSeconds(max$1), toSeconds(minMin) + DAY_IN_SECONDS));
    let filter = isFunction(flexibleSlotTimeLimits?.eventFilter) ? flexibleSlotTimeLimits.eventFilter : (event) => !bgEvent(event.display);
    loop: for (let date of viewDates2) {
      let start = addDuration(cloneDate(date), min$1);
      let end = addDuration(cloneDate(date), max$1);
      let minStart = addDuration(cloneDate(date), minMin);
      let maxEnd = addDuration(cloneDate(date), maxMax);
      for (let event of filteredEvents2) {
        if (!event.allDay && filter(event) && event.start < maxEnd && event.end > minStart) {
          if (event.start < start) {
            let seconds = max((event.start - date) / 1e3, toSeconds(minMin));
            if (seconds < toSeconds(min$1)) {
              min$1.seconds = seconds;
            }
          }
          if (event.end > end) {
            let seconds = min((event.end - date) / 1e3, toSeconds(maxMax));
            if (seconds > toSeconds(max$1)) {
              max$1.seconds = seconds;
            }
          }
          if (toSeconds(min$1) === toSeconds(minMin) && toSeconds(max$1) === toSeconds(maxMax)) {
            break loop;
          }
        }
      }
    }
  }
  return { min: min$1, max: max$1 };
}
function arrayProxy(array) {
  let counter = 0;
  let version = $.state($.proxy(counter));
  return proxy(array, () => $.get(version), () => true, () => $.set(version, ++counter, true));
}
function objectProxy(object) {
  let counter = 0;
  let versions = $.proxy({});
  return proxy(object, (prop) => versions[prop], (a, b) => a !== b, (prop) => versions[prop] = ++counter);
}
function proxy(target, setDependency, hasEffect, invokeEffect) {
  return new Proxy(target, {
    get(target2, prop, receiver) {
      if (hasOwn(target2, prop)) {
        setDependency(prop);
      }
      return Reflect.get(target2, prop, receiver);
    },
    set(target2, prop, value, receiver) {
      let has = hasEffect(target2[prop], value);
      let result = Reflect.set(target2, prop, value, receiver);
      if (has) {
        invokeEffect(prop);
      }
      return result;
    }
  });
}
function createOptions(plugins) {
  let options = {
    buttonText: {
      today: "today"
    },
    customButtons: {},
    customScrollbars: false,
    // ec option
    date: /* @__PURE__ */ new Date(),
    datesSet: void 0,
    dayHeaderFormat: {
      weekday: "short",
      month: "numeric",
      day: "numeric"
    },
    dayHeaderAriaLabelFormat: {
      dateStyle: "full"
    },
    displayEventEnd: true,
    duration: { weeks: 1 },
    events: [],
    eventAllUpdated: void 0,
    eventBackgroundColor: void 0,
    eventClassNames: void 0,
    eventClick: void 0,
    eventColor: void 0,
    eventContent: void 0,
    eventDidMount: void 0,
    eventFilter: void 0,
    // ec option
    eventMouseEnter: void 0,
    eventMouseLeave: void 0,
    eventOrder: void 0,
    eventSources: [],
    eventTextColor: void 0,
    eventTimeFormat: {
      hour: "numeric",
      minute: "2-digit"
    },
    filterEventsWithResources: false,
    firstDay: 0,
    headerToolbar: {
      start: "title",
      center: "",
      end: "today prev,next"
    },
    height: void 0,
    hiddenDays: [],
    highlightedDates: [],
    // ec option
    icons: {},
    // ec option
    lazyFetching: true,
    loading: void 0,
    locale: void 0,
    refetchResourcesOnNavigate: false,
    resources: [],
    selectable: false,
    theme: {
      active: "ec-active",
      bgEvent: "ec-bg-event",
      bgEvents: "ec-bg-events",
      body: "ec-body",
      button: "ec-button",
      buttonGroup: "ec-button-group",
      calendar: "ec",
      colHead: "ec-col-head",
      customScrollbars: "ec-custom-scrollbars",
      day: "ec-day",
      dayHead: "ec-day-head",
      disabled: "ec-disabled",
      event: "ec-event",
      eventBody: "ec-event-body",
      eventTime: "ec-event-time",
      eventTitle: "ec-event-title",
      events: "ec-events",
      grid: "ec-grid",
      header: "ec-header",
      hidden: "ec-hidden",
      highlight: "ec-highlight",
      icon: "ec-icon",
      main: "ec-main",
      noBeb: "ec-no-beb",
      // no block end border
      noIeb: "ec-no-ieb",
      // no inline end border
      today: "ec-today",
      title: "ec-title",
      toolbar: "ec-toolbar",
      view: "",
      weekdays: ["ec-sun", "ec-mon", "ec-tue", "ec-wed", "ec-thu", "ec-fri", "ec-sat"]
    },
    titleFormat: {
      year: "numeric",
      month: "short",
      day: "numeric"
    },
    validRange: void 0,
    view: void 0,
    viewDidMount: void 0,
    views: {}
  };
  for (let plugin of plugins) {
    plugin.createOptions?.(options);
  }
  return options;
}
function createParsers(plugins) {
  let parsers = {
    date: (input) => setMidnight(createDate(input)),
    duration: createDuration,
    events: createEvents,
    eventSources: createEventSources,
    hiddenDays: (input) => [...new Set(input)],
    highlightedDates: (input) => input.map((item) => setMidnight(createDate(item))),
    resources: (input) => isArray(input) ? createResources(input) : input,
    validRange: createDateRange
  };
  for (let plugin of plugins) {
    plugin.createParsers?.(parsers);
  }
  return parsers;
}
const specialOptions = ["buttonText", "customButtons", "icons", "theme"];
function optionsState(plugins, userOptions) {
  let defOptions = createOptions(plugins);
  let parsers = createParsers(plugins);
  defOptions = parseOptions(defOptions, parsers);
  userOptions = parseOptions(userOptions, parsers);
  let defViews = extractOption(defOptions, "views") ?? {};
  let userViews = extractOption(userOptions, "views") ?? {};
  let options = objectProxy({});
  assign(options, defOptions);
  if (userOptions.view) {
    options.view = userOptions.view;
  }
  let setters = {};
  let viewOptions = {};
  let viewComponents = {};
  let views = /* @__PURE__ */ new Set([...keys(defViews), ...keys(userViews)]);
  for (let view2 of views) {
    let userViewOptions = userViews[view2] ?? {};
    let defOpts = mergeOpts(defOptions, defViews[view2] ?? defViews[userViewOptions.type] ?? {});
    let opts = mergeOpts(defOpts, userOptions, userViewOptions);
    let component = extractOption(opts, "component");
    delete opts.view;
    for (let key of keys(opts)) {
      if (hasOwn(options, key)) {
        if (!setters[key]) {
          setters[key] = [];
        }
        setters[key].push(
          specialOptions.includes(key) ? (value) => opts[key] = isFunction(value) ? value(defOpts[key]) : value : (value) => opts[key] = value
        );
      } else {
        delete opts[key];
      }
    }
    viewOptions[view2] = opts;
    viewComponents[view2] = component;
  }
  assign(options, viewOptions[options.view]);
  return [
    options,
    function setOption(key, value, parsed = true) {
      if (hasOwn(options, key)) {
        if (!parsed) {
          if (key in parsers) {
            value = parsers[key](value);
          } else if (isPlainObject(value)) {
            value = { ...value };
          } else if (isArray(value)) {
            value = [...value];
          }
        }
        setters[key]?.forEach((set) => set(value));
        options[key] = value;
      }
    },
    function setViewOptions(view2) {
      assign(options, viewOptions[view2]);
      return viewComponents[view2];
    }
  ];
}
function parseOptions(opts, parsers) {
  let result = { ...opts };
  for (let key of keys(parsers)) {
    if (key in result) {
      result[key] = parsers[key](result[key]);
    }
  }
  if (opts.views) {
    result.views = {};
    for (let view2 of keys(opts.views)) {
      result.views[view2] = parseOptions(opts.views[view2], parsers);
    }
  }
  return result;
}
function extractOption(options, name) {
  let extracted = options[name];
  delete options[name];
  return extracted;
}
function mergeOpts(...args) {
  let result = {};
  for (let opts of args) {
    let override = {};
    for (let key of specialOptions) {
      if (isFunction(opts[key])) {
        override[key] = opts[key](result[key]);
      }
    }
    result = {
      ...result,
      ...opts,
      ...override
    };
  }
  return result;
}
function diff(options, prevOptions) {
  let diff2 = [];
  for (let key of keys(options)) {
    if (options[key] !== prevOptions[key]) {
      diff2.push([key, options[key]]);
    }
  }
  return diff2;
}
function switchView(mainState) {
  return () => {
    let { options: { view: view2 } } = mainState;
    untrack(() => {
      let initComponent = mainState.setViewOptions(view2);
      mainState.extensions = {};
      mainState.features = [];
      mainState.viewComponent = initComponent(mainState);
    });
  };
}
function loadEvents(mainState, loadingInvoker) {
  return () => {
    let {
      activeRange: activeRange2,
      fetchedRange: { events: fetchedRange },
      viewDates: viewDates2,
      options: { events, eventSources, lazyFetching }
    } = mainState;
    untrack(() => {
      load(
        eventSources.map((source) => isFunction(source.events) ? source.events : source),
        events,
        createEvents,
        (result) => mainState.events = arrayProxy(result),
        activeRange2,
        fetchedRange,
        viewDates2,
        true,
        lazyFetching,
        loadingInvoker
      );
    });
  };
}
function loadResources(mainState, loadingInvoker) {
  return () => {
    let {
      activeRange: activeRange2,
      fetchedRange: { resources: fetchedRange },
      viewDates: viewDates2,
      options: { lazyFetching, refetchResourcesOnNavigate, resources }
    } = mainState;
    untrack(() => {
      load(
        isArray(resources) ? [] : [resources],
        resources,
        createResources,
        (result) => mainState.resources = arrayProxy(result),
        activeRange2,
        fetchedRange,
        viewDates2,
        refetchResourcesOnNavigate,
        lazyFetching,
        loadingInvoker
      );
    });
  };
}
function load(sources, defaultResult, parseResult, applyResult, activeRange2, fetchedRange, viewDates2, refetchOnNavigate, lazyFetching, loading) {
  if (empty(viewDates2)) {
    return;
  }
  if (empty(sources)) {
    applyResult(defaultResult);
    return;
  }
  if ((refetchOnNavigate || !fetchedRange.start) && (!lazyFetching || !fetchedRange.start || fetchedRange.start > activeRange2.start || fetchedRange.end < activeRange2.end)) {
    let result = [];
    let failure = (e) => loading.stop();
    let success = (data) => {
      result = result.concat(parseResult(data));
      applyResult(result);
      loading.stop();
    };
    let startStr = toISOString(activeRange2.start);
    let endStr = toISOString(activeRange2.end);
    for (let source of sources) {
      loading.start();
      if (isFunction(source)) {
        let result2 = source(refetchOnNavigate ? {
          start: toLocalDate(activeRange2.start),
          end: toLocalDate(activeRange2.end),
          startStr,
          endStr
        } : {}, success, failure);
        if (result2 !== void 0) {
          Promise.resolve(result2).then(success, failure);
        }
      } else {
        let params = isFunction(source.extraParams) ? source.extraParams() : assign({}, source.extraParams);
        if (refetchOnNavigate) {
          params.start = startStr;
          params.end = endStr;
        }
        params = new URLSearchParams(params);
        let url = source.url, headers = {}, body;
        if (["GET", "HEAD"].includes(source.method)) {
          url += (url.includes("?") ? "&" : "?") + params;
        } else {
          headers["content-type"] = "application/x-www-form-urlencoded;charset=UTF-8";
          body = String(params);
        }
        fetch(url, {
          method: source.method,
          headers,
          body,
          signal: getAbortSignal(),
          credentials: "same-origin"
        }).then((response) => response.json()).then(success).catch(failure);
      }
    }
    assign(fetchedRange, activeRange2);
  }
}
function createLoadingInvoker(mainState) {
  let counter = 0;
  function invoke(value) {
    let { options: { loading } } = mainState;
    if (isFunction(loading)) {
      loading(value);
    }
  }
  return {
    start: () => ++counter === 1 && invoke(true),
    stop: () => --counter === 0 && invoke(false)
  };
}
function setNowAndToday(mainState) {
  return () => {
    let interval = setInterval(() => {
      let now = createDate();
      let today = setMidnight(cloneDate(now));
      mainState.now = now;
      if (!datesEqual(mainState.today, today)) {
        mainState.today = today;
      }
    }, 1e3);
    return () => clearInterval(interval);
  };
}
function runDatesSet(mainState) {
  return () => {
    let { activeRange: activeRange2, options: { datesSet } } = mainState;
    untrack(() => {
      if (isFunction(datesSet)) {
        datesSet({
          start: toLocalDate(activeRange2.start),
          end: toLocalDate(activeRange2.end),
          startStr: toISOString(activeRange2.start),
          endStr: toISOString(activeRange2.end),
          view: toViewWithLocalDates(mainState.view)
        });
      }
    });
  };
}
function runEventAllUpdated(mainState) {
  let timer;
  return () => {
    let { filteredEvents: filteredEvents2, options: { eventAllUpdated } } = mainState;
    untrack(() => {
      if (isFunction(eventAllUpdated)) {
        if (!timer) {
          timer = setTimeout(() => {
            timer = null;
            eventAllUpdated({ view: toViewWithLocalDates(mainState.view) });
          });
        }
      }
    });
  };
}
function runViewDidMount(mainState) {
  return () => {
    let { options: { view: view2, viewDidMount } } = mainState;
    untrack(() => {
      if (isFunction(viewDidMount)) {
        tick().then(() => viewDidMount({
          view: toViewWithLocalDates(mainState.view)
        }));
      }
    });
  };
}
function currentRange(mainState) {
  return () => {
    let { options: { date, duration, firstDay } } = mainState;
    let start, end;
    untrack(() => {
      start = cloneDate(date);
      if (duration.months) {
        start.setUTCDate(1);
      } else if (duration.inWeeks) {
        prevClosestDay(start, firstDay);
      }
      end = addDuration(cloneDate(start), duration);
    });
    return { start, end };
  };
}
function activeRange(mainState) {
  return () => {
    let { currentRange: currentRange2, extensions: { activeRange: activeRange2 } } = mainState;
    let start, end;
    untrack(() => {
      start = cloneDate(currentRange2.start);
      end = cloneDate(currentRange2.end);
    });
    return activeRange2 ? activeRange2(start, end) : { start, end };
  };
}
function filteredEvents(mainState) {
  return () => {
    let { events, options: { eventFilter, eventOrder, filterEventsWithResources, resources, view: view2 } } = mainState;
    let result = [...events];
    untrack(() => {
      if (isFunction(eventFilter)) {
        let events2 = events.map(toEventWithLocalDates);
        let view3 = toViewWithLocalDates(mainState.view);
        result = result.filter((event, index2) => eventFilter({
          event: toEventWithLocalDates(event),
          index: index2,
          events: events2,
          view: view3
        }));
      }
      if (filterEventsWithResources) {
        result = result.filter((event) => resources.some((resource) => event.resourceIds.includes(resource.id)));
      }
      if (isFunction(eventOrder)) {
        result.sort((a, b) => eventOrder(
          toEventWithLocalDates(a),
          toEventWithLocalDates(b)
        ));
      } else {
        result.sort((a, b) => a.start - b.start || b.allDay - a.allDay);
      }
    });
    return result;
  };
}
function viewDates(mainState) {
  return () => {
    let { options, activeRange: activeRange2 } = mainState;
    let { hiddenDays } = options;
    let dates = [];
    untrack(() => {
      let date = setMidnight(cloneDate(activeRange2.start));
      let end = setMidnight(cloneDate(activeRange2.end));
      while (date < end) {
        if (!hiddenDays.includes(date.getUTCDay())) {
          dates.push(cloneDate(date));
        }
        addDay(date);
      }
      if (!dates.length && hiddenDays.length && hiddenDays.length < 7) {
        while (hiddenDays.includes(date.getUTCDay())) {
          addDay(date);
        }
        tick().then(() => {
          mainState.setOption("date", date);
        });
      }
    });
    return dates;
  };
}
function viewTitle(mainState) {
  return () => {
    let { currentRange: currentRange2, intlTitle } = mainState;
    let title;
    untrack(() => {
      title = intlTitle.formatRange(currentRange2.start, subtractDay(cloneDate(currentRange2.end)));
    });
    return title;
  };
}
function view(mainState) {
  return () => {
    let { activeRange: activeRange2, currentRange: currentRange2, viewTitle: viewTitle2, options: { view: view2 } } = mainState;
    let viewObj;
    untrack(() => {
      viewObj = createView(view2, viewTitle2, currentRange2, activeRange2);
    });
    return viewObj;
  };
}
class State {
  #auxComponents;
  get auxComponents() {
    return $.get(this.#auxComponents);
  }
  set auxComponents(value) {
    $.set(this.#auxComponents, value, true);
  }
  #currentRange;
  get currentRange() {
    return $.get(this.#currentRange);
  }
  set currentRange(value) {
    $.set(this.#currentRange, value);
  }
  #activeRange;
  get activeRange() {
    return $.get(this.#activeRange);
  }
  set activeRange(value) {
    $.set(this.#activeRange, value);
  }
  #fetchedRange;
  get fetchedRange() {
    return $.get(this.#fetchedRange);
  }
  set fetchedRange(value) {
    $.set(this.#fetchedRange, value, true);
  }
  #events;
  get events() {
    return $.get(this.#events);
  }
  set events(value) {
    $.set(this.#events, value);
  }
  #filteredEvents;
  get filteredEvents() {
    return $.get(this.#filteredEvents);
  }
  set filteredEvents(value) {
    $.set(this.#filteredEvents, value);
  }
  #mainEl;
  get mainEl() {
    return $.get(this.#mainEl);
  }
  set mainEl(value) {
    $.set(this.#mainEl, value, true);
  }
  #now;
  get now() {
    return $.get(this.#now);
  }
  set now(value) {
    $.set(this.#now, value, true);
  }
  #resources;
  get resources() {
    return $.get(this.#resources);
  }
  set resources(value) {
    $.set(this.#resources, value);
  }
  #today;
  get today() {
    return $.get(this.#today);
  }
  set today(value) {
    $.set(this.#today, value, true);
  }
  #intlEventTime;
  get intlEventTime() {
    return $.get(this.#intlEventTime);
  }
  set intlEventTime(value) {
    $.set(this.#intlEventTime, value);
  }
  #intlDayHeader;
  get intlDayHeader() {
    return $.get(this.#intlDayHeader);
  }
  set intlDayHeader(value) {
    $.set(this.#intlDayHeader, value);
  }
  #intlDayHeaderAL;
  get intlDayHeaderAL() {
    return $.get(this.#intlDayHeaderAL);
  }
  set intlDayHeaderAL(value) {
    $.set(this.#intlDayHeaderAL, value);
  }
  #intlTitle;
  get intlTitle() {
    return $.get(this.#intlTitle);
  }
  set intlTitle(value) {
    $.set(this.#intlTitle, value);
  }
  #viewDates;
  get viewDates() {
    return $.get(this.#viewDates);
  }
  set viewDates(value) {
    $.set(this.#viewDates, value);
  }
  #viewTitle;
  get viewTitle() {
    return $.get(this.#viewTitle);
  }
  set viewTitle(value) {
    $.set(this.#viewTitle, value);
  }
  #view;
  get view() {
    return $.get(this.#view);
  }
  set view(value) {
    $.set(this.#view, value);
  }
  #viewComponent;
  get viewComponent() {
    return $.get(this.#viewComponent);
  }
  set viewComponent(value) {
    $.set(this.#viewComponent, value, true);
  }
  #extensions;
  get extensions() {
    return $.get(this.#extensions);
  }
  set extensions(value) {
    $.set(this.#extensions, value, true);
  }
  #features;
  get features() {
    return $.get(this.#features);
  }
  set features(value) {
    $.set(this.#features, value, true);
  }
  #interaction;
  get interaction() {
    return $.get(this.#interaction);
  }
  set interaction(value) {
    $.set(this.#interaction, value, true);
  }
  #iClasses;
  get iClasses() {
    return $.get(this.#iClasses);
  }
  set iClasses(value) {
    $.set(this.#iClasses, value, true);
  }
  #iClass;
  get iClass() {
    return $.get(this.#iClass);
  }
  set iClass(value) {
    $.set(this.#iClass, value, true);
  }
  options;
  setOption;
  setViewOptions;
  constructor(plugins, options) {
    [this.options, this.setOption, this.setViewOptions] = optionsState(plugins, options);
    this.#auxComponents = $.state($.proxy([]));
    this.#currentRange = $.derived(currentRange(this));
    this.#activeRange = $.derived(activeRange(this));
    this.#fetchedRange = $.state($.proxy({ events: {}, resources: {} }));
    this.#events = $.state(arrayProxy(this.options.events));
    this.#filteredEvents = $.derived(filteredEvents(this));
    this.#mainEl = $.state();
    this.#now = $.state($.proxy(createDate()));
    this.#resources = $.state(arrayProxy(this.options.resources));
    this.#today = $.state($.proxy(setMidnight(createDate())));
    this.#intlEventTime = $.derived(intlRange(this, "eventTimeFormat"));
    this.#intlDayHeader = $.derived(intl(this, "dayHeaderFormat"));
    this.#intlDayHeaderAL = $.derived(intl(this, "dayHeaderAriaLabelFormat"));
    this.#intlTitle = $.derived(intlRange(this, "titleFormat"));
    this.#viewDates = $.derived(viewDates(this));
    this.#viewTitle = $.derived(viewTitle(this));
    this.#view = $.derived(view(this));
    this.#viewComponent = $.state();
    this.#extensions = $.state($.proxy({}));
    this.#features = $.state($.proxy([]));
    this.#interaction = $.state($.proxy({}));
    this.iEvents = new SvelteMap();
    this.#iClasses = $.state($.proxy(identity));
    this.#iClass = $.state();
    for (let plugin of plugins) {
      plugin.initState?.(this);
    }
    this.#initEffects();
  }
  #initEffects() {
    let loading = createLoadingInvoker(this);
    $.user_pre_effect(switchView(this));
    $.user_pre_effect(setNowAndToday(this));
    $.user_effect(loadEvents(this, loading));
    $.user_effect(loadResources(this, loading));
    $.user_effect(runDatesSet(this));
    $.user_effect(runEventAllUpdated(this));
    $.user_effect(runViewDidMount(this));
  }
}
var root_2$5 = $.from_html(`<h2></h2>`);
var root_3$2 = $.from_html(`<button><i></i></button>`);
var root_4$1 = $.from_html(`<button><i></i></button>`);
var root_5 = $.from_html(`<button> </button>`);
var root_6$1 = $.from_html(`<button></button>`);
var root_7$1 = $.from_html(`<button> </button>`);
function Buttons($$anchor, $$props) {
  $.push($$props, true);
  let mainState = getContext("state");
  let currentRange2 = $.derived(() => mainState.currentRange), today = $.derived(() => mainState.today), viewTitle2 = $.derived(() => mainState.viewTitle), viewDates2 = $.derived(() => mainState.viewDates), buttonText = $.derived(() => mainState.options.buttonText), customButtons = $.derived(() => mainState.options.customButtons), date = $.derived(() => mainState.options.date), duration = $.derived(() => mainState.options.duration), hiddenDays = $.derived(() => mainState.options.hiddenDays), theme = $.derived(() => mainState.options.theme), validRange = $.derived(() => mainState.options.validRange), view2 = $.derived(() => mainState.options.view);
  let prevDisabled = $.state(false);
  let nextDisabled = $.state(false);
  let todayDisabled = $.state(false);
  let running = false;
  $.user_pre_effect(() => {
    $.get(viewDates2);
    $.get(validRange);
    $$props.buttons;
    untrack(() => {
      if (!running) {
        running = true;
        if ($$props.buttons.includes("prev")) {
          $.set(prevDisabled, false);
          if ($.get(validRange).start) {
            $.set(prevDisabled, test(prev), true);
          }
        }
        if ($$props.buttons.includes("next")) {
          $.set(nextDisabled, false);
          if ($.get(validRange).end) {
            $.set(nextDisabled, test(next), true);
          }
        }
        if ($$props.buttons.includes("today")) {
          $.set(todayDisabled, $.get(today) >= $.get(currentRange2).start && $.get(today) < $.get(currentRange2).end, true);
          if (!$.get(todayDisabled) && ($.get(validRange).start || $.get(validRange).end)) {
            $.set(todayDisabled, test(setToday), true);
          }
        }
        tick().then(() => running = false);
      }
    });
  });
  function test(fn) {
    let currentDate = $.get(date);
    fn();
    let result = $.get(viewDates2).every((date2) => outsideRange(date2, $.get(validRange)));
    mainState.setOption("date", currentDate);
    return result;
  }
  function prev() {
    mainState.setOption("date", prevDate(cloneDate($.get(date)), $.get(duration), $.get(hiddenDays)));
  }
  function next() {
    mainState.setOption("date", nextDate(cloneDate($.get(date)), $.get(duration), $.get(hiddenDays)));
  }
  function setToday() {
    mainState.setOption("date", cloneDate($.get(today)));
  }
  var fragment = $.comment();
  var node = $.first_child(fragment);
  $.each(node, 17, () => $$props.buttons, $.index, ($$anchor2, button) => {
    var fragment_1 = $.comment();
    var node_1 = $.first_child(fragment_1);
    {
      var consequent = ($$anchor3) => {
        var h2 = root_2$5();
        $.attach(h2, () => contentFrom($.get(viewTitle2)));
        $.template_effect(() => $.set_class(h2, 1, $.get(theme).title));
        $.append($$anchor3, h2);
      };
      var consequent_1 = ($$anchor3) => {
        var button_1 = root_3$2();
        var i = $.child(button_1);
        $.reset(button_1);
        $.template_effect(() => {
          $.set_class(button_1, 1, `${$.get(theme).button ?? ""} ec-${$.get(button) ?? ""}`);
          $.set_attribute(button_1, "aria-label", $.get(buttonText).prev);
          $.set_attribute(button_1, "title", $.get(buttonText).prev);
          button_1.disabled = $.get(prevDisabled);
          $.set_class(i, 1, `${$.get(theme).icon ?? ""} ec-${$.get(button) ?? ""}`);
        });
        $.delegated("click", button_1, prev);
        $.append($$anchor3, button_1);
      };
      var consequent_2 = ($$anchor3) => {
        var button_2 = root_4$1();
        var i_1 = $.child(button_2);
        $.reset(button_2);
        $.template_effect(() => {
          $.set_class(button_2, 1, `${$.get(theme).button ?? ""} ec-${$.get(button) ?? ""}`);
          $.set_attribute(button_2, "aria-label", $.get(buttonText).next);
          $.set_attribute(button_2, "title", $.get(buttonText).next);
          button_2.disabled = $.get(nextDisabled);
          $.set_class(i_1, 1, `${$.get(theme).icon ?? ""} ec-${$.get(button) ?? ""}`);
        });
        $.delegated("click", button_2, next);
        $.append($$anchor3, button_2);
      };
      var consequent_3 = ($$anchor3) => {
        var button_3 = root_5();
        var text = $.child(button_3, true);
        $.reset(button_3);
        $.template_effect(() => {
          $.set_class(button_3, 1, `${$.get(theme).button ?? ""} ec-${$.get(button) ?? ""}`);
          button_3.disabled = $.get(todayDisabled);
          $.set_text(text, $.get(buttonText)[$.get(button)]);
        });
        $.delegated("click", button_3, setToday);
        $.append($$anchor3, button_3);
      };
      var consequent_4 = ($$anchor3) => {
        var button_4 = root_6$1();
        $.attach(button_4, () => contentFrom($.get(customButtons)[$.get(button)].text));
        $.template_effect(() => $.set_class(button_4, 1, $.clsx([
          $.get(theme).button,
          `ec-${$.get(button)}`,
          $.get(customButtons)[$.get(button)].active && $.get(theme).active
        ])));
        $.delegated("click", button_4, function(...$$args) {
          $.get(customButtons)[$.get(button)].click?.apply(this, $$args);
        });
        $.append($$anchor3, button_4);
      };
      var consequent_5 = ($$anchor3) => {
        var button_5 = root_7$1();
        var text_1 = $.child(button_5, true);
        $.reset(button_5);
        $.template_effect(() => {
          $.set_class(button_5, 1, $.clsx([
            $.get(theme).button,
            `ec-${$.get(button)}`,
            $.get(view2) === $.get(button) && $.get(theme).active
          ]));
          $.set_text(text_1, $.get(buttonText)[$.get(button)]);
        });
        $.delegated("click", button_5, () => mainState.setOption("view", $.get(button)));
        $.append($$anchor3, button_5);
      };
      $.if(node_1, ($$render) => {
        if ($.get(button) === "title") $$render(consequent);
        else if ($.get(button) === "prev") $$render(consequent_1, 1);
        else if ($.get(button) === "next") $$render(consequent_2, 2);
        else if ($.get(button) === "today") $$render(consequent_3, 3);
        else if ($.get(customButtons)[$.get(button)]) $$render(consequent_4, 4);
        else if ($.get(button) !== "") $$render(consequent_5, 5);
      });
    }
    $.append($$anchor2, fragment_1);
  });
  $.append($$anchor, fragment);
  $.pop();
}
$.delegate(["click"]);
var root_3$1 = $.from_html(`<div><!></div>`);
var root_1$c = $.from_html(`<div></div>`);
var root$b = $.from_html(`<nav></nav>`);
function Toolbar($$anchor, $$props) {
  $.push($$props, true);
  let $$d = $.derived(() => getContext("state")), headerToolbar = $.derived(() => $.get($$d).options.headerToolbar), theme = $.derived(() => $.get($$d).options.theme);
  let sections = $.derived(() => {
    let sections2 = {};
    for (let key of ["start", "center", "end"]) {
      sections2[key] = $.get(headerToolbar)[key]?.split(" ").map((group) => group.split(",")) ?? [];
    }
    return sections2;
  });
  var nav = root$b();
  $.each(nav, 21, () => keys($.get(sections)), $.index, ($$anchor2, key) => {
    var div = root_1$c();
    $.each(div, 21, () => $.get(sections)[$.get(key)], $.index, ($$anchor3, buttons) => {
      var fragment = $.comment();
      var node = $.first_child(fragment);
      {
        var consequent = ($$anchor4) => {
          var div_1 = root_3$1();
          var node_1 = $.child(div_1);
          Buttons(node_1, {
            get buttons() {
              return $.get(buttons);
            }
          });
          $.reset(div_1);
          $.template_effect(() => $.set_class(div_1, 1, $.get(theme).buttonGroup));
          $.append($$anchor4, div_1);
        };
        var alternate = ($$anchor4) => {
          Buttons($$anchor4, {
            get buttons() {
              return $.get(buttons);
            }
          });
        };
        $.if(node, ($$render) => {
          if ($.get(buttons).length > 1) $$render(consequent);
          else $$render(alternate, false);
        });
      }
      $.append($$anchor3, fragment);
    });
    $.reset(div);
    $.template_effect(() => $.set_class(div, 1, `ec-${$.get(key) ?? ""}`));
    $.append($$anchor2, div);
  });
  $.reset(nav);
  $.template_effect(() => $.set_class(nav, 1, $.get(theme).toolbar));
  $.append($$anchor, nav);
  $.pop();
}
var root$a = $.from_html(`<div><!> <!> <!></div>`);
function Calendar($$anchor, $$props) {
  $.push($$props, true);
  let plugins = $.prop($$props, "plugins", 19, () => []), options = $.prop($$props, "options", 19, () => ({}));
  let mainState = new State(plugins(), options());
  setContext("state", mainState);
  let auxComponents = $.derived(() => mainState.auxComponents), features = $.derived(() => mainState.features), events = $.derived(() => mainState.events), interaction = $.derived(() => mainState.interaction), iClass = $.derived(() => mainState.iClass), view2 = $.derived(() => mainState.view), View2 = $.derived(() => mainState.viewComponent), date = $.derived(() => mainState.options.date), duration = $.derived(() => mainState.options.duration), height2 = $.derived(() => mainState.options.height), hiddenDays = $.derived(() => mainState.options.hiddenDays), customScrollbars = $.derived(() => mainState.options.customScrollbars), theme = $.derived(() => mainState.options.theme);
  let prevOptions = { ...options() };
  $.user_pre_effect(() => {
    for (let [name, value] of diff(options(), prevOptions)) {
      untrack(() => {
        setOption(name, value);
      });
    }
    assign(prevOptions, options());
  });
  function setOption(name, value) {
    mainState.setOption(name, value, false);
    return this;
  }
  function getOption(name) {
    let value = mainState.options[name];
    return isDate(value) ? toLocalDate(value) : value;
  }
  function refetchResources() {
    mainState.fetchedRange.resources = {};
    return this;
  }
  function refetchEvents() {
    mainState.fetchedRange.events = {};
    return this;
  }
  function getEvents() {
    return $.get(events).map(toEventWithLocalDates);
  }
  function getEventById(id) {
    id = String(id);
    for (let event of $.get(events)) {
      if (event.id === id) {
        return toEventWithLocalDates(event);
      }
    }
    return null;
  }
  function addEvent(event) {
    event = createEvents([event])[0];
    $.get(events).push(event);
    return toEventWithLocalDates(event);
  }
  function updateEvent(event) {
    let id = String(event.id);
    let idx = $.get(events).findIndex((event2) => event2.id === id);
    if (idx >= 0) {
      event = createEvents([event])[0];
      $.get(events)[idx] = event;
      return toEventWithLocalDates(event);
    }
    return null;
  }
  function removeEventById(id) {
    id = String(id);
    let idx = $.get(events).findIndex((event) => event.id === id);
    if (idx >= 0) {
      $.get(events).splice(idx, 1);
    }
    return this;
  }
  function getView() {
    return toViewWithLocalDates($.get(view2));
  }
  function unselect() {
    $.get(interaction).action?.unselect();
    return this;
  }
  function dateFromPoint(x, y) {
    let dayEl = getElementWithPayload(x, y);
    if (dayEl) {
      let info = getPayload(dayEl)(x, y);
      info.date = toLocalDate(info.date);
      return info;
    }
    return null;
  }
  function next() {
    mainState.setOption("date", nextDate(cloneDate($.get(date)), $.get(duration), $.get(hiddenDays)));
    return this;
  }
  function prev() {
    mainState.setOption("date", prevDate(cloneDate($.get(date)), $.get(duration), $.get(hiddenDays)));
    return this;
  }
  var $$exports = {
    setOption,
    getOption,
    refetchResources,
    refetchEvents,
    getEvents,
    getEventById,
    addEvent,
    updateEvent,
    removeEventById,
    getView,
    unselect,
    dateFromPoint,
    next,
    prev
  };
  var div = root$a();
  let styles;
  var node = $.child(div);
  Toolbar(node, {});
  var node_1 = $.sibling(node, 2);
  $.component(node_1, () => $.get(View2), ($$anchor2, View_12) => {
    View_12($$anchor2, {});
  });
  var node_2 = $.sibling(node_1, 2);
  $.each(node_2, 17, () => $.get(auxComponents), $.index, ($$anchor2, AuxComponent) => {
    var fragment = $.comment();
    var node_3 = $.first_child(fragment);
    $.component(node_3, () => $.get(AuxComponent), ($$anchor3, AuxComponent_1) => {
      AuxComponent_1($$anchor3, {});
    });
    $.append($$anchor2, fragment);
  });
  $.reset(div);
  $.template_effect(
    ($0) => {
      $.set_class(div, 1, $.clsx([
        $.get(theme).calendar,
        $.get(theme).view,
        $.get(iClass) && $.get(theme)[$.get(iClass)],
        $.get(customScrollbars) && $.get(theme).customScrollbars
      ]));
      $.set_attribute(div, "role", $0);
      styles = $.set_style(div, "", styles, { height: $.get(height2) });
    },
    [() => $.get(features).includes("list") ? "list" : "table"]
  );
  $.append($$anchor, div);
  return $.pop($$exports);
}
function colsCount(mainState) {
  return () => {
    let { viewDates: viewDates2, options: { duration, hiddenDays } } = mainState;
    let count;
    untrack(() => count = duration.months || duration.inWeeks ? 7 - hiddenDays.length : viewDates2.length);
    return count;
  };
}
function grid$3(mainState, viewState) {
  return () => {
    let { options: { highlightedDates, validRange }, viewDates: viewDates2 } = mainState;
    let { colsCount: colsCount2 } = viewState;
    let grid2 = [];
    untrack(() => {
      let days = [];
      let gridColumn = 1;
      let gridRow = 1;
      for (let date of viewDates2) {
        days.push({
          gridColumn,
          gridRow,
          resource: void 0,
          dayStart: date,
          dayEnd: addDay(cloneDate(date)),
          disabled: outsideRange(date, validRange),
          highlight: highlightedDates.some((d) => datesEqual(d, date))
        });
        if (gridColumn === colsCount2) {
          grid2.push(days);
          days = [];
          gridColumn = 0;
          ++gridRow;
        }
        ++gridColumn;
      }
    });
    return grid2;
  };
}
function eventChunks$2(mainState, viewState) {
  return () => {
    let { filteredEvents: filteredEvents2 } = mainState;
    let { grid: grid2 } = viewState;
    let chunks = [];
    let bgChunks = [];
    untrack(() => {
      for (let event of filteredEvents2) {
        for (let days of grid2) {
          if (bgEvent(event.display)) {
            if (event.allDay) {
              bgChunks = bgChunks.concat(createAllDayChunks(event, days));
            }
          } else {
            chunks = chunks.concat(createAllDayChunks(event, days));
          }
        }
      }
      prepareAllDayChunks(chunks);
    });
    return { chunks, bgChunks };
  };
}
function iEventChunks$2(mainState, viewState) {
  return () => {
    let { iEvents } = mainState;
    let { grid: grid2 } = viewState;
    let iChunks = [];
    for (let [, event] of iEvents) {
      if (!event) {
        continue;
      }
      untrack(() => {
        for (let days of grid2) {
          iChunks = iChunks.concat(createAllDayChunks(event, days, false));
        }
      });
    }
    return iChunks;
  };
}
let ViewState$4 = class ViewState {
  #colsCount;
  get colsCount() {
    return $.get(this.#colsCount);
  }
  set colsCount(value) {
    $.set(this.#colsCount, value);
  }
  #grid;
  get grid() {
    return $.get(this.#grid);
  }
  set grid(value) {
    $.set(this.#grid, value);
  }
  #gridEl;
  get gridEl() {
    return $.get(this.#gridEl);
  }
  set gridEl(value) {
    $.set(this.#gridEl, value, true);
  }
  #chunks;
  get chunks() {
    return $.get(this.#chunks);
  }
  set chunks(value) {
    $.set(this.#chunks, value);
  }
  #bgChunks;
  get bgChunks() {
    return $.get(this.#bgChunks);
  }
  set bgChunks(value) {
    $.set(this.#bgChunks, value);
  }
  #iChunks;
  get iChunks() {
    return $.get(this.#iChunks);
  }
  set iChunks(value) {
    $.set(this.#iChunks, value);
  }
  #intlDayCell;
  get intlDayCell() {
    return $.get(this.#intlDayCell);
  }
  set intlDayCell(value) {
    $.set(this.#intlDayCell, value);
  }
  #intlDayPopover;
  get intlDayPopover() {
    return $.get(this.#intlDayPopover);
  }
  set intlDayPopover(value) {
    $.set(this.#intlDayPopover, value);
  }
  #popupDay;
  get popupDay() {
    return $.get(this.#popupDay);
  }
  set popupDay(value) {
    $.set(this.#popupDay, value, true);
  }
  constructor(mainState) {
    this.#colsCount = $.derived(colsCount(mainState));
    this.#grid = $.derived(grid$3(mainState, this));
    this.#gridEl = $.state();
    let $$d = $.derived(eventChunks$2(mainState, this)), chunks = $.derived(() => $.get($$d).chunks), bgChunks = $.derived(() => $.get($$d).bgChunks);
    this.#chunks = $.derived(() => $.get(chunks));
    this.#bgChunks = $.derived(() => $.get(bgChunks));
    this.#iChunks = $.derived(iEventChunks$2(mainState, this));
    this.hiddenChunks = new SvelteMap();
    this.#intlDayCell = $.derived(intl(mainState, "dayCellFormat"));
    this.#intlDayPopover = $.derived(intl(mainState, "dayPopoverFormat"));
    this.#popupDay = $.state(null);
  }
};
var root$9 = $.from_html(`<div><!></div>`);
function BaseDay($$anchor, $$props) {
  $.push($$props, true);
  let el = $.prop($$props, "el", 15), allDay = $.prop($$props, "allDay", 3, false), resource = $.prop($$props, "resource", 3, void 0), dateFromPoint = $.prop($$props, "dateFromPoint", 3, () => $$props.date), classes = $.prop($$props, "classes", 3, identity), disabled = $.prop($$props, "disabled", 3, false), highlight = $.prop($$props, "highlight", 3, false), role = $.prop($$props, "role", 3, "cell"), noIeb = $.prop($$props, "noIeb", 3, false), noBeb = $.prop($$props, "noBeb", 3, false);
  let $$d = $.derived(() => getContext("state")), today = $.derived(() => $.get($$d).today), action = $.derived(() => $.get($$d).interaction.action), theme = $.derived(() => $.get($$d).options.theme);
  let $$d_1 = $.derived(() => getContext("view-state")), snap2 = $.derived(() => $.get($$d_1).snap);
  let isToday = $.derived(() => datesEqual($$props.date, $.get(today)));
  let classNames = $.derived(() => classes()([
    $.get(theme).day,
    $.get(theme).weekdays?.[$$props.date.getUTCDay()],
    $.get(isToday) && $.get(theme).today,
    highlight() && $.get(theme).highlight,
    disabled() && $.get(theme).disabled,
    noIeb() && $.get(theme).noIeb,
    noBeb() && $.get(theme).noBeb
  ]));
  onMount(() => {
    setPayload(el(), (x, y) => {
      return {
        allDay: allDay(),
        date: dateFromPoint()(x, y),
        resource: resource(),
        dayEl: el(),
        disabled: disabled()
      };
    });
  });
  let onpointerdown = $.derived(() => !disabled() && $.get(action) ? (jsEvent) => $.get(action).select(jsEvent, $.get(snap2)) : void 0);
  var div = root$9();
  var node = $.child(div);
  $.snippet(node, () => $$props.children ?? $.noop);
  $.reset(div);
  $.bind_this(div, ($$value) => el($$value), () => el());
  $.template_effect(() => {
    $.set_class(div, 1, $.clsx($.get(classNames)));
    $.set_attribute(div, "role", role());
  });
  $.delegated("pointerdown", div, function(...$$args) {
    $.get(onpointerdown)?.apply(this, $$args);
  });
  $.append($$anchor, div);
  $.pop();
}
$.delegate(["pointerdown"]);
var root_1$b = $.from_html(`<div></div>`);
var root$8 = $.from_html(`<article><!></article>`);
function BaseEvent($$anchor, $$props) {
  $.push($$props, true);
  let el = $.prop($$props, "el", 15), classes = $.prop($$props, "classes", 3, identity), styles = $.prop($$props, "styles", 3, identity);
  let $$d = $.derived(() => getContext("state")), intlEventTime = $.derived(() => $.get($$d).intlEventTime), resources = $.derived(() => $.get($$d).resources), view2 = $.derived(() => $.get($$d).view), displayEventEnd = $.derived(() => $.get($$d).options.displayEventEnd), eventBackgroundColor$1 = $.derived(() => $.get($$d).options.eventBackgroundColor), eventColor = $.derived(() => $.get($$d).options.eventColor), eventContent = $.derived(() => $.get($$d).options.eventContent), eventClick = $.derived(() => $.get($$d).options.eventClick), eventDidMount = $.derived(() => $.get($$d).options.eventDidMount), eventClassNames = $.derived(() => $.get($$d).options.eventClassNames), eventMouseEnter = $.derived(() => $.get($$d).options.eventMouseEnter), eventMouseLeave = $.derived(() => $.get($$d).options.eventMouseLeave), eventTextColor$1 = $.derived(() => $.get($$d).options.eventTextColor), theme = $.derived(() => $.get($$d).options.theme);
  let event = $.derived(() => $$props.chunk.event);
  let display = $.derived(() => $$props.chunk.event.display);
  let bgColor = $.derived(() => $.get(event).backgroundColor ?? eventBackgroundColor($$props.chunk.resource ?? findFirstResource($.get(event), $.get(resources))) ?? $.get(eventBackgroundColor$1) ?? $.get(eventColor));
  let txtColor = $.derived(() => $.get(event).textColor ?? eventTextColor($$props.chunk.resource ?? findFirstResource($.get(event), $.get(resources))) ?? $.get(eventTextColor$1));
  let style = $.derived(() => entries(styles()({ "background-color": $.get(bgColor), "color": $.get(txtColor) })).map((entry) => `${entry[0]}:${entry[1]}`).concat($.get(event).styles).join(";"));
  let classNames = $.derived(() => classes()([
    bgEvent($.get(display)) ? $.get(theme).bgEvent : $.get(theme).event,
    ...createEventClasses($.get(eventClassNames), $.get(event), $.get(view2))
  ]));
  let $$d_1 = $.derived(() => createEventContent($$props.chunk, $.get(displayEventEnd), $.get(eventContent), $.get(theme), $.get(intlEventTime), $.get(view2))), $$array = $.derived(() => $.to_array($.get($$d_1), 2)), timeText = $.derived(() => $.get($$array)[0]), content = $.derived(() => $.get($$array)[1]);
  onMount(() => {
    if (isFunction($.get(eventDidMount))) {
      $.get(eventDidMount)({
        event: toEventWithLocalDates($.get(event)),
        timeText: $.get(timeText),
        el: el(),
        view: toViewWithLocalDates($.get(view2))
      });
    }
  });
  function createHandler(fn, display2) {
    return isFunction(fn) && !helperEvent(display2) ? (jsEvent) => fn({
      event: toEventWithLocalDates($.get(event)),
      el: el(),
      jsEvent,
      view: toViewWithLocalDates($.get(view2))
    }) : void 0;
  }
  let onclick = $.derived(() => !bgEvent($.get(display)) && createHandler($.get(eventClick), $.get(display)) || void 0);
  let onkeydown = $.derived(() => $.get(onclick) && keyEnter($.get(onclick)));
  let onmouseenter = $.derived(() => createHandler($.get(eventMouseEnter), $.get(display)));
  let onmouseleave = $.derived(() => createHandler($.get(eventMouseLeave), $.get(display)));
  var article = root$8();
  {
    const defaultBody = ($$anchor2) => {
      var div = root_1$b();
      $.attach(div, () => contentFrom($.get(content)));
      $.template_effect(() => $.set_class(div, 1, $.clsx($.get(theme).eventBody)));
      $.append($$anchor2, div);
    };
    var node = $.child(article);
    {
      var consequent = ($$anchor2) => {
        var fragment = $.comment();
        var node_1 = $.first_child(fragment);
        $.snippet(node_1, () => $$props.body, () => defaultBody, () => $.get(bgColor), () => $.get(txtColor));
        $.append($$anchor2, fragment);
      };
      var alternate = ($$anchor2) => {
        defaultBody($$anchor2);
      };
      $.if(node, ($$render) => {
        if ($$props.body) $$render(consequent);
        else $$render(alternate, false);
      });
    }
    $.reset(article);
    $.bind_this(article, ($$value) => el($$value), () => el());
  }
  $.template_effect(() => {
    $.set_class(article, 1, $.clsx($.get(classNames)));
    $.set_style(article, $.get(style));
    $.set_attribute(article, "role", $.get(onclick) ? "button" : void 0);
    $.set_attribute(article, "tabindex", $.get(onclick) ? 0 : void 0);
  });
  $.delegated("click", article, function(...$$args) {
    $.get(onclick)?.apply(this, $$args);
  });
  $.delegated("keydown", article, function(...$$args) {
    $.get(onkeydown)?.apply(this, $$args);
  });
  $.event("mouseenter", article, function(...$$args) {
    $.get(onmouseenter)?.apply(this, $$args);
  });
  $.event("mouseleave", article, function(...$$args) {
    $.get(onmouseleave)?.apply(this, $$args);
  });
  $.delegated("pointerdown", article, function(...$$args) {
    $$props.onpointerdown?.apply(this, $$args);
  });
  $.append($$anchor, article);
  $.pop();
}
$.delegate(["click", "keydown", "pointerdown"]);
var root$7 = $.from_html(`<div><!></div>`);
function ColHead($$anchor, $$props) {
  $.push($$props, true);
  let weekday = $.prop($$props, "weekday", 3, true), colSpan = $.prop($$props, "colSpan", 3, 1), ariaHidden = $.prop($$props, "ariaHidden", 3, false), disabled = $.prop($$props, "disabled", 3, false), highlight = $.prop($$props, "highlight", 3, false);
  let $$d = $.derived(() => getContext("state")), today = $.derived(() => $.get($$d).today), theme = $.derived(() => $.get($$d).options.theme);
  var div = root$7();
  var node = $.child(div);
  $.snippet(node, () => $$props.children);
  $.reset(div);
  $.template_effect(
    ($0) => {
      $.set_class(div, 1, $0);
      $.set_attribute(div, "role", ariaHidden() ? null : "columnheader");
      $.set_attribute(div, "aria-colspan", ariaHidden() || colSpan() <= 1 ? null : colSpan());
      $.set_attribute(div, "aria-colindex", ariaHidden() ? null : $$props.colIndex);
      $.set_attribute(div, "aria-hidden", ariaHidden() ? "true" : null);
    },
    [
      () => $.clsx([
        $$props.className ?? $.get(theme).colHead,
        weekday() && $.get(theme).weekdays?.[$$props.date.getUTCDay()],
        weekday() && datesEqual($$props.date, $.get(today)) && $.get(theme).today,
        highlight() && $.get(theme).highlight,
        disabled() && $.get(theme).disabled
      ])
    ]
  );
  $.append($$anchor, div);
  $.pop();
}
var root$6 = $.from_html(`<time></time>`);
function DayHeader($$anchor, $$props) {
  $.push($$props, true);
  let alPrefix = $.prop($$props, "alPrefix", 3, "");
  let $$d = $.derived(() => getContext("state")), intlDayHeader = $.derived(() => $.get($$d).intlDayHeader), intlDayHeaderAL = $.derived(() => $.get($$d).intlDayHeaderAL);
  var time = root$6();
  $.attach(time, () => contentFrom($.get(intlDayHeader).format($$props.date)));
  $.template_effect(
    ($0, $1) => {
      $.set_attribute(time, "datetime", $0);
      $.set_attribute(time, "aria-label", `${alPrefix() ?? ""}${$1 ?? ""}`);
    },
    [
      () => toISOString($$props.date, 10),
      () => $.get(intlDayHeaderAL).format($$props.date)
    ]
  );
  $.append($$anchor, time);
  $.pop();
}
function InteractableEvent($$anchor, $$props) {
  $.push($$props, true);
  let el = $.prop($$props, "el", 15);
  let $$d = $.derived(() => getContext("state")), iClasses = $.derived(() => $.get($$d).iClasses), action = $.derived(() => $.get($$d).interaction.action), Resizer2 = $.derived(() => $.get($$d).interaction.resizer);
  let $$d_1 = $.derived(() => getContext("view-state")), snap2 = $.derived(() => $.get($$d_1).snap);
  let event = $.derived(() => $$props.chunk.event);
  let display = $.derived(() => $$props.chunk.event.display);
  let classes = $.derived(() => (classNames) => $.get(iClasses)(classNames, $.get(event)));
  function createDragHandler(event2) {
    return $.get(action)?.draggable(event2) ? (jsEvent) => $.get(action).drag(event2, jsEvent, $$props.forceDate, $$props.forceMargin, $.get(snap2)) : $.get(action)?.noAction;
  }
  let onpointerdown = $.derived(() => !bgEvent($.get(display)) && !helperEvent($.get(display)) ? createDragHandler($.get(event)) : void 0);
  {
    const body = ($$anchor2, defaultBody = $.noop) => {
      var fragment_1 = $.comment();
      var node = $.first_child(fragment_1);
      {
        var consequent = ($$anchor3) => {
          var fragment_2 = $.comment();
          var node_1 = $.first_child(fragment_2);
          $.component(node_1, () => $.get(Resizer2), ($$anchor4, Resizer_1) => {
            Resizer_1($$anchor4, {
              get chunk() {
                return $$props.chunk;
              },
              get axis() {
                return $$props.axis;
              },
              get forceDate() {
                return $$props.forceDate;
              },
              get forceMargin() {
                return $$props.forceMargin;
              },
              children: ($$anchor5, $$slotProps) => {
                var fragment_3 = $.comment();
                var node_2 = $.first_child(fragment_3);
                $.snippet(node_2, defaultBody);
                $.append($$anchor5, fragment_3);
              },
              $$slots: { default: true }
            });
          });
          $.append($$anchor3, fragment_2);
        };
        var alternate = ($$anchor3) => {
          var fragment_4 = $.comment();
          var node_3 = $.first_child(fragment_4);
          $.snippet(node_3, defaultBody);
          $.append($$anchor3, fragment_4);
        };
        $.if(node, ($$render) => {
          if ($.get(Resizer2)) $$render(consequent);
          else $$render(alternate, false);
        });
      }
      $.append($$anchor2, fragment_1);
    };
    BaseEvent($$anchor, {
      get chunk() {
        return $$props.chunk;
      },
      get classes() {
        return $.get(classes);
      },
      get styles() {
        return $$props.styles;
      },
      get onpointerdown() {
        return $.get(onpointerdown);
      },
      get el() {
        return el();
      },
      set el($$value) {
        el($$value);
      },
      body,
      $$slots: { body: true }
    });
  }
  $.pop();
}
var root_2$4 = $.from_html(`<time></time>`);
var root_3 = $.from_html(`<span></span>`);
var root_4 = $.from_html(`<a role="button" tabindex="0" aria-haspopup="dialog"></a>`);
var root_1$a = $.from_html(`<div><!> <!></div> <div><!></div>`, 1);
function Day$3($$anchor, $$props) {
  $.push($$props, true);
  const $firstDay = () => $.store_get($.get(firstDay), "$firstDay", $$stores);
  const [$$stores, $$cleanup] = $.setup_stores();
  let mainState = getContext("state");
  let viewState = getContext("view-state");
  let features = $.derived(() => mainState.features), date = $.derived(() => mainState.options.date), firstDay = $.derived(() => mainState.options.firstDay), moreLinkContent = $.derived(() => mainState.options.moreLinkContent), theme = $.derived(() => mainState.options.theme), weekNumbers = $.derived(() => mainState.options.weekNumbers), weekNumberContent = $.derived(() => mainState.options.weekNumberContent);
  let hiddenChunks = $.derived(() => viewState.hiddenChunks), intlDayCell = $.derived(() => viewState.intlDayCell);
  let dayStart = $.derived(() => $$props.day.dayStart), disabled = $.derived(() => $$props.day.disabled), highlight = $.derived(() => $$props.day.highlight);
  let otherMonth = $.derived(() => $.get(dayStart).getUTCMonth() !== $.get(date).getUTCMonth());
  let classes = $.derived(() => (classNames) => [...classNames, $.get(otherMonth) && $.get(theme).otherMonth]);
  let showWeekNumber = $.derived(() => $.get(weekNumbers) && $.get(dayStart).getUTCDay() === ($.get(firstDay) ? 1 : 0));
  let weekNumber = $.derived(() => {
    let weekNumber2;
    if ($.get(showWeekNumber)) {
      let week = getWeekNumber($.get(dayStart), $firstDay());
      if ($.get(weekNumberContent)) {
        weekNumber2 = isFunction($.get(weekNumberContent)) ? $.get(weekNumberContent)({ date: toLocalDate($.get(dayStart)), week }) : $.get(weekNumberContent);
      } else {
        weekNumber2 = "W" + String(week).padStart(2, "0");
      }
    }
    return weekNumber2;
  });
  let dayHiddenChunks = $.derived(() => $.get(hiddenChunks).get($.get(dayStart).getTime()));
  let moreLink = $.derived(() => {
    let moreLink2 = "";
    if ($.get(dayHiddenChunks)) {
      let text = "+" + $.get(dayHiddenChunks).length + " more";
      if ($.get(moreLinkContent)) {
        moreLink2 = isFunction($.get(moreLinkContent)) ? $.get(moreLinkContent)({ num: $.get(dayHiddenChunks).length, text }) : $.get(moreLinkContent);
      } else {
        moreLink2 = text;
      }
    }
    return moreLink2;
  });
  function showMore() {
    viewState.popupDay = $$props.day;
  }
  BaseDay($$anchor, {
    get date() {
      return $.get(dayStart);
    },
    allDay: true,
    get classes() {
      return $.get(classes);
    },
    get disabled() {
      return $.get(disabled);
    },
    get highlight() {
      return $.get(highlight);
    },
    get noIeb() {
      return $$props.noIeb;
    },
    get noBeb() {
      return $$props.noBeb;
    },
    children: ($$anchor2, $$slotProps) => {
      var fragment_1 = root_1$a();
      var div = $.first_child(fragment_1);
      var node = $.child(div);
      {
        var consequent = ($$anchor3) => {
          var time = root_2$4();
          $.attach(time, () => contentFrom($.get(intlDayCell).format($.get(dayStart))));
          $.template_effect(($0) => $.set_attribute(time, "datetime", $0), [() => toISOString($.get(dayStart), 10)]);
          $.append($$anchor3, time);
        };
        var d = $.derived(() => $.get(features).includes("dayNumber"));
        $.if(node, ($$render) => {
          if ($.get(d)) $$render(consequent);
        });
      }
      var node_1 = $.sibling(node, 2);
      {
        var consequent_1 = ($$anchor3) => {
          var span = root_3();
          $.attach(span, () => contentFrom($.get(weekNumber)));
          $.template_effect(() => $.set_class(span, 1, $.get(theme).weekNumber));
          $.append($$anchor3, span);
        };
        $.if(node_1, ($$render) => {
          if ($.get(showWeekNumber)) $$render(consequent_1);
        });
      }
      $.reset(div);
      var div_1 = $.sibling(div, 2);
      var node_2 = $.child(div_1);
      {
        var consequent_2 = ($$anchor3) => {
          var a = root_4();
          var event_handler = $.derived(() => stopPropagation(showMore));
          var event_handler_1 = $.derived(() => keyEnter(showMore));
          var event_handler_2 = $.derived(stopPropagation);
          $.attach(a, () => contentFrom($.get(moreLink)));
          $.delegated("click", a, function(...$$args) {
            $.get(event_handler)?.apply(this, $$args);
          });
          $.delegated("keydown", a, function(...$$args) {
            $.get(event_handler_1)?.apply(this, $$args);
          });
          $.delegated("pointerdown", a, function(...$$args) {
            $.get(event_handler_2)?.apply(this, $$args);
          });
          $.append($$anchor3, a);
        };
        $.if(node_2, ($$render) => {
          if ($.get(dayHiddenChunks)) $$render(consequent_2);
        });
      }
      $.reset(div_1);
      $.template_effect(() => {
        $.set_class(div, 1, $.get(theme).dayHead);
        $.set_class(div_1, 1, $.get(theme).dayFoot);
      });
      $.append($$anchor2, fragment_1);
    },
    $$slots: { default: true }
  });
  $.pop();
  $$cleanup();
}
$.delegate(["click", "keydown", "pointerdown"]);
function Event$3($$anchor, $$props) {
  $.push($$props, true);
  let inPopup = $.prop($$props, "inPopup", 3, false);
  let $$d = $.derived(() => getContext("state")), dayMaxEvents = $.derived(() => $.get($$d).options.dayMaxEvents);
  let $$d_1 = $.derived(() => getContext("view-state")), colsCount2 = $.derived(() => $.get($$d_1).colsCount), gridEl = $.derived(() => $.get($$d_1).gridEl), hiddenChunks = $.derived(() => $.get($$d_1).hiddenChunks), popupDay = $.derived(() => $.get($$d_1).popupDay);
  let el = $.state(void 0);
  let margin = $.state(1);
  let hidden = $.state(false);
  let event = $.derived(() => $$props.chunk.event);
  let display = $.derived(() => $$props.chunk.event.display);
  let dayEl = $.derived(() => $.get(gridEl).children.item(($$props.chunk.gridRow - 1) * $.get(colsCount2) + $$props.chunk.gridColumn - 1));
  $.user_effect(() => {
    if (!inPopup()) {
      $.set(margin, height($.get(dayEl).firstElementChild) || 1, true);
    }
  });
  let styles = $.derived(() => (style) => {
    style["grid-column"] = `${$$props.chunk.gridColumn} / span ${$$props.chunk.dates.length}`;
    style["grid-row"] = $$props.chunk.gridRow;
    if (!bgEvent($.get(display))) {
      let marginTop = inPopup() ? 1 : $.get(margin);
      if ($.get(event)._margin) {
        let [_margin, _gridRow] = $.get(event)._margin;
        if (_margin > marginTop && $$props.chunk.gridRow === _gridRow) {
          marginTop = _margin;
        }
      }
      style["margin-block-start"] = `${marginTop}px`;
    }
    if ($.get(hidden)) {
      style["visibility"] = "hidden";
    }
    return style;
  });
  function reposition() {
    $.set(margin, repositionEvent$1($$props.chunk, height($.get(el)), height($.get(dayEl).firstElementChild) || 1), true);
  }
  function hide() {
    if ($.get(dayMaxEvents) === true) {
      let h = height($.get(dayEl)) - footHeight($.get(dayEl));
      $.set(hidden, $$props.chunk.bottom > h);
      if ($.get(hidden)) {
        for (let date of $$props.chunk.dates) {
          let key = date.getTime();
          if ($.get(hiddenChunks).has(key)) {
            let chunks = $.get(hiddenChunks).get(key);
            if (!chunks.includes($$props.chunk)) {
              $.get(hiddenChunks).set(key, [...chunks, $$props.chunk]);
            }
          } else {
            $.get(hiddenChunks).set(key, [$$props.chunk]);
          }
        }
      }
    } else {
      $.set(hidden, false);
      if ($.get(hiddenChunks).size) {
        $.get(hiddenChunks).clear();
      }
    }
  }
  function footHeight(dayEl2) {
    let h = 0;
    for (let i = 0; i < $$props.chunk.dates.length; ++i) {
      h = max(h, height(dayEl2.lastElementChild));
      dayEl2 = dayEl2.nextElementSibling;
      if (!dayEl2) {
        break;
      }
    }
    return h;
  }
  var $$exports = { reposition, hide };
  {
    let $0 = $.derived(() => inPopup() && $.get(popupDay).dayStart);
    let $1 = $.derived(() => [$.get(margin), $$props.chunk.gridRow]);
    InteractableEvent($$anchor, {
      get chunk() {
        return $$props.chunk;
      },
      get styles() {
        return $.get(styles);
      },
      axis: "x",
      get forceDate() {
        return $.get($0);
      },
      get forceMargin() {
        return $.get($1);
      },
      get el() {
        return $.get(el);
      },
      set el($$value) {
        $.set(el, $$value, true);
      }
    });
  }
  return $.pop($$exports);
}
var root$5 = $.from_html(`<dialog closedby="closerequest"><header><time></time>  <a role="button" tabindex="0">&times;</a></header> <div></div></dialog>`);
function Popup($$anchor, $$props) {
  $.push($$props, true);
  let viewState = getContext("view-state");
  let $$d = $.derived(() => getContext("state")), interaction = $.derived(() => $.get($$d).interaction), buttonText = $.derived(() => $.get($$d).options.buttonText), theme = $.derived(() => $.get($$d).options.theme);
  let colsCount2 = $.derived(() => viewState.colsCount), chunks = $.derived(() => viewState.chunks), gridEl = $.derived(() => viewState.gridEl), intlDayPopover = $.derived(() => viewState.intlDayPopover), popupDay = $.derived(() => viewState.popupDay);
  let el = $.state(void 0);
  let style = $.state("");
  let gridColumn = $.derived(() => $.get(popupDay).gridColumn), gridRow = $.derived(() => $.get(popupDay).gridRow), dayStart = $.derived(() => $.get(popupDay).dayStart), dayEnd = $.derived(() => $.get(popupDay).dayEnd);
  let popupChunks = $.derived(() => {
    let result = [];
    for (let chunk of $.get(chunks)) {
      if (chunk.gridRow === $.get(gridRow) && chunk.gridColumn <= $.get(gridColumn) && chunk.gridColumn + chunk.dates.length > $.get(gridColumn)) {
        result.push(assign({}, chunk, createEventChunk(chunk.event, $.get(dayStart), $.get(dayEnd))));
      }
    }
    result.sort((a, b) => a.top - b.top);
    return result;
  });
  onMount(() => {
    $.get(el).show();
  });
  $.user_effect(() => {
    if ($.get(popupChunks).length) {
      untrack(position);
    } else {
      close();
    }
  });
  function position() {
    let dayEl = $.get(gridEl).children.item(($.get(gridRow) - 1) * $.get(colsCount2) + $.get(gridColumn) - 1);
    let popupRect = rect($.get(el));
    let dayRect = rect(dayEl);
    let gridRect = rect($.get(gridEl));
    $.set(style, "");
    let left;
    if (popupRect.width >= gridRect.width) {
      left = gridRect.left - dayRect.left;
      let right = dayRect.right - gridRect.right;
      $.set(style, $.get(style) + `inset-inline-end:${right}px;`);
    } else {
      left = (dayRect.width - popupRect.width) / 2;
      if (dayRect.left + left < gridRect.left) {
        left = gridRect.left - dayRect.left;
      } else if (dayRect.left + left + popupRect.width > gridRect.right) {
        left = gridRect.right - dayRect.left - popupRect.width;
      }
    }
    $.set(style, $.get(style) + `inset-inline-start:${left}px;`);
    let top;
    if (popupRect.height >= gridRect.height) {
      top = gridRect.top - dayRect.top;
      $.set(style, $.get(style) + `block-size:${gridRect.height}px;`);
    } else {
      top = (dayRect.height - popupRect.height) / 2;
      if (dayRect.top + top < gridRect.top) {
        top = gridRect.top - dayRect.top;
      } else if (dayRect.top + top + popupRect.height > gridRect.bottom) {
        top = gridRect.bottom - dayRect.top - popupRect.height;
      }
    }
    $.set(style, $.get(style) + `inset-block-start:${top}px;`);
  }
  function close() {
    viewState.popupDay = null;
  }
  function handlePointerDownOutside() {
    close();
    $.get(interaction).action?.noClick();
  }
  var dialog = root$5();
  let styles;
  var header = $.child(dialog);
  var time = $.child(header);
  $.attach(time, () => contentFrom($.get(intlDayPopover).format($.get(dayStart))));
  var a_1 = $.sibling(time, 2);
  $.autofocus(a_1, true);
  var event_handler = $.derived(() => stopPropagation(close));
  var event_handler_1 = $.derived(() => keyEnter(close));
  $.reset(header);
  var div = $.sibling(header, 2);
  $.each(div, 21, () => $.get(popupChunks), $.index, ($$anchor2, chunk) => {
    Event$3($$anchor2, {
      get chunk() {
        return $.get(chunk);
      },
      inPopup: true
    });
  });
  $.reset(div);
  $.reset(dialog);
  $.bind_this(dialog, ($$value) => $.set(el, $$value), () => $.get(el));
  $.attach(dialog, () => outsideEvent("pointerdown"));
  $.template_effect(
    ($0) => {
      $.set_class(dialog, 1, $.get(theme).popup);
      styles = $.set_style(dialog, $.get(style), styles, { "grid-area": `${$.get(gridRow) + 1} / ${$.get(gridColumn)}` });
      $.set_class(header, 1, $.get(theme).dayHead);
      $.set_attribute(time, "datetime", $0);
      $.set_attribute(a_1, "aria-label", $.get(buttonText).close);
      $.set_class(div, 1, $.get(theme).events);
    },
    [() => toISOString($.get(dayStart), 10)]
  );
  $.event("pointerdownoutside", dialog, handlePointerDownOutside);
  $.event("close", dialog, close);
  $.delegated("click", a_1, function(...$$args) {
    $.get(event_handler)?.apply(this, $$args);
  });
  $.delegated("keydown", a_1, function(...$$args) {
    $.get(event_handler_1)?.apply(this, $$args);
  });
  $.append($$anchor, dialog);
  $.pop();
}
$.delegate(["click", "keydown"]);
var root_2$3 = $.from_html(`<div role="columnheader"><span></span></div>`);
var root_1$9 = $.from_html(`<section><header><div role="row"></div></header> <div><div></div> <div><!> <!> <!></div></div> <!></section>`);
function View$3($$anchor, $$props) {
  $.push($$props, true);
  let mainState = getContext("state");
  let viewState = new ViewState$4(mainState);
  setContext("view-state", viewState);
  let intlDayHeader = $.derived(() => mainState.intlDayHeader), intlDayHeaderAL = $.derived(() => mainState.intlDayHeaderAL), dayMaxEvents = $.derived(() => mainState.options.dayMaxEvents), theme = $.derived(() => mainState.options.theme);
  let grid2 = $.derived(() => viewState.grid), chunks = $.derived(() => viewState.chunks), bgChunks = $.derived(() => viewState.bgChunks), iChunks = $.derived(() => viewState.iChunks), hiddenChunks = $.derived(() => viewState.hiddenChunks), popupDay = $.derived(() => viewState.popupDay);
  let refs = [];
  function reposition() {
    runReposition(refs, $.get(chunks));
    $.get(hiddenChunks).clear();
    tick().then(hide);
  }
  function hide() {
    $.get(hiddenChunks).size;
    refs.forEach((ref) => ref?.hide());
  }
  $.user_effect(reposition);
  $.user_effect(hide);
  var fragment = $.comment();
  var node = $.first_child(fragment);
  {
    var consequent_1 = ($$anchor2) => {
      var section = root_1$9();
      let styles;
      var header = $.child(section);
      var div = $.child(header);
      $.each(div, 21, () => $.get(grid2)[0], $.index, ($$anchor3, $$item, i) => {
        let dayStart = () => $.get($$item).dayStart;
        var div_1 = root_2$3();
        $.set_attribute(div_1, "aria-colindex", 1 + i);
        var span = $.child(div_1);
        $.attach(span, () => contentFrom($.get(intlDayHeader).format(dayStart())));
        $.reset(div_1);
        $.template_effect(
          ($0, $1) => {
            $.set_class(div_1, 1, $0);
            $.set_attribute(span, "aria-label", $1);
          },
          [
            () => $.clsx([
              $.get(theme).colHead,
              $.get(theme).weekdays?.[dayStart().getUTCDay()]
            ]),
            () => $.get(intlDayHeaderAL).format(dayStart())
          ]
        );
        $.append($$anchor3, div_1);
      });
      $.reset(div);
      $.reset(header);
      var div_2 = $.sibling(header, 2);
      var div_3 = $.child(div_2);
      $.each(div_3, 21, () => $.get(grid2), $.index, ($$anchor3, days, i) => {
        var fragment_1 = $.comment();
        var node_1 = $.first_child(fragment_1);
        $.each(node_1, 17, () => $.get(days), $.index, ($$anchor4, day, j) => {
          {
            let $0 = $.derived(() => j + 1 === length($.get(days)));
            let $1 = $.derived(() => i + 1 === length($.get(grid2)));
            Day$3($$anchor4, {
              get day() {
                return $.get(day);
              },
              get noIeb() {
                return $.get($0);
              },
              get noBeb() {
                return $.get($1);
              }
            });
          }
        });
        $.append($$anchor3, fragment_1);
      });
      $.reset(div_3);
      $.bind_this(div_3, ($$value) => viewState.gridEl = $$value, () => viewState?.gridEl);
      var div_4 = $.sibling(div_3, 2);
      var node_2 = $.child(div_4);
      $.each(node_2, 19, () => $.get(chunks), (chunk) => chunk.id, ($$anchor3, chunk, i) => {
        $.bind_this(
          Event$3($$anchor3, {
            get chunk() {
              return $.get(chunk);
            }
          }),
          ($$value, i2) => refs[i2] = $$value,
          (i2) => refs?.[i2],
          () => [$.get(i)]
        );
      });
      var node_3 = $.sibling(node_2, 2);
      $.each(node_3, 17, () => $.get(bgChunks), (chunk) => chunk.id, ($$anchor3, chunk) => {
        Event$3($$anchor3, {
          get chunk() {
            return $.get(chunk);
          }
        });
      });
      var node_4 = $.sibling(node_3, 2);
      $.each(node_4, 17, () => $.get(iChunks), $.index, ($$anchor3, chunk) => {
        Event$3($$anchor3, {
          get chunk() {
            return $.get(chunk);
          }
        });
      });
      $.reset(div_4);
      $.reset(div_2);
      var node_5 = $.sibling(div_2, 2);
      {
        var consequent = ($$anchor3) => {
          Popup($$anchor3, {});
        };
        $.if(node_5, ($$render) => {
          if ($.get(popupDay)) $$render(consequent);
        });
      }
      $.reset(section);
      $.bind_this(section, ($$value) => mainState.mainEl = $$value, () => mainState?.mainEl);
      $.attach(section, () => resizeObserver(reposition));
      $.template_effect(
        ($0) => {
          $.set_class(section, 1, $.clsx([
            $.get(theme).main,
            $.get(dayMaxEvents) === true && $.get(theme).uniform
          ]));
          styles = $.set_style(section, "", styles, $0);
          $.set_class(header, 1, $.get(theme).header);
          $.set_class(div, 1, $.get(theme).grid);
          $.set_class(div_2, 1, $.get(theme).body);
          $.set_class(div_3, 1, $.get(theme).grid);
          $.set_class(div_4, 1, $.get(theme).events);
        },
        [
          () => ({
            "--ec-grid-cols": length($.get(grid2)[0]),
            "--ec-grid-rows": length($.get(grid2))
          })
        ]
      );
      $.append($$anchor2, section);
    };
    var d = $.derived(() => !empty($.get(grid2)) && !empty($.get(grid2)[0]));
    $.if(node, ($$render) => {
      if ($.get(d)) $$render(consequent_1);
    });
  }
  $.append($$anchor, fragment);
  $.pop();
}
const index$5 = {
  createOptions(options) {
    assign(options, {
      dayMaxEvents: false,
      dayCellFormat: { day: "numeric" },
      dayPopoverFormat: { month: "long", day: "numeric", year: "numeric" },
      moreLinkContent: void 0,
      weekNumbers: false,
      weekNumberContent: void 0,
      // Common options
      view: "dayGridMonth"
    });
    assign(options.buttonText, {
      dayGridDay: "day",
      dayGridMonth: "month",
      dayGridWeek: "week",
      close: "Close"
    });
    assign(options.theme, {
      uniform: "ec-uniform",
      dayFoot: "ec-day-foot",
      otherMonth: "ec-other-month",
      popup: "ec-popup",
      weekNumber: "ec-week-number"
    });
    assign(options.views, {
      dayGridDay: {
        buttonText: btnTextDay,
        component: () => View$3,
        dayHeaderFormat: { weekday: "long" },
        displayEventEnd: false,
        duration: { days: 1 },
        theme: themeView("ec-day-grid ec-day-view")
      },
      dayGridWeek: {
        buttonText: btnTextWeek,
        component: () => View$3,
        displayEventEnd: false,
        theme: themeView("ec-day-grid ec-week-view")
      },
      dayGridMonth: {
        buttonText: btnTextMonth,
        component: initMonthViewComponent$1,
        dayHeaderFormat: { weekday: "short" },
        dayHeaderAriaLabelFormat: { weekday: "long" },
        displayEventEnd: false,
        duration: { months: 1 },
        theme: themeView("ec-day-grid ec-month-view"),
        titleFormat: { year: "numeric", month: "long" }
      }
    });
  }
};
function initMonthViewComponent$1(mainState) {
  mainState.features = ["dayNumber"];
  mainState.extensions.activeRange = (start, end) => {
    let { options: { firstDay } } = mainState;
    return {
      start: prevClosestDay(start, firstDay),
      end: nextClosestDay(end, firstDay)
    };
  };
  return View$3;
}
function eventDraggable(event, $eventStartEditable, $editable) {
  return event.startEditable ?? $eventStartEditable ?? event.editable ?? $editable;
}
function eventResizable(event, $eventDurationEditable, $editable) {
  return event.durationEditable ?? $eventDurationEditable ?? event.editable ?? $editable;
}
let busy = false;
function animate(fn) {
  if (!busy) {
    busy = true;
    window.requestAnimationFrame(() => {
      fn();
      busy = false;
    });
  }
}
function limit(value, minLimit, maxLimit) {
  return max(minLimit, min(maxLimit, value));
}
function setIClasses(mainState) {
  return () => {
    let { options: { editable, eventStartEditable, theme } } = mainState;
    mainState.iClasses = (classNames, event) => {
      let { display } = event;
      return [
        ...classNames,
        helperEvent(display) ? [theme[display]] : !bgEvent(display) && eventDraggable(event, eventStartEditable, editable) ? [theme.draggable] : []
      ];
    };
  };
}
function handleScroll(mainState) {
  return () => {
    let { interaction, mainEl } = mainState;
    if (mainEl) {
      return listen(mainEl, "scroll", () => {
        interaction.action.handleScroll();
        interaction.pointer?.handleScroll();
      });
    }
  };
}
class AuxState {
  constructor(mainState) {
    this.#setupEffects(mainState);
  }
  #setupEffects(mainState) {
    $.user_pre_effect(setIClasses(mainState));
    $.user_effect(handleScroll(mainState));
  }
}
function Action($$anchor, $$props) {
  $.push($$props, true);
  let mainState = getContext("state");
  let events = $.derived(() => mainState.events), iEvents = $.derived(() => mainState.iEvents), features = $.derived(() => mainState.features), view2 = $.derived(() => mainState.view), mainEl = $.derived(() => mainState.mainEl), dateClick = $.derived(() => mainState.options.dateClick), dragConstraint = $.derived(() => mainState.options.dragConstraint), dragScroll = $.derived(() => mainState.options.dragScroll), editable = $.derived(() => mainState.options.editable), eventStartEditable = $.derived(() => mainState.options.eventStartEditable), eventDragMinDistance = $.derived(() => mainState.options.eventDragMinDistance), eventDragStart = $.derived(() => mainState.options.eventDragStart), eventDragStop = $.derived(() => mainState.options.eventDragStop), eventDrop = $.derived(() => mainState.options.eventDrop), eventLongPressDelay = $.derived(() => mainState.options.eventLongPressDelay), eventResizeStart = $.derived(() => mainState.options.eventResizeStart), eventResizeStop = $.derived(() => mainState.options.eventResizeStop), eventResize = $.derived(() => mainState.options.eventResize), longPressDelay = $.derived(() => mainState.options.longPressDelay), resizeConstraint = $.derived(() => mainState.options.resizeConstraint), selectable = $.derived(() => mainState.options.selectable), selectFn = $.derived(() => mainState.options.select), selectBackgroundColor = $.derived(() => mainState.options.selectBackgroundColor), selectConstraint = $.derived(() => mainState.options.selectConstraint), selectLongPressDelay = $.derived(() => mainState.options.selectLongPressDelay), selectMinDistance = $.derived(() => mainState.options.selectMinDistance), unselectFn = $.derived(() => mainState.options.unselect), unselectAuto = $.derived(() => mainState.options.unselectAuto), unselectCancel = $.derived(() => mainState.options.unselectCancel), validRange = $.derived(() => mainState.options.validRange);
  const ACTION_DRAG = 1;
  const ACTION_RESIZE_END = 2;
  const ACTION_RESIZE_START = 3;
  const ACTION_SELECT = 4;
  const ACTION_CLICK = 5;
  const ACTION_NO_ACTION = 6;
  let action;
  let interacting;
  let event;
  let iEvent;
  let display;
  let date;
  let newDate;
  let resource;
  let newResource;
  let fromX;
  let fromY;
  let toX;
  let toY;
  let gridEl;
  let allDaySlot;
  let delta;
  let allDay;
  let iClass;
  let minResize;
  let selectStep;
  let selected;
  let noDateClick;
  let timer;
  let viewport;
  let margin;
  let snapDuration;
  let extraDuration;
  function draggable(event2) {
    return eventDraggable(event2, $.get(eventStartEditable), $.get(editable));
  }
  function drag(eventToDrag, jsEvent, forceDate, forceMargin, snap2) {
    if (!action) {
      action = validJsEvent(jsEvent) ? ACTION_DRAG : ACTION_NO_ACTION;
      if (complexAction()) {
        event = eventToDrag;
        common(jsEvent, snap2);
        if (forceDate) {
          date = forceDate;
        }
        if (forceMargin) {
          margin = forceMargin;
        }
        iClass = "dragging";
        move(jsEvent);
      }
    }
  }
  function resize(eventToResize, jsEvent, start, axis, forceDate, forceMargin, zeroDuration, snap2) {
    if (!action) {
      action = validJsEvent(jsEvent) ? start ? ACTION_RESIZE_START : ACTION_RESIZE_END : ACTION_NO_ACTION;
      if (complexAction()) {
        event = eventToResize;
        common(jsEvent, snap2);
        if (forceDate) {
          date = forceDate;
        }
        if (forceMargin) {
          margin = forceMargin;
        }
        iClass = axis === "x" ? "resizingX" : "resizingY";
        if (resizingStart()) {
          minResize = cloneDate(event.end);
          if (allDay) {
            copyTime(minResize, event.start);
            if (minResize >= event.end) {
              subtractDay(minResize);
            }
          } else {
            subtractDuration(minResize, snapDuration);
            if (minResize < event.start) {
              minResize = event.start;
            }
            date = event.start;
          }
        } else {
          minResize = cloneDate(event.start);
          if (allDay) {
            copyTime(minResize, event.end);
            if (minResize <= event.start && !zeroDuration) {
              addDay(minResize);
            }
          } else {
            addDuration(minResize, snapDuration);
            if (minResize > event.end) {
              minResize = event.end;
            }
            date = event.end;
            if (!zeroDuration) {
              date = subtractDuration(cloneDate(date), snapDuration);
            }
          }
          if (zeroDuration && !allDay) {
            extraDuration = snapDuration;
          }
        }
        move(jsEvent);
      }
    }
  }
  function select(jsEvent, snap2) {
    if (!action) {
      action = validJsEvent(jsEvent) ? $.get(selectable) && !$.get(features).includes("list") ? ACTION_SELECT : ACTION_CLICK : ACTION_NO_ACTION;
      if (complexAction()) {
        common(jsEvent, snap2);
        iClass = "selecting";
        selectStep = allDay ? createDuration({ day: 1 }) : snapDuration;
        event = {
          allDay,
          start: date,
          end: addDuration(cloneDate(date), selectStep),
          resourceIds: resource ? [resource.id] : []
        };
        move(jsEvent);
      }
    }
  }
  function noAction() {
    if (!action) {
      action = ACTION_NO_ACTION;
    }
  }
  function common(jsEvent, snap2) {
    window.getSelection().removeAllRanges();
    fromX = toX = jsEvent.clientX;
    fromY = toY = jsEvent.clientY;
    snapDuration = snap2?.duration;
    let dayEl = getElementWithPayload(toX, toY);
    ({ allDay, date, resource } = getPayload(dayEl)(toX, toY));
    allDaySlot = $.get(mainEl) !== ancestor(dayEl, 3);
    gridEl = ancestor(dayEl, 1);
    calcViewport();
    if (jsEvent.pointerType !== "mouse") {
      timer = setTimeout(
        () => {
          if (action) {
            interacting = true;
            move(jsEvent);
          }
        },
        (selecting() ? $.get(selectLongPressDelay) : $.get(eventLongPressDelay)) ?? $.get(longPressDelay)
      );
    }
  }
  function move(jsEvent) {
    if (interacting || jsEvent && jsEvent.pointerType === "mouse" && distance() >= (selecting() ? $.get(selectMinDistance) : $.get(eventDragMinDistance))) {
      interacting = true;
      unselect(jsEvent);
      mainState.iClass = iClass;
      if (!iEvent) {
        if (selecting()) {
          createIEventSelect();
        } else {
          createIEvent(jsEvent, resizing() ? $.get(eventResizeStart) : $.get(eventDragStart));
        }
      }
      let payload = findPayload(findDayEl());
      if (payload) {
        let newAllDay;
        ({ allDay: newAllDay, date: newDate, resource: newResource } = payload);
        if (newAllDay === allDay) {
          let candidate = copyIEventData({}, iEvent);
          let constraintFn = $.get(resizeConstraint);
          delta = createDuration((newDate - date) / 1e3);
          if (resizingStart()) {
            candidate.start = addDuration(cloneDate(event.start), delta);
            if (candidate.start > minResize) {
              candidate.start = minResize;
              delta = createDuration((minResize - event.start) / 1e3);
            }
          } else {
            candidate.end = addDuration(cloneDate(event.end), delta);
            if (extraDuration) {
              addDuration(candidate.end, extraDuration);
            }
            if (resizing()) {
              if (candidate.end < minResize) {
                candidate.end = minResize;
                delta = createDuration((minResize - event.end) / 1e3);
              }
            } else if (selecting()) {
              if (candidate.end < event.end) {
                candidate.start = subtractDuration(candidate.end, selectStep);
                candidate.end = event.end;
              } else {
                candidate.start = event.start;
              }
              constraintFn = $.get(selectConstraint);
            } else {
              candidate.start = addDuration(cloneDate(event.start), delta);
              if (resource) {
                candidate.resourceIds = event.resourceIds.filter((id) => id !== resource.id);
                candidate.resourceIds.push(newResource.id);
              }
              constraintFn = $.get(dragConstraint);
            }
          }
          do {
            if (constraintFn !== void 0) {
              candidate = copyIEventData(cloneEvent(event), candidate);
              let result = constraintFn(selecting() ? createSelectCallbackInfo(candidate, jsEvent) : createCallbackInfo(candidate, event, jsEvent));
              if (result === false) {
                updateIEvent(event);
                break;
              }
            }
            updateIEvent(candidate);
          } while (0);
        }
      }
    }
    if ($.get(dragScroll)) {
      let thresholdY = 24;
      let thresholdX = 24;
      animate(() => {
        if (viewport) {
          if (!allDaySlot) {
            if (toY < viewport.top + thresholdY) {
              $.get(mainEl).scrollTop += max(-8, (toY - viewport.top - thresholdY) / 3);
            }
            if (toY > viewport.bottom - thresholdY) {
              $.get(mainEl).scrollTop += min(8, (toY - viewport.bottom + thresholdY) / 3);
            }
          }
          if (toX < viewport.left + thresholdX) {
            $.get(mainEl).scrollLeft += max(-8, (toX - viewport.left - thresholdX) / 3);
          }
          if (toX > viewport.right - thresholdX) {
            $.get(mainEl).scrollLeft += min(8, (toX - viewport.right + thresholdX) / 3);
          }
          if (toY < thresholdY) {
            window.scrollBy(0, max(-8, (toY - thresholdY) / 3));
          }
          if (toY > window.innerHeight - thresholdY) {
            window.scrollBy(0, min(8, (toY - window.innerHeight + thresholdY) / 3));
          }
        }
      });
    }
  }
  function handleScroll2() {
    if (complexAction()) {
      calcViewport();
      move();
    }
  }
  function handlePointerMove(jsEvent) {
    if (complexAction() && jsEvent.isPrimary) {
      toX = jsEvent.clientX;
      toY = jsEvent.clientY;
      move(jsEvent);
    }
  }
  function handlePointerUp(jsEvent) {
    if (selected && $.get(unselectAuto) && !($.get(unselectCancel) && jsEvent.target.closest($.get(unselectCancel)))) {
      unselect(jsEvent);
    }
    if (action && jsEvent.isPrimary) {
      if (interacting) {
        if (selecting()) {
          selected = true;
          if (isFunction($.get(selectFn))) {
            let info = createSelectCallbackInfo(iEvent, jsEvent);
            $.get(selectFn)(info);
          }
        } else {
          event.display = display;
          let callback = resizing() ? $.get(eventResizeStop) : $.get(eventDragStop);
          if (isFunction(callback)) {
            callback({
              event: toEventWithLocalDates(event),
              jsEvent,
              view: toViewWithLocalDates($.get(view2))
            });
          }
          let oldEvent = cloneEvent(event);
          updateEvent(event, iEvent);
          destroyIEvent();
          callback = resizing() ? $.get(eventResize) : $.get(eventDrop);
          if (isFunction(callback)) {
            let eventRef = event;
            let info = createCallbackInfo(event, oldEvent, jsEvent);
            callback(assign(info, {
              revert() {
                updateEvent(eventRef, oldEvent);
              }
            }));
          }
        }
      } else {
        if (clicking() || selecting()) {
          if (isFunction($.get(dateClick)) && !noDateClick) {
            toX = jsEvent.clientX;
            toY = jsEvent.clientY;
            let dayEl = getElementWithPayload(toX, toY);
            if (dayEl) {
              let { allDay: allDay2, date: date2, resource: resource2 } = getPayload(dayEl)(toX, toY);
              $.get(dateClick)({
                allDay: allDay2,
                date: toLocalDate(date2),
                dateStr: toISOString(date2),
                dayEl,
                jsEvent,
                view: toViewWithLocalDates($.get(view2)),
                resource: resource2
              });
            }
          }
        }
      }
      handlePointerCancel();
    }
    noDateClick = false;
  }
  function handlePointerCancel() {
    interacting = false;
    action = fromX = fromY = toX = toY = event = display = date = newDate = resource = newResource = delta = extraDuration = allDay = minResize = selectStep = margin = gridEl = viewport = snapDuration = void 0;
    mainState.iClass = void 0;
    if (timer) {
      clearTimeout(timer);
      timer = void 0;
    }
  }
  function findDayEl() {
    return getElementWithPayload(limit(toX, viewport.left, viewport.right), limit(toY, viewport.top, viewport.bottom));
  }
  function findPayload(dayEl) {
    if (dayEl) {
      let payload = getPayload(dayEl)(toX, toY);
      if (payload.disabled) {
        if (!$.get(validRange).end || payload.date < $.get(validRange).end) {
          return findPayload(dayEl.nextElementSibling);
        }
        if (!$.get(validRange).start || payload.date > $.get(validRange).start) {
          return findPayload(dayEl.previousElementSibling);
        }
      } else {
        if ((selecting() || resizing()) && payload.resource && !iEvent.resourceIds.includes(payload.resource.id) && !$.get(features).includes("timeline")) {
          if (toX > fromX) {
            return findPayload(dayEl.previousElementSibling);
          } else {
            return findPayload(dayEl.nextElementSibling);
          }
        }
        return payload;
      }
    }
    return null;
  }
  function calcViewport() {
    let mainRect = rect($.get(mainEl));
    let gridRect = rect(gridEl);
    let scaleX = mainRect.width / $.get(mainEl).offsetWidth;
    let scaleY = mainRect.height / $.get(mainEl).offsetHeight;
    let rtl = isRtl();
    viewport = {
      left: max(0, rtl ? mainRect.right - $.get(mainEl).clientWidth * scaleX : gridRect.left + $.get(mainEl).scrollLeft * scaleX),
      right: min(document.documentElement.clientWidth, rtl ? gridRect.right + $.get(mainEl).scrollLeft * scaleX : mainRect.left + $.get(mainEl).clientWidth * scaleX) - 2,
      top: max(0, gridRect.top + (!allDaySlot ? $.get(mainEl).scrollTop : 0) * scaleY),
      bottom: min(document.documentElement.clientHeight, !allDaySlot ? mainRect.top + $.get(mainEl).clientHeight * scaleY : gridRect.bottom) - 2
    };
  }
  function createIEvent(jsEvent, callback) {
    if (isFunction(callback)) {
      callback({
        event: toEventWithLocalDates(event),
        jsEvent,
        view: toViewWithLocalDates($.get(view2))
      });
    }
    display = event.display;
    event.display = "preview";
    iEvent = cloneEvent(event);
    if (margin !== void 0) {
      iEvent._margin = margin;
    }
    if (extraDuration) {
      addDuration(iEvent.end, extraDuration);
    }
    event.display = "ghost";
    $.get(events).length = $.get(events).length;
  }
  function createIEventSelect() {
    iEvent = {
      id: "{select}",
      allDay: event.allDay,
      start: event.start,
      title: "",
      display: "preview",
      extendedProps: {},
      backgroundColor: $.get(selectBackgroundColor),
      resourceIds: event.resourceIds,
      classNames: [],
      styles: []
    };
  }
  function destroyIEvent() {
    iEvent = void 0;
    $.get(iEvents).delete("action");
  }
  function copyIEventData(target, source) {
    target.start = source.start;
    target.end = source.end;
    target.resourceIds = source.resourceIds;
    return { ...target };
  }
  function updateEvent(target, source) {
    copyIEventData(target, source);
    $.get(events).length = $.get(events).length;
  }
  function updateIEvent(source) {
    iEvent = copyIEventData(iEvent, source);
    $.get(iEvents).set("action", iEvent);
  }
  function createSelectCallbackInfo(event2, jsEvent) {
    let { start, end } = toEventWithLocalDates(event2);
    return {
      start,
      end,
      startStr: toISOString(event2.start),
      endStr: toISOString(event2.end),
      allDay,
      view: toViewWithLocalDates($.get(view2)),
      resource,
      jsEvent
    };
  }
  function createCallbackInfo(event2, oldEvent, jsEvent) {
    let info;
    if (resizing()) {
      info = resizingStart() ? { startDelta: delta, endDelta: createDuration(0) } : { startDelta: createDuration(0), endDelta: delta };
    } else {
      info = {
        delta,
        oldResource: resource !== newResource ? resource : void 0,
        newResource: resource !== newResource ? newResource : void 0
      };
    }
    assign(info, {
      event: toEventWithLocalDates(event2),
      oldEvent: toEventWithLocalDates(oldEvent),
      view: toViewWithLocalDates($.get(view2)),
      jsEvent
    });
    return info;
  }
  function distance() {
    return Math.sqrt(Math.pow(toX - fromX, 2) + Math.pow(toY - fromY, 2));
  }
  function resizing() {
    return action === ACTION_RESIZE_END || resizingStart();
  }
  function resizingStart() {
    return action === ACTION_RESIZE_START;
  }
  function clicking() {
    return action === ACTION_CLICK;
  }
  function selecting() {
    return action === ACTION_SELECT;
  }
  function complexAction() {
    return action && action < ACTION_CLICK;
  }
  function validJsEvent(jsEvent) {
    return jsEvent.isPrimary && (jsEvent.pointerType !== "mouse" || jsEvent.buttons & 1);
  }
  function unselect(jsEvent) {
    if (selected) {
      selected = false;
      destroyIEvent();
      if (isFunction($.get(unselectFn))) {
        $.get(unselectFn)({ jsEvent, view: toViewWithLocalDates($.get(view2)) });
      }
    }
  }
  $.user_pre_effect(() => {
    $.get(view2);
    unselect();
  });
  function noClick() {
    noDateClick = true;
  }
  function handleTouchStart(jsEvent) {
    if (complexAction()) {
      let target = jsEvent.target;
      let stops = [];
      let stop = () => runAll(stops);
      stops.push(listen(target, "touchmove", createPreventDefaultHandler(() => interacting)));
      stops.push(listen(target, "touchend", stop));
      stops.push(listen(target, "touchcancel", stop));
    }
  }
  function createPreventDefaultHandler(condition) {
    return (jsEvent) => {
      if (condition()) {
        jsEvent.preventDefault();
      }
    };
  }
  onMount(() => listen(window, "touchmove", noop, { passive: false }));
  var $$exports = {
    draggable,
    drag,
    resize,
    select,
    noAction,
    handleScroll: handleScroll2,
    unselect,
    noClick
  };
  $.event("pointermove", $.window, handlePointerMove);
  $.event("pointerup", $.window, handlePointerUp);
  $.event("pointercancel", $.window, handlePointerCancel);
  $.event("scroll", $.window, handleScroll2);
  var event_handler = $.derived(() => createPreventDefaultHandler(complexAction));
  $.event("selectstart", $.window, function(...$$args) {
    $.get(event_handler)?.apply(this, $$args);
  });
  var event_handler_1 = $.derived(() => createPreventDefaultHandler(() => timer));
  $.event("contextmenu", $.window, function(...$$args) {
    $.get(event_handler_1)?.apply(this, $$args);
  });
  $.event("touchstart", $.window, handleTouchStart, void 0, true);
  return $.pop($$exports);
}
function Pointer($$anchor, $$props) {
  $.push($$props, true);
  let $$d = $.derived(() => getContext("state")), iEvents = $.derived(() => $.get($$d).iEvents), slotDuration = $.derived(() => $.get($$d).options.slotDuration);
  let x = 0;
  let y = 0;
  let iEvent;
  function move() {
    let dayEl = getElementWithPayload(x, y);
    if (dayEl && !$.get(iEvents).has("action")) {
      let { allDay, date, resource, disabled } = getPayload(dayEl)(x, y);
      if (!disabled) {
        if (!iEvent) {
          createPointerEvent();
        }
        iEvent.allDay = allDay;
        iEvent.start = date;
        iEvent.end = addDuration(cloneDate(date), $.get(slotDuration));
        iEvent.resourceIds = resource ? [resource.id] : [];
        $.get(iEvents).set("pointer", { ...iEvent });
        return;
      }
    }
    removePointerEvent();
  }
  function handleScroll2() {
    move();
  }
  function handlePointerMove(jsEvent) {
    if (validEvent(jsEvent)) {
      x = jsEvent.clientX;
      y = jsEvent.clientY;
      move();
    }
  }
  function createPointerEvent() {
    iEvent = {
      id: "{pointer}",
      title: "",
      display: "pointer",
      extendedProps: {},
      backgroundColor: "transparent",
      classNames: [],
      styles: []
    };
  }
  function removePointerEvent() {
    iEvent = void 0;
    $.get(iEvents).delete("pointer");
  }
  function validEvent(jsEvent) {
    return jsEvent.isPrimary && jsEvent.pointerType === "mouse";
  }
  var $$exports = { handleScroll: handleScroll2 };
  $.event("pointermove", $.window, handlePointerMove);
  $.event("scroll", $.window, handleScroll2);
  return $.pop($$exports);
}
var root_1$8 = $.from_html(`<div></div>`);
var root_2$2 = $.from_html(`<div></div>`);
var root$4 = $.from_html(`<!> <!> <!>`, 1);
function Resizer($$anchor, $$props) {
  $.push($$props, true);
  let forceDate = $.prop($$props, "forceDate", 3, void 0), forceMargin = $.prop($$props, "forceMargin", 3, void 0);
  let $$d = $.derived(() => getContext("state")), action = $.derived(() => $.get($$d).interaction.action), editable = $.derived(() => $.get($$d).options.editable), eventDurationEditable = $.derived(() => $.get($$d).options.eventDurationEditable), eventResizableFromStart = $.derived(() => $.get($$d).options.eventResizableFromStart), theme = $.derived(() => $.get($$d).options.theme);
  let $$d_1 = $.derived(() => getContext("view-state")), snap2 = $.derived(() => $.get($$d_1).snap);
  let event = $.derived(() => $$props.chunk.event);
  let display = $.derived(() => $$props.chunk.event.display);
  let resizable = $.derived(() => !bgEvent($.get(display)) && !helperEvent($.get(display)) && eventResizable($.get(event), $.get(eventDurationEditable), $.get(editable)));
  function createResizeHandler(start) {
    return (jsEvent) => $.get(action).resize($.get(event), jsEvent, start, $$props.axis, forceDate(), forceMargin(), $$props.chunk.zeroDuration, $.get(snap2));
  }
  var fragment = root$4();
  var node = $.first_child(fragment);
  {
    var consequent = ($$anchor2) => {
      var div = root_1$8();
      var event_handler = $.derived(() => createResizeHandler(true));
      $.template_effect(() => $.set_class(div, 1, `${$.get(theme).resizer ?? ""} ${$.get(theme).start ?? ""}`));
      $.delegated("pointerdown", div, function(...$$args) {
        $.get(event_handler)?.apply(this, $$args);
      });
      $.append($$anchor2, div);
    };
    $.if(node, ($$render) => {
      if ($.get(resizable) && $.get(eventResizableFromStart)) $$render(consequent);
    });
  }
  var node_1 = $.sibling(node, 2);
  $.snippet(node_1, () => $$props.children);
  var node_2 = $.sibling(node_1, 2);
  {
    var consequent_1 = ($$anchor2) => {
      var div_1 = root_2$2();
      var event_handler_1 = $.derived(() => createResizeHandler(false));
      $.template_effect(() => $.set_class(div_1, 1, $.get(theme).resizer));
      $.delegated("pointerdown", div_1, function(...$$args) {
        $.get(event_handler_1)?.apply(this, $$args);
      });
      $.append($$anchor2, div_1);
    };
    $.if(node_2, ($$render) => {
      if ($.get(resizable)) $$render(consequent_1);
    });
  }
  $.append($$anchor, fragment);
  $.pop();
}
$.delegate(["pointerdown"]);
var root$3 = $.from_html(`<!> <!>`, 1);
function Auxiliary($$anchor, $$props) {
  $.push($$props, true);
  let mainState = getContext("state");
  new AuxState(mainState);
  let interaction = $.derived(() => mainState.interaction), pointer = $.derived(() => mainState.options.pointer);
  $.get(interaction).resizer = Resizer;
  var fragment = root$3();
  var node = $.first_child(fragment);
  $.bind_this(Action(node, {}), ($$value) => $.get(interaction).action = $$value, () => $.get(interaction)?.action);
  var node_1 = $.sibling(node, 2);
  {
    var consequent = ($$anchor2) => {
      $.bind_this(Pointer($$anchor2, {}), ($$value) => $.get(interaction).pointer = $$value, () => $.get(interaction)?.pointer);
    };
    $.if(node_1, ($$render) => {
      if ($.get(pointer)) $$render(consequent);
    });
  }
  $.append($$anchor, fragment);
  $.pop();
}
const index$4 = {
  createOptions(options) {
    assign(options, {
      dateClick: void 0,
      dragConstraint: void 0,
      dragScroll: true,
      editable: false,
      eventDragMinDistance: 5,
      eventDragStart: void 0,
      eventDragStop: void 0,
      eventDrop: void 0,
      eventDurationEditable: true,
      eventLongPressDelay: void 0,
      eventResizableFromStart: false,
      eventResizeStart: void 0,
      eventResizeStop: void 0,
      eventResize: void 0,
      eventStartEditable: true,
      longPressDelay: 1e3,
      pointer: false,
      resizeConstraint: void 0,
      select: void 0,
      selectBackgroundColor: void 0,
      // ec option
      selectConstraint: void 0,
      selectLongPressDelay: void 0,
      selectMinDistance: 5,
      snapDuration: void 0,
      unselect: void 0,
      unselectAuto: true,
      unselectCancel: ""
    });
    assign(options.theme, {
      draggable: "ec-draggable",
      ghost: "ec-ghost",
      preview: "ec-preview",
      pointer: "ec-pointer",
      resizer: "ec-resizer",
      start: "ec-start",
      dragging: "ec-dragging",
      resizingY: "ec-resizing-y",
      resizingX: "ec-resizing-x",
      selecting: "ec-selecting"
    });
  },
  initState(mainState) {
    mainState.auxComponents.push(Auxiliary);
  }
};
let ViewState$3 = class ViewState2 {
  #intlListDay;
  get intlListDay() {
    return $.get(this.#intlListDay);
  }
  set intlListDay(value) {
    $.set(this.#intlListDay, value);
  }
  #intlListDaySide;
  get intlListDaySide() {
    return $.get(this.#intlListDaySide);
  }
  set intlListDaySide(value) {
    $.set(this.#intlListDaySide, value);
  }
  constructor(mainState) {
    this.#intlListDay = $.derived(intl(mainState, "listDayFormat"));
    this.#intlListDaySide = $.derived(intl(mainState, "listDaySideFormat"));
  }
};
var root_1$7 = $.from_html(`<div></div> <!>`, 1);
function Event$2($$anchor, $$props) {
  $.push($$props, true);
  let $$d = $.derived(() => getContext("state")), interaction = $.derived(() => $.get($$d).interaction), theme = $.derived(() => $.get($$d).options.theme);
  let styles = $.derived(() => (style) => {
    delete style["background-color"];
    delete style["color"];
    return style;
  });
  {
    const body = ($$anchor2, defaultBody = $.noop, bgColor = $.noop, txtColor = $.noop) => {
      var fragment_1 = root_1$7();
      var div = $.first_child(fragment_1);
      let styles_1;
      var node = $.sibling(div, 2);
      $.snippet(node, defaultBody);
      $.template_effect(() => {
        $.set_class(div, 1, $.get(theme).eventTag);
        styles_1 = $.set_style(div, "", styles_1, { "background-color": bgColor() });
      });
      $.append($$anchor2, fragment_1);
    };
    let $0 = $.derived(() => $.get(interaction).action?.noAction);
    BaseEvent($$anchor, {
      get chunk() {
        return $$props.chunk;
      },
      get styles() {
        return $.get(styles);
      },
      get onpointerdown() {
        return $.get($0);
      },
      body,
      $$slots: { body: true }
    });
  }
  $.pop();
}
var root_2$1 = $.from_html(`<h4><time></time> <time></time></h4> <!>`, 1);
function Day$2($$anchor, $$props) {
  $.push($$props, true);
  let $$d = $.derived(() => getContext("state")), filteredEvents2 = $.derived(() => $.get($$d).filteredEvents), highlightedDates = $.derived(() => $.get($$d).options.highlightedDates), theme = $.derived(() => $.get($$d).options.theme), validRange = $.derived(() => $.get($$d).options.validRange);
  let $$d_1 = $.derived(() => getContext("view-state")), intlListDay = $.derived(() => $.get($$d_1).intlListDay), intlListDaySide = $.derived(() => $.get($$d_1).intlListDaySide);
  let highlight = $.derived(() => $.get(highlightedDates).some((d) => datesEqual(d, $$props.date)));
  let disabled = $.derived(() => outsideRange($$props.date, $.get(validRange)));
  let datetime = $.derived(() => toISOString($$props.date, 10));
  let chunks = $.derived(() => {
    let chunks2 = [];
    if (!$.get(disabled)) {
      let start = $$props.date;
      let end = addDay(cloneDate($$props.date));
      for (let event of $.get(filteredEvents2)) {
        if (!bgEvent(event.display) && eventIntersects(event, start, end)) {
          let chunk = createEventChunk(event, start, end);
          chunks2.push(chunk);
        }
      }
    }
    return chunks2;
  });
  var fragment = $.comment();
  var node = $.first_child(fragment);
  {
    var consequent = ($$anchor2) => {
      BaseDay($$anchor2, {
        get date() {
          return $$props.date;
        },
        allDay: true,
        role: "listitem",
        get disabled() {
          return $.get(disabled);
        },
        get highlight() {
          return $.get(highlight);
        },
        children: ($$anchor3, $$slotProps) => {
          var fragment_2 = root_2$1();
          var h4 = $.first_child(fragment_2);
          var time = $.child(h4);
          $.attach(time, () => contentFrom($.get(intlListDay).format($$props.date)));
          var time_1 = $.sibling(time, 2);
          $.attach(time_1, () => contentFrom($.get(intlListDaySide).format($$props.date)));
          $.reset(h4);
          var node_1 = $.sibling(h4, 2);
          $.each(node_1, 17, () => $.get(chunks), (chunk) => chunk.event, ($$anchor4, chunk) => {
            Event$2($$anchor4, {
              get chunk() {
                return $.get(chunk);
              }
            });
          });
          $.template_effect(() => {
            $.set_class(h4, 1, $.get(theme).dayHead);
            $.set_attribute(time, "datetime", $.get(datetime));
            $.set_class(time_1, 1, $.get(theme).daySide);
            $.set_attribute(time_1, "datetime", $.get(datetime));
          });
          $.append($$anchor3, fragment_2);
        },
        $$slots: { default: true }
      });
    };
    $.if(node, ($$render) => {
      if ($.get(chunks).length) $$render(consequent);
    });
  }
  $.append($$anchor, fragment);
  $.pop();
}
var root_1$6 = $.from_html(`<div></div>`);
var root$2 = $.from_html(`<section><!></section>`);
function View$2($$anchor, $$props) {
  $.push($$props, true);
  let mainState = getContext("state");
  let viewState = new ViewState$3(mainState);
  setContext("view-state", viewState);
  let filteredEvents2 = $.derived(() => mainState.filteredEvents), view2 = $.derived(() => mainState.view), viewDates2 = $.derived(() => mainState.viewDates), noEventsClick = $.derived(() => mainState.options.noEventsClick), noEventsContent = $.derived(() => mainState.options.noEventsContent), theme = $.derived(() => mainState.options.theme);
  let noEvents = $.derived(() => {
    let noEvents2 = true;
    if (!empty($.get(viewDates2))) {
      let start = $.get(viewDates2)[0];
      let end = addDay(cloneDate($.get(viewDates2).at(-1)));
      for (let event of $.get(filteredEvents2)) {
        if (!bgEvent(event.display) && event.start < end && event.end > start) {
          noEvents2 = false;
          break;
        }
      }
    }
    return noEvents2;
  });
  let content = $.derived(() => isFunction($.get(noEventsContent)) ? $.get(noEventsContent)() : $.get(noEventsContent));
  function onclick(jsEvent) {
    if (isFunction($.get(noEventsClick))) {
      $.get(noEventsClick)({ jsEvent, view: toViewWithLocalDates($.get(view2)) });
    }
  }
  var section = root$2();
  var node = $.child(section);
  {
    var consequent = ($$anchor2) => {
      var div = root_1$6();
      $.attach(div, () => contentFrom($.get(content)));
      $.template_effect(() => $.set_class(div, 1, $.get(theme).noEvents));
      $.delegated("click", div, onclick);
      $.append($$anchor2, div);
    };
    var alternate = ($$anchor2) => {
      var fragment = $.comment();
      var node_1 = $.first_child(fragment);
      $.each(node_1, 17, () => $.get(viewDates2), $.index, ($$anchor3, date) => {
        Day$2($$anchor3, {
          get date() {
            return $.get(date);
          }
        });
      });
      $.append($$anchor2, fragment);
    };
    $.if(node, ($$render) => {
      if ($.get(noEvents)) $$render(consequent);
      else $$render(alternate, false);
    });
  }
  $.reset(section);
  $.bind_this(section, ($$value) => mainState.mainEl = $$value, () => mainState?.mainEl);
  $.template_effect(() => $.set_class(section, 1, $.get(theme).main));
  $.append($$anchor, section);
  $.pop();
}
$.delegate(["click"]);
const index$3 = {
  createOptions(options) {
    assign(options, {
      listDayFormat: { weekday: "long" },
      listDaySideFormat: { year: "numeric", month: "long", day: "numeric" },
      noEventsClick: void 0,
      // ec option
      noEventsContent: "No events",
      // Common options
      view: "listWeek"
    });
    assign(options.buttonText, {
      listDay: "list",
      listWeek: "list",
      listMonth: "list",
      listYear: "list"
    });
    assign(options.theme, {
      daySide: "ec-day-side",
      eventTag: "ec-event-tag",
      noEvents: "ec-no-events"
    });
    assign(options.views, {
      listDay: {
        buttonText: btnTextDay,
        component: initViewComponent$3,
        duration: { days: 1 },
        theme: themeView("ec-list ec-day-view")
      },
      listWeek: {
        buttonText: btnTextWeek,
        component: initViewComponent$3,
        duration: { weeks: 1 },
        theme: themeView("ec-list ec-week-view")
      },
      listMonth: {
        buttonText: btnTextMonth,
        component: initViewComponent$3,
        duration: { months: 1 },
        theme: themeView("ec-list ec-month-view")
      },
      listYear: {
        buttonText: btnTextYear,
        component: initViewComponent$3,
        duration: { years: 1 },
        theme: themeView("ec-list ec-year-view")
      }
    });
  }
};
function initViewComponent$3(mainState) {
  mainState.features = ["list"];
  return View$2;
}
function createChunks$1(event, days, withId = true) {
  let chunks = [];
  for (let { gridColumn, gridRow, resource, start, end, disabled } of days) {
    if (!disabled && eventIntersects(event, start, end, resource)) {
      let chunk = createEventChunk(event, start, end);
      assign(chunk, {
        gridColumn,
        gridRow,
        resource,
        top: (chunk.start - start) / 1e3,
        height: (chunk.end - chunk.start) / 1e3,
        maxHeight: (end - chunk.start) / 1e3
      });
      if (withId) {
        assignChunkId(chunk);
      }
      chunks.push(chunk);
    }
  }
  return chunks;
}
function groupChunks(chunks) {
  let groups = {};
  for (let chunk of chunks) {
    let { gridColumn } = chunk;
    let group = groups[gridColumn];
    let column = 0;
    if (group && chunk.start < group.end) {
      for (; column < group.columns.length; ++column) {
        if (group.columns[column].at(-1).end <= chunk.start) {
          break;
        }
      }
      if (chunk.end > group.end) {
        group.end = chunk.end;
      }
    } else {
      group = {
        columns: [],
        end: chunk.end
      };
    }
    if (group.columns.length < column + 1) {
      group.columns.push([]);
    }
    group.columns[column].push(chunk);
    groups[gridColumn] = group;
    chunk.group = group;
    chunk.groupColumn = column;
  }
}
function createAllDayContent(allDayContent) {
  let text = "all-day";
  let content;
  if (allDayContent) {
    content = isFunction(allDayContent) ? allDayContent({ text }) : allDayContent;
    if (typeof content === "string") {
      content = { html: content };
    }
  } else {
    content = {
      html: text
    };
  }
  return content;
}
function setExtensions(mainState) {
  mainState.extensions.activeRange = (start, end) => {
    let { options: { slotMaxTime } } = mainState;
    if (slotMaxTime.days || slotMaxTime.seconds > DAY_IN_SECONDS) {
      addDuration(subtractDay(end), slotMaxTime);
      let start2 = subtractDay(cloneDate(end));
      if (start2 < start) {
        start = start2;
      }
    }
    return { start, end };
  };
}
function createTRROptions(options) {
  if (!("scrollTime" in options)) {
    assign(options, {
      columnWidth: void 0,
      // ec option
      flexibleSlotTimeLimits: false,
      // ec option
      nowIndicator: false,
      scrollTime: "06:00:00",
      slotDuration: "00:30:00",
      slotHeight: 24,
      // ec option
      slotLabelInterval: void 0,
      slotLabelFormat: {
        hour: "numeric",
        minute: "2-digit"
      },
      slotMaxTime: "24:00:00",
      slotMinTime: "00:00:00",
      snapDuration: void 0
    });
    assign(options.theme, {
      nowIndicator: "ec-now-indicator",
      sidebar: "ec-sidebar",
      slot: "ec-slot"
    });
  }
}
function createTROptions(options) {
  if (!("allDaySlot" in options)) {
    assign(options, {
      allDayContent: void 0,
      allDaySlot: true,
      slotEventOverlap: true
    });
    assign(options.theme, {
      allDay: "ec-all-day"
    });
  }
}
function createTRRParsers(parsers) {
  if (!("scrollTime" in parsers)) {
    assign(parsers, {
      scrollTime: createDuration,
      slotDuration: createDuration,
      slotLabelInterval: (input) => input !== void 0 ? createDuration(input) : void 0,
      slotMaxTime: createDuration,
      slotMinTime: createDuration,
      snapDuration: (input) => input !== void 0 ? createDuration(input) : void 0
    });
  }
}
function createRROptions(options) {
  if (!("resourceLabelContent" in options)) {
    options.filterResourcesWithEvents = false;
    options.resourceLabelContent = void 0;
    options.resourceLabelDidMount = void 0;
  }
}
function grid$2(mainState, viewState) {
  return () => {
    let { viewDates: viewDates2, options: { highlightedDates, validRange } } = mainState;
    let { slotTimeLimits: slotTimeLimits2 } = viewState;
    let days = [];
    untrack(() => {
      let gridColumn = 1;
      for (let date of viewDates2) {
        days.push({
          gridColumn,
          gridRow: 1,
          resource: void 0,
          start: addDuration(cloneDate(date), slotTimeLimits2.min),
          end: addDuration(cloneDate(date), slotTimeLimits2.max),
          dayStart: date,
          dayEnd: addDay(cloneDate(date)),
          disabled: outsideRange(date, validRange),
          highlight: highlightedDates.some((d) => datesEqual(d, date))
        });
        ++gridColumn;
      }
    });
    return [days];
  };
}
function eventChunks$1(mainState, viewState) {
  return () => {
    let { filteredEvents: filteredEvents2 } = mainState;
    let { grid: grid2 } = viewState;
    let chunks = [];
    let bgChunks = [];
    let allDayChunks = [];
    let allDayBgChunks = [];
    untrack(() => {
      for (let event of filteredEvents2) {
        for (let days of grid2) {
          if (bgEvent(event.display)) {
            bgChunks = bgChunks.concat(createChunks$1(event, days));
            if (event.allDay) {
              allDayBgChunks = allDayBgChunks.concat(createAllDayChunks(event, days));
            }
          } else {
            if (event.allDay) {
              allDayChunks = allDayChunks.concat(createAllDayChunks(event, days));
            } else {
              chunks = chunks.concat(createChunks$1(event, days));
            }
          }
        }
      }
      groupChunks(chunks);
      prepareAllDayChunks(allDayChunks);
    });
    return { chunks, bgChunks, allDayChunks, allDayBgChunks };
  };
}
function iEventChunks$1(mainState, viewState) {
  return () => {
    let { iEvents } = mainState;
    let { grid: grid2 } = viewState;
    let iChunks = [];
    let allDayIChunks = [];
    for (let [, event] of iEvents) {
      if (!event) {
        continue;
      }
      untrack(() => {
        for (let days of grid2) {
          if (event.allDay) {
            allDayIChunks = allDayIChunks.concat(createAllDayChunks(event, days, false));
          } else {
            iChunks = iChunks.concat(createChunks$1(event, days, false));
          }
        }
      });
    }
    return { iChunks, allDayIChunks };
  };
}
function slotTimeLimits(mainState) {
  return () => {
    let { filteredEvents: filteredEvents2, viewDates: viewDates2, options: { flexibleSlotTimeLimits, slotMinTime, slotMaxTime } } = mainState;
    let limits;
    untrack(() => {
      limits = createSlotTimeLimits(slotMinTime, slotMaxTime, flexibleSlotTimeLimits, viewDates2, filteredEvents2);
    });
    return limits;
  };
}
function slotLabelPeriodicity(mainState) {
  return () => {
    let { options: { slotDuration, slotLabelInterval } } = mainState;
    let periodicity;
    untrack(() => {
      periodicity = slotLabelInterval === void 0 ? toSeconds(slotDuration) < 3600 ? 2 : 1 : ceil(toSeconds(slotLabelInterval) / toSeconds(slotDuration)) || 1;
    });
    return periodicity;
  };
}
function slots(mainState, viewState) {
  return () => {
    let { options: { slotDuration } } = mainState;
    let { intlSlotLabel, slotLabelPeriodicity: slotLabelPeriodicity2, slotTimeLimits: slotTimeLimits2 } = viewState;
    let slots2;
    untrack(() => {
      slots2 = createSlots(setMidnight(createDate()), slotDuration, slotLabelPeriodicity2, slotTimeLimits2, intlSlotLabel);
    });
    return slots2;
  };
}
function snap(mainState) {
  return () => {
    let { options: { slotDuration, snapDuration } } = mainState;
    snapDuration ??= slotDuration;
    return {
      duration: snapDuration,
      ratio: toSeconds(snapDuration) / toSeconds(slotDuration)
    };
  };
}
function TRRState() {
  return class {
    #intlSlotLabel;
    get intlSlotLabel() {
      return $.get(this.#intlSlotLabel);
    }
    set intlSlotLabel(value) {
      $.set(this.#intlSlotLabel, value);
    }
    #slotLabelPeriodicity;
    get slotLabelPeriodicity() {
      return $.get(this.#slotLabelPeriodicity);
    }
    set slotLabelPeriodicity(value) {
      $.set(this.#slotLabelPeriodicity, value);
    }
    #sidebarWidth;
    get sidebarWidth() {
      return $.get(this.#sidebarWidth);
    }
    set sidebarWidth(value) {
      $.set(this.#sidebarWidth, value, true);
    }
    #snap;
    get snap() {
      return $.get(this.#snap);
    }
    set snap(value) {
      $.set(this.#snap, value);
    }
    constructor(mainState) {
      this.#intlSlotLabel = $.derived(intl(mainState, "slotLabelFormat"));
      this.#slotLabelPeriodicity = $.derived(slotLabelPeriodicity(mainState));
      this.#sidebarWidth = $.state(0);
      this.#snap = $.derived(snap(mainState));
    }
  };
}
function TRState(Base) {
  return class extends Base {
    #slotTimeLimits;
    get slotTimeLimits() {
      return $.get(this.#slotTimeLimits);
    }
    set slotTimeLimits(value) {
      $.set(this.#slotTimeLimits, value);
    }
    #slots;
    get slots() {
      return $.get(this.#slots);
    }
    set slots(value) {
      $.set(this.#slots, value);
    }
    #chunks;
    get chunks() {
      return $.get(this.#chunks);
    }
    set chunks(value) {
      $.set(this.#chunks, value);
    }
    #bgChunks;
    get bgChunks() {
      return $.get(this.#bgChunks);
    }
    set bgChunks(value) {
      $.set(this.#bgChunks, value);
    }
    #allDayChunks;
    get allDayChunks() {
      return $.get(this.#allDayChunks);
    }
    set allDayChunks(value) {
      $.set(this.#allDayChunks, value);
    }
    #allDayBgChunks;
    get allDayBgChunks() {
      return $.get(this.#allDayBgChunks);
    }
    set allDayBgChunks(value) {
      $.set(this.#allDayBgChunks, value);
    }
    #iChunks;
    get iChunks() {
      return $.get(this.#iChunks);
    }
    set iChunks(value) {
      $.set(this.#iChunks, value);
    }
    #allDayIChunks;
    get allDayIChunks() {
      return $.get(this.#allDayIChunks);
    }
    set allDayIChunks(value) {
      $.set(this.#allDayIChunks, value);
    }
    constructor(mainState) {
      super(mainState);
      this.#slotTimeLimits = $.derived(
        slotTimeLimits(mainState)
        // flexible limits
      );
      this.#slots = $.derived(slots(mainState, this));
      let $$d = $.derived(eventChunks$1(mainState, this)), chunks = $.derived(() => $.get($$d).chunks), bgChunks = $.derived(() => $.get($$d).bgChunks), allDayChunks = $.derived(() => $.get($$d).allDayChunks), allDayBgChunks = $.derived(() => $.get($$d).allDayBgChunks);
      this.#chunks = $.derived(() => $.get(chunks));
      this.#bgChunks = $.derived(() => $.get(bgChunks));
      this.#allDayChunks = $.derived(() => $.get(allDayChunks));
      this.#allDayBgChunks = $.derived(() => $.get(allDayBgChunks));
      let $$d_1 = $.derived(iEventChunks$1(mainState, this)), iChunks = $.derived(() => $.get($$d_1).iChunks), allDayIChunks = $.derived(() => $.get($$d_1).allDayIChunks);
      this.#iChunks = $.derived(() => $.get(iChunks));
      this.#allDayIChunks = $.derived(() => $.get(allDayIChunks));
    }
  };
}
let ViewState$2 = class ViewState3 extends TRState(TRRState()) {
  #grid;
  get grid() {
    return $.get(this.#grid);
  }
  set grid(value) {
    $.set(this.#grid, value);
  }
  constructor(mainState) {
    super(mainState);
    this.#grid = $.derived(grid$2(mainState, this));
  }
};
function viewResources(mainState) {
  return () => {
    let {
      activeRange: activeRange2,
      filteredEvents: filteredEvents2,
      resources,
      options: { filterResourcesWithEvents },
      extensions: { viewResources: viewResources2 }
    } = mainState;
    let result = viewResources2 ? viewResources2(resources) : resources;
    untrack(() => {
      if (filterResourcesWithEvents) {
        result = resources.filter(
          (resource) => filteredEvents2.some(
            (event) => !bgEvent(event.display) && eventIntersects(event, activeRange2.start, activeRange2.end, resource)
          )
        );
      }
      if (!result.length) {
        result = createResources([{}]);
      }
    });
    return result;
  };
}
function grid$1(mainState, viewState) {
  return () => {
    let { viewDates: viewDates2, options: { datesAboveResources, highlightedDates, validRange } } = mainState;
    let { slotTimeLimits: slotTimeLimits2, viewResources: viewResources2 } = viewState;
    let grid2 = [];
    untrack(() => {
      let gridColumn = 1;
      let loop = datesAboveResources ? [viewDates2, viewResources2] : [viewResources2, viewDates2];
      for (let item0 of loop[0]) {
        let days = [];
        for (let item1 of loop[1]) {
          let date = datesAboveResources ? item0 : item1;
          let resource = datesAboveResources ? item1 : item0;
          days.push({
            gridColumn,
            gridRow: 1,
            resource,
            start: addDuration(cloneDate(date), slotTimeLimits2.min),
            end: addDuration(cloneDate(date), slotTimeLimits2.max),
            dayStart: date,
            dayEnd: addDay(cloneDate(date)),
            disabled: outsideRange(date, validRange),
            highlight: highlightedDates.some((d) => datesEqual(d, date))
          });
          ++gridColumn;
        }
        grid2.push(days);
      }
    });
    return grid2;
  };
}
function RRState(Base) {
  return class extends Base {
    #viewResources;
    get viewResources() {
      return $.get(this.#viewResources);
    }
    set viewResources(value) {
      $.set(this.#viewResources, value);
    }
    constructor(mainState) {
      super(mainState);
      this.#viewResources = $.derived(viewResources(mainState));
    }
  };
}
let ViewState$1 = class ViewState4 extends RRState(TRState(TRRState())) {
  #grid;
  get grid() {
    return $.get(this.#grid);
  }
  set grid(value) {
    $.set(this.#grid, value);
  }
  constructor(mainState) {
    super(mainState);
    this.#grid = $.derived(grid$1(mainState, this));
  }
};
var root$1 = $.from_html(`<span></span>`);
function Label($$anchor, $$props) {
  $.push($$props, true);
  let date = $.prop($$props, "date", 3, void 0), setLabel = $.prop($$props, "setLabel", 3, void 0);
  let $$d = $.derived(() => getContext("state")), intlDayHeaderAL = $.derived(() => $.get($$d).intlDayHeaderAL), resourceLabelContent = $.derived(() => $.get($$d).options.resourceLabelContent), resourceLabelDidMount = $.derived(() => $.get($$d).options.resourceLabelDidMount);
  let el = $.state(void 0);
  let content = $.derived(() => {
    if ($.get(resourceLabelContent)) {
      return isFunction($.get(resourceLabelContent)) ? $.get(resourceLabelContent)({
        resource: $$props.resource,
        date: date() ? toLocalDate(date()) : void 0
      }) : $.get(resourceLabelContent);
    } else {
      return $$props.resource.title;
    }
  });
  let ariaLabel = $.state(void 0);
  $.user_effect(() => {
    $.get(content);
    untrack(() => {
      if (date()) {
        $.set(ariaLabel, $.get(intlDayHeaderAL).format(date()) + ", " + $.get(el).innerText);
      } else if (setLabel()) {
        $.set(ariaLabel, void 0);
        setLabel()($.get(el).innerText);
      }
    });
  });
  onMount(() => {
    if (isFunction($.get(resourceLabelDidMount))) {
      $.get(resourceLabelDidMount)({
        resource: $$props.resource,
        date: date() ? toLocalDate(date()) : void 0,
        el: $.get(el)
      });
    }
  });
  var span = root$1();
  $.bind_this(span, ($$value) => $.set(el, $$value), () => $.get(el));
  $.attach(span, () => contentFrom($.get(content)));
  $.template_effect(() => $.set_attribute(span, "aria-label", $.get(ariaLabel)));
  $.append($$anchor, span);
  $.pop();
}
function Day$1($$anchor, $$props) {
  $.push($$props, true);
  let allDay = $.prop($$props, "allDay", 3, false);
  let $$d = $.derived(() => getContext("state")), slotHeight = $.derived(() => $.get($$d).options.slotHeight);
  let $$d_1 = $.derived(() => getContext("view-state")), snap2 = $.derived(() => $.get($$d_1).snap);
  let date = $.derived(() => $$props.day.dayStart), start = $.derived(() => $$props.day.start), resource = $.derived(() => $$props.day.resource), disabled = $.derived(() => $$props.day.disabled), highlight = $.derived(() => $$props.day.highlight);
  let el = $.state(void 0);
  function dateFromPoint(x, y) {
    if (allDay()) {
      return $.get(date);
    } else {
      let dayRect = rect($.get(el));
      let scaleY = dayRect.height / $.get(el).offsetHeight;
      return addDuration(cloneDate($.get(start)), $.get(snap2).duration, floor((y - dayRect.top) / ($.get(slotHeight) * $.get(snap2).ratio * scaleY)));
    }
  }
  BaseDay($$anchor, {
    get date() {
      return $.get(date);
    },
    get allDay() {
      return allDay();
    },
    get resource() {
      return $.get(resource);
    },
    dateFromPoint,
    get disabled() {
      return $.get(disabled);
    },
    get highlight() {
      return $.get(highlight);
    },
    get noIeb() {
      return $$props.noIeb;
    },
    get noBeb() {
      return $$props.noBeb;
    },
    get el() {
      return $.get(el);
    },
    set el($$value) {
      $.set(el, $$value, true);
    }
  });
  $.pop();
}
function Event$1($$anchor, $$props) {
  $.push($$props, true);
  let $$d = $.derived(() => getContext("state")), slotEventOverlap = $.derived(() => $.get($$d).options.slotEventOverlap), slotDuration = $.derived(() => $.get($$d).options.slotDuration), slotHeight = $.derived(() => $.get($$d).options.slotHeight);
  let styles = $.derived(() => (style) => {
    let step = toSeconds($.get(slotDuration));
    let top = $$props.chunk.top / step * $.get(slotHeight);
    let height2 = $$props.chunk.height / step * $.get(slotHeight) || $.get(slotHeight);
    let maxHeight = $$props.chunk.maxHeight / step * $.get(slotHeight);
    style["grid-column"] = $$props.chunk.gridColumn;
    style["inset-block-start"] = `${top}px`;
    style["min-block-size"] = `${height2}px`;
    style["block-size"] = `${height2}px`;
    style["max-block-size"] = `${maxHeight}px`;
    let maxWidth = "100% - var(--ec-event-col-gap)";
    if ($$props.chunk.group) {
      let groupColumns = $$props.chunk.group.columns.length;
      style["z-index"] = `${$$props.chunk.groupColumn + 1}`;
      style["inset-inline-start"] = `calc((${maxWidth}) / ${groupColumns} * ${$$props.chunk.groupColumn})`;
      style["inline-size"] = `calc((${maxWidth}) / ${groupColumns} * ${$.get(slotEventOverlap) ? 0.5 * (1 + groupColumns - $$props.chunk.groupColumn) : 1})`;
    }
    return style;
  });
  InteractableEvent($$anchor, {
    get chunk() {
      return $$props.chunk;
    },
    get styles() {
      return $.get(styles);
    },
    axis: "y"
  });
  $.pop();
}
function AllDayEvent($$anchor, $$props) {
  $.push($$props, true);
  let el = $.state(void 0);
  let margin = $.state(0);
  let event = $.derived(() => $$props.chunk.event);
  let styles = $.derived(() => (style) => {
    style["grid-column"] = `${$$props.chunk.gridColumn} / span ${$$props.chunk.dates.length}`;
    if ($.get(margin) || $.get(event)._margin) {
      style["margin-block-start"] = `${$.get(event)._margin ?? $.get(margin)}px`;
    }
    return style;
  });
  function reposition() {
    $.set(margin, repositionEvent$1($$props.chunk, height($.get(el))), true);
  }
  var $$exports = { reposition };
  InteractableEvent($$anchor, {
    get chunk() {
      return $$props.chunk;
    },
    get styles() {
      return $.get(styles);
    },
    axis: "x",
    get forceMargin() {
      return $.get(margin);
    },
    get el() {
      return $.get(el);
    },
    set el($$value) {
      $.set(el, $$value, true);
    }
  });
  return $.pop($$exports);
}
var root_1$5 = $.from_html(`<div></div>`);
function NowIndicator$1($$anchor, $$props) {
  $.push($$props, true);
  let span = $.prop($$props, "span", 3, 1);
  let $$d = $.derived(() => getContext("state")), mainEl = $.derived(() => $.get($$d).mainEl), now = $.derived(() => $.get($$d).now), today = $.derived(() => $.get($$d).today), slotDuration = $.derived(() => $.get($$d).options.slotDuration), slotHeight = $.derived(() => $.get($$d).options.slotHeight), theme = $.derived(() => $.get($$d).options.theme);
  let $$d_1 = $.derived(() => getContext("view-state")), sidebarWidth = $.derived(() => $.get($$d_1).sidebarWidth);
  let $$d_2 = $.derived(() => {
    for (let day of $$props.days) {
      if (datesEqual(day.dayStart, $.get(today))) {
        return day;
      }
    }
    return {};
  }), gridColumn = $.derived(() => $.get($$d_2).gridColumn), start = $.derived(() => $.get($$d_2).start), end = $.derived(() => $.get($$d_2).end);
  let top = $.derived(() => {
    if ($.get(now) < $.get(start) || $.get(now) > $.get(end)) {
      return null;
    }
    let step = toSeconds($.get(slotDuration));
    return ($.get(now) - $.get(start)) / 1e3 / step * $.get(slotHeight);
  });
  let observerOptions = $.derived(() => ({
    root: $.get(mainEl),
    rootMargin: isRtl() ? `0px -${$.get(sidebarWidth) + 5.5}px 0px 0px` : `0px 0px 0px -${$.get(sidebarWidth) + 5.5}px`,
    threshold: 0
  }));
  function onIntersect(el, entry) {
    el.classList.toggle($.get(theme).hidden, !entry.isIntersecting);
  }
  var fragment = $.comment();
  var node = $.first_child(fragment);
  {
    var consequent = ($$anchor2) => {
      var div = root_1$5();
      let styles;
      $.attach(div, () => intersectionObserver(onIntersect, $.get(observerOptions)));
      $.template_effect(() => {
        $.set_class(div, 1, $.get(theme).nowIndicator);
        styles = $.set_style(div, "", styles, {
          "grid-column": `${$.get(gridColumn) + 1} / span ${span() ?? ""}`,
          "inset-block-start": `${$.get(top) ?? ""}px`
        });
      });
      $.append($$anchor2, div);
    };
    $.if(node, ($$render) => {
      if ($.get(gridColumn) && $.get(top) !== null) $$render(consequent);
    });
  }
  $.append($$anchor, fragment);
  $.pop();
}
var root_6 = $.from_html(`<div><aside></aside> <div role="row"></div> <div><!> <!> <!></div></div>`);
var root_12 = $.from_html(`<div><time></time></div>`);
var root_1$4 = $.from_html(`<section><header><aside></aside> <div role="row"><!></div> <!></header> <div role="rowgroup"><aside aria-hidden="true"></aside> <div role="row"></div> <div><!> <!> <!></div></div> <!></section>`);
function View$1($$anchor, $$props) {
  $.push($$props, true);
  let viewState = $.prop($$props, "viewState", 7);
  let mainState = getContext("state");
  if (!viewState()) {
    viewState(new ViewState$2(mainState));
  }
  setContext("view-state", viewState());
  let mainEl = $.derived(() => mainState.mainEl), viewDates2 = $.derived(() => mainState.viewDates), allDayContent = $.derived(() => mainState.options.allDayContent), allDaySlot = $.derived(() => mainState.options.allDaySlot), columnWidth = $.derived(() => mainState.options.columnWidth), showNowIndicator = $.derived(() => mainState.options.nowIndicator), scrollTime = $.derived(() => mainState.options.scrollTime), slotHeight = $.derived(() => mainState.options.slotHeight), slotDuration = $.derived(() => mainState.options.slotDuration), theme = $.derived(() => mainState.options.theme);
  let allDayChunks = $.derived(() => viewState().allDayChunks), allDayBgChunks = $.derived(() => viewState().allDayBgChunks), allDayIChunks = $.derived(() => viewState().allDayIChunks), bgChunks = $.derived(() => viewState().bgChunks), chunks = $.derived(() => viewState().chunks), iChunks = $.derived(() => viewState().iChunks), grid2 = $.derived(() => viewState().grid), sidebarWidth = $.derived(() => viewState().sidebarWidth), slots2 = $.derived(() => viewState().slots), slotLabelPeriodicity2 = $.derived(() => viewState().slotLabelPeriodicity), slotTimeLimits2 = $.derived(() => viewState().slotTimeLimits);
  let headerHeight = $.state(0);
  let allDayText = $.derived(() => createAllDayContent($.get(allDayContent)));
  $.user_effect(() => {
    $.get(scrollTime);
    if (!empty($.get(viewDates2))) {
      tick().then(scrollToTime);
    }
  });
  function scrollToTime() {
    $.get(mainEl).scrollTop = ((toSeconds($.get(scrollTime)) - toSeconds($.get(slotTimeLimits2).min)) / toSeconds($.get(slotDuration)) - 0.5) * $.get(slotHeight);
  }
  let refs = [];
  function reposition() {
    runReposition(refs, $.get(allDayChunks));
  }
  $.user_effect(reposition);
  var fragment = $.comment();
  var node = $.first_child(fragment);
  {
    var consequent_4 = ($$anchor2) => {
      var section = root_1$4();
      let styles;
      var header_1 = $.child(section);
      var aside = $.child(header_1);
      var div = $.sibling(aside, 2);
      var node_1 = $.child(div);
      {
        var consequent = ($$anchor3) => {
          var fragment_1 = $.comment();
          var node_2 = $.first_child(fragment_1);
          $.snippet(node_2, () => $$props.header);
          $.append($$anchor3, fragment_1);
        };
        var alternate = ($$anchor3) => {
          var fragment_2 = $.comment();
          var node_3 = $.first_child(fragment_2);
          $.each(node_3, 17, () => $.get(grid2)[0], $.index, ($$anchor4, $$item, i) => {
            let date = () => $.get($$item).dayStart;
            let disabled = () => $.get($$item).disabled;
            let highlight = () => $.get($$item).highlight;
            ColHead($$anchor4, {
              get date() {
                return date();
              },
              colIndex: 1 + i,
              get disabled() {
                return disabled();
              },
              get highlight() {
                return highlight();
              },
              children: ($$anchor5, $$slotProps) => {
                DayHeader($$anchor5, {
                  get date() {
                    return date();
                  }
                });
              },
              $$slots: { default: true }
            });
          });
          $.append($$anchor3, fragment_2);
        };
        $.if(node_1, ($$render) => {
          if ($$props.header) $$render(consequent);
          else $$render(alternate, false);
        });
      }
      $.reset(div);
      var node_4 = $.sibling(div, 2);
      {
        var consequent_1 = ($$anchor3) => {
          var div_1 = root_6();
          var aside_1 = $.child(div_1);
          $.attach(aside_1, () => contentFrom($.get(allDayText)));
          var div_2 = $.sibling(aside_1, 2);
          $.each(div_2, 21, () => $.get(grid2), $.index, ($$anchor4, days, i) => {
            var fragment_5 = $.comment();
            var node_5 = $.first_child(fragment_5);
            $.each(node_5, 17, () => $.get(days), $.index, ($$anchor5, day, j) => {
              {
                let $0 = $.derived(() => i + 1 === length($.get(grid2)) && j + 1 === length($.get(days)));
                Day$1($$anchor5, {
                  get day() {
                    return $.get(day);
                  },
                  allDay: true,
                  get noIeb() {
                    return $.get($0);
                  }
                });
              }
            });
            $.append($$anchor4, fragment_5);
          });
          $.reset(div_2);
          var div_3 = $.sibling(div_2, 2);
          var node_6 = $.child(div_3);
          $.each(node_6, 19, () => $.get(allDayChunks), (chunk) => chunk.id, ($$anchor4, chunk, i) => {
            $.bind_this(
              AllDayEvent($$anchor4, {
                get chunk() {
                  return $.get(chunk);
                }
              }),
              ($$value, i2) => refs[i2] = $$value,
              (i2) => refs?.[i2],
              () => [$.get(i)]
            );
          });
          var node_7 = $.sibling(node_6, 2);
          $.each(node_7, 17, () => $.get(allDayBgChunks), (chunk) => chunk.id, ($$anchor4, chunk) => {
            AllDayEvent($$anchor4, {
              get chunk() {
                return $.get(chunk);
              }
            });
          });
          var node_8 = $.sibling(node_7, 2);
          $.each(node_8, 17, () => $.get(allDayIChunks), $.index, ($$anchor4, chunk) => {
            AllDayEvent($$anchor4, {
              get chunk() {
                return $.get(chunk);
              }
            });
          });
          $.reset(div_3);
          $.reset(div_1);
          $.template_effect(() => {
            $.set_class(div_1, 1, $.get(theme).allDay);
            $.set_class(aside_1, 1, $.get(theme).sidebar);
            $.set_class(div_2, 1, $.get(theme).grid);
            $.set_class(div_3, 1, $.get(theme).events);
          });
          $.append($$anchor3, div_1);
        };
        $.if(node_4, ($$render) => {
          if ($.get(allDaySlot)) $$render(consequent_1);
        });
      }
      $.reset(header_1);
      var div_4 = $.sibling(header_1, 2);
      var aside_2 = $.child(div_4);
      $.each(aside_2, 21, () => $.get(slots2), $.index, ($$anchor3, slot, i) => {
        var div_5 = root_12();
        let styles_1;
        var time = $.child(div_5);
        $.attach(time, () => contentFrom($.get(slot)[1]));
        $.reset(div_5);
        $.template_effect(() => {
          $.set_class(div_5, 1, $.clsx([$.get(theme).slot, !i && $.get(theme).hidden]));
          styles_1 = $.set_style(div_5, "", styles_1, { "--ec-slot-label-periodicity": $.get(slot)[2] });
          $.set_attribute(time, "datetime", $.get(slot)[0]);
        });
        $.append($$anchor3, div_5);
      });
      $.reset(aside_2);
      var div_6 = $.sibling(aside_2, 2);
      $.each(div_6, 21, () => $.get(grid2), $.index, ($$anchor3, days, i) => {
        var fragment_10 = $.comment();
        var node_9 = $.first_child(fragment_10);
        $.each(node_9, 17, () => $.get(days), $.index, ($$anchor4, day, j) => {
          {
            let $0 = $.derived(() => i + 1 === length($.get(grid2)) && j + 1 === length($.get(days)));
            Day$1($$anchor4, {
              get day() {
                return $.get(day);
              },
              get noIeb() {
                return $.get($0);
              },
              noBeb: true
            });
          }
        });
        $.append($$anchor3, fragment_10);
      });
      $.reset(div_6);
      var div_7 = $.sibling(div_6, 2);
      var node_10 = $.child(div_7);
      $.each(node_10, 17, () => $.get(chunks), (chunk) => chunk.id, ($$anchor3, chunk) => {
        Event$1($$anchor3, {
          get chunk() {
            return $.get(chunk);
          }
        });
      });
      var node_11 = $.sibling(node_10, 2);
      $.each(node_11, 17, () => $.get(bgChunks), (chunk) => chunk.id, ($$anchor3, chunk) => {
        Event$1($$anchor3, {
          get chunk() {
            return $.get(chunk);
          }
        });
      });
      var node_12 = $.sibling(node_11, 2);
      $.each(node_12, 17, () => $.get(iChunks), $.index, ($$anchor3, chunk) => {
        Event$1($$anchor3, {
          get chunk() {
            return $.get(chunk);
          }
        });
      });
      $.reset(div_7);
      $.reset(div_4);
      var node_13 = $.sibling(div_4, 2);
      {
        var consequent_3 = ($$anchor3) => {
          var fragment_15 = $.comment();
          var node_14 = $.first_child(fragment_15);
          {
            var consequent_2 = ($$anchor4) => {
              var fragment_16 = $.comment();
              var node_15 = $.first_child(fragment_16);
              $.snippet(node_15, () => $$props.nowIndicator);
              $.append($$anchor4, fragment_16);
            };
            var alternate_1 = ($$anchor4) => {
              NowIndicator$1($$anchor4, {
                get days() {
                  return $.get(grid2)[0];
                }
              });
            };
            $.if(node_14, ($$render) => {
              if ($$props.nowIndicator) $$render(consequent_2);
              else $$render(alternate_1, false);
            });
          }
          $.append($$anchor3, fragment_15);
        };
        $.if(node_13, ($$render) => {
          if ($.get(showNowIndicator)) $$render(consequent_3);
        });
      }
      $.reset(section);
      $.bind_this(section, ($$value) => mainState.mainEl = $$value, () => mainState?.mainEl);
      $.attach(section, () => resizeObserver(reposition));
      $.template_effect(
        ($0) => {
          $.set_class(section, 1, $.get(theme).main);
          styles = $.set_style(section, "", styles, $0);
          $.set_class(header_1, 1, $.get(theme).header);
          $.set_class(aside, 1, $.get(theme).sidebar);
          $.set_class(div, 1, $.get(theme).grid);
          $.set_class(div_4, 1, $.get(theme).body);
          $.set_class(aside_2, 1, $.get(theme).sidebar);
          $.set_class(div_6, 1, $.get(theme).grid);
          $.set_class(div_7, 1, $.get(theme).events);
        },
        [
          () => ({
            "--ec-grid-cols": length($.get(grid2)) * length($.get(grid2)[0]),
            "--ec-col-group-span": length($.get(grid2)[0]),
            "--ec-col-width": $.get(columnWidth) ?? "minmax(0, 1fr)",
            "--ec-slot-label-periodicity": $.get(slotLabelPeriodicity2),
            "--ec-slot-height": `${$.get(slotHeight) ?? ""}px`,
            "--ec-header-height": `${$.get(headerHeight) ?? ""}px`,
            "--ec-sidebar-width": `${$.get(sidebarWidth) ?? ""}px`
          })
        ]
      );
      $.bind_element_size(aside, "offsetWidth", ($$value) => viewState().sidebarWidth = $$value);
      $.bind_element_size(header_1, "offsetHeight", ($$value) => $.set(headerHeight, $$value));
      $.append($$anchor2, section);
    };
    var d = $.derived(() => !empty($.get(grid2)) && !empty($.get(grid2)[0]));
    $.if(node, ($$render) => {
      if ($.get(d)) $$render(consequent_4);
    });
  }
  $.append($$anchor, fragment);
  $.pop();
}
var root_1$3 = $.from_html(`<!> <!>`, 1);
function View_1($$anchor, $$props) {
  $.push($$props, true);
  let mainState = getContext("state");
  let viewState = new ViewState$1(mainState);
  let today = $.derived(() => mainState.today), mainEl = $.derived(() => mainState.mainEl), viewDates2 = $.derived(() => mainState.viewDates), scrollTime = $.derived(() => mainState.options.scrollTime), datesAboveResources = $.derived(() => mainState.options.datesAboveResources), theme = $.derived(() => mainState.options.theme);
  let grid2 = $.derived(() => viewState.grid), sidebarWidth = $.derived(() => viewState.sidebarWidth);
  let resourceLabels = $.proxy([]);
  $.user_effect(() => {
    if ($.get(datesAboveResources)) {
      $.get(viewDates2);
      $.get(scrollTime);
      tick().then(scrollToTime);
    }
  });
  function scrollToTime() {
    if ($.get(today) >= $.get(viewDates2)[0] && $.get(today) <= $.get(viewDates2).at(-1)) {
      for (let days of $.get(grid2)) {
        let day = days[0];
        if (datesEqual(day.dayStart, $.get(today))) {
          $.get(mainEl).scrollLeft = ($.get(mainEl).scrollWidth - $.get(sidebarWidth)) / (length($.get(grid2)) * length(days)) * (day.gridColumn - 1) * (isRtl() ? -1 : 1);
          break;
        }
      }
    }
  }
  {
    const header = ($$anchor2) => {
      var fragment_1 = root_1$3();
      var node = $.first_child(fragment_1);
      $.each(node, 17, () => $.get(grid2), $.index, ($$anchor3, days, i) => {
        const computed_const = $.derived(() => {
          const { dayStart: date, resource, disabled, highlight } = $.get(days)[0];
          return { date, resource, disabled, highlight };
        });
        {
          let $0 = $.derived(() => length($.get(grid2)[0]) > 1 ? $.get(theme).colGroup : void 0);
          let $1 = $.derived(() => length($.get(days)));
          let $2 = $.derived(() => 1 + i * length($.get(days)));
          let $3 = $.derived(() => $.get(datesAboveResources) && $.get(computed_const).disabled);
          let $4 = $.derived(() => $.get(datesAboveResources) && $.get(computed_const).highlight);
          ColHead($$anchor3, {
            get date() {
              return $.get(computed_const).date;
            },
            get className() {
              return $.get($0);
            },
            get weekday() {
              return $.get(datesAboveResources);
            },
            get colSpan() {
              return $.get($1);
            },
            get colIndex() {
              return $.get($2);
            },
            get disabled() {
              return $.get($3);
            },
            get highlight() {
              return $.get($4);
            },
            children: ($$anchor4, $$slotProps) => {
              var fragment_3 = $.comment();
              var node_1 = $.first_child(fragment_3);
              {
                var consequent = ($$anchor5) => {
                  DayHeader($$anchor5, {
                    get date() {
                      return $.get(computed_const).date;
                    }
                  });
                };
                var alternate = ($$anchor5) => {
                  Label($$anchor5, {
                    get resource() {
                      return $.get(computed_const).resource;
                    },
                    setLabel: (label) => resourceLabels[i] = label + ", "
                  });
                };
                $.if(node_1, ($$render) => {
                  if ($.get(datesAboveResources)) $$render(consequent);
                  else $$render(alternate, false);
                });
              }
              $.append($$anchor4, fragment_3);
            },
            $$slots: { default: true }
          });
        }
      });
      var node_2 = $.sibling(node, 2);
      {
        var consequent_2 = ($$anchor3) => {
          var fragment_6 = $.comment();
          var node_3 = $.first_child(fragment_6);
          $.each(node_3, 17, () => $.get(grid2), $.index, ($$anchor4, days, i) => {
            var fragment_7 = $.comment();
            var node_4 = $.first_child(fragment_7);
            $.each(node_4, 17, () => $.get(days), $.index, ($$anchor5, day, j) => {
              const computed_const_1 = $.derived(() => {
                const { dayStart: date, resource, disabled, highlight } = $.get(day);
                return { date, resource, disabled, highlight };
              });
              {
                let $0 = $.derived(() => 1 + j + i * length($.get(days)));
                ColHead($$anchor5, {
                  get date() {
                    return $.get(computed_const_1).date;
                  },
                  get colIndex() {
                    return $.get($0);
                  },
                  get disabled() {
                    return $.get(computed_const_1).disabled;
                  },
                  get highlight() {
                    return $.get(computed_const_1).highlight;
                  },
                  children: ($$anchor6, $$slotProps) => {
                    var fragment_9 = $.comment();
                    var node_5 = $.first_child(fragment_9);
                    {
                      var consequent_1 = ($$anchor7) => {
                        Label($$anchor7, {
                          get resource() {
                            return $.get(computed_const_1).resource;
                          },
                          get date() {
                            return $.get(computed_const_1).date;
                          }
                        });
                      };
                      var alternate_1 = ($$anchor7) => {
                        DayHeader($$anchor7, {
                          get date() {
                            return $.get(computed_const_1).date;
                          },
                          get alPrefix() {
                            return resourceLabels[i];
                          }
                        });
                      };
                      $.if(node_5, ($$render) => {
                        if ($.get(datesAboveResources)) $$render(consequent_1);
                        else $$render(alternate_1, false);
                      });
                    }
                    $.append($$anchor6, fragment_9);
                  },
                  $$slots: { default: true }
                });
              }
            });
            $.append($$anchor4, fragment_7);
          });
          $.append($$anchor3, fragment_6);
        };
        var d = $.derived(() => length($.get(grid2)[0]) > 1);
        $.if(node_2, ($$render) => {
          if ($.get(d)) $$render(consequent_2);
        });
      }
      $.append($$anchor2, fragment_1);
    };
    const nowIndicator = ($$anchor2) => {
      var fragment_12 = $.comment();
      var node_6 = $.first_child(fragment_12);
      {
        var consequent_3 = ($$anchor3) => {
          {
            let $0 = $.derived(() => $.get(grid2).flat());
            let $1 = $.derived(() => length($.get(grid2)[0]));
            NowIndicator$1($$anchor3, {
              get days() {
                return $.get($0);
              },
              get span() {
                return $.get($1);
              }
            });
          }
        };
        var alternate_3 = ($$anchor3) => {
          var fragment_14 = $.comment();
          var node_7 = $.first_child(fragment_14);
          {
            var consequent_4 = ($$anchor4) => {
              var fragment_15 = $.comment();
              var node_8 = $.first_child(fragment_15);
              $.each(node_8, 17, () => $.get(grid2), $.index, ($$anchor5, days) => {
                NowIndicator$1($$anchor5, {
                  get days() {
                    return $.get(days);
                  }
                });
              });
              $.append($$anchor4, fragment_15);
            };
            var d_1 = $.derived(() => length($.get(grid2)[0]) > 1);
            var alternate_2 = ($$anchor4) => {
              {
                let $0 = $.derived(() => $.get(grid2).flat());
                let $1 = $.derived(() => length($.get(grid2)));
                NowIndicator$1($$anchor4, {
                  get days() {
                    return $.get($0);
                  },
                  get span() {
                    return $.get($1);
                  }
                });
              }
            };
            $.if(node_7, ($$render) => {
              if ($.get(d_1)) $$render(consequent_4);
              else $$render(alternate_2, false);
            });
          }
          $.append($$anchor3, fragment_14);
        };
        $.if(node_6, ($$render) => {
          if ($.get(datesAboveResources)) $$render(consequent_3);
          else $$render(alternate_3, false);
        });
      }
      $.append($$anchor2, fragment_12);
    };
    View$1($$anchor, {
      get viewState() {
        return viewState;
      },
      header,
      nowIndicator,
      $$slots: { header: true, nowIndicator: true }
    });
  }
  $.pop();
}
const index$2 = {
  createOptions(options) {
    createTROptions(options);
    createTRROptions(options);
    createRROptions(options);
    assign(options, {
      datesAboveResources: false,
      // Common options
      view: "resourceTimeGridWeek"
    });
    assign(options.buttonText, {
      resourceTimeGridDay: "resources",
      resourceTimeGridWeek: "resources"
    });
    assign(options.theme, {
      colGroup: "ec-col-group"
    });
    assign(options.views, {
      resourceTimeGridDay: {
        buttonText: btnTextDay,
        component: initViewComponent$2,
        dayHeaderFormat: { weekday: "long" },
        duration: { days: 1 },
        theme: themeView("ec-resource ec-time-grid ec-day-view")
      },
      resourceTimeGridWeek: {
        buttonText: btnTextWeek,
        component: initViewComponent$2,
        duration: { weeks: 1 },
        theme: themeView("ec-resource ec-time-grid ec-week-view")
      }
    });
  },
  createParsers(parsers) {
    createTRRParsers(parsers);
  }
};
function initViewComponent$2(mainState) {
  setExtensions(mainState);
  return View_1;
}
function createChunks(event, days, monthView2, withId = true) {
  let dates = [];
  let firstStart;
  let lastEnd;
  let gridColumn;
  let gridRow;
  let resource;
  let left;
  let width = 0;
  for (let { gridColumn: column, gridRow: row, resource: dayResource, dayStart, dayEnd, start, end, disabled } of days) {
    if (!disabled) {
      if (monthView2) {
        if (eventIntersects(event, dayStart, dayEnd, dayResource)) {
          if (!dates.length) {
            firstStart = dayStart;
            gridColumn = column;
            gridRow = row;
            resource = dayResource;
          }
          dates.push(dayStart);
          lastEnd = end;
        }
      } else {
        if (eventIntersects(event, start, end, dayResource)) {
          if (!dates.length) {
            firstStart = start;
            gridColumn = column;
            gridRow = row;
            resource = dayResource;
            left = max(event.start - start, 0) / 1e3;
          }
          dates.push(dayStart);
          lastEnd = end;
          width += (min(end, event.end) - max(start, event.start)) / 1e3;
        }
      }
    }
  }
  if (dates.length) {
    let chunk = createEventChunk(event, firstStart, lastEnd);
    assign(chunk, { gridColumn, gridRow, resource, dates, left, width });
    if (withId) {
      assignChunkId(chunk);
    }
    return [chunk];
  }
  return [];
}
function prepareChunks(chunks) {
  let dayChunks = {};
  for (let chunk of chunks) {
    let { gridColumn, gridRow } = chunk;
    for (let i = 0; i < chunk.dates.length; ++i) {
      let key2 = `${gridRow}_${gridColumn + i}`;
      if (dayChunks[key2]) {
        dayChunks[key2].push(chunk);
      } else {
        dayChunks[key2] = [chunk];
      }
    }
    let key = `${gridRow}_${gridColumn}`;
    chunk.day = dayChunks[key];
  }
}
function repositionEvent(chunk, height2, monthView2) {
  let top = 1;
  let bottom = top + height2;
  let dayChunks = chunk.day;
  dayChunks.sort((a, b) => (a.top ?? Number.POSITIVE_INFINITY) - (b.top ?? Number.POSITIVE_INFINITY));
  for (let dayChunk of dayChunks) {
    if (dayChunk === chunk || !("top" in dayChunk)) {
      continue;
    }
    if ((monthView2 || chunk.start < dayChunk.end && chunk.end > dayChunk.start) && top < dayChunk.bottom && bottom > dayChunk.top) {
      let offset = dayChunk.bottom - top + 1;
      top += offset;
      bottom += offset;
    }
  }
  assign(chunk, { top, bottom });
  return top;
}
function getSlotTimeLimits(dayTimeLimits2, date) {
  return dayTimeLimits2[date.getTime()] ?? { min: createDuration(0), max: createDuration("24:00:00") };
}
function grid(mainState, viewState) {
  return () => {
    let { viewDates: viewDates2, options: { highlightedDates, validRange } } = mainState;
    let { dayTimeLimits: dayTimeLimits2, viewResources: viewResources2 } = viewState;
    let grid2 = [];
    untrack(() => {
      let gridRow = 1;
      for (let resource of viewResources2) {
        let days = [];
        let gridColumn = 1;
        for (let date of viewDates2) {
          let slotTimeLimits2 = dayTimeLimits2[date.getTime()];
          days.push({
            gridColumn,
            gridRow,
            resource,
            start: addDuration(cloneDate(date), slotTimeLimits2.min),
            end: addDuration(cloneDate(date), slotTimeLimits2.max),
            dayStart: date,
            dayEnd: addDay(cloneDate(date)),
            disabled: outsideRange(date, validRange),
            highlight: highlightedDates.some((d) => datesEqual(d, date))
          });
          ++gridColumn;
        }
        grid2.push(days);
        ++gridRow;
      }
    });
    return grid2;
  };
}
function eventChunks(mainState, viewState) {
  return () => {
    let { filteredEvents: filteredEvents2 } = mainState;
    let { grid: grid2, monthView: monthView2 } = viewState;
    let chunks = [];
    let bgChunks = [];
    untrack(() => {
      for (let event of filteredEvents2) {
        for (let days of grid2) {
          if (bgEvent(event.display)) {
            if (!monthView2 || event.allDay) {
              bgChunks = bgChunks.concat(createChunks(event, days, monthView2));
            }
          } else {
            chunks = chunks.concat(createChunks(event, days, monthView2));
          }
        }
      }
      prepareChunks(chunks);
    });
    return { chunks, bgChunks };
  };
}
function iEventChunks(mainState, viewState) {
  return () => {
    let { iEvents } = mainState;
    let { grid: grid2, monthView: monthView2 } = viewState;
    let iChunks = [];
    for (let [, event] of iEvents) {
      if (!event) {
        continue;
      }
      untrack(() => {
        for (let days of grid2) {
          iChunks = iChunks.concat(createChunks(event, days, monthView2, false));
        }
      });
    }
    return iChunks;
  };
}
function dayTimeLimits(mainState) {
  return () => {
    let { filteredEvents: filteredEvents2, viewDates: viewDates2, options: { flexibleSlotTimeLimits, slotMinTime, slotMaxTime } } = mainState;
    let dayTimeLimits2 = {};
    untrack(() => {
      for (let date of viewDates2) {
        dayTimeLimits2[date.getTime()] = createSlotTimeLimits(
          slotMinTime,
          slotMaxTime,
          flexibleSlotTimeLimits,
          [date],
          filteredEvents2
        );
      }
    });
    return dayTimeLimits2;
  };
}
function daySlots(mainState, viewState) {
  return () => {
    let { viewDates: viewDates2, options: { slotDuration } } = mainState;
    let { dayTimeLimits: dayTimeLimits2, intlSlotLabel, slotLabelPeriodicity: slotLabelPeriodicity2 } = viewState;
    let slots2 = {};
    untrack(() => {
      for (let date of viewDates2) {
        let key = date.getTime();
        slots2[key] = key in dayTimeLimits2 ? createSlots(date, slotDuration, slotLabelPeriodicity2, dayTimeLimits2[key], intlSlotLabel) : [];
      }
    });
    return slots2;
  };
}
function nestedResources(mainState) {
  return () => {
    let { resources } = mainState;
    let nested;
    untrack(() => {
      nested = resources.some((resource) => getPayload(resource).children.length);
    });
    return nested;
  };
}
function monthView(mainState) {
  return () => {
    let { options: { slotDuration } } = mainState;
    let monthView2;
    untrack(() => {
      monthView2 = !toSeconds(slotDuration);
    });
    return monthView2;
  };
}
class ViewState5 extends RRState(TRRState()) {
  #dayTimeLimits;
  get dayTimeLimits() {
    return $.get(this.#dayTimeLimits);
  }
  set dayTimeLimits(value) {
    $.set(this.#dayTimeLimits, value);
  }
  #daySlots;
  get daySlots() {
    return $.get(this.#daySlots);
  }
  set daySlots(value) {
    $.set(this.#daySlots, value);
  }
  #grid;
  get grid() {
    return $.get(this.#grid);
  }
  set grid(value) {
    $.set(this.#grid, value);
  }
  #monthView;
  get monthView() {
    return $.get(this.#monthView);
  }
  set monthView(value) {
    $.set(this.#monthView, value);
  }
  #chunks;
  get chunks() {
    return $.get(this.#chunks);
  }
  set chunks(value) {
    $.set(this.#chunks, value);
  }
  #bgChunks;
  get bgChunks() {
    return $.get(this.#bgChunks);
  }
  set bgChunks(value) {
    $.set(this.#bgChunks, value);
  }
  #iChunks;
  get iChunks() {
    return $.get(this.#iChunks);
  }
  set iChunks(value) {
    $.set(this.#iChunks, value);
  }
  #nestedResources;
  get nestedResources() {
    return $.get(this.#nestedResources);
  }
  set nestedResources(value) {
    $.set(this.#nestedResources, value);
  }
  constructor(mainState) {
    super(mainState);
    this.#dayTimeLimits = $.derived(
      dayTimeLimits(mainState)
      // flexible time limits per day
    );
    this.#daySlots = $.derived(daySlots(mainState, this));
    this.#grid = $.derived(grid(mainState, this));
    this.#monthView = $.derived(monthView(mainState));
    let $$d = $.derived(eventChunks(mainState, this)), chunks = $.derived(() => $.get($$d).chunks), bgChunks = $.derived(() => $.get($$d).bgChunks);
    this.#chunks = $.derived(() => $.get(chunks));
    this.#bgChunks = $.derived(() => $.get(bgChunks));
    this.#iChunks = $.derived(iEventChunks(mainState, this));
    this.#nestedResources = $.derived(nestedResources(mainState));
  }
}
function Day($$anchor, $$props) {
  $.push($$props, true);
  let $$d = $.derived(() => getContext("state")), slotWidth = $.derived(() => $.get($$d).options.slotWidth);
  let $$d_1 = $.derived(() => getContext("view-state")), monthView2 = $.derived(() => $.get($$d_1).monthView), snap2 = $.derived(() => $.get($$d_1).snap);
  let date = $.derived(() => $$props.day.dayStart), start = $.derived(() => $$props.day.start), resource = $.derived(() => $$props.day.resource), disabled = $.derived(() => $$props.day.disabled), highlight = $.derived(() => $$props.day.highlight);
  let el = $.state(void 0);
  function dateFromPoint(x, y) {
    if ($.get(monthView2)) {
      return $.get(date);
    } else {
      let dayRect = rect($.get(el));
      let scaleX = dayRect.width / $.get(el).offsetWidth;
      return addDuration(cloneDate($.get(start)), $.get(snap2).duration, floor((isRtl() ? dayRect.right - x : x - dayRect.left) / ($.get(slotWidth) * $.get(snap2).ratio * scaleX)));
    }
  }
  BaseDay($$anchor, {
    get allDay() {
      return $.get(monthView2);
    },
    get date() {
      return $.get(date);
    },
    get resource() {
      return $.get(resource);
    },
    dateFromPoint,
    get disabled() {
      return $.get(disabled);
    },
    get highlight() {
      return $.get(highlight);
    },
    get noIeb() {
      return $$props.noIeb;
    },
    get noBeb() {
      return $$props.noBeb;
    },
    get el() {
      return $.get(el);
    },
    set el($$value) {
      $.set(el, $$value, true);
    }
  });
  $.pop();
}
function Event($$anchor, $$props) {
  $.push($$props, true);
  let $$d = $.derived(() => getContext("state")), slotDuration = $.derived(() => $.get($$d).options.slotDuration), slotWidth = $.derived(() => $.get($$d).options.slotWidth);
  let $$d_1 = $.derived(() => getContext("view-state")), monthView2 = $.derived(() => $.get($$d_1).monthView);
  let el = $.state(void 0);
  let margin = $.state(1);
  let event = $.derived(() => $$props.chunk.event);
  let styles = $.derived(() => (style) => {
    style["grid-column"] = `${$$props.chunk.gridColumn} / span ${$$props.chunk.dates.length}`;
    style["grid-row"] = $$props.chunk.gridRow;
    if (!$.get(monthView2)) {
      let left = $$props.chunk.left / toSeconds($.get(slotDuration)) * $.get(slotWidth);
      style["inset-inline-start"] = `${left}px`;
      style["inline-size"] = `${$$props.chunk.width / toSeconds($.get(slotDuration)) * $.get(slotWidth)}px`;
    }
    let marginTop = $.get(margin);
    if ($.get(event)._margin) {
      let [_margin, _gridRow] = $.get(event)._margin;
      if ($$props.chunk.gridRow === _gridRow) {
        marginTop = _margin;
      }
    }
    style["margin-block-start"] = `${marginTop}px`;
    return style;
  });
  function reposition() {
    $.set(margin, repositionEvent($$props.chunk, height($.get(el)), $.get(monthView2)), true);
  }
  var $$exports = { reposition };
  {
    let $0 = $.derived(() => [$.get(margin), $$props.chunk.gridRow]);
    InteractableEvent($$anchor, {
      get chunk() {
        return $$props.chunk;
      },
      get styles() {
        return $.get(styles);
      },
      axis: "x",
      get forceMargin() {
        return $.get($0);
      },
      get el() {
        return $.get(el);
      },
      set el($$value) {
        $.set(el, $$value, true);
      }
    });
  }
  return $.pop($$exports);
}
var root_1$2 = $.from_html(`<span></span>`);
var root_2 = $.from_html(`<button></button>`);
var root = $.from_html(`<!> <span><!></span>`, 1);
function Expander($$anchor, $$props) {
  $.push($$props, true);
  let resource = $.prop($$props, "resource", 7);
  let $$d = $.derived(() => getContext("state")), resources = $.derived(() => $.get($$d).resources), view2 = $.derived(() => $.get($$d).view), buttonText = $.derived(() => $.get($$d).options.buttonText), icons = $.derived(() => $.get($$d).options.icons), resourceExpand = $.derived(() => $.get($$d).options.resourceExpand), theme = $.derived(() => $.get($$d).options.theme);
  let payload = $.state({});
  let expanded = $.derived(() => resource().expanded);
  let title = $.derived(() => $.get(buttonText)[$.get(expanded) ? "collapse" : "expand"]);
  $.user_pre_effect(() => {
    $.set(payload, getPayload(resource()));
  });
  function onclick(jsEvent) {
    resource().expanded = $.set(expanded, !$.get(expanded));
    toggle($.get(payload).children);
    $.get(resources).length = $.get(resources).length;
    if (isFunction($.get(resourceExpand))) {
      $.get(resourceExpand)({
        resource: resource(),
        jsEvent,
        view: toViewWithLocalDates($.get(view2))
      });
    }
  }
  function toggle(children) {
    for (let child of children) {
      let payload2 = getPayload(child);
      payload2.hidden = !$.get(expanded);
      if (child.expanded) {
        toggle(payload2.children);
      }
    }
  }
  var fragment = root();
  var node = $.first_child(fragment);
  $.each(node, 17, () => Array($.get(payload).level), $.index, ($$anchor2, level) => {
    var span = root_1$2();
    $.template_effect(() => $.set_class(span, 1, $.get(theme).expander));
    $.append($$anchor2, span);
  });
  var span_1 = $.sibling(node, 2);
  var node_1 = $.child(span_1);
  {
    var consequent = ($$anchor2) => {
      var button = root_2();
      $.attach(button, () => contentFrom($.get(icons)[$.get(expanded) ? "collapse" : "expand"]));
      $.template_effect(() => {
        $.set_class(button, 1, $.get(theme).button);
        $.set_attribute(button, "aria-label", $.get(title));
        $.set_attribute(button, "title", $.get(title));
      });
      $.delegated("click", button, onclick);
      $.append($$anchor2, button);
    };
    $.if(node_1, ($$render) => {
      if ($.get(payload).children?.length) $$render(consequent);
    });
  }
  $.reset(span_1);
  $.template_effect(() => $.set_class(span_1, 1, $.get(theme).expander));
  $.append($$anchor, fragment);
  $.pop();
}
$.delegate(["click"]);
var root_1$1 = $.from_html(`<div></div>`);
function NowIndicator($$anchor, $$props) {
  $.push($$props, true);
  let $$d = $.derived(() => getContext("state")), mainEl = $.derived(() => $.get($$d).mainEl), now = $.derived(() => $.get($$d).now), today = $.derived(() => $.get($$d).today), slotDuration = $.derived(() => $.get($$d).options.slotDuration), slotWidth = $.derived(() => $.get($$d).options.slotWidth), theme = $.derived(() => $.get($$d).options.theme);
  let $$d_1 = $.derived(() => getContext("view-state")), grid2 = $.derived(() => $.get($$d_1).grid), sidebarWidth = $.derived(() => $.get($$d_1).sidebarWidth);
  let $$d_2 = $.derived(() => {
    for (let day of $.get(grid2)[0]) {
      if (datesEqual(day.dayStart, $.get(today))) {
        return day;
      }
    }
    return {};
  }), gridColumn = $.derived(() => $.get($$d_2).gridColumn), start = $.derived(() => $.get($$d_2).start), end = $.derived(() => $.get($$d_2).end);
  let left = $.derived(() => {
    if ($.get(now) < $.get(start) || $.get(now) > $.get(end)) {
      return null;
    }
    let step = toSeconds($.get(slotDuration));
    return ($.get(now) - $.get(start)) / 1e3 / step * $.get(slotWidth);
  });
  let observerOptions = $.derived(() => ({
    root: $.get(mainEl),
    rootMargin: isRtl() ? `0px -${$.get(sidebarWidth) + 1}px 0px 0px` : `0px 0px 0px -${$.get(sidebarWidth) + 1}px`,
    threshold: 0
  }));
  function onIntersect(el, entry) {
    el.classList.toggle($.get(theme).hidden, !entry.isIntersecting);
  }
  var fragment = $.comment();
  var node = $.first_child(fragment);
  {
    var consequent = ($$anchor2) => {
      var div = root_1$1();
      let styles;
      $.attach(div, () => intersectionObserver(onIntersect, $.get(observerOptions)));
      $.template_effect(() => {
        $.set_class(div, 1, $.get(theme).nowIndicator);
        styles = $.set_style(div, "", styles, {
          "grid-column": $.get(gridColumn) + 1,
          "grid-row": `2 / span ${$.get(grid2).length ?? ""}`,
          "inset-inline-start": `${$.get(left) ?? ""}px`
        });
      });
      $.append($$anchor2, div);
    };
    $.if(node, ($$render) => {
      if ($.get(gridColumn) && $.get(left) !== null) $$render(consequent);
    });
  }
  $.append($$anchor, fragment);
  $.pop();
}
var root_7 = $.from_html(`<div><time></time></div>`);
var root_8 = $.from_html(`<div role="rowheader"><!> <!></div>`);
var root_1 = $.from_html(`<section><header><aside></aside> <div role="row"><!> <!></div></header> <div role="rowgroup"><aside></aside> <div role="row"></div> <div><!> <!> <!></div></div> <!></section>`);
function View($$anchor, $$props) {
  $.push($$props, true);
  let mainState = getContext("state");
  let viewState = new ViewState5(mainState);
  setContext("view-state", viewState);
  let mainEl = $.derived(() => mainState.mainEl), today = $.derived(() => mainState.today), viewDates2 = $.derived(() => mainState.viewDates), columnWidth = $.derived(() => mainState.options.columnWidth), nowIndicator = $.derived(() => mainState.options.nowIndicator), scrollTime = $.derived(() => mainState.options.scrollTime), slotDuration = $.derived(() => mainState.options.slotDuration), slotHeight = $.derived(() => mainState.options.slotHeight), slotWidth = $.derived(() => mainState.options.slotWidth), theme = $.derived(() => mainState.options.theme);
  let chunks = $.derived(() => viewState.chunks), bgChunks = $.derived(() => viewState.bgChunks), iChunks = $.derived(() => viewState.iChunks), daySlots2 = $.derived(() => viewState.daySlots), dayTimeLimits2 = $.derived(() => viewState.dayTimeLimits), grid2 = $.derived(() => viewState.grid), monthView2 = $.derived(() => viewState.monthView), nestedResources2 = $.derived(() => viewState.nestedResources), sidebarWidth = $.derived(() => viewState.sidebarWidth), slotLabelPeriodicity2 = $.derived(() => viewState.slotLabelPeriodicity), viewResources2 = $.derived(() => viewState.viewResources);
  let headerHeight = $.state(0);
  $.user_effect(() => {
    $.get(scrollTime);
    if (!empty($.get(viewDates2))) {
      tick().then(scrollToTime);
    }
  });
  function scrollToTime() {
    let scrollLeft = 0;
    let todayOutOfView = $.get(today) < $.get(viewDates2)[0] || $.get(today) > $.get(viewDates2).at(-1);
    if ($.get(monthView2)) {
      if (!todayOutOfView) {
        let days = $.get(grid2)[0];
        for (let day of days) {
          if (datesEqual(day.dayStart, $.get(today))) {
            $.get(mainEl).scrollLeft = ($.get(mainEl).scrollWidth - $.get(sidebarWidth)) / length(days) * (day.gridColumn - 1) * (isRtl() ? -1 : 1);
            break;
          }
        }
      }
    } else {
      for (let date of $.get(viewDates2)) {
        let slotTimeLimits2 = getSlotTimeLimits($.get(dayTimeLimits2), date);
        if (todayOutOfView || datesEqual(date, $.get(today))) {
          scrollLeft += max(min(toSeconds($.get(scrollTime)), toSeconds(slotTimeLimits2.max)) - toSeconds(slotTimeLimits2.min), 0);
          break;
        } else {
          scrollLeft += toSeconds(slotTimeLimits2.max) - toSeconds(slotTimeLimits2.min);
        }
      }
      $.get(mainEl).scrollLeft = scrollLeft / toSeconds($.get(slotDuration)) * $.get(slotWidth) * (isRtl() ? -1 : 1);
    }
  }
  let refs = [];
  function reposition() {
    runReposition(refs, $.get(chunks));
  }
  $.user_effect(reposition);
  var fragment = $.comment();
  var node = $.first_child(fragment);
  {
    var consequent_3 = ($$anchor2) => {
      var section = root_1();
      let styles;
      var header = $.child(section);
      var aside = $.child(header);
      var div = $.sibling(aside, 2);
      var node_1 = $.child(div);
      $.each(node_1, 17, () => $.get(grid2)[0], $.index, ($$anchor3, $$item, i) => {
        let date = () => $.get($$item).dayStart;
        let disabled = () => $.get($$item).disabled;
        let highlight = () => $.get($$item).highlight;
        ColHead($$anchor3, {
          get date() {
            return date();
          },
          colIndex: 1 + i,
          get disabled() {
            return disabled();
          },
          get highlight() {
            return highlight();
          },
          children: ($$anchor4, $$slotProps) => {
            DayHeader($$anchor4, {
              get date() {
                return date();
              }
            });
          },
          $$slots: { default: true }
        });
      });
      var node_2 = $.sibling(node_1, 2);
      {
        var consequent = ($$anchor3) => {
          var fragment_3 = $.comment();
          var node_3 = $.first_child(fragment_3);
          $.each(node_3, 17, () => $.get(grid2)[0], $.index, ($$anchor4, $$item) => {
            let date = () => $.get($$item).dayStart;
            let disabled = () => $.get($$item).disabled;
            let highlight = () => $.get($$item).highlight;
            ColHead($$anchor4, {
              get date() {
                return date();
              },
              get className() {
                return $.get(theme).slots;
              },
              get disabled() {
                return disabled();
              },
              get highlight() {
                return highlight();
              },
              ariaHidden: true,
              children: ($$anchor5, $$slotProps) => {
                var fragment_5 = $.comment();
                var node_4 = $.first_child(fragment_5);
                $.each(node_4, 17, () => $.get(daySlots2)[date().getTime()], $.index, ($$anchor6, slot) => {
                  var div_1 = root_7();
                  let styles_1;
                  var time = $.child(div_1);
                  $.attach(time, () => contentFrom($.get(slot)[1]));
                  $.reset(div_1);
                  $.template_effect(() => {
                    $.set_class(div_1, 1, $.get(theme).slot);
                    styles_1 = $.set_style(div_1, "", styles_1, { "--ec-slot-label-periodicity": $.get(slot)[2] });
                    $.set_attribute(time, "datetime", $.get(slot)[0]);
                  });
                  $.append($$anchor6, div_1);
                });
                $.append($$anchor5, fragment_5);
              },
              $$slots: { default: true }
            });
          });
          $.append($$anchor3, fragment_3);
        };
        $.if(node_2, ($$render) => {
          if (!$.get(monthView2)) $$render(consequent);
        });
      }
      $.reset(div);
      $.reset(header);
      var div_2 = $.sibling(header, 2);
      var aside_1 = $.child(div_2);
      $.each(aside_1, 21, () => $.get(viewResources2), $.index, ($$anchor3, resource) => {
        var div_3 = root_8();
        var node_5 = $.child(div_3);
        {
          var consequent_1 = ($$anchor4) => {
            Expander($$anchor4, {
              get resource() {
                return $.get(resource);
              }
            });
          };
          $.if(node_5, ($$render) => {
            if ($.get(nestedResources2)) $$render(consequent_1);
          });
        }
        var node_6 = $.sibling(node_5, 2);
        Label(node_6, {
          get resource() {
            return $.get(resource);
          }
        });
        $.reset(div_3);
        $.template_effect(() => $.set_class(div_3, 1, $.get(theme).rowHead));
        $.append($$anchor3, div_3);
      });
      $.reset(aside_1);
      var div_4 = $.sibling(aside_1, 2);
      $.each(div_4, 21, () => $.get(grid2), $.index, ($$anchor3, days, i) => {
        var fragment_7 = $.comment();
        var node_7 = $.first_child(fragment_7);
        $.each(node_7, 17, () => $.get(days), $.index, ($$anchor4, day, j) => {
          {
            let $0 = $.derived(() => j + 1 === length($.get(days)));
            let $1 = $.derived(() => i + 1 === length($.get(grid2)));
            Day($$anchor4, {
              get day() {
                return $.get(day);
              },
              get noIeb() {
                return $.get($0);
              },
              get noBeb() {
                return $.get($1);
              }
            });
          }
        });
        $.append($$anchor3, fragment_7);
      });
      $.reset(div_4);
      var div_5 = $.sibling(div_4, 2);
      var node_8 = $.child(div_5);
      $.each(node_8, 19, () => $.get(chunks), (chunk) => chunk.id, ($$anchor3, chunk, i) => {
        $.bind_this(
          Event($$anchor3, {
            get chunk() {
              return $.get(chunk);
            }
          }),
          ($$value, i2) => refs[i2] = $$value,
          (i2) => refs?.[i2],
          () => [$.get(i)]
        );
      });
      var node_9 = $.sibling(node_8, 2);
      $.each(node_9, 17, () => $.get(bgChunks), (chunk) => chunk.id, ($$anchor3, chunk) => {
        Event($$anchor3, {
          get chunk() {
            return $.get(chunk);
          }
        });
      });
      var node_10 = $.sibling(node_9, 2);
      $.each(node_10, 17, () => $.get(iChunks), $.index, ($$anchor3, chunk) => {
        Event($$anchor3, {
          get chunk() {
            return $.get(chunk);
          }
        });
      });
      $.reset(div_5);
      $.reset(div_2);
      var node_11 = $.sibling(div_2, 2);
      {
        var consequent_2 = ($$anchor3) => {
          NowIndicator($$anchor3, {});
        };
        $.if(node_11, ($$render) => {
          if ($.get(nowIndicator) && !$.get(monthView2)) $$render(consequent_2);
        });
      }
      $.reset(section);
      $.bind_this(section, ($$value) => mainState.mainEl = $$value, () => mainState?.mainEl);
      $.attach(section, () => resizeObserver(reposition));
      $.template_effect(
        ($0) => {
          $.set_class(section, 1, $.get(theme).main);
          styles = $.set_style(section, "", styles, $0);
          $.set_class(header, 1, $.get(theme).header);
          $.set_class(aside, 1, $.get(theme).sidebar);
          $.set_class(div, 1, $.get(theme).grid);
          $.set_class(div_2, 1, $.get(theme).body);
          $.set_class(aside_1, 1, $.get(theme).sidebar);
          $.set_class(div_4, 1, $.get(theme).grid);
          $.set_class(div_5, 1, $.get(theme).events);
        },
        [
          () => ({
            "--ec-grid-cols": length($.get(grid2)[0]),
            "--ec-grid-rows": `${length($.get(grid2)) > 1 ? `repeat(${length($.get(grid2)) - 1}, auto)` : ""} 1fr`,
            "--ec-col-width": $.get(columnWidth) ?? "minmax(4em, 1fr)",
            "--ec-slot-label-periodicity": $.get(slotLabelPeriodicity2),
            "--ec-slot-height": `${$.get(slotHeight) ?? ""}px`,
            "--ec-slot-width": `${$.get(slotWidth) ?? ""}px`,
            "--ec-header-height": `${$.get(headerHeight) ?? ""}px`,
            "--ec-sidebar-width": `${$.get(sidebarWidth) ?? ""}px`
          })
        ]
      );
      $.bind_element_size(aside, "offsetWidth", ($$value) => viewState.sidebarWidth = $$value);
      $.bind_element_size(header, "offsetHeight", ($$value) => $.set(headerHeight, $$value));
      $.append($$anchor2, section);
    };
    var d = $.derived(() => !empty($.get(grid2)) && !empty($.get(grid2)[0]));
    $.if(node, ($$render) => {
      if ($.get(d)) $$render(consequent_3);
    });
  }
  $.append($$anchor, fragment);
  $.pop();
}
const index$1 = {
  createOptions(options) {
    createTRROptions(options);
    createRROptions(options);
    assign(options, {
      resourceExpand: void 0,
      slotWidth: 32,
      // Common options
      view: "resourceTimelineWeek"
    });
    assign(options.buttonText, {
      expand: "Expand",
      collapse: "Collapse",
      resourceTimelineDay: "timeline",
      resourceTimelineWeek: "timeline",
      resourceTimelineMonth: "timeline"
    });
    assign(options.icons, {
      collapse: { html: "&minus;" },
      expand: { html: "&plus;" }
    });
    assign(options.theme, {
      expander: "ec-expander",
      rowHead: "ec-row-head",
      slots: "ec-slots"
    });
    assign(options.views, {
      resourceTimelineDay: {
        buttonText: btnTextDay,
        component: initViewComponent$1,
        displayEventEnd: false,
        dayHeaderFormat: { weekday: "long" },
        duration: { days: 1 },
        theme: themeView("ec-resource ec-timeline ec-day-view"),
        titleFormat: { year: "numeric", month: "long", day: "numeric" }
      },
      resourceTimelineWeek: {
        buttonText: btnTextWeek,
        component: initViewComponent$1,
        displayEventEnd: false,
        duration: { weeks: 1 },
        theme: themeView("ec-resource ec-timeline ec-week-view")
      },
      resourceTimelineMonth: {
        buttonText: btnTextMonth,
        component: initMonthViewComponent,
        displayEventEnd: false,
        dayHeaderFormat: {
          weekday: "short",
          day: "numeric"
        },
        duration: { months: 1 },
        slotDuration: { days: 1 },
        theme: themeView("ec-resource ec-timeline ec-month-view"),
        titleFormat: { year: "numeric", month: "long" }
      }
    });
  },
  createParsers(parsers) {
    createTRRParsers(parsers);
  }
};
function initViewComponent$1(mainState) {
  setExtensions(mainState);
  return initMonthViewComponent(mainState);
}
function initMonthViewComponent(mainState) {
  mainState.features = ["timeline"];
  mainState.extensions.viewResources = (resources) => resources.filter((resource) => !getPayload(resource).hidden);
  return View;
}
const index = {
  createOptions(options) {
    createTRROptions(options);
    createTROptions(options);
    assign(options.buttonText, {
      timeGridDay: "day",
      timeGridWeek: "week"
    });
    assign(options, {
      view: "timeGridWeek"
    });
    assign(options.views, {
      timeGridDay: {
        buttonText: btnTextDay,
        component: initViewComponent,
        dayHeaderFormat: { weekday: "long" },
        duration: { days: 1 },
        theme: themeView("ec-time-grid ec-day-view"),
        titleFormat: { year: "numeric", month: "long", day: "numeric" }
      },
      timeGridWeek: {
        buttonText: btnTextWeek,
        component: initViewComponent,
        duration: { weeks: 1 },
        theme: themeView("ec-time-grid ec-week-view")
      }
    });
  },
  createParsers(parsers) {
    createTRRParsers(parsers);
  }
};
function initViewComponent(mainState) {
  setExtensions(mainState);
  return View$1;
}
function createCalendar(target, plugins, options) {
  return mount(Calendar, {
    target,
    props: {
      plugins,
      options
    }
  });
}
function destroyCalendar(calendar) {
  return unmount(calendar);
}
export {
  index$5 as DayGrid,
  index$4 as Interaction,
  index$3 as List,
  index$2 as ResourceTimeGrid,
  index$1 as ResourceTimeline,
  index as TimeGrid,
  createCalendar,
  destroyCalendar
};
