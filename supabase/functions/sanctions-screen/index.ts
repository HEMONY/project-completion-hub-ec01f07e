import { serve } from "https://deno.land/std@0.224.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.45.0";

const cors = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers":
    "authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version",
  "Access-Control-Allow-Methods": "POST, OPTIONS",
};

const json = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), {
    status,
    headers: { ...cors, "Content-Type": "application/json" },
  });

const norm = (s: string) =>
  (s || "")
    .toLowerCase()
    .normalize("NFKD")
    .replace(/[\u064B-\u065F\u0670]/g, "")
    .replace(/[^\p{L}\p{N}\s]/gu, " ")
    .replace(/\s+/g, " ")
    .trim();

// match if every significant word in input appears in candidate (or reverse)
const fuzzyMatch = (input: string, candidate: string) => {
  const a = norm(input);
  const b = norm(candidate);
  if (!a || !b) return false;
  if (a === b || a.includes(b) || b.includes(a)) return true;
  const aw = a.split(" ").filter((w) => w.length > 2);
  const bw = b.split(" ").filter((w) => w.length > 2);
  if (aw.length < 2) return false;
  const hits = aw.filter((w) => bw.some((x) => x === w)).length;
  return hits >= Math.min(3, aw.length);
};

serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: cors });
  if (req.method !== "POST") return json({ error: "Method not allowed" }, 405);

  try {
    const { persons, entityId, entityName } = await req.json() as {
      persons: { name: string; role: string; nationality?: string; emirates_id?: string }[];
      entityId?: string;
      entityName?: string;
    };

    if (!Array.isArray(persons) || persons.length === 0) {
      return json({ matches: [] });
    }

    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const supabase = createClient(supabaseUrl, serviceKey);

    const { data: list, error } = await supabase
      .from("sanctions_list")
      .select("id, english_name, arabic_name, country, list_reference")
      .eq("status", "active");

    if (error) return json({ error: error.message }, 500);

    const matches: any[] = [];
    for (const p of persons) {
      if (!p.name?.trim()) continue;
      for (const s of list ?? []) {
        if (fuzzyMatch(p.name, s.english_name) || (s.arabic_name && fuzzyMatch(p.name, s.arabic_name))) {
          matches.push({
            person_name: p.name,
            person_role: p.role,
            matched_english: s.english_name,
            matched_arabic: s.arabic_name,
            country: s.country,
            reference: s.list_reference,
            sanctions_id: s.id,
          });
          break;
        }
      }
    }

    // If matches found, log notification + flag entity
    if (matches.length > 0 && entityId) {
      await supabase.from("admin_notifications").insert({
        entity_id: entityId,
        type: "sanctions_match",
        severity: "critical",
        title: `🚨 Sanctions match: ${entityName ?? "Entity"}`,
        message: `${matches.length} person(s) matched the sanctions list and were rejected.`,
        details: { matches, entity_name: entityName },
      });

      await supabase
        .from("entities")
        .update({
          sanctions_match: true,
          sanctions_match_details: matches,
          application_status: "rejected",
          rejection_reason: `Sanctions list match — ${matches.map((m) => m.person_name).join(", ")}`,
        })
        .eq("id", entityId);
    }

    return json({ matches, count: matches.length });
  } catch (err) {
    return json({ error: err instanceof Error ? err.message : String(err) }, 500);
  }
});
