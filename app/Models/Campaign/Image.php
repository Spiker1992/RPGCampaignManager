<?php

namespace App\Models\Campaign;

use DB;
use Storage;
use App\Models\Model;
use App\Models\Campaign;
use Image as Intervention;

class Image extends Model
{
    protected $fillable = [
        'name',
        'path',
        'size_kb',
        'type',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function getPreviewAttribute()
    {
        return asset('storage/' . str_replace('.', '_preview.', $this->path));
    }

    public function getMapURLAttribute()
    {
        return asset('storage/' . $this->path);
    }

    public static function upload($campaign, $img, $name = null)
    {
        if ($image = static::findImageByHash($campaign, $img)) {
            return $image;
        }

        return DB::transaction(function () use ($campaign, $img, $name) {
            $path = static::uploadImage($campaign, $img);
            $title = ($name) ? $name : $img->getClientOriginalName();

            $type = $img->getMimeType();
            $size = $img->getsize()/1000;
            // FIle is okay!
            $model = static::make(['path' => $path, 'name'=> $title, 'type' => $type, 'size_kb' => $size]);
            $campaign->images()->save($model);
            return $model;
        });
    }

    public function swap($img, $name = null)
    {
        if ($img) {
            $this->type = $img->getMimeType();
            $this->size_kb = $img->getsize()/1000;
            $this->path = static::uploadImage($this->campaign, $img);
        }

        return $this;
    }

    protected static function findImageByHash($campaign, $img)
    {
        $ext = $img->getClientOriginalExtension();
        $hash = md5_file($img->getRealPath());
        $image = $campaign->images()->where('path', "{$campaign->user_id}/{$campaign->id}/{$hash}.{$ext}")->first();
        return $image;
    }

    protected static function uploadImage($campaign, $img)
    {
        $ext = $img->getClientOriginalExtension();
        $hash = md5_file($img->getRealPath());

        $img->storeAs("{$campaign->user_id}/{$campaign->id}", "{$hash}.{$ext}", 'public');
        $preview = Intervention::make($img)->resize(
            400,
            400,
            function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            }
        )
        ->encode();
        Storage::disk('public')->put("{$campaign->user_id}/{$campaign->id}/{$hash}_preview.{$ext}", $preview);

        return "{$campaign->user_id}/{$campaign->id}/{$hash}.{$ext}";
    }

    public function delete()
    {
        // Get preview path
        $preview = str_replace('.', '_preview.', $this->path);
        // Purge file also
        Storage::disk('public')->delete($this->path);
        Storage::disk('public')->delete($preview);

        parent::delete();
    }
}
