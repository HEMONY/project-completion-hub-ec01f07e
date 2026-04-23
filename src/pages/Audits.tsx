import { Link } from "react-router-dom";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useI18n } from "@/lib/i18n";
import { Sparkles, FileBarChart, ShieldCheck, TrendingUp } from "lucide-react";

export default function Audits() {
  const { t } = useI18n();
  return (
    <AppShell>
      <div className="max-w-5xl mx-auto space-y-6">
        <div>
          <h1 className="text-3xl font-bold flex items-center gap-2">
            <Sparkles className="size-7 text-primary" /> {t("ai_audit_title")}
          </h1>
          <p className="text-muted-foreground mt-1">{t("ai_audit_subtitle")}</p>
        </div>

        <div className="grid md:grid-cols-3 gap-4">
          {[
            { icon: FileBarChart, title: "Financial analysis", desc: "Auto review of statements" },
            { icon: ShieldCheck, title: "Compliance check", desc: "KYC, AML & sanctions cross-check" },
            { icon: TrendingUp, title: "Risk scoring", desc: "Health score 0-100" },
          ].map((f) => (
            <Card key={f.title} className="shadow-card">
              <CardContent className="p-5 space-y-2">
                <div className="size-10 rounded-lg bg-primary/10 text-primary grid place-items-center">
                  <f.icon className="size-5" />
                </div>
                <div className="font-semibold">{f.title}</div>
                <div className="text-sm text-muted-foreground">{f.desc}</div>
              </CardContent>
            </Card>
          ))}
        </div>

        <Card className="shadow-card">
          <CardHeader>
            <CardTitle>Coming soon</CardTitle>
            <CardDescription>
              The AI auditor will analyze your entity's submitted KYC, financial statements, and tax disclosures to produce a health score and prioritized issue list.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild variant="premium">
              <Link to="/entities">Open an entity</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
