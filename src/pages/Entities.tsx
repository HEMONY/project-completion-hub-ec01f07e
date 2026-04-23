import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { supabase } from "@/integrations/supabase/client";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "./Index";
import { Badge } from "@/components/ui/badge";

export default function Entities() {
  const { user, loading } = useAuth();
  const { t } = useI18n();
  const navigate = useNavigate();
  const [rows, setRows] = useState<any[]>([]);
  const [q, setQ] = useState("");

  useEffect(() => {
    if (!loading && !user) navigate("/auth");
  }, [user, loading, navigate]);

  useEffect(() => {
    if (!user) return;
    supabase.from("entities").select("*").order("created_at", { ascending: false }).then(({ data }) => setRows(data ?? []));
  }, [user]);

  const filtered = rows.filter(
    (r) => !q || r.entity_name?.toLowerCase().includes(q.toLowerCase()) || r.engagement_number?.toLowerCase().includes(q.toLowerCase())
  );

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
                      <th className="py-3 text-start">Engagement #</th>
                      <th className="py-3 text-start">Type</th>
                      <th className="py-3 text-start">Status</th>
                      <th className="py-3 text-start">Created</th>
                      <th className="py-3 text-end pe-3">Actions</th>
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
