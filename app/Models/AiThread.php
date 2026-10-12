<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class AiThread extends Model
{
    protected $fillable = ['user_id', 'title'];

    public function user()
    {
        return $table->belongsTo(User::class);
    }
    public function messages()
    {
        return $this->hasMany(AiMessage::class);
    }
}
