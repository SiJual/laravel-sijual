<?php

namespace App\Http\Controllers\SiPromo;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentRequest;
use App\Models\ContentAsset;
use App\Models\Product;
use App\Models\UmkmProfile;
use App\Services\AI\SiPromoBridgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GenerateController extends Controller
{
    public function __construct(private SiPromoBridgeService $bridge) {}

    public function create(ContentRequest $request): RedirectResponse
    {
        // The pipeline runs a bounded tool loop and may render a poster, so it
        // legitimately takes longer than PHP's default 30s limit.
        set_time_limit(240);

        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        if (! $this->bridge->isHealthy()) {
            return back()->withInput()->withErrors([
                'prompt' => 'Layanan AI SiPromo (Python sidecar) sedang tidak aktif. Jalankan "docker compose up" atau start service-nya.',
            ]);
        }

        // The pipeline grounds every claim on real catalogue rows, so at least
        // one product is required.
        $productIds = $this->resolveProductIds($request, $profile->id);

        if (empty($productIds)) {
            return back()->withInput()->withErrors([
                'product_ids' => 'Pilih minimal satu produk dari SiStok — konten AI digrounding pada data produk asli.',
            ]);
        }

        $attempt = 0;
        $draft = null;
        $lastError = null;

        // The claim policy can reject a draft when the model reaches for an
        // unsupported claim. That is not a hard failure — a second pass usually
        // produces a compliant draft.
        while ($attempt < 2 && $draft === null) {
            $attempt++;
            try {
                $draft = $this->bridge->generate(
                    profile: $profile,
                    productIds: $productIds,
                    keyMessage: $request->prompt,
                    contentType: $request->content_type,
                    tone: $this->mapTone($request->tone),
                    platform: $request->input('platform', 'generic'),
                    objective: $request->input('objective', 'awareness'),
                    targetAudience: $request->input('target_audience'),
                    callToAction: $request->input('call_to_action'),
                );
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();

                // Only a claim rejection is worth a second pass; anything else
                // (sidecar down, product missing) will fail the same way twice.
                if (! str_contains($lastError, 'klaim tanpa bukti')) {
                    break;
                }
            }
        }

        if ($draft === null) {
            return back()->withInput()->withErrors(['prompt' => $lastError ?? 'Pipeline AI gagal membuat draft.']);
        }

        // The pipeline persists the draft itself (draft + sources + trace +
        // revision) in its own request/transaction. Its HTTP response can
        // arrive slightly before that write is visible to our connection, so
        // give it a few short retries before treating the row as missing —
        // otherwise we'd create a duplicate content_assets row and orphan the
        // sidecar's provenance data (generation_runs/sources/revisions).
        $content = null;
        if (! empty($draft['content_id'])) {
            for ($i = 0; $i < 5; $i++) {
                $content = ContentAsset::find($draft['content_id']);
                if ($content) {
                    break;
                }
                usleep(150_000);
            }
        }

        $payload = [
            'umkm_id' => $profile->id,
            'title' => $draft['title'] ?? ('Promosi - ' . Str::limit($request->prompt, 30, '')),
            'content_type' => $request->content_type,
            'prompt' => $request->prompt,
            'generated_text' => $draft['primary_copy'] ?? '',
            'caption' => $draft['caption'] ?? '',
            'hashtags' => $draft['hashtags'] ?? [],
            'generated_image_url' => $draft['image_url'] ?? null,
            'version' => $draft['version'] ?? 1,
            // The pipeline always flags drafts for human review, so nothing is
            // published straight from a model output.
            'status' => 'draft',
            'brand_metadata' => [
                'pipeline' => 'ai-sipromo-python',
                'content_id' => $draft['content_id'] ?? null,
                'generation_run_id' => $draft['generation_run_id'] ?? null,
                'call_to_action' => $draft['call_to_action'] ?? null,
                'visual_brief' => $draft['visual_brief'] ?? null,
                'target_audience_summary' => $draft['target_audience_summary'] ?? null,
                'rationale' => $draft['rationale'] ?? [],
                'claims' => $draft['claims'] ?? [],
                'evidence' => $draft['evidence'] ?? [],
                'warnings' => $draft['warnings'] ?? [],
                'requires_human_review' => $draft['requires_human_review'] ?? true,
                'product_ids' => $productIds,
            ],
        ];

        if ($content) {
            $content->update($payload);
        } else {
            $content = ContentAsset::create($payload);
        }

        $message = 'Konten promosi berhasil dibuat oleh pipeline AI SiPromo.';
        if (! empty($draft['warnings'])) {
            $message .= ' Ada ' . count($draft['warnings']) . ' catatan yang perlu ditinjau.';
        }

        return redirect()->route('sipromo.preview', $content->id)->with('success', $message);
    }

    /**
     * Accepts an explicit selection; falls back to the products whose names
     * appear in the prompt, then to the best-stocked item in the catalogue.
     *
     * @return array<int, string>
     */
    private function resolveProductIds(ContentRequest $request, string $umkmId): array
    {
        $selected = array_filter((array) $request->input('product_ids', []));

        if ($selected) {
            return Product::where('umkm_id', $umkmId)
                ->whereIn('id', $selected)
                ->limit(10)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        $catalogue = Product::where('umkm_id', $umkmId)->get();
        $prompt = Str::lower($request->prompt);

        $mentioned = $catalogue
            ->filter(fn ($p) => Str::contains($prompt, Str::lower($p->name)))
            ->take(10);

        if ($mentioned->isNotEmpty()) {
            return $mentioned->pluck('id')->map(fn ($id) => (string) $id)->all();
        }

        $fallback = $catalogue->sortByDesc('stock_level')->first();

        return $fallback ? [(string) $fallback->id] : [];
    }

    /**
     * The UI collects free-text tone; the pipeline takes a fixed enum.
     */
    private function mapTone(?string $tone): string
    {
        return match (Str::lower((string) $tone)) {
            'profesional', 'professional', 'formal' => 'professional',
            'ceria', 'playful', 'santai', 'kasual' => 'playful',
            'premium', 'elegan', 'mewah' => 'premium',
            'edukatif', 'educational', 'informatif' => 'educational',
            default => 'friendly',
        };
    }
}
