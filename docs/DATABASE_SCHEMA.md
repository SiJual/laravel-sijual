# SiJual — Database Schema Documentation

Skema database SiJual diimplementasikan di atas **Supabase PostgreSQL** dengan **Row Level Security (RLS)** aktif di seluruh tabel.

## Table Inventory & Descriptions

1. **`users`**: Data otentikasi & akun pengguna (`id`, `email`, `role`, `full_name`, `avatar_url`).
2. **`umkm_profiles`**: Profil bisnis UMKM multi-tenant (`id`, `user_id`, `business_name`, `business_type`, `phone`, `address`).
3. **`outlets`**: Cabang / lokasi outlet fisik (`id`, `umkm_id`, `name`, `address`, `latitude`, `longitude`, `is_primary`).
4. **`categories`**: Kategori transaksi keuangan (`id`, `umkm_id`, `name`, `type`, `is_system`).
5. **`transactions`**: Pencatatan keuangan SiKas (`id`, `umkm_id`, `outlet_id`, `category_id`, `type`, `amount`, `description`, `source`, `payment_method`, `transaction_date`).
6. **`reports`**: Rekapitulasi agregat keuangan (`id`, `umkm_id`, `period_type`, `total_income`, `total_expense`, `net_profit`).
7. **`market_analyses`**: Hasil riset geodemografis SiPasar (`id`, `umkm_id`, `location_query`, `latitude`, `longitude`, `radius_km`, `market_fit_score`, `demographic_data`).
8. **`competitors`**: Data usaha sejenis yang terdeteksi (`id`, `analysis_id`, `name`, `rating`, `review_count`, `sentiment`, `latitude`, `longitude`).
9. **`products`**: Katalog stok inventori SiStok (`id`, `umkm_id`, `sku`, `name`, `category`, `price`, `stock_level`, `status`).
10. **`content_assets`**: Hasil generasi konten promosi AI SiPromo (`id`, `umkm_id`, `title`, `content_type`, `prompt`, `caption`, `hashtags`, `generated_image_url`).

## Composite Indexes
- `idx_transactions_umkm_date` ON `transactions` (`umkm_id`, `transaction_date` DESC)
- `idx_products_umkm_status` ON `products` (`umkm_id`, `status`)
- `idx_competitors_analysis` ON `competitors` (`analysis_id`)
