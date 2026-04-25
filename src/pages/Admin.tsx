import { useEffect, useState, useRef } from "react";
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
import Papa from "papaparse";
import {
  Building2, ShieldCheck,
  Search, Eye, FileText, AlertCircle, ScrollText,
  Upload, Trash2, Plus, Users, Activity,
  BarChart3, Shield, RefreshCw, ExternalLink, ArrowLeft, Image as ImageIcon,
} from "lucide-react";

function NativeSelect(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select
      {...props}
      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
    />
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1.5">
      <label className="text-sm font-medium">{label}</label>
      {children}
    </div>
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

const STATUS_LABELS: Record<string, string> = {
  draft: "مسودة",
  submitted: "مُقدَّم",
  under_review: "قيد المراجعة",
  approved: "معتمد",
  rejected: "مرفوض",
  edited: "معدَّل",
};

const DOC_TYPE_LABELS: Record<string, string> = {
  cdd_identity: "هوية",
  cdd_eligibility: "أهلية",
  cdd_auditor: "مدقق",
  eid_passport: "هوية / جواز",
  trade_license: "رخصة تجارية",
  authorization_letter: "تفويض",
};

function DocumentPreview({ doc }: { doc: any }) {
  const [url, setUrl] = useState("");

  useEffect(() => {
    let active = true;
    supabase.storage.from("kyc-documents").createSignedUrl(doc.storage_path, 60 * 10).then(({ data }) => {
      if (active) setUrl(data?.signedUrl ?? "");
    });
    return () => {
      active = false;
    };
  }, [doc.storage_path]);

  if (!url) return <div className="flex h-56 items-center justify-center rounded-md bg-muted/40 text-sm text-muted-foreground">جاري تحميل المعاينة...</div>;
  if (doc.mime_type?.startsWith("image/")) {
    return <img src={url} alt={doc.file_name} className="h-56 w-full rounded-md border border-border object-contain bg-muted/30" loading="lazy" />;
  }
  if (doc.mime_type === "application/pdf") {
    return <iframe title={doc.file_name} src={url} className="h-72 w-full rounded-md border border-border bg-background" />;
  }
  return <div className="flex h-56 items-center justify-center rounded-md bg-muted/40 text-sm text-muted-foreground">لا توجد معاينة لهذا النوع</div>;
}

type Tab = "overview" | "entities" | "documents" | "sanctions" | "users" | "logs";

export default function AdminDashboard() {
  const { user, loading } = useAuth();
  const { role, roleLoading } = useRole();
  const navigate = useNavigate();
  const [tab, setTab] = useState<Tab>("overview");

  // Entities state
  const [entities, setEntities] = useState<any[]>([]);
  const [stats, setStats] = useState({ submitted: 0, under_review: 0, approved: 0, rejected: 0, draft: 0, total: 0 });
  const [q, setQ] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [busy, setBusy] = useState<string | null>(null);
  const [reviewModal, setReviewModal] = useState<{ entity: any; action: "approve" | "reject" } | null>(null);
  const [reviewNotes, setReviewNotes] = useState("");
  const [documents, setDocuments] = useState<any[]>([]);
  const [docQ, setDocQ] = useState("");
  const [docStatusFilter, setDocStatusFilter] = useState("all");
  const [docTypeFilter, setDocTypeFilter] = useState("all");
  const [docReview, setDocReview] = useState<{ doc: any; status: "approved" | "rejected" } | null>(null);
  const [docReason, setDocReason] = useState("");

  // Sanctions state
  const [sanctions, setSanctions] = useState<any[]>([]);
  const [sanctionQ, setSanctionQ] = useState("");
  const [uploading, setUploading] = useState(false);
  const [addModal, setAddModal] = useState(false);
  const [newSanction, setNewSanction] = useState({
    english_name: "", arabic_name: "", country: "", type: "", list_reference: "", source: "",
  });
  const [deleteConfirm, setDeleteConfirm] = useState<string | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  // Users state
  const [users, setUsers] = useState<any[]>([]);

  // Logs state
  const [logs, setLogs] = useState<any[]>([]);

  useEffect(() => {
    if (!loading && !user) navigate("/auth");
  }, [user, loading, navigate]);

  useEffect(() => {
    if (!user) return;
    fetchEntities();
    fetchDocuments();
    fetchSanctions();
    fetchUsers();
    fetchLogs();
  }, [user]);

  // ── Entities ──────────────────────────────────────────────
  const fetchEntities = async () => {
    const { data } = await supabase
      .from("entities")
      .select("*, profiles(full_name, email)")
      .order("created_at", { ascending: false });
    const rows = data ?? [];
    setEntities(rows);
    setStats({
      total: rows.length,
      submitted: rows.filter((r) => r.application_status === "submitted").length,
      under_review: rows.filter((r) => r.application_status === "under_review").length,
      approved: rows.filter((r) => r.application_status === "approved").length,
      rejected: rows.filter((r) => r.application_status === "rejected").length,
      draft: rows.filter((r) => r.application_status === "draft").length,
    });
  };

  const fetchDocuments = async () => {
    const { data, error } = await supabase
      .from("kyc_documents")
      .select("*")
      .order("uploaded_at", { ascending: false });
    if (error) return toast.error(error.message);
    setDocuments(data ?? []);
  };

  const openDocument = async (doc: any) => {
    const { data, error } = await supabase.storage.from("kyc-documents").createSignedUrl(doc.storage_path, 60 * 10);
    if (error) return toast.error(error.message);
    window.open(data.signedUrl, "_blank", "noopener,noreferrer");
  };

  const submitDocumentReview = async () => {
    if (!docReview || !user) return;
    const payload = {
      status: docReview.status,
      rejection_reason: docReview.status === "rejected" ? docReason.trim() : null,
      reviewed_by: user.id,
      reviewed_at: new Date().toISOString(),
    };
    const { error } = await supabase.from("kyc_documents").update(payload as any).eq("id", docReview.doc.id);
    if (error) return toast.error(error.message);
    await supabase.from("user_audit_logs").insert({
      user_id: user.id,
      action: `document_${docReview.status}`,
      description: `${docReview.status === "approved" ? "اعتمد" : "رفض"} المستند: ${docReview.doc.file_name}${docReason ? ". السبب: " + docReason : ""}`,
    });
    toast.success(docReview.status === "approved" ? "تم اعتماد المستند" : "تم رفض المستند");
    setDocReview(null);
    setDocReason("");
    fetchDocuments();
    fetchLogs();
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
    await supabase.from("user_audit_logs").insert({
      user_id: user!.id,
      action: `entity_${newStatus}`,
      description: `${newStatus === "approved" ? "وافق على" : "رفض"} الكيان: ${entity.entity_name}${reviewNotes ? ". السبب: " + reviewNotes : ""}`,
    });
    setBusy(null);
    setReviewModal(null);
    setReviewNotes("");
    if (error) return toast.error(error.message);
    toast.success(action === "approve" ? "✅ تمت الموافقة على الكيان" : "❌ تم رفض الكيان");
    fetchEntities();
    fetchLogs();
  };

  // ── Sanctions ─────────────────────────────────────────────
  const fetchSanctions = async () => {
    const { data } = await supabase.from("sanctions_list").select("*").order("english_name");
    setSanctions(data ?? []);
  };

  const addSanction = async () => {
    if (!newSanction.english_name.trim()) return toast.error("الاسم الإنجليزي مطلوب");
    const { error } = await supabase.from("sanctions_list").insert({
      ...newSanction,
      status: "active",
    });
    if (error) return toast.error(error.message);
    toast.success("تمت الإضافة إلى قائمة العقوبات");
    setAddModal(false);
    setNewSanction({ english_name: "", arabic_name: "", country: "", type: "", list_reference: "", source: "" });
    fetchSanctions();
  };

  const deleteSanction = async (id: string) => {
    const { error } = await supabase.from("sanctions_list").delete().eq("id", id);
    if (error) return toast.error(error.message);
    toast.success("تم الحذف من القائمة");
    setDeleteConfirm(null);
    fetchSanctions();
  };

  const uploadCSV = (file: File) => {
    setUploading(true);
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      complete: async (res) => {
        const records = (res.data as any[])
          .map((r) => ({
            english_name: r.english_name || r.name || r.Name || r["English Name"],
            arabic_name: r.arabic_name || r["Arabic Name"] || null,
            country: r.country || r.Country || null,
            type: r.type || r.Type || null,
            list_reference: r.list_reference || r.reference || null,
            source: r.source || r.Source || null,
            status: "active",
          }))
          .filter((r) => r.english_name);
        if (!records.length) {
          setUploading(false);
          return toast.error("لا توجد سجلات صالحة في الملف");
        }
        const { error } = await supabase.from("sanctions_list").insert(records);
        setUploading(false);
        if (error) return toast.error(error.message);
        toast.success(`تمت إضافة ${records.length} سجل`);
        fetchSanctions();
      },
    });
  };

  // ── Users ──────────────────────────────────────────────────
  const fetchUsers = async () => {
    const { data } = await supabase
      .from("profiles")
      .select("*, user_roles(role)")
      .order("created_at", { ascending: false });
    setUsers(data ?? []);
  };

  const setUserRole = async (userId: string, newRole: string) => {
    // Delete existing role then insert new one
    await supabase.from("user_roles").delete().eq("user_id", userId);
    if (newRole !== "user") {
      const { error } = await supabase.from("user_roles").insert({ user_id: userId, role: newRole } as any);
      if (error) return toast.error(error.message);
    }
    toast.success("تم تحديث صلاحية المستخدم");
    fetchUsers();
  };

  // ── Logs ───────────────────────────────────────────────────
  const fetchLogs = async () => {
    const { data } = await supabase
      .from("user_audit_logs")
      .select("*, profiles(full_name, email)")
      .order("created_at", { ascending: false })
      .limit(100);
    setLogs(data ?? []);
  };

  // ── Guards ─────────────────────────────────────────────────
  if (loading || roleLoading) {
    return (
      <AppShell>
        <div className="text-center py-20 text-muted-foreground">جاري التحميل...</div>
      </AppShell>
    );
  }

  if (role !== "admin") {
    return (
      <AppShell>
        <div className="max-w-lg mx-auto text-center py-20 space-y-4">
          <AlertCircle className="size-12 text-destructive mx-auto" />
          <h2 className="text-xl font-bold">غير مصرح</h2>
          <p className="text-muted-foreground">هذه الصفحة مخصصة للمشرفين فقط.</p>
          <Button asChild variant="outline"><Link to="/">العودة للرئيسية</Link></Button>
        </div>
      </AppShell>
    );
  }

  const filteredEntities = entities.filter((e) => {
    if (statusFilter !== "all" && e.application_status !== statusFilter) return false;
    if (q && !e.entity_name?.toLowerCase().includes(q.toLowerCase()) &&
      !e.engagement_number?.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const filteredSanctions = sanctions.filter((s) =>
    !sanctionQ ||
    s.english_name?.toLowerCase().includes(sanctionQ.toLowerCase()) ||
    s.arabic_name?.includes(sanctionQ)
  );

  const filteredDocuments = documents.filter((doc) => {
    if (docStatusFilter !== "all" && (doc.status ?? "pending") !== docStatusFilter) return false;
    if (docTypeFilter !== "all" && doc.document_type !== docTypeFilter) return false;
    const query = docQ.trim().toLowerCase();
    if (!query) return true;
    return [doc.file_name, doc.document_type, doc.entity_id, doc.user_id]
      .some((value) => String(value ?? "").toLowerCase().includes(query));
  });

  const tabs: { id: Tab; label: string; icon: any }[] = [
    { id: "overview", label: "نظرة عامة", icon: BarChart3 },
    { id: "entities", label: `الكيانات (${entities.length})`, icon: Building2 },
    { id: "documents", label: `المستندات (${documents.length})`, icon: FileText },
    { id: "sanctions", label: `قائمة العقوبات (${sanctions.length})`, icon: ScrollText },
    { id: "users", label: `المستخدمون (${users.length})`, icon: Users },
    { id: "logs", label: "سجل النشاط", icon: Activity },
  ];

  return (
    <AppShell>
      <div className="max-w-7xl mx-auto space-y-6">

        {/* Header */}
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-3xl font-bold flex items-center gap-2">
              <Shield className="size-8 text-primary" /> لوحة الإدارة
            </h1>
            <p className="text-muted-foreground text-sm mt-1">
              إدارة شاملة للكيانات، العقوبات، المستخدمين وسجل النشاط
            </p>
          </div>
          <Badge variant="outline" className="text-xs px-3 py-1">
            {role === "admin" ? "مشرف" : role === "auditor" ? "مراجع" : "مشرف وسيط"}
          </Badge>
        </div>

        {/* Tabs */}
        <div className="flex gap-1 border-b border-border overflow-x-auto">
          {tabs.map((t) => {
            const Icon = t.icon;
            return (
              <button
                key={t.id}
                onClick={() => setTab(t.id)}
                className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                  tab === t.id
                    ? "border-primary text-primary"
                    : "border-transparent text-muted-foreground hover:text-foreground"
                }`}
              >
                <Icon className="size-4" />
                {t.label}
              </button>
            );
          })}
        </div>

        {/* ─── TAB: OVERVIEW ─────────────────────────────────── */}
        {tab === "overview" && (
          <div className="space-y-6">
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
              {[
                { label: "إجمالي الكيانات", value: stats.total, color: "text-foreground", bg: "bg-muted/40" },
                { label: "مسودة", value: stats.draft, color: "text-muted-foreground", bg: "bg-muted/40" },
                { label: "مُقدَّمة", value: stats.submitted, color: "text-blue-600", bg: "bg-blue-50 dark:bg-blue-950/30" },
                { label: "قيد المراجعة", value: stats.under_review, color: "text-yellow-600", bg: "bg-yellow-50 dark:bg-yellow-950/30" },
                { label: "معتمدة", value: stats.approved, color: "text-green-600", bg: "bg-green-50 dark:bg-green-950/30" },
                { label: "مرفوضة", value: stats.rejected, color: "text-destructive", bg: "bg-destructive/10" },
                { label: "مستندات معلّقة", value: documents.filter((d) => (d.status ?? "pending") === "pending").length, color: "text-warning", bg: "bg-warning/10" },
                { label: "مستندات معتمدة", value: documents.filter((d) => d.status === "approved").length, color: "text-success", bg: "bg-success/10" },
                { label: "مستندات مرفوضة", value: documents.filter((d) => d.status === "rejected").length, color: "text-destructive", bg: "bg-destructive/10" },
              ].map((c) => (
                <Card key={c.label} className="shadow-card">
                  <CardContent className="p-4 text-center">
                    <div className={`text-3xl font-bold ${c.color}`}>{c.value}</div>
                    <div className="text-xs text-muted-foreground mt-1">{c.label}</div>
                  </CardContent>
                </Card>
              ))}
            </div>

            {/* Quick actions */}
            <div className="grid md:grid-cols-2 gap-4">
              <Card className="shadow-card">
                <CardHeader><CardTitle className="text-base">آخر الكيانات المُقدَّمة</CardTitle></CardHeader>
                <CardContent className="p-0">
                  {entities.filter((e) => e.application_status === "submitted").slice(0, 5).length === 0 ? (
                    <div className="py-8 text-center text-sm text-muted-foreground">لا توجد كيانات مُقدَّمة</div>
                  ) : (
                    <ul className="divide-y divide-border">
                      {entities.filter((e) => e.application_status === "submitted").slice(0, 5).map((e) => (
                        <li key={e.id} className="flex items-center justify-between px-4 py-3 hover:bg-muted/20">
                          <div>
                            <div className="text-sm font-medium">{e.entity_name}</div>
                            <div className="text-xs text-muted-foreground">{e.profiles?.email ?? "—"}</div>
                          </div>
                          <Button size="sm" variant="outline" onClick={() => { setTab("entities"); }}>
                            مراجعة
                          </Button>
                        </li>
                      ))}
                    </ul>
                  )}
                </CardContent>
              </Card>

              <Card className="shadow-card">
                <CardHeader><CardTitle className="text-base">آخر نشاطات المراجعة</CardTitle></CardHeader>
                <CardContent className="p-0">
                  {logs.slice(0, 5).length === 0 ? (
                    <div className="py-8 text-center text-sm text-muted-foreground">لا توجد نشاطات</div>
                  ) : (
                    <ul className="divide-y divide-border">
                      {logs.slice(0, 5).map((log) => (
                        <li key={log.id} className="px-4 py-3">
                          <div className="text-xs font-medium truncate">{log.description}</div>
                          <div className="text-xs text-muted-foreground mt-0.5">
                            {new Date(log.created_at).toLocaleString("ar-AE")}
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                </CardContent>
              </Card>
            </div>
          </div>
        )}

        {/* ─── TAB: ENTITIES ─────────────────────────────────── */}
        {tab === "entities" && (
          <div className="space-y-4">
            <Card className="shadow-card">
              <CardContent className="p-4">
                <div className="flex flex-wrap gap-3">
                  <div className="relative flex-1 min-w-48">
                    <Search className="absolute start-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                    <Input placeholder="بحث باسم الكيان..." value={q} onChange={(e) => setQ(e.target.value)} className="ps-9" />
                  </div>
                  <NativeSelect value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} style={{ width: 180 }}>
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

            <Card className="shadow-card">
              <CardHeader><CardTitle>الكيانات ({filteredEntities.length})</CardTitle></CardHeader>
              <CardContent className="p-0">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-muted/40 border-b border-border">
                      <tr>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">اسم الكيان</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">العميل</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">الحالة</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">تاريخ الإنشاء</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">الإجراءات</th>
                      </tr>
                    </thead>
                    <tbody>
                      {filteredEntities.length === 0 ? (
                        <tr><td colSpan={5} className="py-12 text-center text-muted-foreground">لا توجد كيانات</td></tr>
                      ) : filteredEntities.map((e) => (
                        <tr key={e.id} className="border-b border-border/60 hover:bg-muted/20 transition-colors">
                          <td className="py-3 px-4 font-medium">{e.entity_name}</td>
                          <td className="py-3 px-4 text-xs text-muted-foreground">
                            <div>{e.profiles?.full_name ?? "—"}</div>
                            <div className="opacity-70">{e.profiles?.email ?? ""}</div>
                          </td>
                          <td className="py-3 px-4">
                            <Badge variant={(STATUS_COLORS[e.application_status] as any) ?? "secondary"}>
                              {STATUS_LABELS[e.application_status] ?? e.application_status}
                            </Badge>
                          </td>
                          <td className="py-3 px-4 text-xs text-muted-foreground">
                            {new Date(e.created_at).toLocaleDateString("ar-AE")}
                          </td>
                          <td className="py-3 px-4">
                            <div className="flex items-center gap-1.5 flex-wrap">
                              <Button asChild size="sm" variant="outline" title="عرض">
                                <Link to={`/kyc/${e.id}/kyc`}><Eye className="size-3.5" /></Link>
                              </Button>
                              <Button asChild size="sm" variant="outline" title="فحص">
                                <Link to={`/screening`}><ShieldCheck className="size-3.5" /></Link>
                              </Button>
                              <Button asChild size="sm" variant="outline" title="CDD">
                                <Link to={`/cdd/${e.id}`}><FileText className="size-3.5" /></Link>
                              </Button>
                              {e.application_status === "submitted" && (
                                <Button size="sm" variant="outline" disabled={busy === e.id} onClick={() => moveToReview(e.id)}>
                                  {busy === e.id ? "..." : "بدء المراجعة"}
                                </Button>
                              )}
                              {e.application_status === "under_review" && (
                                <>
                                  <Button size="sm" className="bg-green-600 hover:bg-green-700 text-white"
                                    onClick={() => setReviewModal({ entity: e, action: "approve" })}>
                                    موافقة
                                  </Button>
                                  <Button size="sm" variant="destructive"
                                    onClick={() => setReviewModal({ entity: e, action: "reject" })}>
                                    رفض
                                  </Button>
                                </>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        {/* ─── TAB: DOCUMENTS ────────────────────────────────── */}
        {tab === "documents" && (
          <div className="space-y-4">
            <Card className="shadow-card">
              <CardContent className="p-4">
                <div className="flex flex-wrap gap-3">
                  <div className="relative flex-1 min-w-56">
                    <Search className="absolute start-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                    <Input placeholder="بحث باسم الملف أو الكيان أو المستخدم..." value={docQ} onChange={(e) => setDocQ(e.target.value)} className="ps-9" />
                  </div>
                  <NativeSelect value={docStatusFilter} onChange={(e) => setDocStatusFilter(e.target.value)} style={{ width: 170 }}>
                    <option value="all">كل حالات المستندات</option>
                    <option value="pending">قيد المراجعة</option>
                    <option value="approved">معتمدة</option>
                    <option value="rejected">مرفوضة</option>
                  </NativeSelect>
                  <NativeSelect value={docTypeFilter} onChange={(e) => setDocTypeFilter(e.target.value)} style={{ width: 170 }}>
                    <option value="all">كل الأنواع</option>
                    <option value="cdd_identity">هوية</option>
                    <option value="cdd_eligibility">أهلية</option>
                    <option value="cdd_auditor">مدقق</option>
                  </NativeSelect>
                  <Button variant="outline" onClick={fetchDocuments}>
                    <RefreshCw className="size-4" /> تحديث
                  </Button>
                </div>
              </CardContent>
            </Card>

            <Card className="shadow-card">
              <CardHeader><CardTitle className="flex items-center gap-2"><FileText className="size-5" /> مستندات CDD ({filteredDocuments.length})</CardTitle></CardHeader>
              <CardContent className="p-0">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-muted/40 border-b border-border">
                      <tr>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">المستند</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">النوع</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">الحالة</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">تاريخ الرفع</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">الإجراءات</th>
                      </tr>
                    </thead>
                    <tbody>
                      {filteredDocuments.length === 0 ? (
                        <tr><td colSpan={5} className="py-12 text-center text-muted-foreground">لا توجد مستندات</td></tr>
                      ) : filteredDocuments.map((doc) => {
                        const status = doc.status ?? "pending";
                        return (
                          <tr key={doc.id} className="border-b border-border/60 hover:bg-muted/20 transition-colors">
                            <td className="py-3 px-4">
                              <div className="font-medium max-w-64 truncate">{doc.file_name}</div>
                              <div className="text-xs text-muted-foreground font-mono truncate">{doc.entity_id}</div>
                            </td>
                            <td className="py-3 px-4"><Badge variant="secondary">{doc.document_type}</Badge></td>
                            <td className="py-3 px-4">
                              <Badge variant={status === "approved" ? "success" : status === "rejected" ? "destructive" : "warning"}>
                                {status === "approved" ? "معتمد" : status === "rejected" ? "مرفوض" : "قيد المراجعة"}
                              </Badge>
                              {doc.rejection_reason && <div className="mt-1 text-xs text-destructive max-w-48 truncate">{doc.rejection_reason}</div>}
                            </td>
                            <td className="py-3 px-4 text-xs text-muted-foreground whitespace-nowrap">{new Date(doc.uploaded_at).toLocaleString("ar-AE")}</td>
                            <td className="py-3 px-4">
                              <div className="flex flex-wrap items-center gap-1.5">
                                <Button size="sm" variant="outline" onClick={() => openDocument(doc)}><ExternalLink className="size-3.5" /> عرض</Button>
                                {status !== "approved" && <Button size="sm" variant="success" onClick={() => setDocReview({ doc, status: "approved" })}>اعتماد</Button>}
                                {status !== "rejected" && <Button size="sm" variant="destructive" onClick={() => setDocReview({ doc, status: "rejected" })}>رفض</Button>}
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        {/* ─── TAB: SANCTIONS ────────────────────────────────── */}
        {tab === "sanctions" && (
          <div className="space-y-4">
            {/* Toolbar */}
            <div className="flex flex-wrap gap-3 items-center">
              <div className="relative flex-1 min-w-48">
                <Search className="absolute start-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <Input placeholder="بحث بالاسم..." value={sanctionQ} onChange={(e) => setSanctionQ(e.target.value)} className="ps-9" />
              </div>
              <Button variant="outline" onClick={() => fileRef.current?.click()} disabled={uploading}>
                <Upload className="size-4" /> {uploading ? "جاري الرفع..." : "استيراد CSV"}
              </Button>
              <input ref={fileRef} type="file" accept=".csv" className="hidden"
                onChange={(e) => { const f = e.target.files?.[0]; if (f) uploadCSV(f); e.target.value = ""; }} />
              <Button onClick={() => setAddModal(true)}>
                <Plus className="size-4" /> إضافة شخص
              </Button>
            </div>

            {/* Table */}
            <Card className="shadow-card">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <ScrollText className="size-5 text-destructive" />
                  قائمة العقوبات ({filteredSanctions.length})
                </CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-muted/40 border-b border-border">
                      <tr>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">الاسم الإنجليزي</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">الاسم العربي</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">الدولة</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">النوع</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">المرجع</th>
                        <th className="py-3 px-4 text-start font-medium text-muted-foreground">حذف</th>
                      </tr>
                    </thead>
                    <tbody>
                      {filteredSanctions.length === 0 ? (
                        <tr><td colSpan={6} className="py-12 text-center text-muted-foreground">القائمة فارغة</td></tr>
                      ) : filteredSanctions.map((s) => (
                        <tr key={s.id} className="border-b border-border/60 hover:bg-muted/20">
                          <td className="py-3 px-4 font-medium">{s.english_name}</td>
                          <td className="py-3 px-4 text-muted-foreground">{s.arabic_name ?? "—"}</td>
                          <td className="py-3 px-4 text-xs">{s.country ?? "—"}</td>
                          <td className="py-3 px-4 text-xs">{s.type ?? "—"}</td>
                          <td className="py-3 px-4 text-xs text-muted-foreground">{s.list_reference ?? "—"}</td>
                          <td className="py-3 px-4">
                            {deleteConfirm === s.id ? (
                              <div className="flex gap-1.5">
                                <Button size="sm" variant="destructive" onClick={() => deleteSanction(s.id)}>تأكيد</Button>
                                <Button size="sm" variant="ghost" onClick={() => setDeleteConfirm(null)}>إلغاء</Button>
                              </div>
                            ) : (
                              <Button size="sm" variant="ghost" className="text-destructive hover:text-destructive"
                                onClick={() => setDeleteConfirm(s.id)}>
                                <Trash2 className="size-4" />
                              </Button>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        {/* ─── TAB: USERS ────────────────────────────────────── */}
        {tab === "users" && (
          <Card className="shadow-card">
            <CardHeader><CardTitle className="flex items-center gap-2"><Users className="size-5" /> إدارة المستخدمين ({users.length})</CardTitle></CardHeader>
            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-muted/40 border-b border-border">
                    <tr>
                      <th className="py-3 px-4 text-start font-medium text-muted-foreground">الاسم</th>
                      <th className="py-3 px-4 text-start font-medium text-muted-foreground">البريد</th>
                      <th className="py-3 px-4 text-start font-medium text-muted-foreground">تاريخ الإنشاء</th>
                      <th className="py-3 px-4 text-start font-medium text-muted-foreground">الصلاحية</th>
                    </tr>
                  </thead>
                  <tbody>
                    {users.length === 0 ? (
                      <tr><td colSpan={4} className="py-12 text-center text-muted-foreground">لا توجد مستخدمون</td></tr>
                    ) : users.map((u) => {
                      const userRole = u.user_roles?.[0]?.role ?? "user";
                      return (
                        <tr key={u.id} className="border-b border-border/60 hover:bg-muted/20">
                          <td className="py-3 px-4 font-medium">{u.full_name ?? "—"}</td>
                          <td className="py-3 px-4 text-muted-foreground text-xs">{u.email ?? "—"}</td>
                          <td className="py-3 px-4 text-xs text-muted-foreground">
                            {new Date(u.created_at).toLocaleDateString("ar-AE")}
                          </td>
                          <td className="py-3 px-4">
                            {role === "admin" ? (
                              <NativeSelect
                                value={userRole}
                                onChange={(e) => setUserRole(u.id, e.target.value)}
                                style={{ width: 140 }}
                              >
                                <option value="user">مستخدم</option>
                                <option value="moderator">مشرف وسيط</option>
                                <option value="admin">مشرف</option>
                              </NativeSelect>
                            ) : (
                              <Badge variant="secondary">{userRole === "admin" ? "مشرف" : userRole === "moderator" ? "مشرف وسيط" : "مستخدم"}</Badge>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        )}

        {/* ─── TAB: LOGS ─────────────────────────────────────── */}
        {tab === "logs" && (
          <Card className="shadow-card">
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2"><Activity className="size-5" /> سجل النشاط</CardTitle>
                <Button size="sm" variant="outline" onClick={fetchLogs}>تحديث</Button>
              </div>
            </CardHeader>
            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-muted/40 border-b border-border">
                    <tr>
                      <th className="py-3 px-4 text-start font-medium text-muted-foreground">الوقت</th>
                      <th className="py-3 px-4 text-start font-medium text-muted-foreground">المستخدم</th>
                      <th className="py-3 px-4 text-start font-medium text-muted-foreground">الإجراء</th>
                      <th className="py-3 px-4 text-start font-medium text-muted-foreground">التفاصيل</th>
                    </tr>
                  </thead>
                  <tbody>
                    {logs.length === 0 ? (
                      <tr><td colSpan={4} className="py-12 text-center text-muted-foreground">لا توجد سجلات</td></tr>
                    ) : logs.map((log) => (
                      <tr key={log.id} className="border-b border-border/60 hover:bg-muted/20">
                        <td className="py-3 px-4 text-xs text-muted-foreground whitespace-nowrap">
                          {new Date(log.created_at).toLocaleString("ar-AE")}
                        </td>
                        <td className="py-3 px-4 text-xs">
                          <div>{log.profiles?.full_name ?? "—"}</div>
                          <div className="opacity-60">{log.profiles?.email ?? ""}</div>
                        </td>
                        <td className="py-3 px-4">
                          <Badge variant="outline" className="text-xs font-mono">{log.action}</Badge>
                        </td>
                        <td className="py-3 px-4 text-xs text-muted-foreground max-w-xs truncate">
                          {log.description ?? "—"}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        )}
      </div>

      {/* ─── Modal: Review Entity ──────────────────────────── */}
      {reviewModal && (
        <div className="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
          <Card className="w-full max-w-md shadow-2xl">
            <CardHeader>
              <CardTitle>
                {reviewModal.action === "approve" ? "✅ تأكيد الموافقة" : "❌ تأكيد الرفض"}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-sm text-muted-foreground">
                الكيان: <strong className="text-foreground">{reviewModal.entity.entity_name}</strong>
              </p>
              {reviewModal.action === "reject" && (
                <Field label="سبب الرفض *">
                  <Textarea
                    placeholder="اذكر سبب رفض الطلب..."
                    value={reviewNotes}
                    onChange={(e) => setReviewNotes(e.target.value)}
                    rows={3}
                  />
                </Field>
              )}
              {reviewModal.action === "approve" && (
                <div className="rounded-md bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 p-3 text-sm text-green-800 dark:text-green-300">
                  سيتم إخطار العميل بالموافقة على طلبه.
                </div>
              )}
              <div className="flex gap-3 justify-end pt-2 border-t border-border">
                <Button variant="outline" onClick={() => { setReviewModal(null); setReviewNotes(""); }}>
                  إلغاء
                </Button>
                <Button
                  className={reviewModal.action === "approve" ? "bg-green-600 hover:bg-green-700 text-white" : ""}
                  variant={reviewModal.action === "reject" ? "destructive" : "default"}
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

      {/* ─── Modal: Review Document ─────────────────────────── */}
      {docReview && (
        <div className="fixed inset-0 z-50 bg-foreground/60 flex items-center justify-center p-4">
          <Card className="w-full max-w-md shadow-2xl">
            <CardHeader>
              <CardTitle>{docReview.status === "approved" ? "تأكيد اعتماد المستند" : "تأكيد رفض المستند"}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-sm text-muted-foreground">
                المستند: <strong className="text-foreground">{docReview.doc.file_name}</strong>
              </p>
              {docReview.status === "rejected" && (
                <Field label="سبب الرفض *">
                  <Textarea value={docReason} onChange={(e) => setDocReason(e.target.value)} rows={3} placeholder="اكتب سبب الرفض..." />
                </Field>
              )}
              <div className="flex gap-3 justify-end pt-2 border-t border-border">
                <Button variant="outline" onClick={() => { setDocReview(null); setDocReason(""); }}>إلغاء</Button>
                <Button variant={docReview.status === "rejected" ? "destructive" : "success"} onClick={submitDocumentReview} disabled={docReview.status === "rejected" && !docReason.trim()}>
                  {docReview.status === "approved" ? "اعتماد" : "رفض"}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      {/* ─── Modal: Add Sanction ───────────────────────────── */}
      {addModal && (
        <div className="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
          <Card className="w-full max-w-lg shadow-2xl">
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Plus className="size-5" /> إضافة إلى قائمة العقوبات
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid md:grid-cols-2 gap-3">
                <Field label="الاسم الإنجليزي *">
                  <Input placeholder="Full Name" value={newSanction.english_name}
                    onChange={(e) => setNewSanction({ ...newSanction, english_name: e.target.value })} />
                </Field>
                <Field label="الاسم العربي">
                  <Input placeholder="الاسم بالعربي" value={newSanction.arabic_name}
                    onChange={(e) => setNewSanction({ ...newSanction, arabic_name: e.target.value })} />
                </Field>
                <Field label="الدولة">
                  <Input placeholder="UAE, Syria, ..." value={newSanction.country}
                    onChange={(e) => setNewSanction({ ...newSanction, country: e.target.value })} />
                </Field>
                <Field label="النوع">
                  <NativeSelect value={newSanction.type}
                    onChange={(e) => setNewSanction({ ...newSanction, type: e.target.value })}>
                    <option value="">—</option>
                    <option value="Individual">شخص طبيعي</option>
                    <option value="Entity">كيان/شركة</option>
                    <option value="Vessel">سفينة</option>
                  </NativeSelect>
                </Field>
                <Field label="رقم المرجع">
                  <Input placeholder="UN-123 / OFAC-456" value={newSanction.list_reference}
                    onChange={(e) => setNewSanction({ ...newSanction, list_reference: e.target.value })} />
                </Field>
                <Field label="المصدر">
                  <Input placeholder="OFAC / UN / EU" value={newSanction.source}
                    onChange={(e) => setNewSanction({ ...newSanction, source: e.target.value })} />
                </Field>
              </div>
              <div className="flex gap-3 justify-end pt-2 border-t border-border">
                <Button variant="outline" onClick={() => setAddModal(false)}>إلغاء</Button>
                <Button onClick={addSanction} disabled={!newSanction.english_name.trim()}>
                  <Plus className="size-4" /> إضافة
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </AppShell>
  );
}
