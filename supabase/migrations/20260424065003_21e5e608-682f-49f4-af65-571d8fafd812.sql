
-- ============================================
-- COMPLETE KYC SCHEMA: missing PHP tables
-- ============================================

-- 1) CDD Verifications (admin-side compliance verification)
CREATE TABLE IF NOT EXISTS public.cdd_verifications (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  entity_id UUID NOT NULL,
  admin_id UUID NOT NULL,
  identity_verification TEXT CHECK (identity_verification IN ('verified','failed')),
  eligibility_verification TEXT CHECK (eligibility_verification IN ('verified','failed')),
  auditor_verification TEXT CHECK (auditor_verification IN ('verified','failed')),
  economic_sector TEXT,
  eligibility_status TEXT CHECK (eligibility_status IN ('eligible','not_eligible','pending')) DEFAULT 'pending',
  verification_history JSONB DEFAULT '[]'::jsonb,
  notes TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.cdd_verifications ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins manage cdd verifications"
  ON public.cdd_verifications FOR ALL TO authenticated
  USING (public.has_role(auth.uid(), 'admin'))
  WITH CHECK (public.has_role(auth.uid(), 'admin'));

CREATE POLICY "Entity owners view cdd"
  ON public.cdd_verifications FOR SELECT TO authenticated
  USING (EXISTS (SELECT 1 FROM public.entities e WHERE e.id = entity_id AND e.user_id = auth.uid()));

CREATE TRIGGER set_cdd_updated_at BEFORE UPDATE ON public.cdd_verifications
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- 2) Independence Confirmations (auditor independence)
CREATE TABLE IF NOT EXISTS public.independence_confirmations (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  entity_id UUID NOT NULL,
  user_id UUID NOT NULL,
  engagement_number TEXT,
  confirmation_type TEXT NOT NULL CHECK (confirmation_type IN ('confirmed','conflict')),
  confirmation_status TEXT DEFAULT 'pending' CHECK (confirmation_status IN ('pending','confirmed','conflict_declared','sent','terminated')),
  confirmed_by UUID,
  confirmation_text TEXT,
  signature_name TEXT,
  signature_date TIMESTAMPTZ,
  conflict_details TEXT,
  status_message TEXT,
  is_sent BOOLEAN DEFAULT false,
  sent_at TIMESTAMPTZ,
  client_audit TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.independence_confirmations ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users manage own independence"
  ON public.independence_confirmations FOR ALL TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'))
  WITH CHECK (auth.uid() = user_id);

CREATE TRIGGER set_ind_updated_at BEFORE UPDATE ON public.independence_confirmations
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- 3) Audit Acceptance Memorandum (acceptance step)
CREATE TABLE IF NOT EXISTS public.audit_acceptance_memorandum (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  entity_id UUID NOT NULL,
  user_id UUID NOT NULL,
  client_name TEXT NOT NULL,
  engagement_number TEXT,
  financial_year TEXT,
  commencement_date DATE,
  risk_assessment TEXT DEFAULT 'LOW RISK' CHECK (risk_assessment IN ('LOW RISK','MEDIUM RISK','HIGH RISK')),
  auditor_name TEXT,
  accepted BOOLEAN DEFAULT false,
  accepted_at TIMESTAMPTZ,
  notes TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.audit_acceptance_memorandum ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users manage own acceptance"
  ON public.audit_acceptance_memorandum FOR ALL TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'))
  WITH CHECK (auth.uid() = user_id);

CREATE TRIGGER set_acc_updated_at BEFORE UPDATE ON public.audit_acceptance_memorandum
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- 4) User Audit Logs (activity log)
CREATE TABLE IF NOT EXISTS public.user_audit_logs (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  user_id UUID NOT NULL,
  action TEXT NOT NULL,
  description TEXT,
  ip_address TEXT,
  user_agent TEXT,
  metadata JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.user_audit_logs ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users view own audit logs"
  ON public.user_audit_logs FOR SELECT TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'));

CREATE POLICY "Users insert own audit logs"
  ON public.user_audit_logs FOR INSERT TO authenticated
  WITH CHECK (auth.uid() = user_id);

-- 5) Add review/reviewer fields to entities (matches PHP)
ALTER TABLE public.entities ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMPTZ;
ALTER TABLE public.entities ADD COLUMN IF NOT EXISTS reviewed_by UUID;
ALTER TABLE public.entities ADD COLUMN IF NOT EXISTS rejection_reason TEXT;

-- 6) Enforce 50,000,000 turnover ceiling at DB level (validation trigger, not CHECK)
CREATE OR REPLACE FUNCTION public.validate_entity_turnover()
RETURNS TRIGGER LANGUAGE plpgsql SET search_path = public AS $$
BEGIN
  IF NEW.total_turnover IS NOT NULL AND NEW.total_turnover > 50000000 THEN
    RAISE EXCEPTION 'Total turnover cannot exceed 50,000,000 AED';
  END IF;
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS validate_turnover_trigger ON public.entities;
CREATE TRIGGER validate_turnover_trigger
  BEFORE INSERT OR UPDATE ON public.entities
  FOR EACH ROW EXECUTE FUNCTION public.validate_entity_turnover();

-- 7) Helpful indexes
CREATE INDEX IF NOT EXISTS idx_entities_user_status ON public.entities(user_id, application_status);
CREATE INDEX IF NOT EXISTS idx_screening_entity ON public.screening_results(entity_id);
CREATE INDEX IF NOT EXISTS idx_cdd_entity ON public.cdd_verifications(entity_id);
CREATE INDEX IF NOT EXISTS idx_ind_entity ON public.independence_confirmations(entity_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON public.user_audit_logs(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_sanctions_english_name ON public.sanctions_list(english_name);
CREATE INDEX IF NOT EXISTS idx_sanctions_arabic_name ON public.sanctions_list(arabic_name);
