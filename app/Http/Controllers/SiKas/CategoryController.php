<?php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\UmkmProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:income,expense',
            'icon' => 'nullable|string|max:50',
        ]);

        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $category = Category::create([
            'id' => (string) Str::uuid(),
            'umkm_id' => $profile->id,
            'name' => strip_tags(trim($request->name)),
            'type' => $request->type,
            'icon' => $request->icon ?? ($request->type === 'income' ? 'cash' : 'tag'),
            'sort_order' => 99,
            'is_system' => false,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Kategori berhasil ditambahkan.',
                'data' => $category,
            ]);
        }

        return back()->with('success', 'Kategori baru berhasil ditambahkan.');
    }
}
