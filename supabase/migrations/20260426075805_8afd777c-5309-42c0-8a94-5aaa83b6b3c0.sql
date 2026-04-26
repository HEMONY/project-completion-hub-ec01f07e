DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_enum
    WHERE enumlabel = 'manager'
      AND enumtypid = 'public.app_role'::regtype
  ) THEN
    ALTER TYPE public.app_role ADD VALUE 'manager';
  END IF;
END $$;

ALTER TABLE public.entities
  ADD COLUMN IF NOT EXISTS review_stage text NOT NULL DEFAULT 'client_draft',
  ADD COLUMN IF NOT EXISTS digital_signature_required boolean NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS digital_signature_status text NOT NULL DEFAULT 'not_requested',
  ADD COLUMN IF NOT EXISTS digital_signature_requested_at timestamp with time zone,
  ADD COLUMN IF NOT EXISTS digital_signature_signed_at timestamp with time zone,
  ADD COLUMN IF NOT EXISTS digital_signature_name text;

CREATE INDEX IF NOT EXISTS idx_entities_review_stage ON public.entities(review_stage);
CREATE INDEX IF NOT EXISTS idx_entities_digital_signature_status ON public.entities(digital_signature_status);

CREATE OR REPLACE FUNCTION public.can_manage_staff(_user_id uuid)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1
    FROM public.user_roles
    WHERE user_id = _user_id
      AND role::text IN ('admin', 'manager')
  )
$$;

DROP POLICY IF EXISTS "Admins manage roles" ON public.user_roles;
CREATE POLICY "Managers manage staff roles"
ON public.user_roles
FOR ALL
TO authenticated
USING (public.can_manage_staff(auth.uid()))
WITH CHECK (public.can_manage_staff(auth.uid()));