import { useEffect, useState, useRef } from "react";
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
import { Plus, Trash2, Upload, FileText, X } from "lucide-react";

//const validSteps: KycStepKey[] = ["kyc", "audit-fee", "financial-year", "tax-status", "engagement"];
const validSteps: KycStepKey[] = [
  "kyc",
  "uae-id",
  "audit-fee",
  "financial-year",
  "tax-status",
  "engagement",
  "financial-analysis",
  "payment",
];
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
// STEP 1
function KycForm({ entity, onSaved, t }: any) {
  const { user } = useAuth();
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
    mainland_company_type: entity.mainland_company_type ?? "",
  });
  const [shareholders, setShareholders] = useState<any[]>(entity.shareholders ?? []);
  const [ubos, setUbos] = useState<any[]>(entity.ubos ?? []);
  const [hasUbo, setHasUbo] = useState<string>(
    (entity.ubos ?? []).length > 0 ? "Yes" : ""
  );
  const [managementControl, setManagementControl] = useState<string>(
    entity.management_control ?? ""
  );
  const [eidFiles, setEidFiles] = useState<File[]>([]);
  const [tradeFiles, setTradeFiles] = useState<File[]>([]);
  const [authFiles, setAuthFiles] = useState<File[]>([]);
  const [busy, setBusy] = useState(false);

  // خيارات Management Control تُبنى ديناميكياً من المساهمين + UBOs
  const mgmtOptions = [
    ...shareholders.map((s) => s.name).filter(Boolean),
    ...( hasUbo === "Yes" ? ubos.map((u) => u.name).filter(Boolean) : []),
    "Other",
  ];

  const uploadFilesToStorage = async (files: File[], folder: string) => {
    const paths: string[] = [];
    for (const file of files) {
      const ext = file.name.split(".").pop() || "bin";
      const path = `${user!.id}/${entity.id}/${folder}/${Date.now()}_${file.name}`;
      const { error } = await supabase.storage
        .from("kyc-documents")
        .upload(path, file, { upsert: true });
      if (!error) paths.push(path);
    }
    return paths;
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (Number(form.total_turnover) > 50_000_000) {
      return toast.error(t("kyc_turnover_max"));
    }
    setBusy(true);

    // رفع الملفات
    const eidPaths = eidFiles.length > 0
      ? await uploadFilesToStorage(eidFiles, "eid")
      : [];
    const tradePaths = tradeFiles.length > 0
      ? await uploadFilesToStorage(tradeFiles, "trade")
      : [];
    const authPaths = authFiles.length > 0
      ? await uploadFilesToStorage(authFiles, "auth")
      : [];

    // حفظ بيانات الكيان
    const { data, error } = await supabase
      .from("entities")
      .update({
        ...form,
        shareholders,
        ubos: hasUbo === "Yes" ? ubos : [],
        management_control: managementControl,
        current_step: 2,
      })
      .eq("id", entity.id)
      .select()
      .single();

    // حفظ الملفات في kyc_documents
    if (user && (eidPaths.length || tradePaths.length || authPaths.length)) {
      const docRecords: any[] = [];
      eidFiles.forEach((f, i) => {
        if (eidPaths[i]) docRecords.push({
          entity_id: entity.id,
          user_id: user.id,
          document_type: "eid_passport",
          file_name: f.name,
          storage_path: eidPaths[i],
          mime_type: f.type,
          size_bytes: f.size,
        });
      });
      tradeFiles.forEach((f, i) => {
        if (tradePaths[i]) docRecords.push({
          entity_id: entity.id,
          user_id: user.id,
          document_type: "trade_license",
          file_name: f.name,
          storage_path: tradePaths[i],
          mime_type: f.type,
          size_bytes: f.size,
        });
      });
      authFiles.forEach((f, i) => {
        if (authPaths[i]) docRecords.push({
          entity_id: entity.id,
          user_id: user.id,
          document_type: "authorization_letter",
          file_name: f.name,
          storage_path: authPaths[i],
          mime_type: f.type,
          size_bytes: f.size,
        });
      });
      if (docRecords.length > 0) {
        await supabase.from("kyc_documents").insert(docRecords);
      }
    }

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
            <Field label={t("kyc_owner_name") + " *"}>
              <Input required value={form.entity_name} onChange={(e) => setForm({ ...form, entity_name: e.target.value })} />
            </Field>
            <Field label={t("kyc_business_registration") + " *"}>
              <NativeSelect
                required
                value={form.registration_status}
                onChange={(e) => {
                  setForm({ ...form, registration_status: e.target.value, mainland_company_type: "" });
                }}
              >
                <option value="">—</option>
                <option value="Unlicensed Natural Person(s)">Unlicensed Natural Person(s)</option>
                <option value="Free Zone Licensed">Free Zone Licensed</option>
                <option value="Mainland Licensed-Multiple Owners">Mainland Licensed-Multiple Owners</option>
                <option value="Mainland Licensed-Sole Owner">Mainland Licensed-Sole Owner</option>
              </NativeSelect>
            </Field>

            {/* حقل نوع الشركة — يظهر فقط للشركات الـ Mainland */}
            {form.registration_status === "Mainland Licensed-Multiple Owners" && (
              <Field label="Mainland Company Type *">
                <NativeSelect
                  required
                  value={form.mainland_company_type}
                  onChange={(e) => setForm({ ...form, mainland_company_type: e.target.value })}
                >
                  <option value="">—</option>
                  <option value="Civil Company">Civil Company</option>
                  <option value="Limited Liability Company">Limited Liability Company</option>
                  <option value="General Partnership Company">General Partnership Company</option>
                  <option value="Limited Partnership Company">Limited Partnership Company</option>
                  <option value="Branch of Local Company">Branch of Local Company</option>
                  <option value="Branch of Foreign Company">Branch of Foreign Company</option>
                </NativeSelect>
              </Field>
            )}

            <Field label={t("kyc_license_number")}>
              <Input value={form.license_number} onChange={(e) => setForm({ ...form, license_number: e.target.value })} />
            </Field>
            <Field label={t("kyc_main_activity")}>
              <Input value={form.main_activity} onChange={(e) => setForm({ ...form, main_activity: e.target.value })} />
            </Field>
            <Field label={t("kyc_issue_date")}>
              <Input type="date" value={form.license_issue_date ?? ""} onChange={(e) => setForm({ ...form, license_issue_date: e.target.value })} />
            </Field>
            <Field label={t("kyc_expiry_date")}>
              <Input type="date" value={form.license_expiry_date ?? ""} onChange={(e) => setForm({ ...form, license_expiry_date: e.target.value })} />
            </Field>
            <Field label={t("kyc_emirate")}>
              <NativeSelect value={form.emirate} onChange={(e) => setForm({ ...form, emirate: e.target.value })}>
                <option value="">—</option>
                {["Abu Dhabi","Dubai","Sharjah","Ajman","Umm Al Quwain","Ras Al Khaimah","Fujairah"].map(x => <option key={x}>{x}</option>)}
              </NativeSelect>
            </Field>
            <Field label={t("kyc_turnover") + " *"}>
              <Input required type="number" min={0} max={50000000} step="0.01" value={form.total_turnover}
                onChange={(e) => setForm({ ...form, total_turnover: parseFloat(e.target.value || "0") })} />
            </Field>
          </div>

          <Field label={t("kyc_address") + " *"}>
            <Textarea required value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
          </Field>

          {/* المساهمون */}
          <PeopleTable title={t("kyc_shareholders")} rows={shareholders} setRows={setShareholders} t={t} />

          {/* سؤال UBO */}
          <div className="space-y-3">
            <Field label="هل يوجد مستفيد فعلي (UBO) يملك 25% أو أكثر بشكل غير مباشر؟ *">
              <NativeSelect
                required
                value={hasUbo}
                onChange={(e) => {
                  setHasUbo(e.target.value);
                  if (e.target.value === "No") setUbos([]);
                }}
              >
                <option value="">—</option>
                <option value="Yes">نعم</option>
                <option value="No">لا</option>
              </NativeSelect>
            </Field>
            {hasUbo === "Yes" && (
              <PeopleTable title="المستفيدون الفعليون (UBOs)" rows={ubos} setRows={setUbos} t={t} />
            )}
          </div>

          {/* المسؤول عن الإدارة */}
          <Field label="من المسؤول عن الإدارة والسيطرة الفعلية؟ *">
            <NativeSelect
              required
              value={managementControl}
              onChange={(e) => setManagementControl(e.target.value)}
            >
              <option value="">—</option>
              {mgmtOptions.map((name) => (
                <option key={name} value={name}>{name}</option>
              ))}
            </NativeSelect>
          </Field>

          {/* رفع الملفات */}
          <div className="space-y-4">
            <h3 className="font-semibold text-base border-t border-border pt-4">المستندات المطلوبة</h3>
            <div className="grid md:grid-cols-3 gap-3">
              <FileUploadZone
                label="هوية / جواز السفر"
                files={eidFiles}
                onChange={setEidFiles}
                accept=".pdf,.jpg,.jpeg,.png"
              />
              <FileUploadZone
                label="الرخصة التجارية"
                files={tradeFiles}
                onChange={setTradeFiles}
                accept=".pdf,.jpg,.jpeg,.png"
              />
              <FileUploadZone
                label="خطاب التفويض"
                files={authFiles}
                onChange={setAuthFiles}
                accept=".pdf,.jpg,.jpeg,.png"
              />
            </div>
          </div>

          <div className="flex justify-end pt-4 border-t border-border">
            <Button type="submit" variant="premium" disabled={busy}>
              {busy ? t("saving") : t("btn_next")}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}

// مكوّن رفع الملفات
function FileUploadZone({ label, files, onChange, accept }: {
  label: string;
  files: File[];
  onChange: (files: File[]) => void;
  accept: string;
}) {
  const ref = useRef<HTMLInputElement>(null);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newFiles = Array.from(e.target.files || []);
    onChange([...files, ...newFiles]);
    e.target.value = "";
  };

  const remove = (i: number) => onChange(files.filter((_, idx) => idx !== i));

  return (
    <div className="space-y-2">
      <label
        className="group rounded-lg border-2 border-dashed border-border hover:border-primary hover:bg-accent/20 transition-colors p-4 cursor-pointer flex flex-col items-center justify-center text-center min-h-24"
        onClick={() => ref.current?.click()}
      >
        <Upload className="size-5 text-muted-foreground group-hover:text-primary mb-1.5" />
        <span className="text-xs font-medium text-muted-foreground group-hover:text-primary">{label}</span>
        <span className="text-[10px] text-muted-foreground mt-0.5">PDF, JPG, PNG (max 5MB)</span>
        <input ref={ref} type="file" multiple accept={accept} className="hidden" onChange={handleChange} />
      </label>
      {files.length > 0 && (
        <ul className="space-y-1">
          {files.map((f, i) => (
            <li key={i} className="flex items-center gap-2 text-xs bg-muted/50 rounded px-2 py-1">
              <FileText className="size-3 shrink-0 text-muted-foreground" />
              <span className="truncate flex-1">{f.name}</span>
              <button type="button" onClick={() => remove(i)} className="text-destructive shrink-0">
                <X className="size-3" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
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
  const [signerName, setSignerName] = useState("");
  const [busy, setBusy] = useState(false);
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!agreed) return toast.error(t("required"));
    setBusy(true);
    const { error } = await supabase.from("audit_fees").upsert(
      {
        entity_id: entity.id,
        user_id: entity.user_id,
        turnover: entity.total_turnover,
        calculated_fee: fee,
        agreed: true,
        digital_signature_name: signerName,
        digital_signature_date: new Date().toISOString(),
      },
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
          <Field label="اسم الموقِّع الرقمي *">
            <Input
              required
              placeholder="الاسم الكامل"
              value={signerName}
              onChange={(e) => setSignerName(e.target.value)}
            />
          </Field>
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
