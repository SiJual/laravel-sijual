<?php

namespace App\Http\Controllers\SiPromo;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentRequest;
use App\Models\ContentAsset;
use App\Models\UmkmProfile;
use App\Services\AI\CaptionGeneratorService;
use App\Services\AI\ImageGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class GenerateController extends Controller
{
    public function __construct(
        private CaptionGeneratorService $captionService,
        private ImageGenerationService $imageService
    ) {}

    public function create(ContentRequest $request): RedirectResponse
    {
        // Image generation (OpenAI) can take a while — avoid a 30s PHP timeout.
        set_time_limit(90);

        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $aiResult = $this->captionService->generate($profile->business_name, $request->prompt, $request->content_type);
        $imageUrl = $this->imageService->generateImage($request->prompt);

        $content = ContentAsset::create([
            'umkm_id' => $profile->id,
            'title' => 'Promosi - ' . substr($request->prompt, 0, 30),
            'content_type' => $request->content_type,
            'prompt' => $request->prompt,
            'generated_text' => $aiResult['text'],
            'caption' => $aiResult['caption'],
            'hashtags' => $aiResult['hashtags'],
            'generated_image_url' => $imageUrl,
            'status' => 'published',
        ]);

        return redirect()->route('sipromo.preview', $content->id)->with('success', 'Konten promosi AI berhasil dibuat!');
    }
}
