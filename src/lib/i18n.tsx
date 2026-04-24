import { createContext, useContext, useEffect, useState, type ReactNode } from "react";

export type Lang = "ar" | "en";

type Dict = Record<string, { ar: string; en: string }>;

export const dict: Dict = {
  // Navigation & app
  app_name: { ar: "محاسبة", en: "Muhasba" },
  app_tagline: {
    ar: "منصة المحاسبة والامتثال الذكية",
    en: "Smart Accounting & Compliance Platform",
  },
  nav_dashboard: { ar: "لوحة التحكم", en: "Dashboard" },
  nav_entities: { ar: "الكيانات", en: "Entities" },
  nav_new_kyc: { ar: "تطبيق جديد", en: "New Application" },
  nav_screening: { ar: "الفحص", en: "Screening" },
  nav_sanctions: { ar: "قائمة العقوبات", en: "Sanctions List" },
  nav_audits: { ar: "تدقيق الذكاء الاصطناعي", en: "AI Audits" },
  nav_logout: { ar: "تسجيل الخروج", en: "Logout" },

  // Dashboard
  dashboard_title: { ar: "لوحة التحكم", en: "Dashboard" },
  dashboard_subtitle: {
    ar: "نظرة عامة على الكيانات والامتثال",
    en: "Overview of entities and compliance",
  },
  card_submitted: { ar: "كيانات مُقدَّمة", en: "Submitted Entities" },
  card_approved: { ar: "كيانات معتمدة", en: "Approved Entities" },
  card_rejected: { ar: "كيانات مرفوضة", en: "Rejected Entities" },
  card_in_progress: { ar: "قيد التقدم", en: "In Progress" },
  view_all: { ar: "عرض الكل", en: "View all" },
  start_new_kyc: { ar: "ابدأ تطبيقًا جديدًا", en: "Start a new application" },
  recent_entities: { ar: "أحدث الكيانات", en: "Recent Entities" },
  no_entities: {
    ar: "لا توجد كيانات بعد. ابدأ بإضافة كيان جديد.",
    en: "No entities yet. Start by adding a new entity.",
  },

  // Auth
  auth_login: { ar: "تسجيل الدخول", en: "Login" },
  auth_signup: { ar: "إنشاء حساب", en: "Sign up" },
  auth_email: { ar: "البريد الإلكتروني", en: "Email" },
  auth_password: { ar: "كلمة المرور", en: "Password" },
  auth_full_name: { ar: "الاسم الكامل", en: "Full Name" },
  auth_no_account: { ar: "ليس لديك حساب؟", en: "No account?" },
  auth_have_account: { ar: "لديك حساب بالفعل؟", en: "Already have an account?" },
  auth_welcome_back: { ar: "أهلًا بعودتك", en: "Welcome back" },
  auth_create_account: { ar: "أنشئ حسابك", en: "Create your account" },

  // KYC steps
  kyc_step1: { ar: "اعرف عميلك (KYC)", en: "Know Your Customer (KYC)" },
  kyc_step2: { ar: "إقرار رسوم التدقيق", en: "Audit Fee Acknowledgement" },
  kyc_step3: { ar: "تفاصيل السنة المالية", en: "Financial Year Details" },
  kyc_step4: { ar: "إفصاح الوضع الضريبي", en: "Tax Status Disclosure" },
  kyc_step5: { ar: "قبول خطاب الارتباط", en: "Engagement Letter Acceptance" },
  status_completed: { ar: "مكتمل", en: "COMPLETED" },
  status_pending: { ar: "قيد التنفيذ", en: "PENDING" },
  status_not_started: { ar: "لم يبدأ", en: "NOT STARTED" },
  step_of: { ar: "من", en: "of" },

  // Step 0
  step0_welcome: { ar: "مرحبًا بك في عملية الإدخال", en: "Welcome to Client Onboarding" },
  step0_subtitle: { ar: "اختر نوع المستخدم لبدء العملية", en: "Select your user type to begin" },
  step0_new_client: { ar: "عميل جديد", en: "New Client" },
  step0_new_desc: { ar: "أبدأ ارتباطًا جديدًا", en: "Start a new engagement" },
  step0_returning: { ar: "عميل عائد", en: "Returning Client" },
  step0_returning_desc: {
    ar: "لدي ملف موجود وأرغب في الاستمرار",
    en: "I have an existing profile and want to continue",
  },
  step0_start_new: { ar: "ابدأ كعميل جديد", en: "Start as New Client" },
  step0_continue: { ar: "متابعة كعميل عائد", en: "Continue as Returning" },

  // KYC form
  kyc_business_registration: { ar: "حالة تسجيل النشاط", en: "Business Registration Status" },
  kyc_owner_name: { ar: "اسم الشركة / المالك", en: "Company / Owner Name" },
  kyc_license_number: { ar: "رقم الرخصة", en: "License Number" },
  kyc_issue_date: { ar: "تاريخ الإصدار", en: "Issue Date" },
  kyc_expiry_date: { ar: "تاريخ الانتهاء", en: "Expiry Date" },
  kyc_main_activity: { ar: "النشاط الرئيسي", en: "Main Activity" },
  kyc_emirate: { ar: "الإمارة", en: "Emirate" },
  kyc_address: { ar: "العنوان", en: "Address" },
  kyc_turnover: { ar: "إجمالي الإيرادات (درهم)", en: "Total Turnover (AED)" },
  kyc_shareholders: { ar: "المساهمون", en: "Shareholders" },
  kyc_ubos: { ar: "المالكون المنتفعون النهائيون (UBOs)", en: "Ultimate Beneficial Owners (UBOs)" },
  kyc_ubo_question: {
    ar: "هل يوجد مالك منتفع نهائي يختلف عن المساهمين؟",
    en: "Are there UBOs different from shareholders?",
  },
  kyc_management: { ar: "السيطرة الإدارية", en: "Management Control" },
  kyc_name: { ar: "الاسم", en: "Name" },
  kyc_capital: { ar: "نسبة رأس المال %", en: "Capital %" },
  kyc_nationality: { ar: "الجنسية", en: "Nationality" },
  kyc_emirates_id: { ar: "رقم الهوية الإماراتية", en: "Emirates ID" },
  kyc_pep: { ar: "شخصية معرضة سياسيًا (PEP)؟", en: "PEP?" },
  kyc_add_row: { ar: "+ إضافة سطر", en: "+ Add row" },
  kyc_remove: { ar: "حذف", en: "Remove" },
  kyc_person_n: { ar: "السجل رقم", en: "Entry #" },
  kyc_no_entries: { ar: "لا توجد سجلات بعد. اضغط \"إضافة سطر\" للبدء.", en: "No entries yet. Click \"Add row\" to start." },
  kyc_turnover_max: {
    ar: "نعتذر، الحد الأقصى لإجمالي الإيرادات هو 50,000,000 درهم. يرجى التواصل معنا لمناقشة الحالات الخاصة.",
    en: "We apologize, the maximum total turnover is AED 50,000,000. Please contact us to discuss special cases.",
  },
  kyc_documents: { ar: "المستندات", en: "Documents" },
  kyc_upload_id: { ar: "رفع الهوية / جواز السفر", en: "Upload ID / Passport" },
  kyc_upload_license: { ar: "رفع الرخصة التجارية", en: "Upload Trade License" },

  // Audit fee
  audit_fee_title: { ar: "إقرار رسوم التدقيق", en: "Audit Fee Acknowledgement" },
  audit_fee_desc: {
    ar: "يرجى مراجعة وقبول رسوم التدقيق لارتباطك",
    en: "Please review and acknowledge the audit fee for your engagement.",
  },
  audit_fee_calculated: { ar: "الرسوم المحتسبة", en: "Calculated Fee" },
  audit_fee_aed: { ar: "درهم إماراتي", en: "AED" },
  audit_fee_agree: {
    ar: "أوافق على رسوم التدقيق المذكورة أعلاه",
    en: "I agree to the audit fee stated above",
  },

  // Financial year
  fy_title: { ar: "تفاصيل السنة المالية", en: "Financial Year Details" },
  fy_first_question: {
    ar: "هل هذه أول قوائم مالية لكيانك؟",
    en: "Is this your entity's first financial statements?",
  },
  fy_first_start: { ar: "تاريخ بداية السنة الأولى", en: "First Year Start Date" },
  fy_first_end: { ar: "تاريخ نهاية السنة الأولى", en: "First Year End Date" },
  fy_current_start: { ar: "بداية السنة الحالية", en: "Current Year Start" },
  fy_current_end: { ar: "نهاية السنة الحالية", en: "Current Year End" },
  fy_previous_audited: {
    ar: "هل تم تدقيق السنة السابقة؟",
    en: "Was the previous year audited?",
  },

  // Tax
  tax_title: { ar: "إفصاح الوضع الضريبي", en: "Tax Status Disclosure" },
  tax_vat_status: { ar: "حالة ضريبة القيمة المضافة", en: "VAT Status" },
  tax_vat_number: { ar: "رقم تسجيل ضريبة القيمة المضافة", en: "VAT Registration Number" },
  tax_excise_status: { ar: "حالة الضريبة الانتقائية", en: "Excise Tax Status" },
  tax_corporate_status: { ar: "حالة ضريبة الشركات", en: "Corporate Tax Status" },
  tax_corporate_number: { ar: "رقم تسجيل ضريبة الشركات", en: "Corporate Tax Registration Number" },
  tax_corporate_treatment: { ar: "المعاملة الضريبية للشركات", en: "Corporate Tax Treatment" },
  tax_sbr: { ar: "إعفاء الأعمال الصغيرة", en: "Small Business Relief" },
  registered: { ar: "مسجل", en: "Registered" },
  not_registered: { ar: "غير مسجل", en: "Not Registered" },
  yes: { ar: "نعم", en: "Yes" },
  no: { ar: "لا", en: "No" },

  // Engagement letter
  engagement_title: { ar: "قبول خطاب الارتباط", en: "Engagement Letter Acceptance" },
  engagement_intro: {
    ar: "يرجى مراجعة خطاب الارتباط بعناية ثم تأكيد قبولك أدناه.",
    en: "Please review the engagement letter carefully and confirm your acceptance below.",
  },
  engagement_view: { ar: "عرض خطاب الارتباط", en: "View Engagement Letter" },
  engagement_accept: { ar: "أقبل شروط الارتباط", en: "I accept the engagement terms" },
  engagement_complete: { ar: "إتمام التطبيق", en: "Complete Application" },

  // Buttons
  btn_next: { ar: "التالي", en: "Next" },
  btn_back: { ar: "السابق", en: "Back" },
  btn_save: { ar: "حفظ", en: "Save" },
  btn_submit: { ar: "إرسال", en: "Submit" },
  btn_cancel: { ar: "إلغاء", en: "Cancel" },
  btn_close: { ar: "إغلاق", en: "Close" },
  btn_view: { ar: "عرض", en: "View" },
  btn_edit: { ar: "تحرير", en: "Edit" },
  btn_delete: { ar: "حذف", en: "Delete" },
  btn_run_audit: { ar: "تشغيل تدقيق الذكاء الاصطناعي", en: "Run AI Audit" },

  // Common
  loading: { ar: "جارٍ التحميل…", en: "Loading…" },
  saving: { ar: "جارٍ الحفظ…", en: "Saving…" },
  saved: { ar: "تم الحفظ", en: "Saved" },
  error_generic: { ar: "حدث خطأ ما", en: "Something went wrong" },
  required: { ar: "مطلوب", en: "Required" },

  // Sanctions
  sanctions_title: { ar: "إدارة قائمة العقوبات", en: "Sanctions List Management" },
  sanctions_search: { ar: "ابحث بالاسم أو الجنسية…", en: "Search by name or nationality…" },
  sanctions_total: { ar: "إجمالي السجلات", en: "Total Records" },
  sanctions_active: { ar: "السجلات النشطة", en: "Active Records" },

  // Screening
  screening_title: { ar: "فحص الأسماء", en: "Name Screening" },
  screening_run: { ar: "تشغيل الفحص", en: "Run Screening" },
  screening_no_match: { ar: "لا يوجد تطابق", en: "No match" },
  screening_partial: { ar: "تطابق جزئي", en: "Partial match" },
  screening_confirmed: { ar: "تطابق مؤكد", en: "Confirmed match" },

  // AI audit
  ai_audit_title: { ar: "المدقق المالي بالذكاء الاصطناعي", en: "AI Financial Auditor" },
  ai_audit_subtitle: {
    ar: "ارفع البيانات المالية واحصل على تقرير تدقيق فوري",
    en: "Upload financial data and get an instant audit report",
  },
  health_score: { ar: "درجة الصحة المالية", en: "Health Score" },
  critical: { ar: "حرج", en: "Critical" },
  warning: { ar: "تحذير", en: "Warning" },
  info: { ar: "معلومة", en: "Info" },
};

type I18nContext = {
  lang: Lang;
  setLang: (l: Lang) => void;
  t: (key: keyof typeof dict) => string;
  dir: "rtl" | "ltr";
};

const Ctx = createContext<I18nContext | null>(null);

export function I18nProvider({ children }: { children: ReactNode }) {
  const [lang, setLangState] = useState<Lang>("ar");

  useEffect(() => {
    const stored = (typeof window !== "undefined" && localStorage.getItem("lang")) as Lang | null;
    if (stored === "ar" || stored === "en") setLangState(stored);
  }, []);

  useEffect(() => {
    if (typeof document === "undefined") return;
    document.documentElement.lang = lang;
    document.documentElement.dir = lang === "ar" ? "rtl" : "ltr";
  }, [lang]);

  const setLang = (l: Lang) => {
    setLangState(l);
    if (typeof window !== "undefined") localStorage.setItem("lang", l);
  };

  const t = (key: keyof typeof dict) => dict[key]?.[lang] ?? String(key);

  return (
    <Ctx.Provider value={{ lang, setLang, t, dir: lang === "ar" ? "rtl" : "ltr" }}>
      {children}
    </Ctx.Provider>
  );
}

export function useI18n() {
  const ctx = useContext(Ctx);
  if (!ctx) throw new Error("useI18n must be inside I18nProvider");
  return ctx;
}
