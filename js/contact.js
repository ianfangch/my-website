(() => {
    const copyButton = document.getElementById("copy-sales-email");
    const status = document.getElementById("copy-email-status");

    const fallbackCopy = (value) => {
        const field = document.createElement("textarea");
        field.value = value;
        field.setAttribute("readonly", "");
        field.style.position = "fixed";
        field.style.opacity = "0";
        document.body.appendChild(field);
        field.select();
        const copied = document.execCommand("copy");
        field.remove();
        return copied;
    };

    if (copyButton && status) copyButton.addEventListener("click", async () => {
        const email = copyButton.dataset.email;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(email);
            } else if (!fallbackCopy(email)) {
                throw new Error("Copy failed");
            }
            status.textContent = "Email address copied.";
        } catch {
            status.textContent = `Please copy manually: ${email}`;
        }
    });

    const form = document.getElementById("quote-builder-form");
    const formStatus = document.getElementById("quote-builder-status");
    if (!form || !formStatus) return;

    const requestedCategory = new URLSearchParams(window.location.search).get("category");
    if (requestedCategory) {
        const categoryField = form.elements.category;
        const matchingOption = Array.from(categoryField.options).find((option) => option.value.toLowerCase() === requestedCategory.toLowerCase());
        if (matchingOption) categoryField.value = matchingOption.value;
    }

    const buildMessage = () => {
        const data = new FormData(form);
        return [
            "Hello IanProject team,",
            "",
            "I would like to request a quotation.",
            "",
            `Name: ${data.get("name") || ""}`,
            `Company: ${data.get("company") || ""}`,
            `Country / market: ${data.get("country") || ""}`,
            `Product category: ${data.get("category") || ""}`,
            `Project type: ${data.get("project") || ""}`,
            `Estimated quantity: ${data.get("quantity") || ""}`,
            `Delivery destination: ${data.get("destination") || ""}`,
            `Target delivery date: ${data.get("date") || ""}`,
            `Required model / finish: ${data.get("finish") || ""}`,
            `Packaging requirement: ${data.get("packaging") || ""}`,
            `Inspection requirement: ${data.get("inspection") || ""}`,
            "",
            "Requirements:",
            data.get("message") || "",
            "",
            "Please advise the suitable products, MOQ, indicative pricing and lead time."
        ].join("\n");
    };

    const openChannel = (channel) => {
        if (!form.reportValidity()) {
            formStatus.textContent = "Please complete the required fields.";
            return;
        }
        const message = buildMessage();
        const url = channel === "whatsapp"
            ? `https://wa.me/8619956229033?text=${encodeURIComponent(message)}`
            : `mailto:sales@ianproject.com?subject=${encodeURIComponent("Project Quote Request - IanProject")}&body=${encodeURIComponent(message)}`;
        formStatus.textContent = channel === "whatsapp" ? "Opening WhatsApp…" : "Opening your email application…";
        window.location.href = url;
    };

    form.addEventListener("submit", (event) => {
        event.preventDefault();
        openChannel("email");
    });
    form.querySelector('[data-quote-channel="whatsapp"]').addEventListener("click", () => openChannel("whatsapp"));
})();
