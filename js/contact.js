(() => {
    const copyButton = document.getElementById("copy-sales-email");
    const status = document.getElementById("copy-email-status");

    if (!copyButton || !status) return;

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

    copyButton.addEventListener("click", async () => {
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
})();
