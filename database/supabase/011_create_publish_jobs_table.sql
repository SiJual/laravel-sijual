CREATE TABLE IF NOT EXISTS public.publish_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    content_id UUID NOT NULL REFERENCES public.content_assets(id) ON DELETE CASCADE,
    platform TEXT NOT NULL CHECK (platform IN ('instagram', 'facebook')),
    status TEXT DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'publishing', 'published', 'failed')),
    platform_response JSONB DEFAULT '{}'::jsonb,
    scheduled_at TIMESTAMPTZ,
    published_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.publish_jobs ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can manage publish jobs of their content assets" ON public.publish_jobs
    FOR ALL USING (
        content_id IN (
            SELECT id FROM public.content_assets WHERE umkm_id IN (
                SELECT id FROM public.umkm_profiles WHERE user_id = auth.uid()
            )
        )
    );
