export function onRequest(context) {
    if (context.request.method !== "GET") {
        return new Response("Method not allowed.", {
            status: 405,
            headers: { Allow: "GET" },
        });
    }

    return new Response(
        JSON.stringify({
            turnstileSiteKey: String(context.env.TURNSTILE_SITE_KEY || "").trim() || null,
        }),
        {
            headers: {
                "content-type": "application/json; charset=utf-8",
                "cache-control": "public, max-age=300",
            },
        },
    );
}
