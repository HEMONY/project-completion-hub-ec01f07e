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
import { Badge } from "@/components/ui/badge";
import { toast } from "sonner";
import { Plus, Trash2, Upload, FileText, X, CheckCircle2, AlertTriangle, Loader2, ExternalLink } from "lucide-react";

// Steps match exactly the HTML sections:
// S1: Entity Info + Contact + Shareholders + UBOs + Management + PEP + Declarations = "kyc"
// S2: Audit Fee = "audit-fee"
// S3: Financial Year = "financial-year"
// S4: Tax Status = "tax-status"
// S5: Engagement Letter = "engagement"
// S6: Payment = "payment"
const validSteps: KycStepKey[] = [
  "kyc",
  "audit-fee",
  "financial-year",
  "tax-status",
  "engagement",
  "payment",
];

function NativeSelect(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select
      {...props}
      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
    />
  );
}
const BLACKLISTED_NATIONALITIES = ["Iranian", "Burmese", "North Korean"];

const ALL_NATIONALITIES = [
  "Afghan", "Albanian", "Algerian", "American", "Andorran", "Angolan",
  "Antiguans", "Argentine", "Armenian", "Australian", "Austrian", "Azerbaijani",
  "Bahamian", "Bahraini", "Bangladeshi", "Barbadian", "Belarusian", "Belgian",
  "Belizean", "Beninese", "Bhutanese", "Bolivian", "Bosnian", "Botswana",
  "Brazilian", "British", "Bruneian", "Bulgarian", "Burkinabe", "Burundian",
  "Cabo Verdean", "Cambodian", "Cameroonian", "Canadian", "Central African",
  "Chadian", "Chilean", "Chinese", "Colombian", "Comoran", "Congolese",
  "Costa Rican", "Croatian", "Cuban", "Cypriot", "Czech",
  "Danish", "Djiboutian", "Dominican",
  "Dutch",
  "Ecuadorian", "Egyptian", "Emirati", "Equatorial Guinean", "Eritrean",
  "Estonian", "Eswatini", "Ethiopian",
  "Fijian", "Filipino", "Finnish", "French",
  "Gabonese", "Gambian", "Georgian", "German", "Ghanaian", "Greek",
  "Grenadian", "Guatemalan", "Guinean", "Guinea-Bissauan", "Guyanese",
  "Haitian", "Honduran", "Hungarian",
  "Icelandic", "Indian", "Indonesian", "Iraqi", "Irish", "Israeli",
  "Italian", "Ivorian",
  "Jamaican", "Japanese", "Jordanian",
  "Kazakhstani", "Kenyan", "Kiribatian", "Kuwaiti", "Kyrgyz",
  "Laotian", "Latvian", "Lebanese", "Lesothan", "Liberian", "Libyan",
  "Liechtensteiner", "Lithuanian", "Luxembourger",
  "Malagasy", "Malawian", "Malaysian", "Maldivian", "Malian", "Maltese",
  "Marshallese", "Mauritanian", "Mauritian", "Mexican", "Micronesian",
  "Moldovan", "Monacan", "Mongolian", "Montenegrin", "Moroccan", "Mozambican",
  "Namibian", "Nauruan", "Nepalese", "New Zealander", "Nicaraguan",
  "Nigerian", "Nigerien", "Norwegian",
  "Omani",
  "Pakistani", "Palauan", "Palestinian", "Panamanian", "Papua New Guinean",
  "Paraguayan", "Peruvian", "Polish", "Portuguese",
  "Qatari",
  "Romanian", "Russian", "Rwandan",
  "Saint Lucian", "Salvadoran", "Samoan", "Saudi", "Senegalese", "Serbian",
  "Seychellois", "Sierra Leonean", "Singaporean", "Slovak", "Slovenian",
  "Solomon Islander", "Somali", "South African", "South Sudanese",
  "Spanish", "Sri Lankan", "Sudanese", "Surinamese", "Swedish", "Swiss", "Syrian",
  "Taiwanese", "Tajik", "Tanzanian", "Thai", "Timorese", "Togolese",
  "Tongan", "Trinidadian", "Tunisian", "Turkish", "Turkmen", "Tuvaluan",
  "Ugandan", "Ukrainian", "Uruguayan", "Uzbek",
  "Vanuatuan", "Venezuelan", "Vietnamese",
  "Yemeni",
  "Zambian", "Zimbabwean",
].filter((n) => !BLACKLISTED_NATIONALITIES.includes(n));

function isBlacklisted(nationality: string) {
  return BLACKLISTED_NATIONALITIES.some((b) =>
    nationality.toLowerCase().includes(b.toLowerCase())
  );
}
function NationalitySelect({
  value, onChange,
}: { value: string; onChange: (v: string) => void }) {
  const [search, setSearch] = useState("");
  const [open, setOpen] = useState(false);

  const filtered = ALL_NATIONALITIES.filter((n) =>
    n.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="relative">
      <div
        onClick={() => setOpen(!open)}
        className="flex h-10 w-full cursor-pointer items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none"
      >
        <span className={value ? "text-foreground" : "text-muted-foreground"}>
          {value || "Select nationality..."}
        </span>
        <svg className="size-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </div>
      {open && (
        <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-background shadow-lg">
          <div className="p-2">
            <input
              autoFocus
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search nationality..."
              className="w-full rounded border border-input bg-background px-2 py-1.5 text-sm outline-none"
            />
          </div>
          <ul className="max-h-48 overflow-y-auto">
            {filtered.length === 0 ? (
              <li className="px-3 py-2 text-sm text-muted-foreground">No results</li>
            ) : filtered.map((n) => (
              <li
                key={n}
                onClick={() => { onChange(n); setOpen(false); setSearch(""); }}
                className={`cursor-pointer px-3 py-2 text-sm hover:bg-accent ${value === n ? "bg-primary/10 font-medium" : ""}`}
              >
                {n}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

function Field({ label, children, required }: { label: string; children: React.ReactNode; required?: boolean }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-sm font-semibold">{label}{required && <span className="text-destructive ms-1">*</span>}</Label>
      {children}
    </div>
  );
}

function SectionTitle({ number, title }: { number: number; title: string }) {
  return (
    <div className="flex items-center gap-3 mt-8 mb-5 pb-3 border-b-2 border-border">
      <div className="size-8 rounded-full bg-primary text-primary-foreground grid place-items-center text-sm font-bold shrink-0">
        {number}
      </div>
      <h3 className="text-base font-bold uppercase tracking-wide">{title}</h3>
    </div>
  );
}

// ── OCR Name Verification ─────────────────────────────────────────────────
// ── OCR Name Verification via Claude Vision API ────────────────────────────
async function verifyWithOCR(
  imageFile: File,
  enteredName: string,
  type: "id" | "license" = "id"
): Promise<{ match: boolean; extractedName: string; dates: Record<string, string>; confidence: string }> {
  const supabaseUrl = import.meta.env.VITE_SUPABASE_URL;
  const supabaseKey = import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY;
  // Convert file to base64
  const b64 = await new Promise<string>((res, rej) => {
    const reader = new FileReader();
    reader.onload = () => res((reader.result as string).split(",")[1]);
    reader.onerror = () => rej(new Error("File read failed"));
    reader.readAsDataURL(imageFile);
  });

  // Claude Vision only supports image types — PDFs must be sent as image/jpeg
  const mime: "image/jpeg" | "image/png" | "image/gif" | "image/webp" =
    imageFile.type === "image/png"  ? "image/png"  :
    imageFile.type === "image/gif"  ? "image/gif"  :
    imageFile.type === "image/webp" ? "image/webp" :
    "image/jpeg";
  
  const prompt = type === "license"
    ? `This is a UAE Trade License / Professional License document.
Extract ALL of the following fields and return ONLY a valid JSON object — no markdown, no extra text:
{
  "extractedName": "<Trade Name exactly as printed>",
  "licenseNumber": "<License No value>",
  "issueDate": "<Issue Date as DD/MM/YYYY>",
  "expiryDate": "<Expiry Date as DD/MM/YYYY>",
  "legalType": "<Legal Type e.g. Sole Establishment, LLC>",
  "match": <true if extractedName matches "${enteredName}" ignoring case/spaces/punctuation, otherwise false>
}`
    : `This is a UAE Emirates ID (front and back) or passport.
Extract ALL of the following fields and return ONLY a valid JSON object — no markdown, no extra text:
{
  "extractedName": "<Full Name in English exactly as printed>",
  "idNumber": "<ID Number>",
  "dateOfBirth": "<Date of Birth as DD/MM/YYYY>",
  "expiryDate": "<Expiry Date as DD/MM/YYYY>",
  "issuingDate": "<Issuing Date as DD/MM/YYYY>",
  "nationality": "<Nationality>",
  "match": <true if extractedName matches "${enteredName}" — consider a match if all words in the entered name appear in the extracted name ignoring case/spaces, otherwise false>
}`;

  try {
    const resp = await fetch(`${supabaseUrl}/functions/v1/verify-id-ocr`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "apikey": supabaseKey,
        "Authorization": `Bearer ${supabaseKey}`,
      },
      body: JSON.stringify({ imageB64: b64, imageMime: mime, enteredName, type }),
    });

    if (!resp.ok) {
      console.error("OCR error:", resp.status, await resp.text());
      return { match: false, extractedName: "", dates: {}, confidence: "api_error" };
    }

    const data = await resp.json();
    const parsed = data;

    const extractedName = parsed.extractedName ?? "";

    // مقارنة محلية موثوقة بدل الاعتماد على Claude فقط
    const localMatch = (() => {
      if (!extractedName || !enteredName) return false;
      const norm = (s: string) => s.toLowerCase().replace(/[\s\-_.,']/g, "");
      const entered = norm(enteredName);
      const extracted = norm(extractedName);
      if (entered === extracted) return true;
      if (extracted.includes(entered) || entered.includes(extracted)) return true;
      const words = enteredName.toLowerCase().split(/\s+/).filter(w => w.length > 1);
      return words.length > 0 && words.every(w => extractedName.toLowerCase().includes(w));
    })();

    return {
      match: parsed.match === true || localMatch,
      extractedName,
      dates: {
        ...(parsed.issueDate     && { issue_date:      parsed.issueDate }),
        ...(parsed.issuingDate   && { issuing_date:    parsed.issuingDate }),
        ...(parsed.expiryDate    && { expiry_date:     parsed.expiryDate }),
        ...(parsed.dateOfBirth   && { dob:             parsed.dateOfBirth }),
        ...(parsed.licenseNumber && { license_number:  parsed.licenseNumber }),
        ...(parsed.idNumber      && { id_number:       parsed.idNumber }),
        ...(parsed.legalType     && { legal_type:      parsed.legalType }),
      },
      confidence: parsed.match ? "high" : "low",
    };
  } catch (err) {
    console.error("OCR error:", err);
    return { match: false, extractedName: "", dates: {}, confidence: "error" };
  }
}

// ── File Upload Zone ────────────────────────────────────────────────────────
function FileUploadZone({
  label, files, onChange, accept, single,
}: {
  label: string; files: File[]; onChange: (f: File[]) => void; accept: string; single?: boolean;
}) {
  const ref = useRef<HTMLInputElement>(null);
  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newFiles = Array.from(e.target.files || []);
    onChange(single ? newFiles.slice(0, 1) : [...files, ...newFiles]);
    e.target.value = "";
  };
  const remove = (i: number) => onChange(files.filter((_, idx) => idx !== i));
  return (
    <div className="space-y-2">
      <div
        onClick={() => ref.current?.click()}
        className="border-2 border-dashed border-border hover:border-primary hover:bg-accent/20 transition-colors rounded-lg p-4 text-center cursor-pointer group"
      >
        <Upload className="size-5 text-muted-foreground group-hover:text-primary mx-auto mb-1.5" />
        <div className="text-xs font-medium text-muted-foreground group-hover:text-primary">{label}</div>
        <div className="text-[10px] text-muted-foreground mt-0.5">PDF, JPG, PNG</div>
        <input ref={ref} type="file" multiple={!single} accept={accept} className="hidden" onChange={handleChange} />
      </div>
      {files.length > 0 && (
        <ul className="space-y-1">
          {files.map((f, i) => (
            <li key={i} className="flex items-center gap-2 text-xs bg-muted/50 rounded px-2 py-1.5">
              <FileText className="size-3 shrink-0 text-muted-foreground" />
              <span className="truncate flex-1">{f.name}</span>
              <button type="button" onClick={() => remove(i)} className="text-destructive shrink-0 hover:opacity-80">
                <X className="size-3" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

// ── OCR Badge ───────────────────────────────────────────────────────────────
function OcrBadge({ status, extractedName }: { status: "idle"|"checking"|"match"|"mismatch"|"no_key"; extractedName?: string }) {
  if (status === "idle") return null;
  if (status === "checking") return (
    <div className="flex items-center gap-1.5 text-xs text-primary">
      <Loader2 className="size-3.5 animate-spin" /> Verifying name against ID...
    </div>
  );
  if (status === "no_key") return (
    <div className="text-xs text-muted-foreground">OCR verification unavailable (no API key)</div>
  );
  if (status === "match") return (
    <div className="flex items-center gap-1.5 text-xs text-green-600">
      <CheckCircle2 className="size-3.5" /> Name matches ID: "<span className="font-medium">{extractedName}</span>"
    </div>
  );
  return (
    <div className="flex items-center gap-1.5 text-xs text-destructive">
      <AlertTriangle className="size-3.5" />
      Name mismatch! ID shows: "<span className="font-medium">{extractedName || "unreadable"}</span>"
    </div>
  );
}

// ── Person Card (Shareholder / UBO / Manager) ───────────────────────────────
type Person = {
  name: string;
  capital: string;
  nationality: string;
  dob_place: string;
  emirates_id: string;
  address: string;
  id_files: File[];
  passport_files: File[];
  ocr_status: "idle"|"checking"|"match"|"mismatch"|"no_key";
  ocr_extracted: string;
  ocr_dates: Record<string, string>;
};
// ── Document Preview Modal ──────────────────────────────────────────────────
function DocPreview({ file, onClose }: { file: File; onClose: () => void }) {
  const url = URL.createObjectURL(file);
  const isPdf = file.type === "application/pdf" || file.name.endsWith(".pdf");
  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
      onClick={onClose}
    >
      <div
        className="relative bg-background rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between px-4 py-3 border-b border-border">
          <span className="text-sm font-semibold truncate">{file.name}</span>
          <button
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground transition-colors"
          >
            <X className="size-5" />
          </button>
        </div>
        <div className="flex-1 overflow-auto">
          {isPdf ? (
            <iframe src={url} className="w-full h-[75vh] border-none" />
          ) : (
            <img src={url} alt={file.name} className="w-full object-contain max-h-[75vh]" />
          )}
        </div>
      </div>
    </div>
  );
}
function PersonCard({
  person, index, onChange, onRemove, showCapital, canRemove, label,
}: {
  person: Person;
  index: number;
  onChange: (p: Person) => void;
  onRemove: () => void;
  showCapital?: boolean;
  canRemove: boolean;
  label: string;
}) {
  const set = (k: keyof Person, v: any) => onChange({ ...person, [k]: v });
  const [previewFile, setPreviewFile] = useState<File | null>(null);

  const runOCR = async (files?: File[]) => {
    const filesToUse = files ?? person.id_files;
    if (filesToUse.length === 0) return;
    if (!person.name.trim()) {
      toast.error("Please enter the full name before verifying");
      return;
    }
    onChange({ ...person, id_files: filesToUse, ocr_status: "checking" });
    const result = await verifyWithOCR(filesToUse[0], person.name, "id");

    let idNumMatch = true;
    if (result.dates.id_number && person.emirates_id) {
      idNumMatch = person.emirates_id.replace(/\D/g, "") === result.dates.id_number.replace(/\D/g, "");
    }
    const finalMatch = result.match && idNumMatch;

    onChange({
      ...person,
      id_files: filesToUse,
      ocr_status: finalMatch ? "match" : "mismatch",
      ocr_extracted: result.extractedName,
      ocr_dates: result.dates,
    });
  };

  return (
    <>
      {previewFile && <DocPreview file={previewFile} onClose={() => setPreviewFile(null)} />}
      <div className="border border-border rounded-xl bg-card p-5 space-y-4">
        <div className="flex items-center justify-between">
          <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
            {label} {index + 1}
          </span>
          {canRemove && (
            <Button type="button" size="sm" variant="ghost" onClick={onRemove} className="text-destructive hover:text-destructive h-7">
              <Trash2 className="size-3.5" /> <span className="ms-1 text-xs">Remove</span>
            </Button>
          )}
        </div>
        <div className="grid md:grid-cols-2 gap-3">
          <Field label="Full Name" required>
            <Input required value={person.name} placeholder="Full legal name" onChange={(e) => set("name", e.target.value)} />
          </Field>
          {showCapital && (
            <Field label="Capital (%)" required>
              <Input required type="number" min={0} max={100} step="0.01" value={person.capital} placeholder="e.g. 50" onChange={(e) => set("capital", e.target.value)} />
            </Field>
          )}
          <Field label="Nationality" required>
            <NationalitySelect value={person.nationality} onChange={(v) => set("nationality", v)} />
            {isBlacklisted(person.nationality) && (
              <div className="text-xs text-destructive mt-1 font-semibold">
                ⚠️ This nationality is not permitted under our compliance framework
              </div>
            )}
          </Field>
          <Field label="Date & Place of Birth" required>
            <Input required value={person.dob_place} placeholder="DD/MM/YYYY, City, Country" onChange={(e) => set("dob_place", e.target.value)} />
          </Field>
          <Field label="Emirates ID Number (15 digits)" required>
            <Input
              required value={person.emirates_id} placeholder="784XXXXXXXXXX" maxLength={15}
              onChange={(e) => set("emirates_id", e.target.value.replace(/\D/g, "").slice(0, 15))}
            />
            {person.emirates_id && person.emirates_id.length !== 15 && (
              <div className="text-xs text-destructive mt-1">Must be exactly 15 digits</div>
            )}
          </Field>
          <Field label="Residential Address" required>
            <Input required value={person.address} placeholder="Full residential address" onChange={(e) => set("address", e.target.value)} />
          </Field>
        </div>

        {/* Document Upload with OCR */}
        <div className="grid md:grid-cols-2 gap-3 pt-2 border-t border-border">
          {/* Emirates ID */}
          <div className="space-y-2">
            <FileUploadZone
              label="Upload Emirates ID *"
              files={person.id_files}
              onChange={(files) => onChange({ ...person, id_files: files, ocr_status: "idle" })}
              accept="image/png,image/jpeg,image/jpg,.pdf"
              single
            />
            {person.id_files.length > 0 && (
              <div className="flex gap-2">
                <Button
                  type="button" size="sm" variant="outline" className="flex-1"
                  disabled={person.ocr_status === "checking"}
                  onClick={() => runOCR()}
                >
                  {person.ocr_status === "checking"
                    ? <><Loader2 className="size-3.5 animate-spin mr-1" /> Verifying...</>
                    : <><CheckCircle2 className="size-3.5 mr-1" /> Verify Name against ID</>}
                </Button>
                <Button
                  type="button" size="sm" variant="outline"
                  title="Preview Emirates ID"
                  onClick={() => setPreviewFile(person.id_files[0])}
                >
                  <svg xmlns="http://www.w3.org/2000/svg" className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </Button>
              </div>
            )}
            <OcrBadge status={person.ocr_status} extractedName={person.ocr_extracted} />
            {Object.keys(person.ocr_dates ?? {}).length > 0 && (person.ocr_status === "match" || person.ocr_status === "mismatch") && (
              <div className="text-xs bg-muted/40 border border-border rounded-lg p-2 space-y-0.5">
                {person.ocr_dates.id_number && (
                  <div className="flex items-center gap-2">
                    <span>🪪 ID No:</span>
                    <span className="font-semibold">{person.ocr_dates.id_number}</span>
                    {person.emirates_id && (
                      person.emirates_id.replace(/\D/g, "") === person.ocr_dates.id_number.replace(/\D/g, "")
                        ? <span className="text-green-600 font-semibold">✅ Matches</span>
                        : <span className="text-destructive font-semibold">⚠️ ID mismatch: entered {person.emirates_id}</span>
                    )}
                  </div>
                )}
                {person.ocr_dates.expiry_date && (
                  <div className="flex items-center gap-2">
                    <span>⏳ Expiry:</span>
                    <span className="font-semibold">{person.ocr_dates.expiry_date}</span>
                    {(() => {
                      const [d, m, y] = person.ocr_dates.expiry_date.split("/");
                      const expiry = new Date(`${y}-${m}-${d}`);
                      return expiry < new Date()
                        ? <span className="text-destructive font-semibold">⚠️ EXPIRED</span>
                        : <span className="text-green-600 font-semibold">✅ Valid</span>;
                    })()}
                  </div>
                )}
              </div>
            )}
            {person.ocr_status === "mismatch" && (
              <div className="text-sm text-destructive bg-destructive/10 border border-destructive/30 rounded-lg p-3 space-y-1">
                <div className="font-semibold">⚠️ Name Mismatch — Cannot Proceed</div>
                <div>Name you entered: <span className="font-bold">{person.name}</span></div>
                <div>Name on Emirates ID: <span className="font-bold">{person.ocr_extracted || "Could not be read"}</span></div>
                <div className="text-xs text-muted-foreground mt-1">Please correct the name to match exactly what appears on the ID.</div>
              </div>
            )}
          </div>

          {/* Passport */}
          <div className="space-y-2">
            <FileUploadZone
              label="Upload Passport *"
              files={person.passport_files}
              onChange={(files) => set("passport_files", files)}
              accept="image/*,.pdf"
              single
            />
            {person.passport_files.length > 0 && (
              <Button
                type="button" size="sm" variant="outline" className="w-full"
                title="Preview Passport"
                onClick={() => setPreviewFile(person.passport_files[0])}
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="size-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                Preview Passport
              </Button>
            )}
          </div>
        </div>
      </div>
    </>
  );
}

function emptyPerson(): Person {
  return { name: "", capital: "", nationality: "", dob_place: "", emirates_id: "", address: "", id_files: [], passport_files: [], ocr_status: "idle", ocr_extracted: "", ocr_dates: {} };
}

// ── LEGAL DECLARATIONS (15 items from HTML) ─────────────────────────────────
const DECLARATIONS = [
        "The entity hereby declares and confirms that it maintains proper accounting records and statutory books that accurately reflect its financial position and enable the traceability of all transactions in compliance with applicable laws.",
        "The entity hereby confirms that its prior period financial statements (if any) were audited by a licensed auditor, and no material reservations or audit findings were issued that would necessitate an adjustment to the opening balances or a restatement of previous financial data.",
        "The entity hereby declares that, if it is not currently registered with the Federal Tax Authority (FTA), such non-registration is based on valid legal and commercial grounds in compliance with applicable tax laws. The entity remains responsible for monitoring its tax status and registering once the statutory requirements are met.",
        "The entity hereby confirms that all its employees are officially registered with the Ministry of Human Resources and Emiratisation (MOHRE) or the General Directorate of Residency and Foreigners Affairs (GDRFA), as applicable to its business activities. In the absence of registered staff, the entity declares that its operations are either limited to the personal efforts of the Business Owner or are executed through formal contracts with authorized third parties, in full compliance with applicable regulations.",
        "The entity hereby declares that all generated income is derived from genuine and legitimate economic activities, and confirms that it possesses the necessary infrastructure and resources to generate such income. Furthermore, the entity affirms that its registered business address is appropriate and adequate for the nature and scale of its operations.",
        "The entity hereby confirms that its total annual revenue (both operating and non-operating), for the current financial year and any prior years (if applicable), has not exceeded AED 50 million for any single financial period.",
        "The entity hereby confirms that any remarks, fines, or penalties issued by the Federal Tax Authority (FTA) against it (if any) are strictly related to outstanding tax liabilities or technical/procedural errors, and do not involve any matters related to integrity or intentional tax evasion.",
        "The entity hereby confirms that it maintains no business or financial relationships with prohibited, suspicious, or shell entities. Furthermore, any transactions with parties located in high-risk jurisdictions (if any) are conducted on a strictly arms length basis with clear economic substance. The entity undertakes to provide all supporting documentation requested by the auditor for verification purposes.",
        "The entity hereby confirms that any changes occurred during the current financial year whether regarding partners, Ultimate Beneficial Owners (UBOs), business activities, or the entitys legal name were implemented for legitimate commercial reasons and are fully justified. The entity affirms that such changes were not intended, under any circumstances, to conceal the identity of the beneficial owner or to divert the flow of funds for illicit purposes.",
        "The entity hereby confirms that there are no ongoing legal disputes or pending litigations among its partners/owners. Furthermore, the entity affirms that its management and ownership structure are stable, with no existing conflicts that could impact business continuity or the decision-making process.",
        "The entity hereby confirms that there are no confirmed, suspected, or alleged instances of fraud or embezzlement during the current financial year. Furthermore, the entity declares that there are no internal reports or ongoing investigations regarding the integrity of financial data or professional conduct within the entity.",
        "The entity hereby confirms that it does not engage in activities related to financial derivatives, virtual assets, or controlled and non-proliferation goods. Furthermore, the entity declares that its financial statements do not include any assets or transactions arising from mergers or acquisitions, nor do they involve foreign assets, foreign bank accounts, or offshore operating expenses.",
        "The entity hereby confirms that it does not engage in activities related to financial derivatives, virtual assets, or controlled and non-proliferation goods. Furthermore, the entity declares that its financial statements do not include any assets or transactions arising from mergers or acquisitions, nor do they involve foreign assets, foreign bank accounts, or offshore operating expenses. The entity specifically affirms that the management of its activities, including strategic and operational decision-making, is not conducted outside the United Arab Emirates.",
        "The entity hereby confirms that there is no intention, plan, or decision to liquidate the entity, dispose of its material assets, or sell the business. The entity further affirms its ability to continue as a going concern for the foreseeable future.",
        "The entity confirms that the person completing this form is legally authorized to do so on its behalf. The entity certifies the accuracy of all information provided and assumes full legal responsibility for any false or misleading data, undertaking to update it immediately upon any changes."
    ];



// ── User Documents Panel ────────────────────────────────────────────────────
function MyDocumentsPanel({ entityId, userId }: { entityId: string; userId: string }) {
  const [docs, setDocs] = useState<any[]>([]);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    supabase.from("kyc_documents").select("*")
      .eq("entity_id", entityId)
      .eq("user_id", userId)
      .order("uploaded_at", { ascending: false })
      .then(({ data }) => setDocs(data ?? []));
  }, [entityId, userId]);

  if (docs.length === 0) return null;

  const DOC_LABELS: Record<string, string> = {
    trade_license: "رخصة تجارية",
    emirates_id: "هوية إماراتية",
    passport: "جواز سفر",
    authorization_letter: "تفويض",
    cdd_identity: "هوية CDD",
    cdd_eligibility: "أهلية",
    cdd_auditor: "مدقق",
  };

  const openDoc = async (doc: any) => {
    const { data, error } = await supabase.storage.from("kyc-documents").createSignedUrl(doc.storage_path, 600);
    if (error) { toast.error(error.message); return; }
    window.open(data.signedUrl, "_blank", "noopener,noreferrer");
  };

  return (
    <Card className="shadow-card mt-4">
      <CardHeader className="py-3 px-4 cursor-pointer" onClick={() => setOpen(!open)}>
        <CardTitle className="text-sm flex items-center justify-between gap-2">
          <span className="flex items-center gap-2"><FileText className="size-4 text-primary" /> المستندات المرفوعة ({docs.length})</span>
          <span className="text-muted-foreground text-xs">{open ? "▲" : "▼"}</span>
        </CardTitle>
      </CardHeader>
      {open && (
        <CardContent className="px-4 pb-4 space-y-2">
          {docs.map((doc) => {
            const status = doc.status ?? "pending";
            return (
              <div key={doc.id} className="flex items-center gap-2 rounded-md border border-border bg-muted/20 px-3 py-2 text-xs">
                <FileText className="size-3 shrink-0 text-muted-foreground" />
                <div className="flex-1 min-w-0">
                  <div className="truncate font-medium">{doc.file_name}</div>
                  <div className="text-muted-foreground">{DOC_LABELS[doc.document_type] ?? doc.document_type}</div>
                </div>
                <span className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium ${
                  status === "approved" ? "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"
                  : status === "rejected" ? "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"
                  : "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400"
                }`}>
                  {status === "approved" ? "معتمد" : status === "rejected" ? "مرفوض" : "قيد المراجعة"}
                </span>
                {doc.rejection_reason && (
                  <span className="text-destructive text-[10px] shrink-0 max-w-24 truncate" title={doc.rejection_reason}>
                    {doc.rejection_reason}
                  </span>
                )}
                <Button type="button" size="sm" variant="ghost" className="h-6 px-1 shrink-0" onClick={() => openDoc(doc)}>
                  <ExternalLink className="size-3" />
                </Button>
              </div>
            );
          })}
        </CardContent>
      )}
    </Card>
  );
}

// ═══════════════════════════════════════════════════════════════════
// MAIN KycStep router
// ═══════════════════════════════════════════════════════════════════
export default function KycStep() {
  const { entityId, step } = useParams<{ entityId: string; step: string }>();
  const { t } = useI18n();
  const { user, loading } = useAuth();
  const navigate = useNavigate();
  const [entity, setEntity] = useState<any>(null);

  const stepKey = (validSteps.includes(step as KycStepKey) ? step : "kyc") as KycStepKey;

  useEffect(() => {
    if (!loading && !user) navigate("/auth");
  }, [user, loading, navigate]);

  useEffect(() => {
    if (!entityId || !user) return;
    supabase.from("entities").select("*").eq("id", entityId).eq("user_id", user.id).single()
      .then(({ data, error }) => {
        if (error || !data) navigate("/entities");
        else setEntity(data);
      });
  }, [entityId, user]);

  if (!entity) return <AppShell><div className="py-20 text-center text-muted-foreground">{t("loading")}</div></AppShell>;

  const goNext = () => {
    const next = validSteps[validSteps.indexOf(stepKey) + 1];
    if (next) navigate(`/kyc/${entityId}/${next}`);
    else navigate("/entities");
  };
  const goBack = () => {
    const prev = validSteps[validSteps.indexOf(stepKey) - 1];
    if (prev) navigate(`/kyc/${entityId}/${prev}`);
    else navigate("/entities");
  };

  const completedSteps = validSteps.slice(0, Math.max(0, (entity.current_step || 1) - 1));

  return (
    <AppShell>
      <div className="max-w-6xl mx-auto">
        <div className="grid lg:grid-cols-[260px_1fr] gap-6 items-start">
          <div>
            <KycStepper current={stepKey} entityId={entityId} completed={completedSteps} />
            {user && entityId && <MyDocumentsPanel entityId={entityId} userId={user.id} />}
          </div>
          <div className="min-w-0">
            {stepKey === "kyc" && (
              <KycForm entity={entity} onSaved={(e) => { setEntity(e); goNext(); }} t={t} />
            )}
            {stepKey === "audit-fee" && (
              <AuditFeeForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />
            )}
            {stepKey === "financial-year" && (
              <FinancialYearForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />
            )}
            {stepKey === "tax-status" && (
              <TaxStatusForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />
            )}
            {stepKey === "engagement" && (
              <EngagementForm entity={entity} onSaved={goNext} onBack={goBack} t={t} />
            )}
            {stepKey === "payment" && (
              <PaymentForm entity={entity} onBack={goBack} t={t} />
            )}
          </div>
        </div>
      </div>
    </AppShell>
  );
}

// ═══════════════════════════════════════════════════════════════════
// STEP 1 — KYC Form (7 sections matching HTML exactly)
// ═══════════════════════════════════════════════════════════════════

type PepDeclaration = {
  sourceOfFunds: string[];
  otherSource: string;
  wealthSummary: string;
  confirmed: boolean;
};
function KycForm({ entity, onSaved, t }: any) {
  const { user } = useAuth();
  const [busy, setBusy] = useState(false);
  const [previewFile, setPreviewFile] = useState<File | null>(null);

  // § Section 1 — Entity Information
  const [registrationStatus, setRegistrationStatus] = useState(entity.registration_status ?? "");
  const [entityName, setEntityName] = useState(entity.entity_name === "Untitled Entity" ? "" : entity.entity_name);
  const [sourceOfFunds, setSourceOfFunds] = useState(entity.source_of_funds ?? "");
  const [employerName, setEmployerName] = useState(entity.employer_name ?? "");
  const [employerEmiratesId, setEmployerEmiratesId] = useState(entity.employer_emirates_id ?? "");
  const [employerIdFiles, setEmployerIdFiles] = useState<File[]>([]);
  const [employerOcrStatus, setEmployerOcrStatus] = useState<"idle"|"checking"|"match"|"mismatch"|"no_key">("idle");
  const [employerOcrExtracted, setEmployerOcrExtracted] = useState("");
  const [employerOcrDates, setEmployerOcrDates] = useState<Record<string, string>>({});
  const [licenseNumber, setLicenseNumber] = useState(entity.license_number ?? "");
  const [licenseDate, setLicenseDate] = useState(entity.license_issue_date ?? "");
  const [legalType, setLegalType] = useState(entity.legal_type ?? "");
  const [principalActivity, setPrincipalActivity] = useState(entity.principal_activity ?? entity.main_activity ?? "");
  const [economicSector, setEconomicSector] = useState(entity.economic_sector ?? "");
  const [licenseFiles, setLicenseFiles] = useState<File[]>([]);
  const [licenseOcrStatus, setLicenseOcrStatus] = useState<"idle"|"checking"|"match"|"mismatch"|"no_key">("idle");
  const [licenseOcrExtracted, setLicenseOcrExtracted] = useState("");
  const [licenseOcrDates, setLicenseOcrDates] = useState<Record<string, string>>({});

  // § Section 2 — Contact Details
  const [emirate, setEmirate] = useState(entity.emirate ?? "");
  const [address, setAddress] = useState(entity.address ?? "");
  const [telephone, setTelephone] = useState(entity.telephone ?? "");
  const [email, setEmail] = useState(entity.email ?? "");

  // § Section 3 — Shareholders
  const [shareholders, setShareholders] = useState<Person[]>(
    (entity.shareholders ?? []).length > 0
      ? entity.shareholders.map((s: any) => ({ ...emptyPerson(), ...s, id_files: [], passport_files: [] }))
      : [emptyPerson()]
  );

  // § Section 4 — UBOs
  const [hasUbo, setHasUbo] = useState<"yes"|"no"|"">(entity.ubos?.length > 0 ? "yes" : "");
  const [ubos, setUbos] = useState<Person[]>(
    (entity.ubos ?? []).length > 0
      ? entity.ubos.map((u: any) => ({ ...emptyPerson(), ...u, id_files: [], passport_files: [] }))
      : []
  );

  // § Section 5 — Management
  const [managementSelect, setManagementSelect] = useState(entity.management_control ?? "");
  const [managers, setManagers] = useState<Person[]>([emptyPerson()]);
  const [poaFiles, setPoaFiles] = useState<File[]>([]);

  // § Section 6 — PEP
  const [hasPep, setHasPep] = useState<"yes"|"no"|"">(entity.pep_exists ? "yes" : "");
  const [selectedPepNames, setSelectedPepNames] = useState<string[]>(
    entity.pep_persons?.length > 0 ? entity.pep_persons.map((p: any) => p.name ?? "") : []
  );
  /*type PepDeclaration = {
    sourceOfFunds: string[];
    otherSource: string;
    wealthSummary: string;
    confirmed: boolean;
  };*/
  const [pepDeclarations, setPepDeclarations] = useState<Record<string, PepDeclaration>>(
    entity.pep_persons?.length > 0
      ? Object.fromEntries(entity.pep_persons.map((p: any) => [p.name ?? "", {
          sourceOfFunds: p.sourceOfFunds ?? [],
          otherSource: p.otherSource ?? "",
          wealthSummary: p.wealthSummary ?? "",
          confirmed: p.confirmed ?? false,
        }]))
      : {}
  );

  // § Section 7 — Declarations
  const [declarations, setDeclarations] = useState<boolean[]>(
    Array(DECLARATIONS.length).fill(false)
  );

  // Dynamic options
  // بعد
  const isUnlicensed = registrationStatus === "unlicensed";
  const isSole = registrationStatus === "sole";
  const isLicensed = ["multiple", "freezone", "branch"].includes(registrationStatus);
  const managementOptions = [
    ...shareholders.map((s) => s.name).filter(Boolean),
    ...ubos.map((u) => u.name).filter(Boolean),
    "Other",
  ];

  // Legal type options per status
  const legalTypeOptions: Record<string, string[]> = {
    multiple: ["Limited Liability Company", "General Partnership Company", "Limited Partnership Company", "Civil Company"],
    freezone: ["Free Zone Establishment", "Free Zone Company", "Free Zone Branch"],
    branch: ["Branch of Local Company", "Branch of Foreign Company"],
  };

  // Upload files
  const uploadFiles = async (files: File[], folder: string, docType?: string): Promise<string[]> => {
    const paths: string[] = [];
    for (const file of files) {
      const p = `${user!.id}/${entity.id}/${folder}/${Date.now()}_${file.name}`;
      const { data } = await supabase.storage.from("kyc-documents").upload(p, file, { upsert: true });
      if (data) {
        paths.push(data.path);
        if (docType) {
          try {
            await supabase.from("kyc_documents").insert({
              entity_id: entity.id,
              user_id: user!.id,
              file_name: file.name,
              storage_path: data.path,
              document_type: docType,
              mime_type: file.type || "application/octet-stream",
            } as any);
          } catch {
            // non-critical — file is uploaded to storage regardless
          }
        }
      }
    }
    return paths;
  };

  // Validation
  const validate = (): string[] => {
    const errs: string[] = [];
    if (!registrationStatus) errs.push("Entity Registration Status is required");
    if (!entityName.trim()) errs.push("Owner/Company name is required");
    if (isLicensed && !licenseNumber.trim()) errs.push("License number is required");
    if (isLicensed && !licenseDate) errs.push("License issue date is required");
    if (isLicensed && licenseFiles.length > 0 && licenseOcrStatus === "checking") errs.push("Trade License verification still in progress, please wait");
    if (isLicensed && licenseFiles.length > 0 && licenseOcrStatus === "mismatch") errs.push(`Company name does not match Trade License. License shows: "${licenseOcrExtracted || "unreadable"}"`);
    if (isLicensed && licenseOcrDates.license_number && licenseNumber.trim()) {
      const normalize = (s: string) => s.toLowerCase().replace(/[\s\-_.\/]/g, "");
      if (normalize(licenseNumber) !== normalize(licenseOcrDates.license_number)) {
        errs.push(`License number entered (${licenseNumber}) does not match document (${licenseOcrDates.license_number})`);
      }
    }
    if (isLicensed && licenseOcrDates.expiry_date) {
      const [d, m, y] = licenseOcrDates.expiry_date.split("/");
      const expiry = new Date(`${y}-${m}-${d}`);
      if (expiry < new Date()) {
        errs.push(`Trade License is expired (${licenseOcrDates.expiry_date}) — cannot proceed`);
      }
    }
    if (isLicensed && licenseOcrDates.issue_date && licenseDate) {
      const fromDoc = licenseOcrDates.issue_date.split("/").reverse().join("-");
      if (fromDoc !== licenseDate) {
        errs.push(`License issue date entered (${licenseDate}) does not match document (${licenseOcrDates.issue_date})`);
      }
    }
    if (!principalActivity.trim()) errs.push("Principal Activity is required");
    if (!economicSector) errs.push("Economic Sector is required");
    if (!emirate) errs.push("Emirate is required");
    if (!address.trim()) errs.push("Address is required");
    if (!telephone.trim()) errs.push("Telephone is required");
    if (telephone && !/^\+971[0-9]{7,12}$/.test(telephone.replace(/\s/g, ""))) {
      errs.push("Telephone must start with +971 followed by 7-12 digits");
    }
    if (!email.trim()) errs.push("Email is required");
    /*if (employerName.trim()) {
      if (!employerEmiratesId || employerEmiratesId.length !== 15) errs.push("Employer Emirates ID must be 15 digits");
      if (employerIdFiles.length === 0) errs.push("Employer Emirates ID document is required when employer name is provided");
      if (employerOcrStatus === "checking") errs.push("Employer ID verification is still in progress");
      if (employerOcrStatus === "mismatch") errs.push(`Employer name does not match Emirates ID document. Extracted: ${employerOcrExtracted || "unreadable"}`);
      if (employerOcrDates.id_number && employerEmiratesId) {
        if (employerEmiratesId.replace(/\D/g, "") !== employerOcrDates.id_number.replace(/\D/g, "")) {
          errs.push(`Employer Emirates ID number entered does not match document (${employerOcrDates.id_number})`);
        }
      }
    }*/

    // Shareholders
    if (shareholders.length === 0) errs.push("At least one shareholder is required");
    let totalCapital = 0;
    shareholders.forEach((sh, i) => {
      if (!sh.name.trim()) errs.push(`Shareholder ${i + 1}: Full name required`);
      if (!sh.capital) errs.push(`Shareholder ${i + 1}: Capital % required`);
      if (!sh.nationality.trim()) errs.push(`Shareholder ${i + 1}: Nationality required`);
      if (!sh.dob_place.trim()) errs.push(`Shareholder ${i + 1}: Date and place of birth required`);
      if (!sh.emirates_id || sh.emirates_id.length !== 15) errs.push(`Shareholder ${i + 1}: Emirates ID must be 15 digits`);
      if (!sh.address.trim()) errs.push(`Shareholder ${i + 1}: Address required`);
      if (sh.id_files.length === 0) errs.push(`Shareholder ${i + 1}: Emirates ID document required`);
      if (sh.passport_files.length === 0) errs.push(`Shareholder ${i + 1}: Passport document required`);
      if (isBlacklisted(sh.nationality)) errs.push(`⚠️ SUSPENDED: Shareholder ${i + 1} nationality does not align with our compliance framework`);
      if (sh.ocr_status === "checking") errs.push(`Shareholder ${i + 1}: ID verification still in progress, please wait`);
      if (sh.ocr_status === "mismatch") errs.push(`Shareholder ${i + 1}: Name does not match Emirates ID document`);
      if (sh.ocr_dates?.id_number && sh.emirates_id) {
        if (sh.emirates_id.replace(/\D/g, "") !== sh.ocr_dates.id_number.replace(/\D/g, "")) {
          errs.push(`Shareholder ${i + 1}: Emirates ID number (${sh.emirates_id}) does not match document (${sh.ocr_dates.id_number})`);
        }
      }
      totalCapital += parseFloat(sh.capital) || 0;
    });
    if (shareholders.length > 0 && Math.abs(totalCapital - 100) > 0.01) {
      errs.push(`Total shareholder capital must equal 100% (currently ${totalCapital.toFixed(2)}%)`);
    }

    // UBOs
    if (hasUbo === "yes") {
      if (ubos.length === 0) errs.push("At least one UBO is required");
      ubos.forEach((u, i) => {
        if (!u.name.trim()) errs.push(`UBO ${i + 1}: Full name required`);
        if (!u.nationality.trim()) errs.push(`UBO ${i + 1}: Nationality required`);
        if (!u.dob_place.trim()) errs.push(`UBO ${i + 1}: Date and place of birth required`);
        if (!u.emirates_id || u.emirates_id.length !== 15) errs.push(`UBO ${i + 1}: Emirates ID must be 15 digits`);
        if (!u.address.trim()) errs.push(`UBO ${i + 1}: Address required`);
        if (u.id_files.length === 0) errs.push(`UBO ${i + 1}: Emirates ID document required`);
        if (u.passport_files.length === 0) errs.push(`UBO ${i + 1}: Passport document required`);
        if (isBlacklisted(u.nationality)) errs.push(`⚠️ SUSPENDED: UBO ${i + 1} nationality does not align with our compliance framework`);
        if (u.ocr_status === "checking") errs.push(`UBO ${i + 1}: ID verification still in progress, please wait`);
        if (u.ocr_status === "mismatch") errs.push(`UBO ${i + 1}: Name does not match Emirates ID document`);
        if (u.ocr_dates?.id_number && u.emirates_id) {
          if (u.emirates_id.replace(/\D/g, "") !== u.ocr_dates.id_number.replace(/\D/g, "")) {
            errs.push(`UBO ${i + 1}: Emirates ID number (${u.emirates_id}) does not match document (${u.ocr_dates.id_number})`);
          }
        }
      });
    }

    // Management
    if (!managementSelect) errs.push("Please select who is responsible for management");
    if (managementSelect === "Other") {
      managers.forEach((m, i) => {
        if (!m.name.trim()) errs.push(`Manager ${i + 1}: Name required`);
        if (m.ocr_status === "mismatch") errs.push(`Manager ${i + 1}: Name does not match Emirates ID`);
      });
      if (poaFiles.length === 0) errs.push("Power of Attorney (POA) document is required");
    }

    // PEP
    if (hasPep === "yes") {
      if (selectedPepNames.length === 0) errs.push("Please select at least one PEP person");
      selectedPepNames.forEach((name) => {
        const decl = pepDeclarations[name];
        if (!decl) { errs.push(`PEP (${name}): Declaration required`); return; }
        if ((decl.sourceOfFunds ?? []).length === 0) errs.push(`PEP (${name}): Source of Funds required`);
        if (decl.sourceOfFunds?.includes("Other") && !decl.otherSource?.trim()) errs.push(`PEP (${name}): Please specify other source of funds`);
        //if (!decl.wealthSummary?.trim()) errs.push(`PEP (${name}): Brief Summary of Source of Wealth required`);
        if (!decl.confirmed) errs.push(`PEP (${name}): Must confirm the declaration`);
      });
    }

    // Declarations
    DECLARATIONS.forEach((_, i) => {
      if (!declarations[i]) errs.push(`You must confirm declaration number ${i + 1}`);
    });

    return errs;
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const errs = validate();
    if (errs.length > 0) {
      toast.error(errs[0] + (errs.length > 1 ? ` (+${errs.length - 1} more)` : ""));
      return;
    }
    setBusy(true);
    try {

    // ── Sanctions screening BEFORE upload/save ───────────────────────────
    const pepPersons = [...shareholders, ...ubos, ...managers].filter(p => selectedPepNames.includes(p.name));
    const personsToScreen = [
      { name: entityName, role: "Owner / Entity" },
      ...shareholders.map((s, i) => ({ name: s.name, role: `Shareholder ${i + 1}`, nationality: s.nationality, emirates_id: s.emirates_id })),
      ...(hasUbo === "yes" ? ubos.map((u, i) => ({ name: u.name, role: `UBO ${i + 1}`, nationality: u.nationality, emirates_id: u.emirates_id })) : []),
      ...(managementSelect === "Other" ? managers.map((m, i) => ({ name: m.name, role: `Manager ${i + 1}`, nationality: m.nationality, emirates_id: m.emirates_id })) : []),
      ...(hasPep === "yes" ? pepPersons.map((p, i) => ({ name: p.name, role: `PEP ${i + 1}`, nationality: p.nationality, emirates_id: p.emirates_id })) : []),
    ].filter((p) => p.name?.trim());

    try {
      const { data: scr } = await supabase.functions.invoke("sanctions-screen", {
        body: { persons: personsToScreen, entityId: entity.id, entityName },
      });
      if (scr?.matches?.length > 0) {
        const names = scr.matches.map((m: any) => `${m.person_name} (${m.person_role}) ↔ ${m.matched_english}`).join("\n• ");
        toast.error(
          `🚨 SANCTIONS MATCH — Application rejected.\nThe following person(s) appear on the sanctions list:\n• ${names}\n\nCompliance team has been notified.`,
          { duration: 12000 }
        );
        return;
      }
    } catch (err) {
      console.error("Sanctions screening failed:", err);
    }

    // Upload license
    const licensePaths = licenseFiles.length > 0 ? await uploadFiles(licenseFiles, "trade", "trade_license") : [];

    // Upload shareholder docs
    for (const sh of shareholders) {
      if (sh.id_files.length > 0) await uploadFiles(sh.id_files, `shareholders/${sh.name}/eid`, "emirates_id");
      if (sh.passport_files.length > 0) await uploadFiles(sh.passport_files, `shareholders/${sh.name}/passport`, "passport");
    }

    // Upload UBO docs
    for (const u of ubos) {
      if (u.id_files.length > 0) await uploadFiles(u.id_files, `ubos/${u.name}/eid`, "emirates_id");
      if (u.passport_files.length > 0) await uploadFiles(u.passport_files, `ubos/${u.name}/passport`, "passport");
    }

    // PEP — no extra uploads needed (persons already uploaded in their sections)

    // Upload Employer ID doc
    if (employerIdFiles.length > 0) await uploadFiles(employerIdFiles, `employer/${employerName || "unknown"}/eid`, "emirates_id");

    // Upload POA
    if (poaFiles.length > 0) await uploadFiles(poaFiles, "poa", "authorization_letter");

    const stripPerson = ({ id_files, passport_files, ocr_status, ocr_extracted, ocr_dates, ...rest }: Person) => rest;

    const payload: any = {
      entity_name: entityName,
      registration_status: registrationStatus,
      legal_type: legalType,
      license_number: licenseNumber,
      license_issue_date: licenseDate || null,
      principal_activity: principalActivity,
      main_activity: principalActivity,
      economic_sector: economicSector,
      emirate,
      address,
      telephone,
      email,
      source_of_funds: sourceOfFunds,
      employer_name: employerName,
      employer_emirates_id: employerEmiratesId || null,
      shareholders: shareholders.map(stripPerson),
      ubos: hasUbo === "yes" ? ubos.map(stripPerson) : [],
      pep_exists: hasPep === "yes",
      pep_persons: hasPep === "yes" ? selectedPepNames.map(name => ({
        name,
        ...pepDeclarations[name],
      })) : [],
      management_control: managementSelect,
      declarations_signed: declarations,
      current_step: 2,
    };

    const { data, error } = await supabase.from("entities").update(payload).eq("id", entity.id).select().single();
    if (error) { toast.error(error.message); return; }
    toast.success("Saved successfully");
    onSaved(data);

    } catch (err: any) {
      console.error("Submit error:", err);
      toast.error(err?.message ?? "حدث خطأ، يرجى المحاولة مجدداً");
    } finally {
      setBusy(false);
    }
  };
  const normalize = (s: string) => s.toLowerCase().replace(/[\s\-_.\/]/g, "");
  return (
    <Card className="shadow-card">
      {previewFile && <DocPreview file={previewFile} onClose={() => setPreviewFile(null)} />}
      <CardHeader>
        <CardTitle className="text-xl uppercase tracking-wide text-center border-b border-border pb-4">
          Entity Onboarding
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-2">

          {/* ── SECTION 1: Entity Information ── */}
          <SectionTitle number={1} title="Entity Information" />
          <div className="space-y-4">
            <Field label="Entity Registration Status" required>
              <NativeSelect required value={registrationStatus} onChange={(e) => setRegistrationStatus(e.target.value)}>
                <option value="">-- Select --</option>
                <option value="unlicensed">Unlicensed Entity</option>
                <option value="sole">Mainland Licensed Entity - Sole Owner</option>
                <option value="multiple">Mainland Licensed Entity - Multiple Owners</option>
                <option value="freezone">Free Zone Licensed Entity</option>
                <option value="branch">Mainland Licensed Entity - Branch</option>
              </NativeSelect>
            </Field>

            <Field label={isUnlicensed || isSole ? "Owner Name" : "Company Name"} required>
              <Input required value={entityName} placeholder="Enter name" onChange={(e) => setEntityName(e.target.value)} />
            </Field>

            {(isUnlicensed || isSole) && (
              <Field label="Source of Funds" required>
                <Input required value={sourceOfFunds} placeholder="e.g., Salary, Business, Investment" onChange={(e) => setSourceOfFunds(e.target.value)} />
              </Field>
            )}

            {(isUnlicensed || isSole) && (
              <>
                <Field label="Name of Employer" required>
                  <Input required value={employerName} placeholder="e.g., Employer name, Self employed" onChange={(e) => setEmployerName(e.target.value)} />
                </Field>
                
                
              </>
            )}

            {isLicensed && (
              <div className="space-y-4 border border-border rounded-lg p-4 bg-muted/10">
                <Field label="License Number" required>
                  <Input required value={licenseNumber} placeholder="Enter license number" onChange={(e) => setLicenseNumber(e.target.value)} />
                </Field>
                <Field label="License Issue Date" required>
                  <Input required type="date" value={licenseDate} onChange={(e) => setLicenseDate(e.target.value)} />
                </Field>
                {legalTypeOptions[registrationStatus] && (
                  <Field label="Legal Structure">
                    <NativeSelect value={legalType} onChange={(e) => setLegalType(e.target.value)}>
                      <option value="">-- Select --</option>
                      {legalTypeOptions[registrationStatus].map((o) => <option key={o}>{o}</option>)}
                    </NativeSelect>
                  </Field>
                )}
                <div className="space-y-2">
                  <FileUploadZone
                    label="Upload Trade License *"
                    files={licenseFiles}
                    onChange={(files) => {
                      setLicenseFiles(files);
                      setLicenseOcrStatus("idle");
                      setLicenseOcrDates({});
                    }}
                    accept="image/*,.pdf"
                    single
                  />
                  {licenseFiles.length > 0 && (
                  <div className="flex gap-2">
                    <Button
                      type="button" size="sm" variant="outline" className="flex-1"
                      disabled={licenseOcrStatus === "checking"}
                      onClick={async () => {
                        if (!entityName.trim()) { toast.error("Please enter company name first"); return; }
                        setLicenseOcrStatus("checking");
                        const result = await verifyWithOCR(licenseFiles[0], entityName, "license");
                        setLicenseOcrStatus(result.match ? "match" : "mismatch");
                        setLicenseOcrExtracted(result.extractedName);
                        setLicenseOcrDates(result.dates);
                      }}
                    >
                      {licenseOcrStatus === "checking"
                        ? <><Loader2 className="size-3.5 animate-spin mr-1" /> Verifying License...</>
                        : <><CheckCircle2 className="size-3.5 mr-1" /> Verify Trade License</>}
                    </Button>
                    <Button
                      type="button" size="sm" variant="outline"
                      title="Preview Document"
                      onClick={() => setPreviewFile(licenseFiles[0])}
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </Button>
                  </div>
                )}
                  <OcrBadge status={licenseOcrStatus} extractedName={licenseOcrExtracted} />
                  {Object.keys(licenseOcrDates).length > 0 && (
                  <div className="text-xs bg-muted/40 border border-border rounded-lg p-2 space-y-1">
                    {/* مقارنة رقم الرخصة */}
                    {licenseOcrDates.license_number && (
                      <div className="flex items-center gap-2">
                        <span>🔢 License No:</span>
                        <span className="font-semibold">{licenseOcrDates.license_number}</span>
                        {licenseNumber.trim() && (
                          normalize(licenseNumber) === normalize(licenseOcrDates.license_number)
                            ? <span className="text-green-600 font-semibold">✅ Matches</span>
                            : <span className="text-destructive font-semibold">⚠️ Entered: {licenseNumber}</span>
                        )}
                      </div>
                    )}
                    {/* مقارنة تاريخ الإصدار */}
                    {licenseOcrDates.issue_date && (
                      <div className="flex items-center gap-2">
                        <span>📅 Issue Date:</span>
                        <span className="font-semibold">{licenseOcrDates.issue_date}</span>
                        {licenseDate && (
                          (() => {
                            const fromDoc = licenseOcrDates.issue_date.split("/").reverse().join("-");
                            return fromDoc === licenseDate
                              ? <span className="text-green-600 font-semibold">✅ Matches</span>
                              : <span className="text-destructive font-semibold">⚠️ Entered: {licenseDate}</span>;
                          })()
                        )}
                      </div>
                    )}
                    {/* تاريخ الانتهاء */}
                    {licenseOcrDates.expiry_date && (
                      <div className="flex items-center gap-2">
                        <span>⏳ Expiry Date:</span>
                        <span className="font-semibold">{licenseOcrDates.expiry_date}</span>
                        {(() => {
                          const [d, m, y] = licenseOcrDates.expiry_date.split("/");
                          const expiry = new Date(`${y}-${m}-${d}`);
                          const isExpired = expiry < new Date();
                          return isExpired
                            ? <span className="text-destructive font-semibold">⚠️ EXPIRED</span>
                            : <span className="text-green-600 font-semibold">✅ Valid</span>;
                        })()}
                      </div>
                    )}
                    {licenseOcrDates.legal_type && <div>🏢 Legal Type: <span className="font-semibold">{licenseOcrDates.legal_type}</span></div>}
                  </div>
                )}
                  {licenseOcrStatus === "mismatch" && (
                    <div className="text-sm text-destructive bg-destructive/10 border border-destructive/30 rounded-lg p-3 space-y-1">
                      <div className="font-semibold">⚠️ License Name Mismatch — Cannot Proceed</div>
                      <div>Name you entered: <span className="font-bold">{entityName}</span></div>
                      <div>Name on License: <span className="font-bold">{licenseOcrExtracted || "Could not be read"}</span></div>
                      <div className="text-xs text-muted-foreground mt-1">Please correct the company name to match the license.</div>
                    </div>
                  )}
                </div>
              </div>
            )}

            <Field label="Principal Activity" required>
              <Input required value={principalActivity} placeholder="Enter principal activity" onChange={(e) => setPrincipalActivity(e.target.value)} />
            </Field>

            <Field label="Economic Sector" required>
              <NativeSelect required value={economicSector} onChange={(e) => setEconomicSector(e.target.value)}>
                <option value="">-- Select --</option>
                {["Agriculture, Forestry & Fishing","Mining & Quarrying","Manufacturing","Energy","Construction, Engineering & Machinery","Transportation & Logistics","Technology & Telecom","Real Estate & Facility Services","Education","Health Care","Hospitality","Professional Services","Personal & Community Services","Media","Support Services","General Trading","Tourism & Travel Services","Other"].map((s) => <option key={s} value={s}>{s}</option>)}
              </NativeSelect>
            </Field>
          </div>

          {/* ── SECTION 2: Contact Details ── */}
          <SectionTitle number={2} title="Contact Details" />
          <div className="grid md:grid-cols-2 gap-4">
            <Field label="Emirate" required>
              <NativeSelect required value={emirate} onChange={(e) => setEmirate(e.target.value)}>
                <option value="">-- Select Emirate --</option>
                {["Abu Dhabi","Dubai","Sharjah","Ajman","Ras Al Khaimah","Fujairah","Umm Al Quwain"].map((e) => <option key={e}>{e}</option>)}
              </NativeSelect>
            </Field>
            <Field label="Telephone Number" required>
              <Input required value={telephone} placeholder="+971 xx xxx xxxx" onChange={(e) => setTelephone(e.target.value)} />
            </Field>
            <div className="md:col-span-2">
              <Field label="Address" required>
                <Textarea required value={address} placeholder="Enter full address" onChange={(e) => setAddress(e.target.value)} />
              </Field>
            </div>
            <div className="md:col-span-2">
              <Field label="Email" required>
                <Input required type="email" value={email} placeholder="Enter email address" onChange={(e) => setEmail(e.target.value)} />
              </Field>
            </div>
          </div>

          {/* ── SECTION 3: Shareholders ── */}
          <SectionTitle number={3} title="Shareholders / Proprietors" />
          <div className="space-y-4">
            {shareholders.map((sh, i) => (
              <PersonCard
                key={i} person={sh} index={i} label="Shareholder"
                showCapital canRemove={shareholders.length > 1}
                onChange={(p) => setShareholders(shareholders.map((s, idx) => idx === i ? p : s))}
                onRemove={() => setShareholders(shareholders.filter((_, idx) => idx !== i))}
              />
            ))}
            {/* Capital total */}
            {shareholders.length > 1 && (
              <div className="text-sm font-medium text-right">
                Total Capital: {shareholders.reduce((a, s) => a + (parseFloat(s.capital) || 0), 0).toFixed(2)}%
                {Math.abs(shareholders.reduce((a, s) => a + (parseFloat(s.capital) || 0), 0) - 100) > 0.01 && (
                  <span className="text-destructive ms-2">⚠️ Must equal 100%</span>
                )}
              </div>
            )}
            <Button type="button" variant="outline" size="sm" onClick={() => setShareholders([...shareholders, emptyPerson()])}>
              <Plus className="size-3.5" /> Add Shareholder
            </Button>
          </div>

          {/* ── SECTION 4: Beneficial Owners ── */}
          <SectionTitle number={4} title="Beneficial Owners" />
          <div className="space-y-4">
            <Field label="Is there any other individual who directly or indirectly owns 25% or more of the capital or has power to exercise significant influence?" required>

              <div className="flex gap-6 pt-1">
                <label className="flex items-center gap-2 cursor-pointer text-sm font-medium">
                  <input type="radio" name="ubo" value="yes" checked={hasUbo === "yes"} onChange={() => { setHasUbo("yes"); if (ubos.length === 0) setUbos([emptyPerson()]); }} />
                  Yes
                </label>
                <label className="flex items-center gap-2 cursor-pointer text-sm font-medium">
                  <input type="radio" name="ubo" value="no" checked={hasUbo === "no"} onChange={() => { setHasUbo("no"); setUbos([]); }} />
                  No
                </label>
              </div>
            </Field>
            {hasUbo === "yes" && (
              <>
                {ubos.map((u, i) => (
                  <PersonCard
                    key={i} person={u} index={i} label="Beneficial Owner"
                    canRemove={ubos.length > 1}
                    onChange={(p) => setUbos(ubos.map((x, idx) => idx === i ? p : x))}
                    onRemove={() => setUbos(ubos.filter((_, idx) => idx !== i))}
                  />
                ))}
                {ubos.length < 4 && (
                  <Button type="button" variant="outline" size="sm" onClick={() => setUbos([...ubos, emptyPerson()])}>
                    <Plus className="size-3.5" /> Add Beneficial Owner
                  </Button>
                )}
              </>
            )}
          </div>

          {/* ── SECTION 5: Management ── */}
          <SectionTitle number={5} title="Management & Effective Control" />
          <div className="space-y-4">
            <Field label="Who is responsible for management and effective control?" required>
              <NativeSelect required value={managementSelect} onChange={(e) => setManagementSelect(e.target.value)}>
                <option value="">-- Select --</option>
                {managementOptions.map((o) => <option key={o} value={o}>{o}</option>)}
              </NativeSelect>
            </Field>
            {managementSelect === "Other" && (
              <>
                {managers.map((m, i) => (
                  <PersonCard
                    key={i} person={m} index={i} label="Manager"
                    canRemove={managers.length > 1}
                    onChange={(p) => setManagers(managers.map((x, idx) => idx === i ? p : x))}
                    onRemove={() => setManagers(managers.filter((_, idx) => idx !== i))}
                  />
                ))}
                <Button type="button" variant="outline" size="sm" onClick={() => setManagers([...managers, emptyPerson()])}>
                  <Plus className="size-3.5" /> Add Manager
                </Button>
                <FileUploadZone label="Upload Power of Attorney (POA) *" files={poaFiles} onChange={setPoaFiles} accept="image/*,.pdf" single />
                  {poaFiles.length > 0 && (
                    <Button
                      type="button" size="sm" variant="outline"
                      title="Preview POA Document"
                      onClick={() => setPreviewFile(poaFiles[0])}
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" className="size-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      Preview POA
                    </Button>
                  )}
              </>
            )}
          </div>

          {/* ── SECTION 6: PEP ── */}
          <SectionTitle number={6} title="Politically Exposed Persons (PEP)" />
          <div className="space-y-4">
            <Field label="Is any person classified as a Politically Exposed Person (PEP), locally or internationally, or closely related to a PEP?" required>

              <div className="flex gap-6 pt-1">
                <label className="flex items-center gap-2 cursor-pointer text-sm font-medium">
                  <input type="radio" name="pep" value="yes" checked={hasPep === "yes"} onChange={() => setHasPep("yes")} />
                  Yes
                </label>
                <label className="flex items-center gap-2 cursor-pointer text-sm font-medium">
                  <input type="radio" name="pep" value="no" checked={hasPep === "no"} onChange={() => { setHasPep("no"); setSelectedPepNames([]); }} />
                  No
                </label>
              </div>
            </Field>

            {hasPep === "yes" && (() => {
              // جمع كل الأشخاص المدخلين مسبقاً
              const allPersons = [
                ...shareholders.map(s => ({ name: s.name, role: "Shareholder" })),
                ...ubos.map(u => ({ name: u.name, role: "UBO" })),
                ...managers.map(m => ({ name: m.name, role: "Manager" })),
              ].filter(p => p.name.trim());

              const updateDecl = (name: string, key: keyof PepDeclaration, value: any) => {
                setPepDeclarations(prev => ({
                  ...prev,
                  [name]: { sourceOfFunds: [], otherSource: "", wealthSummary: "", confirmed: false, ...prev[name], [key]: value },
                }));
              };

              const toggleSof = (name: string, value: string) => {
                const current = pepDeclarations[name]?.sourceOfFunds ?? [];
                const updated = current.includes(value) ? current.filter(v => v !== value) : [...current, value];
                updateDecl(name, "sourceOfFunds", updated);
              };

              return (
                <div className="space-y-4">
                  {/* اختيار الأشخاص */}
                  <div className="border border-border rounded-lg p-4 bg-muted/10">
                    <div className="text-sm font-semibold mb-3">Select PEP Person(s) from previously entered persons:</div>
                    {allPersons.length === 0 ? (
                      <div className="text-sm text-muted-foreground">No persons entered yet. Please fill Shareholders / UBOs / Management sections first.</div>
                    ) : (
                      <div className="space-y-2">
                        {allPersons.map(({ name, role }) => (
                          <label key={name} className="flex items-center gap-3 cursor-pointer text-sm p-2 rounded hover:bg-muted/30">
                            <input
                              type="checkbox"
                              checked={selectedPepNames.includes(name)}
                              onChange={(e) => {
                                if (e.target.checked) {
                                  setSelectedPepNames(prev => [...prev, name]);
                                  setPepDeclarations(prev => ({
                                    ...prev,
                                    [name]: prev[name] ?? { sourceOfFunds: [], otherSource: "", wealthSummary: "", confirmed: false },
                                  }));
                                } else {
                                  setSelectedPepNames(prev => prev.filter(n => n !== name));
                                }
                              }}
                              className="size-4"
                            />
                            <span className="font-medium">{name}</span>
                            <span className="text-muted-foreground text-xs">({role})</span>
                          </label>
                        ))}
                      </div>
                    )}
                  </div>

                  {/* نموذج الإقرار لكل شخص مختار */}
                  {selectedPepNames.map((name) => (
                    <div key={name} className="border border-primary/30 rounded-xl p-5 space-y-4 bg-primary/5">
                      <div className="font-semibold text-sm border-b border-border pb-2">
                        📋 Declaration of Source of Funds — <span className="text-primary">{name}</span>
                      </div>

                      {/* Source of Funds */}
                      <div className="space-y-2">
                        <div className="text-sm font-semibold">Source of Funds (SOF) *</div>
                        <div className="text-xs text-muted-foreground mb-2">The funds used for my capital share in the above-mentioned entity originated from:</div>
                        <div className="grid grid-cols-2 gap-2">
                          {["Personal Savings", "Salary/Employment Income", "Sale of Asset", "Business Profits", "Other"].map(opt => (
                            <label key={opt} className="flex items-center gap-2 text-sm cursor-pointer">
                              <input
                                type="checkbox"
                                checked={(pepDeclarations[name]?.sourceOfFunds ?? []).includes(opt)}
                                onChange={() => toggleSof(name, opt)}
                                className="size-4"
                              />
                              {opt}
                            </label>
                          ))}
                        </div>
                        {(pepDeclarations[name]?.sourceOfFunds ?? []).includes("Other") && (
                          <Input
                            placeholder="Please specify..."
                            value={pepDeclarations[name]?.otherSource ?? ""}
                            onChange={(e) => updateDecl(name, "otherSource", e.target.value)}
                            className="mt-1"
                          />
                        )}
                      </div>

                      {/* Source of Wealth */}
                      /*<div className="space-y-2">
                        <div className="text-sm font-semibold">Brief Summary of Source of Wealth (SOW) *</div>
                        <textarea
                          className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                          placeholder="Briefly describe your source of wealth..."
                          value={pepDeclarations[name]?.wealthSummary ?? ""}
                          onChange={(e) => updateDecl(name, "wealthSummary", e.target.value)}
                        />
                      </div>*/

                      {/* Confirmation */}
                      <label className={`flex items-start gap-3 cursor-pointer border rounded-lg p-3 transition-colors ${pepDeclarations[name]?.confirmed ? "border-green-300 bg-green-50 dark:bg-green-950/20" : "border-border"}`}>
                        <input
                          type="checkbox"
                          checked={pepDeclarations[name]?.confirmed ?? false}
                          onChange={(e) => updateDecl(name, "confirmed", e.target.checked)}
                          className="size-4 mt-0.5"
                        />
                        <span className="text-xs text-muted-foreground leading-relaxed">
                          I, <strong>{name}</strong>, As the Authorized Signatory of this entity, I hereby declare that the information provided above regarding Politically Exposed Persons (PEP) and their Source of Funds and Wealth is true and accurate. I confirm that these funds are derived from legitimate sources and are not related to any illegal activities, and I undertake to provide any supporting documentation upon request.
                        </span>
                      </label>
                    </div>
                  ))}
                </div>
              );
            })()}
          </div>

          {/* ── SECTION 7: Compliance & Legal Declarations ── */}
          <SectionTitle number={7} title="Compliance & Legal Declarations" />
          <div className="space-y-3">
            {DECLARATIONS.map((text, i) => (
              <div
                key={i}
                className={`border rounded-lg p-4 flex gap-4 items-start transition-colors ${declarations[i] ? "border-green-300 bg-green-50 dark:bg-green-950/20" : "border-border bg-card"}`}
              >
                <div className="flex-1 text-sm leading-relaxed text-foreground">
                  <span className="font-semibold text-muted-foreground me-2">{i + 1}.</span>
                  {text}
                </div>
                <label className="flex items-center gap-2 shrink-0 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={declarations[i]}
                    onChange={(e) => setDeclarations(declarations.map((d, idx) => idx === i ? e.target.checked : d))}
                    className="size-4 rounded"
                  />
                  <span className="text-sm font-medium">Confirm</span>
                </label>
              </div>
            ))}
            <div className="text-xs text-muted-foreground text-center pt-2">
              {declarations.filter(Boolean).length} / {DECLARATIONS.length} confirmed
            </div>
          </div>

          {/* Submit */}
          <div className="flex justify-end pt-6 border-t border-border">
            <Button type="submit" variant="premium" disabled={busy} size="lg">
              {busy ? <><Loader2 className="size-4 animate-spin" /> Saving...</> : "Save & Continue →"}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}

// ═══════════════════════════════════════════════════════════════════
// STEP 2 — Audit Fee
// ═══════════════════════════════════════════════════════════════════
function calculateAuditFee(turnover: number): number {
  if (turnover <= 100000) return 0;
  if (turnover <= 250000) return 1500;
  if (turnover <= 500000) return 2000;
  if (turnover <= 1000000) return 2500;
  if (turnover <= 2000000) return 3000;
  if (turnover <= 5000000) return 4000;
  if (turnover <= 10000000) return 5000;
  if (turnover <= 20000000) return 7000;
  if (turnover <= 30000000) return 9000;
  if (turnover <= 50000000) return 12000;
  return 12000;
}

function AuditFeeForm({ entity, onSaved, onBack, t }: any) {
  const fee = calculateAuditFee(entity.total_turnover ?? 0);
  const [agreed, setAgreed] = useState(false);
  const [signerName, setSignerName] = useState("");
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!agreed) return toast.error("Please agree to the audit fee");
    if (!signerName.trim()) return toast.error("Signer name is required");
    setBusy(true);
    await supabase.from("audit_fees").upsert({
      entity_id: entity.id, user_id: entity.user_id,
      turnover: entity.total_turnover, calculated_fee: fee, agreed: true,
      digital_signature_name: signerName,
      digital_signature_date: new Date().toISOString(),
    }, { onConflict: "entity_id" });
    await supabase.from("entities").update({ current_step: 3 }).eq("id", entity.id);
    setBusy(false);
    toast.success(t("saved"));
    onSaved();
  };

  return (
    <Card className="shadow-card">
      <CardHeader><CardTitle>Audit Fee Acknowledgement</CardTitle></CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-6">
          <div className="rounded-xl border border-border bg-muted/20 p-5 space-y-3">
            <div className="text-sm text-muted-foreground">Total Turnover</div>
            <div className="text-2xl font-bold">AED {(entity.total_turnover ?? 0).toLocaleString()}</div>
            <div className="h-px bg-border" />
            <div className="text-sm text-muted-foreground">Calculated Audit Fee</div>
            <div className="text-3xl font-bold text-primary">AED {fee.toLocaleString()}</div>
          </div>
          <Field label="Digital Signer Name *">
            <Input required value={signerName} placeholder="Full name as on Emirates ID" onChange={(e) => setSignerName(e.target.value)} />
          </Field>
          <label className="flex items-start gap-3 text-sm cursor-pointer border border-border rounded-lg p-4">
            <input type="checkbox" checked={agreed} onChange={(e) => setAgreed(e.target.checked)} className="mt-0.5 size-4" />
            <span>I agree to the audit fee of AED {fee.toLocaleString()} and the payment terms stated above.</span>
          </label>
          <div className="flex justify-between pt-4 border-t border-border">
            <Button type="button" variant="outline" onClick={onBack}>{t("btn_back")}</Button>
            <Button type="submit" variant="premium" disabled={busy || !agreed}>
              {busy ? t("saving") : t("btn_next")}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}

// ═══════════════════════════════════════════════════════════════════
// STEP 3 — Financial Year
// ═══════════════════════════════════════════════════════════════════
function FinancialYearForm({ entity, onSaved, onBack, t }: any) {
  const [form, setForm] = useState({
    is_first_year: entity.is_first_year ?? "",
    first_year_start: entity.first_year_start ?? "",
    first_year_end: entity.first_year_end ?? "",
    current_year_start: entity.current_year_start ?? "",
    current_year_end: entity.current_year_end ?? "",
    previous_year_audited: entity.previous_year_audited ?? "",
  });
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    await supabase.from("financial_years").upsert({ entity_id: entity.id, user_id: entity.user_id, ...form } as any, { onConflict: "entity_id" });
    await supabase.from("entities").update({ current_step: 4 }).eq("id", entity.id);
    setBusy(false);
    toast.success(t("saved"));
    onSaved();
  };

  const set = (k: string, v: string) => setForm({ ...form, [k]: v });

  return (
    <Card className="shadow-card">
      <CardHeader><CardTitle>Financial Year Details</CardTitle></CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-5">
          <Field label="Is this the first financial year?" required>
            <div className="flex gap-6 pt-1">
              {["Yes", "No"].map((v) => (
                <label key={v} className="flex items-center gap-2 cursor-pointer text-sm font-medium">
                  <input type="radio" name="first_year" value={v} checked={form.is_first_year === v} onChange={() => set("is_first_year", v)} />
                  {v}
                </label>
              ))}
            </div>
          </Field>
          {form.is_first_year === "Yes" && (
            <div className="grid md:grid-cols-2 gap-4">
              <Field label="First Year Start Date" required><Input required type="date" value={form.first_year_start} onChange={(e) => set("first_year_start", e.target.value)} /></Field>
              <Field label="First Year End Date" required><Input required type="date" value={form.first_year_end} onChange={(e) => set("first_year_end", e.target.value)} /></Field>
            </div>
          )}
          <div className="grid md:grid-cols-2 gap-4">
            <Field label="Current Year Start" required><Input required type="date" value={form.current_year_start} onChange={(e) => set("current_year_start", e.target.value)} /></Field>
            <Field label="Current Year End" required><Input required type="date" value={form.current_year_end} onChange={(e) => set("current_year_end", e.target.value)} /></Field>
          </div>
          {form.is_first_year === "No" && (
            <Field label="Was the previous year audited?" required>
              <div className="flex gap-6 pt-1">
                {["Yes", "No"].map((v) => (
                  <label key={v} className="flex items-center gap-2 cursor-pointer text-sm font-medium">
                    <input type="radio" name="prev_audited" value={v} checked={form.previous_year_audited === v} onChange={() => set("previous_year_audited", v)} />
                    {v}
                  </label>
                ))}
              </div>
            </Field>
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

// ═══════════════════════════════════════════════════════════════════
// STEP 4 — Tax Status
// ═══════════════════════════════════════════════════════════════════
function TaxStatusForm({ entity, onSaved, onBack, t }: any) {
  const [form, setForm] = useState({
    vat_status: entity.vat_status ?? "",
    vat_number: entity.vat_number ?? "",
    not_registered_reason: entity.not_registered_reason ?? "",
    corporate_tax_status: entity.corporate_tax_status ?? "",
    corporate_tax_number: entity.corporate_tax_number ?? "",
    corporate_tax_treatment: entity.corporate_tax_treatment ?? "",
    small_business_relief: entity.small_business_relief ?? "",
    excise_status: entity.excise_status ?? "",
  });
  const [busy, setBusy] = useState(false);
  const set = (k: string, v: string) => setForm({ ...form, [k]: v });

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    await supabase.from("tax_status").upsert({ entity_id: entity.id, user_id: entity.user_id, ...form } as any, { onConflict: "entity_id" });
    await supabase.from("entities").update({ current_step: 5 }).eq("id", entity.id);
    setBusy(false);
    toast.success(t("saved"));
    onSaved();
  };

  return (
    <Card className="shadow-card">
      <CardHeader><CardTitle>Tax Status Disclosure</CardTitle></CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-5">
          <Field label="VAT Status" required>
            <NativeSelect required value={form.vat_status} onChange={(e) => set("vat_status", e.target.value)}>
              <option value="">-- Select --</option>
              <option value="registered">Registered</option>
              <option value="not_registered">Not Registered</option>
              <option value="exempt">Exempt</option>
            </NativeSelect>
          </Field>
          {form.vat_status === "registered" && (
            <Field label="VAT Registration Number"><Input value={form.vat_number} onChange={(e) => set("vat_number", e.target.value)} /></Field>
          )}
          {form.vat_status === "not_registered" && (
            <Field label="Reason for Non-Registration">
              <NativeSelect value={form.not_registered_reason} onChange={(e) => set("not_registered_reason", e.target.value)}>
                <option value="">-- Select --</option>
                <option value="below_threshold">Below Threshold</option>
                <option value="exempt_activity">Exempt Activity</option>
                <option value="other">Other</option>
              </NativeSelect>
            </Field>
          )}
          <Field label="Corporate Tax Status" required>
            <NativeSelect required value={form.corporate_tax_status} onChange={(e) => set("corporate_tax_status", e.target.value)}>
              <option value="">-- Select --</option>
              <option value="registered">Registered</option>
              <option value="not_registered">Not Registered</option>
              <option value="exempt">Exempt</option>
            </NativeSelect>
          </Field>
          {form.corporate_tax_status === "registered" && (
            <>
              <Field label="Corporate Tax Number"><Input value={form.corporate_tax_number} onChange={(e) => set("corporate_tax_number", e.target.value)} /></Field>
              <Field label="Tax Treatment">
                <NativeSelect value={form.corporate_tax_treatment} onChange={(e) => set("corporate_tax_treatment", e.target.value)}>
                  <option value="">-- Select --</option>
                  <option value="standard">Standard Rate (9%)</option>
                  <option value="free_zone_qualifying">Free Zone Qualifying Income (0%)</option>
                  <option value="small_business_relief">Small Business Relief</option>
                </NativeSelect>
              </Field>
              <Field label="Small Business Relief">
                <div className="flex gap-6 pt-1">
                  {["Yes", "No"].map((v) => (
                    <label key={v} className="flex items-center gap-2 cursor-pointer text-sm font-medium">
                      <input type="radio" name="sbr" value={v} checked={form.small_business_relief === v} onChange={() => set("small_business_relief", v)} />
                      {v}
                    </label>
                  ))}
                </div>
              </Field>
            </>
          )}
          <Field label="Excise Tax Status">
            <NativeSelect value={form.excise_status} onChange={(e) => set("excise_status", e.target.value)}>
              <option value="">-- Select --</option>
              <option value="registered">Registered</option>
              <option value="not_registered">Not Registered</option>
            </NativeSelect>
          </Field>
          <div className="flex justify-between pt-4 border-t border-border">
            <Button type="button" variant="outline" onClick={onBack}>{t("btn_back")}</Button>
            <Button type="submit" variant="premium" disabled={busy}>{busy ? t("saving") : t("btn_next")}</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}

// ═══════════════════════════════════════════════════════════════════
// STEP 5 — Engagement Letter
// ═══════════════════════════════════════════════════════════════════
function EngagementForm({ entity, onSaved, onBack, t }: any) {
  const { user } = useAuth();
  const [agreed, setAgreed] = useState(false);
  const [signerName, setSignerName] = useState("");
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!agreed) return toast.error("Please accept the engagement letter");
    if (!signerName.trim()) return toast.error("Signer name is required");
    setBusy(true);
    await supabase.from("engagement_letters").upsert({
      entity_id: entity.id, user_id: entity.user_id,
      accepted: true, accepted_at: new Date().toISOString(),
      letter_content: signerName,
    } as any, { onConflict: "entity_id" });
    await supabase.from("entities").update({
      application_status: "submitted",
      submitted_at: new Date().toISOString(),
      current_step: 6,
    }).eq("id", entity.id);
    await supabase.from("user_audit_logs").insert({
      user_id: user!.id, action: "application_submitted",
      description: `Submitted application for entity: ${entity.entity_name}`,
    });
    setBusy(false);
    toast.success("Application submitted successfully!");
    onSaved();
  };

  return (
    <Card className="shadow-card">
      <CardHeader><CardTitle>Engagement Letter Acceptance</CardTitle></CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-6">
          <div className="rounded-xl border border-border bg-muted/10 p-5 text-sm leading-relaxed space-y-3 max-h-64 overflow-y-auto">
            <p className="font-semibold">Terms of Engagement — Muhasba Accounting</p>
            <p>This engagement letter confirms the terms under which Muhasba Accounting LLC will provide audit and assurance services to <strong>{entity.entity_name}</strong>.</p>
            <p>The audit will be conducted in accordance with International Standards on Auditing (ISA) and UAE regulatory requirements. Our fees are as agreed in the Audit Fee Acknowledgement step.</p>
            <p>By accepting this letter, you confirm that all information provided is accurate and complete, and that the entity authorizes Muhasba Accounting LLC to proceed with the engagement.</p>
            <p className="text-muted-foreground text-xs">This submission will be authenticated via UAE PASS. An authentication request will be sent to the person responsible for the management and effective control of the entity.</p>
          </div>
          <Field label="Name of Authorized Signatory *">
            <Input required value={signerName} placeholder="Full name" onChange={(e) => setSignerName(e.target.value)} />
          </Field>
          <label className="flex items-start gap-3 text-sm cursor-pointer border border-border rounded-lg p-4">
            <input type="checkbox" checked={agreed} onChange={(e) => setAgreed(e.target.checked)} className="mt-0.5 size-4" />
            <span>I accept the terms of the engagement letter and confirm that all submitted information is accurate and complete.</span>
          </label>
          <div className="flex justify-between pt-4 border-t border-border">
            <Button type="button" variant="outline" onClick={onBack}>{t("btn_back")}</Button>
            <Button type="submit" variant="premium" disabled={busy || !agreed}>
              {busy ? "Submitting..." : "Submit Application"}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}

// ═══════════════════════════════════════════════════════════════════
// STEP 6 — Payment
// ═══════════════════════════════════════════════════════════════════
function PaymentForm({ entity, onBack, t }: any) {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [method, setMethod] = useState<"card"|"bank_transfer"|"apple_pay"|"google_pay"|"">("");
  const [busy, setBusy] = useState(false);
  const [paid, setPaid] = useState(entity.payment_status === "paid");
  const [cardForm, setCardForm] = useState({ number: "", expiry: "", cvv: "", name: "" });
  const [fee, setFee] = useState(0);

  useEffect(() => {
    supabase.from("audit_fees").select("calculated_fee").eq("entity_id", entity.id).maybeSingle()
      .then(({ data }) => setFee(data?.calculated_fee ?? 0));
  }, [entity.id]);

  const processPayment = async () => {
    if (!method) return toast.error("Please select a payment method");
    if (method === "card" && (!cardForm.number || !cardForm.expiry || !cardForm.cvv || !cardForm.name))
      return toast.error("Please fill all card details");
    setBusy(true);
    await new Promise((r) => setTimeout(r, 2000));
    const ref = `PAY-${entity.id.slice(0, 8).toUpperCase()}-${Date.now()}`;
    await supabase.from("payments").insert({
      entity_id: entity.id, user_id: user!.id,
      amount: fee, currency: "AED", status: "paid",
      method, reference: ref, paid_at: new Date().toISOString(),
    });
    await supabase.from("entities").update({ payment_status: "paid" }).eq("id", entity.id);
    setPaid(true);
    setBusy(false);
    toast.success(`Payment successful — Ref: ${ref}`);
  };

  if (paid) return (
    <Card className="shadow-card">
      <CardContent className="py-20 text-center space-y-5">
        <div className="size-20 rounded-full bg-green-100 dark:bg-green-900/30 grid place-items-center mx-auto">
          <CheckCircle2 className="size-10 text-green-600" />
        </div>
        <div className="text-2xl font-bold text-green-600">Payment Complete</div>
        <div className="text-muted-foreground">Your application has been submitted and paid successfully.</div>
        <Button variant="premium" onClick={() => navigate("/entities")}>Go to My Entities</Button>
      </CardContent>
    </Card>
  );

  return (
    <Card className="shadow-card">
      <CardHeader><CardTitle>Payment</CardTitle></CardHeader>
      <CardContent className="space-y-6">
        <div className="rounded-xl border border-border bg-muted/20 p-5">
          <div className="text-sm text-muted-foreground">Amount Due</div>
          <div className="text-4xl font-bold text-primary mt-1">
            {fee.toLocaleString()} <span className="text-base font-normal text-muted-foreground">AED</span>
          </div>
          <div className="text-xs text-muted-foreground mt-1">Audit fee — {entity.entity_name}</div>
        </div>

        <div className="grid grid-cols-2 gap-3">
          {(["card","bank_transfer","apple_pay","google_pay"] as const).map((m) => (
            <button key={m} type="button" onClick={() => setMethod(m)}
              className={`border-2 rounded-xl p-3 text-sm font-medium transition-colors ${method === m ? "border-primary bg-primary/5" : "border-border hover:border-primary/50"}`}>
              {m === "card" ? "💳 Credit / Debit Card" : m === "bank_transfer" ? "🏦 Bank Transfer" : m === "apple_pay" ? " Apple Pay" : " Google Pay"}
            </button>
          ))}
        </div>

        {method === "card" && (
          <div className="space-y-3 border border-border rounded-xl p-4">
            <Field label="Card Number"><Input value={cardForm.number} placeholder="XXXX XXXX XXXX XXXX" maxLength={19} onChange={(e) => setCardForm({ ...cardForm, number: e.target.value })} dir="ltr" /></Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Expiry (MM/YY)"><Input value={cardForm.expiry} placeholder="MM/YY" maxLength={5} onChange={(e) => setCardForm({ ...cardForm, expiry: e.target.value })} dir="ltr" /></Field>
              <Field label="CVV"><Input type="password" value={cardForm.cvv} placeholder="XXX" maxLength={4} onChange={(e) => setCardForm({ ...cardForm, cvv: e.target.value })} dir="ltr" /></Field>
            </div>
            <Field label="Cardholder Name"><Input value={cardForm.name} placeholder="NAME AS ON CARD" onChange={(e) => setCardForm({ ...cardForm, name: e.target.value.toUpperCase() })} dir="ltr" /></Field>
          </div>
        )}

        {method === "bank_transfer" && (
          <div className="rounded-xl border border-border bg-muted/10 p-4 space-y-2 text-sm">
            <div className="font-semibold">Bank Transfer Details</div>
            <div className="grid grid-cols-2 gap-1 text-xs">
              <span className="text-muted-foreground">Bank:</span><span>Emirates NBD</span>
              <span className="text-muted-foreground">Account Name:</span><span>Muhasba Accounting LLC</span>
              <span className="text-muted-foreground">IBAN:</span><span dir="ltr">AE07 0331 2345 6789 0123 456</span>
              <span className="text-muted-foreground">Reference:</span><span className="font-mono font-bold">{entity.engagement_number ?? entity.id.slice(0, 8).toUpperCase()}</span>
            </div>
          </div>
        )}

        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          🔒 All transactions are secured with SSL 256-bit encryption
        </div>

        <div className="flex justify-between pt-4 border-t border-border">
          <Button type="button" variant="outline" onClick={onBack}>{t("btn_back")}</Button>
          <Button variant="premium" disabled={busy || !method} onClick={processPayment}>
            {busy ? <><Loader2 className="size-4 animate-spin" /> Processing...</> : `Pay AED ${fee.toLocaleString()}`}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
