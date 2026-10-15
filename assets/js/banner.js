/*!
 * b83 Cookie Banner – Frontend.
 *
 * Ablauf:
 *  1. Gültige Einwilligung im Cookie? → Dienste laden, Banner bleibt zu.
 *  2. Sonst Banner zeigen. Erst nach einer Entscheidung wird geladen.
 *  3. Jede Entscheidung wird gespeichert (Cookie) und protokolliert (REST).
 *  4. Werden Dienste abgewählt: deren Cookies löschen und Seite neu laden.
 */
(function () {
	"use strict";

	var cfg = window.b83CookieConfig;
	if (!cfg || !cfg.services) return;

	var services = cfg.services;
	var loaded = {};
	var root, reopen;

	/* --------------------------------------------------------------
	 * Hilfen
	 * ------------------------------------------------------------ */

	function serviceIds(filter) {
		return Object.keys(services).filter(function (id) {
			return filter ? filter(services[id], id) : true;
		});
	}

	function selectableIds() {
		return serviceIds(function (s) { return !s.loader; });
	}

	function uuid() {
		if (window.crypto && typeof window.crypto.randomUUID === "function") return window.crypto.randomUUID();
		var b = new Uint8Array(16);
		(window.crypto || window.msCrypto).getRandomValues(b);
		b[6] = (b[6] & 0x0f) | 0x40;
		b[8] = (b[8] & 0x3f) | 0x80;
		var h = Array.prototype.map.call(b, function (x) { return ("0" + x.toString(16)).slice(-2); }).join("");
		return h.slice(0, 8) + "-" + h.slice(8, 12) + "-" + h.slice(12, 16) + "-" + h.slice(16, 20) + "-" + h.slice(20);
	}

	function readCookie(name) {
		var m = document.cookie.match(new RegExp("(?:^|; )" + name.replace(/[.$?*|{}()\[\]\\\/+^]/g, "\\$&") + "=([^;]*)"));
		return m ? m[1] : null;
	}

	/** Gültige Einwilligung oder null (fehlend, beschädigt oder alte Revision). */
	function getConsent() {
		try {
			var raw = readCookie(cfg.cookie);
			if (!raw) return null;
			var c = JSON.parse(decodeURIComponent(raw));
			if (!c || c.rev !== cfg.revision || !Array.isArray(c.services) || typeof c.id !== "string") return null;
			c.services = c.services.filter(function (id) { return Object.prototype.hasOwnProperty.call(services, id); });
			return c;
		} catch (e) {
			return null;
		}
	}

	function writeConsent(consent) {
		var expires = new Date(Date.now() + cfg.lifetimeDays * 864e5).toUTCString();
		var secure = location.protocol === "https:" ? "; Secure" : "";
		document.cookie = cfg.cookie + "=" + encodeURIComponent(JSON.stringify(consent)) + "; expires=" + expires + "; path=/; SameSite=Lax" + secure;
	}

	function domains() {
		var parts = location.hostname.split(".");
		var list = [""];
		for (var i = 0; i < parts.length - 1; i++) {
			var d = parts.slice(i).join(".");
			list.push(d, "." + d);
		}
		return list;
	}

	function deleteCookie(name) {
		domains().forEach(function (d) {
			document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/" + (d ? "; domain=" + d : "");
		});
	}

	function deleteCookiesByPattern(patterns) {
		if (!patterns || !patterns.length) return;
		var res = patterns.map(function (p) {
			return new RegExp("^" + p.replace(/[.+?^${}()|[\]\\]/g, "\\$&").replace(/\*/g, ".*") + "$");
		});
		document.cookie.split(";").forEach(function (part) {
			var name = part.split("=")[0].trim();
			if (name && res.some(function (re) { return re.test(name); })) deleteCookie(name);
		});
	}

	function hasCategoryConsent(list, category) {
		return list.some(function (id) { return services[id] && services[id].category === category; });
	}

	/** Ergänzt technische Lade-Dienste (Tag Manager), wenn sie gebraucht werden. */
	function withLoaders(list) {
		var out = list.filter(function (id) { return services[id] && !services[id].loader; });
		if (hasCategoryConsent(out, "statistics") || hasCategoryConsent(out, "marketing")) {
			serviceIds(function (s) { return s.loader; }).forEach(function (id) { out.push(id); });
		}
		return out;
	}

	function script(src, id) {
		if (id && document.getElementById(id)) return;
		var s = document.createElement("script");
		s.async = true;
		s.src = src;
		if (id) s.id = id;
		document.head.appendChild(s);
	}

	/** Fügt vom Administrator hinterlegten Code ein (Skripte werden ausgeführt). */
	function injectHtml(html) {
		var holder = document.createElement("div");
		holder.innerHTML = html;
		Array.prototype.slice.call(holder.childNodes).forEach(function (node) {
			if (node.nodeType === 1 && node.tagName === "SCRIPT") {
				var s = document.createElement("script");
				Array.prototype.slice.call(node.attributes).forEach(function (a) { s.setAttribute(a.name, a.value); });
				s.text = node.text || "";
				document.head.appendChild(s);
			} else if (node.nodeType === 1) {
				document.body.appendChild(node);
			}
		});
	}

	/* --------------------------------------------------------------
	 * Dienste laden
	 * ------------------------------------------------------------ */

	function updateConsentMode(list) {
		if (typeof window.gtag !== "function") return;
		var g = function (b) { return b ? "granted" : "denied"; };
		var stat = hasCategoryConsent(list, "statistics");
		var mkt = hasCategoryConsent(list, "marketing");
		window.gtag("consent", "update", {
			analytics_storage: g(stat),
			ad_storage: g(mkt),
			ad_user_data: g(mkt),
			ad_personalization: g(mkt),
			personalization_storage: g(mkt)
		});
	}

	var loaders = {
		gtm: function (c) {
			if (!c.gtm_id) return;
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push({ "gtm.start": Date.now(), event: "gtm.js" });
			script("https://www.googletagmanager.com/gtm.js?id=" + encodeURIComponent(c.gtm_id), "b83-cb-gtm");
		},
		ga4: function (c) {
			if (!c.measurement_id) return; // läuft dann über den Tag Manager
			script("https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(c.measurement_id), "b83-cb-ga4");
			window.gtag("js", new Date());
			window.gtag("config", c.measurement_id);
		},
		meta_pixel: function (c) {
			if (!c.pixel_id || window.fbq) return;
			var n = (window.fbq = function () {
				n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
			});
			if (!window._fbq) window._fbq = n;
			n.push = n;
			n.loaded = true;
			n.version = "2.0";
			n.queue = [];
			script("https://connect.facebook.net/en_US/fbevents.js", "b83-cb-meta");
			window.fbq("init", c.pixel_id);
			window.fbq("track", "PageView");
		}
	};

	function unblock(id) {
		document.querySelectorAll('[data-b83-blocked="' + id + '"]').forEach(function (box) {
			var tpl = box.querySelector("template.b83-cb-blocked__embed");
			if (!tpl) return;
			var frag = tpl.content ? tpl.content.cloneNode(true) : document.createRange().createContextualFragment(tpl.innerHTML);
			box.innerHTML = "";
			box.appendChild(frag);
			box.classList.add("is-loaded");
			box.removeAttribute("data-b83-blocked");
		});
	}

	function apply(list) {
		updateConsentMode(list);
		list.forEach(function (id) {
			if (loaded[id] || !services[id]) return;
			loaded[id] = true;
			if (loaders[id]) loaders[id](services[id].config || {});
			if (cfg.code && cfg.code[id]) injectHtml(cfg.code[id]);
			unblock(id);
		});
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({ event: "b83_consent", b83_services: list.slice() });
		document.dispatchEvent(new CustomEvent("b83:consent", { detail: { services: list.slice() } }));
	}

	/* --------------------------------------------------------------
	 * Speichern und protokollieren
	 * ------------------------------------------------------------ */

	function log(consent, action) {
		if (!cfg.restUrl || !window.fetch) return;
		try {
			fetch(cfg.restUrl, {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				credentials: "omit",
				keepalive: true,
				body: JSON.stringify({
					consent_id: consent.id,
					action: action,
					services: consent.services,
					revision: consent.rev,
					lang: cfg.lang,
					page: location.pathname
				})
			}).catch(function () {});
		} catch (e) {}
	}

	function save(list, action) {
		var previous = getConsent();
		var consent = {
			id: previous ? previous.id : uuid(),
			rev: cfg.revision,
			ts: new Date().toISOString(),
			services: withLoaders(list)
		};
		writeConsent(consent);
		log(consent, action);

		// Widerruf: abgewählte Dienste aufräumen und neu laden, damit nichts weiterläuft.
		var removed = previous ? previous.services.filter(function (id) { return consent.services.indexOf(id) === -1; }) : [];
		var wasLoaded = removed.some(function (id) { return loaded[id]; });
		removed.forEach(function (id) { deleteCookiesByPattern(services[id] && services[id].cookiePatterns); });

		hideBanner();
		if (wasLoaded) {
			location.reload();
			return;
		}
		apply(consent.services);
	}

	/* --------------------------------------------------------------
	 * Oberfläche
	 * ------------------------------------------------------------ */

	function view(name, noFocus) {
		root.querySelectorAll("[data-b83-view]").forEach(function (v) {
			v.hidden = v.getAttribute("data-b83-view") !== name;
		});
		var target = root.querySelector('[data-b83-view="' + name + '"]');
		if (noFocus) return;
		var focus = name === "settings" ? target.querySelector(".b83-cb__title") : target.querySelector("button");
		if (focus) focus.focus({ preventScroll: true });
	}

	function syncCategories() {
		root.querySelectorAll("[data-b83-cat]").forEach(function (catBox) {
			var cat = catBox.getAttribute("data-b83-cat");
			var boxes = root.querySelectorAll('[data-b83-service][data-b83-category="' + cat + '"]');
			var on = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).length;
			catBox.checked = on > 0 && on === boxes.length;
			catBox.indeterminate = on > 0 && on < boxes.length;
		});
	}

	function preset(list) {
		root.querySelectorAll("[data-b83-service]").forEach(function (box) {
			box.checked = list.indexOf(box.getAttribute("data-b83-service")) !== -1;
		});
		syncCategories();
		var current = getConsent();
		var idBox = root.querySelector(".b83-cb__id");
		if (idBox && current) {
			idBox.hidden = false;
			idBox.querySelector("[data-b83-cb-id]").textContent = current.id;
		}
	}

	function showBanner(startView, noFocus) {
		var current = getConsent();
		preset(current ? current.services : []);
		root.hidden = false;
		if (reopen) reopen.hidden = true;
		view(startView || "intro", noFocus);
	}

	function hideBanner() {
		root.hidden = true;
		if (reopen) reopen.hidden = false;
	}

	function selected() {
		return Array.prototype.filter.call(root.querySelectorAll("[data-b83-service]"), function (b) {
			return b.checked;
		}).map(function (b) { return b.getAttribute("data-b83-service"); });
	}

	function bind() {
		root.addEventListener("click", function (e) {
			var btn = e.target.closest("[data-b83-cb]");
			if (btn) {
				var a = btn.getAttribute("data-b83-cb");
				if (a === "accept") save(selectableIds(), "accept_all");
				else if (a === "reject") save([], "reject_all");
				else if (a === "save") save(selected(), "custom");
				else if (a === "settings") view("settings");
				return;
			}
			var more = e.target.closest(".b83-cb__more");
			if (more) {
				var panel = document.getElementById(more.getAttribute("aria-controls"));
				var open = more.getAttribute("aria-expanded") === "true";
				more.setAttribute("aria-expanded", open ? "false" : "true");
				if (panel) panel.hidden = open;
			}
		});

		root.addEventListener("change", function (e) {
			var t = e.target;
			if (t.hasAttribute("data-b83-cat")) {
				root.querySelectorAll('[data-b83-service][data-b83-category="' + t.getAttribute("data-b83-cat") + '"]').forEach(function (b) {
					b.checked = t.checked;
				});
			}
			if (t.hasAttribute("data-b83-service") || t.hasAttribute("data-b83-cat")) syncCategories();
		});

		document.addEventListener("click", function (e) {
			if (e.target.closest("[data-b83-cb-open]")) {
				e.preventDefault();
				showBanner("settings");
				return;
			}
			var allow = e.target.closest("[data-b83-cb-allow]");
			if (allow) {
				e.preventDefault();
				var id = allow.getAttribute("data-b83-cb-allow");
				var current = getConsent();
				var list = current ? current.services.slice() : [];
				if (list.indexOf(id) === -1) list.push(id);
				var bannerOpen = !root.hidden;
				save(list, "service");
				if (bannerOpen && !current) showBanner("intro", true); // Banner für die übrigen Dienste offen lassen.
			}
		});
	}

	/* --------------------------------------------------------------
	 * Start
	 * ------------------------------------------------------------ */

	function init() {
		root = document.getElementById("b83-cb");
		reopen = document.getElementById("b83-cb-reopen");

		// Altes Cookie aus Version 6 entfernen.
		if (cfg.legacyCookie && readCookie(cfg.legacyCookie) !== null) {
			deleteCookie(cfg.legacyCookie);
			try { window.localStorage.removeItem(cfg.legacyCookie); } catch (e) {}
		}

		if (!root) return;
		bind();

		var consent = getConsent();
		if (consent) {
			apply(consent.services);
			if (reopen) reopen.hidden = false;
		} else {
			showBanner("intro", true); // beim Seitenaufruf keinen Fokus stehlen
		}
	}

	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
	else init();
})();
