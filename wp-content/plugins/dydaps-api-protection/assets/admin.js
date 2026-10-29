(function () {
	function setActiveTab(tab) {
		var tabs = Array.prototype.slice.call(document.querySelectorAll("[role=tab]"));
		var index;

		for (index = 0; index < tabs.length; index++) {
			var active = tabs[index] === tab;
			var panel = document.getElementById(tabs[index].getAttribute("aria-controls"));

			tabs[index].setAttribute("aria-selected", active ? "true" : "false");
			tabs[index].setAttribute("tabindex", active ? "0" : "-1");
			if (panel) {
				panel.hidden = !active;
			}
		}
	}

	function copyText(target) {
		var value = target.value || target.textContent || "";

		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(value.trim());
		}

		var selection = window.getSelection();
		var range = document.createRange();
		range.selectNodeContents(target);
		selection.removeAllRanges();
		selection.addRange(range);
		var copied = document.execCommand("copy");
		selection.removeAllRanges();
		return copied ? Promise.resolve() : Promise.reject(new Error("Copy unavailable"));
	}

	function filterRows(inputSelector, rowSelector) {
		var input = document.querySelector(inputSelector);
		if (!input) {
			return;
		}
		var query = input.value.trim().toLowerCase();
		var rows = document.querySelectorAll(rowSelector);
		var index;

		for (index = 0; index < rows.length; index++) {
			var text = rows[index].getAttribute("data-search") || "";
			rows[index].hidden = query !== "" && text.indexOf(query) === -1;
		}
	}

	document.addEventListener("click", function (event) {
		var tab = event.target.closest("[role=tab]");
		var tabLink = event.target.closest("[data-dydaps-tab-link]");
		var copyButton = event.target.closest("[data-dydaps-copy-target]");

		if (tab) {
			setActiveTab(tab);
			return;
		}
		if (tabLink) {
			var targetTab = document.getElementById("dydaps-tab-" + tabLink.getAttribute("data-dydaps-tab-link"));
			if (targetTab) {
				setActiveTab(targetTab);
				targetTab.focus();
			}
		}
		if (copyButton) {
			var target = document.querySelector(copyButton.getAttribute("data-dydaps-copy-target"));
			if (target && window.dydapsApiProtectionAdmin) {
				var defaultLabel = copyButton.getAttribute("data-dydaps-default-label") || copyButton.textContent;
				copyText(target).then(function () {
					copyButton.textContent = window.dydapsApiProtectionAdmin.copiedLabel;
					window.clearTimeout(copyButton._dydapsCopyTimer);
					copyButton._dydapsCopyTimer = window.setTimeout(function () {
						copyButton.textContent = defaultLabel;
					}, 1600);
				});
			}
		}
	});

	document.addEventListener("keydown", function (event) {
		var tab = event.target.closest("[role=tab]");
		if (!tab) {
			return;
		}
		var tabs = Array.prototype.slice.call(document.querySelectorAll("[role=tab]"));
		var index = tabs.indexOf(tab);
		if (event.key === "ArrowRight") {
			index = (index + 1) % tabs.length;
		} else if (event.key === "ArrowLeft") {
			index = (index + tabs.length - 1) % tabs.length;
		} else if (event.key === "Home") {
			index = 0;
		} else if (event.key === "End") {
			index = tabs.length - 1;
		} else {
			return;
		}
		event.preventDefault();
		setActiveTab(tabs[index]);
		tabs[index].focus();
	});

	document.addEventListener("input", function (event) {
		if (event.target.matches("[data-dydaps-log-search]")) {
			filterLogs();
		} else if (event.target.matches("[data-dydaps-endpoint-search]")) {
			filterRows("[data-dydaps-endpoint-search]", "[data-dydaps-endpoint-row]");
		}
	});

	document.addEventListener("change", function (event) {
		if (event.target.matches("[data-event-age-select]")) {
			var selectedOption = event.target.options[event.target.selectedIndex];
			var eventCount = document.querySelector("[data-event-count]");
			if (selectedOption && eventCount) {
				eventCount.textContent = selectedOption.getAttribute("data-count") || "0";
			}
		}
	});
})();
