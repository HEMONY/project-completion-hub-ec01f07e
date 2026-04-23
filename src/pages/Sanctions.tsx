import { useEffect, useRef, useState } from "react";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { useI18n } from "@/lib/i18n";
import { useAuth } from "@/lib/auth";
import { supabase } from "@/integrations/supabase/client";
import { Upload, ListChecks, ShieldAlert } from "lucide-react";
import Papa from "papaparse";
import { toast } from "sonner";

export default function Sanctions() {
  const { t } = useI18n();
  const { user } = useAuth();
  const [rows, setRows] = useState<any[]>([]);
  const [q, setQ] = useState("");
  const [uploading, setUploading] = useState(false);
  const [isAdmin, setIsAdmin] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  const refresh = () => supabase.from("sanctions_list").select("*").order("english_name").then(({ data }) => setRows(data ?? []));

  useEffect(() => {
    refresh();
    if (user) {
      supabase.from("user_roles").select("role").eq("user_id", user.id).eq("role", "admin").maybeSingle().then(({ data }) => setIsAdmin(!!data));
    }
  }, [user]);

  const filtered = rows.filter((r) => !q || r.english_name?.toLowerCase().includes(q.toLowerCase()) || r.arabic_name?.includes(q));

  const onUpload = (file: File) => {
    setUploading(true);
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      complete: async (res) => {
        const records = (res.data as any[]).map((r) => ({
          english_name: r.english_name || r.name || r.Name || r["English Name"],
          arabic_name: r.arabic_name || r["Arabic Name"] || null,
          country: r.country || r.Country || null,
          type: r.type || r.Type || null,
          list_reference: r.list_reference || r.reference || null,
          source: r.source || r.Source || null,
          status: r.status || "active",
        })).filter((r) => r.english_name);
        if (records.length === 0) {
          toast.error(t("error_generic"));
          setUploading(false);
          return;
        }
        const { error } = await supabase.from("sanctions_list").insert(records);
        setUploading(false);
        if (error) toast.error(error.message);
        else {
          toast.success(`${records.length} records added`);
          refresh();
        }
      },
    });
  };

  return (
    <AppShell>
      <div className="max-w-7xl mx-auto space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="text-3xl font-bold flex items-center gap-2"><ShieldAlert className="size-7 text-primary" /> {t("sanctions_title")}</h1>
          </div>
          {isAdmin && (
            <div>
              <input ref={fileRef} type="file" accept=".csv" hidden onChange={(e) => e.target.files?.[0] && onUpload(e.target.files[0])} />
              <Button variant="premium" disabled={uploading} onClick={() => fileRef.current?.click()}>
                <Upload className="size-4" /> {uploading ? t("loading") : "Upload CSV"}
              </Button>
            </div>
          )}
        </div>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard label={t("sanctions_total")} value={rows.length} icon={ListChecks} />
          <StatCard label={t("sanctions_active")} value={rows.filter((r) => r.status === "active").length} icon={ShieldAlert} />
        </div>
        <Card className="shadow-card">
          <CardHeader>
            <Input placeholder={t("sanctions_search")} value={q} onChange={(e) => setQ(e.target.value)} className="max-w-sm" />
          </CardHeader>
          <CardContent>
            {filtered.length === 0 ? (
              <div className="py-12 text-center text-muted-foreground text-sm">No records yet. {isAdmin ? "Upload a CSV to populate." : ""}</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="text-start text-muted-foreground border-b border-border">
                    <tr>
                      <th className="py-3 text-start ps-3">English Name</th>
                      <th className="py-3 text-start">Arabic</th>
                      <th className="py-3 text-start">Country</th>
                      <th className="py-3 text-start">Type</th>
                      <th className="py-3 text-start">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filtered.slice(0, 200).map((r) => (
                      <tr key={r.id} className="border-b border-border/60 hover:bg-accent/30">
                        <td className="py-2 ps-3 font-medium">{r.english_name}</td>
                        <td className="py-2">{r.arabic_name ?? "—"}</td>
                        <td className="py-2">{r.country ?? "—"}</td>
                        <td className="py-2"><Badge variant="outline">{r.type ?? "—"}</Badge></td>
                        <td className="py-2"><Badge variant={r.status === "active" ? "success" : "secondary"}>{r.status}</Badge></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                {filtered.length > 200 && <div className="text-xs text-muted-foreground mt-3">Showing 200 of {filtered.length}</div>}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}

function StatCard({ label, value, icon: Icon }: { label: string; value: number; icon: any }) {
  return (
    <Card className="shadow-card">
      <CardContent className="p-5 flex items-center justify-between">
        <div>
          <div className="text-sm text-muted-foreground">{label}</div>
          <div className="text-3xl font-bold mt-2">{value}</div>
        </div>
        <div className="size-10 rounded-lg bg-primary/10 text-primary grid place-items-center">
          <Icon className="size-5" />
        </div>
      </CardContent>
    </Card>
  );
}
