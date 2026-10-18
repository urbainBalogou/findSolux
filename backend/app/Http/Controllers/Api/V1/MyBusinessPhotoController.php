<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MyBusinessResource;
use App\Services\Photos\PhotoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MyBusinessPhotoController extends Controller
{
    public function __construct(private readonly PhotoStorage $photos) {}

    public function store(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        $max = config('findsolux.photos.max_per_business');

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:'.config('findsolux.photos.max_upload_kb')],
        ]);

        if ($business->photos()->count() >= $max) {
            throw ValidationException::withMessages([
                'photo' => "Vous avez atteint le maximum de {$max} photos. Supprimez-en une pour en ajouter une autre.",
            ]);
        }

        $stored = $this->photos->store($request->file('photo'), "businesses/{$business->id}");
        $business->photos()->create([
            ...$stored,
            'position' => ($business->photos()->max('position') ?? -1) + 1,
        ]);

        return (new MyBusinessResource($business->load(MyBusinessResource::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Nouvel ordre d'affichage : la première photo est la photo principale.
     */
    public function reorder(Request $request): MyBusinessResource
    {
        $business = $request->user()->business;
        $photoIds = $business->photos()->pluck('id')->all();

        $request->validate([
            'photo_ids' => ['required', 'array', 'size:'.count($photoIds)],
            'photo_ids.*' => ['integer', 'distinct', Rule::in($photoIds)],
        ], [
            'photo_ids.size' => 'La liste doit contenir toutes vos photos.',
        ]);

        DB::transaction(function () use ($request, $business) {
            foreach ($request->input('photo_ids') as $position => $id) {
                $business->photos()->whereKey($id)->update(['position' => $position]);
            }
        });

        return new MyBusinessResource($business->load(MyBusinessResource::RELATIONS));
    }

    public function destroy(Request $request, int $photo): MyBusinessResource
    {
        $business = $request->user()->business;
        $model = $business->photos()->findOrFail($photo);

        $model->delete();
        $this->photos->delete($model->path, $model->thumbnail_path);

        // Positions recalculées sans trou.
        $business->photos()->get()->each(fn ($remaining, $position) => $remaining->update(['position' => $position]));

        return new MyBusinessResource($business->load(MyBusinessResource::RELATIONS));
    }
}
