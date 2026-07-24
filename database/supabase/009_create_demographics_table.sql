CREATE TABLE IF NOT EXISTS public.demographics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    umkm_id UUID NOT NULL REFERENCES public.umkm_profiles(id) ON DELETE CASCADE,
    area_name TEXT NOT NULL,
    population_data JSONB DEFAULT '{}'::jsonb,
    income_data JSONB DEFAULT '{}'::jsonb,
    age_distribution JSONB DEFAULT '{}'::jsonb,
    data_source TEXT DEFAULT 'bps' CHECK (data_source IN ('bps', 'osm')),
    fetched_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.demographics ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can manage demographics of their UMKM" ON public.demographics
    FOR ALL USING (
        umkm_id IN (SELECT id FROM public.umkm_profiles WHERE user_id = auth.uid())
    );
