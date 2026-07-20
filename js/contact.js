(() => {
    const form = document.getElementById("contact-form");
    const status = document.getElementById("contact-status");
    const submitButton = document.getElementById("contact-submit");
    const turnstileContainer = document.getElementById("turnstile-container");
    let turnstileWidgetId = null;
    let turnstileRequired = false;

    if (!form || !status || !submitButton) return;

    const showStatus = (type, message) => {
        status.className = `alert alert-${type} mt-3`;
        status.textContent = message;
        status.hidden = false;
    };

    const loadTurnstile = async () => {
        if (!turnstileContainer) return;

        try {
            const configResponse = await fetch("/api/contact-config", {
                headers: { Accept: "application/json" },
            });
            if (!configResponse.ok) return;

            const config = await configResponse.json();
            if (!config.turnstileSiteKey) return;
            turnstileRequired = true;

            const script = document.createElement("script");
            script.src = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
            script.async = true;
            script.defer = true;
            script.addEventListener("load", () => {
                turnstileWidgetId = window.turnstile.render(turnstileContainer, {
                    sitekey: config.turnstileSiteKey,
                    theme: "light",
                });
            });
            document.head.appendChild(script);
        } catch {
            // The form remains usable when Turnstile has not been configured.
        }
    };

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        if (
            turnstileRequired &&
            !form.querySelector('input[name="cf-turnstile-response"]')?.value
        ) {
            showStatus("warning", "Please complete the security check before submitting.");
            return;
        }

        showStatus("info", "Sending your enquiry...");
        submitButton.disabled = true;
        submitButton.textContent = "Sending...";

        try {
            const response = await fetch(form.action, {
                method: "POST",
                body: new FormData(form),
                headers: { Accept: "application/json" },
            });
            const result = await response.json().catch(() => ({}));

            if (!response.ok || !result.ok) {
                throw new Error(
                    result.message ||
                        "We could not send your enquiry. Please contact us by email or WhatsApp.",
                );
            }

            form.reset();
            showStatus("success", result.message);
        } catch (error) {
            showStatus(
                "danger",
                error.message ||
                    "We could not send your enquiry. Please contact us by email or WhatsApp.",
            );
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = "Send Enquiry";
            if (turnstileWidgetId !== null && window.turnstile) {
                window.turnstile.reset(turnstileWidgetId);
            }
            status.focus();
        }
    });

    loadTurnstile();
})();
