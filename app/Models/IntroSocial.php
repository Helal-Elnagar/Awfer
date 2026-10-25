<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntroSocial extends BaseModel
{
    protected const IMAGEPATH = 'intro_socials';
    protected $fillable = ['key', 'url', 'icon'];

    public function getIconAttribute()
    {
        if (isset($this->attributes['icon']) && $this->attributes['icon'] && $this->attributes['icon'] != 'default.png') {
            $image = $this->getImage($this->attributes['icon'], 'intro_socials');
        } else {
            $image = $this->defaultImage(static::IMAGEPATH);
        }
        return $image;
    }

    /**
     * Get the social icon URL with a fallback to default image
     *
     * @return string
     */
    public function getIconUrlAttribute()
    {
        if (isset($this->attributes['icon']) && $this->attributes['icon'] && $this->attributes['icon'] != 'default.png') {
            return $this->getImage($this->attributes['icon'], self::IMAGEPATH);
        }

        return $this->defaultImage(self::IMAGEPATH);
    }

    public function setIconAttribute($value)
    {
        try {
            // Log what we're receiving
            \Illuminate\Support\Facades\Log::info('setIconAttribute called', [
                'value_type' => gettype($value),
                'is_object' => is_object($value),
                'class' => is_object($value) ? get_class($value) : 'not an object',
                'model' => get_class($this),
                'image_path' => static::IMAGEPATH ?? 'undefined'
            ]);

            // Delete old image if exists
            if (isset($this->attributes['icon']) && $this->attributes['icon'] != 'default.png') {
                $this->deleteFile($this->attributes['icon'], static::IMAGEPATH);
            }

            // Upload the new image
            $this->attributes['icon'] = $this->uploadAllTyps($value, static::IMAGEPATH);

            // Log the result
            \Illuminate\Support\Facades\Log::info('Icon uploaded successfully', [
                'new_icon' => $this->attributes['icon']
            ]);
        } catch (\Exception $e) {
            // Log any errors
            \Illuminate\Support\Facades\Log::error('Error in setIconAttribute: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            // Set default image on error
            $this->attributes['icon'] = 'default.png';
        }
    }
}
