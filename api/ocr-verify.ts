import type { VercelRequest, VercelResponse } from "@vercel/node";

export default async function handler(req: VercelRequest, res: VercelResponse) {
  res.setHeader("Access-Control-Allow-Origin", "*");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type");

  if (req.method === "OPTIONS") return res.status(200).end();
  if (req.method !== "POST") return res.status(405).json({ error: "Method not allowed" });

  const apiKey = process.env.ANTHROPIC_API_KEY;
  if (!apiKey) return res.status(500).json({ error: "API key not configured" });

  const { imageB64, imageMime, enteredName, type } = req.body;

  const prompt = type === "license"
    ? `This is a UAE Trade License document. Return ONLY valid JSON no extra text:
{"extractedName":"<trade name>","licenseNumber":"<no>","issueDate":"<DD/MM/YYYY>","expiryDate":"<DD/MM/YYYY>","legalType":"<type>","match":<true if matches "${enteredName}" ignoring case/spaces>}`
    : `This is a UAE Emirates ID or passport. Return ONLY valid JSON no extra text:
{"extractedName":"<full English name>","idNumber":"<ID>","dateOfBirth":"<DD/MM/YYYY>","issuingDate":"<DD/MM/YYYY>","expiryDate":"<DD/MM/YYYY>","nationality":"<nat>","match":<true if all words in "${enteredName}" appear in extracted name ignoring case>}`;

  try {
    const claudeResp = await fetch("https://api.anthropic.com/v1/messages", {
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

    const data = await claudeResp.json();
    const raw = (data.content?.[0]?.text ?? "").replace(/```json|```/g, "").trim();
    const parsed = JSON.parse(raw);

    return res.status(200).json({
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
    });
  } catch (err) {
    return res.status(500).json({ error: "Internal error" });
  }
}
