// src/pages/Home.tsx — صفحة تعريفية (Landing Page)
// أضف Route في App.tsx: <Route path="/home" element={<Home />} />
// وفي Index.tsx: if (!user) return <Navigate to="/home" />
import { Link } from "react-router-dom";
import { useI18n } from "@/lib/i18n";
import {
  ShieldCheck, FileText, Sparkles, CheckCircle2,
  Building2, Globe, Lock, ArrowRight, Users, Star,
} from "lucide-react";
import { Button } from "@/components/ui/button";

export default function Home() {
  const { lang, setLang } = useI18n();

  const features = [
    {
      icon: FileText,
      title: "Digital KYC Onboarding",
      desc: "Complete entity onboarding with 7 sections: entity info, shareholders, UBOs, management, PEP, and compliance declarations — fully digital.",
    },
    {
      icon: ShieldCheck,
      title: "AML & Sanctions Screening",
      desc: "Automated screening against international sanctions lists (OFAC, UN, EU) with AI-powered name matching.",
    },
    {
      icon: Sparkles,
      title: "AI-Powered Audit Analysis",
      desc: "Intelligent financial analysis using Claude AI to generate comprehensive audit reports with risk scoring.",
    },
    {
      icon: Lock,
      title: "Emirates ID OCR Verification",
      desc: "Upload Emirates ID and our AI instantly extracts and verifies the name matches the submitted information.",
    },
    {
      icon: Globe,
      title: "UAE Compliance Ready",
      desc: "Built for UAE regulations: FTA, MOHRE, GDRFA, corporate tax, VAT, and anti-money laundering standards.",
    },
    {
      icon: Users,
      title: "Multi-Role Access",
      desc: "Separate portals for clients and auditors. Clients submit; auditors review, screen, analyze, and approve.",
    },
  ];

  const steps = [
    { num: "01", title: "Register & Confirm",     desc: "Create your account and confirm your legal authorization to represent the entity." },
    { num: "02", title: "KYC Information",        desc: "Enter entity info, shareholders, beneficial owners, management, PEP status, and sign 15 compliance declarations." },
    { num: "03", title: "Audit Fee & Financials", desc: "Review your calculated audit fee, financial year details, and tax status disclosure." },
    { num: "04", title: "Sign & Pay",             desc: "Accept the engagement letter and pay the audit fee to submit your application." },
    { num: "05", title: "Auditor Review",         desc: "Our team screens, verifies, and runs AI analysis on your submission." },
    { num: "06", title: "Approval & Report",      desc: "Receive your audit report and digitally sign the final results." },
  ];

  const stats = [
    { val: "500+", label: "Entities Onboarded" },
    { val: "100%", label: "UAE Compliant" },
    { val: "AI",   label: "Powered Analysis" },
    { val: "24h",  label: "Avg. Review Time" },
  ];

  return (
    <div className="min-h-screen bg-background text-foreground">
      {/* ── Navbar ── */}
      <header className="sticky top-0 z-50 border-b border-border bg-background/80 backdrop-blur">
        <div className="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="size-9 rounded-xl gradient-primary grid place-items-center text-primary-foreground font-bold text-lg shadow-elegant">
              م
            </div>
            <span className="font-bold text-lg">Muhasba</span>
          </div>
          <div className="flex items-center gap-3">
            <Button
              variant="ghost" size="sm"
              onClick={() => setLang(lang === "ar" ? "en" : "ar")}
              className="text-xs"
            >
              {lang === "ar" ? "English" : "العربية"}
            </Button>
            <Button variant="outline" size="sm" asChild>
              <Link to="/auth">Sign In</Link>
            </Button>
            <Button variant="premium" size="sm" asChild>
              <Link to="/auth">Get Started</Link>
            </Button>
          </div>
        </div>
      </header>

      {/* ── Hero ── */}
      <section className="relative overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-accent/10 pointer-events-none" />
        <div className="max-w-7xl mx-auto px-6 py-24 md:py-36 text-center relative">
          <div className="inline-flex items-center gap-2 border border-border rounded-full px-4 py-1.5 text-xs text-muted-foreground mb-6 bg-background/60 backdrop-blur">
            <span className="size-1.5 rounded-full bg-green-500 animate-pulse" />
            UAE-Compliant Digital KYC Platform
          </div>
          <h1 className="text-4xl md:text-6xl font-bold leading-tight mb-6">
            Entity Onboarding &<br />
            <span className="gradient-text bg-clip-text text-transparent bg-gradient-to-r from-primary to-primary/60">
              Audit Management
            </span>
            <br />Made Simple
          </h1>
          <p className="text-lg md:text-xl text-muted-foreground max-w-2xl mx-auto mb-10 leading-relaxed">
            The complete digital platform for UAE business entity onboarding, KYC compliance, AML screening, and AI-powered audit analysis — built for modern accounting firms.
          </p>
          <div className="flex flex-wrap items-center justify-center gap-4">
            <Button variant="premium" size="lg" asChild className="gap-2 text-base px-8">
              <Link to="/auth">
                Start Your Application <ArrowRight className="size-4" />
              </Link>
            </Button>
            <Button variant="outline" size="lg" asChild className="gap-2 text-base">
              <Link to="/auth">Sign In to Dashboard</Link>
            </Button>
          </div>
        </div>
      </section>

      {/* ── Stats ── */}
      <section className="border-y border-border bg-muted/20">
        <div className="max-w-5xl mx-auto px-6 py-12 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
          {stats.map((s) => (
            <div key={s.label}>
              <div className="text-3xl md:text-4xl font-bold text-primary mb-1">{s.val}</div>
              <div className="text-sm text-muted-foreground">{s.label}</div>
            </div>
          ))}
        </div>
      </section>

      {/* ── Features ── */}
      <section className="py-24">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-16">
            <h2 className="text-3xl md:text-4xl font-bold mb-4">Everything You Need for Compliance</h2>
            <p className="text-lg text-muted-foreground max-w-xl mx-auto">
              A comprehensive platform designed specifically for UAE accounting firms and their clients.
            </p>
          </div>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {features.map((f) => {
              const Icon = f.icon;
              return (
                <div key={f.title} className="border border-border rounded-2xl p-6 bg-card hover:border-primary/40 hover:shadow-md transition-all group">
                  <div className="size-12 rounded-xl bg-primary/10 text-primary grid place-items-center mb-4 group-hover:bg-primary group-hover:text-primary-foreground transition-colors">
                    <Icon className="size-6" />
                  </div>
                  <h3 className="font-semibold text-base mb-2">{f.title}</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">{f.desc}</p>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      {/* ── Process ── */}
      <section className="py-24 bg-muted/20 border-y border-border">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-16">
            <h2 className="text-3xl md:text-4xl font-bold mb-4">How It Works</h2>
            <p className="text-lg text-muted-foreground">Six simple steps from registration to approved audit report.</p>
          </div>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {steps.map((s, i) => (
              <div key={s.num} className="relative border border-border rounded-2xl bg-card p-6">
                <div className="text-5xl font-black text-muted/20 absolute top-4 end-5 leading-none">{s.num}</div>
                <div className="relative">
                  <div className="size-8 rounded-full bg-primary text-primary-foreground grid place-items-center text-sm font-bold mb-4">
                    {i + 1}
                  </div>
                  <h3 className="font-semibold text-base mb-2">{s.title}</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">{s.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── KYC Sections ── */}
      <section className="py-24">
        <div className="max-w-5xl mx-auto px-6">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold mb-3">KYC Form — 7 Comprehensive Sections</h2>
            <p className="text-muted-foreground">Designed to meet UAE AML/CFT regulatory requirements</p>
          </div>
          <div className="space-y-3">
            {[
              { n: 1, t: "Entity Information", d: "Registration status, license details, principal activity, economic sector" },
              { n: 2, t: "Contact Details",    d: "Emirate, address, telephone, email" },
              { n: 3, t: "Shareholders / Proprietors", d: "Full details with Emirates ID OCR verification and passport upload" },
              { n: 4, t: "Beneficial Owners (UBOs)", d: "Indirect ownership ≥25% with full identity verification" },
              { n: 5, t: "Management & Effective Control", d: "Who controls the entity day-to-day, with POA if applicable" },
              { n: 6, t: "Politically Exposed Persons (PEP)", d: "PEP declaration with supporting documentation" },
              { n: 7, t: "Compliance & Legal Declarations", d: "15 mandatory compliance confirmations covering all AML/CFT requirements" },
            ].map((item) => (
              <div key={item.n} className="flex items-start gap-4 border border-border rounded-xl p-4 bg-card">
                <div className="size-8 rounded-full bg-primary text-primary-foreground grid place-items-center text-sm font-bold shrink-0">
                  {item.n}
                </div>
                <div>
                  <div className="font-semibold text-sm">{item.t}</div>
                  <div className="text-xs text-muted-foreground mt-0.5">{item.d}</div>
                </div>
                <CheckCircle2 className="size-4 text-green-500 ms-auto mt-1 shrink-0" />
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── CTA ── */}
      <section className="py-24 bg-gradient-to-br from-primary/10 via-transparent to-accent/10">
        <div className="max-w-3xl mx-auto px-6 text-center space-y-6">
          <div className="text-4xl font-bold">Ready to Get Started?</div>
          <p className="text-lg text-muted-foreground">
            Join UAE businesses using Muhasba for compliant, efficient, and digital entity onboarding.
          </p>
          <div className="flex flex-wrap justify-center gap-4">
            <Button variant="premium" size="lg" asChild className="gap-2 px-10">
              <Link to="/auth">Create Account <ArrowRight className="size-4" /></Link>
            </Button>
            <Button variant="outline" size="lg" asChild>
              <Link to="/auth">Sign In</Link>
            </Button>
          </div>
        </div>
      </section>

      {/* ── Footer ── */}
      <footer className="border-t border-border py-8">
        <div className="max-w-7xl mx-auto px-6 flex flex-wrap items-center justify-between gap-4 text-sm text-muted-foreground">
          <div className="flex items-center gap-2">
            <div className="size-6 rounded-lg gradient-primary grid place-items-center text-primary-foreground font-bold text-xs">م</div>
            <span>Muhasba Accounting Platform</span>
          </div>
          <div>© {new Date().getFullYear()} Muhasba. All rights reserved.</div>
          <div className="flex gap-4">
            <Link to="/auth" className="hover:text-foreground transition-colors">Sign In</Link>
            <Link to="/auth" className="hover:text-foreground transition-colors">Register</Link>
          </div>
        </div>
      </footer>
    </div>
  );
}
