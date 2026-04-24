import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { supabase } from "@/integrations/supabase/client";
import { AppShell } from "@/components/AppShell";
import { KycStepper, type KycStepKey } from "@/components/KycStepper";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "sonner";
import { Plus, Trash2 } from "lucide-react";

const validSteps: KycStepKey[] = ["kyc", "audit-fee", "financial-year", "tax-status", "engagement"];

function NativeSelect(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select
      {...props}
      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
    />
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <div className="space-y-1.5"><Label className="text-sm">{label}</Label>{children}</div>;
}

export default function KycStep() {
  const { entityId = "", step = "kyc" } = useParams();
  const { user, loading } = useAuth();
  const { t, dir } = useI18n();
  const navigate = useNavigate();
  const stepKey = (validSteps.includes(step as KycStepKey) ? step : "kyc") as KycStepKey;

  const [entity, setEntity] = useState<any | null>(null);
  const [loadingData, setLoadingData] = useState(true);

  useEffect(() => { if (!loading && !user) navigate("/auth"); }, [user, loading, navigate]);

  useEffect(() => {
    if (!user || !entityId) return;
    setLoadingData(true);
    supabase.from("entities").select("*").eq("id", entityId).single().then(({ data, error }) => {
      if (error) toast.error(error.message);
      setEntity(data);
      setLoadingData(false);
    });
  }, [user, entityId]);

  if (!user || loadingData) return <AppShell><div className="text-center py-20 text-muted-foreground">{t("loading")}</div></AppShell>;
  if (!entity) return <AppShell><div className="text-center py-20 text-muted-foreground">Not found</div></AppShell>;

  const goNext = () => {
    const next = validSteps[validSteps.indexOf(stepKey) + 1];
    if (next) navigate(`/kyc/${entityId}/${next}`);
    else navigate("/entities");
  };
  const goBack = () => {
    const prev = validSteps[validSteps.indexOf(stepKey) - 1];
    if (prev) navigate(`/kyc/${entityId}/${prev}`);
  };

  const completed = validSteps.slice(0, Math.max(0, (entity.current_step || 1) - 1));

  return (
    <AppShell>
      <div className="max-w-7xl mx-auto grid md:grid-cols-[280px_1fr] gap-6" dir={dir}>
        <KycStepper current={stepKey} entityId={entityId} completed={completed} />
        <div className="min-w-0">
          {stepKey === "kyc" && <KycForm entity={entity} onSaved={(e) => { setEntity(e); goNext(); }} t={t} />}
          {stepKey === "audit-fee" && <AuditFeeForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />}
          {stepKey === "financial-year" && <FinancialYearForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />}
          {stepKey === "tax-status" && <TaxStatusForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />}
          {stepKey === "engagement" && <EngagementForm entity={entity} onBack={goBack} t={t} />}
        </div>
      </div>
    </AppShell>
  );
}

// STEP 1
function KycForm({ entity, onSaved, t }: any) {
  const [form, setForm] = useState({
    entity_name: entity.entity_name === "Untitled Entity" ? "" : entity.entity_name,
    registration_status: entity.registration_status ?? "",
    license_number: entity.license_number ?? "",
    license_issue_date: entity.license_issue_date ?? "",
    license_expiry_date: entity.license_expiry_date ?? "",
    main_activity: entity.main_activity ?? "",
    emirate: entity.emirate ?? "",
    address: entity.address ?? "",
    total_turnover: entity.total_turnover ?? 0,
  });
  const [shareholders, setShareholders] = useState<any[]>(entity.shareholders ?? []);
  const [ubos, setUbos] = useState<any[]>(entity.ubos ?? []);
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (Number(form.total_turnover) > 50_000_000) {
      return toast.error(t("kyc_turnover_max"));
    }
    setBusy(true);
    const { data, error } = await supabase.from("entities").update({ ...form, shareholders, ubos, current_step: 2 }).eq("id", entity.id).select().single();
    setBusy(false);
    if (error) {
      const msg = /turnover/i.test(error.message) ? t("kyc_turnover_max") : error.message;
      return toast.error(msg);
    }
    toast.success(t("saved"));
    onSaved(data);
  };

  return (
    <Card className="shadow-card">
      <CardHeader><CardTitle>{t("kyc_step1")}</CardTitle></CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-6">
          <div className="grid md:grid-cols-2 gap-4">
            <Field label={t("kyc_owner_name") + " *"}><Input required value={form.entity_name} onChange={(e) => setForm({ ...form, entity_name: e.target.value })} /></Field>
            <Field label={t("kyc_business_registration") + " *"}>
              <NativeSelect required value={form.registration_status} onChange={(e) => setForm({ ...form, registration_status: e.target.value })}>
                <option value="">—</option>
                <option>Mainland Licensed-Single Owner</option>
                <option>Mainland Licensed-Multiple Owners</option>
                <option>Free Zone Licensed</option>
                <option>Offshore</option>
              </NativeSelect>
            </Field>
            <Field label={t("kyc_license_number")}><Input value={form.license_number} onChange={(e) => setForm({ ...form, license_number: e.target.value })} /></Field>
            <Field label={t("kyc_main_activity")}><Input value={form.main_activity} onChange={(e) => setForm({ ...form, main_activity: e.target.value })} /></Field>
            <Field label={t("kyc_issue_date")}><Input type="date" value={form.license_issue_date ?? ""} onChange={(e) => setForm({ ...form, license_issue_date: e.target.value })} /></Field>
            <Field label={t("kyc_expiry_date")}><Input type="date" value={form.license_expiry_date ?? ""} onChange={(e) => setForm({ ...form, license_expiry_date: e.target.value })} /></Field>
            <Field label={t("kyc_emirate")}>
              <NativeSelect value={form.emirate} onChange={(e) => setForm({ ...form, emirate: e.target.value })}>
                <option value="">—</option>
                {["Abu Dhabi","Dubai","Sharjah","Ajman","Umm Al Quwain","Ras Al Khaimah","Fujairah"].map(x => <option key={x}>{x}</option>)}
              </NativeSelect>
            </Field>
            <Field label={t("kyc_turnover") + " *"}><Input required type="number" min={0} max={50000000} step="0.01" value={form.total_turnover} onChange={(e) => setForm({ ...form, total_turnover: parseFloat(e.target.value || "0") })} /></Field>
          </div>
          <Field label={t("kyc_address") + " *"}><Textarea required value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} /></Field>
          <PeopleTable title={t("kyc_shareholders")} rows={shareholders} setRows={setShareholders} t={t} />
          <PeopleTable title={t("kyc_ubos")} rows={ubos} setRows={setUbos} t={t} />
          <div className="flex justify-end pt-4 border-t border-border">
            <Button type="submit" variant="premium" disabled={busy}>{busy ? t("saving") : t("btn_next")}</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}

function PeopleTable({ title, rows, setRows, t }: any) {
  const add = () => setRows([...rows, { name: "", capital_percentage: 0, nationality: "", emirates_id: "", pep_status: "No" }]);
  const remove = (i: number) => setRows(rows.filter((_: any, idx: number) => idx !== i));
  const update = (i: number, k: string, v: any) => setRows(rows.map((r: any, idx: number) => (idx === i ? { ...r, [k]: v } : r)));
  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <Label className="text-base font-semibold">{title}</Label>
        <Button type="button" size="sm" variant="outline" onClick={add}>
          <Plus className="size-3.5" /> {t("kyc_add_row")}
        </Button>
      </div>
      {rows.length === 0 ? (
        <div className="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
          {t("kyc_no_entries")}
        </div>
      ) : (
        <div className="space-y-3">
          {rows.map((r: any, i: number) => (
            <div key={i} className="rounded-lg border border-border bg-card p-4 shadow-sm">
              <div className="mb-3 flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">
                  {t("kyc_person_n")} {i + 1}
                </span>
                <Button type="button" size="sm" variant="ghost" onClick={() => remove(i)} className="text-destructive hover:text-destructive">
                  <Trash2 className="size-4" /> <span className="ms-1">{t("kyc_remove")}</span>
                </Button>
              </div>
              <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                <Field label={t("kyc_name")}>
                  <Input value={r.name} onChange={(e) => update(i, "name", e.target.value)} />
                </Field>
                <Field label={t("kyc_capital")}>
                  <Input type="number" min={0} max={100} step="0.01" value={r.capital_percentage} onChange={(e) => update(i, "capital_percentage", parseFloat(e.target.value || "0"))} />
                </Field>
                <Field label={t("kyc_nationality")}>
                  <Input value={r.nationality} onChange={(e) => update(i, "nationality", e.target.value)} />
                </Field>
                <Field label={t("kyc_emirates_id")}>
                  <Input value={r.emirates_id} onChange={(e) => update(i, "emirates_id", e.target.value)} />
                </Field>
                <Field label={t("kyc_pep")}>
                  <NativeSelect value={r.pep_status} onChange={(e) => update(i, "pep_status", e.target.value)}>
                    <option value="No">{t("no")}</option>
                    <option value="Yes">{t("yes")}</option>
                  </NativeSelect>
                </Field>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// STEP 2
function calculateAuditFee(turnover: number) {
  if (turnover <= 0) return 0;
  if (turnover <= 1_000_000) return 1000;
  if (turnover <= 10_000_000) return 2000;
  if (turnover <= 20_000_000) return 3000;
  const blocks = Math.ceil((turnover - 20_000_000) / 5_000_000);
  return 3000 + blocks * 500;
}

function AuditFeeForm({ entity, onSaved, onBack, t }: any) {
  const fee = calculateAuditFee(Number(entity.total_turnover) || 0);
  const [agreed, setAgreed] = useState(false);
  const [busy, setBusy] = useState(false);
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!agreed) return toast.error(t("required"));
    setBusy(true);
    const { error } = await supabase.from("audit_fees").upsert(
      { entity_id: entity.id, user_id: entity.user_id, turnover: entity.total_turnover, calculated_fee: fee, agreed: true },
      { onConflict: "entity_id" }
    );
    await supabase.from("entities").update({ current_step: 3 }).eq("id", entity.id);
    setBusy(false);
    if (error) return toast.error(error.message);
    toast.success(t("saved"));
    onSaved();
  };
  return (
    <Card className="shadow-card">
      <CardHeader><CardTitle>{t("audit_fee_title")}</CardTitle></CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-6">
          <p className="text-sm text-muted-foreground">{t("audit_fee_desc")}</p>
          <div className="rounded-lg border border-border p-6 bg-gradient-to-br from-accent/30 to-transparent">
            <div className="text-sm text-muted-foreground">{t("audit_fee_calculated")}</div>
            <div className="mt-1 text-4xl font-bold text-primary">
              {fee.toLocaleString()} <span className="text-base text-muted-foreground font-normal">{t("audit_fee_aed")}</span>
            </div>
          </div>
          <label className="flex items-start gap-3 text-sm cursor-pointer">
            <input type="checkbox" checked={agreed} onChange={(e) => setAgreed(e.target.checked)} className="mt-1" />
            <span>{t("audit_fee_agree")}</span>
          </label>
          <div className="flex justify-between pt-4 border-t border-border">
            <Button type="button" variant="outline" onClick={onBack}>{t("btn_back")}</Button>
            <Button type="submit" variant="premium" disabled={busy || !agreed}>{busy ? t("saving") : t("btn_next")}</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}

// STEP 3
function FinancialYearForm({ entity, onSaved, onBack, t }: any) {
  const [form, setForm] = useState<any>({ is_first_year: true, first_start_date: "", first_end_date: "", current_start_date: "", current_end_date: "", previous_audited: "" });
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    supabase.from("financial_years").select("*").eq("entity_id", entity.id).maybeSingle().then(({ data }) => data && setForm((f: any) => ({ ...f, ...data })));
  }, [entity.id]);
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    const { error } = await supabase.from("financial_years").upsert({
      entity_id: entity.id, user_id: entity.user_id,
      is_first_year: form.is_first_year,
      first_start_date: form.first_start_date || null, first_end_date: form.first_end_date || null,
      current_start_date: form.current_start_date || null, current_end_date: form.current_end_date || null,
      previous_audited: form.previous_audited || null,
    }, { onConflict: "entity_id" });
    await supabase.from("entities").update({ current_step: 4 }).eq("id", entity.id);
    setBusy(false);
    if (error) return toast.error(error.message);
    toast.success(t("saved")); onSaved();
  };
  return (
    <Card className="shadow-card">
      <CardHeader><CardTitle>{t("fy_title")}</CardTitle></CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-5">
          <Field label={t("fy_first_question")}>
            <NativeSelect value={form.is_first_year ? "yes" : "no"} onChange={(e) => setForm({ ...form, is_first_year: e.target.value === "yes" })}>
              <option value="yes">{t("yes")}</option><option value="no">{t("no")}</option>
            </NativeSelect>
          </Field>
          {form.is_first_year ? (
            <div className="grid md:grid-cols-2 gap-4">
              <Field label={t("fy_first_start")}><Input type="date" value={form.first_start_date || ""} onChange={(e) => setForm({ ...form, first_start_date: e.target.value })} /></Field>
              <Field label={t("fy_first_end")}><Input type="date" value={form.first_end_date || ""} onChange={(e) => setForm({ ...form, first_end_date: e.target.value })} /></Field>
            </div>
          ) : (
            <>
              <div className="grid md:grid-cols-2 gap-4">
                <Field label={t("fy_current_start")}><Input type="date" value={form.current_start_date || ""} onChange={(e) => setForm({ ...form, current_start_date: e.target.value })} /></Field>
                <Field label={t("fy_current_end")}><Input type="date" value={form.current_end_date || ""} onChange={(e) => setForm({ ...form, current_end_date: e.target.value })} /></Field>
              </div>
              <Field label={t("fy_previous_audited")}>
                <NativeSelect value={form.previous_audited || ""} onChange={(e) => setForm({ ...form, previous_audited: e.target.value })}>
                  <option value="">—</option><option value="yes">{t("yes")}</option><option value="no">{t("no")}</option>
                </NativeSelect>
              </Field>
            </>
          )}
          <div className="flex justify-between pt-4 border-t border-border">
            <Button type="button" variant="outline" onClick={onBack}>{t("btn_back")}</Button>
            <Button type="submit" variant="premium" disabled={busy}>{busy ? t("saving") : t("btn_next")}</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}

// STEP 4
function TaxStatusForm({ entity, onSaved, onBack, t }: any) {
  const [form, setForm] = useState<any>({ vat_status: "", vat_registration_number: "", excise_tax_status: "", corporate_tax_status: "", corporate_tax_registration_number: "", corporate_tax_treatment: "", small_business_relief: "" });
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    supabase.from("tax_status").select("*").eq("entity_id", entity.id).maybeSingle().then(({ data }) => data && setForm((f: any) => ({ ...f, ...data })));
  }, [entity.id]);
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    const { error } = await supabase.from("tax_status").upsert({ entity_id: entity.id, user_id: entity.user_id, ...form }, { onConflict: "entity_id" });
    await supabase.from("entities").update({ current_step: 5 }).eq("id", entity.id);
    setBusy(false);
    if (error) return toast.error(error.message);
    toast.success(t("saved")); onSaved();
  };
  return (
    <Card className="shadow-card">
      <CardHeader><CardTitle>{t("tax_title")}</CardTitle></CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-5">
          <div className="grid md:grid-cols-2 gap-4">
            <Field label={t("tax_vat_status")}>
              <NativeSelect value={form.vat_status} onChange={(e) => setForm({ ...form, vat_status: e.target.value })}>
                <option value="">—</option><option value="registered">{t("registered")}</option><option value="not_registered">{t("not_registered")}</option>
              </NativeSelect>
            </Field>
            {form.vat_status === "registered" && (
              <Field label={t("tax_vat_number")}><Input value={form.vat_registration_number} onChange={(e) => setForm({ ...form, vat_registration_number: e.target.value })} /></Field>
            )}
            <Field label={t("tax_excise_status")}>
              <NativeSelect value={form.excise_tax_status} onChange={(e) => setForm({ ...form, excise_tax_status: e.target.value })}>
                <option value="">—</option><option value="registered">{t("registered")}</option><option value="not_registered">{t("not_registered")}</option>
              </NativeSelect>
            </Field>
            <Field label={t("tax_corporate_status")}>
              <NativeSelect value={form.corporate_tax_status} onChange={(e) => setForm({ ...form, corporate_tax_status: e.target.value })}>
                <option value="">—</option><option value="registered">{t("registered")}</option><option value="not_registered">{t("not_registered")}</option>
              </NativeSelect>
            </Field>
            {form.corporate_tax_status === "registered" && (
              <>
                <Field label={t("tax_corporate_number")}><Input value={form.corporate_tax_registration_number} onChange={(e) => setForm({ ...form, corporate_tax_registration_number: e.target.value })} /></Field>
                <Field label={t("tax_corporate_treatment")}><Input value={form.corporate_tax_treatment} onChange={(e) => setForm({ ...form, corporate_tax_treatment: e.target.value })} /></Field>
                <Field label={t("tax_sbr")}>
                  <NativeSelect value={form.small_business_relief} onChange={(e) => setForm({ ...form, small_business_relief: e.target.value })}>
                    <option value="">—</option><option value="yes">{t("yes")}</option><option value="no">{t("no")}</option>
                  </NativeSelect>
                </Field>
              </>
            )}
          </div>
          <div className="flex justify-between pt-4 border-t border-border">
            <Button type="button" variant="outline" onClick={onBack}>{t("btn_back")}</Button>
            <Button type="submit" variant="premium" disabled={busy}>{busy ? t("saving") : t("btn_next")}</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}

// STEP 5
function EngagementForm({ entity, onBack, t }: any) {
  const [accepted, setAccepted] = useState(false);
  const [busy, setBusy] = useState(false);
  const navigate = useNavigate();
  const engagementNumber = `ENG-${entity.id.slice(0, 8).toUpperCase()}-${new Date().getFullYear()}`;
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!accepted) return toast.error(t("required"));
    setBusy(true);
    await supabase.from("engagement_letters").upsert({
      entity_id: entity.id, user_id: entity.user_id, engagement_number: engagementNumber,
      accepted: true, accepted_at: new Date().toISOString(),
    }, { onConflict: "entity_id" });
    await supabase.from("entities").update({
      engagement_number: engagementNumber, application_status: "submitted",
      submitted_at: new Date().toISOString(), current_step: 5,
    }).eq("id", entity.id);
    setBusy(false);
    toast.success(t("saved"));
    navigate("/entities");
  };
  return (
    <Card className="shadow-card">
      <CardHeader><CardTitle>{t("engagement_title")}</CardTitle></CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-5">
          <p className="text-sm text-muted-foreground">{t("engagement_intro")}</p>
          <div className="rounded-lg border border-border bg-muted/30 p-6 space-y-3">
            <div className="font-mono text-sm">Engagement #: <span className="font-bold text-primary">{engagementNumber}</span></div>
            <div className="text-sm">Entity: <strong>{entity.entity_name}</strong></div>
            <div className="text-sm text-muted-foreground leading-relaxed">
              This engagement letter confirms our understanding of the terms of our engagement and the nature and limitations of the audit services we will provide. Our audit will be conducted in accordance with International Standards on Auditing (ISAs).
            </div>
          </div>
          <label className="flex items-start gap-3 text-sm cursor-pointer">
            <input type="checkbox" checked={accepted} onChange={(e) => setAccepted(e.target.checked)} className="mt-1" />
            <span>{t("engagement_accept")}</span>
          </label>
          <div className="flex justify-between pt-4 border-t border-border">
            <Button type="button" variant="outline" onClick={onBack}>{t("btn_back")}</Button>
            <Button type="submit" variant="success" disabled={busy || !accepted}>{busy ? t("saving") : t("engagement_complete")}</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
