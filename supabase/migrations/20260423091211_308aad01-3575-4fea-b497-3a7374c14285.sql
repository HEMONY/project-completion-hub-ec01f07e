
-- Roles system
CREATE TYPE public.app_role AS ENUM ('admin', 'moderator', 'user');

CREATE TABLE public.user_roles (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  role app_role NOT NULL DEFAULT 'user',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (user_id, role)
);
ALTER TABLE public.user_roles ENABLE ROW LEVEL SECURITY;

CREATE OR REPLACE FUNCTION public.has_role(_user_id UUID, _role app_role)
RETURNS BOOLEAN
LANGUAGE SQL
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1 FROM public.user_roles
    WHERE user_id = _user_id AND role = _role
  )
$$;

CREATE POLICY "Users view own roles" ON public.user_roles
  FOR SELECT TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'));

CREATE POLICY "Admins manage roles" ON public.user_roles
  FOR ALL TO authenticated
  USING (public.has_role(auth.uid(), 'admin'))
  WITH CHECK (public.has_role(auth.uid(), 'admin'));

-- Helper trigger function
CREATE OR REPLACE FUNCTION public.set_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$;

-- Profiles
CREATE TABLE public.profiles (
  id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  full_name TEXT,
  email TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users view own profile" ON public.profiles
  FOR SELECT TO authenticated
  USING (auth.uid() = id OR public.has_role(auth.uid(), 'admin'));

CREATE POLICY "Users update own profile" ON public.profiles
  FOR UPDATE TO authenticated
  USING (auth.uid() = id);

CREATE POLICY "Users insert own profile" ON public.profiles
  FOR INSERT TO authenticated
  WITH CHECK (auth.uid() = id);

CREATE TRIGGER profiles_set_updated_at
  BEFORE UPDATE ON public.profiles
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- Auto-create profile + default role on signup
CREATE OR REPLACE FUNCTION public.handle_new_user()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  INSERT INTO public.profiles (id, full_name, email)
  VALUES (NEW.id, NEW.raw_user_meta_data->>'full_name', NEW.email);
  INSERT INTO public.user_roles (user_id, role)
  VALUES (NEW.id, 'user');
  RETURN NEW;
END;
$$;

CREATE TRIGGER on_auth_user_created
  AFTER INSERT ON auth.users
  FOR EACH ROW EXECUTE FUNCTION public.handle_new_user();

-- Entities
CREATE TABLE public.entities (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL,
  entity_name TEXT NOT NULL,
  engagement_number TEXT,
  application_type TEXT NOT NULL DEFAULT 'new',
  application_status TEXT NOT NULL DEFAULT 'draft',
  current_step INTEGER NOT NULL DEFAULT 1,
  registration_status TEXT,
  mainland_company_type TEXT,
  license_number TEXT,
  license_issue_date DATE,
  license_expiry_date DATE,
  main_activity TEXT,
  emirate TEXT,
  address TEXT,
  total_turnover NUMERIC DEFAULT 0,
  shareholders JSONB DEFAULT '[]'::jsonb,
  ubos JSONB DEFAULT '[]'::jsonb,
  management_control JSONB,
  screening_completed BOOLEAN DEFAULT false,
  ind_completed BOOLEAN DEFAULT false,
  cdd_completed BOOLEAN DEFAULT false,
  submitted_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.entities ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users view own entities" ON public.entities FOR SELECT TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'));
CREATE POLICY "Users insert own entities" ON public.entities FOR INSERT TO authenticated
  WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Users update own entities" ON public.entities FOR UPDATE TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'));
CREATE POLICY "Users delete own entities" ON public.entities FOR DELETE TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'));

CREATE TRIGGER entities_set_updated_at BEFORE UPDATE ON public.entities
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();
CREATE INDEX idx_entities_user ON public.entities(user_id);
CREATE INDEX idx_entities_status ON public.entities(application_status);

-- Audit fees
CREATE TABLE public.audit_fees (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_id UUID NOT NULL REFERENCES public.entities(id) ON DELETE CASCADE,
  user_id UUID NOT NULL,
  turnover NUMERIC NOT NULL,
  calculated_fee NUMERIC NOT NULL,
  agreed BOOLEAN DEFAULT false,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.audit_fees ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Users manage own audit fees" ON public.audit_fees FOR ALL TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'))
  WITH CHECK (auth.uid() = user_id);
CREATE TRIGGER audit_fees_set_updated_at BEFORE UPDATE ON public.audit_fees
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- Financial years
CREATE TABLE public.financial_years (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_id UUID NOT NULL REFERENCES public.entities(id) ON DELETE CASCADE,
  user_id UUID NOT NULL,
  is_first_year BOOLEAN DEFAULT true,
  first_start_date DATE,
  first_end_date DATE,
  current_start_date DATE,
  current_end_date DATE,
  previous_start_date DATE,
  previous_end_date DATE,
  previous_audited TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.financial_years ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Users manage own financial years" ON public.financial_years FOR ALL TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'))
  WITH CHECK (auth.uid() = user_id);
CREATE TRIGGER financial_years_set_updated_at BEFORE UPDATE ON public.financial_years
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- Tax status
CREATE TABLE public.tax_status (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_id UUID NOT NULL REFERENCES public.entities(id) ON DELETE CASCADE,
  user_id UUID NOT NULL,
  vat_status TEXT,
  vat_registration_number TEXT,
  excise_tax_status TEXT,
  corporate_tax_status TEXT,
  corporate_tax_registration_number TEXT,
  corporate_tax_treatment TEXT,
  small_business_relief TEXT,
  not_registered_reason TEXT,
  other_reason_details TEXT,
  previous_data JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.tax_status ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Users manage own tax status" ON public.tax_status FOR ALL TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'))
  WITH CHECK (auth.uid() = user_id);
CREATE TRIGGER tax_status_set_updated_at BEFORE UPDATE ON public.tax_status
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- Engagement letters
CREATE TABLE public.engagement_letters (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_id UUID NOT NULL REFERENCES public.entities(id) ON DELETE CASCADE,
  user_id UUID NOT NULL,
  engagement_number TEXT,
  accepted BOOLEAN DEFAULT false,
  accepted_at TIMESTAMPTZ,
  letter_content TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.engagement_letters ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Users manage own engagement letters" ON public.engagement_letters FOR ALL TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'))
  WITH CHECK (auth.uid() = user_id);
CREATE TRIGGER engagement_letters_set_updated_at BEFORE UPDATE ON public.engagement_letters
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- Sanctions list
CREATE TABLE public.sanctions_list (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  english_name TEXT NOT NULL,
  arabic_name TEXT,
  country TEXT,
  type TEXT,
  list_reference TEXT,
  source TEXT,
  status TEXT NOT NULL DEFAULT 'active',
  expiry_date DATE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.sanctions_list ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Authenticated read sanctions" ON public.sanctions_list FOR SELECT TO authenticated USING (true);
CREATE POLICY "Admins manage sanctions" ON public.sanctions_list FOR ALL TO authenticated
  USING (public.has_role(auth.uid(), 'admin'))
  WITH CHECK (public.has_role(auth.uid(), 'admin'));
CREATE TRIGGER sanctions_list_set_updated_at BEFORE UPDATE ON public.sanctions_list
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();
CREATE INDEX idx_sanctions_english_name ON public.sanctions_list USING gin(to_tsvector('simple', english_name));

-- Screening results
CREATE TABLE public.screening_results (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_id UUID NOT NULL REFERENCES public.entities(id) ON DELETE CASCADE,
  user_id UUID NOT NULL,
  name_to_screen TEXT NOT NULL,
  name_type TEXT,
  ai_result TEXT NOT NULL DEFAULT 'no-match',
  admin_result TEXT,
  notes TEXT,
  screened_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  verified_by UUID,
  verified_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.screening_results ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Users view own screening" ON public.screening_results FOR SELECT TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'));
CREATE POLICY "Users insert own screening" ON public.screening_results FOR INSERT TO authenticated
  WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Users update own screening" ON public.screening_results FOR UPDATE TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'));
CREATE POLICY "Users delete own screening" ON public.screening_results FOR DELETE TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'));

-- KYC documents
CREATE TABLE public.kyc_documents (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_id UUID NOT NULL REFERENCES public.entities(id) ON DELETE CASCADE,
  user_id UUID NOT NULL,
  document_type TEXT NOT NULL,
  file_name TEXT NOT NULL,
  storage_path TEXT NOT NULL,
  mime_type TEXT,
  size_bytes INTEGER,
  uploaded_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.kyc_documents ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Users manage own kyc documents" ON public.kyc_documents FOR ALL TO authenticated
  USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'))
  WITH CHECK (auth.uid() = user_id);

-- Storage bucket
INSERT INTO storage.buckets (id, name, public)
VALUES ('kyc-documents', 'kyc-documents', false)
ON CONFLICT (id) DO NOTHING;

CREATE POLICY "Users read own kyc files" ON storage.objects FOR SELECT TO authenticated
  USING (bucket_id = 'kyc-documents' AND (auth.uid()::text = (storage.foldername(name))[1] OR public.has_role(auth.uid(), 'admin')));
CREATE POLICY "Users upload own kyc files" ON storage.objects FOR INSERT TO authenticated
  WITH CHECK (bucket_id = 'kyc-documents' AND auth.uid()::text = (storage.foldername(name))[1]);
CREATE POLICY "Users delete own kyc files" ON storage.objects FOR DELETE TO authenticated
  USING (bucket_id = 'kyc-documents' AND (auth.uid()::text = (storage.foldername(name))[1] OR public.has_role(auth.uid(), 'admin')));
