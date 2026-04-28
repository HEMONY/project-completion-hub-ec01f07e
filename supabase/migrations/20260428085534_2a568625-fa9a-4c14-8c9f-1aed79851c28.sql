ALTER TABLE public.entities
ADD COLUMN IF NOT EXISTS contact_email TEXT,
ADD COLUMN IF NOT EXISTS telephone TEXT,
ADD COLUMN IF NOT EXISTS economic_sector TEXT,
ADD COLUMN IF NOT EXISTS source_of_funds TEXT,
ADD COLUMN IF NOT EXISTS employer_name TEXT,
ADD COLUMN IF NOT EXISTS pep_persons JSONB NOT NULL DEFAULT '[]'::jsonb,
ADD COLUMN IF NOT EXISTS legal_declarations JSONB NOT NULL DEFAULT '{}'::jsonb,
ADD COLUMN IF NOT EXISTS ocr_verification JSONB NOT NULL DEFAULT '{}'::jsonb;

CREATE INDEX IF NOT EXISTS idx_entities_economic_sector ON public.entities (economic_sector);
CREATE INDEX IF NOT EXISTS idx_entities_ocr_verification ON public.entities USING GIN (ocr_verification);