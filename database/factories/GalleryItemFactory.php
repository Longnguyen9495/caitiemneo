<?php

namespace Database\Factories;

use App\Enums\GalleryMediaType;
use App\Models\GalleryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryItem>
 */
class GalleryItemFactory extends Factory
{
    protected $model = GalleryItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'type' => GalleryMediaType::Photo,
            'path' => 'storage/gallery/'.$slug.'-1440.webp',
            'sources' => [
                480 => 'storage/gallery/'.$slug.'-480.webp',
                960 => 'storage/gallery/'.$slug.'-960.webp',
                1440 => 'storage/gallery/'.$slug.'-1440.webp',
            ],
            'width' => 1440,
            'height' => 1920,
            'original_name' => $slug.'.jpg',
            'byte_size' => fake()->numberBetween(20_000, 200_000),
            'uploaded_by' => null,
        ];
    }

    public function video(): static
    {
        return $this->state(function (): array {
            $slug = fake()->unique()->slug(2);

            return [
                'type' => GalleryMediaType::Video,
                'path' => 'storage/gallery/'.$slug.'.mp4',
                'sources' => null,
                'width' => null,
                'height' => null,
                'original_name' => $slug.'.mp4',
            ];
        });
    }
}
