import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Route, Routes } from "react-router-dom";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { I18nProvider } from "@/lib/i18n";
import { AuthProvider } from "@/lib/auth";
import Index from "./pages/Index";
import Auth from "./pages/Auth";
import Entities from "./pages/Entities";
import KycStart from "./pages/KycStart";
import KycStep from "./pages/KycStep";
import Screening from "./pages/Screening";
import Sanctions from "./pages/Sanctions";
import Audits from "./pages/Audits";
import CddVerification from "./pages/CddVerification";
import Admin from "./pages/Admin";
import AuditResult from "./pages/AuditResult";
import ResetPassword from "./pages/ResetPassword";
import NotFound from "./pages/NotFound";

const queryClient = new QueryClient();

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <I18nProvider>
        <AuthProvider>
          <Toaster />
          <Sonner richColors position="top-center" />
          <BrowserRouter>
            <Routes>
              <Route path="/" element={<Index />} />
              <Route path="/auth" element={<Auth />} />
              <Route path="/reset-password" element={<ResetPassword />} />
              <Route path="/entities" element={<Entities />} />
              <Route path="/kyc/start" element={<KycStart />} />
              <Route path="/kyc/:entityId/:step" element={<KycStep />} />
              <Route path="/screening" element={<Screening />} />
              <Route path="/sanctions" element={<Sanctions />} />
              <Route path="/audits" element={<Audits />} />
              <Route path="/cdd/:entityId" element={<CddVerification />} />
              <Route path="/admin" element={<Admin />} />
              <Route path="/audit-result" element={<AuditResult />} />
              <Route path="*" element={<NotFound />} />
            </Routes>
          </BrowserRouter>
        </AuthProvider>
      </I18nProvider>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;