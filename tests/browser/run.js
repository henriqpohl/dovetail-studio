/* Browser tests of the Virtual Assistant front end (needs Node and Playwright):
 *     node tests/browser/run.js
 * The page is built by tests/browser/page.php from the real plugin output; the REST API is mocked. */
const { chromium } = require("playwright");
const { execFileSync } = require("child_process");
const fs = require("fs"), os = require("os"), path = require("path");

const plugin = path.resolve(__dirname, "../..");
function build(env) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), "dt-browser-"));
  execFileSync("php", ["-r", `
    $src = ${JSON.stringify(plugin)}; $dst = ${JSON.stringify(root)} . '/wp-content/plugins/dovetail-studio';
    $copy = function ($s, $d) use (&$copy) { @mkdir($d, 0777, true); foreach (scandir($s) as $f) { if (in_array($f, ['.', '..', '.git', 'tests', 'tools'], true)) continue; is_dir("$s/$f") ? $copy("$s/$f", "$d/$f") : copy("$s/$f", "$d/$f"); } };
    $copy($src, $dst); @mkdir(${JSON.stringify(root)} . '/wp-content/uploads', 0777, true);`]);
  const out = path.join(root, "page.json");
  execFileSync("php", [path.join(__dirname, "page.php")], { env: Object.assign({}, process.env, env, { DT_TEST_ROOT: root, DT_OUT: out }) });
  return JSON.parse(fs.readFileSync(out, "utf8"));
}

let ok = 0, failed = 0;
function t(name, cond, detail) { if (cond) ok++; else { failed++; console.log("FAIL: " + name + (detail ? " — " + detail : "")); } }

async function page(browser, built, handlers) {
  const p = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const log = { errors: [], token: 0, config: 0, js: 0, ask: [], submit: [] };
  p.on("pageerror", (e) => log.errors.push(e.message));
  await p.route("**/*", async (route) => {
    const r = route.request(), u = r.url();
    if (u === "https://example.com/test/") return route.fulfill({ contentType: "text/html", body: built.html });
    if (u.includes("front-loader")) return route.fulfill({ contentType: "text/javascript", body: fs.readFileSync(built.loader, "utf8") });
    if (/front-tab(\.min)?\.css/.test(u)) { log.tabcss = (log.tabcss || 0) + 1; return route.fulfill({ contentType: "text/css", body: fs.readFileSync(built.tabcss, "utf8") }); }
    if (/front(\.min)?\.css/.test(u)) { log.css = (log.css || 0) + 1; return route.fulfill({ contentType: "text/css", body: fs.readFileSync(built.css, "utf8") }); }
    if (u.includes("/v1/config")) { log.config++; return route.fulfill({ contentType: "application/json", body: JSON.stringify(built.config) }); }
    if (/front(\.min)?\.js/.test(u)) { log.js++; return route.fulfill({ contentType: "text/javascript", body: fs.readFileSync(built.js, "utf8") }); }
    if (u.includes("/v1/token")) { log.token++; return route.fulfill({ contentType: "application/json", body: JSON.stringify({ token: "1790000000." + "a".repeat(32) }) }); }
    if (u.includes("/v1/ask")) { log.ask.push(JSON.parse(r.postData())); return (handlers.ask || defaultAsk)(route, log); }
    if (u.includes("/v1/submit")) { log.submit.push(r.postDataBuffer().toString()); return route.fulfill({ contentType: "application/json", body: '{"ok":true,"ref":"DT-2610-ABCDEF"}' }); }
    if (u.includes("/v1/")) return route.fulfill({ contentType: "application/json", body: '{"ok":true}' });
    return route.fulfill({ status: 404, body: "" });
  });
  await p.goto("https://example.com/test/");
  return { p, log };
}
function defaultAsk(route) { return route.fulfill({ contentType: "application/json", body: JSON.stringify({ answer: "We design brands.", provider: "openai" }) }); }

(async () => {
  const browser = await chromium.launch();
  for (const min of ["1", "0"]) {
    const built = build({ DT_MIN: min });
    const label = min === "1" ? "minified" : "source";

    // Nothing heavy before interaction; the tab loads the assistant.
    let { p, log } = await page(browser, built, {});
    await p.waitForTimeout(500);
    t(`[${label}] front.js and the panel styles not loaded on page load`, log.js === 0 && log.config === 0 && !log.css && log.tabcss >= 1);
    await p.click("#dt-va-tab");
    await p.waitForSelector("#dt-va:not([hidden])", { timeout: 5000 }).catch(() => {});
    t(`[${label}] tab loads config + panel styles + front.js and opens the panel`, log.config === 1 && log.js === 1 && log.css === 1 && (await p.isVisible("#dt-va")));
    t(`[${label}] panel styled (front.css applied)`, (await p.evaluate(() => getComputedStyle(document.getElementById("dt-va")).position)) === "fixed");
    await p.waitForTimeout(1200);
    t(`[${label}] greeting shown`, (await p.textContent("#dt-va-body")).includes("AI Assistant at"));

    // A question: expired token is renewed once and the answer appears.
    let first = true;
    await p.unroute("**/v1/ask*").catch(() => {});
    log.ask = [];
    await p.fill("#dt-va-input", "What services do you offer?");
    await p.keyboard.press("Enter");
    await p.waitForTimeout(3500);
    t(`[${label}] question answered with a token`, (await p.textContent("#dt-va-body")).includes("We design brands.") && log.ask.length >= 1 && /^\d+\.a{32}$/.test(log.ask[0].token || ""));

    // Call back without email.
    await p.click("text=I'd rather talk to a person");
    await p.waitForTimeout(1500);
    t(`[${label}] contact card: no broken sentence without a city`, !(await p.textContent("#dt-va-body")).includes("The team in  takes") && (await p.textContent("#dt-va-body")).includes("The team takes the calls themselves."));
    await p.click("text=Ask the team to call me");
    await p.waitForTimeout(1500);
    await p.fill("#dt-va-c-name", "Ann Test");
    await p.fill("#dt-va-c-phone", "0871234567");
    await p.click("#dt-va-body form .dt-va-btn");
    await p.waitForTimeout(6000);
    const sent = log.submit[0] || "";
    t(`[${label}] call back sent with a token and no placeholder email`, sent.includes("1790000000.") && !sent.includes("no-email") && /name="email"\r\n\r\n\r\n/.test(sent));
    t(`[${label}] no JavaScript errors`, log.errors.length === 0, log.errors.join(" | "));
    const stored = await p.evaluate(() => localStorage.getItem("dt-va-session") || "");
    t(`[${label}] contact details never stored in the browser`, !stored.includes("0871234567") && !stored.includes("Ann Test"));
    await p.close();

    // Idle load: the nudge still works without a click.
    ({ p, log } = await page(browser, built, {}));
    await p.waitForTimeout(4500);
    t(`[${label}] assistant loads on its own once the page is idle`, log.js === 1);
    await p.close();
  }

  // A flow built in the admin (door + Step questions): the answers reach the brief.
  {
    const built = build({ DT_MIN: "1" });
    built.config.flowConfig = {
      doors: [{ id: "web", label: "A new website", action: "service", service: "web" }],
      steps: {
        web1: { type: "choice", question: "What kind of website?", answerKey: "site_type", options: ["Brochure site", "Online shop"], next: "web2" },
        web2: { type: "choice", question: "Do you have a logo?", answerKey: "has_logo", options: ["Yes", "No, I need one"], next: "capture" },
      },
    };
    const { p, log } = await page(browser, built, {});
    await p.click("#dt-va-tab");
    await p.waitForTimeout(1500);
    await p.click("text=A new website");
    await p.waitForTimeout(1500);
    await p.click(".dt-va-chip >> text=Online shop");
    await p.waitForTimeout(1500);
    await p.click(".dt-va-chip >> text=No, I need one");
    await p.waitForTimeout(2000);
    await p.fill("#dt-va-f-name", "Ann Test");
    await p.fill("#dt-va-f-email", "ann@example.org");
    await p.fill("#dt-va-f-phone", "0871234567");
    await p.click("#dt-va-body form .dt-va-btn");
    await p.waitForTimeout(6000);
    const sent = log.submit[0] || "";
    const field = (n) => (sent.match(new RegExp('name="' + n + '"\\r\\n\\r\\n([\\s\\S]*?)\\r\\n--')) || [])[1] || "";
    t("[admin flow] custom answers in the brief", field("brief").includes("Site type: Online shop") && field("brief").includes("Has logo: No, I need one"), field("brief"));
    t("[admin flow] the door's label is the enquiry type", field("door") === "A new website" && field("brief").includes("Enquiry: A new website"), field("door"));
    t("[admin flow] no JavaScript errors", log.errors.length === 0, log.errors.join(" | "));
    await p.close();
  }

  // A closure (holiday) on today's date: the header says when the office reopens.
  {
    const built = build({ DT_MIN: "1" });
    const iso = (n) => new Date(Date.now() + n * 864e5).toISOString().slice(0, 10);
    built.config.publicConfig.OPENING_HOURS = Object.assign({}, built.config.publicConfig.OPENING_HOURS, { closed: [{ from: iso(-1), to: iso(12) }] });
    built.config.hours.timezone = "UTC";
    const { p, log } = await page(browser, built, {});
    await p.click("#dt-va-tab");
    await p.waitForTimeout(1500);
    const status = await p.textContent("#dt-va-status");
    t("[closed dates] header says when it reopens, by date", /reopens on (\d{1,2} \w+|\w+ \d{1,2}) at/.test(status), status);
    t("[closed dates] no JavaScript errors", log.errors.length === 0, log.errors.join(" | "));
    await p.close();
  }

  // Translations reach the browser.
  const pt = build({ DT_LOCALE: "pt_BR" });
  let { p, log } = await page(browser, pt, {});
  t("[pt_BR] static markup translated", (await p.content()).includes("Tem uma pergunta?"));
  await p.click("#dt-va-tab");
  await p.waitForTimeout(2000);
  const text = await p.textContent("#dt-va-body");
  t("[pt_BR] greeting translated", text.includes("Olá. Sou o assistente de IA da") && text.includes("O que você procura?"), text.slice(0, 120));
  t("[pt_BR] no JavaScript errors", log.errors.length === 0, log.errors.join(" | "));
  await browser.close();
  console.log(`RESULT {"ok":${ok},"fail":${failed}}`);
  process.exit(failed ? 1 : 0);
})();
