import { Link } from "react-router-dom";
import { Check } from "lucide-react";
import { useI18n, type dict } from "@/lib/i18n";
import { cn } from "@/lib/utils";

export type KycStepKey =
  | "kyc"
  | "uae-id"
  | "audit-fee"
  | "financial-year"
  | "tax-status"
  | "engagement"
  | "financial-analysis"
  | "payment";

const stepOrder: KycStepKey[] = [
  "kyc",
  "audit-fee",
  "financial-year",
  "tax-status",
  "engagement",
  "payment",
  "uae-id",
];

const labelKeys: Record<KycStepKey, keyof typeof dict> = {
  "kyc":                "kyc_step1",
  "uae-id":             "kyc_step_uaeid",
  "audit-fee":          "kyc_step2",
  "financial-year":     "kyc_step3",
  "tax-status":         "kyc_step4",
  "engagement":         "kyc_step5",
  "financial-analysis": "kyc_step_analysis",
  "payment":            "kyc_step_payment",
};

export function KycStepper({
  current,
  entityId,
  completed = [],
}: {
  current: KycStepKey;
  entityId?: string;
  completed?: KycStepKey[];
}) {
  const { t } = useI18n();
  const currentIdx = stepOrder.indexOf(current);

  return (
    <aside className="rounded-xl border border-border bg-card p-6 sticky top-20 self-start shadow-card">
      <div className="text-lg font-semibold mb-1">{t("nav_new_kyc")}</div>
      <div className="text-xs text-muted-foreground mb-6">{t("step0_subtitle")}</div>
      <ol className="space-y-1 relative">
        <div className="absolute start-[14px] top-3 bottom-3 w-px bg-border" />
        {stepOrder.map((step, idx) => {
          const isCompleted = completed.includes(step) || idx < currentIdx;
          const isActive = step === current;
          const stepNumber = idx + 1;
          const content = (
            <div className="flex items-center gap-3 py-3 relative z-10">
              <div
                className={cn(
                  "size-7 rounded-full grid place-items-center text-xs font-semibold border-2 shrink-0 transition-colors",
                  isCompleted && "bg-success border-success text-success-foreground",
                  isActive && !isCompleted && "bg-primary border-primary text-primary-foreground",
                  !isActive && !isCompleted && "bg-muted border-border text-muted-foreground"
                )}
              >
                {isCompleted ? <Check className="size-3.5" /> : stepNumber}
              </div>
              <div className="min-w-0">
                <div className={cn("text-sm leading-tight", isActive && "font-semibold")}>
                  {t(labelKeys[step])}
                </div>
                <div
                  className={cn(
                    "text-[10px] uppercase tracking-wider mt-0.5",
                    isCompleted ? "text-success" : isActive ? "text-warning" : "text-muted-foreground"
                  )}
                >
                  {isCompleted ? t("status_completed") : isActive ? t("status_pending") : t("status_not_started")}
                </div>
              </div>
            </div>
          );
          if (entityId && (isCompleted || isActive)) {
            return (
              <li key={step}>
                <Link to={`/kyc/${entityId}/${step}`} className="block hover:bg-accent/40 rounded-md px-1">
                  {content}
                </Link>
              </li>
            );
          }
          return <li key={step} className="px-1 opacity-70">{content}</li>;
        })}
      </ol>
    </aside>
  );
}
