-- Patch 019: Fix RLS Storage to allow service_role to manage files
-- This fixes the issue where AI backend uploads via service role get NULL owner and can't be read/updated properly by RLS.

-- Storage Policies for 'content_assets' bucket

-- Re-create INSERT policy to allow service_role
DROP POLICY IF EXISTS "Users can upload their own assets" ON storage.objects;
CREATE POLICY "Users can upload their own assets" 
ON storage.objects FOR INSERT 
TO public
WITH CHECK (
    bucket_id = 'content_assets' AND 
    (auth.uid()::text = (storage.foldername(name))[1] OR auth.role() = 'service_role')
);

-- Re-create SELECT policy to allow service_role and public access
DROP POLICY IF EXISTS "Assets are publicly accessible" ON storage.objects;
CREATE POLICY "Assets are publicly accessible" 
ON storage.objects FOR SELECT 
TO public
USING (bucket_id = 'content_assets');

-- Re-create UPDATE policy
DROP POLICY IF EXISTS "Users can update their own assets" ON storage.objects;
CREATE POLICY "Users can update their own assets" 
ON storage.objects FOR UPDATE 
TO public
USING (
    bucket_id = 'content_assets' AND 
    (auth.uid()::text = (storage.foldername(name))[1] OR auth.role() = 'service_role')
);

-- Re-create DELETE policy
DROP POLICY IF EXISTS "Users can delete their own assets" ON storage.objects;
CREATE POLICY "Users can delete their own assets" 
ON storage.objects FOR DELETE 
TO public
USING (
    bucket_id = 'content_assets' AND 
    (auth.uid()::text = (storage.foldername(name))[1] OR auth.role() = 'service_role')
);
