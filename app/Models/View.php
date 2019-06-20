<?php

namespace App\Models;

use App\Models\Article;
use Illuminate\Database\Eloquent\Model;

class View extends Model
{
    protected $fillable = [
        'article_id',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}