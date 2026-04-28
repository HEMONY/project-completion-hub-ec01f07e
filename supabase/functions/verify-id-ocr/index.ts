import { serve } from "https://deno.land/std@0.168.0/http/server.ts";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version",
};

const normalizeName = (value: string) =>
  value.toLowerCase().replace(/[^\p{L}\p{N}\s]/gu, " ").replace(/\s+/g, " ").trim();

const namesMatch = (expected: string, extracted: string) => {
  const a = normalizeName(expected);
  const b = normalizeName(extracted);
  if (!a || !b) return false;
  if (a === b || b.includes(a) || a.includes(b)) return true;
  const matches = a.split(" ").filter((part) => part.length > 2 && b.includes(part)).length;
  return matches >= Math.min(2, a.split(" ").length);
};

serve(async (req) => {
  if (req.method === "OPTIONS") return new Response(null, { headers: corsHeaders });

  try {
    const { expectedName, fileBase64, mimeType } = await req.json();
    if (!expectedName || !fileBase64 || !mimeType) {
      return new Response(JSON.stringify({ error: "Missing required OCR input" }), { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } });
    }
    if (!String(mimeType).startsWith("image/")) {
      return new Response(JSON.stringify({ error: "OCR currently supports JPG and PNG identity images" }), { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } });
    }

    const apiKey = Deno.env.get("LOVABLE_API_KEY");
    if (!apiKey) throw new Error("LOVABLE_API_KEY is not configured");

    const response = await fetch("https://ai.gateway.lovable.dev/v1/chat/completions", {
      method: "POST",
      headers: { Authorization: `Bearer ${apiKey}`, "Content-Type": "application/json" },
      body: JSON.stringify({
        model: "google/gemini-2.5-flash",
        messages: [
          { role: "system", content: "Extract identity document text. Return JSON only: {\"full_name\": string, \"id_number\": string, \"expiry_date\": string, \"confidence\": number}. Use empty strings when unclear." },
          { role: "user", content: [
            { type: "text", text: "Read this UAE Emirates ID or passport image and extract the visible holder name." },
            { type: "image_url", image_url: { url: `data:${mimeType};base64,${fileBase64}` } },
          ] },
        ],
        response_format: { type: "json_object" },
      }),
    });

    if (!response.ok) {
      const text = await response.text();
      const status = response.status === 429 ? 429 : response.status === 402 ? 402 : 500;
      return new Response(JSON.stringify({ error: status === 429 ? "OCR rate limit exceeded" : status === 402 ? "OCR service needs credits" : `OCR failed: ${text}` }), { status, headers: { ...corsHeaders, "Content-Type": "application/json" } });
    }

    const data = await response.json();
    const raw = data.choices?.[0]?.message?.content ?? "{}";
    const extracted = JSON.parse(raw);
    const fullName = String(extracted.full_name ?? "");

    return new Response(JSON.stringify({
      extracted,
      expectedName,
      name_match: namesMatch(expectedName, fullName),
    }), { headers: { ...corsHeaders, "Content-Type": "application/json" } });
  } catch (error) {
    return new Response(JSON.stringify({ error: error instanceof Error ? error.message : "Unknown OCR error" }), { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } });
  }
});