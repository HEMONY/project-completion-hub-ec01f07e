import { useState, useEffect } from "react";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useI18n } from "@/lib/i18n";
import { useAuth } from "@/lib/auth";
import { supabase } from "@/integrations/supabase/client";
import { Sparkles, FileBarChart, ShieldCheck, TrendingUp, AlertCircle } from "lucide-react";
import { toast } from "sonner";

function NativeSelect(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select {...props} className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
  );
}

export default function Audits() {
  const { t } = useI18n();
  const { user } = useAuth();
  const [entities, setEntities] = useState<any[]>([]);
  const [entityId, setEntityId] = useState("");
  const [result, setResult] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!user) return;
    supabase
      .from("entities")
      .select("id, entity_name, application_status")
      .order("created_at", { ascending: false })
      .then(({ data }) => setEntities(data ?? []));
  }, [user]);

  const runAudit = async () => {
    if (!entityId) return;
    setBusy(true);
    setResult("");

    const { data: entity } = await supabase
      .from("entities")
      .select("*")
      .eq("id", entityId)
      .single();

    const { data: auditFee } = await supabase
      .from("audit_fees")
      .select("*")
      .eq("entity_id", entityId)
      .maybeSingle();

    const { data: taxStatus } = await supabase
      .from("tax_status")
      .select("*")
      .eq("entity_id", entityId)
      .maybeSingle();

    const { data: screenings } = await supabase
      .from("screening_results")
      .select("*")
      .eq("entity_id", entityId);

    const { data: cdd } = await supabase
      .from("cdd_verifications")
      .select("*")
      .eq("entity_id", entityId)
      .maybeSingle();

    const prompt = `أنت مراجع حسابات متخصص. قم بتحليل بيانات الكيان التالية وأصدر تقرير مراجعة شامل:

**بيانات الكيان:**
- الاسم: ${entity?.entity_name}
- نوع الطلب: ${entity?.application_type}
- حالة التسجيل: ${entity?.registration_status}
- دوران الأعمال: ${entity?.total_turnover?.toLocaleString()} AED
- الإمارة: ${entity?.emirate}
- النشاط الرئيسي: ${entity?.main_activity}
- عدد المساهمين: ${(entity?.shareholders ?? []).length}
- عدد UBOs: ${(entity?.ubos ?? []).length}

**رسوم المراجعة:** ${auditFee?.calculated_fee?.toLocaleString() ?? "غير محددة"} AED

**الوضع الضريبي:**
- ضريبة القيمة المضافة: ${taxStatus?.vat_status ?? "غير محدد"}
- ضريبة الشركات: ${taxStatus?.corporate_tax_status ?? "غير محدد"}

**نتائج الفحص:** ${screenings?.length ?? 0} فحص — ${screenings?.filter((s) => s.ai_result === "confirmed").length ?? 0} تطابق مؤكد

**تحقق CDD:** ${cdd?.eligibility_status ?? "لم يكتمل"}

قدّم:
1. ملخص تنفيذي (3-4 جمل)
2. نقاط المخاطر (إن وجدت)
3. درجة الصحة المالية من 100
4. التوصيات (قائمة مرقمة)`;

    try {
      const response = await fetch("https://api.anthropic.com/v1/messages", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          model: "claude-sonnet-4-20250514",
          max_tokens: 1000,
          messages: [{ role: "user", content: prompt }],
        }),
      });
      const data = await response.json();
      const text = data.content?.map((c: any) => c.text).join("") ?? "لم يتم الحصول على نتيجة.";
      setResult(text);

      // سجّل في audit_logs
      await supabase.from("user_audit_logs").insert({
        user_id: user!.id,
        action: "ai_audit_run",
        description: `تشغيل AI Audit للكيان: ${entity?.entity_name}`,
      });
    } catch {
      toast.error("فشل تشغيل تحليل الذكاء الاصطناعي");
    }

    setBusy(false);
  };

  return (
    <AppShell>
      <div className="max-w-5xl mx-auto space-y-6">
        <div>
          <h1 className="text-3xl font-bold flex items-center gap-2">
            <Sparkles className="size-7 text-primary" /> {t("ai_audit_title")}
          </h1>
          <p className="text-muted-foreground mt-1">{t("ai_audit_subtitle")}</p>
        </div>

        <div className="grid md:grid-cols-3 gap-4">
          {[
            { icon: FileBarChart, title: "تحليل مالي", desc: "مراجعة آلية للبيانات" },
            { icon: ShieldCheck, title: "فحص الامتثال", desc: "KYC & AML & قوائم العقوبات" },
            { icon: TrendingUp, title: "تقييم المخاطر", desc: "درجة صحة 0-100" },
          ].map((f) => (
            <Card key={f.title} className="shadow-card">
              <CardContent className="p-5 space-y-2">
                <div className="size-10 rounded-lg bg-primary/10 text-primary grid place-items-center">
                  <f.icon className="size-5" />
                </div>
                <div className="font-semibold">{f.title}</div>
                <div className="text-sm text-muted-foreground">{f.desc}</div>
              </CardContent>
            </Card>
          ))}
        </div>

        <Card className="shadow-card">
          <CardHeader>
            <CardTitle>تشغيل تحليل الذكاء الاصطناعي</CardTitle>
            <CardDescription>اختر كياناً لتحليل بياناته بالكامل</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex gap-3">
              <NativeSelect
                value={entityId}
                onChange={(e) => { setEntityId(e.target.value); setResult(""); }}
                style={{ flex: 1 }}
              >
                <option value="">اختر كياناً...</option>
                {entities.map((e) => (
                  <option key={e.id} value={e.id}>{e.entity_name}</option>
                ))}
              </NativeSelect>
              <Button variant="premium" onClick={runAudit} disabled={busy || !entityId}>
                <Sparkles className="size-4" /> {busy ? "جاري التحليل..." : "تشغيل التحليل"}
              </Button>
            </div>

            {busy && (
              <div className="rounded-lg border border-border p-6 text-center space-y-2">
                <Sparkles className="size-6 text-primary mx-auto animate-pulse" />
                <p className="text-sm text-muted-foreground">الذكاء الاصطناعي يحلل البيانات...</p>
              </div>
            )}

            {result && !busy && (
              <div className="rounded-lg border border-border bg-muted/20 p-6 space-y-4">
                <div className="flex items-center gap-2 font-semibold text-sm">
                  <AlertCircle className="size-4 text-primary" /> نتيجة التحليل
                </div>
                <div className="text-sm leading-relaxed whitespace-pre-wrap">{result}</div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
