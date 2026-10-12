<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AiMessage extends Model
{
    use HasFactory;

    protected $fillable = ['ai_thread_id', 'role', 'content', 'provider', 'model'];

    public function thread()
    {
        return $this->belongsTo(AiThread::class, 'ai_thread_id');
    }
}
