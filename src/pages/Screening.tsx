import { useEffect, useState } from "react";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { useI18n } from "@/lib/i18n";
import { useAuth, useRole } from "@/lib/auth";
import { supabase } from "@/integrations/supabase/client";
import { ShieldCheck, Search } from "lucide-react";
import { toast } from "sonner";

export default function Screening() {
  const { t } = useI18n();
  const { user } = useAuth();
  const { role, roleLoading } = useRole();
  const [entities, setEntities] = useState<any[]>([]);
  const [entityId, setEntityId] = useState<string>("");
  const [name, setName] = useState("");
  const [nameType, setNameType] = useState("Owner");
  const [results, setResults] = useState<any[]>([]);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!user) return;
    supabase.from("entities").select("id, entity_name").order("created_at", { ascending: false }).then(({ data }) => setEntities(data ?? []));
  }, [user]);

  useEffect(() => {
    if (!entityId) return setResults([]);
    supabase.from("screening_results").select("*").eq("entity_id", entityId).order("screened_at", { ascending: false }).then(({ data }) => setResults(data ?? []));
  }, [entityId]);

  const runScreen = async () => {
    if (!user || !entityId || !name.trim()) return;
    setBusy(true);
    // Search sanctions list for fuzzy match
    const { data: hits } = await supabase
      .from("sanctions_list")
      .select("english_name, arabic_name")
      .or(`english_name.ilike.%${name}%,arabic_name.ilike.%${name}%`)
      .limit(5);
    const ai_result = hits && hits.length > 0
      ? (hits.some((h) => h.english_name?.toLowerCase() === name.toLowerCase()) ? "confirmed" : "partial")
      : "no-match";
    const { error } = await supabase.from("screening_results").insert({
      user_id: user.id,
      entity_id: entityId,
      name_to_screen: name.trim(),
      name_type: nameType,
      ai_result,
      notes: hits && hits.length > 0 ? `Matched: ${hits.map((h) => h.english_name).join(", ")}` : null,
    });
    setBusy(false);
    if (error) toast.error(error.message);
    else {
      toast.success(t("saved"));
      setName("");
      supabase.from("screening_results").select("*").eq("entity_id", entityId).order("screened_at", { ascending: false }).then(({ data }) => setResults(data ?? []));
    }
  };
  // فحص تلقائي لكل الأسماء المرتبطة بكيان
  const runBulkScreen = async () => {
    if (!entityId || !user) return;

    // جلب بيانات الكيان
    const { data: entityData } = await supabase
      .from("entities")
      .select("entity_name, shareholders, ubos, management_control")
      .eq("id", entityId)
      .single();

    if (!entityData) return;

    const namesToScreen: { name: string; type: string }[] = [];

    // اسم الكيان نفسه
    if (entityData.entity_name) {
      namesToScreen.push({ name: entityData.entity_name, type: "Owner" });
    }

    // المساهمون
    const shareholders: any[] = Array.isArray(entityData.shareholders) ? entityData.shareholders : [];
    shareholders.forEach((s) => {
      if (s.name) namesToScreen.push({ name: s.name, type: "Shareholder" });
    });

    // UBOs
    const ubos: any[] = Array.isArray(entityData.ubos) ? entityData.ubos : [];
    ubos.forEach((u) => {
      if (u.name) namesToScreen.push({ name: u.name, type: "UBO" });
    });

    if (namesToScreen.length === 0) {
      toast.error("لا توجد أسماء لفحصها في هذا الكيان");
      return;
    }

    setBusy(true);
    let screened = 0;

    for (const item of namesToScreen) {
      const { data: hits } = await supabase
        .from("sanctions_list")
        .select("english_name, arabic_name")
        .or(`english_name.ilike.%${item.name}%,arabic_name.ilike.%${item.name}%`)
        .limit(5);

      const ai_result = hits && hits.length > 0
        ? (hits.some((h) => h.english_name?.toLowerCase() === item.name.toLowerCase()) ? "confirmed" : "partial")
        : "no-match";

      await supabase.from("screening_results").insert({
        user_id: user.id,
        entity_id: entityId,
        name_to_screen: item.name,
        name_type: item.type,
        ai_result,
        notes: hits && hits.length > 0
          ? `Matched: ${hits.map((h) => h.english_name).join(", ")}`
          : null,
      });
      screened++;
    }

    // تحديث حالة الفحص في entities
    await supabase
      .from("entities")
      .update({ screening_completed: true })
      .eq("id", entityId);

    setBusy(false);
    toast.success(`تم فحص ${screened} اسم بنجاح`);

    // تحديث النتائج
    supabase
      .from("screening_results")
      .select("*")
      .eq("entity_id", entityId)
      .order("screened_at", { ascending: false })
      .then(({ data }) => setResults(data ?? []));
  };
  // Guard: للمشرفين فقط
  if (!roleLoading && !["admin", "auditor", "moderator"].includes(role)) {
    return (
      <AppShell>
        <div className="max-w-md mx-auto text-center py-24 space-y-4">
          <div className="text-5xl">🔒</div>
          <h2 className="text-xl font-bold">غير مصرح بالوصول</h2>
          <p className="text-muted-foreground">صفحة الفحص متاحة للمشرفين فقط.</p>
        </div>
      </AppShell>
    );
  }
  return (
    <AppShell>
      <div className="max-w-5xl mx-auto space-y-6">
        <div>
          <h1 className="text-3xl font-bold flex items-center gap-2"><ShieldCheck className="size-7 text-primary" /> {t("screening_title")}</h1>
          <p className="text-muted-foreground mt-1 text-sm">Screen names against the sanctions list</p>
        </div>

        <Card className="shadow-card">
          <CardHeader><CardTitle>New screening</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="grid md:grid-cols-3 gap-3">
              <Select value={entityId} onValueChange={setEntityId}>
                <SelectTrigger><SelectValue placeholder="Select entity" /></SelectTrigger>
                <SelectContent>
                  {entities.map((e) => <SelectItem key={e.id} value={e.id}>{e.entity_name}</SelectItem>)}
                </SelectContent>
              </Select>
              <Input placeholder="Name to screen" value={name} onChange={(e) => setName(e.target.value)} />
              <Select value={nameType} onValueChange={setNameType}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {["Owner", "Shareholder", "UBO", "Management"].map((x) => <SelectItem key={x} value={x}>{x}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div className="flex gap-2 flex-wrap">
              <Button variant="premium" onClick={runScreen} disabled={!!busy || !entityId || !name.trim()}>
                <Search className="size-4" /> {busy ? t("loading") : "فحص اسم محدد"}
              </Button>
              <Button variant="outline" onClick={runBulkScreen} disabled={!!busy || !entityId}>
                <ShieldCheck className="size-4" /> {busy ? "جاري الفحص..." : "فحص كل الأسماء تلقائياً"}
              </Button>
            </div>
          </CardContent>
        </Card>

        {entityId && (
          <Card className="shadow-card">
            <CardHeader><CardTitle>Results</CardTitle></CardHeader>
            <CardContent>
              {results.length === 0 ? (
                <div className="py-8 text-center text-sm text-muted-foreground">No screenings yet for this entity</div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="text-muted-foreground border-b border-border">
                      <tr>
                        <th className="py-2 text-start ps-3">Name</th>
                        <th className="py-2 text-start">Type</th>
                        <th className="py-2 text-start">AI Result</th>
                        <th className="py-2 text-start">Notes</th>
                        <th className="py-2 text-start">Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      {results.map((r) => (
                        <tr key={r.id} className="border-b border-border/60">
                          <td className="py-2 ps-3 font-medium">{r.name_to_screen}</td>
                          <td className="py-2">{r.name_type}</td>
                          <td className="py-2">
                            <Badge variant={r.ai_result === "confirmed" ? "destructive" : r.ai_result === "partial" ? "warning" : "success"}>
                              {r.ai_result}
                            </Badge>
                          </td>
                          <td className="py-2 text-xs text-muted-foreground">{r.notes ?? "—"}</td>
                          <td className="py-2 text-xs text-muted-foreground">{new Date(r.screened_at).toLocaleString()}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </AppShell>
  );
}
