INSERT INTO public.categories (name, type, icon, sort_order, is_system) VALUES
('Penjualan Produk', 'income', 'shopping-bag', 1, true),
('Penjualan Jasa', 'income', 'briefcase', 2, true),
('Pendapatan Lain-lain', 'income', 'cash', 3, true),
('Bahan Baku & Material', 'expense', 'box', 1, true),
('Operasional & Utilitas', 'expense', 'lightning-bolt', 2, true),
('Gaji Karyawan', 'expense', 'users', 3, true),
('Pemasaran & Iklan', 'expense', 'megaphone', 4, true),
('Sewa Tempat', 'expense', 'home', 5, true),
('Transportasi & Logistik', 'expense', 'truck', 6, true),
('Pengeluaran Lain-lain', 'expense', 'tag', 7, true)
ON CONFLICT DO NOTHING;
