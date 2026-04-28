REVOKE ALL ON FUNCTION public.can_access_admin_panel(uuid) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.can_access_admin_panel(uuid) FROM anon;
REVOKE ALL ON FUNCTION public.can_access_admin_panel(uuid) FROM authenticated;