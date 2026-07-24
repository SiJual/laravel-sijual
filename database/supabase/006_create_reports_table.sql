CREATE TABLE IF NOT EXISTS public.reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    umkm_id UUID NOT NULL REFERENCES public.umkm_profiles(id) ON DELETE CASCADE,
    type TEXT NOT NULL CHECK (type IN ('daily', 'weekly', 'monthly')),
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    data JSONB DEFAULT '{}'::jsonb,
    file_url TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.reports ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can manage reports of their UMKM" ON public.reports
    FOR ALL USING (
        umkm_id IN (SELECT id FROM public.umkm_profiles WHERE user_id = auth.uid())
    );
