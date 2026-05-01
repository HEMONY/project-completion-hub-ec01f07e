import { useEffect, useState } from "react";
import { Link, Navigate, useNavigate } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { supabase } from "@/integrations/supabase/client";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Building2, FilePlus2, ShieldCheck, Sparkles, ArrowRight, CheckCircle2, Clock, XCircle } from "lucide-react";

type EntityRow = {
  id: string;
  entity_name: string;
  engagement_number: string | null;
  application_status: string;
  application_type: string;
  created_at: string;
};

export default function Index() {
  const { user, loading } = useAuth();
  const { t, dir } = useI18n();
  const navigate = useNavigate();
  const [entities, setEntities] = useState<EntityRow[]>([]);
  const [stats, setStats] = useState({ submitted: 0, approved: 0, rejected: 0, draft: 0 });

  useEffect(() => {
    if (!loading && !user) navigate("/auth");
  }, [user, loading, navigate]);

  useEffect(() => {
    if (!user) return;
    (async () => {
      const { data } = await supabase
        .from("entities")
        .select("id, entity_name, engagement_number, application_status, application_type, created_at")
        .order("created_at", { ascending: false })
        .limit(8);
      setEntities((data ?? []) as EntityRow[]);
      const all = await supabase.from("entities").select("application_status");
      const rows = all.data ?? [];
      setStats({
        submitted: rows.filter((r) => r.application_status === "submitted").length,
        approved: rows.filter((r) => r.application_status === "approved").length,
        rejected: rows.filter((r) => r.application_status === "rejected").length,
        draft: rows.filter((r) => ["draft", "under_review", "edited"].includes(r.application_status)).length,
      });
    })();
  }, [user]);

  if (loading || !user) {
    return (
      <div className="min-h-screen grid place-items-center bg-background text-foreground" dir={dir}>
        {t("loading")}
      </div>
    );
  }

  const statCards = [
    { label: t("card_submitted"), value: stats.submitted, icon: Clock, color: "text-info", bg: "bg-info/15" },
    { label: t("card_approved"), value: stats.approved, icon: CheckCircle2, color: "text-success", bg: "bg-success/15" },
    { label: t("card_rejected"), value: stats.rejected, icon: XCircle, color: "text-destructive", bg: "bg-destructive/15" },
    { label: t("card_in_progress"), value: stats.draft, icon: Building2, color: "text-warning", bg: "bg-warning/15" },
  ];

  return (
    <AppShell>
      <div className="space-y-8 max-w-7xl mx-auto">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="text-3xl md:text-4xl font-bold tracking-tight">{t("dashboard_title")}</h1>
            <p className="text-muted-foreground mt-1">{t("dashboard_subtitle")}</p>
          </div>
          <Button asChild variant="premium" size="lg">
            <Link to="/kyc/start">
              <FilePlus2 />
              {t("start_new_kyc")}
            </Link>
          </Button>
        </div>

        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {statCards.map((c) => (
            <Card key={c.label} className="overflow-hidden shadow-card hover:shadow-elegant transition-shadow">
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

        <div className="grid lg:grid-cols-3 gap-4">
          <Card className="lg:col-span-2 shadow-card">
            <CardHeader className="flex-row items-center justify-between">
              <CardTitle>{t("recent_entities")}</CardTitle>
              <Button variant="ghost" size="sm" asChild>
                <Link to="/entities">
                  {t("view_all")} <ArrowRight className="size-3.5" />
                </Link>
              </Button>
            </CardHeader>
            <CardContent>
              {entities.length === 0 ? (
                <div className="py-10 text-center text-sm text-muted-foreground">{t("no_entities")}</div>
              ) : (
                <ul className="divide-y divide-border">
                  {entities.map((e) => (
                    <li key={e.id} className="py-3 flex items-center justify-between gap-3">
                      <Link to={`/kyc/${e.id}/kyc`} className="min-w-0 flex-1 hover:opacity-80">
                        <div className="font-medium truncate">{e.entity_name}</div>
                        <div className="text-xs text-muted-foreground font-mono">{e.engagement_number ?? "—"}</div>
                      </Link>
                      <StatusBadge status={e.application_status} />
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>

          <Card className="shadow-card">
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Sparkles className="size-5 text-primary" />
                {t("ai_audit_title")}
              </CardTitle>
              <CardDescription>{t("ai_audit_subtitle")}</CardDescription>
            </CardHeader>
            <CardContent>
              <Button asChild variant="outline" className="w-full">
                <Link to="/audits">
                  <ShieldCheck />
                  {t("nav_audits")}
                </Link>
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}

export function StatusBadge({ status }: { status: string }) {
  const map: Record<string, { v: "default" | "secondary" | "success" | "warning" | "destructive" | "info"; label: string }> = {
    draft: { v: "secondary", label: "Draft" },
    submitted: { v: "info", label: "Submitted" },
    under_review: { v: "warning", label: "Under Review" },
    approved: { v: "success", label: "Approved" },
    rejected: { v: "destructive", label: "Rejected" },
    edited: { v: "warning", label: "Edited" },
    pending_review: { v: "warning", label: "Pending Review" },
  };
  const m = map[status] ?? { v: "secondary" as const, label: status };
  return <Badge variant={m.v}>{m.label}</Badge>;
}
