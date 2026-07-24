-- ============================================================
-- FILE: 018_schema_patch_audit.sql
-- PURPOSE: Perbaikan skema database berdasarkan audit mendalam
-- DATE: 2026-07-24
-- ============================================================

-- ===========================================
-- FIX #5: UNIQUE constraint pada umkm_profiles.user_id
-- Satu user hanya boleh punya satu UMKM profile (one-to-one)
-- ===========================================
ALTER TABLE public.umkm_profiles
    ADD CONSTRAINT unique_user_umkm UNIQUE (user_id);

-- ===========================================
-- FIX #2: Kolom fisik agregasi pada tabel reports
-- PRD mengharuskan total_income, total_expense, net_profit
-- sebagai kolom fisik agar bisa di-index dan di-query langsung
-- ===========================================
ALTER TABLE public.reports
    ADD COLUMN IF NOT EXISTS total_income BIGINT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS total_expense BIGINT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS net_profit BIGINT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS transaction_count INT DEFAULT 0;

-- Index untuk query agregasi per periode
CREATE INDEX IF NOT EXISTS idx_reports_period ON public.reports(umkm_id, type, period_start);

-- ===========================================
-- FIX #3: Relasi demographics → market_analyses
-- Data demografi dihasilkan oleh proses analisis, harus terikat
-- ke analysis_id (sama seperti competitors)
-- ===========================================
ALTER TABLE public.demographics
    ADD COLUMN IF NOT EXISTS analysis_id UUID REFERENCES public.market_analyses(id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_demographics_analysis_id ON public.demographics(analysis_id);

-- ===========================================
-- FIX #4: Auth sync trigger (auth.users → public.users)
-- Agar perubahan email/avatar di Supabase Auth otomatis
-- tersinkronisasi ke tabel public.users
-- ===========================================

-- Function untuk menangani INSERT baru (user register)
CREATE OR REPLACE FUNCTION public.handle_new_auth_user()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO public.users (id, email, full_name, avatar_url, role)
    VALUES (
        NEW.id,
        NEW.email,
        COALESCE(NEW.raw_user_meta_data->>'full_name', NEW.raw_user_meta_data->>'name', NEW.email),
        NEW.raw_user_meta_data->>'avatar_url',
        'owner'
    )
    ON CONFLICT (id) DO UPDATE SET
        email = EXCLUDED.email,
        full_name = COALESCE(EXCLUDED.full_name, public.users.full_name),
        avatar_url = COALESCE(EXCLUDED.avatar_url, public.users.avatar_url),
        updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function untuk menangani UPDATE (user ubah profil via Supabase)
CREATE OR REPLACE FUNCTION public.handle_auth_user_update()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE public.users
    SET
        email = NEW.email,
        full_name = COALESCE(NEW.raw_user_meta_data->>'full_name', NEW.raw_user_meta_data->>'name', public.users.full_name),
        avatar_url = COALESCE(NEW.raw_user_meta_data->>'avatar_url', public.users.avatar_url),
        updated_at = now()
    WHERE id = NEW.id;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Trigger: saat user baru registrasi via Supabase Auth
DROP TRIGGER IF EXISTS on_auth_user_created ON auth.users;
CREATE TRIGGER on_auth_user_created
    AFTER INSERT ON auth.users
    FOR EACH ROW EXECUTE FUNCTION public.handle_new_auth_user();

-- Trigger: saat user update profil via Supabase Auth
DROP TRIGGER IF EXISTS on_auth_user_updated ON auth.users;
CREATE TRIGGER on_auth_user_updated
    AFTER UPDATE ON auth.users
    FOR EACH ROW EXECUTE FUNCTION public.handle_auth_user_update();

-- ===========================================
-- FIX #6: CHECK constraint pada products.category
-- Membatasi nilai kategori produk yang valid
-- ===========================================
ALTER TABLE public.products
    DROP CONSTRAINT IF EXISTS products_category_check;

ALTER TABLE public.products
    ADD CONSTRAINT products_category_check
    CHECK (category IS NULL OR category IN ('textiles', 'handicrafts', 'food_bev', 'services', 'other'));
