(function () {
    "use strict";

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

        document.addEventListener("click", function (event) {
            if (!menu.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                setOpen(false);
            }
        });
    }


    var root = document.documentElement;

    function measureScrollbar() {
        var width = window.innerWidth - root.clientWidth;

        if (width < 0) {
            width = 0;
        }

        root.style.setProperty("--scrollbar-w", width + "px");
    }

    measureScrollbar();

    window.addEventListener("load", measureScrollbar);
    window.addEventListener("resize", measureScrollbar);

    if (window.ResizeObserver) {
        new ResizeObserver(measureScrollbar).observe(root);
    }
})();