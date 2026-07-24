CREATE TABLE IF NOT EXISTS public.transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    umkm_id UUID NOT NULL REFERENCES public.umkm_profiles(id) ON DELETE CASCADE,
    outlet_id UUID REFERENCES public.outlets(id) ON DELETE SET NULL,
    category_id UUID REFERENCES public.categories(id) ON DELETE SET NULL,
    type TEXT NOT NULL CHECK (type IN ('income', 'expense')),
    amount BIGINT NOT NULL DEFAULT 0,
    description TEXT,
    notes TEXT,
    source TEXT DEFAULT 'manual' CHECK (source IN ('voice', 'manual', 'qris')),
    payment_method TEXT DEFAULT 'cash',
    merchant_name TEXT,
    ai_metadata JSONB DEFAULT '{}'::jsonb,
    is_verified BOOLEAN DEFAULT true,
    transaction_date DATE NOT NULL DEFAULT CURRENT_DATE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.transactions ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can manage transactions of their UMKM" ON public.transactions
    FOR ALL USING (
        umkm_id IN (SELECT id FROM public.umkm_profiles WHERE user_id = auth.uid())
    );
