/* ============================================================
   MAIN.JS - behavior of the website (usually no need to edit).
   Look for "TODO (PHP)" comments: those are the spots where you
   will connect the forms and lists to your PHP + MySQL back end.
   ============================================================ */
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const page = document.body.dataset.page;

/* Gray placeholder picture shown when an image file is missing */
function placeholder(label = "No photo") {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="400" height="260"><rect width="400" height="260" fill="#e2e8e4"/><text x="200" y="136" font-family="Arial" font-size="20" fill="#7b8a82" text-anchor="middle">${label}</text></svg>`;
  return "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);
}
const img = (src, alt) => `<img src="${src || placeholder(alt)}" alt="${alt}" onerror="this.onerror=null;this.src='${placeholder(alt)}'">`;
const fmtDate = d => new Date(d + "T00:00").toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" });
const fill = (sel, list, first) => { const el = $(sel); if (el) el.innerHTML = (first ? `<option value="">${first}</option>` : "") + list.map(v => `<option>${v}</option>`).join(""); };

/* ---------- Header and footer (shared by all pages) ---------- */
function renderLayout() {
  document.title = (document.title ? document.title + " | " : "") + SITE.name;
  const links = [["index.html","home","Home"],["browse.html","browse","Browse Items"],["report.html?type=lost","report","Report Lost"],["report.html?type=found","post","Post Found"],["my-posts.html","myposts","My Posts"]];
  $("#site-header").outerHTML = `<header class="site-header"><div class="container">
    <a class="brand" href="index.html">${img(SITE.logo, "Logo")}<span>${SITE.name}</span></a>
    <button class="menu-toggle" aria-label="Menu" onclick="$('.nav').classList.toggle('open')">&#9776;</button>
    <nav class="nav">${links.map(l => `<a href="${l[0]}" class="${page === l[1] ? "active" : ""}">${l[2]}</a>`).join("")}
    <a href="admin.html" class="${page === "admin" ? "active" : ""}">Admin</a>
    <a href="login.html" class="btn-nav">Login</a></nav></div></header>`;
  $("#site-footer").outerHTML = `<footer class="site-footer"><div class="container">
    <div><h4>${SITE.name}</h4><p>${SITE.footerText}</p></div>
    <div><h4>Office</h4><p>${SITE.contact.office}<br>${SITE.contact.hours}</p></div>
    <div><h4>Contact</h4><p>${SITE.contact.email}<br>${SITE.contact.phone}</p></div></div>
    <div class="copy">&copy; ${new Date().getFullYear()} ${SITE.name} &middot; ${SITE.campus}</div></footer>`;
}

/* ---------- Item card ---------- */
/* Shows the status badge only when it adds information (Claimed / Returned) */
const badges = it => `<span class="badge ${it.type}">${it.type}</span>` + (["Claimed", "Returned"].includes(it.status) ? ` <span class="badge ${it.status.toLowerCase()}">${it.status}</span>` : "");
const card = it => `<a class="item-card" href="item.html?id=${it.id}">${img(it.photo, it.title)}
  <div class="body">${badges(it)}
  <h3>${it.title}</h3><div class="meta">${it.category} &middot; ${it.location}<br>${fmtDate(it.date)}</div></div></a>`;

/* ---------- Forms: simple validation helper ---------- */
function validate(form) {
  let ok = true;
  $$("[required]", form).forEach(f => {
    const err = f.closest(".field")?.querySelector(".error");
    const bad = !f.value.trim();
    if (err) err.textContent = bad ? "This field is required." : "";
    if (bad) ok = false;
  });
  return ok;
}
function showMsg(text) { const m = $("#msg"); m.textContent = text; m.classList.add("show"); m.scrollIntoView({ behavior: "smooth", block: "center" }); }

/* ---------- Pages ---------- */
const pages = {
  home() {
    $("#hero").style.setProperty("--hero-img", `url('${new URL(SITE.hero.image, document.baseURI).href}')`);
    $("#hero-title").textContent = SITE.hero.title;
    $("#hero-text").textContent = SITE.hero.text;
    $("#hero-q").placeholder = SITE.hero.searchPlaceholder;
    $("#hero-form").addEventListener("submit", e => { e.preventDefault(); location.href = "browse.html?q=" + encodeURIComponent($("#hero-q").value); });
    $("#steps").innerHTML = SITE.steps.map((s, i) => `<div class="step"><div class="num">${i + 1}</div><h3>${s.title}</h3><p class="meta">${s.text}</p></div>`).join("");
    $("#cats").innerHTML = SITE.categories.map(c => `<a class="chip" href="browse.html?category=${encodeURIComponent(c)}">${c}</a>`).join("");
    $("#recent").innerHTML = SITE.items.slice(-4).reverse().map(card).join(""); // TODO (PHP): load latest approved posts
  },

  browse() {
    fill("#f-type", ["lost", "found"], "All types"); fill("#f-cat", SITE.categories, "All categories");
    fill("#f-loc", SITE.locations, "All locations"); fill("#f-status", SITE.statuses, "All statuses");
    const p = new URLSearchParams(location.search);
    $("#f-q").value = p.get("q") || ""; $("#f-cat").value = p.get("category") || ""; $("#f-type").value = p.get("type") || "";
    const run = () => {
      const q = $("#f-q").value.toLowerCase();
      // TODO (PHP): replace this filter with a fetch() to your search.php using the same values
      const list = SITE.items.filter(i => (!q || (i.title + i.desc).toLowerCase().includes(q)) &&
        (!$("#f-type").value || i.type === $("#f-type").value) && (!$("#f-cat").value || i.category === $("#f-cat").value) &&
        (!$("#f-loc").value || i.location === $("#f-loc").value) && (!$("#f-status").value || i.status === $("#f-status").value));
      $("#count").textContent = list.length + " item(s) found";
      $("#results").innerHTML = list.length ? list.map(card).join("") : `<p class="empty">No items match your search.</p>`;
    };
    $$(".filters input,.filters select").forEach(el => el.addEventListener("input", run)); run();
  },

  item() {
    const it = SITE.items.find(i => i.id == new URLSearchParams(location.search).get("id"));
    if (!it) { $("#detail").innerHTML = `<p class="empty">Item not found. <a href="browse.html">Back to browse</a></p>`; return; }
    document.title = it.title;
    $("#detail").innerHTML = `<div>${img(it.photo, it.title)}</div><div>
      ${badges(it)}
      <h1>${it.title}</h1><p>${it.desc}</p>
      <dl><dt>Category</dt><dd>${it.category}</dd><dt>Location</dt><dd>${it.location}</dd><dt>Date</dt><dd>${fmtDate(it.date)}</dd>
      ${it.kept ? `<dt>Kept at</dt><dd>${it.kept}</dd>` : ""}<dt>Office</dt><dd>${SITE.contact.office}<br>${SITE.contact.hours}</dd></dl>
      <div id="claim-box" class="panel"><h3>${it.type === "found" ? "Is this yours?" : "Did you find this?"}</h3>
      <p class="meta">${it.type === "found" ? "Describe details only the owner would know. The admin will verify before releasing the item." : "Tell us where you found it. The admin will contact you."}</p>
      <div id="msg" class="msg"></div>
      <form id="claim-form" novalidate><div class="field"><label for="proof">${it.type === "found" ? "Proof of ownership" : "Details"}</label>
      <textarea id="proof" rows="4" required></textarea><span class="error"></span></div>
      <button class="btn" style="margin-top:12px">Submit</button></form></div></div>`;
    if (it.status === "Claimed" || it.status === "Returned") $("#claim-box").innerHTML = `<p class="meta">This item has already been ${it.status.toLowerCase()}.</p>`;
    $("#claim-form")?.addEventListener("submit", e => { e.preventDefault();
      if (validate(e.target)) { showMsg("Submitted! The admin will review it and update your claim status."); e.target.reset(); } // TODO (PHP): POST to submit_claim.php
    });
  },

  report() {
    const type = new URLSearchParams(location.search).get("type") === "found" ? "found" : "lost";
    const lost = type === "lost";
    $("#form-title").textContent = lost ? "Report a Lost Item" : "Post a Found Item";                       // EDIT: page title
    $("#form-sub").textContent = lost ? "Tell us what you lost so others can help you find it." : "Tell us what you found so the owner can claim it."; // EDIT: subtitle
    $("#where-label").textContent = lost ? "Where did you lose it?" : "Where did you find it?";
    $("#date-label").textContent = lost ? "Date lost" : "Date found";
    $("#extra-label").textContent = lost ? "Your contact number" : "Where is the item kept now?";
    fill("#cat", SITE.categories, "Select category"); fill("#loc", SITE.locations, "Select location");
    $("#date").max = new Date().toISOString().slice(0, 10);
    $("#photo").addEventListener("change", e => { const f = e.target.files[0], pv = $("#preview");
      if (!f) { pv.style.display = "none"; return; }
      if (!/image\/(jpeg|png)/.test(f.type) || f.size > 3 * 1024 * 1024) { alert("Please choose a JPG or PNG image under 3 MB."); e.target.value = ""; pv.style.display = "none"; return; }
      pv.src = URL.createObjectURL(f); pv.style.display = "block"; });
    $("#report-form").addEventListener("submit", e => { e.preventDefault();
      if (validate(e.target)) { showMsg("Thank you! Your post was sent to the admin for review and will appear once approved."); e.target.reset(); $("#preview").style.display = "none"; } // TODO (PHP): POST (multipart) to save_item.php
    });
  },

  login() {
    $$(".tab").forEach(t => t.addEventListener("click", () => { $$(".tab").forEach(x => x.classList.toggle("active", x === t));
      $("#login-form").classList.toggle("hidden", t.dataset.tab !== "login"); $("#register-form").classList.toggle("hidden", t.dataset.tab !== "register"); $("#msg").classList.remove("show"); }));
    $("#login-form").addEventListener("submit", e => { e.preventDefault(); if (validate(e.target)) showMsg("Login form is ready. Connect it to login.php."); });  // TODO (PHP): POST to login.php
    $("#register-form").addEventListener("submit", e => { e.preventDefault();
      if (!validate(e.target)) return;
      if ($("#r-pass").value.length < 8) { $("#r-pass").nextElementSibling.textContent = "Use at least 8 characters."; return; }
      if ($("#r-pass").value !== $("#r-pass2").value) { $("#r-pass2").nextElementSibling.textContent = "Passwords do not match."; return; }
      showMsg("Registration form is ready. Connect it to register.php.");  // TODO (PHP): POST to register.php
    });
  },

  myposts() {
    $("#my-posts").innerHTML = SITE.items.filter(i => SITE.myPostIds.includes(i.id)).map(card).join("");
    $("#my-claims").innerHTML = SITE.myClaims.map(c => `<tr><td>${c.item}</td><td>${fmtDate(c.date)}</td><td><span class="badge ${c.status.toLowerCase()}">${c.status}</span></td></tr>`).join("");
    $$(".tab").forEach(t => t.addEventListener("click", () => { $$(".tab").forEach(x => x.classList.toggle("active", x === t));
      $("#tab-posts").classList.toggle("hidden", t.dataset.tab !== "posts"); $("#tab-claims").classList.toggle("hidden", t.dataset.tab !== "claims"); }));
  },

  admin() {
    const n = s => SITE.items.filter(i => i.status === s).length;
    $("#stats").innerHTML = [["Lost", n("Lost")], ["Found", n("Found")], ["Claimed", n("Claimed")], ["Returned", n("Returned")], ["Pending review", SITE.pendingPosts.length + SITE.pendingClaims.length]]
      .map(s => `<div class="stat"><b>${s[1]}</b>${s[0]}</div>`).join("");
    const row = (cells, id) => `<tr data-id="${id}">${cells.map(c => `<td>${c}</td>`).join("")}<td><button class="btn small" data-act="approved">Approve</button> <button class="btn small danger" data-act="rejected">Reject</button></td></tr>`;
    $("#pending-posts").innerHTML = SITE.pendingPosts.map(p => row([p.title, p.type, p.who, fmtDate(p.date)], p.id)).join("");
    $("#pending-claims").innerHTML = SITE.pendingClaims.map(c => row([c.item, c.who, c.proof, fmtDate(c.date)], c.id)).join("");
    document.addEventListener("click", e => { const b = e.target.closest("[data-act]"); if (!b) return;
      const tr = b.closest("tr"); tr.lastElementChild.innerHTML = `<span class="badge ${b.dataset.act}">${b.dataset.act}</span>`; }); // TODO (PHP): POST the decision to update_status.php
    $$(".tab").forEach(t => t.addEventListener("click", () => { $$(".tab").forEach(x => x.classList.toggle("active", x === t));
      $("#tab-posts").classList.toggle("hidden", t.dataset.tab !== "posts"); $("#tab-claims").classList.toggle("hidden", t.dataset.tab !== "claims"); }));
  }
};

renderLayout();
(pages[page] || (() => {}))();
