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
          {stepKey === "uae-id" && <UaeIdForm entity={entity} onSaved={(e) => { setEntity(e); goNext(); }} onBack={goBack} t={t} />}
          {stepKey === "audit-fee" && <AuditFeeForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />}
          {stepKey === "financial-year" && <FinancialYearForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />}
          {stepKey === "tax-status" && <TaxStatusForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />}
          {stepKey === "engagement" && <EngagementForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />}
          {stepKey === "financial-analysis" && <FinancialAnalysisForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />}
          {stepKey === "payment" && <PaymentForm entity={entity} onBack={goBack} t={t} />}
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

// ── UAE ID VERIFICATION FORM ────────────────────────────────────────────────
function UaeIdForm({ entity, onSaved, onBack, t }: any) {
  const { user } = useAuth();
  const [form, setForm] = useState({
    id_number: "",
    full_name_ar: "",
    full_name_en: "",
    nationality: "",
    dob: "",
    expiry_date: "",
    gender: "",
  });
  const [frontFile, setFrontFile] = useState<File[]>([]);
  const [backFile, setBackFile] = useState<File[]>([]);
  const [busy, setBusy] = useState(false);
  const [extracting, setExtracting] = useState(false);
  const [existing, setExisting] = useState<any>(null);

  useEffect(() => {
    supabase
      .from("uae_id_verifications")
      .select("*")
      .eq("entity_id", entity.id)
      .maybeSingle()
      .then(({ data }) => {
        if (data) {
          setExisting(data);
          setForm({
            id_number: data.id_number ?? "",
            full_name_ar: data.full_name_ar ?? "",
            full_name_en: data.full_name_en ?? "",
            nationality: data.nationality ?? "",
            dob: data.dob ?? "",
            expiry_date: data.expiry_date ?? "",
            gender: data.gender ?? "",
          });
        }
      });
  }, [entity.id]);

  // استخراج البيانات من صورة الهوية باستخدام Claude Vision
  const extractFromImage = async (file: File) => {
    setExtracting(true);
    try {
      const reader = new FileReader();
      const b64 = await new Promise<string>((res) => {
        reader.onload = () => res((reader.result as string).split(",")[1]);
        reader.readAsDataURL(file);
      });
      const resp = await fetch("https://api.anthropic.com/v1/messages", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-api-key": import.meta.env.VITE_ANTHROPIC_API_KEY ?? "",
          "anthropic-version": "2023-06-01",
        },
        body: JSON.stringify({
          model: "claude-sonnet-4-20250514",
          max_tokens: 500,
          messages: [{
            role: "user",
            content: [
              {
                type: "image",
                source: { type: "base64", media_type: file.type as any, data: b64 },
              },
              {
                type: "text",
                text: `استخرج المعلومات من هذه الهوية الإماراتية وأجب بـ JSON فقط بدون أي نص آخر:
{
  "id_number": "رقم الهوية (784-XXXX-XXXXXXX-X)",
  "full_name_ar": "الاسم بالعربية",
  "full_name_en": "Name in English",
  "nationality": "الجنسية بالإنجليزية",
  "dob": "YYYY-MM-DD",
  "expiry_date": "YYYY-MM-DD",
  "gender": "Male أو Female"
}`,
              },
            ],
          }],
        }),
      });
      if (resp.ok) {
        const data = await resp.json();
        const text = data.content?.[0]?.text ?? "";
        const clean = text.replace(/```json|```/g, "").trim();
        const parsed = JSON.parse(clean);
        setForm((f) => ({ ...f, ...parsed }));
        toast.success("تم استخراج البيانات من الهوية بنجاح");
      }
    } catch {
      toast.error("لم يتمكن النظام من استخراج البيانات — يرجى إدخالها يدوياً");
    }
    setExtracting(false);
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.id_number.trim()) return toast.error("رقم الهوية مطلوب");
    setBusy(true);

    // رفع صورة الوجه
    let frontPath: string | null = existing?.front_path ?? null;
    let backPath: string | null = existing?.back_path ?? null;
    if (frontFile[0]) {
      const p = `${user!.id}/${entity.id}/uae-front-${Date.now()}`;
      const { data } = await supabase.storage.from("uae-id-docs").upload(p, frontFile[0], { upsert: true });
      if (data) frontPath = data.path;
    }
    if (backFile[0]) {
      const p = `${user!.id}/${entity.id}/uae-back-${Date.now()}`;
      const { data } = await supabase.storage.from("uae-id-docs").upload(p, backFile[0], { upsert: true });
      if (data) backPath = data.path;
    }

    const payload = {
      entity_id: entity.id,
      user_id: user!.id,
      ...form,
      front_path: frontPath,
      back_path: backPath,
      status: "pending",
    };

    const { error } = existing
      ? await supabase.from("uae_id_verifications").update(payload).eq("id", existing.id)
      : await supabase.from("uae_id_verifications").insert(payload);

    await supabase.from("entities").update({ current_step: 3, uae_id_verified: false }).eq("id", entity.id);
    setBusy(false);
    if (error) return toast.error(error.message);
    toast.success("تم حفظ بيانات الهوية");
    onSaved(entity);
  };

  return (
    <Card className="shadow-card">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          التحقق من الهوية الإماراتية
        </CardTitle>
        {existing?.status === "verified" && (
          <div className="text-sm text-green-600 font-medium">✅ الهوية موثّقة</div>
        )}
      </CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-6">
          {/* رفع صور الهوية */}
          <div className="space-y-3">
            <div className="text-sm font-medium">صور الهوية الإماراتية</div>
            <div className="grid md:grid-cols-2 gap-3">
              <div>
                <FileUploadZone
                  label="الوجه الأمامي"
                  files={frontFile}
                  onChange={(files) => {
                    setFrontFile(files);
                    if (files[0]) extractFromImage(files[0]);
                  }}
                  accept=".jpg,.jpeg,.png,.pdf"
                />
                {extracting && (
                  <div className="text-xs text-primary mt-1 animate-pulse">
                    جاري استخراج البيانات بالذكاء الاصطناعي...
                  </div>
                )}
              </div>
              <FileUploadZone
                label="الوجه الخلفي"
                files={backFile}
                onChange={setBackFile}
                accept=".jpg,.jpeg,.png,.pdf"
              />
            </div>
            <div className="text-xs text-muted-foreground">
              عند رفع الوجه الأمامي، سيقوم الذكاء الاصطناعي باستخراج البيانات تلقائياً.
            </div>
          </div>

          {/* بيانات الهوية */}
          <div className="grid md:grid-cols-2 gap-4">
            <Field label="رقم الهوية الإماراتية *">
              <Input
                required
                placeholder="784-XXXX-XXXXXXX-X"
                value={form.id_number}
                onChange={(e) => setForm({ ...form, id_number: e.target.value })}
                dir="ltr"
              />
            </Field>
            <Field label="الجنس">
              <NativeSelect value={form.gender} onChange={(e) => setForm({ ...form, gender: e.target.value })}>
                <option value="">—</option>
                <option value="Male">ذكر</option>
                <option value="Female">أنثى</option>
              </NativeSelect>
            </Field>
            <Field label="الاسم الكامل (عربي)">
              <Input
                value={form.full_name_ar}
                onChange={(e) => setForm({ ...form, full_name_ar: e.target.value })}
                dir="rtl"
              />
            </Field>
            <Field label="Full Name (English)">
              <Input
                value={form.full_name_en}
                onChange={(e) => setForm({ ...form, full_name_en: e.target.value })}
                dir="ltr"
              />
            </Field>
            <Field label="الجنسية">
              <Input
                value={form.nationality}
                onChange={(e) => setForm({ ...form, nationality: e.target.value })}
              />
            </Field>
            <Field label="تاريخ الميلاد">
              <Input
                type="date"
                value={form.dob}
                onChange={(e) => setForm({ ...form, dob: e.target.value })}
              />
            </Field>
            <Field label="تاريخ انتهاء الهوية">
              <Input
                type="date"
                value={form.expiry_date}
                onChange={(e) => setForm({ ...form, expiry_date: e.target.value })}
              />
            </Field>
          </div>

          {/* تحذير إذا انتهت الهوية */}
          {form.expiry_date && new Date(form.expiry_date) < new Date() && (
            <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
              ⚠️ تاريخ انتهاء الهوية قد مضى — تأكد من صحة التاريخ
            </div>
          )}

          <div className="flex justify-between pt-4 border-t border-border">
            <Button type="button" variant="outline" onClick={onBack}>{t("btn_back")}</Button>
            <Button type="submit" variant="premium" disabled={busy || extracting}>
              {busy ? t("saving") : t("btn_next")}
            </Button>
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

// ── FINANCIAL ANALYSIS FORM ─────────────────────────────────────────────────
function FinancialAnalysisForm({ entity, onSaved, onBack, t }: any) {
  const { user } = useAuth();
  const [files, setFiles] = useState<File[]>([]);
  const [manualData, setManualData] = useState({
    total_assets: "",
    total_liabilities: "",
    equity: "",
    revenue: "",
    net_profit: "",
    operating_expenses: "",
    cash: "",
    accounts_receivable: "",
  });
  const [inputMode, setInputMode] = useState<"file" | "manual">("file");
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<any>(null);

  const labels: Record<string, string> = {
    total_assets: "إجمالي الأصول (AED)",
    total_liabilities: "إجمالي الخصوم (AED)",
    equity: "حقوق الملكية (AED)",
    revenue: "الإيرادات (AED)",
    net_profit: "صافي الربح (AED)",
    operating_expenses: "مصاريف التشغيل (AED)",
    cash: "النقد وما يعادله (AED)",
    accounts_receivable: "الذمم المدينة (AED)",
  };

  const readFileAsText = (file: File): Promise<string> =>
    new Promise((res) => {
      const r = new FileReader();
      r.onload = () => res(r.result as string);
      r.readAsText(file, "UTF-8");
    });

  const buildPrompt = (dataText: string) => `
أنت محلل مالي متخصص. بناءً على البيانات التالية أصدر تحليلاً مالياً شاملاً بالعربية:

${dataText}

أجب بـ JSON فقط بهذا الشكل:
{
  "balance_sheet": {
    "total_assets": رقم,
    "total_liabilities": رقم,
    "equity": رقم
  },
  "income_stmt": {
    "revenue": رقم,
    "net_profit": رقم,
    "operating_expenses": رقم,
    "profit_margin": رقم بالنسبة المئوية
  },
  "ratios": {
    "current_ratio": رقم,
    "debt_to_equity": رقم,
    "return_on_equity": رقم بالنسبة المئوية,
    "return_on_assets": رقم بالنسبة المئوية
  },
  "health_score": رقم من 0 إلى 100,
  "risks": ["خطر 1", "خطر 2"],
  "summary": "ملخص تنفيذي 3 جمل باللغة العربية"
}`.trim();

  const runAnalysis = async () => {
    setBusy(true);
    setResult(null);
    let dataText = "";

    if (inputMode === "file" && files.length > 0) {
      // قراءة الملفات ورفعها
      const uploadedPaths: string[] = [];
      for (const file of files) {
        const p = `${user!.id}/${entity.id}/fin-${Date.now()}-${file.name}`;
        await supabase.storage.from("financial-files").upload(p, file, { upsert: true });
        uploadedPaths.push(p);
        // قراءة CSV/نص
        if (file.type.includes("csv") || file.type.includes("text")) {
          const text = await readFileAsText(file);
          dataText += `\n=== ${file.name} ===\n${text.slice(0, 3000)}`;
        }
      }
      if (!dataText) {
        dataText = `ملفات تم رفعها: ${files.map((f) => f.name).join(", ")}. لا يمكن قراءة محتوى الملفات الثنائية (Excel/PDF) مباشرة — يرجى استخدام وضع الإدخال اليدوي.`;
      }
    } else {
      const vals = manualData;
      dataText = `
الميزانية العمومية:
- إجمالي الأصول: ${vals.total_assets} AED
- إجمالي الخصوم: ${vals.total_liabilities} AED
- حقوق الملكية: ${vals.equity} AED
- النقد وما يعادله: ${vals.cash} AED
- الذمم المدينة: ${vals.accounts_receivable} AED

قائمة الدخل:
- الإيرادات: ${vals.revenue} AED
- صافي الربح: ${vals.net_profit} AED
- مصاريف التشغيل: ${vals.operating_expenses} AED

اسم الكيان: ${entity.entity_name}
القطاع: ${entity.main_activity ?? "غير محدد"}
دوران الأعمال المُصرَّح: ${entity.total_turnover?.toLocaleString() ?? "غير محدد"} AED
`.trim();
    }

    let parsed: any = null;

    // محاولة Claude API
    try {
      const resp = await fetch("https://api.anthropic.com/v1/messages", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-api-key": import.meta.env.VITE_ANTHROPIC_API_KEY ?? "",
          "anthropic-version": "2023-06-01",
        },
        body: JSON.stringify({
          model: "claude-sonnet-4-20250514",
          max_tokens: 1200,
          messages: [{ role: "user", content: buildPrompt(dataText) }],
        }),
      });
      if (resp.ok) {
        const data = await resp.json();
        const text = (data.content?.[0]?.text ?? "").replace(/```json|```/g, "").trim();
        parsed = JSON.parse(text);
      }
    } catch { /* fallback */ }

    // Fallback: حساب محلي من البيانات اليدوية
    if (!parsed && inputMode === "manual") {
      const a = parseFloat(manualData.total_assets) || 0;
      const l = parseFloat(manualData.total_liabilities) || 0;
      const eq = parseFloat(manualData.equity) || (a - l);
      const rev = parseFloat(manualData.revenue) || 0;
      const np = parseFloat(manualData.net_profit) || 0;
      const opex = parseFloat(manualData.operating_expenses) || 0;
      const cash = parseFloat(manualData.cash) || 0;
      const ar = parseFloat(manualData.accounts_receivable) || 0;
      const currentRatio = l > 0 ? parseFloat(((cash + ar) / l).toFixed(2)) : 0;
      const d2e = eq > 0 ? parseFloat((l / eq).toFixed(2)) : 0;
      const roe = eq > 0 ? parseFloat(((np / eq) * 100).toFixed(1)) : 0;
      const roa = a > 0 ? parseFloat(((np / a) * 100).toFixed(1)) : 0;
      const margin = rev > 0 ? parseFloat(((np / rev) * 100).toFixed(1)) : 0;
      const risks: string[] = [];
      if (currentRatio < 1) risks.push("نسبة السيولة أقل من 1 — خطر على الملاءة قصيرة المدى");
      if (d2e > 2) risks.push("نسبة الدين إلى حقوق الملكية مرتفعة");
      if (margin < 5) risks.push("هامش الربح منخفض — مراجعة هيكل التكاليف");
      if (np < 0) risks.push("الكيان يُحقق خسائر");
      let score = 60;
      if (currentRatio >= 1.5) score += 10;
      if (d2e <= 1) score += 10;
      if (margin >= 15) score += 10;
      if (roe >= 10) score += 10;
      parsed = {
        balance_sheet: { total_assets: a, total_liabilities: l, equity: eq },
        income_stmt: { revenue: rev, net_profit: np, operating_expenses: opex, profit_margin: margin },
        ratios: { current_ratio: currentRatio, debt_to_equity: d2e, return_on_equity: roe, return_on_assets: roa },
        health_score: Math.min(100, Math.max(0, score)),
        risks,
        summary: `الكيان ${entity.entity_name} لديه إيرادات ${rev.toLocaleString()} AED وصافي ربح ${np.toLocaleString()} AED بهامش ربح ${margin}%. درجة الصحة المالية الإجمالية ${score}/100.`,
        source: "local",
      };
    }

    if (parsed) {
      setResult(parsed);
      // حفظ في قاعدة البيانات
      await supabase.from("financial_analyses").insert({
        entity_id: entity.id,
        user_id: user!.id,
        source_files: files.map((f) => f.name),
        raw_data: inputMode === "manual" ? manualData : {},
        balance_sheet: parsed.balance_sheet,
        income_stmt: parsed.income_stmt,
        ratios: parsed.ratios,
        ai_summary: parsed.summary,
        ai_risks: parsed.risks,
        health_score: parsed.health_score,
      });
      await supabase.from("entities").update({ financial_analyzed: true, current_step: 7 }).eq("id", entity.id);
    } else {
      toast.error("لم يتمكن النظام من إجراء التحليل");
    }
    setBusy(false);
  };

  const fmt = (n: any) => Number(n || 0).toLocaleString("ar-AE");

  return (
    <Card className="shadow-card">
      <CardHeader>
        <CardTitle>التحليل المالي وميزانية الكيان</CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        {/* اختيار وضع الإدخال */}
        <div className="flex gap-2">
          <Button
            type="button"
            variant={inputMode === "file" ? "premium" : "outline"}
            size="sm"
            onClick={() => setInputMode("file")}
          >
            رفع ملفات
          </Button>
          <Button
            type="button"
            variant={inputMode === "manual" ? "premium" : "outline"}
            size="sm"
            onClick={() => setInputMode("manual")}
          >
            إدخال يدوي
          </Button>
        </div>

        {/* وضع رفع الملفات */}
        {inputMode === "file" && (
          <div className="space-y-3">
            <div className="text-sm text-muted-foreground">
              ارفع ملفات Excel أو CSV أو PDF المالية. ملفات CSV سيتم قراءتها تلقائياً.
            </div>
            <FileUploadZone
              label="الملفات المالية (Excel · CSV · PDF)"
              files={files}
              onChange={setFiles}
              accept=".xlsx,.xls,.csv,.pdf"
            />
          </div>
        )}

        {/* وضع الإدخال اليدوي */}
        {inputMode === "manual" && (
          <div className="grid md:grid-cols-2 gap-4">
            {Object.entries(labels).map(([key, lbl]) => (
              <Field key={key} label={lbl}>
                <Input
                  type="number"
                  min={0}
                  placeholder="0"
                  value={(manualData as any)[key]}
                  onChange={(e) => setManualData({ ...manualData, [key]: e.target.value })}
                />
              </Field>
            ))}
          </div>
        )}

        <div className="flex gap-3 flex-wrap">
          <Button
            type="button"
            variant="premium"
            onClick={runAnalysis}
            disabled={busy || (inputMode === "file" && files.length === 0)}
          >
            {busy ? "جاري التحليل..." : "تشغيل التحليل المالي"}
          </Button>
          {result && (
            <Button type="button" variant="outline" onClick={() => { onSaved(entity); }}>
              {t("btn_next")}
            </Button>
          )}
        </div>

        {/* نتائج التحليل */}
        {busy && (
          <div className="rounded-xl border border-border bg-muted/20 p-8 text-center animate-pulse">
            الذكاء الاصطناعي يحلل البيانات المالية...
          </div>
        )}
        {result && !busy && (
          <div className="space-y-4 animate-fade-in">
            {/* درجة الصحة */}
            <div className="rounded-xl border border-border bg-card p-5">
              <div className="flex items-center gap-4">
                <div className="text-4xl font-bold text-primary">{result.health_score}/100</div>
                <div>
                  <div className="font-semibold">درجة الصحة المالية</div>
                  {result.source === "local" && (
                    <div className="text-xs text-muted-foreground">تحليل محلي (بدون AI)</div>
                  )}
                </div>
              </div>
            </div>

            {/* الميزانية العمومية */}
            <div className="rounded-xl border border-border p-4 space-y-2">
              <div className="font-semibold text-sm">الميزانية العمومية</div>
              <div className="grid grid-cols-3 gap-3 text-sm">
                <div><div className="text-muted-foreground text-xs">الأصول</div><div className="font-mono">{fmt(result.balance_sheet?.total_assets)}</div></div>
                <div><div className="text-muted-foreground text-xs">الخصوم</div><div className="font-mono">{fmt(result.balance_sheet?.total_liabilities)}</div></div>
                <div><div className="text-muted-foreground text-xs">حقوق الملكية</div><div className="font-mono">{fmt(result.balance_sheet?.equity)}</div></div>
              </div>
            </div>

            {/* قائمة الدخل */}
            <div className="rounded-xl border border-border p-4 space-y-2">
              <div className="font-semibold text-sm">قائمة الدخل</div>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div><div className="text-muted-foreground text-xs">الإيرادات</div><div className="font-mono">{fmt(result.income_stmt?.revenue)}</div></div>
                <div><div className="text-muted-foreground text-xs">صافي الربح</div><div className="font-mono">{fmt(result.income_stmt?.net_profit)}</div></div>
                <div><div className="text-muted-foreground text-xs">المصاريف</div><div className="font-mono">{fmt(result.income_stmt?.operating_expenses)}</div></div>
                <div><div className="text-muted-foreground text-xs">هامش الربح</div><div className="font-mono">{result.income_stmt?.profit_margin}%</div></div>
              </div>
            </div>

            {/* النسب المالية */}
            <div className="rounded-xl border border-border p-4 space-y-2">
              <div className="font-semibold text-sm">النسب المالية الرئيسية</div>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div><div className="text-muted-foreground text-xs">نسبة السيولة</div><div className="font-mono">{result.ratios?.current_ratio}</div></div>
                <div><div className="text-muted-foreground text-xs">دين/ملكية</div><div className="font-mono">{result.ratios?.debt_to_equity}</div></div>
                <div><div className="text-muted-foreground text-xs">العائد على الملكية</div><div className="font-mono">{result.ratios?.return_on_equity}%</div></div>
                <div><div className="text-muted-foreground text-xs">العائد على الأصول</div><div className="font-mono">{result.ratios?.return_on_assets}%</div></div>
              </div>
            </div>

            {/* المخاطر */}
            {result.risks?.length > 0 && (
              <div className="rounded-xl border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950/20 p-4 space-y-2">
                <div className="text-sm font-semibold text-yellow-800 dark:text-yellow-300">نقاط المخاطر</div>
                <ul className="space-y-1">
                  {result.risks.map((r: string, i: number) => (
                    <li key={i} className="text-xs text-yellow-700 dark:text-yellow-400">• {r}</li>
                  ))}
                </ul>
              </div>
            )}

            {/* الملخص التنفيذي */}
            {result.summary && (
              <div className="rounded-xl border border-border bg-muted/10 p-4 text-sm leading-relaxed">
                <div className="font-semibold mb-1">الملخص التنفيذي</div>
                {result.summary}
              </div>
            )}
          </div>
        )}

        <div className="flex justify-between pt-4 border-t border-border">
          <Button type="button" variant="outline" onClick={onBack}>{t("btn_back")}</Button>
          {result && (
            <Button type="button" variant="premium" onClick={() => onSaved(entity)}>
              {t("btn_next")}
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

// STEP 5
function EngagementForm({ entity, onSaved, onBack, t }: any) {
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
    onSaved();
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

// ── PAYMENT FORM ─────────────────────────────────────────────────────────────
function PaymentForm({ entity, onBack, t }: any) {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [method, setMethod] = useState<"card" | "bank_transfer" | "apple_pay" | "google_pay" | "">("");
  const [busy, setBusy] = useState(false);
  const [paid, setPaid] = useState(false);
  const [cardForm, setCardForm] = useState({ number: "", expiry: "", cvv: "", name: "" });

  // جلب الرسوم من audit_fees
  const [fee, setFee] = useState(0);
  useEffect(() => {
    supabase.from("audit_fees").select("calculated_fee").eq("entity_id", entity.id).maybeSingle()
      .then(({ data }) => setFee(data?.calculated_fee ?? 0));
    // تحقق من الدفع السابق
    supabase.from("payments").select("status").eq("entity_id", entity.id).eq("status", "paid").maybeSingle()
      .then(({ data }) => { if (data) setPaid(true); });
  }, [entity.id]);

  const processPayment = async () => {
    if (!method) return toast.error("اختر طريقة الدفع");
    if (method === "card" && (!cardForm.number || !cardForm.expiry || !cardForm.cvv))
      return toast.error("أدخل بيانات البطاقة");
    setBusy(true);

    // محاكاة بوابة الدفع (في الإنتاج يُستبدل بـ Stripe/PayTabs/Telr)
    await new Promise((r) => setTimeout(r, 2000));

    const ref = `PAY-${entity.id.slice(0, 8).toUpperCase()}-${Date.now()}`;
    const { error } = await supabase.from("payments").insert({
      entity_id: entity.id,
      user_id: user!.id,
      amount: fee,
      currency: "AED",
      status: "paid",
      method,
      reference: ref,
      gateway_ref: `GATEWAY-${Math.random().toString(36).slice(2, 10).toUpperCase()}`,
      paid_at: new Date().toISOString(),
    });
    if (!error) {
      await supabase.from("entities").update({ payment_status: "paid" }).eq("id", entity.id);
      await supabase.from("user_audit_logs").insert({
        user_id: user!.id,
        action: "payment_completed",
        description: `دفع رسوم المراجعة ${fee.toLocaleString()} AED للكيان ${entity.entity_name} — المرجع: ${ref}`,
      });
      setPaid(true);
      toast.success(`✅ تمت عملية الدفع بنجاح — المرجع: ${ref}`);
    } else {
      toast.error("فشل في معالجة الدفع — حاول مرة أخرى");
    }
    setBusy(false);
  };

  if (paid) {
    return (
      <Card className="shadow-card">
        <CardContent className="py-16 text-center space-y-5">
          <div className="size-20 rounded-full bg-green-100 dark:bg-green-900/30 grid place-items-center mx-auto">
            <span className="text-4xl">✅</span>
          </div>
          <div>
            <div className="text-2xl font-bold text-green-600">تمت عملية الدفع</div>
            <div className="text-muted-foreground mt-1">طلبك مكتمل وسيتم مراجعته قريباً</div>
          </div>
          <Button variant="premium" onClick={() => navigate("/entities")}>
            العودة إلى كياناتي
          </Button>
        </CardContent>
      </Card>
    );
  }

  const methods = [
    { id: "card", label: "بطاقة ائتمانية / مدين", icon: "💳" },
    { id: "bank_transfer", label: "تحويل بنكي", icon: "🏦" },
    { id: "apple_pay", label: "Apple Pay", icon: "" },
    { id: "google_pay", label: "Google Pay", icon: "" },
  ] as const;

  return (
    <Card className="shadow-card">
      <CardHeader>
        <CardTitle>الدفع وإتمام الطلب</CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        {/* ملخص المبلغ */}
        <div className="rounded-xl border border-border bg-gradient-to-br from-accent/20 to-transparent p-5">
          <div className="text-sm text-muted-foreground">المبلغ المستحق</div>
          <div className="text-4xl font-bold text-primary mt-1">
            {fee.toLocaleString()} <span className="text-base font-normal text-muted-foreground">AED</span>
          </div>
          <div className="text-xs text-muted-foreground mt-1">
            رسوم مراجعة — الكيان: {entity.entity_name}
          </div>
        </div>

        {/* اختيار طريقة الدفع */}
        <div className="space-y-2">
          <div className="text-sm font-medium">طريقة الدفع</div>
          <div className="grid grid-cols-2 gap-3">
            {methods.map((m) => (
              <button
                key={m.id}
                type="button"
                onClick={() => setMethod(m.id)}
                className={`flex items-center gap-3 rounded-xl border-2 p-3 text-sm text-start transition-colors ${
                  method === m.id
                    ? "border-primary bg-primary/5"
                    : "border-border hover:border-primary/50 hover:bg-accent/20"
                }`}
              >
                <span className="text-xl">{m.icon}</span>
                <span>{m.label}</span>
              </button>
            ))}
          </div>
        </div>

        {/* بيانات البطاقة */}
        {method === "card" && (
          <div className="space-y-3 rounded-xl border border-border p-4">
            <div className="text-sm font-medium">بيانات البطاقة</div>
            <Field label="رقم البطاقة">
              <Input
                placeholder="XXXX XXXX XXXX XXXX"
                value={cardForm.number}
                onChange={(e) => {
                  const v = e.target.value.replace(/\D/g, "").slice(0, 16);
                  const spaced = v.replace(/(\d{4})/g, "$1 ").trim();
                  setCardForm({ ...cardForm, number: spaced });
                }}
                dir="ltr"
                maxLength={19}
              />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="تاريخ الانتهاء">
                <Input
                  placeholder="MM/YY"
                  value={cardForm.expiry}
                  onChange={(e) => {
                    let v = e.target.value.replace(/\D/g, "").slice(0, 4);
                    if (v.length >= 2) v = v.slice(0, 2) + "/" + v.slice(2);
                    setCardForm({ ...cardForm, expiry: v });
                  }}
                  dir="ltr"
                  maxLength={5}
                />
              </Field>
              <Field label="CVV">
                <Input
                  placeholder="XXX"
                  type="password"
                  value={cardForm.cvv}
                  onChange={(e) => setCardForm({ ...cardForm, cvv: e.target.value.replace(/\D/g, "").slice(0, 4) })}
                  dir="ltr"
                  maxLength={4}
                />
              </Field>
            </div>
            <Field label="اسم حامل البطاقة">
              <Input
                placeholder="NAME AS ON CARD"
                value={cardForm.name}
                onChange={(e) => setCardForm({ ...cardForm, name: e.target.value.toUpperCase() })}
                dir="ltr"
              />
            </Field>
          </div>
        )}

        {/* تحويل بنكي */}
        {method === "bank_transfer" && (
          <div className="rounded-xl border border-border bg-muted/20 p-4 space-y-2 text-sm">
            <div className="font-semibold">تفاصيل الحساب البنكي</div>
            <div className="grid grid-cols-2 gap-2 text-xs">
              <div className="text-muted-foreground">اسم البنك:</div><div>Emirates NBD</div>
              <div className="text-muted-foreground">اسم الحساب:</div><div>Muhasba Accounting LLC</div>
              <div className="text-muted-foreground">رقم الحساب:</div><div dir="ltr">1234-5678-9012</div>
              <div className="text-muted-foreground">IBAN:</div><div dir="ltr">AE07 0331 2345 6789 0123 456</div>
              <div className="text-muted-foreground">Swift:</div><div>EBILAEAD</div>
              <div className="text-muted-foreground">المرجع:</div>
              <div className="font-mono font-bold">{entity.engagement_number ?? entity.id.slice(0, 8).toUpperCase()}</div>
            </div>
            <div className="text-xs text-muted-foreground mt-2">
              ⚠️ يُرجى ذكر رقم المرجع عند التحويل. سيتم تأكيد استلام الدفع خلال 1-2 يوم عمل.
            </div>
          </div>
        )}

        {/* ملاحظة أمان */}
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <span>🔒</span>
          <span>جميع معاملاتك محمية بتشفير SSL 256-bit</span>
        </div>

        <div className="flex justify-between pt-4 border-t border-border">
          <Button type="button" variant="outline" onClick={onBack}>{t("btn_back")}</Button>
          <Button
            type="button"
            variant="premium"
            disabled={busy || !method}
            onClick={processPayment}
            className="min-w-32"
          >
            {busy ? "جاري المعالجة..." : `ادفع ${fee.toLocaleString()} AED`}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
