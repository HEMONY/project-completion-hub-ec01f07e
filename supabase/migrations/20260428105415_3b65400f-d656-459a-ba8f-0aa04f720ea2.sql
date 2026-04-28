CREATE OR REPLACE FUNCTION public.can_access_admin_panel(_user_id uuid)
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
      AND role::text IN ('admin', 'manager', 'moderator', 'auditor')
  )
$$;

DROP POLICY IF EXISTS "Users view own entities" ON public.entities;
CREATE POLICY "Users and staff view entities"
ON public.entities
FOR SELECT
TO authenticated
USING ((auth.uid() = user_id) OR public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "Users update own entities" ON public.entities;
CREATE POLICY "Users and staff update entities"
ON public.entities
FOR UPDATE
TO authenticated
USING ((auth.uid() = user_id) OR public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "Users delete own entities" ON public.entities;
CREATE POLICY "Users and staff delete entities"
ON public.entities
FOR DELETE
TO authenticated
USING ((auth.uid() = user_id) OR public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "Users view own documents and admins view all" ON public.kyc_documents;
CREATE POLICY "Users and staff view documents"
ON public.kyc_documents
FOR SELECT
TO authenticated
USING ((auth.uid() = user_id) OR public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "Users update own pending documents and admins review all" ON public.kyc_documents;
CREATE POLICY "Users update own pending documents and staff review all"
ON public.kyc_documents
FOR UPDATE
TO authenticated
USING (((auth.uid() = user_id) AND (status = 'pending'::text)) OR public.can_access_admin_panel(auth.uid()))
WITH CHECK ((auth.uid() = user_id) OR public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "Users delete own documents and admins delete all" ON public.kyc_documents;
CREATE POLICY "Users and staff delete documents"
ON public.kyc_documents
FOR DELETE
TO authenticated
USING ((auth.uid() = user_id) OR public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "Users view own profile" ON public.profiles;
CREATE POLICY "Users and staff view profiles"
ON public.profiles
FOR SELECT
TO authenticated
USING ((auth.uid() = id) OR public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "Users view own audit logs" ON public.user_audit_logs;
CREATE POLICY "Users and staff view audit logs"
ON public.user_audit_logs
FOR SELECT
TO authenticated
USING ((auth.uid() = user_id) OR public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "admin_manage" ON public.audit_signatures;
CREATE POLICY "staff manage audit signatures"
ON public.audit_signatures
FOR ALL
TO authenticated
USING (public.can_access_admin_panel(auth.uid()))
WITH CHECK (public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "client_view_own" ON public.audit_signatures;
CREATE POLICY "clients and staff view audit signatures"
ON public.audit_signatures
FOR SELECT
TO authenticated
USING (
  (entity_id IN (SELECT entities.id FROM public.entities WHERE entities.user_id = auth.uid()))
  OR public.can_access_admin_panel(auth.uid())
);

DROP POLICY IF EXISTS "Admins manage sanctions" ON public.sanctions_list;
CREATE POLICY "Staff manage sanctions"
ON public.sanctions_list
FOR ALL
TO authenticated
USING (public.can_access_admin_panel(auth.uid()))
WITH CHECK (public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "Users view own screening" ON public.screening_results;
CREATE POLICY "Users and staff view screening"
ON public.screening_results
FOR SELECT
TO authenticated
USING ((auth.uid() = user_id) OR public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "Users update own screening" ON public.screening_results;
CREATE POLICY "Users and staff update screening"
ON public.screening_results
FOR UPDATE
TO authenticated
USING ((auth.uid() = user_id) OR public.can_access_admin_panel(auth.uid()));

DROP POLICY IF EXISTS "Users delete own screening" ON public.screening_results;
CREATE POLICY "Users and staff delete screening"
ON public.screening_results
FOR DELETE
TO authenticated
USING ((auth.uid() = user_id) OR public.can_access_admin_panel(auth.uid()));