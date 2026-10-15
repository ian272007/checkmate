/* =========================================================
   CHECKMATE: styled confirm dialog (replaces the browser's confirm())
   Put this file in: asset/js/cm_confirm.js

   Usage (it returns a Promise, so use .then or await):

     cmConfirm({
         title: "Warn ian francisco?",
         message: "Line one.\n\nLine two (a blank line makes a new paragraph).",
         confirmText: "Send warning",
         cancelText: "Cancel",
         tone: "warn"          // "warn" (gold) or "danger" (red)
     }).then(function (ok) {
         if (ok) { ... }
     });
   ========================================================= */

(function () {
    let current = null;

    window.cmConfirm = function (options) {
        const opts = options || {};

        // only one dialog at a time
        if (current) {
            current.close(false);
        }

        return new Promise(function (resolve) {
            const tone = opts.tone === "danger" ? "danger" : "warn";
            const previousFocus = document.activeElement;

            const overlay = document.createElement("div");
            overlay.className = "cmc-overlay";

            const dialog = document.createElement("div");
            dialog.className = "cmc-dialog " + tone;
            dialog.setAttribute("role", "alertdialog");
            dialog.setAttribute("aria-modal", "true");
            dialog.setAttribute("aria-labelledby", "cmcTitle");
            dialog.setAttribute("aria-describedby", "cmcMessage");

            const icon = document.createElement("div");
            icon.className = "cmc-icon";
            icon.textContent = tone === "danger" ? "✕" : "!";

            const label = document.createElement("span");
            label.className = "cmc-label";
            label.textContent = opts.label || (tone === "danger" ? "CONFIRM ACTION" : "PLEASE CONFIRM");

            const title = document.createElement("h3");
            title.id = "cmcTitle";
            title.textContent = opts.title || "Are you sure?";

            const message = document.createElement("div");
            message.id = "cmcMessage";
            message.className = "cmc-message";

            String(opts.message || "")
                .split(/\n\s*\n/)
                .forEach(function (text) {
                    if (text.trim() === "") {
                        return;
                    }
                    const p = document.createElement("p");
                    p.textContent = text.trim();
                    message.appendChild(p);
                });

            const buttons = document.createElement("div");
            buttons.className = "cmc-buttons";

            const cancel = document.createElement("button");
            cancel.type = "button";
            cancel.className = "cmc-btn";
            cancel.textContent = opts.cancelText || "Cancel";

            const confirm = document.createElement("button");
            confirm.type = "button";
            confirm.className = "cmc-btn cmc-btn-confirm";
            confirm.textContent = opts.confirmText || "Confirm";

            buttons.append(cancel, confirm);
            dialog.append(icon, label, title, message, buttons);
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            document.body.classList.add("cmc-open");

            function close(result) {
                if (!current) {
                    return;
                }

                current = null;
                document.removeEventListener("keydown", onKey, true);
                overlay.classList.add("hide");
                document.body.classList.remove("cmc-open");

                setTimeout(function () {
                    overlay.remove();
                }, 160);

                if (previousFocus && typeof previousFocus.focus === "function") {
                    previousFocus.focus();
                }

                resolve(result);
            }

            function onKey(event) {
                if (event.key === "Escape") {
                    event.preventDefault();
                    event.stopPropagation();
                    close(false);
                    return;
                }

                // keep Tab inside the dialog
                if (event.key === "Tab") {
                    event.preventDefault();
                    (document.activeElement === cancel ? confirm : cancel).focus();
                }
            }

            cancel.addEventListener("click", function () {
                close(false);
            });

            confirm.addEventListener("click", function () {
                close(true);
            });

            overlay.addEventListener("mousedown", function (event) {
                if (event.target === overlay) {
                    close(false);
                }
            });

            document.addEventListener("keydown", onKey, true);

            current = { close: close };

            // safer default: focus "Cancel" so a stray Enter doesn't confirm
            cancel.focus();
        });
    };
})();