/*!
 * b83 Cookie Banner – Backend.
 */
(function ($) {
	"use strict";
	var t = window.b83CookieAdmin || {};
	var counter = 0;

	$(function () {
		if ($.fn.wpColorPicker) $(".b83-cb-color").wpColorPicker();

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
