// ================================================================
// src/pages/Auth.tsx — استبدل الملف كاملاً
// ================================================================
import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { supabase } from "@/integrations/supabase/client";
import { useI18n } from "@/lib/i18n";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";
import { Languages, Mail } from "lucide-react";

function Divider({ label }: { label: string }) {
  return (
    <div className="flex items-center gap-3 my-4">
      <div className="flex-1 h-px bg-border" />
      <span className="text-xs text-muted-foreground">{label}</span>
      <div className="flex-1 h-px bg-border" />
    </div>
  );
}

export default function AuthPage() {
  const { user, signIn, signUp, resetPassword, loading } = useAuth();
  const { t, dir, lang, setLang } = useI18n();
  const navigate = useNavigate();
  const [mode, setMode] = useState<"login" | "signup" | "forgot">("login");
  const [email, setEmail]       = useState("");
  const [password, setPassword] = useState("");
  const [name, setName]         = useState("");
  const [busy, setBusy]         = useState(false);
  const [oauthBusy, setOauthBusy] = useState<string | null>(null);

  useEffect(() => {
    if (!loading && user) navigate("/");
  }, [user, loading, navigate]);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    try {
      const { error } = mode === "login"
        ? await signIn(email, password)
        : mode === "signup"
        ? await signUp(email, password, name)
        : await resetPassword(email);
      if (error) toast.error(error);
      else if (mode === "signup")
        toast.success(lang === "ar" ? "تحقق من بريدك لتأكيد الحساب." : "Check your email to verify your account.");
      else if (mode === "forgot")
        toast.success(lang === "ar" ? "تم إرسال رابط إعادة التعيين." : "Reset link sent to your email.");
      else navigate("/");
    } finally {
      setBusy(false);
    }
  };

  const signInWithOAuth = async (provider: "google" | "azure" | "apple") => {
    setOauthBusy(provider);
    const { error } = await supabase.auth.signInWithOAuth({
      provider,
      options: {
        redirectTo: `${window.location.origin}/`,
        scopes: provider === "azure" ? "email profile openid" : undefined,
      },
    });
    if (error) {
      toast.error(error.message);
      setOauthBusy(null);
    }
  };

  const oauthProviders = [
    {
      id: "google" as const,
      label: "Google",
      icon: (
        <svg className="size-5" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
      ),
    },
    {
      id: "azure" as const,
      label: "Microsoft",
      icon: (
        <svg className="size-5" viewBox="0 0 24 24">
          <path fill="#F25022" d="M1 1h10v10H1z"/>
          <path fill="#7FBA00" d="M13 1h10v10H13z"/>
          <path fill="#00A4EF" d="M1 13h10v10H1z"/>
          <path fill="#FFB900" d="M13 13h10v10H13z"/>
        </svg>
      ),
    },
    {
      id: "apple" as const,
      label: "Apple",
      icon: (
        <svg className="size-5" viewBox="0 0 24 24" fill="currentColor">
          <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
        </svg>
      ),
    },
  ];

  return (
    <div
      className="min-h-screen grid place-items-center p-6 bg-gradient-to-br from-background via-background to-accent/40"
      dir={dir}
    >
      <Card className="w-full max-w-md shadow-elegant">
        <CardHeader className="text-center pb-4">
          <div className="mx-auto size-14 rounded-2xl gradient-primary grid place-items-center text-primary-foreground font-bold text-2xl mb-3 shadow-elegant">
            م
          </div>
          <CardTitle className="text-2xl">
            {mode === "forgot"
              ? t("auth_reset_password")
              : mode === "login"
              ? t("auth_welcome_back")
              : t("auth_create_account")}
          </CardTitle>
          <CardDescription>{t("app_tagline")}</CardDescription>
          <Button
            variant="ghost" size="sm"
            className="mx-auto mt-2 gap-2"
            onClick={() => setLang(lang === "ar" ? "en" : "ar")}
          >
            <Languages className="size-4" />
            {lang === "ar" ? "English" : "العربية"}
          </Button>
        </CardHeader>

        <CardContent className="space-y-4">
          {/* OAuth buttons — login/signup only */}
          {mode !== "forgot" && (
            <>
              <div className="grid grid-cols-3 gap-2">
                {oauthProviders.map((p) => (
                  <Button
                    key={p.id}
                    type="button"
                    variant="outline"
                    className="flex items-center justify-center gap-2 h-11"
                    disabled={oauthBusy === p.id}
                    onClick={() => signInWithOAuth(p.id)}
                  >
                    {oauthBusy === p.id ? (
                      <div className="size-4 border-2 border-current border-t-transparent rounded-full animate-spin" />
                    ) : (
                      p.icon
                    )}
                    <span className="text-xs font-medium">{p.label}</span>
                  </Button>
                ))}
              </div>
              <Divider label={lang === "ar" ? "أو بالبريد الإلكتروني" : "or with email"} />
            </>
          )}

          {/* Email/password form */}
          <form onSubmit={onSubmit} className="space-y-4">
            {mode === "signup" && (
              <div className="space-y-2">
                <Label htmlFor="name">{t("auth_full_name")}</Label>
                <Input
                  id="name" value={name}
                  onChange={(e) => setName(e.target.value)}
                  required placeholder={lang === "ar" ? "الاسم الكامل" : "Full name"}
                />
              </div>
            )}
            <div className="space-y-2">
              <Label htmlFor="email">{t("auth_email")}</Label>
              <Input
                id="email" type="email" value={email}
                onChange={(e) => setEmail(e.target.value)}
                required autoComplete="email"
                placeholder="example@email.com"
              />
            </div>
            {mode !== "forgot" && (
              <div className="space-y-2">
                <Label htmlFor="password">{t("auth_password")}</Label>
                <Input
                  id="password" type="password" value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  minLength={6} required
                  autoComplete={mode === "login" ? "current-password" : "new-password"}
                  placeholder="••••••••"
                />
              </div>
            )}

            <Button
              type="submit" disabled={busy}
              variant="premium" className="w-full" size="lg"
            >
              {busy ? (
                <div className="flex items-center gap-2">
                  <div className="size-4 border-2 border-current border-t-transparent rounded-full animate-spin" />
                  {t("loading")}
                </div>
              ) : mode === "forgot"
                ? t("auth_reset_password")
                : mode === "login"
                ? t("auth_login")
                : t("auth_signup")}
            </Button>
          </form>

          {/* Links */}
          <div className="text-center text-sm text-muted-foreground space-y-2">
            <div>
              {mode === "login" ? t("auth_no_account") : t("auth_have_account")}{" "}
              <button
                type="button"
                className="text-primary font-medium underline-offset-4 hover:underline"
                onClick={() => setMode(mode === "login" ? "signup" : "login")}
              >
                {mode === "login" ? t("auth_signup") : t("auth_login")}
              </button>
            </div>
            {mode === "login" && (
              <div>
                <button
                  type="button"
                  className="text-primary font-medium underline-offset-4 hover:underline"
                  onClick={() => setMode("forgot")}
                >
                  {t("auth_forgot_password")}
                </button>
              </div>
            )}
            {mode !== "login" && (
              <div>
                <button
                  type="button"
                  className="text-muted-foreground text-xs hover:underline"
                  onClick={() => setMode("login")}
                >
                  {lang === "ar" ? "← العودة لتسجيل الدخول" : "← Back to login"}
                </button>
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
