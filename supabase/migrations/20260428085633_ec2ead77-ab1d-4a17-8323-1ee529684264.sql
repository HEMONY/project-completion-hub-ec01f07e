REVOKE EXECUTE ON FUNCTION public.has_role(uuid, app_role) FROM authenticated;
REVOKE EXECUTE ON FUNCTION public.can_manage_staff(uuid) FROM authenticated;
REVOKE EXECUTE ON FUNCTION public.get_entity_stats() FROM authenticated;