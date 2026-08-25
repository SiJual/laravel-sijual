<?php

namespace App\Services\AI;

use App\Models\UmkmProfile;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Client for the SiPromo pipeline running in the Python sidecar
 * (hybrid RAG + read-only tool calling + deterministic claim policy).
 *
 * The sidecar resolves the tenant from a signed JWT and re-checks it against
 * `umkm_memberships`, so this service mints the token and makes sure the
 * membership row exists before calling.
 */
class SiPromoBridgeService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.sipasar_bridge.base_url'), '/');
    }

    public function isHealthy(): bool
    {
        try {
            return Http::timeout(3)->get("{$this->baseUrl}/api/v1/health/live")->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Run the grounded generation pipeline.
     *
     * @param  array<int, string>  $productIds
     * @return array<string, mixed> the sidecar's PromotionDraftDTO
     *
     * @throws \RuntimeException on a non-2xx response or an unreachable sidecar
     */
    public function generate(
        UmkmProfile $profile,
        array $productIds,
        string $keyMessage,
        string $contentType = 'social_media',
        string $tone = 'friendly',
        string $platform = 'generic',
        string $objective = 'awareness',
        ?string $targetAudience = null,
        ?string $callToAction = null,
    ): array {
        $this->ensureMembership($profile);

        $response = Http::timeout(180)
            ->withToken($this->issueToken($profile))
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->post("{$this->baseUrl}/api/v1/promotions/generate", [
                'objective' => $objective,
                'content_type' => $contentType,
                'platform' => $platform,
                'product_ids' => array_values($productIds),
                'target_audience' => $targetAudience,
                'tone' => $tone,
                'language' => 'id',
                'key_message' => $keyMessage,
                'call_to_action' => $callToAction,
                'constraints' => [],
                'include_market_context' => true,
                'include_business_performance' => false,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException($this->readError($response->json(), $response->status()));
        }

        return $response->json();
    }

    /**
     * The sidecar signs with the same secret Laravel uses for its own tokens.
     */
    private function issueToken(UmkmProfile $profile): string
    {
        $now = time();

        return JWT::encode([
            'sub' => (string) $profile->user_id,
            'user_id' => (string) $profile->user_id,
            'umkm_id' => (string) $profile->id,
            'role' => 'owner',
            'iat' => $now,
            'exp' => $now + 900,
        ], (string) config('jwt.secret', env('JWT_SECRET')), 'HS256');
    }

    /**
     * Tenant access is verified against `umkm_memberships`, a table owned by
     * the sidecar's migrations. Laravel's own onboarding doesn't write to it,
     * so mirror the ownership here.
     */
    private function ensureMembership(UmkmProfile $profile): void
    {
        $exists = DB::table('umkm_memberships')
            ->where('umkm_id', $profile->id)
            ->where('user_id', $profile->user_id)
            ->where('status', 'active')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('umkm_memberships')->updateOrInsert(
            ['umkm_id' => $profile->id, 'user_id' => $profile->user_id],
            ['id' => (string) Str::uuid(), 'role' => 'owner', 'status' => 'active', 'created_at' => now()],
        );
    }


    /**
     * The claim policy speaks in English rule names. Turn each violation into
     * something an UMKM owner can act on.
     */
    private function explainClaimViolation(string $message): string
    {
        $reasons = [];

        foreach (explode(';', $message) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $quoted = null;
            if (preg_match("/'([^']+)'/", $part, $m)) {
                $quoted = $m[1];
            }

            $reasons[] = match (true) {
                str_contains($part, 'discount claim without supporting data') =>
                    'Copy menyebut diskon, tetapi tidak ada data promo di katalog. Hapus angka diskon dari prompt, atau daftarkan harga promonya dulu di SiStok.',
                str_contains($part, 'numeric claim not grounded') =>
                    "Angka \"{$quoted}\" tidak ada sumbernya di data usaha. Hindari menyebut persentase atau jumlah yang belum tercatat.",
                str_contains($part, 'stock claim in copy') =>
                    'Copy menyebut jumlah stok. Angka stok tidak boleh dipakai di materi promosi karena cepat berubah.',
                str_contains($part, 'claim not grounded') =>
                    "Klaim \"{$quoted}\" tidak didukung data produk maupun riset pasar yang tersimpan.",
                str_contains($part, 'superlative') =>
                    'Copy memakai klaim superlatif (misal "terbaik", "nomor satu") yang tidak bisa dibuktikan.',
                str_contains($part, 'certification claim') =>
                    "Copy menyebut sertifikasi \"{$quoted}\" yang belum terdaftar sebagai bukti.",
                str_contains($part, 'no selected product name') =>
                    'Copy tidak menyebut nama produk yang dipilih. Coba pertegas produknya di prompt.',
                str_contains($part, 'external URL') =>
                    "Copy memuat tautan \"{$quoted}\" yang dikarang model.",
                str_contains($part, 'hashtag references competitor') =>
                    "Tagar \"{$quoted}\" menyebut merek kompetitor.",
                str_contains($part, 'purchase CTA') =>
                    'Ajakan membeli dipakai padahal produk sedang habis. Pilih produk yang stoknya tersedia.',
                default => $part,
            };
        }

        if (! $reasons) {
            return 'Draft ditolak kebijakan klaim: ' . $message;
        }

        return 'Draft ditolak karena ada klaim tanpa bukti — ini penjaga agar AI tidak mengarang. ' . implode(' ', $reasons);
    }

    /**
     * The sidecar reports failures as {"error": {"code": ..., "message": ...}}.
     */
    private function readError(mixed $body, int $status): string
    {
        $error = is_array($body) ? ($body['error'] ?? null) : null;

        if (is_array($error)) {
            $code = $error['code'] ?? 'ERROR';
            $message = $error['message'] ?? 'Permintaan ditolak pipeline AI.';

            return match ($code) {
                'PRODUCT_NOT_FOUND' => 'Produk yang dipilih tidak ditemukan di SiStok.',
                'CLAIM_VIOLATION' => $this->explainClaimViolation($message),
                'UNAUTHENTICATED', 'FORBIDDEN' => 'Akses ke pipeline AI ditolak: ' . $message,
                default => $message,
            };
        }

        return 'Pipeline SiPromo mengembalikan status ' . $status . '.';
    }
}
