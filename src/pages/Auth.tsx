import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";
import { Languages } from "lucide-react";

export default function AuthPage() {
  const { user, signIn, signUp, loading } = useAuth();
  const { t, dir, lang, setLang } = useI18n();
  const navigate = useNavigate();
  const [mode, setMode] = useState<"login" | "signup">("login");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!loading && user) navigate("/");
  }, [user, loading, navigate]);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    try {
      const { error } = mode === "login" ? await signIn(email, password) : await signUp(email, password, name);
      if (error) toast.error(error);
      else if (mode === "signup") toast.success(lang === "ar" ? "تحقق من بريدك لتأكيد الحساب." : "Check your email to verify your account.");
      else navigate("/");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="min-h-screen grid place-items-center p-6 bg-gradient-to-br from-background via-background to-accent/40" dir={dir}>
      <Card className="w-full max-w-md shadow-elegant">
        <CardHeader className="text-center">
          <div className="mx-auto size-14 rounded-2xl gradient-primary grid place-items-center text-primary-foreground font-bold text-2xl mb-3 shadow-elegant">
            م
          </div>
          <CardTitle className="text-2xl">{mode === "login" ? t("auth_welcome_back") : t("auth_create_account")}</CardTitle>
          <CardDescription>{t("app_tagline")}</CardDescription>
          <Button variant="ghost" size="sm" className="mx-auto mt-2 gap-2" onClick={() => setLang(lang === "ar" ? "en" : "ar")}>
            <Languages className="size-4" />
            {lang === "ar" ? "English" : "العربية"}
          </Button>
        </CardHeader>
        <CardContent>
          <form onSubmit={onSubmit} className="space-y-4">
            {mode === "signup" && (
              <div className="space-y-2">
                <Label htmlFor="name">{t("auth_full_name")}</Label>
                <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required />
              </div>
            )}
            <div className="space-y-2">
              <Label htmlFor="email">{t("auth_email")}</Label>
              <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">{t("auth_password")}</Label>
              <Input id="password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} minLength={6} required autoComplete={mode === "login" ? "current-password" : "new-password"} />
            </div>
            <Button type="submit" disabled={busy} variant="premium" className="w-full" size="lg">
              {busy ? t("loading") : mode === "login" ? t("auth_login") : t("auth_signup")}
            </Button>
          </form>
          <div className="mt-4 text-center text-sm text-muted-foreground">
            {mode === "login" ? t("auth_no_account") : t("auth_have_account")}{" "}
            <button type="button" className="text-primary font-medium underline-offset-4 hover:underline" onClick={() => setMode(mode === "login" ? "signup" : "login")}>
              {mode === "login" ? t("auth_signup") : t("auth_login")}
            </button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
