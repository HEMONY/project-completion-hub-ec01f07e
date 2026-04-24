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
import { cn } from "@/lib/utils";

const STATUS_FILTERS = ["all", "draft", "submitted", "under_review", "approved", "rejected"] as const;
type StatusFilter = typeof STATUS_FILTERS[number];

export default function Entities() {
  const { user, loading } = useAuth();
  const { t } = useI18n();
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const [rows, setRows] = useState<any[]>([]);
  const [q, setQ] = useState("");

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
                      <tr key={r.id} className="border-b border-border/60 last:border-0 hover:bg-accent/30">
                        <td className="py-3 ps-3 font-medium">{r.entity_name}</td>
                        <td className="py-3 font-mono text-xs">{r.engagement_number ?? "—"}</td>
                        <td className="py-3"><Badge variant="outline">{r.application_type}</Badge></td>
                        <td className="py-3"><StatusBadge status={r.application_status} /></td>
                        <td className="py-3 text-xs text-muted-foreground">{new Date(r.created_at).toLocaleDateString()}</td>
                        <td className="py-3 text-end pe-3">
                          <Button asChild size="sm" variant="outline">
                            <Link to={`/kyc/${r.id}/kyc`}>{t("btn_view")}</Link>
                          </Button>
                        </td>
                      </tr>
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
