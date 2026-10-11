<?php

namespace App\Http\Controllers;

use App\Models\GlossaryTerm;
use App\Support\GlossaryData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Terimler sözlüğü: listeleme, arama ve düzenleme.
 */
class GlossaryController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->input('q'));
        $cat = $request->input('kategori');

        $terms = GlossaryTerm::query()
            ->when($cat && isset(GlossaryData::CATEGORIES[$cat]), fn ($w) => $w->where('category', $cat))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('term', 'ilike', "%{$q}%")
                ->orWhere('short', 'ilike', "%{$q}%")
                ->orWhere('body', 'ilike', "%{$q}%")))
            ->get();

        // Türkçe alfabetik sıra (Ç, Ğ, İ, Ö, Ş, Ü doğru yerde); intl yoksa yaklaşık sıralama
        if (class_exists(\Collator::class)) {
            $coll = new \Collator('tr_TR');
            $terms = $terms->sort(fn ($a, $b) => $coll->compare($a->term, $b->term))->values();
        } else {
            $terms = $terms->sortBy(fn ($t) => Str::ascii(mb_strtolower($t->term)), SORT_NATURAL)->values();
        }

        $counts = GlossaryTerm::query()->selectRaw('category, COUNT(*) AS n')->groupBy('category')->pluck('n', 'category');

        return view('glossary.index', [
            'terms' => $terms,
            'grouped' => $terms->groupBy(fn ($t) => $t->initial()),
            'categories' => GlossaryData::CATEGORIES,
            'counts' => $counts,
            'q' => $q,
            'cat' => $cat,
            'total' => GlossaryTerm::count(),
        ]);
    }

    public function create(): View
    {
        return view('glossary.form', ['term' => new GlossaryTerm(['category' => 'fizik']), 'categories' => GlossaryData::CATEGORIES, 'all' => $this->allSlugs()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $term = GlossaryTerm::create($this->validated($request));

        return redirect()->to(route('glossary.index').'#'.$term->slug)->with('success', "“{$term->term}” sözlüğe eklendi.");
    }

    public function edit(GlossaryTerm $term): View
    {
        return view('glossary.form', ['term' => $term, 'categories' => GlossaryData::CATEGORIES, 'all' => $this->allSlugs()]);
    }

    public function update(Request $request, GlossaryTerm $term): RedirectResponse
    {
        $term->update($this->validated($request, $term));

        return redirect()->to(route('glossary.index').'#'.$term->slug)->with('success', "“{$term->term}” güncellendi.");
    }

    public function destroy(GlossaryTerm $term): RedirectResponse
    {
        $term->delete();

        return redirect()->route('glossary.index')->with('success', "“{$term->term}” sözlükten silindi.");
    }

    private function validated(Request $request, ?GlossaryTerm $term = null): array
    {
        if (! $request->filled('slug')) {
            $request->merge(['slug' => Str::slug((string) $request->input('term'))]);
        }

        $data = $request->validate([
            'term' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('glossary_terms', 'slug')->ignore($term?->id)],
            'category' => ['required', Rule::in(array_keys(GlossaryData::CATEGORIES))],
            'short' => ['required', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:10000'],
            'related' => ['nullable', 'array'],
            'related.*' => ['string', 'exists:glossary_terms,slug'],
        ], [], ['term' => 'terim', 'slug' => 'kısa ad', 'category' => 'kategori', 'short' => 'kısa tanım', 'body' => 'açıklama']);

        $data['related'] = array_values($data['related'] ?? []);

        return $data;
    }

    private function allSlugs(): array
    {
        return GlossaryTerm::orderBy('term')->pluck('term', 'slug')->all();
    }
}
