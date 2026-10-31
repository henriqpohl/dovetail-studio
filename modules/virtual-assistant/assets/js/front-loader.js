/* DT-VA loader: the only assistant script on every page. It loads the full assistant
   (configuration, panel styles, panel and front.js) on the first interaction with the tab,
   or once the page is idle, so the nudge and the "resume your brief" reminder still appear. */
(function () {
  "use strict";
  var L = window.DT_VA_LOADER;
  var tab = document.getElementById("dt-va-tab");
  if (!L || !tab) return;
  var state = 0; // 0 idle, 1 loading, 2 loaded
  var openWhenReady = false;

  /* The panel styles, then the panel and front.js. A failed stylesheet does not stop the assistant. */
  function styles(done) {
    if (!L.style || document.getElementById("dt-va-front-css")) return done();
    var l = document.createElement("link");
    l.id = "dt-va-front-css";
    l.rel = "stylesheet";
    l.href = L.style;
    l.onload = l.onerror = function () {
      done();
    };
    document.head.appendChild(l);
  }

  function inject() {
    styles(script);
  }

  function script() {
    var tpl = document.getElementById("dt-va-panel-tpl");
    if (tpl && !document.getElementById("dt-va")) {
      document.body.appendChild(tpl.content.cloneNode(true));
    }
    var s = document.createElement("script");
    s.src = L.script;
    s.async = true;
    s.setAttribute("data-no-optimize", "1");
    s.onload = function () {
      state = 2;
      // front.js binds its own handler to the tab; replay the click that started the load.
      if (openWhenReady) tab.click();
    };
    s.onerror = function () {
      state = 0;
    };
    document.body.appendChild(s);
  }

  function load(open) {
    if (open) openWhenReady = true;
    if (state !== 0) return;
    state = 1;
    if (!window.fetch) {
      state = 0;
      return;
    }
    fetch(L.config, { credentials: "same-origin" })
      .then(function (r) {
        if (!r.ok) throw new Error("config");
        return r.json();
      })
      .then(function (cfg) {
        window.DT_VA_SERVER = cfg;
        inject();
      })
      .catch(function () {
        state = 0;
      });
  }

  tab.addEventListener("click", function () {
    if (state !== 2) load(true);
  });
  // Start fetching as soon as the visitor shows intent.
  tab.addEventListener("pointerenter", function () {
    load(false);
  });
  tab.addEventListener("focus", function () {
    load(false);
  });

  // Otherwise load after the page has settled, off the critical path.
  var idle = window.requestIdleCallback || function (cb) {
    return setTimeout(cb, 1);
  };
  var start = function () {
    setTimeout(function () {
      idle(function () {
        load(false);
      });
    }, L.idleMs || 3000);
  };
  if (document.readyState === "complete") start();
  else window.addEventListener("load", start);
})();
