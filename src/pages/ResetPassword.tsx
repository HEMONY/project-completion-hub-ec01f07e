import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";

export default function ResetPassword() {
  const { updatePassword } = useAuth();
  const { t, dir } = useI18n();
  const navigate = useNavigate();
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (password !== confirm) return toast.error(dir === "rtl" ? "كلمتا المرور غير متطابقتين" : "Passwords do not match");
    setBusy(true);
    const { error } = await updatePassword(password);
    setBusy(false);
    if (error) return toast.error(error);
    toast.success(dir === "rtl" ? "تم تحديث كلمة المرور" : "Password updated");
    navigate("/auth");
  };

  return (
    <div className="min-h-screen grid place-items-center p-6 bg-gradient-to-br from-background via-background to-accent/40" dir={dir}>
      <Card className="w-full max-w-md shadow-elegant">
        <CardHeader className="text-center">
          <CardTitle className="text-2xl">{t("auth_reset_password")}</CardTitle>
          <CardDescription>{t("app_tagline")}</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="password">{t("auth_new_password")}</Label>
              <Input id="password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} minLength={6} required autoComplete="new-password" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="confirm">{dir === "rtl" ? "تأكيد كلمة المرور" : "Confirm password"}</Label>
              <Input id="confirm" type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} minLength={6} required autoComplete="new-password" />
            </div>
            <Button type="submit" disabled={busy} variant="premium" className="w-full" size="lg">
              {busy ? t("loading") : t("auth_reset_password")}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}