/* CHECKMATE - profile menu (shared by all student pages)
   Clicking the 3 dots next to the profile opens a small menu with
   "Change Password" and "Log out".
   The styles are injected here, so no CSS file needs editing. */

(() => {
    "use strict";

    if (window.__checkmateUserMenu) return;
    window.__checkmateUserMenu = true;

    const DEFAULT_LOGOUT_URL = "../../authentication/Login/logout.php";
    const DEFAULT_CHANGE_PASSWORD_URL = "change_password.php";

    const style = document.createElement("style");
    style.textContent = `
        .cm-user-menu {
            position: fixed;
            z-index: 99998;
            box-sizing: border-box;
            padding: 0;
            overflow: hidden;
            border: 1px solid #71582d;
            border-radius: 5px;
            background: #181816;
            box-shadow: 0 10px 28px rgba(0, 0, 0, .5);
            font-family: Arial, Helvetica, sans-serif;
        }
        .cm-user-menu[hidden] { display: none; }
        .cm-user-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 18px;
            color: #e7e0d5;
            font-size: 13px;
            line-height: 18px;
            text-decoration: none;
            white-space: nowrap;
        }
        .cm-user-menu a + a {
            border-top: 1px solid #2a2418;
        }
        .cm-user-menu a:focus {
            outline: none;
        }
        .cm-user-menu a:hover,
        .cm-user-menu a:focus-visible {
            background: #292217;
            color: #d4a657;
        }
        .cm-user-menu .cm-menu-icon {
            width: 16px;
            text-align: center;
            color: #d4a657;
            font-size: 14px;
        }
    `;
    document.head.appendChild(style);

    function makeItem(href, iconText, labelText) {
        const link = document.createElement("a");
        link.href = href;
        link.setAttribute("role", "menuitem");

        const icon = document.createElement("span");
        icon.className = "cm-menu-icon";
        icon.setAttribute("aria-hidden", "true");
        icon.textContent = iconText;

        const label = document.createElement("span");
        label.textContent = labelText;

        link.append(icon, label);
        return link;
    }

    function init() {
        const button = document.querySelector(".user-more");

        if (!button) return;

        const menu = document.createElement("div");
        menu.className = "cm-user-menu";
        menu.id = "cmUserMenu";
        menu.setAttribute("role", "menu");
        menu.hidden = true;

        // Change Password goes first (above Log out)
        const changePassword = makeItem(
            button.dataset.changePassword || DEFAULT_CHANGE_PASSWORD_URL,
            "⚿",
            "Change Password"
        );

        const logout = makeItem(
            button.dataset.logout || DEFAULT_LOGOUT_URL,
            "→",
            "Log out"
        );

        menu.append(changePassword, logout);
        document.body.appendChild(menu);

        button.setAttribute("aria-haspopup", "menu");
        button.setAttribute("aria-expanded", "false");
        button.setAttribute("aria-controls", "cmUserMenu");

        function place() {
            const rect = button.getBoundingClientRect();

            menu.style.visibility = "hidden";
            menu.hidden = false;

            // line the menu up with the profile card (like the admin popup):
            // same left/right edges (slightly inset) and opens upward
            const card = button.closest(".sidebar-user") || button;
            const cardRect = card.getBoundingClientRect();

            const width = Math.max(cardRect.width - 10, 170);
            menu.style.width = width + "px";

            const height = menu.offsetHeight;

            let left = cardRect.left + 5;
            let top = cardRect.top - height - 10;

            left = Math.max(8, Math.min(left, window.innerWidth - width - 8));

            if (top < 8) {
                top = rect.bottom + 8;
            }

            menu.style.left = left + "px";
            menu.style.top = top + "px";
            menu.style.visibility = "visible";
        }

        function open(fromKeyboard) {
            place();
            button.setAttribute("aria-expanded", "true");

            // only move focus into the menu for keyboard users,
            // so mouse users don't see an item highlighted without hovering
            if (fromKeyboard) {
                changePassword.focus();
            }
        }

        function close() {
            menu.hidden = true;
            button.setAttribute("aria-expanded", "false");
        }

        button.addEventListener("click", function (event) {
            event.stopPropagation();

            if (menu.hidden) {
                open(event.detail === 0);
            } else {
                close();
            }
        });

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
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();