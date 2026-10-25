<?php

namespace App\Http\Requests\Admin\clientreviews;

use Illuminate\Foundation\Http\FormRequest;

class Update extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
               'name'                  => 'required|max:191',
            'body'                  => 'required|max:191',

        ];
    }
}
