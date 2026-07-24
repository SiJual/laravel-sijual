CREATE TABLE IF NOT EXISTS public.market_analyses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    umkm_id UUID NOT NULL REFERENCES public.umkm_profiles(id) ON DELETE CASCADE,
    location_query TEXT,
    latitude DOUBLE PRECISION,
    longitude DOUBLE PRECISION,
    radius_km DOUBLE PRECISION DEFAULT 1.0,
    market_fit_score INT DEFAULT 0,
    analysis_data JSONB DEFAULT '{}'::jsonb,
    demographic_data JSONB DEFAULT '{}'::jsonb,
    status TEXT DEFAULT 'completed' CHECK (status IN ('pending', 'processing', 'completed', 'failed')),
    expires_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.market_analyses ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can manage market analyses of their UMKM" ON public.market_analyses
    FOR ALL USING (
        umkm_id IN (SELECT id FROM public.umkm_profiles WHERE user_id = auth.uid())
    );
