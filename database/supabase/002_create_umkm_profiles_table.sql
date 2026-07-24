CREATE TABLE IF NOT EXISTS public.umkm_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    business_name TEXT NOT NULL,
    business_type TEXT,
    address TEXT,
    city TEXT,
    province TEXT,
    latitude DOUBLE PRECISION,
    longitude DOUBLE PRECISION,
    phone TEXT,
    logo_url TEXT,
    profile_completeness INT DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.umkm_profiles ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can manage own UMKM profiles" ON public.umkm_profiles
    FOR ALL USING (auth.uid() = user_id);
