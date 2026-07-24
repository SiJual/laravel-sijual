CREATE TABLE IF NOT EXISTS public.content_assets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    umkm_id UUID NOT NULL REFERENCES public.umkm_profiles(id) ON DELETE CASCADE,
    title TEXT,
    content_type TEXT NOT NULL CHECK (content_type IN ('social_media', 'ad_copy', 'blog_post', 'email')),
    prompt TEXT,
    generated_text TEXT,
    generated_image_url TEXT,
    caption TEXT,
    hashtags TEXT[] DEFAULT '{}',
    brand_metadata JSONB DEFAULT '{}'::jsonb,
    tone TEXT,
    style TEXT,
    version INT DEFAULT 1,
    status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'archived')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.content_assets ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can manage content assets of their UMKM" ON public.content_assets
    FOR ALL USING (
        umkm_id IN (SELECT id FROM public.umkm_profiles WHERE user_id = auth.uid())
    );
