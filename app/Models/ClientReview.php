<?php

namespace App\Models;

use Spatie\Translatable\HasTranslations;

class ClientReview extends BaseModel
{
    const IMAGEPATH = 'clientreviews' ; 

    use HasTranslations; 
    protected $fillable = ['name','body' ,'image'];

}
