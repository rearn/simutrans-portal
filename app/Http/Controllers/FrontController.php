<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Article;

class FrontController extends Controller
{
    public function index()
    {
        $data = [
            'articles' => [
                'latest' => Article::active()->with('user', 'attachments', 'categories.parent')->latest()->limit(5)->get(),
                'random' => Article::active()->with('user', 'attachments', 'categories.parent')->inRandomOrder()->limit(5)->get(),
            ]
        ];

        return static::viewWithHeader('front.index', $data);
    }

    public function articles(Article $article)
    {
        abort_unless($article->is_publish, 404);

        $data = [
            'article' => $article->load('user', 'attachments', 'categories.parent'),
        ];
        return static::viewWithHeader('front.articles', $data);
    }


}
