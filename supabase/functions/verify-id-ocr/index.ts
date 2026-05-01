import { serve } from "https://deno.land/std@0.224.0/http/server.ts";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers":
    "authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version",
  "Access-Control-Allow-Methods": "POST, OPTIONS",
};

type OcrType = "id" | "license";

const jsonResponse = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { ...corsHeaders, "Content-Type": "application/json" },
  });

const normalizeName = (value: string) =>
  value
    .toLowerCase()
    .replace(/[^\p{L}\p{N}\s]/gu, " ")
    .replace(/\s+/g, " ")
    .trim();

const namesMatch = (expectedName: string, extractedName: string) => {
  const expected = normalizeName(expectedName);
  const extracted = normalizeName(extractedName);
  if (!expected || !extracted) return false;
  if (expected === extracted || extracted.includes(expected) || expected.includes(extracted)) return true;

  const expectedWords = expected.split(" ").filter((word) => word.length > 1);
  if (expectedWords.length === 0) return false;
  return expectedWords.every((word) => extracted.includes(word));
};

const extractJsonObject = (text: string) => {
  const cleaned = text.replace(/```json|```/g, "").trim();
  const start = cleaned.indexOf("{");
  const end = cleaned.lastIndexOf("}");
  if (start === -1 || end === -1 || end <= start) throw new Error("OCR model did not return JSON");
  return JSON.parse(cleaned.slice(start, end + 1));
};

const readBody = async (req: Request) => {
  const contentType = req.headers.get("content-type") ?? "";

  if (contentType.includes("multipart/form-data")) {
    const form = await req.formData();
    return Object.fromEntries(form.entries());
  }

  const rawBody = await req.text();
  if (!rawBody.trim()) return {};

  try {
    return JSON.parse(rawBody);
  } catch {
    throw new Error(`Invalid JSON body: ${rawBody.slice(0, 100)}`);
  }
};

serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  if (req.method !== "POST") {
    return jsonResponse({ error: "Method not allowed" }, 405);
  }

  try {
    const body = await readBody(req) as Record<string, unknown>;
    // ── AI Audit (text-only request) ─────────────────────────────────────────
    if (body.prompt && !body.imageB64 && !body.image && !body.imageBase64) {
      const apiKey = Deno.env.get("ANTHROPIC_API_KEY");
      if (!apiKey) return jsonResponse({ error: "No API key" }, 500);
    
      const claude = await fetch("https://api.anthropic.com/v1/messages", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-api-key": apiKey,
          "anthropic-version": "2023-06-01",
        },
        body: JSON.stringify({
          model: "claude-sonnet-4-6",
          max_tokens: 800,
          messages: [{ role: "user", content: String(body.prompt) }],
        }),
      });
    
      if (!claude.ok) {
        const detail = await claude.text();
        return jsonResponse({ error: "Claude failed", detail }, 502);
      }
    
      const data = await claude.json();
      const text = data.content?.find((c: { type?: string; text?: string }) => c.type === "text")?.text ?? "";
      return jsonResponse({ text });
    }
    // ─────────────────────────────────────────────────────────────────────────

    const rawImage =
      body.imageB64 ??
      body.imageBase64 ??
      body.image ??
      body.fileBase64 ??
      body.file ??
      body.data ??
      null;

    const rawMime =
      body.imageMime ??
      body.mimeType ??
      body.mime_type ??
      body.fileType ??
      body.typeMime ??
      "image/jpeg";

    const enteredName = String(body.enteredName ?? body.expectedName ?? body.name ?? "").trim();
    const type = (body.type === "license" ? "license" : "id") as OcrType;

    if (typeof rawImage !== "string" || !rawImage.trim()) {
      return jsonResponse({ error: "Missing required OCR input", receivedKeys: Object.keys(body) }, 400);
    }

    let imageBase64 = rawImage.trim();
    let mimeType = String(rawMime || "image/jpeg").split(";")[0].trim().toLowerCase();

    const dataUrlMatch = imageBase64.match(/^data:([^;]+);base64,(.*)$/s);
    if (dataUrlMatch) {
      mimeType = dataUrlMatch[1].toLowerCase();
      imageBase64 = dataUrlMatch[2].trim();
    }

    if (mimeType === "image/jpg") mimeType = "image/jpeg";
    if (mimeType === "application/x-pdf") mimeType = "application/pdf";

    const allowedImageTypes = ["image/jpeg", "image/png", "image/gif", "image/webp"];
    const isPdf = mimeType === "application/pdf";
    if (!isPdf && !allowedImageTypes.includes(mimeType)) {
      return jsonResponse({ error: "Unsupported file type", mimeType }, 400);
    }

    if (!enteredName) {
      return jsonResponse({ error: "Missing enteredName" }, 400);
    }

    const apiKey = Deno.env.get("ANTHROPIC_API_KEY");
    if (!apiKey) {
      return jsonResponse({ error: "OCR service is not configured" }, 500);
    }

    const prompt = type === "license"
      ? `You are reading a UAE Trade License or Professional License (image or PDF). Extract the trade name and return ONLY valid JSON, no markdown and no extra text:\n{"extractedName":"<trade name exactly as printed>","licenseNumber":"<license number>","issueDate":"<DD/MM/YYYY if visible>","expiryDate":"<DD/MM/YYYY if visible>","legalType":"<legal type if visible>","match":false}\nExpected trade name: ${enteredName}`
      : `You are reading a UAE Emirates ID or passport (image or PDF). Extract the English full name and return ONLY valid JSON, no markdown and no extra text:\n{"extractedName":"<full English name exactly as printed>","idNumber":"<ID number if visible>","dateOfBirth":"<DD/MM/YYYY if visible>","issuingDate":"<DD/MM/YYYY if visible>","expiryDate":"<DD/MM/YYYY if visible>","nationality":"<nationality if visible>","match":false}\nExpected name: ${enteredName}`;

    const mediaBlock = isPdf
      ? { type: "document", source: { type: "base64", media_type: "application/pdf", data: imageBase64 } }
      : { type: "image", source: { type: "base64", media_type: mimeType, data: imageBase64 } };

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
            mediaBlock,
            { type: "text", text: prompt },
          ],
        }],
      }),
    });

    if (!claude.ok) {
      const detail = await claude.text();
      return jsonResponse({ error: "OCR provider failed", detail }, 502);
    }

    const claudeData = await claude.json();
    const rawText = claudeData.content?.find((item: { type?: string; text?: string }) => item.type === "text")?.text ?? claudeData.content?.[0]?.text ?? "";
    const parsed = extractJsonObject(rawText);
    const extractedName = String(parsed.extractedName ?? "").trim();
    const match = namesMatch(enteredName, extractedName);

    return jsonResponse({
      match,
      extractedName,
      dates: {
        ...(parsed.issueDate && { issue_date: parsed.issueDate }),
        ...(parsed.issuingDate && { issuing_date: parsed.issuingDate }),
        ...(parsed.expiryDate && { expiry_date: parsed.expiryDate }),
        ...(parsed.dateOfBirth && { dob: parsed.dateOfBirth }),
        ...(parsed.licenseNumber && { license_number: parsed.licenseNumber }),
        ...(parsed.idNumber && { id_number: parsed.idNumber }),
        ...(parsed.legalType && { legal_type: parsed.legalType }),
      },
    });
  } catch (err) {
    return jsonResponse({ error: err instanceof Error ? err.message : String(err) }, 500);
  }
});
