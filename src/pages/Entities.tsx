import { useEffect, useMemo, useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { supabase } from "@/integrations/supabase/client";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "./Index";
import { Badge } from "@/components/ui/badge";
import { cn, formatDate } from "@/lib/utils";
import { DocumentPreview } from "@/components/DocumentPreview";
import { FileText, ChevronDown, ChevronUp } from "lucide-react";

const STATUS_FILTERS = ["all", "draft", "submitted", "under_review", "approved", "rejected"] as const;
type StatusFilter = typeof STATUS_FILTERS[number];

export default function Entities() {
  const { user, loading } = useAuth();
  const { t } = useI18n();
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const [rows, setRows] = useState<any[]>([]);
  const [q, setQ] = useState("");
  const [openId, setOpenId] = useState<string | null>(null);
  const [docs, setDocs] = useState<Record<string, any[]>>({});

  const toggleDocs = async (id: string) => {
    if (openId === id) { setOpenId(null); return; }
    setOpenId(id);
    if (!docs[id]) {
      const { data } = await supabase.from("kyc_documents").select("*").eq("entity_id", id).order("uploaded_at", { ascending: false });
      setDocs((prev) => ({ ...prev, [id]: data ?? [] }));
    }
  };

  const status = (params.get("status") as StatusFilter) || "all";

  useEffect(() => {
    if (!loading && !user) navigate("/auth");
  }, [user, loading, navigate]);

  useEffect(() => {
    if (!user) return;
    supabase.from("entities").select("*").order("created_at", { ascending: false }).then(({ data }) => setRows(data ?? []));
  }, [user]);

  const counts = useMemo(() => {
    const c: Record<string, number> = { all: rows.length };
    rows.forEach((r) => {
      c[r.application_status] = (c[r.application_status] || 0) + 1;
    });
    return c;
  }, [rows]);

  const filtered = rows.filter((r) => {
    if (status !== "all" && r.application_status !== status) return false;
    if (q && !r.entity_name?.toLowerCase().includes(q.toLowerCase()) && !r.engagement_number?.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  if (!user) return null;

  return (
    <AppShell>
      <div className="max-w-7xl mx-auto space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="text-3xl font-bold">{t("nav_entities")}</h1>
            <p className="text-muted-foreground text-sm mt-1">{t("recent_entities")}</p>
          </div>
          <Button asChild variant="premium">
            <Link to="/kyc/start">{t("start_new_kyc")}</Link>
          </Button>
        </div>

        {/* Status filter chips */}
        <div className="flex flex-wrap gap-2">
          {STATUS_FILTERS.map((s) => {
            const active = status === s;
            const count = counts[s] ?? 0;
            return (
              <button
                key={s}
                onClick={() => {
                  if (s === "all") setParams({});
                  else setParams({ status: s });
                }}
                className={cn(
                  "px-3.5 py-1.5 rounded-full text-sm border transition-all flex items-center gap-2",
                  active
                    ? "bg-primary text-primary-foreground border-primary shadow-sm"
                    : "bg-card hover:bg-accent border-border text-foreground"
                )}
              >
                <span>{t(`filter_${s}` as any)}</span>
                <span className={cn("text-xs rounded-full px-1.5 py-0.5", active ? "bg-primary-foreground/20" : "bg-muted")}>
                  {count}
                </span>
              </button>
            );
          })}
        </div>

        <Card className="shadow-card">
          <CardHeader>
            <Input placeholder={t("sanctions_search")} value={q} onChange={(e) => setQ(e.target.value)} className="max-w-sm" />
          </CardHeader>
          <CardContent>
            {filtered.length === 0 ? (
              <div className="py-12 text-center text-muted-foreground text-sm">{t("no_entities")}</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="text-start text-muted-foreground border-b border-border">
                    <tr>
                      <th className="py-3 text-start ps-3">{t("kyc_owner_name")}</th>
                      <th className="py-3 text-start">{t("entities_engagement")}</th>
                      <th className="py-3 text-start">{t("entities_type")}</th>
                      <th className="py-3 text-start">{t("entities_status")}</th>
                      <th className="py-3 text-start">{t("entities_created")}</th>
                      <th className="py-3 text-end pe-3">{t("entities_actions")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filtered.map((r) => (
                      <>
                      <tr key={r.id} className="border-b border-border/60 last:border-0 hover:bg-accent/30">
                        <td className="py-3 ps-3 font-medium">{r.entity_name}</td>
                        <td className="py-3 font-mono text-xs">{r.engagement_number ?? "—"}</td>
                        <td className="py-3"><Badge variant="outline">{r.application_type}</Badge></td>
                        <td className="py-3"><StatusBadge status={r.application_status} /></td>
                        <td className="py-3 text-xs text-muted-foreground">{formatDate(r.created_at)}</td>
                        <td className="py-3 text-end pe-3">
                          <div className="flex justify-end gap-2 flex-wrap">
                            <Button size="sm" variant="ghost" onClick={() => toggleDocs(r.id)}>
                              <FileText className="size-3.5" />
                              {openId === r.id ? <ChevronUp className="size-3.5" /> : <ChevronDown className="size-3.5" />}
                            </Button>
                            <Button asChild size="sm" variant="outline">
                              <Link to={`/kyc/${r.id}/kyc`}>{t("btn_view")}</Link>
                            </Button>
                            <Button asChild size="sm" variant="ghost">
                              <Link to={`/cdd/${r.id}`}>CDD</Link>
                            </Button>
                          </div>
                        </td>
                      </tr>
                      {openId === r.id && (
                        <tr key={r.id + "-docs"} className="bg-muted/20">
                          <td colSpan={6} className="p-4">
                            <div className="text-sm font-semibold mb-3 flex items-center gap-2"><FileText className="size-4" /> المستندات المرفقة ({(docs[r.id] || []).length})</div>
                            {(docs[r.id] || []).length === 0 ? (
                              <div className="text-xs text-muted-foreground py-4 text-center">لا توجد مستندات مرفقة</div>
                            ) : (
                              <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                                {(docs[r.id] || []).map((d) => (
                                  <div key={d.id} className="border border-border rounded-md p-2 bg-card space-y-2">
                                    <div className="text-xs font-medium truncate">{d.file_name}</div>
                                    <div className="flex gap-2 items-center text-[10px] text-muted-foreground">
                                      <Badge variant="secondary" className="text-[10px]">{d.document_type}</Badge>
                                      <span>{formatDate(d.uploaded_at)}</span>
                                    </div>
                                    <DocumentPreview doc={d} />
                                  </div>
                                ))}
                              </div>
                            )}
                          </td>
                        </tr>
                      )}
                      </>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
