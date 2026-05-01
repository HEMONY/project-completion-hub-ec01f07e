import { useState, useEffect } from "react";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useI18n } from "@/lib/i18n";
import { useAuth, useRole } from "@/lib/auth";
import { useNavigate } from "react-router-dom";
import { supabase } from "@/integrations/supabase/client";
import { Sparkles, FileBarChart, ShieldCheck, TrendingUp, CheckCircle2, AlertTriangle, Info } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { toast } from "sonner";

function NativeSelect(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select
      {...props}
      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
    />
  );
}

// حساب درجة الصحة محلياً
function calcScore(entity: any, auditFee: any, screenings: any[], cdd: any, taxStatus: any): number {
  let score = 100;
  if (!entity?.registration_status) score -= 10;
  if (!entity?.license_number) score -= 5;
  if (!entity?.address) score -= 5;
  if (!entity?.main_activity) score -= 5;
  if (!auditFee) score -= 10;
  const confirmed = screenings?.filter((s) => s.ai_result === "confirmed").length ?? 0;
  const partial = screenings?.filter((s) => s.ai_result === "partial").length ?? 0;
  score -= confirmed * 20;
  score -= partial * 5;
  if (cdd?.eligibility_status === "not_eligible") score -= 20;
  if (cdd?.eligibility_status === "pending" || !cdd) score -= 10;
  if (!taxStatus) score -= 5;
  return Math.max(0, Math.min(100, score));
}

function ScoreRing({ score }: { score: number }) {
  const color = score >= 80 ? "#16a34a" : score >= 60 ? "#d97706" : "#dc2626";
  const r = 40;
  const circ = 2 * Math.PI * r;
  const dash = (score / 100) * circ;
  return (
    <div className="flex flex-col items-center gap-1">
      <svg width="100" height="100" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r={r} fill="none" stroke="currentColor" strokeWidth="10" className="text-muted/30" />
        <circle cx="50" cy="50" r={r} fill="none" stroke={color} strokeWidth="10"
          strokeDasharray={`${dash} ${circ}`} strokeLinecap="round"
          transform="rotate(-90 50 50)" style={{ transition: "stroke-dasharray 0.8s ease" }} />
        <text x="50" y="55" textAnchor="middle" fontSize="20" fontWeight="bold" fill={color}>{score}</text>
      </svg>
      <span className="text-xs text-muted-foreground">درجة الصحة</span>
    </div>
  );
}

export default function Audits() {
  const { t } = useI18n();
  const { user } = useAuth();
  const { role, roleLoading } = useRole();
  const navigate = useNavigate();
  const [entities, setEntities] = useState<any[]>([]);
  const [entityId, setEntityId] = useState("");
  const [selectedEntity, setSelectedEntity] = useState<any>(null);
  const [result, setResult] = useState("");
  const [score, setScore] = useState<number | null>(null);
  const [risks, setRisks] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [method, setMethod] = useState<"local" | "api" | null>(null);

  useEffect(() => {
    if (!user) return;
    supabase
      .from("entities")
      .select("id, entity_name, application_status")
      .order("created_at", { ascending: false })
      .then(({ data }) => setEntities(data ?? []));
  }, [user]);

  const buildRisks = (entity: any, screenings: any[], cdd: any, taxStatus: any): string[] => {
    const r: string[] = [];
    if (!entity?.registration_status) r.push("حالة التسجيل التجاري غير مكتملة");
    if (!entity?.license_number) r.push("رقم الترخيص مفقود");
    if (screenings?.some((s) => s.ai_result === "confirmed")) r.push("⚠️ تطابق مؤكد في قوائم العقوبات");
    if (screenings?.some((s) => s.ai_result === "partial")) r.push("تطابق جزئي في قوائم العقوبات — يتطلب مراجعة");
    if (cdd?.eligibility_status === "not_eligible") r.push("الكيان غير مؤهل وفق تحقق CDD");
    if (!cdd) r.push("لم يُكتمل تحقق CDD بعد");
    if (!taxStatus) r.push("معلومات الوضع الضريبي غير مكتملة");
    return r;
  };

  const buildPrompt = (entity: any, auditFee: any, taxStatus: any, screenings: any[], cdd: any, s: number) => `
أنت مراجع حسابات قانوني متخصص. قدّم تقرير مراجعة موجزاً وواضحاً بالعربية:

الكيان: ${entity?.entity_name}
نوع الطلب: ${entity?.application_type ?? "—"}
حالة التسجيل: ${entity?.registration_status ?? "—"}
دوران الأعمال: ${entity?.total_turnover?.toLocaleString() ?? "—"} AED
الإمارة: ${entity?.emirate ?? "—"}
النشاط: ${entity?.main_activity ?? "—"}
المساهمون: ${(entity?.shareholders ?? []).length} شخص
UBOs: ${(entity?.ubos ?? []).length} شخص

رسوم المراجعة: ${auditFee?.calculated_fee?.toLocaleString() ?? "غير محددة"} AED
VAT: ${taxStatus?.vat_status ?? "—"}
ضريبة الشركات: ${taxStatus?.corporate_tax_status ?? "—"}
نتائج الفحص: ${screenings?.length ?? 0} فحص — تطابق مؤكد: ${screenings?.filter((s) => s.ai_result === "confirmed").length ?? 0}
CDD: ${cdd?.eligibility_status ?? "لم يكتمل"}
درجة الصحة المحسوبة: ${s}/100

اكتب:
**ملخص تنفيذي**: جملتان فقط.
**التوصيات**: 3-5 توصيات قصيرة مرقمة.
لا تعيد الأرقام فقط — أضف تقييماً نوعياً.`.trim();

  const runAudit = async () => {
    if (!entityId || !user) return;
    setBusy(true);
    setResult("");
    setScore(null);
    setRisks([]);
    setMethod(null);

    // جلب البيانات
    const [{ data: entity }, { data: auditFee }, { data: taxStatus }, { data: screenings }, { data: cdd }] =
      await Promise.all([
        supabase.from("entities").select("*").eq("id", entityId).single(),
        supabase.from("audit_fees").select("*").eq("entity_id", entityId).maybeSingle(),
        supabase.from("tax_status").select("*").eq("entity_id", entityId).maybeSingle(),
        supabase.from("screening_results").select("*").eq("entity_id", entityId),
        supabase.from("cdd_verifications").select("*").eq("entity_id", entityId).maybeSingle(),
      ]);
    setSelectedEntity(entity);
    // حساب الدرجة
    const s = calcScore(entity, auditFee, screenings ?? [], cdd, taxStatus);
    setScore(s);
    setRisks(buildRisks(entity, screenings ?? [], cdd, taxStatus));

    const prompt = buildPrompt(entity, auditFee, taxStatus, screenings ?? [], cdd, s);

    // محاولة API عبر Supabase Edge Function
    try {
      const supabaseUrl = import.meta.env.VITE_SUPABASE_URL;
      const supabaseKey = import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY;
      const resp = await fetch(`${supabaseUrl}/functions/v1/ai-audit`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "apikey": supabaseKey,
          "Authorization": `Bearer ${supabaseKey}`,
        },
        body: JSON.stringify({ prompt }),
      });
      if (resp.ok) {
        const data = await resp.json();
        const text = data.text ?? "";
        if (text.trim()) {
          setResult(text);
          setMethod("api");
          await supabase.from("user_audit_logs").insert({
            user_id: user.id,
            action: "ai_audit_run",
            description: `AI Audit للكيان: ${entity?.entity_name} — درجة: ${s}/100`,
          });

          // حفظ نتيجة التدقيق في جدول audit_signatures لإرسالها للمستخدم
          await supabase.from("audit_signatures").upsert({
            entity_id: selectedEntity?.id,
            auditor_id: user.id,
            health_score: s,
            ai_summary: text,
            risks: buildRisks(selectedEntity, screenings ?? [], cdd, taxStatus),
            status: "pending_client_signature",
            sent_at: new Date().toISOString(),
          }, { onConflict: "entity_id" });

          toast.success("✅ تم إرسال نتيجة التدقيق إلى العميل للتوقيع");
          setBusy(false);
          return;
        }
      }
    } catch { /* نستخدم التحليل المحلي */ }

    // Fallback: تقرير محلي
    const localReport = `**ملخص تنفيذي**
${entity?.entity_name ?? "الكيان"} هو ${entity?.registration_status ?? "كيان تجاري"} مقيم في ${entity?.emirate ?? "الإمارات"} بدوران أعمال ${entity?.total_turnover?.toLocaleString() ?? "غير محدد"} درهم. درجة الصحة المالية الإجمالية ${s}/100.

**التوصيات**
${risks.length === 0 ? "1. لا توجد مخاطر واضحة — الملف مكتمل." : risks.map((r, i) => `${i + 1}. ${r}`).join("\n")}
${s < 60 ? `${risks.length + 1}. يُنصح بمراجعة فورية للملف قبل اعتماده.` : ""}`;

    setResult(localReport);
    setMethod("local");
    await supabase.from("user_audit_logs").insert({
        user_id: user.id,
        action: "audit_run_local",
        description: `تحليل محلي للكيان: ${entity?.entity_name} — درجة: ${s}/100`,
      });

      // إرسال النتيجة للمستخدم للتوقيع حتى في التحليل المحلي
      await supabase.from("audit_signatures").upsert({
        entity_id: selectedEntity?.id ?? entityId,
        auditor_id: user.id,
        health_score: s,
        ai_summary: localReport,
        risks: buildRisks(selectedEntity, screenings ?? [], cdd, taxStatus),
        status: "pending_client_signature",
        sent_at: new Date().toISOString(),
      }, { onConflict: "entity_id" });

      toast.success("✅ تم إرسال نتيجة التحليل إلى العميل للتوقيع");
    setBusy(false);
  };
  // Guard: للمشرفين فقط
  if (!roleLoading && !["admin", "auditor", "moderator"].includes(role)) {
    return (
      <AppShell>
        <div className="max-w-md mx-auto text-center py-24 space-y-4">
          <div className="text-5xl">🔒</div>
          <h2 className="text-xl font-bold">غير مصرح بالوصول</h2>
          <p className="text-muted-foreground">صفحة التدقيق متاحة للمشرفين فقط.</p>
        </div>
      </AppShell>
    );
  }
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
            { icon: FileBarChart, title: "تحليل مالي", desc: "مراجعة بيانات دوران الأعمال والرسوم" },
            { icon: ShieldCheck, title: "فحص الامتثال", desc: "KYC · AML · قوائم العقوبات" },
            { icon: TrendingUp, title: "تقييم المخاطر", desc: "درجة صحة 0-100 مع توصيات" },
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
            <CardTitle>تشغيل تحليل الكيان</CardTitle>
            <CardDescription>اختر كياناً لتحليل بياناته الكاملة وإصدار تقرير مراجعة</CardDescription>
          </CardHeader>
          <CardContent className="space-y-5">
            <div className="flex gap-3 flex-wrap">
              <NativeSelect
                value={entityId}
                onChange={(e) => { setEntityId(e.target.value); setResult(""); setScore(null); }}
                style={{ flex: 1, minWidth: 200 }}
              >
                <option value="">اختر كياناً...</option>
                {entities.map((e) => (
                  <option key={e.id} value={e.id}>{e.entity_name}</option>
                ))}
              </NativeSelect>
              <Button variant="premium" onClick={runAudit} disabled={busy || !entityId} className="gap-2">
                <Sparkles className="size-4" />
                {busy ? "جاري التحليل..." : "تشغيل التحليل"}
              </Button>
            </div>

            {/* Loading */}
            {busy && (
              <div className="rounded-xl border border-border bg-muted/20 p-8 text-center space-y-3">
                <Sparkles className="size-8 text-primary mx-auto animate-pulse" />
                <p className="text-sm text-muted-foreground">يتم تحليل جميع بيانات الكيان...</p>
              </div>
            )}

            {/* Result */}
            {!busy && score !== null && (
              <div className="space-y-4 animate-fade-in">

                {/* Score + Method */}
                <div className="flex flex-wrap items-start gap-6 rounded-xl border border-border bg-card p-5">
                  <ScoreRing score={score} />
                  <div className="flex-1 space-y-3">
                    <div className="flex items-center gap-2">
                      <h3 className="font-semibold">نتيجة التقييم</h3>
                      {method === "api" ? (
                        <Badge variant="default" className="text-xs gap-1">
                          <Sparkles className="size-3" /> Claude AI
                        </Badge>
                      ) : (
                        <Badge variant="secondary" className="text-xs gap-1">
                          <Info className="size-3" /> تحليل محلي
                        </Badge>
                      )}
                    </div>

                    <div className="flex flex-wrap gap-2">
                      {score >= 80 && <Badge className="bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300"><CheckCircle2 className="size-3" /> منخفض المخاطر</Badge>}
                      {score >= 60 && score < 80 && <Badge className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300"><AlertTriangle className="size-3" /> مخاطر متوسطة</Badge>}
                      {score < 60 && <Badge className="bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300"><AlertTriangle className="size-3" /> مخاطر عالية</Badge>}
                    </div>
                  </div>
                </div>

                {/* Risk flags */}
                {risks.length > 0 && (
                  <div className="rounded-xl border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950/20 p-4 space-y-2">
                    <div className="text-sm font-semibold flex items-center gap-2 text-yellow-800 dark:text-yellow-300">
                      <AlertTriangle className="size-4" /> نقاط تتطلب المراجعة
                    </div>
                    <ul className="space-y-1">
                      {risks.map((r, i) => (
                        <li key={i} className="text-xs text-yellow-700 dark:text-yellow-400 flex items-start gap-2">
                          <span className="mt-0.5">•</span> {r}
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
                {/* زر إرسال النتيجة للعميل */}
                <div className="rounded-xl border border-primary/30 bg-primary/5 p-4 flex items-center justify-between gap-4">
                  <div>
                    <div className="text-sm font-semibold">إرسال النتيجة للعميل</div>
                    <div className="text-xs text-muted-foreground">سيتمكن العميل من مراجعة التقرير والتوقيع عليه</div>
                  </div>
                  <Button
                    size="sm"
                    onClick={async () => {
                      await supabase.from("audit_signatures").upsert({
                        entity_id: selectedEntity?.id ?? entityId,
                        auditor_id: user!.id,
                        health_score: score ?? 0,
                        ai_summary: result,
                        risks: risks,
                        status: "pending_client_signature",
                        sent_at: new Date().toISOString(),
                      }, { onConflict: "entity_id" });
                      toast.success("تم إرسال النتيجة للعميل");
                    }}
                  >
                    إرسال للعميل
                  </Button>
                </div>
                {/* AI Report */}
                <div className="rounded-xl border border-border bg-muted/10 p-5 space-y-2">
                  <div className="text-sm font-semibold">تقرير المراجعة</div>
                  <div className="text-sm leading-relaxed whitespace-pre-wrap text-muted-foreground">{result}</div>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
