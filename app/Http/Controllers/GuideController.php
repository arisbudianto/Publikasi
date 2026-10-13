<?php

namespace App\Http\Controllers;

use App\Models\M18Guide;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuideController extends Controller
{
    public function index()
    {
        return view('guides.index', ['guides' => M18Guide::whereNotNull('published_at')
            ->where('published_at', '<=', now())->latest('published_at')->paginate(12)]);
    }

    public function show(string $slug)
    {
        $guide = M18Guide::where('slug', $slug)->whereNotNull('published_at')
            ->where('published_at', '<=', now())->firstOrFail();

        return view('guides.show', compact('guide'));
    }

    public function admin()
    {
        return response()->view('guides.admin', ['guides' => M18Guide::latest('id')->paginate(20)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function create()
    {
        return response()->view('guides.form', ['guide' => new M18Guide])->header('Cache-Control', 'no-store, private');
    }

    public function edit(M18Guide $guide)
    {
        return response()->view('guides.form', compact('guide'))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $guide = M18Guide::create($this->attributes($data, null, $request->user()->id));

        return redirect()->route('admin.guides.edit', $guide)->with('success', 'Panduan disimpan.');
    }

    public function update(Request $request, M18Guide $guide)
    {
        $data = $this->validated($request, $guide);
        $guide->update($this->attributes($data, $guide, $request->user()->id));

        return redirect()->route('admin.guides.edit', $guide)->with('success', 'Panduan diperbarui.');
    }

    private function validated(Request $request, ?M18Guide $guide = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('m18_guides', 'slug')->ignore($guide?->id)],
            'title' => 'required|string|min:10|max:180',
            'body' => 'required|string|min:100|max:20000',
            'reviewed_at' => 'nullable|required_if:status,published|date_format:Y-m-d|before_or_equal:today',
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
    }

    private function attributes(array $data, ?M18Guide $guide, int $authorId): array
    {
        return [
            'slug' => $data['slug'], 'title' => $data['title'], 'body' => $data['body'],
            'reviewed_at' => $data['reviewed_at'] ?? null,
            'published_at' => $data['status'] === 'published' ? ($guide?->published_at ?? now()) : null,
            'author_id' => $guide?->author_id ?? $authorId,
        ];
    }
}
