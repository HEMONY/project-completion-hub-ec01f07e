import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { useRole } from "@/lib/auth";
import { supabase } from "@/integrations/supabase/client";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "sonner";
import {
  Building2, Clock, CheckCircle2, XCircle, ShieldCheck,
  Search, Eye, FileText, AlertCircle
} from "lucide-react";

function NativeSelect(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select
      {...props}
      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
    />
  );
}

const STATUS_COLORS: Record<string, string> = {
  draft: "secondary",
  submitted: "default",
  under_review: "warning",
  approved: "success",
  rejected: "destructive",
  edited: "warning",
};

export default function AdminDashboard() {
  const { user, loading } = useAuth();
  const { role, roleLoading } = useRole();
  const navigate = useNavigate();
  const [entities, setEntities] = useState<any[]>([]);
  const [stats, setStats] = useState({ submitted: 0, under_review: 0, approved: 0, rejected: 0 });
  const [q, setQ] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [busy, setBusy] = useState<string | null>(null);
  const [reviewModal, setReviewModal] = useState<{ entity: any; action: "approve" | "reject" } | null>(null);
  const [reviewNotes, setReviewNotes] = useState("");

  useEffect(() => {
    if (!loading && !user) navigate("/auth");
  }, [user, loading, navigate]);

  useEffect(() => {
    if (!user) return;
    fetchEntities();
  }, [user]);

  const fetchEntities = async () => {
    const { data } = await supabase
      .from("entities")
      .select("*, profiles(full_name, email)")
      .order("created_at", { ascending: false });
    const rows = data ?? [];
    setEntities(rows);
    setStats({
      submitted: rows.filter((r) => r.application_status === "submitted").length,
      under_review: rows.filter((r) => r.application_status === "under_review").length,
      approved: rows.filter((r) => r.application_status === "approved").length,
      rejected: rows.filter((r) => r.application_status === "rejected").length,
    });
  };

  const moveToReview = async (entityId: string) => {
    setBusy(entityId);
    const { error } = await supabase
      .from("entities")
      .update({ application_status: "under_review", reviewed_by: user!.id, reviewed_at: new Date().toISOString() })
      .eq("id", entityId);
    setBusy(null);
    if (error) return toast.error(error.message);
    toast.success("تم نقل الكيان إلى قيد المراجعة");
    fetchEntities();
  };

  const submitReview = async () => {
    if (!reviewModal) return;
    const { entity, action } = reviewModal;
    setBusy(entity.id);
    const newStatus = action === "approve" ? "approved" : "rejected";
    const { error } = await supabase
      .from("entities")
      .update({
        application_status: newStatus,
        rejection_reason: action === "reject" ? reviewNotes : null,
        reviewed_by: user!.id,
        reviewed_at: new Date().toISOString(),
      })
      .eq("id", entity.id);

    // سجّل في audit_logs
    await supabase.from("user_audit_logs").insert({
      user_id: user!.id,
      action: `entity_${newStatus}`,
      description: `${newStatus === "approved" ? "وافق على" : "رفض"} الكيان: ${entity.entity_name}. ${reviewNotes ? "السبب: " + reviewNotes : ""}`,
    });

    setBusy(null);
    setReviewModal(null);
    setReviewNotes("");
    if (error) return toast.error(error.message);
    toast.success(action === "approve" ? "تمت الموافقة على الكيان" : "تم رفض الكيان");
    fetchEntities();
  };

  if (loading || roleLoading) {
    return <AppShell><div className="text-center py-20 text-muted-foreground">جاري التحميل...</div></AppShell>;
  }

  if (!["admin", "auditor", "moderator"].includes(role)) {
    return (
      <AppShell>
        <div className="max-w-lg mx-auto text-center py-20 space-y-4">
          <AlertCircle className="size-12 text-destructive mx-auto" />
          <h2 className="text-xl font-bold">غير مصرح</h2>
          <p className="text-muted-foreground">هذه الصفحة مخصصة للمشرفين والمراجعين فقط.</p>
          <Button asChild variant="outline"><Link to="/">العودة للرئيسية</Link></Button>
        </div>
      </AppShell>
    );
  }

  const filtered = entities.filter((e) => {
    if (statusFilter !== "all" && e.application_status !== statusFilter) return false;
    if (q && !e.entity_name?.toLowerCase().includes(q.toLowerCase()) &&
        !e.engagement_number?.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const statCards = [
    { label: "مُقدَّمة", value: stats.submitted, icon: Clock, color: "text-info", bg: "bg-info/15" },
    { label: "قيد المراجعة", value: stats.under_review, icon: FileText, color: "text-warning", bg: "bg-warning/15" },
    { label: "معتمدة", value: stats.approved, icon: CheckCircle2, color: "text-success", bg: "bg-success/15" },
    { label: "مرفوضة", value: stats.rejected, icon: XCircle, color: "text-destructive", bg: "bg-destructive/15" },
  ];

  return (
    <AppShell>
      <div className="max-w-7xl mx-auto space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="text-3xl font-bold">لوحة الإدارة</h1>
            <p className="text-muted-foreground text-sm mt-1">إدارة كيانات العملاء والموافقة عليها</p>
          </div>
        </div>

        {/* إحصائيات */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {statCards.map((c) => (
            <Card key={c.label} className="shadow-card">
              <CardContent className="p-5">
                <div className="flex items-center justify-between">
                  <div className="text-sm text-muted-foreground">{c.label}</div>
                  <div className={`size-10 rounded-lg grid place-items-center ${c.bg} ${c.color}`}>
                    <c.icon className="size-5" />
                  </div>
                </div>
                <div className="mt-3 text-3xl font-bold">{c.value}</div>
              </CardContent>
            </Card>
          ))}
        </div>

        {/* فلاتر */}
        <Card className="shadow-card">
          <CardContent className="p-4">
            <div className="flex flex-wrap gap-3">
              <div className="relative flex-1 min-w-48">
                <Search className="absolute start-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <Input
                  placeholder="بحث باسم الكيان أو رقم الارتباط..."
                  value={q}
                  onChange={(e) => setQ(e.target.value)}
                  className="ps-9"
                />
              </div>
              <NativeSelect
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                style={{ width: "200px" }}
              >
                <option value="all">كل الحالات</option>
                <option value="submitted">مُقدَّمة</option>
                <option value="under_review">قيد المراجعة</option>
                <option value="approved">معتمدة</option>
                <option value="rejected">مرفوضة</option>
                <option value="draft">مسودة</option>
              </NativeSelect>
            </div>
          </CardContent>
        </Card>

        {/* جدول الكيانات */}
        <Card className="shadow-card">
          <CardHeader>
            <CardTitle>الكيانات ({filtered.length})</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-muted/40 border-b border-border">
                  <tr>
                    <th className="py-3 px-4 text-start font-medium text-muted-foreground">اسم الكيان</th>
                    <th className="py-3 px-4 text-start font-medium text-muted-foreground">العميل</th>
                    <th className="py-3 px-4 text-start font-medium text-muted-foreground">رقم الارتباط</th>
                    <th className="py-3 px-4 text-start font-medium text-muted-foreground">الحالة</th>
                    <th className="py-3 px-4 text-start font-medium text-muted-foreground">تاريخ الإنشاء</th>
                    <th className="py-3 px-4 text-start font-medium text-muted-foreground">الإجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="py-12 text-center text-muted-foreground">
                        لا توجد كيانات
                      </td>
                    </tr>
                  ) : (
                    filtered.map((e) => (
                      <tr key={e.id} className="border-b border-border/60 hover:bg-muted/20 transition-colors">
                        <td className="py-3 px-4 font-medium">{e.entity_name}</td>
                        <td className="py-3 px-4 text-muted-foreground text-xs">
                          <div>{e.profiles?.full_name ?? "—"}</div>
                          <div>{e.profiles?.email ?? ""}</div>
                        </td>
                        <td className="py-3 px-4 font-mono text-xs">
                          {e.engagement_number ?? "—"}
                        </td>
                        <td className="py-3 px-4">
                          <Badge variant={(STATUS_COLORS[e.application_status] as any) ?? "secondary"}>
                            {e.application_status}
                          </Badge>
                        </td>
                        <td className="py-3 px-4 text-xs text-muted-foreground">
                          {new Date(e.created_at).toLocaleDateString("ar-AE")}
                        </td>
                        <td className="py-3 px-4">
                          <div className="flex items-center gap-1.5 flex-wrap">
                            {/* عرض الكيان */}
                            <Button asChild size="sm" variant="outline">
                              <Link to={`/kyc/${e.id}/kyc`}>
                                <Eye className="size-3.5" />
                              </Link>
                            </Button>
                            {/* فحص */}
                            <Button asChild size="sm" variant="outline">
                              <Link to={`/screening?entity=${e.id}`}>
                                <ShieldCheck className="size-3.5" />
                              </Link>
                            </Button>
                            {/* CDD */}
                            <Button asChild size="sm" variant="outline">
                              <Link to={`/cdd/${e.id}`}>
                                <FileText className="size-3.5" />
                              </Link>
                            </Button>
                            {/* قيد المراجعة */}
                            {e.application_status === "submitted" && (
                              <Button
                                size="sm"
                                variant="outline"
                                disabled={busy === e.id}
                                onClick={() => moveToReview(e.id)}
                              >
                                {busy === e.id ? "..." : "مراجعة"}
                              </Button>
                            )}
                            {/* موافقة */}
                            {e.application_status === "under_review" && (
                              <>
                                <Button
                                  size="sm"
                                  variant="success"
                                  onClick={() => setReviewModal({ entity: e, action: "approve" })}
                                >
                                  موافقة
                                </Button>
                                <Button
                                  size="sm"
                                  variant="destructive"
                                  onClick={() => setReviewModal({ entity: e, action: "reject" })}
                                >
                                  رفض
                                </Button>
                              </>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* نافذة المراجعة */}
      {reviewModal && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <Card className="w-full max-w-md">
            <CardHeader>
              <CardTitle>
                {reviewModal.action === "approve" ? "✅ تأكيد الموافقة" : "❌ تأكيد الرفض"}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-sm text-muted-foreground">
                الكيان: <strong>{reviewModal.entity.entity_name}</strong>
              </p>
              {reviewModal.action === "reject" && (
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">سبب الرفض *</label>
                  <Textarea
                    placeholder="اذكر سبب رفض الطلب..."
                    value={reviewNotes}
                    onChange={(e) => setReviewNotes(e.target.value)}
                    rows={3}
                  />
                </div>
              )}
              <div className="flex gap-3 justify-end pt-2">
                <Button variant="outline" onClick={() => { setReviewModal(null); setReviewNotes(""); }}>
                  إلغاء
                </Button>
                <Button
                  variant={reviewModal.action === "approve" ? "success" : "destructive"}
                  onClick={submitReview}
                  disabled={reviewModal.action === "reject" && !reviewNotes.trim()}
                >
                  {reviewModal.action === "approve" ? "تأكيد الموافقة" : "تأكيد الرفض"}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </AppShell>
  );
}