CREATE OR REPLACE FUNCTION public.get_entity_stats()
RETURNS TABLE(status text, count bigint)
LANGUAGE sql
STABLE SECURITY DEFINER
SET search_path = public
AS $function$
  SELECT application_status, COUNT(*) as count
  FROM public.entities
  GROUP BY application_status;
$function$;