import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { supabase } from "@/integrations/supabase/client";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { ArrowRight, RefreshCw, UserPlus } from "lucide-react";
import { toast } from "sonner";

export default function KycStart() {
  const { user } = useAuth();
  const { t, dir } = useI18n();
  const navigate = useNavigate();
  const [busy, setBusy] = useState<string | null>(null);

  const begin = async (type: "new" | "return") => {
    if (!user) {
      navigate("/auth");
      return;
    }
    setBusy(type);
    const { data, error } = await supabase
      .from("entities")
      .insert({
        user_id: user.id,
        entity_name: "Untitled Entity",
        application_type: type,
        application_status: "draft",
        current_step: 1,
      })
      .select("id")
      .single();
    setBusy(null);
    if (error || !data) {
      toast.error(error?.message ?? "Failed to create entity");
      return;
    }
    navigate(`/kyc/${data.id}/kyc`);
  };

  return (
    <AppShell>
      <div className="max-w-5xl mx-auto" dir={dir}>
        <div className="text-center mb-10">
          <h1 className="text-3xl md:text-4xl font-bold tracking-tight">{t("step0_welcome")}</h1>
          <p className="text-muted-foreground mt-2">{t("step0_subtitle")}</p>
        </div>
        <div className="grid md:grid-cols-2 gap-6">
          {[
            { type: "new" as const, icon: UserPlus, title: t("step0_new_client"), desc: t("step0_new_desc"), cta: t("step0_start_new") },
            { type: "return" as const, icon: RefreshCw, title: t("step0_returning"), desc: t("step0_returning_desc"), cta: t("step0_continue") },
          ].map((opt) => (
            <Card
              key={opt.type}
              className="group hover:shadow-elegant hover:-translate-y-1 transition-all cursor-pointer shadow-card"
              onClick={() => begin(opt.type)}
            >
              <CardContent className="p-8 text-center space-y-4">
                <div className="mx-auto size-16 rounded-2xl gradient-primary grid place-items-center text-primary-foreground shadow-elegant">
                  <opt.icon className="size-7" />
                </div>
                <h3 className="text-xl font-semibold">{opt.title}</h3>
                <p className="text-sm text-muted-foreground">{opt.desc}</p>
                <Button variant="premium" className="w-full" disabled={busy === opt.type}>
                  {busy === opt.type ? t("loading") : opt.cta} <ArrowRight className="size-4" />
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </AppShell>
  );
}
