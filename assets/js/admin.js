/*!
 * b83 Cookie Banner – Backend.
 * Eigene Dienste, Farbwähler und Live-Vorschau im Tab „Design“.
 * Die Farbrechnung entspricht Frontend\Design::vars() in PHP.
 */
(function ($) {
	"use strict";
	var t = window.b83CookieAdmin || {};
	var counter = 0;

	/* ---------- Farbrechnung (wie in PHP) ---------- */
	function rgb(hex) {
		hex = String(hex || "").replace("#", "");
		if (hex.length === 3) hex = hex.replace(/(.)/g, "$1$1");
		if (!/^[0-9a-f]{6}$/i.test(hex)) return [0, 0, 0];
		return [0, 2, 4].map(function (i) { return parseInt(hex.substr(i, 2), 16); });
	}
	function mix(a, b, w) {
		var x = rgb(a), y = rgb(b);
		return "#" + [0, 1, 2].map(function (i) {
			var v = Math.max(0, Math.min(255, Math.round(x[i] * w + y[i] * (1 - w))));
			return ("0" + v.toString(16)).slice(-2);
		}).join("");
	}
	function darken(hex, amount) { return mix("#000000", hex, amount); }

	/* ---------- Vorschau ---------- */
	function field(name) {
		var $f = $('[name="b83_cb[design][' + name + ']"]');
		return $f.length ? String($f.val() || "").trim() : "";
	}

	function updatePreview() {
		var root = document.documentElement;
		var accent = String($('[name="b83_cb[accent_color]"]').val() || "#d96f21");
		var bg = field("box_bg") || "#ffffff";
		var text = field("box_text") || "#1f2328";
		var btnBg = field("btn_bg") || accent;
		var btnHbg = field("btn_hover_bg") || darken(btnBg, 0.12);
		var px = function (n) { return (parseInt(n, 10) || 0) + "px"; };
		var radius = parseInt(field("btn_radius"), 10) || 0;

		var vars = {
			accent: accent,
			bg: bg,
			text: text,
			muted: mix(text, bg, 0.72),
			line: mix(text, bg, 0.14),
			soft: mix(text, bg, 0.04),
			border: field("box_border_color"),
			"border-w": px(field("box_border_width")),
			radius: px(field("box_radius")),
			shadow: (t.shadows || {})[field("box_shadow")] || "none",
			width: px(field("box_width")),
			pad: px(field("box_padding")),
			font: (t.fonts || {})[field("font")] || "inherit",
			"btn-radius": radius >= (t.pill || 30) ? "999px" : radius + "px",
			"btn-bw": px(field("btn_border_width")),
			"btn-bg": btnBg,
			"btn-text": field("btn_text"),
			"btn-border": field("btn_border") || btnBg,
			"btn-hbg": btnHbg,
			"btn-htext": field("btn_hover_text"),
			"btn-hborder": field("btn_hover_border") || btnHbg,
			"btn2-bg": field("btn2_bg"),
			"btn2-text": field("btn2_text"),
			"btn2-border": field("btn2_border"),
			"btn2-hbg": field("btn2_hover_bg"),
			"btn2-htext": field("btn2_hover_text"),
			"btn2-hborder": field("btn2_hover_border")
		};
		Object.keys(vars).forEach(function (k) {
			if (vars[k]) root.style.setProperty("--b83-cb-" + k, vars[k]);
		});
	}

	$(function () {
		var hasPreview = $(".b83-cb-preview-stage").length > 0;
		var schedule = function () { if (hasPreview) setTimeout(updatePreview, 0); };

		if ($.fn.wpColorPicker) {
			$(".b83-cb-color").wpColorPicker({ change: schedule, clear: schedule });
		}

		$(document).on("input change", "[data-b83-design], [name='b83_cb[accent_color]']", function () {
			if (this.type === "range") {
				$(this).next("output").text(this.value + " " + ($(this).data("unit") || ""));
			}
			schedule();
		});

		$(".b83-cb-preview-stage").on("click", "a, button", function (e) { e.preventDefault(); });
		schedule();

		/* ---------- Eigene Dienste ---------- */
		$(".b83-cb-add-custom").on("click", function () {
			var tpl = $("#b83-cb-custom-template").html();
			counter++;
			var $row = $(tpl.replace(/__KEY__/g, "n" + counter));
			$(".b83-cb-custom-list").append($row);
			$row.find("input[type=text]").first().trigger("focus");
		});

		$(document).on("click", ".b83-cb-remove-custom", function () {
			if (window.confirm(t.confirmRemove)) $(this).closest(".b83-cb-custom").remove();
		});

		$(".b83-cb-new-revision").on("click", function (e) {
			if (!window.confirm(t.confirmRevision)) e.preventDefault();
		});
	});
})(jQuery);
