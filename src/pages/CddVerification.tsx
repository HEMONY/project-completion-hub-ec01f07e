import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { supabase } from "@/integrations/supabase/client";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { toast } from "sonner";
import { ArrowLeft, ShieldCheck, AlertCircle, Clock } from "lucide-react";

function NativeSelect(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select
      {...props}
      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
    />
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-sm">{label}</Label>
      {children}
    </div>
  );
}

type HistoryEntry = {
  at: string;
  by: string;
  identity?: string | null;
  eligibility?: string | null;
  auditor?: string | null;
  status?: string | null;
  notes?: string | null;
};

export default function CddVerification() {
  const { entityId = "" } = useParams();
  const { user, loading } = useAuth();
  const { t, dir } = useI18n();
  const navigate = useNavigate();

  const [entity, setEntity] = useState<any | null>(null);
  const [record, setRecord] = useState<any | null>(null);
  const [isAdmin, setIsAdmin] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [busy, setBusy] = useState(false);

  const [form, setForm] = useState({
    identity_verification: "",
    eligibility_verification: "",
    auditor_verification: "",
    economic_sector: "",
    eligibility_status: "pending",
    notes: "",
  });

  useEffect(() => {
    if (!loading && !user) navigate("/auth");
  }, [user, loading, navigate]);

  useEffect(() => {
    if (!user || !entityId) return;
    setLoadingData(true);
    Promise.all([
      supabase.from("entities").select("*").eq("id", entityId).maybeSingle(),
      supabase.from("cdd_verifications").select("*").eq("entity_id", entityId).maybeSingle(),
      supabase.rpc("has_role", { _user_id: user.id, _role: "admin" }),
    ]).then(([eRes, cRes, rRes]) => {
      if (eRes.error) toast.error(eRes.error.message);
      setEntity(eRes.data);
      if (cRes.data) {
        setRecord(cRes.data);
        setForm({
          identity_verification: cRes.data.identity_verification ?? "",
          eligibility_verification: cRes.data.eligibility_verification ?? "",
          auditor_verification: cRes.data.auditor_verification ?? "",
          economic_sector: cRes.data.economic_sector ?? "",
          eligibility_status: cRes.data.eligibility_status ?? "pending",
          notes: cRes.data.notes ?? "",
        });
      }
      setIsAdmin(Boolean(rRes.data));
      setLoadingData(false);
    });
  }, [user, entityId]);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!user || !isAdmin) return;
    setBusy(true);

    const prevHistory: HistoryEntry[] = Array.isArray(record?.verification_history)
      ? (record.verification_history as HistoryEntry[])
      : [];
    const historyEntry: HistoryEntry = {
      at: new Date().toISOString(),
      by: user.email ?? user.id,
      identity: form.identity_verification || null,
      eligibility: form.eligibility_verification || null,
      auditor: form.auditor_verification || null,
      status: form.eligibility_status || null,
      notes: form.notes || null,
    };
    const verification_history = [...prevHistory, historyEntry];

    const payload = {
      entity_id: entityId,
      admin_id: user.id,
      identity_verification: form.identity_verification || null,
      eligibility_verification: form.eligibility_verification || null,
      auditor_verification: form.auditor_verification || null,
      economic_sector: form.economic_sector || null,
      eligibility_status: form.eligibility_status,
      notes: form.notes || null,
      verification_history,
    };

    const { error, data } = record
      ? await supabase.from("cdd_verifications").update(payload).eq("id", record.id).select().maybeSingle()
      : await supabase.from("cdd_verifications").insert(payload).select().maybeSingle();

    // mark entity cdd_completed if all 3 verified
    const allVerified =
      form.identity_verification === "verified" &&
      form.eligibility_verification === "verified" &&
      form.auditor_verification === "verified";
    if (allVerified) {
      await supabase.from("entities").update({ cdd_completed: true }).eq("id", entityId);
    }

    setBusy(false);
    if (error) return toast.error(error.message);
    setRecord(data);
    toast.success(t("saved"));
  };

  if (!user || loadingData) {
    return (
      <AppShell>
        <div className="text-center py-20 text-muted-foreground">{t("loading")}</div>
      </AppShell>
    );
  }
  if (!entity) {
    return (
      <AppShell>
        <div className="text-center py-20 text-muted-foreground">Not found</div>
      </AppShell>
    );
  }

  const StatusIcon = ({ value }: { value: string | null }) => {
    if (value === "verified") return <ShieldCheck className="size-4 text-success" />;
    if (value === "failed") return <AlertCircle className="size-4 text-destructive" />;
    return <Clock className="size-4 text-muted-foreground" />;
  };

  const history: HistoryEntry[] = Array.isArray(record?.verification_history)
    ? (record.verification_history as HistoryEntry[])
    : [];

  return (
    <AppShell>
      <div className="max-w-5xl mx-auto space-y-6" dir={dir}>
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="text-3xl font-bold">{t("cdd_title")}</h1>
            <p className="text-muted-foreground text-sm mt-1">{t("cdd_subtitle")}</p>
            <div className="mt-2 text-sm">
              <span className="text-muted-foreground">Entity:</span>{" "}
              <span className="font-medium">{entity.entity_name}</span>
              {entity.engagement_number && (
                <Badge variant="outline" className="ms-2 font-mono text-xs">
                  {entity.engagement_number}
                </Badge>
              )}
            </div>
          </div>
          <Button asChild variant="outline" size="sm">
            <Link to={`/kyc/${entityId}/kyc`}>
              <ArrowLeft className="size-4" /> {t("cdd_back_to_kyc")}
            </Link>
          </Button>
        </div>

        {!isAdmin && (
          <div className="rounded-md border border-warning/50 bg-warning/10 px-4 py-3 text-sm flex items-center gap-2">
            <AlertCircle className="size-4 text-warning shrink-0" />
            <span>{t("cdd_admin_only")}</span>
          </div>
        )}

        {/* Current status summary */}
        <div className="grid sm:grid-cols-3 gap-3">
          {[
            { label: t("cdd_identity"), value: form.identity_verification },
            { label: t("cdd_eligibility"), value: form.eligibility_verification },
            { label: t("cdd_auditor"), value: form.auditor_verification },
          ].map((item) => (
            <Card key={item.label} className="shadow-card">
              <CardContent className="p-4 flex items-center justify-between">
                <div>
                  <div className="text-xs text-muted-foreground">{item.label}</div>
                  <div className="text-sm font-semibold mt-1">
                    {item.value === "verified"
                      ? t("cdd_verified")
                      : item.value === "failed"
                      ? t("cdd_failed")
                      : t("cdd_pending")}
                  </div>
                </div>
                <StatusIcon value={item.value || null} />
              </CardContent>
            </Card>
          ))}
        </div>

        {/* Verification form */}
        <Card className="shadow-card">
          <CardHeader>
            <CardTitle className="text-lg">{t("cdd_save")}</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-5">
              <div className="grid md:grid-cols-3 gap-4">
                <Field label={t("cdd_identity")}>
                  <NativeSelect
                    disabled={!isAdmin}
                    value={form.identity_verification}
                    onChange={(e) => setForm({ ...form, identity_verification: e.target.value })}
                  >
                    <option value="">{t("cdd_select")}</option>
                    <option value="verified">{t("cdd_verified")}</option>
                    <option value="failed">{t("cdd_failed")}</option>
                  </NativeSelect>
                </Field>
                <Field label={t("cdd_eligibility")}>
                  <NativeSelect
                    disabled={!isAdmin}
                    value={form.eligibility_verification}
                    onChange={(e) => setForm({ ...form, eligibility_verification: e.target.value })}
                  >
                    <option value="">{t("cdd_select")}</option>
                    <option value="verified">{t("cdd_verified")}</option>
                    <option value="failed">{t("cdd_failed")}</option>
                  </NativeSelect>
                </Field>
                <Field label={t("cdd_auditor")}>
                  <NativeSelect
                    disabled={!isAdmin}
                    value={form.auditor_verification}
                    onChange={(e) => setForm({ ...form, auditor_verification: e.target.value })}
                  >
                    <option value="">{t("cdd_select")}</option>
                    <option value="verified">{t("cdd_verified")}</option>
                    <option value="failed">{t("cdd_failed")}</option>
                  </NativeSelect>
                </Field>
                <Field label={t("cdd_economic_sector")}>
                  <Input
                    disabled={!isAdmin}
                    value={form.economic_sector}
                    onChange={(e) => setForm({ ...form, economic_sector: e.target.value })}
                  />
                </Field>
                <Field label={t("cdd_eligibility_status")}>
                  <NativeSelect
                    disabled={!isAdmin}
                    value={form.eligibility_status}
                    onChange={(e) => setForm({ ...form, eligibility_status: e.target.value })}
                  >
                    <option value="pending">{t("cdd_pending")}</option>
                    <option value="eligible">{t("cdd_eligible")}</option>
                    <option value="not_eligible">{t("cdd_not_eligible")}</option>
                  </NativeSelect>
                </Field>
              </div>
              <Field label={t("cdd_notes")}>
                <Textarea
                  disabled={!isAdmin}
                  rows={3}
                  value={form.notes}
                  onChange={(e) => setForm({ ...form, notes: e.target.value })}
                />
              </Field>
              {isAdmin && (
                <div className="flex justify-end pt-4 border-t border-border">
                  <Button type="submit" variant="premium" disabled={busy}>
                    {busy ? t("saving") : t("cdd_save")}
                  </Button>
                </div>
              )}
            </form>
          </CardContent>
        </Card>

        {/* History */}
        <Card className="shadow-card">
          <CardHeader>
            <CardTitle className="text-lg">{t("cdd_history")}</CardTitle>
          </CardHeader>
          <CardContent>
            {history.length === 0 ? (
              <div className="py-8 text-center text-sm text-muted-foreground">{t("cdd_no_history")}</div>
            ) : (
              <ol className="space-y-3">
                {[...history].reverse().map((h, i) => (
                  <li key={i} className="rounded-md border border-border p-3 bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                      <span>{new Date(h.at).toLocaleString()}</span>
                      <span className="font-mono">{h.by}</span>
                    </div>
                    <div className="mt-2 flex flex-wrap gap-2 text-xs">
                      {h.identity && (
                        <Badge variant="outline">
                          {t("cdd_identity")}: {h.identity === "verified" ? t("cdd_verified") : t("cdd_failed")}
                        </Badge>
                      )}
                      {h.eligibility && (
                        <Badge variant="outline">
                          {t("cdd_eligibility")}: {h.eligibility === "verified" ? t("cdd_verified") : t("cdd_failed")}
                        </Badge>
                      )}
                      {h.auditor && (
                        <Badge variant="outline">
                          {t("cdd_auditor")}: {h.auditor === "verified" ? t("cdd_verified") : t("cdd_failed")}
                        </Badge>
                      )}
                      {h.status && <Badge variant="secondary">{h.status}</Badge>}
                    </div>
                    {h.notes && <div className="mt-2 text-sm">{h.notes}</div>}
                  </li>
                ))}
              </ol>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
