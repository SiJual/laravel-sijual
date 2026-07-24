-- Create sijual-assets bucket if it doesn't exist
INSERT INTO storage.buckets (id, name, public) 
VALUES ('sijual-assets', 'sijual-assets', true)
ON CONFLICT (id) DO NOTHING;

-- Enable RLS on storage.objects
ALTER TABLE storage.objects ENABLE ROW LEVEL SECURITY;

-- 1. Allow public read access to all files in sijual-assets
CREATE POLICY "Public Access to sijual-assets" 
ON storage.objects FOR SELECT
USING (bucket_id = 'sijual-assets');

-- 2. Allow authenticated users to upload files to sijual-assets
CREATE POLICY "Users can upload to sijual-assets" 
ON storage.objects FOR INSERT 
TO authenticated 
WITH CHECK (bucket_id = 'sijual-assets');

-- 3. Allow users to update their own files
CREATE POLICY "Users can update their own files" 
ON storage.objects FOR UPDATE 
TO authenticated 
USING (bucket_id = 'sijual-assets' AND owner = auth.uid());

-- 4. Allow users to delete their own files
CREATE POLICY "Users can delete their own files" 
ON storage.objects FOR DELETE 
TO authenticated 
USING (bucket_id = 'sijual-assets' AND owner = auth.uid());
