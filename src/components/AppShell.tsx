import { type ReactNode, useEffect, useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import {
  LayoutDashboard,
  Building2,
  FilePlus2,
  ShieldCheck,
  Sparkles,
  LogOut,
  Languages,
  Moon,
  Sun,
  Menu,
  X,
  Shield,
  FileText,   
 } from "lucide-react";
import { useI18n } from "@/lib/i18n";
import { useAuth } from "@/lib/auth";
import { useRole } from "@/lib/auth";
import { ChatWidget } from "@/components/ChatWidget";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export function AppShell({ children, centered = false }: { children: ReactNode; centered?: boolean }) {
  const { t, lang, setLang, dir } = useI18n();
  const { user, signOut } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [dark, setDark] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const { role } = useRole();

  useEffect(() => {
    const stored = localStorage.getItem("theme");
    const isDark = stored === "dark" || (!stored && window.matchMedia("(prefers-color-scheme: dark)").matches);
    setDark(isDark);
    document.documentElement.classList.toggle("dark", isDark);
  }, []);

  const toggleDark = () => {
    const next = !dark;
    setDark(next);
    document.documentElement.classList.toggle("dark", next);
    localStorage.setItem("theme", next ? "dark" : "light");
  };

  const isAdmin = ["admin", "auditor", "moderator"].includes(role);

  const nav = [
    { to: "/", label: t("nav_dashboard"), icon: LayoutDashboard },
    { to: "/entities", label: t("nav_entities"), icon: Building2 },
    { to: "/kyc/start", label: t("nav_new_kyc"), icon: FilePlus2 },
    { to: "/audit-result", label: "نتائج التدقيق", icon: FileText },
    // الفحص والتدقيق — للمشرفين فقط
    ...(isAdmin
      ? [
          { to: "/screening", label: t("nav_screening"), icon: ShieldCheck },
          { to: "/audits", label: t("nav_audits"), icon: Sparkles },
          { to: "/admin", label: "لوحة الإدارة", icon: Shield },
        ]
      : []),
  ];

  const isActive = (to: string) =>
    to === "/" ? location.pathname === "/" : location.pathname.startsWith(to);

  const Sidebar = (
    <>
      <div className="px-6 py-5 border-b border-sidebar-border">
        <Link to="/" className="flex items-center gap-3" onClick={() => setMobileOpen(false)}>
          <div className="size-10 rounded-xl gradient-primary grid place-items-center text-primary-foreground font-bold text-lg shadow-elegant">
            م
          </div>
          <div>
            <div className="font-bold text-lg leading-none">{t("app_name")}</div>
            <div className="text-[11px] text-muted-foreground mt-1">{t("app_tagline")}</div>
          </div>
        </Link>
      </div>
      <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
        {nav.map((item) => {
          const active = isActive(item.to);
          const Icon = item.icon;
          return (
            <Link
              key={item.to}
              to={item.to}
              onClick={() => setMobileOpen(false)}
              className={cn(
                "flex items-center gap-3 px-3 py-2.5 rounded-md text-sm transition-all",
                active
                  ? "bg-sidebar-primary text-sidebar-primary-foreground shadow-sm font-medium"
                  : "hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
              )}
            >
              <Icon className="size-4" />
              <span>{item.label}</span>
            </Link>
          );
        })}
      </nav>
      <div className="p-3 border-t border-sidebar-border space-y-2">
        {user && (
          <div className="px-3 py-2 text-xs text-muted-foreground truncate" title={user.email ?? ""}>
            {user.email}
          </div>
        )}
        <Button
          variant="ghost"
          className="w-full justify-start gap-2"
          onClick={async () => {
            await signOut();
            navigate("/auth");
          }}
        >
          <LogOut className="size-4" />
          {t("nav_logout")}
        </Button>
      </div>
    </>
  );

  return (
    <div className="min-h-screen flex bg-background text-foreground" dir={dir}>
      {/* Desktop sidebar */}
      {!centered && (
        <aside className="hidden md:flex w-64 flex-col border-e border-sidebar-border bg-sidebar text-sidebar-foreground">
          {Sidebar}
        </aside>
      )}

      {/* Mobile sidebar */}
      {mobileOpen && (
        <div className={cn("fixed inset-0 z-50 flex", !centered && "md:hidden")}>
          <div className="absolute inset-0 bg-black/50" onClick={() => setMobileOpen(false)} />
          <aside className="relative flex w-72 flex-col bg-sidebar text-sidebar-foreground">
            {Sidebar}
          </aside>
        </div>
      )}

      {/* Main */}
      <div className="flex-1 flex flex-col min-w-0">
        <header className="h-14 border-b border-border bg-card/60 backdrop-blur flex items-center justify-between px-4 md:px-6 sticky top-0 z-40">
          <div className="flex items-center gap-2">
            <Button variant="ghost" size="icon" className={centered ? "" : "md:hidden"} onClick={() => setMobileOpen(!mobileOpen)}>
              {mobileOpen ? <X className="size-4" /> : <Menu className="size-4" />}
            </Button>
            <div className={centered ? "font-bold" : "md:hidden font-bold"}>{t("app_name")}</div>
          </div>
          <div className="ms-auto flex items-center gap-2">
            <Button variant="ghost" size="sm" className="gap-2" onClick={() => setLang(lang === "ar" ? "en" : "ar")}>
              <Languages className="size-4" />
              <span className="hidden sm:inline">{lang === "ar" ? "English" : "العربية"}</span>
            </Button>
            <Button variant="ghost" size="icon" onClick={toggleDark} aria-label="Toggle theme">
              {dark ? <Sun className="size-4" /> : <Moon className="size-4" />}
            </Button>
          </div>
        </header>
        <main className="flex-1 p-4 md:p-8 overflow-x-hidden animate-fade-in">{children}</main>
      </div>
      <ChatWidget />
    </div>
  );
}
