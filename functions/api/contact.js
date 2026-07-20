const json = (body, status = 200) =>
    new Response(JSON.stringify(body), {
        status,
        headers: {
            "content-type": "application/json; charset=utf-8",
            "cache-control": "no-store",
        },
    });

const clean = (value, maxLength) =>
    String(value || "")
        .replace(/\u0000/g, "")
        .trim()
        .slice(0, maxLength);

const isAllowedOrigin = (request) => {
    const origin = request.headers.get("origin");
    if (!origin) return true;

    try {
        const hostname = new URL(origin).hostname;
        return (
            hostname === "ianproject.com" ||
            hostname === "www.ianproject.com" ||
            hostname === "127.0.0.1" ||
            hostname === "localhost" ||
            hostname.endsWith(".my-website-9tm.pages.dev")
        );
    } catch {
        return false;
    }
};

const verifyTurnstile = async (request, env, token) => {
    const secret = clean(env.TURNSTILE_SECRET_KEY, 500);
    if (!secret) return true;
    if (!token) return false;

    try {
        const verification = await fetch(
            "https://challenges.cloudflare.com/turnstile/v0/siteverify",
            {
                method: "POST",
                body: new URLSearchParams({
                    secret,
                    response: token,
                    remoteip: clean(request.headers.get("CF-Connecting-IP"), 100),
                }),
            },
        );
        const result = await verification.json();
        return verification.ok && result.success === true;
    } catch (error) {
        console.error("Turnstile verification failed.", error);
        return false;
    }
};

async function handlePost(context) {
    const { request, env } = context;

    if (!isAllowedOrigin(request)) {
        return json({ ok: false, message: "This form submission was not accepted." }, 403);
    }

    let formData;
    try {
        formData = await request.formData();
    } catch {
        return json({ ok: false, message: "We could not read this enquiry." }, 400);
    }

    // Hidden field: real visitors never fill this in.
    if (clean(formData.get("company_website"), 200)) {
        return json({ ok: true, message: "Thank you. Your enquiry has been received." });
    }

    const name = clean(formData.get("name"), 100);
    const email = clean(formData.get("email"), 200).toLowerCase();
    const project = clean(formData.get("project"), 200) || "Not specified";
    const requirements = clean(formData.get("requirements"), 4000);
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!name || !emailPattern.test(email) || !requirements) {
        return json(
            {
                ok: false,
                message: "Please provide your name, a valid email address and your requirements.",
            },
            400,
        );
    }

    const isHuman = await verifyTurnstile(
        request,
        env,
        clean(formData.get("cf-turnstile-response"), 2048),
    );
    if (!isHuman) {
        return json(
            {
                ok: false,
                message: "Please complete the security check and submit the form again.",
            },
            400,
        );
    }

    const webhookUrl = clean(env.LARK_WEBHOOK_URL, 1000);
    if (
        !webhookUrl ||
        !/^https:\/\/(?:open\.larksuite\.com|open\.feishu\.cn)\/open-apis\/bot\/v2\/hook\//.test(
            webhookUrl,
        )
    ) {
        console.error("LARK_WEBHOOK_URL is missing or invalid.");
        return json(
            {
                ok: false,
                message:
                    "Online enquiries are being configured. Please email info@ianproject.com or contact us on WhatsApp.",
            },
            503,
        );
    }

    const submittedAt = new Date().toISOString();
    const country = clean(request.cf?.country, 10) || "Unknown";
    const message = [
        "New Website Enquiry",
        "",
        `Name: ${name}`,
        `Email: ${email}`,
        `Project / Product: ${project}`,
        `Country: ${country}`,
        `Submitted: ${submittedAt}`,
        "",
        "Requirements:",
        requirements,
    ].join("\n");

    try {
        const larkResponse = await fetch(webhookUrl, {
            method: "POST",
            headers: { "content-type": "application/json; charset=utf-8" },
            body: JSON.stringify({
                msg_type: "text",
                content: { text: message },
            }),
        });

        const responseBody = await larkResponse.json().catch(() => ({}));
        const larkCode = responseBody.code ?? responseBody.StatusCode ?? 0;
        if (!larkResponse.ok || Number(larkCode) !== 0) {
            console.error("Lark notification failed.", larkResponse.status, larkCode);
            return json(
                {
                    ok: false,
                    message:
                        "We could not send your enquiry right now. Please email info@ianproject.com or contact us on WhatsApp.",
                },
                502,
            );
        }
    } catch (error) {
        console.error("Lark notification request failed.", error);
        return json(
            {
                ok: false,
                message:
                    "We could not send your enquiry right now. Please email info@ianproject.com or contact us on WhatsApp.",
            },
            502,
        );
    }

    return json({
        ok: true,
        message: "Thank you. Your enquiry has been sent successfully.",
    });
}

export function onRequest(context) {
    if (context.request.method === "POST") {
        return handlePost(context);
    }
    return json({ ok: false, message: "Method not allowed." }, 405);
}
