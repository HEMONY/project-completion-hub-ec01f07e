ALTER TABLE public.kyc_documents
ADD COLUMN IF NOT EXISTS status text NOT NULL DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS rejection_reason text,
ADD COLUMN IF NOT EXISTS reviewed_by uuid,
ADD COLUMN IF NOT EXISTS reviewed_at timestamp with time zone;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint
    WHERE conname = 'kyc_documents_status_check'
      AND conrelid = 'public.kyc_documents'::regclass
  ) THEN
    ALTER TABLE public.kyc_documents
    ADD CONSTRAINT kyc_documents_status_check
    CHECK (status IN ('pending', 'approved', 'rejected'));
  END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_kyc_documents_status ON public.kyc_documents(status);
CREATE INDEX IF NOT EXISTS idx_kyc_documents_document_type ON public.kyc_documents(document_type);
CREATE INDEX IF NOT EXISTS idx_kyc_documents_uploaded_at ON public.kyc_documents(uploaded_at DESC);

DROP POLICY IF EXISTS "Users manage own kyc documents" ON public.kyc_documents;

CREATE POLICY "Users view own documents and admins view all"
ON public.kyc_documents
FOR SELECT
TO authenticated
USING ((auth.uid() = user_id) OR public.has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Users upload own documents"
ON public.kyc_documents
FOR INSERT
TO authenticated
WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users update own pending documents and admins review all"
ON public.kyc_documents
FOR UPDATE
TO authenticated
USING ((auth.uid() = user_id AND status = 'pending') OR public.has_role(auth.uid(), 'admin'::app_role))
WITH CHECK ((auth.uid() = user_id) OR public.has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Users delete own documents and admins delete all"
ON public.kyc_documents
FOR DELETE
TO authenticated
USING ((auth.uid() = user_id) OR public.has_role(auth.uid(), 'admin'::app_role));

CREATE OR REPLACE FUNCTION public.set_kyc_document_review_fields()
RETURNS trigger
LANGUAGE plpgsql
SET search_path = public
AS $$
BEGIN
  IF NEW.status IS DISTINCT FROM OLD.status THEN
    NEW.reviewed_at = now();
    NEW.reviewed_by = auth.uid();
    IF NEW.status <> 'rejected' THEN
      NEW.rejection_reason = NULL;
    END IF;
  END IF;
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_kyc_documents_review_fields ON public.kyc_documents;
CREATE TRIGGER trg_kyc_documents_review_fields
BEFORE UPDATE ON public.kyc_documents
FOR EACH ROW
EXECUTE FUNCTION public.set_kyc_document_review_fields();