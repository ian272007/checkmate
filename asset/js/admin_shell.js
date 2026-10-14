/* =========================================
   CHECKMATE ADMIN SHELL
   admin_shell.js  (loaded by partials/admin_shell.php)

   1. Opens and closes the three-dot account menu
      (Change Password / Log out) on every admin page.
   2. Measures the page scrollbar so the bell in the top bar
      stays in the same spot on every page.

   The menu listener runs first (capture) and stops the click,
   so it still works if a page's own JavaScript also has
   old account-menu code. Nothing is toggled twice.
   ========================================= */

(function () {
    "use strict";

    /* ---------- 1. ACCOUNT MENU (3 dots) ---------- */

    var button = document.getElementById("accountMenuButton");
    var menu = document.getElementById("accountMenu");

    if (button && menu) {

        var setOpen = function (open) {
            menu.classList.toggle("show", open);
            button.classList.toggle("open", open);
            button.setAttribute("aria-expanded", open ? "true" : "false");
        };

        button.addEventListener("click", function (event) {
            event.stopImmediatePropagation();
            event.stopPropagation();

            setOpen(!menu.classList.contains("show"));
        }, true);

        // clicking anywhere else closes it
        document.addEventListener("click", function (event) {
            if (!menu.contains(event.target)) {
                setOpen(false);
            }
        });

        // pressing Esc closes it
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                setOpen(false);
            }
        });
    }


    /* ---------- 2. SCROLLBAR WIDTH (for the bell position) ---------- */

    var root = document.documentElement;

    function measureScrollbar() {
        // window width minus the width the page can really use = scrollbar
        var width = window.innerWidth - root.clientWidth;

        if (width < 0) {
            width = 0;
        }

        root.style.setProperty("--scrollbar-w", width + "px");
    }

    measureScrollbar();

    window.addEventListener("load", measureScrollbar);
    window.addEventListener("resize", measureScrollbar);

    // some pages fill their tables with JavaScript after loading, so the
    // scrollbar can appear later. Measure again whenever the page size changes.
    if (window.ResizeObserver) {
        new ResizeObserver(measureScrollbar).observe(root);
    }
})();