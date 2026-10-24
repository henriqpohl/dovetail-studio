/* DT-VA Virtual Assistant, guided enquiry flow with an answering layer. */
(function () {
  "use strict";
  /* ================= CONFIG ================= */
  var DT_VA_CONFIG = {
    COMPANY_NAME: "Dovetail Consultancy",
    COMPANY_TYPE: "Office",
    COMPANY_ADDRESS: "The View, Malahide Marina Village, Co. Dublin",
    COMPANY_CITY: "Malahide",
    COMPANY_EMAIL: "info@designminds.ie",
    COMPANY_PHONE_DISPLAY: "01 961 0034",
    COMPANY_PHONE_TEL: "+35319610034",
    WHATSAPP:
      "353862548245" /* WhatsApp number, international format without +. Blank = no WhatsApp offered. */,
    WHATSAPP_DISPLAY: "086 254 8245",
    PRIVACY_URL: "/privacy-policy/",
    WORK_URL: "/our-work/",
    RESPONSE_PROMISE: "within one working day",
    OPENING_HOURS: {
      open: 9,
      close: 17.5,
      days: [1, 2, 3, 4, 5],
    } /* Dublin, Mon to Fri 09:00 to 17:30 */,
    BOOKING_URL:
      "" /* Calendly or Google Calendar appointment link. Blank = no diary booking offered. */,
    GOOGLE_RATING:
      "5.0" /* Google Business Profile, 24/09/2026. Blank = no rating line. */,
    GOOGLE_REVIEWS: 18,
    GOOGLE_URL:
      "https://www.google.com/maps?cid=3424694600460002421" /* the Business Profile on Maps (place CID); reviews are on the card */,
    RESUME_HOURS: 24 /* Remember an unfinished conversation in this browser for this long */,
    ANALYTICS: true /* gtag / dataLayer events, prefixed dt_va_ */,
    /* Case studies are Design Minds' own portfolio; showing it to a different company's
       visitors would be actively wrong. Left empty by default — work() already skips
       straight to money() when both are empty, so no other code needs to change. Add
       your own via a future admin screen, or edit these two literals directly for now. */
    CASE_STUDIES: {},
    CASE_STUDIES_TAGGED: [],
  };
  /* ========================================== */

  var C = DT_VA_CONFIG,
    S = window.DT_VA_SERVER || null;
  if (S && typeof S.companyName === "string" && S.companyName.trim())
    C.COMPANY_NAME = S.companyName.trim();
  C.AI_ASSISTANT_NAME = "";
  if (S && typeof S.aiAssistantName === "string" && S.aiAssistantName.trim())
    C.AI_ASSISTANT_NAME = S.aiAssistantName.trim();
  C.COMPANY_AVATAR = "DT";
  if (S && typeof S.companyAvatar === "string" && S.companyAvatar.trim())
    C.COMPANY_AVATAR = S.companyAvatar.trim();
  C.COLLAPSE_AVATAR = !!(S && S.collapseAvatar);
  var themeKeys = {
    accent: "--dt-va-accent",
    accent_hover: "--dt-va-accent-hover",
    panel: "--dt-va-panel",
    surface: "--dt-va-surface",
    message: "--dt-va-message",
    input: "--dt-va-input",
    text: "--dt-va-text",
    user_message: "--dt-va-user-message",
    user_text: "--dt-va-user-text",
    muted: "--dt-va-muted",
    border: "--dt-va-border",
  };
  if (S && S.themeConfig)
    Object.keys(themeKeys).forEach(function (key) {
      var value = S.themeConfig[key];
      if (typeof value === "string" && /^#[0-9a-f]{6}$/i.test(value))
        document.documentElement.style.setProperty(themeKeys[key], value);
    });
  // Own-property checks preserve intentional blanks and zero; standalone HTML keeps its defaults.
  var publicKeys = [
    "COMPANY_TYPE",
    "COMPANY_ADDRESS",
    "COMPANY_CITY",
    "COMPANY_EMAIL",
    "COMPANY_PHONE_DISPLAY",
    "COMPANY_PHONE_TEL",
    "WHATSAPP",
    "WHATSAPP_DISPLAY",
    "PRIVACY_URL",
    "WORK_URL",
    "RESPONSE_PROMISE",
    "OPENING_HOURS",
    "BOOKING_URL",
    "GOOGLE_RATING",
    "GOOGLE_REVIEWS",
    "GOOGLE_URL",
    "RESUME_HOURS",
  ];
  if (S && S.publicConfig)
    publicKeys.forEach(function (key) {
      if (Object.prototype.hasOwnProperty.call(S.publicConfig, key))
        C[key] = S.publicConfig[key];
    });
  var $ = function (id) {
    return document.getElementById(id);
  };
  var tab = $("dt-va-tab"),
    panel = $("dt-va"),
    body = $("dt-va-body"),
    chips = $("dt-va-chips"),
    input = $("dt-va-input");
  if (!tab || !panel) return;
  var tabMark = tab.querySelector(".dt-va-mark"),
    headAvatar = panel.querySelector(".dt-va-avatar");
  if (tabMark) tabMark.textContent = C.COMPANY_AVATAR;
  if (headAvatar) headAvatar.textContent = C.COMPANY_AVATAR;
  if (C.COLLAPSE_AVATAR) tab.classList.add("dt-va-tab-noavatar");
  var reduced =
    window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches;
  var state = {
    service: null,
    answers: {},
    pending: null,
    started: false,
    step: null,
    lastChips: [],
    history: [],
    transcript: [],
    ref: null,
    lastChipsBeforeAsk: null,
    pendingBeforeAsk: null,
  };
  var LABEL = {
    enquiry: "Enquiry",
    client: "Existing client",
    other: "Something else",
  };
  var QUOTE =
    "\n\nA fixed quote follows once the " +
    esc(C.COMPANY_NAME) +
    " team has seen your brief.";
  var PHONE_LINK =
    '<a href="tel:' +
    C.COMPANY_PHONE_TEL +
    '">' +
    C.COMPANY_PHONE_DISPLAY +
    "</a>";
  /* falls back to Office when blank, matching the field's server-side validation */
  function typeWord(cap) {
    var t = (C.COMPANY_TYPE || "Office").trim() || "Office";
    return cap
      ? t.charAt(0).toUpperCase() + t.slice(1)
      : t.charAt(0).toLowerCase() + t.slice(1);
  }
  function waUrl(text) {
    return (
      "https://wa.me/" +
      C.WHATSAPP +
      "?text=" +
      encodeURIComponent(
        text ||
          "Hello " +
            C.COMPANY_NAME +
            ", I was on your website and I'd like to talk about a project.",
      )
    );
  }
  var WA_LINK = C.WHATSAPP
    ? '<a href="' +
      waUrl() +
      '" target="_blank" rel="noopener">' +
      C.WHATSAPP_DISPLAY +
      "</a>"
    : "";
  function waChip() {
    if (!C.WHATSAPP) return [];
    return [
      {
        label: "WhatsApp the " + typeWord(),
        go: function () {
          var ref = state.ref ? " (ref " + state.ref + ")" : "";
          track("whatsapp");
          window.open(
            waUrl(
              "Hello " +
                C.COMPANY_NAME +
                ", I was chatting with the assistant on your website" +
                ref +
                " and I'd like to talk about " +
                (LABEL[state.service]
                  ? LABEL[state.service].toLowerCase()
                  : "a project") +
                ".",
            ),
            "_blank",
            "noopener",
          );
          bot(
            "WhatsApp is open in a new tab. The team read it during " +
              typeWord() +
              " hours, Monday to Friday.",
            function () {
              setChips([
                {
                  label: "Ask the team to ring me",
                  go: function () {
                    callback();
                  },
                },
                { label: "Back to the questions", go: start },
              ]);
            },
          );
        },
      },
    ];
  }

  /* ---------- analytics ---------- */
  function track(name, params) {
    if (!C.ANALYTICS) return;
    try {
      var p = params || {};
      p.door = p.door || state.service || "none";
      if (typeof window.gtag === "function")
        window.gtag("event", "dt_va_" + name, p);
      else if (Array.isArray(window.dataLayer))
        window.dataLayer.push(Object.assign({ event: "dt_va_" + name }, p));
      if (
        S &&
        S.event &&
        /^(open|door|step|sent|feedback|ask|nudge|nudge_open|human|whatsapp|booking|restart)$/.test(
          name,
        )
      ) {
        var ev = JSON.stringify({
          n: name,
          door: p.door,
          step: p.step || "",
          useful: p.useful || "",
          ok: p.ok,
          why: p.why || "",
          page: location.pathname.slice(0, 80),
        });
        if (navigator.sendBeacon) {
          navigator.sendBeacon(S.event, new Blob([ev], { type: "text/plain" }));
        } else if (window.fetch) {
          fetch(S.event, {
            method: "POST",
            body: ev,
            keepalive: true,
            credentials: "same-origin",
          }).catch(function () {});
        }
      }
    } catch (e) {}
  }

  /* ---------- opening hours (Dublin) ---------- */
  function studioOpen() {
    try {
      var parts = new Intl.DateTimeFormat("en-IE", {
        timeZone: "Europe/Dublin",
        weekday: "short",
        hour: "numeric",
        minute: "numeric",
        hour12: false,
      }).formatToParts(new Date());
      var map = {};
      parts.forEach(function (p) {
        map[p.type] = p.value;
      });
      var day = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].indexOf(
        map.weekday,
      );
      var h = parseInt(map.hour, 10) + parseInt(map.minute, 10) / 60;
      return (
        C.OPENING_HOURS.days.indexOf(day) > -1 &&
        h >= C.OPENING_HOURS.open &&
        h < C.OPENING_HOURS.close
      );
    } catch (e) {
      return true;
    }
  }
  /* c28: when the studio next opens, for the out-of-hours wording. 'today' (before 9am on a working day), 'tomorrow', or a day name. */
  function nextOpen() {
    try {
      var parts = new Intl.DateTimeFormat("en-IE", {
        timeZone: "Europe/Dublin",
        weekday: "short",
        hour: "numeric",
        minute: "numeric",
        hour12: false,
      }).formatToParts(new Date());
      var map = {};
      parts.forEach(function (p) {
        map[p.type] = p.value;
      });
      var names = [
        "Sunday",
        "Monday",
        "Tuesday",
        "Wednesday",
        "Thursday",
        "Friday",
        "Saturday",
      ];
      var day = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].indexOf(
          map.weekday,
        ),
        h = parseInt(map.hour, 10) + parseInt(map.minute, 10) / 60;
      if (C.OPENING_HOURS.days.indexOf(day) > -1 && h < C.OPENING_HOURS.open)
        return "today";
      var n = day;
      for (var i = 0; i < 7; i++) {
        n = (n + 1) % 7;
        if (C.OPENING_HOURS.days.indexOf(n) > -1)
          return i === 0 ? "tomorrow" : names[n];
      }
    } catch (e) {}
    return "the next working day";
  }
  function reopens() {
    var n = nextOpen();
    return n === "today"
      ? "at 9am today"
      : n === "tomorrow"
        ? "at 9am tomorrow"
        : n === "the next working day"
          ? "on the next working day"
          : "on " + n + " at 9am";
  }
  function whenLabel() {
    var n = nextOpen();
    return n === "today"
      ? "Today"
      : n === "tomorrow"
        ? "Tomorrow"
        : n === "the next working day"
          ? "Next working day"
          : n;
  }
  function setStatus() {
    var s = $("dt-va-status");
    if (!s) return;
    s.innerHTML = studioOpen()
      ? "<i></i>Read by the " + esc(C.COMPANY_NAME) + " team"
      : '<i class="dt-va-off"></i>' +
        typeWord(true) +
        " reopens " +
        esc(reopens());
  }
  /* c28: campaign source from the link that brought the visitor (?src= or utm_*), kept for the visit. */
  function source() {
    try {
      return sessionStorage.getItem("dt-va-source") || "";
    } catch (e) {
      return "";
    }
  }
  /* c28: the Google rating line under the greeting. Not a bot bubble, so it stays out of the transcript and the model history. */
  function ratedHtml() {
    if (!C.GOOGLE_RATING) return "";
    var star =
        '<path d="M12 1.8l3.1 6.6 7.2.9-5.3 5 1.4 7.1L12 17.9l-6.4 3.5 1.4-7.1-5.3-5 7.2-.9z"/>',
      p = "",
      i;
    for (i = 0; i < 5; i++)
      p += '<g transform="translate(' + i * 26 + ' 0)">' + star + "</g>";
    return (
      '<a class="dt-va-rated" href="' +
      esc(C.GOOGLE_URL || "#") +
      '" target="_blank" rel="noopener"><svg viewBox="0 0 128 24" aria-hidden="true">' +
      p +
      "</svg><span><b>" +
      esc(C.GOOGLE_RATING) +
      "</b> on Google from " +
      esc(String(C.GOOGLE_REVIEWS == null ? "" : C.GOOGLE_REVIEWS)) +
      " reviews</span></a>"
    );
  }
  function askRated(html, list, freeText) {
    bot(html, function () {
      if (C.GOOGLE_RATING) {
        body.appendChild(el(ratedHtml()));
        scroll();
      }
      setChips(list);
      state.pending = freeText || null;
      save();
    });
  }

  /* ---------- rendering ---------- */
  function esc(t) {
    return String(t).replace(/[&<>"']/g, function (c) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[c];
    });
  }
  function el(html) {
    var t = document.createElement("template");
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
  }
  function scroll() {
    body.scrollTop = body.scrollHeight;
  }
  /* Bring a tall block (the enquiry form) in from its top rather than its bottom, so the question and first field are seen first. */
  function reveal(elm) {
    try {
      body.scrollTop +=
        elm.getBoundingClientRect().top - body.getBoundingClientRect().top - 8;
    } catch (e) {
      scroll();
    }
  }
  function focusQuiet(node) {
    if (!node) return;
    try {
      node.focus({ preventScroll: true });
    } catch (e) {
      node.focus();
    }
  }
  function plain(html) {
    var d = document.createElement("div");
    d.innerHTML = html;
    return d.textContent;
  }
  function user(text) {
    var m = el('<div class="dt-va-msg dt-va-user"></div>');
    m.textContent = text;
    body.appendChild(m);
    scroll();
    state.history.push({ role: "user", content: text });
    state.transcript.push("Client: " + text);
  }
  function sys(text) {
    var m = el('<div class="dt-va-sys"></div>');
    m.textContent = text;
    body.appendChild(m);
    scroll();
  }
  function bot(html, cb) {
    setChips([]);
    var t = el(
      '<div class="dt-va-typing" aria-hidden="true"><i></i><i></i><i></i></div>',
    );
    body.appendChild(t);
    scroll();
    var wait = reduced ? 0 : Math.min(900, 300 + html.length * 4);
    setTimeout(function () {
      t.remove();
      var m = el('<div class="dt-va-msg dt-va-bot"></div>');
      m.innerHTML = html;
      body.appendChild(m);
      scroll();
      var p = plain(html);
      state.history.push({ role: "assistant", content: p });
      state.transcript.push("Assistant: " + p);
      if (cb) cb();
    }, wait);
  }
  function setChips(list) {
    chips.innerHTML = "";
    state.lastChips = list || [];
    body.querySelectorAll(".dt-va-doors").forEach(function (d) {
      d.remove();
    });
    if (list && list.isDoors && phoneWidth()) {
      renderDoors(list);
      return;
    }
    (list || []).forEach(function (c) {
      var b = el('<button class="dt-va-chip" type="button"></button>');
      if (c.human) b.classList.add("dt-va-human");
      b.textContent = c.label;
      b.onclick = function () {
        user(c.label);
        setChips([]);
        state.pending = null;
        c.go(c.label);
      };
      chips.appendChild(b);
    });
  }
  function ask(html, list, freeText) {
    bot(html, function () {
      setChips(list);
      state.pending = freeText || null;
      save();
    });
  }
  function at(step) {
    state.step = step;
    track("step", { step: step });
    save();
  }

  /* ---------- save and resume (this browser only) ---------- */
  var KEY = "dt-va-session";
  function save() {
    try {
      if (!state.started || state.step === "done" || state.step === "open") {
        localStorage.removeItem(KEY);
        return;
      }
      localStorage.setItem(
        KEY,
        JSON.stringify({
          v: 2,
          ts: Date.now(),
          service: state.service,
          answers: state.answers,
          step: state.step,
          history: state.history.slice(-12),
          transcript: state.transcript.slice(-40),
        }),
      );
    } catch (e) {}
  }
  function saved() {
    try {
      var j = JSON.parse(localStorage.getItem(KEY) || "null");
      if (
        C.RESUME_HOURS <= 0 ||
        !j ||
        j.v !== 2 ||
        !j.step ||
        Date.now() - j.ts > C.RESUME_HOURS * 3600e3
      )
        return null;
      return j;
    } catch (e) {
      return null;
    }
  }
  function clearSaved() {
    try {
      localStorage.removeItem(KEY);
    } catch (e) {}
  }

  /* ---------- answering layer ---------- */
  function isExplicitQuestion(t) {
    t = (t || "").trim();
    return (
      /\?/.test(t) ||
      /^(how|what|whats|what's|why|when|where|who|whos|who's|which|do|does|did|can|could|is|are|will|would|should|any|got any|have you|tell me|explain|any idea|i'd like to know|i want to know)\b/i.test(
        t,
      )
    );
  }
  function isQuestion(t) {
    t = (t || "").trim();
    if (/\?/.test(t)) return true;
    if (
      /^(how|what|whats|what's|why|when|where|who|whos|who's|which|do|does|did|can|could|is|are|will|would|should|any|got any|have you|tell me|explain|any idea|i'd like to know|i want to know)\b/i.test(
        t,
      )
    )
      return true;
    if (
      /^(i need|i want|i'm after|we need|we want|i'm looking|we're looking|looking for|i'd like a|i would like a|we'd like a|can i get a quote for|quote for|please quote)/i.test(
        t,
      )
    )
      return false;
    return (
      t.split(/\s+/).length <= 7 &&
      typeof offlineAnswer === "function" &&
      !!offlineAnswer(t)
    );
  }
  function linkify(t) {
    return esc(t).replace(
      /(https?:\/\/[^\s<]+)/g,
      '<a href="$1" target="_blank" rel="noopener">$1</a>',
    );
  }
  function askModel(q, then) {
    state.asked = (state.asked || 0) + 1;
    if (S && S.askEnabled && window.fetch) {
      var t = el('<div class="dt-va-typing"><i></i><i></i><i></i></div>');
      body.appendChild(t);
      scroll();
      setChips([]);
      var controller = window.AbortController ? new AbortController() : null;
      var settled = false;
      // The timer also covers browsers without AbortController. Late responses are ignored.
      function finish(j) {
        if (settled) return;
        settled = true;
        clearTimeout(timer);
        t.remove();
        if (j && typeof j.answer === "string" && j.answer.trim()) {
          track("ask", { step: j.provider || "unknown" });
          bot(linkify(j.answer), withFeedback(q, then));
        } else fallbackAnswer(q, then);
      }
      var timer = setTimeout(function () {
        if (controller) controller.abort();
        finish(null);
      }, S.askTimeoutMs || 30000);
      // q is sent separately; omit the copy just appended to the conversation by user().
      var history = state.history.slice(-10);
      if (
        history.length &&
        history[history.length - 1].role === "user" &&
        history[history.length - 1].content === q
      )
        history.pop();
      var options = {
        method: "POST",
        headers: { "content-type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({
          q: q,
          door: LABEL[state.service] || "",
          history: history,
        }),
      };
      if (controller) options.signal = controller.signal;
      fetch(S.ask, options)
        .then(function (r) {
          if (!r.ok) throw new Error("Assistant unavailable");
          return r.json();
        })
        .then(finish)
        .catch(function () {
          finish(null);
        });
    } else if (typeof window.DT_VA_MOCK_ASK === "function") {
      bot(linkify(window.DT_VA_MOCK_ASK(q)), withFeedback(q, then));
    } else fallbackAnswer(q, then);
  }
  function offlineAnswer(q) {
    var t =
      " " +
      (q || "")
        .toLowerCase()
        .replace(/[^a-z0-9' ]+/g, " ")
        .replace(/\s+/g, " ") +
      " ";
    /* An admin-configured FAQ replaces the built-in one entirely (same convention as doors()/knowledge base); keywords are plain substrings, never regex, so admin-entered content can't cause catastrophic backtracking. */
    var custom = S && S.faqConfig;
    if (Array.isArray(custom) && custom.length) {
      for (var c = 0; c < custom.length; c++) {
        var kws = custom[c].keywords || [];
        for (var k = 0; k < kws.length; k++) {
          if (t.indexOf(" " + kws[k] + " ") > -1) {
            return String(custom[c].answer || "").replace(
              /\{\{COMPANY_NAME\}\}/g,
              C.COMPANY_NAME,
            );
          }
        }
      }
      return null;
    }
    /* Generic built-in fallback: every answer is built from Settings > Company fields
       already collected (phone, email, address, opening hours, Google rating), never
       from Design Minds-specific facts. Business-specific FAQ content (services, process,
       policies) has no field to draw from, so it belongs in the admin's Offline FAQ, not
       here — this list stays short and true for any company that filled in Settings. */
    var T = [
      [
        / (contact details|contact info|how (do|can) i (contact|reach|get in touch|email|phone)|get in touch|phone number|your number|telephone|email address|your email|how to contact|contact you|reach you|speak to someone|talk to someone) /,
        (C.COMPANY_PHONE_DISPLAY
          ? "Phone " + C.COMPANY_PHONE_DISPLAY + ". "
          : "") +
          (C.WHATSAPP && C.WHATSAPP_DISPLAY
            ? "WhatsApp " + C.WHATSAPP_DISPLAY + ". "
            : "") +
          (C.COMPANY_EMAIL ? "Email " + C.COMPANY_EMAIL + ". " : "") +
          "Or leave your details here and one of the team will ring you.",
      ],
      [
        / (opening hours|opening times|open times|office hours|business hours|your hours|what hours|hours of business|what time do you (open|close)|when do you (open|close)|are you open|when are you open|open (today|tomorrow|now|on|at|saturday|sunday|weekend|late)|when can i (ring|call|phone)|best time to (ring|call|phone)) /,
        (studioOpen()
          ? typeWord(true) + " is open now"
          : typeWord(true) + " is closed right now and reopens " + reopens()) +
          (C.COMPANY_PHONE_DISPLAY
            ? ", on " + C.COMPANY_PHONE_DISPLAY + "."
            : ".") +
          " Outside opening hours leave your details here and the team will pick them up on the next working day.",
      ],
      [
        / (meet (in person|up|you|face to face)|in person|face to face|come (in|out|and see|to see)|call in|visit (you|the office|your office)|pop in|drop in|site visit|come to (us|our office)|address|eircode|postcode|zip code|location|located|based in|where are you|where (is|are) (the )?(office|studio|shop|you)|your location|find you|directions|parking) /,
        C.COMPANY_ADDRESS
          ? "Yes, you're welcome to visit. " +
            esc(C.COMPANY_NAME) +
            " is at " +
            esc(C.COMPANY_ADDRESS) +
            (C.COMPANY_PHONE_DISPLAY
              ? ". Ring " + C.COMPANY_PHONE_DISPLAY + " to arrange it."
              : ".")
          : "Leave your details here and the team will get back to you with the best way to meet.",
      ],
      [
        / (reviews?|rating|rated|testimonials?|feedback|what (do|did) (your )?clients say|why (should|would) (we|i) (choose|pick|use|go with)|why choose|recommend(ed)?|reputation|trust you|any good|are you good) /,
        C.GOOGLE_RATING
          ? esc(C.COMPANY_NAME) +
            " is rated " +
            esc(C.GOOGLE_RATING) +
            " on Google" +
            (C.GOOGLE_REVIEWS
              ? " from " + esc(String(C.GOOGLE_REVIEWS)) + " reviews"
              : "") +
            "."
          : "Ask the team for references or recent reviews when they come back to you.",
      ],
      [
        / (cost|costs|price|prices|pricing|how much|budget|fee|fees|rates|charge|charges|expensive|afford|quote|quotation|estimate|ballpark) /,
        "Cost follows scope, so nothing is priced until the brief is understood. Tell me what you're looking at and the budget you have in mind, and the team will come back with a quote.",
      ],
      [
        / (services|what do you (do|offer|provide|supply|make|specialise in|specialize in)|what can you (do|help|offer)|what you do|what are you (good|best) at|specialities|specialties|capabilities|do you (do|make|design|build|offer|handle|supply|provide)|can you (do|make|design|build|help with|handle)|are you able to) /,
        "Tell me a bit about what you're looking for and I can point you in the right direction, or pick one of the options below.",
      ],
    ];
    for (var i = 0; i < T.length; i++) {
      if (T[i][0].test(t)) return T[i][1];
    }
    return null;
  }
  function withFeedback(q, then) {
    return function () {
      var row = el(
        '<div class="dt-va-fb"><span>Useful?</span><button type="button">Yes</button><button type="button">No</button></div>',
      );
      var btns = row.querySelectorAll("button");
      function vote(v) {
        track("feedback", { useful: v });
        state.transcript.push(
          'Feedback on "' + String(q || "").slice(0, 100) + '": ' + v,
        );
        row.innerHTML =
          "<span>" +
          (v === "yes"
            ? "Thanks."
            : "Noted, the team will improve that answer.") +
          "</span>";
      }
      btns[0].onclick = function () {
        vote("yes");
      };
      btns[1].onclick = function () {
        vote("no");
      };
      body.appendChild(row);
      scroll();
      if (then) then();
    };
  }
  function fallbackAnswer(q, then) {
    var a = offlineAnswer(q);
    track("ask", { step: a ? "builtin" : "brief" });
    if (a) {
      return bot(esc(a), withFeedback(q, then));
    }
    state.answers.notes =
      (state.answers.notes ? state.answers.notes + " " : "") + q;
    bot(
      "I've added that question to your brief so the team can answer it properly when they reply. If it's urgent, the " +
        typeWord() +
        " is on " +
        PHONE_LINK +
        ".",
      then,
    );
  }
  /* After an answer mid-flow, put the client back where they were. */
  function resume() {
    var chipsWere = state.lastChipsBeforeAsk || [],
      pendingWas = state.pendingBeforeAsk || null;
    state.lastChipsBeforeAsk = null;
    state.pendingBeforeAsk = null;
    if (chipsWere.length || pendingWas) {
      setChips(chipsWere);
      state.pending = pendingWas;
      if (!chipsWere.length) input.focus();
    } else setChips([{ label: "Start again", go: start }]);
  }

  /* ---------- flow ---------- */
  var STEPS = {};
  function start() {
    clearSaved();
    body.innerHTML = "";
    state.service = null;
    state.answers = {};
    state.pending = null;
    state.started = true;
    state.history = [];
    state.transcript = [];
    state.ref = null;
    state.lastChipsBeforeAsk = null;
    state.pendingBeforeAsk = null;
    at("open");
    state.asked = 0;
    var openLine = studioOpen()
      ? "If you'd rather talk now, the " +
        typeWord() +
        " is on " +
        PHONE_LINK +
        ", Monday to Friday."
      : "The " +
        typeWord() +
        " is closed at the moment and reopens " +
        reopens() +
        ". You can leave everything here and the team will pick it up then, or ring " +
        PHONE_LINK +
        " during office hours.";
    var assistantIntro = C.AI_ASSISTANT_NAME
      ? "I'm <strong>" +
        esc(C.AI_ASSISTANT_NAME) +
        "</strong> the AI Assistant at "
      : "I'm the AI Assistant at ";
    if (phoneWidth()) {
      askRated(
        "<b>Hello. " +
          assistantIntro +
          esc(C.COMPANY_NAME) +
          ".</b> Not a person, but the team reads everything you tell me. Ask me anything about how we work, or pick what you're looking at.",
        doors(),
        routeFree,
      );
    } else {
      askRated(
        "Hello. " +
          assistantIntro +
          esc(C.COMPANY_NAME) +
          ". I'm not a person, but everything you tell me is read by the " +
          esc(C.COMPANY_NAME) +
          " team. I can answer questions about how we work, show you projects like yours, and take the details of your job so the " +
          esc(C.COMPANY_NAME) +
          " team can come back to you properly, usually " +
          C.RESPONSE_PROMISE +
          ".\n\n" +
          openLine +
          "\n\nWhat are you looking at?",
        doors(),
        routeFree,
      );
    }
  }
  STEPS.open = start;
  /* Step-graph interpreter: an admin-configured step (flowConfig.steps[id]) takes over for that
     id; otherwise falls through to the built-in STEPS[id] function, unchanged. Only choice/text
     steps are configurable this way; work/money/timing/capture keep their fixed internal chaining. */
  function goToStep(id) {
    var steps = S && S.flowConfig && S.flowConfig.steps,
      custom = steps && steps[id];
    if (custom) return runConfigStep(id, custom);
    var fn = STEPS[id];
    if (fn) return fn();
    start();
  }
  function resolveNext(next) {
    if (typeof next === "string") return next;
    var cond = next.if,
      val = state.answers[cond.key],
      ok;
    if (cond.answerMissing) ok = !val;
    else if (cond.answerContains) {
      var hay = (" " + String(val || "").toLowerCase() + " ").replace(
        /[^a-z0-9' ]+/g,
        " ",
      );
      ok = cond.answerContains.some(function (w) {
        return hay.indexOf(" " + w + " ") > -1;
      });
    } else if (Object.prototype.hasOwnProperty.call(cond, "answerEquals"))
      ok = val === cond.answerEquals;
    else ok = false;
    return resolveNext(ok ? next.then : next.else || next.then);
  }
  function runConfigStep(id, step) {
    at(id);
    var advance = function (v) {
      if (step.answerKey) state.answers[step.answerKey] = v;
      goToStep(resolveNext(step.next));
    };
    var q = step.question + (step.example ? "\n\n" + step.example : "");
    if (step.type === "choice") {
      ask(
        q,
        step.options.map(function (label) {
          return {
            label: label,
            go: function () {
              advance(label);
            },
          };
        }),
        advance,
      );
    } else {
      ask(q, [], advance);
      input.focus();
    }
  }
  var ICONS = {
    logo: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    print:
      '<path d="M6 3h9l5 5v13H6z"/><path d="M15 3v5h5"/><path d="M9 13h6M9 17h6"/>',
    web: '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
    client:
      '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M16 11l2 2 4-4"/>',
    other:
      '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
    human:
      '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.6a2 2 0 0 1-.5 2.1L8 9.7a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.8.3 1.7.6 2.6.7a2 2 0 0 1 1.7 2z"/>',
  };
  /* Turns one validated door from flowConfig into the chip/button shape doors() returns; server-side validation already limited action/service to known values. */
  function buildConfigDoor(cfg) {
    var go;
    if (cfg.action === "human") go = human;
    else if (cfg.action === "other")
      go = function () {
        other();
      };
    else
      go = function () {
        goToStep(cfg.service + "1");
      };
    return {
      label: cfg.label,
      short: cfg.short,
      sub:
        cfg.sub ||
        (cfg.action === "human"
          ? "Ring the " + typeWord() + " or ask for a call back"
          : ""),
      ico: cfg.icon,
      human: cfg.action === "human",
      go: go,
    };
  }
  function defaultDoors() {
    return [
      {
        label: "Enquire about a product or service",
        sub: "Tell us what you're looking for",
        ico: "logo",
        go: function () {
          goToStep("enquiry1");
        },
      },
      {
        label: "I'm already a client",
        sub: "Changes, updates or an account query",
        ico: "client",
        go: function () {
          goToStep("client1");
        },
      },
      {
        label: "Something else",
        sub: "Or just ask me a question",
        ico: "other",
        go: function () {
          other();
        },
      },
      {
        label: "I'd rather talk to a person",
        sub: "Ring the " + typeWord() + " or ask for a call back",
        ico: "human",
        human: true,
        go: human,
      },
    ];
  }
  function doors() {
    var custom = S && S.flowConfig && S.flowConfig.doors;
    var l =
      Array.isArray(custom) && custom.length
        ? custom.map(buildConfigDoor)
        : defaultDoors();
    l.isDoors = true;
    return l;
  }
  function phoneWidth() {
    return window.innerWidth <= 600;
  }
  function renderDoors(list) {
    var wrap = el(
      '<div class="dt-va-doors" role="group" aria-label="What are you looking at?"></div>',
    );
    list.forEach(function (c) {
      var b = el(
        '<button class="dt-va-door" type="button"><span class="dt-va-ico" aria-hidden="true"><svg viewBox="0 0 24 24">' +
          (ICONS[c.ico] || ICONS.other) +
          '</svg></span><span></span><svg class="dt-va-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>',
      );
      if (c.human) b.classList.add("dt-va-human");
      var t = b.children[1];
      t.textContent = c.short || c.label;
      if (c.sub) {
        var sm = document.createElement("small");
        sm.textContent = c.sub;
        t.appendChild(sm);
      }
      b.onclick = function () {
        wrap.remove();
        user(c.short || c.label);
        setChips([]);
        state.pending = null;
        c.go(c.label);
      };
      wrap.appendChild(b);
    });
    body.appendChild(wrap);
    var prev = wrap.previousElementSibling;
    if (prev && prev.classList.contains("dt-va-rated"))
      prev = prev.previousElementSibling;
    try {
      if (prev) prev.scrollIntoView({ block: "start" });
      else body.scrollTop = 0;
    } catch (e) {
      body.scrollTop = 0;
    }
  }
  function routeFree(txt) {
    var t = txt.toLowerCase();
    if (
      isQuestion(txt) &&
      !/^(i need|i want|i'm looking|we need|we want|looking for)/i.test(
        txt.trim(),
      )
    ) {
      state.lastChipsBeforeAsk = doors();
      state.pendingBeforeAsk = routeFree;
      return askModel(txt, resume);
    }
    if (
      /already a client|existing client|reprint|invoice|amend|update (our|my|the) (account|order|service)/.test(
        t,
      )
    )
      return service("client", txt);
    return service("enquiry", txt);
  }
  function setThen(key, next) {
    return function (v) {
      state.answers[key] = v;
      next();
    };
  }
  /* Named wrapper so service()'s chip targets go through goToStep, making describe independently overridable via flowConfig.steps. */
  function toDescribe() {
    goToStep("describe");
  }
  function service(s, free) {
    state.service = s;
    if (free) state.answers.detail = free;
    track("door");
    if (s === "enquiry") {
      at("enquiry1");
      return describe();
    }
    if (s === "client") {
      at("client1");
      ask(
        "Welcome back. What do you need?",
        [
          {
            label: "A change to an existing order or service",
            go: setThen("type", toDescribe),
          },
          { label: "A repeat order", go: setThen("type", toDescribe) },
          {
            label: "An invoice or account query",
            go: setThen("type", toDescribe),
          },
          { label: "Something new", go: setThen("type", toDescribe) },
        ],
        setThen("type", toDescribe),
      );
    }
  }
  STEPS.enquiry1 = function () {
    service("enquiry");
  };
  STEPS.client1 = function () {
    service("client");
  };
  function describe() {
    at("describe");
    var q, eg;
    if (
      state.service === "client" &&
      !state.answers.project &&
      !/something new/i.test(state.answers.type || "")
    ) {
      var cq = clientQ();
      ask(cq.project, [], function (v) {
        state.answers.project = v;
        describe();
      });
      input.focus();
      return;
    }
    if (state.service === "client") {
      var cq2 = clientQ();
      q = cq2.changes;
      eg = cq2.eg;
    } else {
      q =
        "Tell me a little about the business and who it's for. A line or two is plenty.";
      eg =
        'For example: "Physiotherapy clinic opening in Cork, private patients, two practitioners."';
    }
    ask(q + "\n\n" + eg, [], function (v) {
      state.answers.business = v;
      goToStep(state.service === "client" ? "timing" : "work");
    });
    input.focus();
  }
  STEPS.describe = describe;
  /* The existing-client questions and examples follow the type chosen. */
  function clientQ() {
    var t = (state.answers.type || "").toLowerCase();
    if (/repeat/.test(t))
      return {
        project:
          'Which order or job is it? The name you\'d use for it is fine, and the company if we mightn\'t guess.\n\nFor example: "the March order" or "invoice 1234".',
        changes: "How many, and any changes before it goes ahead?",
        eg: 'For example: "same as last time, no changes" or "same again with the address updated".',
      };
    if (/invoice|account/.test(t))
      return {
        project:
          'Which order or invoice is it about?\n\nFor example: "invoice 1234" or "last month\'s order".',
        changes: "What's the query? A line or two is plenty.",
        eg: 'For example: "a copy of invoice 1234" or "the details are missing from the invoice".',
      };
    if (/something new/.test(t))
      return {
        project: "",
        changes: "What's the new enquiry? A line or two is plenty.",
        eg: 'For example: "a repeat order for the spring campaign" or "a new enquiry for the second branch".',
      };
    return {
      project:
        'Which order or job is it? The name you\'d use for it is fine, and the company if we mightn\'t guess.\n\nFor example: "the March order" or "invoice 1234".',
      changes: "And what needs changing? A line or two is plenty.",
      eg: 'For example: "the delivery address changed" or "the quantity needs updating".',
    };
  }
  /* Case studies matched to what the client told us about their business; the door's defaults fill the rest. */
  function matchCaseStudies() {
    var a = state.answers || {};
    var txt = [a.business, a.detail, a.notes, a.type]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();
    var toks = txt.split(/[^a-z]+/).filter(Boolean);
    var custom = S && S.caseStudiesConfig;
    var list = Array.isArray(custom) && custom.length ? custom : C.CASE_STUDIES_TAGGED;
    if (!toks.length || !list) return [];
    function hit(w) {
      return toks.some(function (t) {
        return (
          t === w || t === w + "s" || (w.length >= 4 && t.indexOf(w) === 0)
        );
      });
    }
    return list.map(function (cs) {
      var n = 0;
      (cs.ind || "").toLowerCase().split(" ").forEach(function (w) {
        if (w.length > 2 && hit(w)) n++;
      });
      return { cs: cs, n: n };
    })
      .filter(function (x) {
        return x.n > 0;
      })
      .sort(function (x, y) {
        return y.n - x.n;
      })
      .map(function (x) {
        return x.cs;
      });
  }
  function caseStudiesFor(service) {
    var custom = S && S.caseStudiesConfig;
    var base = Array.isArray(custom) && custom.length ? custom : C.CASE_STUDIES[service] || [];
    var seen = Object.create(null),
      out = [],
      matched = matchCaseStudies();
    state.matchedSector = matched.length ? matched[0].sec || "" : "";
    state.matchedCount = matched.length;
    matched.concat(base).forEach(function (cs) {
      if (!seen[cs.t] && out.length < 3) {
        seen[cs.t] = 1;
        out.push(cs);
      }
    });
    return out;
  }
  function caseStudyUrl(value, image) {
    if (typeof value !== "string") return "";
    if (!image && /^\/(?!\/)[^\\\s]*$/.test(value)) return value;
    if (!/^https?:\/\//i.test(value)) return "";
    try {
      var parsed = new URL(value);
      return parsed.protocol === "http:" || parsed.protocol === "https:" ? parsed.href : "";
    } catch (_) { return ""; }
  }
  function work() {
    at("work");
    var list = caseStudiesFor(state.service);
    state.shown = list.map(function (w) {
      return { t: w.t, url: caseStudyUrl(w.url) || caseStudyUrl(C.WORK_URL) };
    });
    if (!list.length) return money();
    var nMatched = Math.min(state.matchedCount || 0, list.length),
      intro;
    if (state.matchedSector && nMatched) {
      intro =
        "We've done a lot of work in " +
        esc(state.matchedSector) +
        ". " +
        (nMatched === 1 ? "One example" : nMatched === 2 ? "Two examples" : "Three examples") +
        (list.length > nMatched ? ", plus " + (list.length - nMatched) + " more you might like" : "") +
        ":";
    } else intro = "Thanks. Here's how we've approached similar briefs:";
    bot(intro, function () {
      var wrap = el('<div class="dt-va-cards" role="list"></div>');
      list.forEach(function (w) {
        var card = el(
          '<a class="dt-va-card" role="listitem" target="_blank" rel="noopener"><div class="dt-va-img"></div><div class="dt-va-cap"><b></b><span></span></div></a>',
        );
        card.href = caseStudyUrl(w.url) || caseStudyUrl(C.WORK_URL);
        card.querySelector(".dt-va-img").textContent = w.t;
        var imageUrl = caseStudyUrl(w.img, true);
        if (imageUrl)
          card.querySelector(".dt-va-img").style.backgroundImage =
            'linear-gradient(to top, rgba(0,0,0,.72), rgba(0,0,0,.05) 60%), url(' +
            JSON.stringify(imageUrl) +
            ')';
        card.querySelector("b").textContent = w.s;
        card.querySelector("span").textContent = w.o;
        card.addEventListener("click", function () {
          track("case_study", { title: w.t });
        });
        wrap.appendChild(card);
      });
      body.appendChild(wrap);
      scroll();
      setTimeout(money, reduced ? 0 : 500);
    });
  }
  STEPS.work = work;
  function money() {
    at("money");
    ask(
      "What's your budget? A rough figure is fine; it helps the team pitch the scope right, and a fixed quote follows once they've seen your brief.",
      [{ label: "Not sure yet", go: setThen("budget", timing) }],
      setThen("budget", timing),
    );
  }
  STEPS.money = money;
  function timing() {
    at("timing");
    var c =
      state.service === "client"
        ? [
            {
              label: "Today if at all possible",
              go: setThen("when", capture),
            },
            { label: "This week", go: setThen("when", capture) },
            { label: "No rush", go: setThen("when", capture) },
          ]
        : [
            { label: "Within a month", go: setThen("when", capture) },
            { label: "One to three months", go: setThen("when", capture) },
            { label: "No fixed date", go: setThen("when", capture) },
          ];
    ask("And when do you need it by?", c, setThen("when", capture));
  }
  STEPS.timing = timing;
  function other(fromFree) {
    state.service = "other";
    track("door");
    at("other");
    if (fromFree) {
      return ask(
        "Noted. Anything else you'd like the team to know before they come back to you?",
        [
          {
            label: "No, that's it",
            go: function () {
              capture();
            },
          },
        ],
        function (v) {
          state.answers.business = v;
          capture();
        },
      );
    }
    ask(
      "No problem. Tell me what you have in mind and I'll pass it straight to the team.",
      [],
      function (v) {
        state.answers.business = v;
        capture();
      },
    );
    input.focus();
  }
  STEPS.other = function () {
    other();
  };

  /* ---------- capture ---------- */
  function capture() {
    at("capture");
    var isClient = state.service === "client",
      maxMb = (S && S.maxFileMb) || 10;
    bot(
      isClient
        ? "Grand. Who should the team come back to?"
        : "That's everything I need about the job. So the team can reply, who should they come back to?",
      function () {
        var rendered = Date.now();
        var f = el(
          '<form class="dt-va-form" novalidate enctype="multipart/form-data">' +
            '<div class="dt-va-row"><label>Name<input id="dt-va-f-name" name="name" autocomplete="name" required></label><label>Company<input id="dt-va-f-co" name="company" autocomplete="organization"></label></div>' +
            '<label>Email<input id="dt-va-f-email" name="email" type="email" autocomplete="email" required></label>' +
            '<label>Phone<input id="dt-va-f-phone" name="phone" type="tel" autocomplete="tel" required></label>' +
            '<label>Anything to show us? <span>(a link is fine)</span><input id="dt-va-f-link" name="link" type="url" placeholder="https://"></label>' +
            '<label class="dt-va-file">Or attach a file <span>(PDF, JPG, PNG, AI, EPS, ZIP or Word, up to ' +
            maxMb +
            'MB)</span><input id="dt-va-f-file" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.ai,.eps,.zip,.doc,.docx"></label>' +
            '<label class="dt-va-check"><input type="checkbox" id="dt-va-f-cb"> Book a call back from the team</label>' +
            '<label class="dt-va-cb" id="dt-va-f-cbwrap" hidden>Best time to ring<input id="dt-va-f-time" placeholder="e.g. tomorrow after 2pm"></label>' +
            '<label class="dt-va-hp" aria-hidden="true">Website<input id="dt-va-f-hp" name="website2" tabindex="-1" autocomplete="off"></label>' +
            '<div id="dt-va-turnstile"></div>' +
            '<span class="dt-va-err" id="dt-va-f-err">Name, a working email and a phone number are needed so the team can reply.</span>' +
            "<small>We use these details only to reply to this enquiry. You'll get a copy of the brief by email. <a href=\"" +
            C.PRIVACY_URL +
            '" target="_blank" rel="noopener">Privacy policy</a>.</small>' +
            '<button class="dt-va-btn" type="submit">Send to ' +
            esc(C.COMPANY_NAME) +
            "</button></form>",
        );
        var tsId = null;
        if (S && S.turnstile && window.turnstile) {
          try {
            tsId = window.turnstile.render(
              f.querySelector("#dt-va-turnstile"),
              { sitekey: S.turnstile, size: "flexible", theme: "dark" },
            );
          } catch (e) {}
        }
        f.addEventListener("input", function () {
          f.classList.remove("dt-va-invalid");
        });
        f.querySelector("#dt-va-f-cb").addEventListener("change", function (e) {
          f.querySelector("#dt-va-f-cbwrap").hidden = !e.target.checked;
          if (e.target.checked) {
            f.querySelector("#dt-va-f-time").focus();
          }
        });
        f.querySelector("#dt-va-f-file").addEventListener(
          "change",
          function (e) {
            var fl = e.target.files[0];
            var err = f.querySelector("#dt-va-f-err");
            if (fl && fl.size > maxMb * 1024 * 1024) {
              err.textContent =
                "That file is over " +
                maxMb +
                "MB. A link to it is fine instead.";
              f.classList.add("dt-va-invalid");
              e.target.value = "";
            }
          },
        );
        f.onsubmit = function (e) {
          e.preventDefault();
          var g = function (id) {
            return f.querySelector("#" + id).value.trim();
          };
          var name = g("dt-va-f-name"),
            email = g("dt-va-f-email"),
            cb = f.querySelector("#dt-va-f-cb").checked,
            err = f.querySelector("#dt-va-f-err");
          if (
            !name ||
            !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email) ||
            g("dt-va-f-phone").replace(/\D/g, "").length < 7
          ) {
            err.textContent =
              "Name, a working email and a phone number are needed so the team can reply.";
            f.classList.add("dt-va-invalid");
            return;
          }
          var a = state.answers;
          a.name = name;
          a.company = g("dt-va-f-co");
          a.email = email;
          a.phone = g("dt-va-f-phone");
          a.link = g("dt-va-f-link");
          a.callback = cb;
          a.time = g("dt-va-f-time");
          var file = f.querySelector("#dt-va-f-file").files[0] || null;
          a.file = file ? file.name : "";
          var btn = f.querySelector(".dt-va-btn");
          btn.disabled = true;
          btn.textContent = "Sending…";
          var extra = {
            website2: g("dt-va-f-hp"),
            t: rendered,
            cf_turnstile:
              tsId !== null && window.turnstile
                ? window.turnstile.getResponse(tsId) || ""
                : "",
          };
          submit(file, extra, function (ok, ref, why) {
            f.querySelectorAll("input").forEach(function (i) {
              i.disabled = true;
            });
            btn.textContent = ok ? "Sent" : "Not sent";
            if (ref) state.ref = ref;
            done(ok, why);
          });
        };
        body.appendChild(f);
        reveal(f);
        setTimeout(function () {
          focusQuiet(f.querySelector("#dt-va-f-name"));
        }, 50);
      },
    );
  }
  STEPS.capture = capture;
  function rows() {
    var a = state.answers;
    return [
      ["Enquiry", LABEL[state.service]],
      a.type && ["Type", a.type],
      a.project && ["Project", a.project],
      a.scope && ["Scope", a.scope],
      a.pages ? ["Pages", a.pages] : a.pagesLabel && ["Pages", a.pagesLabel],
      a.goal && ["Goal", a.goal],
      (a.business || a.detail) && [
        state.service === "client" ? "Changes" : "Business",
        a.business || a.detail,
      ],
      a.notes && ["Notes", a.notes],
      a.when && ["Timing", a.when],
      a.budget && ["Budget", a.budget],
      ["Contact", a.name + (a.company ? ", " + a.company : "")],
      ["Email", a.email],
      a.phone && ["Phone", a.phone],
      a.callback && ["Call back", "Yes" + (a.time ? ", " + a.time : "")],
      a.link && ["Link", a.link],
      a.file && ["File", a.file],
      source() && ["Source", source()],
      ["Page", location.pathname],
    ].filter(Boolean);
  }
  function briefText() {
    return rows()
      .map(function (r) {
        return r[0] + ": " + r[1];
      })
      .join("\n");
  }
  function localRef() {
    return "DT-" + String(Math.floor(1000 + Math.random() * 9000));
  }

  /* ---------- submission: DT-VA server, mailto fallback ---------- */
  function submit(file, extra, cb) {
    var a = state.answers;
    if (!S || !S.submit || !window.fetch || !window.FormData) {
      return cb(false, null);
    }
    var fd = new FormData();
    fd.append("name", a.name);
    fd.append("email", a.email);
    fd.append("phone", a.phone || "");
    fd.append("company", a.company || "");
    fd.append("link", a.link || "");
    fd.append("door", LABEL[state.service] || "");
    fd.append("callback", a.callback ? "1" : "0");
    fd.append("time", a.time || "");
    fd.append("brief", briefText());
    fd.append("transcript", state.transcript.join("\n"));
    fd.append("page", location.pathname);
    fd.append("cases", JSON.stringify(state.shown || []));
    fd.append("source", source());
    fd.append("sector", state.matchedSector || "");
    fd.append("asked", String(state.asked || 0));
    Object.keys(extra || {}).forEach(function (k) {
      fd.append(k, extra[k]);
    });
    if (file) fd.append("attachment", file, file.name);
    fetch(S.submit, { method: "POST", body: fd, credentials: "same-origin" })
      .then(function (r) {
        return r.text().then(function (t) {
          var j = null;
          try {
            j = JSON.parse(t);
          } catch (e) {}
          return (
            j || { error: r.status === 413 ? "file_size" : "http" + r.status }
          );
        });
      })
      .then(function (j) {
        cb(
          !!(j && j.ok),
          j && j.ref ? j.ref : null,
          j && j.error ? String(j.error) : "",
        );
      })
      .catch(function () {
        cb(false, null, "network");
      });
  }
  function bookingChip() {
    if (!C.BOOKING_URL) return [];
    return [
      {
        label: "Pick a time in the diary",
        go: function () {
          var u =
            C.BOOKING_URL +
            (C.BOOKING_URL.indexOf("?") > -1 ? "&" : "?") +
            "name=" +
            encodeURIComponent(state.answers.name || "") +
            "&email=" +
            encodeURIComponent(
              (state.answers.email || "").indexOf("@no-email") > -1
                ? ""
                : state.answers.email || "",
            );
          track("booking");
          window.open(u, "_blank", "noopener");
          bot(
            "The diary is open in a new tab. Pick a slot that suits and it goes straight into the " +
              typeWord() +
              " calendar; your brief is already with the team.",
            function () {
              setChips([{ label: "Start again", go: start }]);
            },
          );
        },
      },
    ];
  }
  function sendReason(why) {
    var m = {
      rate: "That is several sends from this connection in the past hour, so this one was held back. ",
      file_type:
        "The attached file is a type I can't take (PDF, JPG, PNG, AI, EPS, ZIP or Word are fine). ",
      file_size:
        "The attached file is too big to send this way. A link to it is fine instead. ",
      fields: "A name, a working email and a phone number are needed. ",
      origin: "The send was blocked as coming from another site. ",
      network: "The connection dropped before the send finished. ",
    };
    return m[why] || "";
  }
  function done(ok, why) {
    var a = state.answers,
      ref = state.ref || localRef();
    state.ref = ref;
    track("sent", {
      ok: ok,
      callback: !!a.callback,
      why: ok ? "" : why || "",
    });
    var msg = ok
      ? a.callback
        ? "Sent. A copy is on its way to " +
          esc(a.email) +
          ". One of the " +
          esc(C.COMPANY_NAME) +
          " team will ring you on " +
          esc(a.phone) +
          (a.time ? ", " + esc(a.time) : "") +
          (studioOpen()
            ? ", " + C.RESPONSE_PROMISE + " at the latest."
            : ", once the " + typeWord() + " reopens " + reopens() + ".")
        : studioOpen()
          ? "Sent. A copy is on its way to " +
            esc(a.email) +
            ". The " +
            esc(C.COMPANY_NAME) +
            " team will read it and come back to you " +
            C.RESPONSE_PROMISE +
            "."
          : "Sent. A copy is on its way to " +
            esc(a.email) +
            ". The " +
            typeWord() +
            " is closed just now and reopens " +
            reopens() +
            "; the team will read your brief then and come back to you the same day."
      : sendReason(why) +
        "I couldn't send that just now. Your brief is below: <a href=\"mailto:" +
        C.COMPANY_EMAIL +
        "?subject=" +
        encodeURIComponent("Enquiry " + ref) +
        "&body=" +
        encodeURIComponent(briefText()) +
        '">email it to us in one click</a>, or ring ' +
        PHONE_LINK +
        ".";
    bot(msg, function () {
      var r = rows();
      var s = el(
        '<div class="dt-va-summary"><div class="dt-va-h">Your brief <span>' +
          esc(ref) +
          "</span></div><dl>" +
          r
            .map(function (x) {
              return "<dt>" + esc(x[0]) + "</dt><dd></dd>";
            })
            .join("") +
          "</dl></div>",
      );
      s.querySelectorAll("dd").forEach(function (d, i) {
        d.textContent = r[i][1];
      });
      body.appendChild(s);
      /* What happens next */
      var steps =
        state.service === "client"
          ? [
              [
                studioOpen() ? "Within one working day" : whenLabel(),
                "The team reads your request and confirms by email or phone.",
              ],
              ["Then", "The work is scheduled and you get a date."],
              [
                "On completion",
                "Proofs or files to you for sign-off before anything goes to print or live.",
              ],
            ]
          : [
              [
                studioOpen() ? "Within one working day" : whenLabel(),
                "The team reads your brief and comes back to you" +
                  (a.callback ? " by phone" : "") +
                  ".",
              ],
              [
                "Then",
                "A 20-minute call to talk it through. Free, no obligation.",
              ],
              [
                "Within a week of the call",
                "A written proposal with a fixed price and a timeline.",
              ],
            ];
      var n = el(
        '<div class="dt-va-next"><div class="dt-va-h">What happens next</div><ol></ol></div>',
      );
      steps.forEach(function (st) {
        var li = el("<li><b></b><span></span></li>");
        li.querySelector("b").textContent = st[0];
        li.querySelector("span").textContent = st[1];
        n.querySelector("ol").appendChild(li);
      });
      body.appendChild(n);
      scroll();
      at("done");
      clearSaved();
      setTimeout(
        function () {
          bot(
            "Thanks, " +
              esc((a.name || "").split(" ")[0]) +
              ". Talk soon. If you think of a question in the meantime, just type it here.",
            function () {
              setChips(
                bookingChip().concat(
                  a.callback
                    ? [{ label: "Start again", go: start }]
                    : [
                        {
                          label: "Actually, I'd like a call back",
                          go: function () {
                            callback();
                          },
                        },
                        {
                          label: "That's everything, thanks",
                          go: function () {
                            setChips([{ label: "Start again", go: start }]);
                          },
                        },
                      ],
                ),
              );
            },
          );
        },
        reduced ? 0 : 500,
      );
    });
  }
  function human() {
    track("human");
    at("human");
    ask(
      "Of course. The team in " +
        esc(C.COMPANY_CITY) +
        " take the calls themselves.\n\n<b>Phone</b> " +
        PHONE_LINK +
        ', Monday to Friday\n<b>Email</b> <a href="mailto:' +
        C.COMPANY_EMAIL +
        '">' +
        C.COMPANY_EMAIL +
        "</a>" +
        (WA_LINK ? "\n<b>WhatsApp</b> " + WA_LINK : "") +
        // Respect saved public settings, including an intentionally blank address.
        (C.COMPANY_ADDRESS
          ? "\n<b>Address</b> " + esc(C.COMPANY_ADDRESS)
          : "") +
        (studioOpen()
          ? ""
          : "\n\nThe " +
            typeWord() +
            " is closed just now and reopens " +
            reopens() +
            ".") +
        "\n\nOr leave a number and we'll ring you" +
        (studioOpen() ? "" : " then") +
        ".",
      bookingChip()
        .concat(waChip())
        .concat([
          {
            label: "Ask the team to ring me",
            go: function () {
              callback();
            },
          },
          { label: "Back to the questions", go: start },
        ]),
    );
  }
  STEPS.human = human;
  function callback() {
    at("callback");
    bot("Where and when suits?", function () {
      var rendered = Date.now();
      var f = el(
        '<form class="dt-va-form" novalidate><label>Name<input id="dt-va-c-name" required autocomplete="name"></label><label>Phone<input id="dt-va-c-phone" type="tel" required autocomplete="tel"></label><label>Best time<input id="dt-va-c-time" placeholder="e.g. tomorrow after 2pm"></label><label class="dt-va-hp" aria-hidden="true">Website<input id="dt-va-c-hp" name="website2" tabindex="-1" autocomplete="off"></label><span class="dt-va-err">A name and phone number are needed.</span><button class="dt-va-btn" type="submit">Request a call</button></form>',
      );
      f.addEventListener("input", function () {
        f.classList.remove("dt-va-invalid");
      });
      f.onsubmit = function (e) {
        e.preventDefault();
        var n = f.querySelector("#dt-va-c-name").value.trim(),
          p = f.querySelector("#dt-va-c-phone").value.trim(),
          t = f.querySelector("#dt-va-c-time").value.trim();
        if (!n || p.replace(/\D/g, "").length < 7) {
          f.classList.add("dt-va-invalid");
          return;
        }
        var a = state.answers;
        a.name = a.name || n;
        a.phone = p;
        a.email = a.email || "callback@no-email.invalid";
        a.callback = true;
        a.time = t;
        a.notes =
          (a.notes ? a.notes + " " : "") +
          "Callback requested from the contact card";
        if (!state.service) state.service = "other";
        f.querySelectorAll("input,button").forEach(function (i) {
          i.disabled = true;
        });
        submit(
          null,
          {
            website2: f.querySelector("#dt-va-c-hp").value,
            t: rendered,
            cf_turnstile: "",
          },
          function (ok) {
            track("sent", { ok: ok, callback: true });
            bot(
              ok
                ? "Thanks " +
                    esc(n.split(" ")[0]) +
                    ". One of the team will ring " +
                    esc(p) +
                    (t ? " " + esc(t) : "") +
                    "."
                : "I couldn't send that just now. Please ring " +
                    PHONE_LINK +
                    ' or email <a href="mailto:' +
                    C.COMPANY_EMAIL +
                    '">' +
                    C.COMPANY_EMAIL +
                    "</a>.",
              function () {
                at("done");
                clearSaved();
                setChips([{ label: "Start again", go: start }]);
              },
            );
          },
        );
      };
      body.appendChild(f);
      reveal(f);
      focusQuiet(f.querySelector("#dt-va-c-name"));
    });
  }
  STEPS.callback = callback;

  /* ---------- free text ---------- */
  function wantsCallback(t) {
    t =
      " " +
      (t || "")
        .toLowerCase()
        .replace(/[^a-z0-9' ]+/g, " ")
        .replace(/\s+/g, " ") +
      " ";
    if (
      / (what|how|when|where|who|why|do you|can i|could i) /.test(t) &&
      !/ (call|ring|phone) me /.test(t)
    )
      return false;
    return / (call me|ring me|phone me|call back|callback|ring back|call us back|give me a (call|ring|shout|bell)|(can|could|will|would) (you|someone|somebody|the team|ye) (call|ring|phone|contact) (me|us)|i('d| would) like a (call|ring)|please (call|ring|phone)|get someone to (call|ring)|talk to me|speak to me|arrange a call|book a call|request a call|a call from|someone (call|ring|phone)) /.test(
      t,
    );
  }
  function wantsWhatsApp(t) {
    if (!C.WHATSAPP) return false;
    t =
      " " +
      (t || "")
        .toLowerCase()
        .replace(/[^a-z0-9' ]+/g, " ")
        .replace(/\s+/g, " ") +
      " ";
    return / (whatsapp|whats app|text me|message me|can i text|can i message) /.test(
      t,
    );
  }
  function sendFree() {
    var v = input.value.trim();
    if (!v) return;
    input.value = "";
    input.style.height = "";
    user(v);
    if (wantsCallback(v)) {
      track("callme");
      state.pending = null;
      setChips([]);
      bot(
        "Of course. Leave a name, a number and a time that suits, and one of the team will ring you.",
        function () {
          callback();
        },
      );
      return;
    }
    if (wantsWhatsApp(v)) {
      track("whatsapp_ask");
      state.pending = null;
      setChips([]);
      bot(
        "You can WhatsApp the " +
          typeWord() +
          ' on <a href="' +
          waUrl() +
          '" target="_blank" rel="noopener">' +
          C.WHATSAPP_DISPLAY +
          "</a>; the team read it during " +
          typeWord() +
          " hours. Or I can have them ring you.",
        function () {
          setChips(
            waChip().concat([
              {
                label: "Ask the team to ring me",
                go: function () {
                  callback();
                },
              },
              {
                label: "Back to the questions",
                go: function () {
                  resume();
                },
              },
            ]),
          );
        },
      );
      return;
    }
    /* A question mid-flow goes to the answering layer, then the client is put back where they were. */
    if (
      state.step !== "open" &&
      ((state.pending && isExplicitQuestion(v)) ||
        (!state.pending && state.lastChips.length && isQuestion(v)))
    ) {
      state.lastChipsBeforeAsk = state.lastChips.slice();
      state.pendingBeforeAsk = state.pending;
      state.pending = null;
      return askModel(v, resume);
    }
    if (state.pending) {
      var p = state.pending;
      state.pending = null;
      setChips([]);
      p(v);
      return;
    }
    if (!state.service) return routeFree(v);
    if (state.step === "done" || isQuestion(v)) {
      state.lastChipsBeforeAsk = state.lastChips.slice();
      return askModel(v, resume);
    }
    state.answers.notes =
      (state.answers.notes ? state.answers.notes + " " : "") + v;
    save();
    var keep = state.lastChips.slice();
    bot("Noted, I've added that to your brief.", function () {
      setChips(keep);
    });
  }
  $("dt-va-send").onclick = sendFree;
  input.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      sendFree();
    }
  });
  input.addEventListener("input", function () {
    input.style.height = "auto";
    input.style.height = Math.min(120, input.scrollHeight) + "px";
  });
  $("dt-va-human").onclick = function () {
    user("I'd rather talk to a person");
    human();
  };
  (function () {
    var w = $("dt-va-wa");
    if (w && C.WHATSAPP) {
      w.href = waUrl();
      w.hidden = false;
      w.addEventListener("click", function () {
        track("whatsapp");
      });
    }
  })();

  /* ---------- phones: lock the page, fit the panel to the visible viewport (keyboard aware) ---------- */
  function isPhone() {
    return (
      window.innerWidth <= 600 ||
      (window.matchMedia &&
        matchMedia("(pointer:coarse)").matches &&
        window.innerWidth <= 900)
    );
  }
  var vv = window.visualViewport;
  function fit() {
    if (panel.hidden || window.innerWidth > 600) {
      panel.style.height = "";
      panel.style.top = "";
      panel.style.width = "";
      panel.style.left = "";
      return;
    }
    if (vv) {
      panel.style.height = Math.round(vv.height) + "px";
      panel.style.top = Math.round(vv.offsetTop) + "px";
      panel.style.width = Math.round(vv.width) + "px";
      panel.style.left = Math.round(vv.offsetLeft) + "px";
    }
  }
  function lock(on) {
    try {
      document.documentElement.classList.toggle(
        "dt-va-lock",
        !!on && window.innerWidth <= 600,
      );
    } catch (e) {}
    if (vv) {
      if (on) {
        vv.addEventListener("resize", fit);
        vv.addEventListener("scroll", fit);
      } else {
        vv.removeEventListener("resize", fit);
        vv.removeEventListener("scroll", fit);
      }
    }
    fit();
  }
  window.addEventListener("resize", function () {
    if (!panel.hidden) lock(true);
  });
  /* a focused field inside the panel body scrolls itself into view once the keyboard has settled */
  body.addEventListener("focusin", function (e) {
    var t = e.target;
    if (t && t.scrollIntoView) {
      setTimeout(function () {
        try {
          t.scrollIntoView({
            block: "center",
            behavior: reduced ? "auto" : "smooth",
          });
        } catch (err) {
          t.scrollIntoView();
        }
      }, 300);
    }
  });
  /* belt and braces against Lenis: wheel and touch inside the panel never reach page-level handlers */
  ["wheel", "touchmove"].forEach(function (ev) {
    panel.addEventListener(
      ev,
      function (e) {
        e.stopPropagation();
      },
      { passive: true },
    );
  });

  /* Phones: place the tab from the visible viewport so a page that overflows horizontally cannot push it off screen. */
  (function () {
    var vv = window.visualViewport;
    function place() {
      if (window.innerWidth > 600) {
        tab.style.left = "";
        return;
      }
      var w = tab.offsetWidth || 48;
      var left = vv
        ? vv.offsetLeft + (vv.width - w) / 2
        : (document.documentElement.clientWidth - w) / 2; /* centred, 17/09 */
      tab.style.left = Math.max(0, Math.round(left)) + "px";
    }
    place();
    window.addEventListener("resize", place);
    window.addEventListener("orientationchange", place);
    window.addEventListener("load", place);
    if (vv) {
      vv.addEventListener("resize", place);
      vv.addEventListener("scroll", place);
    }
    var _show = tab;
    new MutationObserver(place).observe(tab, {
      attributes: true,
      attributeFilter: ["hidden"],
    });
  })();
  /* ---------- open / close / resume ---------- */
  var lastFocus = null;
  function loadTurnstile() {
    if (
      S &&
      S.turnstile &&
      !window.turnstile &&
      !document.getElementById("dt-va-ts")
    ) {
      var sc = document.createElement("script");
      sc.id = "dt-va-ts";
      sc.src =
        "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
      sc.async = true;
      sc.defer = true;
      document.head.appendChild(sc);
    }
  }
  function offerResume(j) {
    body.innerHTML = "";
    state.started = true;
    state.service = j.service;
    state.answers = j.answers || {};
    state.history = j.history || [];
    state.transcript = j.transcript || [];
    state.step = j.step;
    var got = [j.answers && j.answers.type, j.answers && j.answers.business]
      .filter(Boolean)
      .join(", ");
    bot(
      "Welcome back. You were part-way through an enquiry" +
        (j.service ? " about " + (LABEL[j.service] || "").toLowerCase() : "") +
        (got ? " (" + esc(got) + ")" : "") +
        ". Pick up where you left off?",
      function () {
        setChips([
          {
            label: "Yes, carry on",
            go: function () {
              var fn = STEPS[j.step] || start;
              track("resume");
              fn();
            },
          },
          { label: "No, start again", go: start },
        ]);
      },
    );
  }
  function open() {
    lastFocus = document.activeElement;
    panel.hidden = false;
    tab.hidden = true;
    lock(true);
    setStatus();
    loadTurnstile();
    track("open");
    if (!state.started) {
      var j = saved();
      if (j && j.step !== "open" && j.step !== "done") offerResume(j);
      else start();
    }
    if (!isPhone())
      setTimeout(function () {
        $("dt-va-close").focus();
      }, 30);
  }
  function close() {
    panel.hidden = true;
    tab.hidden = false;
    lock(false);
    save();
    (lastFocus || tab).focus();
    track("close");
  }
  tab.onclick = open;
  $("dt-va-close").onclick = close;
  $("dt-va-restart").onclick = function () {
    track("restart");
    start();
  };
  /* Nudge: once per visitor, only while the panel is closed. */
  (function () {
    var n = $("dt-va-nudge");
    if (!n) return;
    function seen() {
      try {
        return !!localStorage.getItem("dt-va-nudged");
      } catch (e) {
        return true;
      }
    }
    function mark() {
      try {
        localStorage.setItem("dt-va-nudged", String(Date.now()));
      } catch (e) {}
    }
    function hide() {
      n.hidden = true;
    }
    var sv = saved();
    if (sv && sv.step !== "open" && sv.step !== "done") {
      /* c28: an unfinished brief in this browser. Once per tab, after 4 s, whatever the nudged flag says. */
      var once = false;
      try {
        once = !!sessionStorage.getItem("dt-va-resume-nudged");
      } catch (e) {
        once = true;
      }
      if (once) return;
      setTimeout(function () {
        if (!panel.hidden) return;
        try {
          sessionStorage.setItem("dt-va-resume-nudged", "1");
        } catch (e) {}
        $("dt-va-nudge-open").innerHTML =
          "<b>Welcome back.</b>Your brief is saved here. Want to finish it?";
        n.hidden = false;
        n.classList.add("dt-va-in");
        track("nudge", { step: "resume" });
      }, 4000);
    } else {
      if (seen()) return;
      var quick =
        /our-work|portfolio|services|contact|logo|brand|web|print/i.test(
          location.pathname,
        );
      setTimeout(
        function () {
          if (!panel.hidden || seen()) return;
          mark();
          if (!studioOpen())
            $("dt-va-nudge-open").innerHTML =
              "<b>Planning a rebrand, a brochure or a website?</b>Leave the team a brief now; they read it when the " +
              typeWord() +
              " reopens " +
              esc(reopens()) +
              ".";
          n.hidden = false;
          n.classList.add("dt-va-in");
          track("nudge");
        },
        quick ? 8000 : 20000,
      );
    }
    $("dt-va-nudge-open").onclick = function () {
      hide();
      track("nudge_open");
      open();
    };
    $("dt-va-nudge-x").onclick = function () {
      hide();
      track("nudge_dismiss");
    };
    tab.addEventListener("click", hide);
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") hide();
    });
  })();
  panel.addEventListener("keydown", function (e) {
    if (e.key === "Escape") close();
  });
  window.addEventListener("pagehide", save);
  /* Deep links: any <a href="#virtual-assistant"> opens it; ?assistant=enquiry|client opens into that door. */
  document.addEventListener("click", function (e) {
    var a =
      e.target.closest && e.target.closest('a[href="#virtual-assistant"]');
    if (a) {
      e.preventDefault();
      open();
    }
  });
  /* c28: ?ask=enquiry|client|1 (or ?assistant=) opens the assistant, into that door when named; src= or utm_* is kept as the source of the visit. */
  (function () {
    var sp;
    try {
      sp = new URLSearchParams(location.search);
    } catch (e) {
      return;
    }
    var src = sp.get("src") || sp.get("utm_campaign") || "",
      med = sp.get("utm_medium") || sp.get("utm_source") || "";
    if (src || med) {
      try {
        sessionStorage.setItem(
          "dt-va-source",
          [src, med]
            .filter(Boolean)
            .join(" / ")
            .replace(/[^\w .\/-]+/g, " ")
            .slice(0, 120),
        );
      } catch (e) {}
    }
    var d = sp.get("ask") || sp.get("assistant") || "";
    if (!d) return;
    clearSaved();
    open();
    if (/^(enquiry|client)$/.test(d))
      setTimeout(
        function () {
          user(LABEL[d]);
          service(d);
        },
        reduced ? 50 : 1200,
      );
  })();
  setStatus();
  $("dt-va-hours").textContent = C.COMPANY_PHONE_DISPLAY + " · Mon to Fri";
  /* c32: Google rating strip on the inner pages (under the shared awards row; on /contact/ under the address). Not on the homepage, which has its own. */
  (function () {
    if (!C.GOOGLE_RATING || document.querySelector(".dt-q-google, .dt-g-strip"))
      return;
    var host = document.querySelector(".post-after .dt-awards"),
      where = "afterend";
    if (!host) {
      var addr = document.querySelector(".post-content .dt-address");
      if (addr) {
        host = addr.closest(".row-inner");
      }
    }
    if (!host) return;
    var star =
        "M12 1.8l3.1 6.6 7.2.9-5.3 5 1.4 7.1L12 17.9l-6.4 3.5 1.4-7.1-5.3-5 7.2-.9z",
      p = "",
      i;
    for (i = 0; i < 5; i++)
      p += '<path d="' + star + '" transform="translate(' + i * 26 + ' 0)"/>';
    var svg =
      '<svg viewBox="0 0 128 24" width="128" height="24" focusable="false" aria-hidden="true">' +
      p +
      "</svg>";
    var pct = Math.max(
      0,
      Math.min(100, (parseFloat(C.GOOGLE_RATING) / 5) * 100),
    ).toFixed(1);
    var d = el(
      '<div class="dt-g-strip"><a href="' +
        esc(C.GOOGLE_URL || "#") +
        '" target="_blank" rel="noopener" aria-label="' +
        esc(C.COMPANY_NAME) +
        " is rated " +
        esc(C.GOOGLE_RATING) +
        " out of 5 on Google from " +
        esc(String(C.GOOGLE_REVIEWS == null ? "" : C.GOOGLE_REVIEWS)) +
        ' reviews. Opens Google in a new tab."><span class="dt-g-w">Google</span><span class="dt-g-s"><span class="dt-g-base">' +
        svg +
        '</span><span class="dt-g-fill" style="width:' +
        pct +
        '%">' +
        svg +
        '</span></span><span class="dt-g-n">' +
        esc(C.GOOGLE_RATING) +
        '</span><span class="dt-g-c">from ' +
        esc(String(C.GOOGLE_REVIEWS == null ? "" : C.GOOGLE_REVIEWS)) +
        " reviews</span></a></div>",
    );
    host.insertAdjacentElement(where, d);
    d.querySelector("a").addEventListener("click", function () {
      track("google_rating");
    });
  })();
})();
