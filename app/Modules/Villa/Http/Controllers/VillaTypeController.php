<?php

namespace App\Modules\Villa\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Media;
use App\Models\Property;
use App\Models\VillaType;
use App\Modules\Core\Services\AuditService;
use App\Modules\Core\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VillaTypeController extends Controller
{
    public function index()
    {
        return view('admin.villas.types', ['types' => VillaType::withCount('villas')->with(['media', 'facilities'])->orderBy('sort_order')->get(), 'facilities' => Facility::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.villas.type-form', ['type' => new VillaType(['is_active' => true, 'max_adults' => 2, 'base_occupancy' => 2, 'bedrooms' => 1, 'bathrooms' => 1]), 'facilities' => Facility::orderBy('category')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $type = VillaType::create($data + ['property_id' => Property::current()->id, 'slug' => $this->slug($data['name'])]);
        $type->facilities()->sync($request->input('facilities', []));
        AuditService::log('villas', 'type_created', $type, $type->name);
        return redirect()->route('admin.villa-types.edit', $type)->with('success', 'Villa type created. Add photos below.');
    }

    public function edit(VillaType $villaType)
    {
        return view('admin.villas.type-form', ['type' => $villaType->load(['media', 'facilities']), 'facilities' => Facility::orderBy('category')->orderBy('name')->get()]);
    }

    public function update(Request $request, VillaType $villaType)
    {
        $data = $this->validated($request);
        $villaType->fill($data);
        AuditService::logChanges('villas', $villaType, 'type_updated');
        $villaType->save();
        $villaType->facilities()->sync($request->input('facilities', []));
        return redirect()->route('admin.villa-types.index')->with('success', $villaType->name.' saved.');
    }

    public function destroy(VillaType $villaType)
    {
        if ($villaType->villas()->exists()) {
            return back()->with('error', 'Move or archive the villas of this type first.');
        }
        $villaType->delete();
        return back()->with('success', 'Villa type archived.');
    }

    public function addMedia(Request $request, VillaType $villaType)
    {
        $request->validate([
            'photos' => ['nullable', 'array', 'max:12'], 'photos.*' => ['file', 'max:'.config('vaasal.security.upload_max_kb')],
            'video_url' => ['nullable', 'url', 'max:300', 'regex:/^https:\/\/(www\.)?(youtube\.com|youtu\.be|vimeo\.com)\//'],
        ], ['video_url.regex' => 'Use a YouTube or Vimeo link.']);
        $n = $villaType->media()->count();
        foreach ($request->file('photos', []) as $file) {
            $villaType->media()->create(['type' => 'image', 'path' => UploadService::image($file, 'villa-types'), 'alt' => $villaType->name, 'sort_order' => $n++, 'is_cover' => $n === 1]);
        }
        if ($request->filled('video_url')) {
            $villaType->media()->create(['type' => 'video', 'path' => $request->input('video_url'), 'title' => $villaType->name.' video tour', 'sort_order' => $n++]);
        }
        return back()->with('success', 'Media added.');
    }

    public function deleteMedia(Media $media)
    {
        if (! str_starts_with($media->path, 'http')) {
            \Storage::disk('public')->delete($media->path);
        }
        $media->delete();
        return back()->with('success', 'Media removed.');
    }

    public function coverMedia(Media $media)
    {
        Media::where('mediable_type', $media->mediable_type)->where('mediable_id', $media->mediable_id)->update(['is_cover' => false]);
        $media->update(['is_cover' => true]);
        return back()->with('success', 'Cover photo updated.');
    }

    public function storeFacility(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:facilities,name'], 'category' => ['required', 'string', 'max:40'], 'icon' => ['nullable', 'string', 'max:40']]);
        Facility::create($data + ['icon' => $data['icon'] ?? 'check']);
        return back()->with('success', 'Facility added.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'],
            'max_adults' => ['required', 'integer', 'min:1', 'max:12'],
            'max_children' => ['required', 'integer', 'min:0', 'max:8'],
            'base_occupancy' => ['required', 'integer', 'min:1', 'max:12'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:10'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:10'],
            'size_sqm' => ['nullable', 'integer', 'min:0'],
            'bed_configuration' => ['nullable', 'string', 'max:120'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'extra_adult_rate' => ['nullable', 'numeric', 'min:0'],
            'extra_child_rate' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer'],
            'facilities' => ['nullable', 'array'], 'facilities.*' => ['exists:facilities,id'],
        ]);
        unset($data['facilities']);
        return $data + ['is_active' => $request->boolean('is_active'), 'extra_adult_rate' => $data['extra_adult_rate'] ?? 0, 'extra_child_rate' => $data['extra_child_rate'] ?? 0, 'sort_order' => $data['sort_order'] ?? 0];
    }

    private function slug(string $name): string
    {
        $slug = Str::slug($name);
        $i = 2;
        while (VillaType::withTrashed()->where('slug', $slug)->exists()) {
            $slug = Str::slug($name).'-'.$i++;
        }
        return $slug;
    }
}
