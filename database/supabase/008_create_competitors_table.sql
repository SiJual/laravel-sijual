CREATE TABLE IF NOT EXISTS public.competitors (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    analysis_id UUID NOT NULL REFERENCES public.market_analyses(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    business_type TEXT,
    address TEXT,
    latitude DOUBLE PRECISION,
    longitude DOUBLE PRECISION,
    rating DOUBLE PRECISION DEFAULT 0.0,
    review_count INT DEFAULT 0,
    sentiment TEXT DEFAULT 'neutral' CHECK (sentiment IN ('positive', 'neutral', 'negative')),
    scraped_data JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.competitors ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can manage competitors of their market analyses" ON public.competitors
    FOR ALL USING (
        analysis_id IN (
            SELECT id FROM public.market_analyses WHERE umkm_id IN (
                SELECT id FROM public.umkm_profiles WHERE user_id = auth.uid()
            )
        )
    );
