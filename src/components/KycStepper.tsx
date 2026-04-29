// src/components/KycStepper.tsx — استبدل الملف كاملاً
import { Link } from "react-router-dom";
import { Check } from "lucide-react";
import { cn } from "@/lib/utils";

export type KycStepKey =
  | "kyc"
  | "audit-fee"
  | "financial-year"
  | "tax-status"
  | "engagement"
  | "payment";

const STEPS: { key: KycStepKey; label: string; sub: string }[] = [
  { key: "kyc",              label: "Know Your Customer (KYC)",     sub: "Entity info · Shareholders · UBOs · Declarations" },
  { key: "audit-fee",        label: "Audit Fee Acknowledgement",    sub: "Review and agree to audit fee" },
  { key: "financial-year",   label: "Financial Year Details",       sub: "Current and prior year dates" },
  { key: "tax-status",       label: "Tax Status Disclosure",        sub: "VAT · Corporate Tax · Excise" },
  { key: "engagement",       label: "Engagement Letter Acceptance", sub: "Accept and submit application" },
  { key: "payment",          label: "Payment",                      sub: "Pay audit fee to complete" },
];

export function KycStepper({
  current,
  entityId,
  completed = [],
}: {
  current: KycStepKey;
  entityId?: string;
  completed?: KycStepKey[];
}) {
  const currentIdx = STEPS.findIndex((s) => s.key === current);

  return (
    <aside className="rounded-xl border border-border bg-card p-6 sticky top-20 self-start shadow-card">
      <div className="text-base font-bold mb-1 uppercase tracking-wide">Entity Onboarding</div>
      <div className="text-xs text-muted-foreground mb-6">Complete all steps to submit</div>
      <ol className="space-y-0.5 relative">
        <div className="absolute start-[13px] top-4 bottom-4 w-px bg-border" />
        {STEPS.map((step, idx) => {
          const isDone = completed.includes(step.key) || idx < currentIdx;
          const isActive = step.key === current;
          const content = (
            <div className="flex items-start gap-3 py-3 relative z-10">
              <div className={cn(
                "size-7 rounded-full grid place-items-center text-xs font-bold border-2 shrink-0 mt-0.5 transition-all",
                isDone  && "bg-green-500 border-green-500 text-white",
                isActive && !isDone && "bg-primary border-primary text-primary-foreground shadow-md",
                !isActive && !isDone && "bg-muted border-border text-muted-foreground"
              )}>
                {isDone ? <Check className="size-3.5" /> : idx + 1}
              </div>
              <div className="min-w-0 flex-1">
                <div className={cn(
                  "text-sm leading-snug",
                  isActive && "font-semibold text-foreground",
                  isDone && "text-muted-foreground",
                  !isActive && !isDone && "text-muted-foreground"
                )}>
                  {step.label}
                </div>
                <div className="text-[10px] text-muted-foreground mt-0.5 leading-tight">
                  {step.sub}
                </div>
                <div className={cn(
                  "text-[9px] uppercase tracking-widest mt-1 font-semibold",
                  isDone && "text-green-600",
                  isActive && !isDone && "text-amber-600",
                  !isActive && !isDone && "text-muted-foreground/60"
                )}>
                  {isDone ? "✓ Completed" : isActive ? "● In Progress" : "Not Started"}
                </div>
              </div>
            </div>
          );
          if (entityId && (isDone || isActive)) {
            return (
              <li key={step.key}>
                <Link to={`/kyc/${entityId}/${step.key}`} className="block hover:bg-accent/40 rounded-lg px-1 transition-colors">
                  {content}
                </Link>
              </li>
            );
          }
          return <li key={step.key} className="px-1 opacity-50 cursor-not-allowed">{content}</li>;
        })}
      </ol>
    </aside>
  );
}
