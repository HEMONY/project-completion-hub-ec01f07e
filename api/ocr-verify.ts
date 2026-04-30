import { serve } from "https://deno.land/std@0.168.0/http/server.ts";

const cors = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: cors });

  try {
    const { imageB64, imageMime, enteredName, type } = await req.json();
    const apiKey = Deno.env.get("ANTHROPIC_API_KEY");
    if (!apiKey) return new Response(JSON.stringify({ error: "No API key" }), { status: 500, headers: cors });

    const prompt = type === "license"
      ? `UAE Trade License. Return ONLY valid JSON no extra text:
{"extractedName":"<trade name>","licenseNumber":"<no>","issueDate":"<DD/MM/YYYY>","expiryDate":"<DD/MM/YYYY>","legalType":"<type>","match":<true if matches "${enteredName}" ignoring case/spaces>}`
      : `UAE Emirates ID or passport. Return ONLY valid JSON no extra text:
{"extractedName":"<full English name>","idNumber":"<ID>","dateOfBirth":"<DD/MM/YYYY>","issuingDate":"<DD/MM/YYYY>","expiryDate":"<DD/MM/YYYY>","nationality":"<nat>","match":<true if all words in "${enteredName}" appear in extracted name ignoring case>}`;

    const claude = await fetch("https://api.anthropic.com/v1/messages", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "x-api-key": apiKey,
        "anthropic-version": "2023-06-01",
      },
      body: JSON.stringify({
        model: "claude-sonnet-4-6",
        max_tokens: 512,
        messages: [{
          role: "user",
          content: [
            { type: "image", source: { type: "base64", media_type: imageMime ?? "image/jpeg", data: imageB64 } },
            { type: "text", text: prompt },
          ],
        }],
      }),
    });

    if (!claude.ok) {
      const err = await claude.text();
      return new Response(JSON.stringify({ error: "Claude failed", detail: err }), { status: 502, headers: cors });
    }

    const claudeData = await claude.json();
    const raw = (claudeData.content?.[0]?.text ?? "").replace(/```json|```/g, "").trim();
    const parsed = JSON.parse(raw);

    return new Response(JSON.stringify({
      match: parsed.match === true,
      extractedName: parsed.extractedName ?? "",
      dates: {
        ...(parsed.issueDate     && { issue_date:     parsed.issueDate }),
        ...(parsed.issuingDate   && { issuing_date:   parsed.issuingDate }),
        ...(parsed.expiryDate    && { expiry_date:    parsed.expiryDate }),
        ...(parsed.dateOfBirth   && { dob:            parsed.dateOfBirth }),
        ...(parsed.licenseNumber && { license_number: parsed.licenseNumber }),
        ...(parsed.idNumber      && { id_number:      parsed.idNumber }),
        ...(parsed.legalType     && { legal_type:     parsed.legalType }),
      },
    }), { headers: { ...cors, "Content-Type": "application/json" } });

  } catch (err) {
    return new Response(JSON.stringify({ error: String(err) }), { status: 500, headers: cors });
  }
});
