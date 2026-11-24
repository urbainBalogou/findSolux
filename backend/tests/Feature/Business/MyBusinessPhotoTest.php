<?php

namespace Tests\Feature\Business;

use App\Models\Business;
use App\Models\BusinessPhoto;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MyBusinessPhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(ReferenceDataSeeder::class);
        $this->business = Business::factory()->create();
        $this->owner = $this->business->owner;
    }

    private function upload(string $name = 'atelier.jpg', int $width = 3000, int $height = 2000): mixed
    {
        return $this->actingAs($this->owner)->postJson('/api/v1/my-business/photos', [
            'photo' => UploadedFile::fake()->image($name, $width, $height),
        ]);
    }

    public function test_a_photo_is_resized_converted_and_thumbnailed(): void
    {
        $response = $this->upload()->assertCreated()->assertJsonCount(1, 'data.photos');

        $photo = BusinessPhoto::firstOrFail();
        $this->assertSame([1280, 853], [$photo->width, $photo->height]);
        $this->assertStringEndsWith('.webp', $photo->path);
        Storage::disk('public')->assertExists([$photo->path, $photo->thumbnail_path]);

        [$thumbWidth] = getimagesizefromstring(Storage::disk('public')->get($photo->thumbnail_path));
        $this->assertSame(400, $thumbWidth);
        $this->assertStringContainsString($photo->thumbnail_path, $response->json('data.photos.0.thumbnail_url'));
    }

    public function test_small_photos_are_not_enlarged(): void
    {
        $this->upload('petite.png', 640, 480)->assertCreated();

        $this->assertSame([640, 480], [BusinessPhoto::first()->width, BusinessPhoto::first()->height]);
    }

    public function test_only_images_are_accepted(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/my-business/photos', [
            'photo' => UploadedFile::fake()->create('devis.pdf', 100, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_the_number_of_photos_is_capped(): void
    {
        config(['findsolux.photos.max_per_business' => 2]);

        $this->upload('1.jpg', 100, 100)->assertCreated();
        $this->upload('2.jpg', 100, 100)->assertCreated();
        $this->upload('3.jpg', 100, 100)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo' => 'maximum de 2 photos']);
    }

    public function test_photos_can_be_reordered(): void
    {
        $this->upload('1.jpg', 100, 100);
        $this->upload('2.jpg', 100, 100);
        $this->upload('3.jpg', 100, 100);
        [$first, $second, $third] = BusinessPhoto::orderBy('id')->pluck('id')->all();

        $this->actingAs($this->owner)
            ->putJson('/api/v1/my-business/photos/order', ['photo_ids' => [$third, $first, $second]])
            ->assertOk()
            ->assertJsonPath('data.photos.0.id', $third)
            ->assertJsonPath('data.photos.2.id', $second);

        $this->actingAs($this->owner)
            ->putJson('/api/v1/my-business/photos/order', ['photo_ids' => [$third, $first]])
            ->assertUnprocessable();
    }

    public function test_a_photo_is_deleted_with_its_files(): void
    {
        $this->upload('1.jpg', 100, 100);
        $this->upload('2.jpg', 100, 100);
        $photo = BusinessPhoto::orderBy('id')->first();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/my-business/photos/{$photo->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.photos');

        Storage::disk('public')->assertMissing([$photo->path, $photo->thumbnail_path]);
        $this->assertSame(0, BusinessPhoto::first()->position);
    }

    public function test_a_photo_of_another_business_cannot_be_deleted(): void
    {
        $other = Business::factory()->create();
        $photo = $other->photos()->create([
            'path' => 'x.webp', 'thumbnail_path' => 'x-thumb.webp', 'width' => 1, 'height' => 1, 'size_bytes' => 1,
        ]);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/my-business/photos/{$photo->id}")
            ->assertNotFound();

        $this->assertModelExists($photo);
    }
}
