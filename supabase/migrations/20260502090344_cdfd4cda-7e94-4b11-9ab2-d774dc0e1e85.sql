
-- Admin notifications for sanctions and other compliance alerts
CREATE TABLE IF NOT EXISTS public.admin_notifications (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_id uuid,
  user_id uuid,
  type text NOT NULL DEFAULT 'sanctions_match',
  severity text NOT NULL DEFAULT 'high',
  title text NOT NULL,
  message text,
  details jsonb DEFAULT '{}'::jsonb,
  is_read boolean NOT NULL DEFAULT false,
  read_at timestamptz,
  read_by uuid,
  created_at timestamptz NOT NULL DEFAULT now()
);

ALTER TABLE public.admin_notifications ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Staff view notifications"
  ON public.admin_notifications FOR SELECT TO authenticated
  USING (public.can_access_admin_panel(auth.uid()));

CREATE POLICY "Staff update notifications"
  ON public.admin_notifications FOR UPDATE TO authenticated
  USING (public.can_access_admin_panel(auth.uid()))
  WITH CHECK (public.can_access_admin_panel(auth.uid()));

CREATE POLICY "Anyone insert notifications"
  ON public.admin_notifications FOR INSERT TO authenticated
  WITH CHECK (true);

CREATE INDEX IF NOT EXISTS idx_admin_notifications_unread
  ON public.admin_notifications (is_read, created_at DESC);

-- Sanctions match tracking on entities
ALTER TABLE public.entities
  ADD COLUMN IF NOT EXISTS sanctions_match boolean NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS sanctions_match_details jsonb DEFAULT '[]'::jsonb;

-- Add doc_number index on sanctions_list for fast lookups
CREATE INDEX IF NOT EXISTS idx_sanctions_english_name ON public.sanctions_list (lower(english_name));
CREATE INDEX IF NOT EXISTS idx_sanctions_arabic_name ON public.sanctions_list (arabic_name);
