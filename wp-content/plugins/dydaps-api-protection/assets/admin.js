(function () {
	function getDirectChildren(checkbox) {
		var id = checkbox.id;
		if (!id) {
			return [];
		}

		return Array.prototype.slice.call(
			document.querySelectorAll("[data-parent-checkbox=" + JSON.stringify("#" + id) + "]")
		);
	}

	function applyToDescendants(checkbox, checked) {
		var children = getDirectChildren(checkbox);
		var i;
		var li;

		for (i = 0; i < children.length; i++) {
			children[i].checked = checked;
			children[i].indeterminate = false;
			li = children[i].closest("li");

			if (li) {
				li.classList.toggle("is-inherited", checked);
			}

			applyToDescendants(children[i], checked);
		}
	}

	function updateAncestors(checkbox) {
		var parentSelector = checkbox.getAttribute("data-parent-checkbox");
		var parent;
		var siblings;
		var checkedCount;
		var indeterminateCount;
		var i;

		if (!parentSelector) {
			return;
		}

		parent = document.querySelector(parentSelector);
		if (!parent) {
			return;
		}

		siblings = Array.prototype.slice.call(
			document.querySelectorAll("[data-parent-checkbox=" + JSON.stringify(parentSelector) + "]")
		);
		checkedCount = 0;
		indeterminateCount = 0;

		for (i = 0; i < siblings.length; i++) {
			if (siblings[i].checked) {
				checkedCount++;
			} else if (siblings[i].indeterminate) {
				indeterminateCount++;
			}
		}

		if (checkedCount === 0 && indeterminateCount === 0) {
			parent.checked = false;
			parent.indeterminate = false;
		} else {
			parent.checked = false;
			parent.indeterminate = true;
		}

		updateAncestors(parent);
	}

	function setAllRoutes(checked) {
		var all = Array.prototype.slice.call(
			document.querySelectorAll("[data-dydaps-route-checkbox]")
		);
		var i;
		var li;

		for (i = 0; i < all.length; i++) {
			all[i].checked = checked;
			all[i].indeterminate = false;
			li = all[i].closest("li");

			if (li) {
				li.classList.toggle(
					"is-inherited",
					checked && !!all[i].getAttribute("data-parent-checkbox")
				);
			}
		}
	}

	function initInheritedState() {
		var allWithParent = Array.prototype.slice.call(
			document.querySelectorAll("[data-parent-checkbox]")
		);
		var i;
		var parentSelector;
		var parent;
		var li;

		for (i = 0; i < allWithParent.length; i++) {
			li = allWithParent[i].closest("li");
			if (!li) {
				continue;
			}

			parentSelector = allWithParent[i].getAttribute("data-parent-checkbox");
			parent = document.querySelector(parentSelector);

			if (parent && parent.checked && allWithParent[i].checked) {
				li.classList.add("is-inherited");
			}
		}

		for (i = allWithParent.length - 1; i >= 0; i--) {
			if (!allWithParent[i].checked && !allWithParent[i].indeterminate) {
				continue;
			}

			parentSelector = allWithParent[i].getAttribute("data-parent-checkbox");
			parent = document.querySelector(parentSelector);

			if (parent && !parent.checked) {
				parent.indeterminate = true;
			}
		}
	}

	document.addEventListener("click", function (event) {
		var copyButton = event.target.closest("[data-dydaps-copy-target]");
		var toggleButton;
		var resetLabel;
		var text;
		var target;

		if (copyButton) {
			target = document.querySelector(copyButton.getAttribute("data-dydaps-copy-target"));
			if (target && navigator.clipboard && window.dydapsApiProtectionAdmin) {
				text = target.value || target.textContent || "";
				resetLabel = copyButton.getAttribute("data-dydaps-default-label") || copyButton.textContent;
				navigator.clipboard.writeText(text.trim()).then(function () {
					copyButton.textContent = window.dydapsApiProtectionAdmin.copiedLabel;
					window.clearTimeout(copyButton._dydapsCopyTimer);
					copyButton._dydapsCopyTimer = window.setTimeout(function () {
						copyButton.textContent = resetLabel;
					}, 1600);
				});
			}
			return;
		}

		toggleButton = event.target.closest("[data-dydaps-toggle-all]");
		if (toggleButton) {
			setAllRoutes(toggleButton.getAttribute("data-dydaps-toggle-all") === "check");
		}
	});

	document.addEventListener("change", function (event) {
		var checkbox = event.target;

		if (checkbox.type !== "checkbox" || !checkbox.hasAttribute("data-dydaps-route-checkbox")) {
			return;
		}

		applyToDescendants(checkbox, checkbox.checked);
		updateAncestors(checkbox);
	});

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initInheritedState);
	} else {
		initInheritedState();
	}
})();
