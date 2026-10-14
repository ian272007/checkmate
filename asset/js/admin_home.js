/* =========================================
   CHECKMATE ADMIN HOME
   admin_home.js

   The sidebar links and Quick access cards are normal
   links now, so they need no JavaScript.
   This file only handles the three-dot account menu.
   ========================================= */

(function () {
    "use strict";

    var button = document.getElementById("accountMenuButton");
    var menu = document.getElementById("accountMenu");

    if (!button || !menu) {
        return;
    }

    // put the menu above the three dots (they sit at the bottom of the sidebar)
    function place() {
        var rect = button.getBoundingClientRect();

        menu.style.visibility = "hidden";
        menu.hidden = false;

        var width = menu.offsetWidth;
        var height = menu.offsetHeight;

        var left = rect.right - width;
        var top = rect.top - height - 8;

        left = Math.max(8, Math.min(left, window.innerWidth - width - 8));

        if (top < 8) {
            top = rect.bottom + 8;
        }

        menu.style.left = left + "px";
        menu.style.top = top + "px";
        menu.style.visibility = "visible";
    }

    function open() {
        place();
        button.setAttribute("aria-expanded", "true");
    }

    function close() {
        menu.hidden = true;
        button.setAttribute("aria-expanded", "false");
    }

    button.addEventListener("click", function (event) {
        event.stopPropagation();

        if (menu.hidden) {
            open();
        } else {
            close();
        }
    });

    // clicking anywhere else, pressing Esc, resizing or scrolling closes it
    document.addEventListener("click", function (event) {
        if (!menu.hidden && !menu.contains(event.target)) {
            close();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && !menu.hidden) {
            close();
            button.focus();
        }
    });

    window.addEventListener("resize", close);
    window.addEventListener("scroll", close, true);
})();