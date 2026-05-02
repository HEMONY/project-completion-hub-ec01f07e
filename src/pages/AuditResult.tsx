import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { supabase } from "@/integrations/supabase/client";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { toast } from "sonner";
import { CheckCircle2, AlertTriangle, FileText, PenLine, Clock } from "lucide-react";
import { formatDateTime } from "@/lib/utils";

function ScoreBar({ score }: { score: number }) {
  const color = score >= 80 ? "bg-green-500" : score >= 60 ? "bg-yellow-500" : "bg-red-500";
  const label = score >= 80 ? "منخفض المخاطر" : score >= 60 ? "مخاطر متوسطة" : "مخاطر عالية";
  return (
    <div className="space-y-2">
      <div className="flex justify-between items-center text-sm">
        <span className="font-medium">درجة الصحة المالية</span>
        <span className="font-bold text-xl">{score}/100</span>
      </div>
      <div className="h-3 rounded-full bg-muted overflow-hidden">
        <div
          className={`h-full rounded-full transition-all ${color}`}
          style={{ width: `${score}%` }}
        />
      </div>
      <div className="text-xs text-muted-foreground">{label}</div>
    </div>
  );
}

export default function AuditResult() {
  const { user, loading } = useAuth();
  const navigate = useNavigate();
  const [results, setResults] = useState<any[]>([]);
  const [dataLoading, setDataLoading] = useState(true);
  const [signing, setSigning] = useState<string | null>(null);
  const [signerName, setSignerName] = useState("");
  const [activeSign, setActiveSign] = useState<string | null>(null);

  useEffect(() => {
    if (!loading && !user) navigate("/auth");
  }, [user, loading, navigate]);

  useEffect(() => {
    if (!user) return;
    fetchResults();
  }, [user]);

  const fetchResults = async () => {
    setDataLoading(true);
    // جلب كل الكيانات الخاصة بالمستخدم التي لها نتيجة تدقيق
    const { data: entities } = await supabase
      .from("entities")
      .select("id, entity_name, engagement_number")
      .eq("user_id", user!.id);

    if (!entities || entities.length === 0) {
      setDataLoading(false);
      return;
    }

    const entityIds = entities.map((e) => e.id);
    const { data: sigs } = await supabase
      .from("audit_signatures")
      .select("*")
      .in("entity_id", entityIds)
      .order("created_at", { ascending: false });

    // دمج بيانات الكيان مع نتيجة التدقيق
    const merged = (sigs ?? []).map((sig) => ({
      ...sig,
      entity: entities.find((e) => e.id === sig.entity_id),
    }));

    setResults(merged);
    setDataLoading(false);
  };

  const signResult = async (sigId: string, entityId: string) => {
    if (!signerName.trim()) return toast.error("يرجى إدخال اسمك للتوقيع");
    setSigning(sigId);

    const { error } = await supabase
      .from("audit_signatures")
      .update({
        status: "client_signed",
        client_signature: signerName.trim(),
        client_signed_at: new Date().toISOString(),
      })
      .eq("id", sigId);

    // تسجيل في audit_logs
    await supabase.from("user_audit_logs").insert({
      user_id: user!.id,
      action: "client_signed_audit",
      description: `وقّع العميل على نتيجة التدقيق — الكيان: ${entityId} — التوقيع: ${signerName}`,
    });

    setSigning(null);
    setActiveSign(null);
    setSignerName("");
    if (error) return toast.error(error.message);
    toast.success("✅ تم توقيعك على نتيجة التدقيق — سيتم إرسالها للمشرف للمراجعة النهائية");
    fetchResults();
  };

  const statusMap: Record<string, { label: string; color: string; icon: any }> = {
    pending_client_signature: {
      label: "بانتظار توقيعك",
      color: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300",
      icon: Clock,
    },
    client_signed: {
      label: "موقَّعة — بانتظار المشرف",
      color: "bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300",
      icon: PenLine,
    },
    admin_countersigned: {
      label: "مكتملة",
      color: "bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300",
      icon: CheckCircle2,
    },
    completed: {
      label: "مكتملة",
      color: "bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300",
      icon: CheckCircle2,
    },
  };

  if (loading || dataLoading) {
    return (
      <AppShell>
        <div className="text-center py-20 text-muted-foreground">جاري التحميل...</div>
      </AppShell>
    );
  }

  return (
    <AppShell>
      <div className="max-w-4xl mx-auto space-y-6">
        <div>
          <h1 className="text-3xl font-bold flex items-center gap-2">
            <FileText className="size-7 text-primary" /> نتائج التدقيق
          </h1>
          <p className="text-muted-foreground mt-1 text-sm">
            راجع نتائج تدقيق كياناتك ووقّع عليها
          </p>
        </div>

        {results.length === 0 ? (
          <Card className="shadow-card">
            <CardContent className="py-16 text-center space-y-3">
              <FileText className="size-10 text-muted-foreground mx-auto" />
              <div className="text-muted-foreground">لا توجد نتائج تدقيق بعد</div>
              <div className="text-xs text-muted-foreground">
                ستظهر هنا نتائج تدقيق كياناتك بمجرد انتهاء المراجع منها
              </div>
            </CardContent>
          </Card>
        ) : (
          <div className="space-y-5">
            {results.map((r) => {
              const statusInfo = statusMap[r.status] ?? statusMap.pending_client_signature;
              const StatusIcon = statusInfo.icon;
              const risks: string[] = Array.isArray(r.risks) ? r.risks : [];
              const isPending = r.status === "pending_client_signature";

              return (
                <Card key={r.id} className="shadow-card">
                  <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <CardTitle className="text-lg">
                          {r.entity?.entity_name ?? "كيان"}
                        </CardTitle>
                        {r.entity?.engagement_number && (
                          <div className="text-xs text-muted-foreground font-mono mt-0.5">
                            {r.entity.engagement_number}
                          </div>
                        )}
                      </div>
                      <span
                        className={`inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full ${statusInfo.color}`}
                      >
                        <StatusIcon className="size-3.5" />
                        {statusInfo.label}
                      </span>
                    </div>
                  </CardHeader>
                  <CardContent className="space-y-5">
                    {/* درجة الصحة */}
                    {r.health_score != null && (
                      <ScoreBar score={r.health_score} />
                    )}

                    {/* نقاط المخاطر */}
                    {risks.length > 0 && (
                      <div className="rounded-xl border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950/20 p-4 space-y-2">
                        <div className="text-sm font-semibold flex items-center gap-2 text-yellow-800 dark:text-yellow-300">
                          <AlertTriangle className="size-4" /> نقاط تتطلب الانتباه
                        </div>
                        <ul className="space-y-1">
                          {risks.map((risk: string, i: number) => (
                            <li key={i} className="text-xs text-yellow-700 dark:text-yellow-400 flex gap-2">
                              <span className="mt-0.5 shrink-0">•</span>
                              <span>{risk}</span>
                            </li>
                          ))}
                        </ul>
                      </div>
                    )}

                    {/* ملخص التدقيق */}
                    {r.ai_summary && (
                      <div className="rounded-xl border border-border bg-muted/10 p-4 space-y-2">
                        <div className="text-sm font-semibold">تقرير التدقيق</div>
                        <div className="text-sm leading-relaxed text-muted-foreground whitespace-pre-wrap">
                          {r.ai_summary}
                        </div>
                      </div>
                    )}

                    {/* تاريخ الإرسال */}
                    {r.sent_at && (
                      <div className="text-xs text-muted-foreground">
                        أُرسل في: {formatDateTime(r.sent_at)}
                      </div>
                    )}

                    {/* التوقيع */}
                    {isPending && (
                      <div className="border-t border-border pt-4 space-y-3">
                        {activeSign === r.id ? (
                          <div className="space-y-3">
                            <div className="text-sm font-medium">
                              أدخل اسمك الكامل للتوقيع الرقمي
                            </div>
                            <Input
                              placeholder="الاسم الكامل"
                              value={signerName}
                              onChange={(e) => setSignerName(e.target.value)}
                              onKeyDown={(e) => e.key === "Enter" && signResult(r.id, r.entity_id)}
                            />
                            <div className="flex gap-2">
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => { setActiveSign(null); setSignerName(""); }}
                              >
                                إلغاء
                              </Button>
                              <Button
                                size="sm"
                                disabled={signing === r.id || !signerName.trim()}
                                onClick={() => signResult(r.id, r.entity_id)}
                              >
                                {signing === r.id ? "جاري التوقيع..." : "✍️ تأكيد التوقيع"}
                              </Button>
                            </div>
                            <div className="text-xs text-muted-foreground">
                              بتوقيعك تؤكد اطلاعك على نتيجة التدقيق وموافقتك على محتواها
                            </div>
                          </div>
                        ) : (
                          <Button
                            className="w-full sm:w-auto gap-2"
                            onClick={() => setActiveSign(r.id)}
                          >
                            <PenLine className="size-4" />
                            التوقيع على نتيجة التدقيق
                          </Button>
                        )}
                      </div>
                    )}

                    {/* عرض التوقيع إذا وقّع */}
                    {r.status !== "pending_client_signature" && r.client_signature && (
                      <div className="border-t border-border pt-4">
                        <div className="flex items-center gap-3 text-sm">
                          <CheckCircle2 className="size-4 text-green-600 shrink-0" />
                          <div>
                            <span className="text-muted-foreground">وُقِّع بواسطة: </span>
                            <strong>{r.client_signature}</strong>
                            {r.client_signed_at && (
                              <span className="text-xs text-muted-foreground ms-2">
                                ({formatDateTime(r.client_signed_at)})
                              </span>
                            )}
                          </div>
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}
      </div>
    </AppShell>
  );
}
