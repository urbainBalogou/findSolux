<?php

namespace App\Services\Photos;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\DriverException;
use Intervention\Image\ImageManager;

/**
 * Photos légères pour les réseaux lents : redimensionnées, converties en WebP,
 * avec une vignette pour les listes. Les métadonnées EXIF (dont la position
 * GPS de la prise de vue) sont supprimées.
 */
class PhotoStorage
{
    /**
     * @return array{path: string, thumbnail_path: string, width: int, height: int, size_bytes: int}
     *
     * @throws ValidationException si le fichier n'est pas une image lisible
     */
    public function store(UploadedFile $file, string $directory): array
    {
        $config = config('findsolux.photos');
        // L'orientation EXIF est appliquée si l'extension PHP « exif » est active.
        $manager = new ImageManager(Driver::class, autoOrientation: true, strip: true);

        try {
            $image = $manager->decodePath($file->getRealPath());
        } catch (DriverException) {
            throw ValidationException::withMessages(['photo' => 'Cette image est illisible. Essayez une autre photo.']);
        }

        $image->scaleDown($config['max_dimension'], $config['max_dimension']);
        $photo = (string) $image->encode(new WebpEncoder($config['webp_quality']));
        $width = $image->width();
        $height = $image->height();

        $image->scaleDown($config['thumbnail_dimension'], $config['thumbnail_dimension']);
        $thumbnail = (string) $image->encode(new WebpEncoder($config['webp_quality']));

        $name = Str::uuid()->toString();
        $path = "{$directory}/{$name}.webp";
        $thumbnailPath = "{$directory}/{$name}-thumb.webp";

        $this->disk()->put($path, $photo);
        $this->disk()->put($thumbnailPath, $thumbnail);

        return [
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'width' => $width,
            'height' => $height,
            'size_bytes' => strlen($photo),
        ];
    }

    public function delete(string ...$paths): void
    {
        $this->disk()->delete($paths);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('findsolux.photos.disk'));
    }
}
