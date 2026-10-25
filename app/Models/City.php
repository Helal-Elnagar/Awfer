<?php

namespace App\Models;

use Spatie\Translatable\HasTranslations;

class City extends BaseModel
{
    use HasTranslations; 
    
    protected $fillable = ['name','region_id','country_id','delivery_fee','boundary'];
    
    public $translatable = ['name'];

    public function boundaryPoints(): array
    {
        $boundary = $this->boundary;
        if (empty($boundary)) {
            return [];
        }
        $decoded = is_array($boundary) ? $boundary : json_decode((string) $boundary, true);
        if (!is_array($decoded)) {
            return [];
        }
        // Unwrap an outer ring if present (e.g. [[[lat,lng],...]] or [[{lat,lng},...]])
        $first = $decoded[0] ?? null;
        if (is_array($first) && isset($first[0]) && !isset($first['lat']) && (is_array($first[0]) || is_object($first[0]))) {
            $decoded = $first;
        }
        // Normalize each point to a [lat, lng] pair
        $points = [];
        foreach ($decoded as $point) {
            if (!is_array($point) && !is_object($point)) {
                continue;
            }
            $p = (array) $point;
            $lat = isset($p['lat']) ? $p['lat'] : ($p[0] ?? null);
            $lng = isset($p['lng']) ? $p['lng'] : ($p[1] ?? null);
            if (is_numeric($lat) && is_numeric($lng)) {
                $points[] = [(float) $lat, (float) $lng];
            }
        }
        return $points;
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'city_id', 'id');
    }

    public function getUsersCountAttribute()
    {
        return $this->users()->where('type', 'client')->count();
    }
    

    public function getProvidersCountAttribute()
    {
        return $this->users()->where('type', 'provider')->count();
    }
    

}
