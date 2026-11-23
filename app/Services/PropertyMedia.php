<?php

namespace App\Services;

use App\Exceptions\LimitExceededException;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyVideo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyMedia
{
    public function __construct(private ImageProcessor $images, private PermissionService $permissions)
    {
    }

    /** Add a photo or floor plan. Honours the poster's photo_limit feature. */
    public function addImage(Property $property, UploadedFile $file, string $type = 'photo'): PropertyImage
    {
        $this->assertImageCapacity($property, $type);

        $disk = Storage::disk('uploads');
        $dir = "properties/{$property->id}";
        $name = Str::uuid()->toString(); // the uploaded file name is never used
        $processed = $this->images->process($file->get());

        if ($processed) {
            $path = "{$dir}/{$name}.jpg";
            $thumb = "{$dir}/thumbs/{$name}.jpg";
            $disk->put($path, $processed['main']);
            $disk->put($thumb, $processed['thumb']);
        } else {
            $path = $file->store($dir, 'uploads');
            $thumb = null;
        }

        $existing = $property->images()->where('type', $type)->count();

        return $property->images()->create([
            'type' => $type,
            'path' => $path,
            'thumb_path' => $thumb,
            'alt' => Str::limit($property->title, 120, '').($type === 'floor_plan' ? ' - floor plan' : ' - photo '.($existing + 1)),
            'is_cover' => $type === 'photo' && ! $property->images()->where('type', 'photo')->where('is_cover', true)->exists(),
            'sort_order' => $existing,
        ]);
    }

    public function removeImage(PropertyImage $image): void
    {
        $property = $image->property_id;
        $wasCover = $image->is_cover;

        Storage::disk('uploads')->delete(array_filter([$image->path, $image->thumb_path]));
        $image->delete();

        if ($wasCover) {
            PropertyImage::where('property_id', $property)->where('type', 'photo')->orderBy('sort_order')->orderBy('id')->first()?->update(['is_cover' => true]);
        }
    }

    public function setCover(PropertyImage $image): void
    {
        if ($image->type !== 'photo') {
            return;
        }

        PropertyImage::where('property_id', $image->property_id)->update(['is_cover' => false]);
        $image->update(['is_cover' => true]);
    }

    /** One video per listing; a new upload replaces the old one. Needs the video_upload feature. */
    public function setVideo(Property $property, UploadedFile $file): PropertyVideo
    {
        $this->permissions->assert($property->user, 'video_upload', $property->posted_by);

        $this->removeVideo($property);

        return $property->video()->create([
            'path' => $file->store("properties/{$property->id}/video", 'uploads'),
            'size_kb' => (int) ceil($file->getSize() / 1024),
        ]);
    }

    public function removeVideo(Property $property): void
    {
        foreach ($property->video()->get() as $video) {
            Storage::disk('uploads')->delete($video->path);
            $video->delete();
        }
    }

    private function assertImageCapacity(Property $property, string $type): void
    {
        $count = $property->images()->where('type', $type)->count();

        if ($type === 'floor_plan') {
            if ($count >= config('property.floor_plan_limit')) {
                throw new LimitExceededException('floor_plan', config('property.floor_plan_limit'));
            }

            return;
        }

        $limit = $this->permissions->limit($property->user, 'photo_limit', $property->posted_by);

        if ($limit !== null && $count >= $limit) {
            throw new LimitExceededException('photo_limit', $limit);
        }
    }
}
