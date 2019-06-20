<?php

namespace App\Models;

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Models\View;
use App\Traits\Slugable;
use App\Models\Conversion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Article extends Model
{
    use Slugable;
    /*
        アドオン紹介
            contents = {
                description: 説明文
                author: 作者名
                link: リンク先URL
                thumbnail?: サムネイル画像ID
                thanks?: 元アドオン、謝辞
                license?: ライセンス
            };
        アドオン投稿
            contents = {
                description: 説明文
                author: 作者名
                file: 添付ファイルID
                thumbnail?: サムネイル画像ID
                thanks?: 元アドオン、謝辞
                license?: ライセンス
            };
        一般記事
            contents = {
                {type:section content:文章},
                {type:image id:添付画像ID},
                ...
            };
    */
    protected $attributes = [
        'contents' => '{}',
    ];
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'contents',
        'status',
    ];
    protected $casts = [
        'contents' => 'array',
    ];


    /*
    |--------------------------------------------------------------------------
    | グローバルスコープ
    |--------------------------------------------------------------------------
    */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('updated_at', 'desc');
        });
    }


    /*
    |--------------------------------------------------------------------------
    | リレーション
    |--------------------------------------------------------------------------
    */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachmentable');
    }
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function views()
    {
        return $this->hasMany(View::class);
    }
    public function conversions()
    {
        return $this->hasMany(Conversion::class);
    }

    /*
    |--------------------------------------------------------------------------
    | スコープ
    |--------------------------------------------------------------------------
    */
    public function scopeActive($query)
    {
        return $query->where('status', config('status.publish'));
    }
    public function scopeLatest($query)
    {
        return $query->active()->orderBy('updated_at', 'desc');
    }

    /*
    |--------------------------------------------------------------------------
    | アクセサ
    |--------------------------------------------------------------------------
    */
    public function getIsPublishAttribute()
    {
        return $this->status === config('status.publish');
    }

    public function getDescriptionAttribute()
    {
        return $this->getContents('description');
    }
    public function getLinkAttribute()
    {
        return $this->getContents('link');
    }
    public function getAuthorAttribute()
    {
        return $this->getContents('author');
    }
    public function getLicenseAttribute()
    {
        return $this->getContents('license');
    }
    public function getThanksAttribute()
    {
        return $this->getContents('thanks');
    }
    public function getAgreementAttribute()
    {
        return $this->getContents('agreement');;
    }

    public function getThumbnailAttribute()
    {
        $id = $this->getContents('thumbnail');
        return $this->attachments->first(function($attachment) use ($id) {
            return $id === $attachment->id;
        });
    }
    public function getFileAttribute()
    {
        $id = $this->getContents('file');
        return $this->attachments->first(function($attachment) use ($id) {
            return $id === $attachment->id;
        });
    }

    public function getHasThumbnailAttribute()
    {
        return !!$this->thumbnail;
    }
    public function getThumbnailUrlAttribute()
    {
        return $this->has_thumbnail
             ? asset('storage/'.$this->thumbnail->path)
             : asset('storage/'.config('attachment.no-thumbnail'));
    }
    public function getHasFileAttribute()
    {
        return !is_null($this->file);
    }

    public function getCategoryPostAttribute()
    {
        return $this->categories->first(function($category) {
            return $category->type === config('category.type.post');
        });
    }
    public function getCategoryPaksAttribute()
    {
        return $this->categories->filter(function($category) {
            return $category->type === config('category.type.pak');
        });
    }
    public function getCategoryAddonsAttribute()
    {
        return $this->categories->filter(function($category) {
            return $category->type === config('category.type.addon');
        });
    }
    public function getCategoryPak128PositionsAttribute()
    {
        return $this->categories->filter(function($category) {
            return $category->type === config('category.type.pak128_position');
        });
    }
    public function getConversionRateAttribute()
    {
        if ($this->conversions_count && $this->views_count) {
            $rate = $this->conversions_count / $this->views_count * 100;
            return sprintf('%.1f %%', $rate);
        }
        return 'N/A';
    }

    /*
    |--------------------------------------------------------------------------
    | 一般
    |--------------------------------------------------------------------------
    */
    public function hasCategory($id)
    {
        return $this->categories->search(function($category) use($id) {
            return $category->id === $id;
        });
    }
    public function getContents($key, $default = null)
    {
        return data_get($this->contents, $key, $default);
    }
    public function setContents($key, $value)
    {
        $tmp = $this->contents;
        $tmp[$key] = $value;
        $this->contents = $tmp;
    }
}
