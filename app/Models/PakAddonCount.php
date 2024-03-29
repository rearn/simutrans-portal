<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Pak-アドオン毎の投稿数（メニュー表示用）
 */
class PakAddonCount extends Model
{
    private const DELETE_SQL = 'DELETE FROM pak_addon_counts';
    private const INSERT_SQL = "INSERT INTO pak_addon_counts (pak_slug, addon_slug, count) (
        SELECT
            pak.slug pak_slug,
            addon.slug addon_slug,
            COUNT(a.id) count
        FROM
            articles a
        LEFT JOIN (
            SELECT
                a.id article_id, c.id, c.slug, c.order
            FROM
                categories c
            LEFT JOIN article_category ac ON ac.category_id = c.id AND c.type = 'pak'
            LEFT JOIN articles a ON a.id = ac.article_id
                AND a.status = 'publish'
        ) pak ON pak.article_id = a.id
        LEFT JOIN (
            SELECT
                a.id article_id, c.id, c.slug, c.order
            FROM
                categories c
            LEFT JOIN article_category ac ON ac.category_id = c.id
                AND c.type = 'addon'
            LEFT JOIN articles a ON a.id = ac.article_id
                AND a.status = 'publish'
        ) addon ON addon.article_id = a.id
        WHERE
            a.post_type IN ('addon-post', 'addon-introduction')
                AND pak.id IS NOT NULL
                AND addon.id IS NOT NULL
        GROUP BY pak.id , addon.id
        ORDER BY pak.order , addon.order)";

    public $timestamps = false;

    protected $fillable = [
        'pak_slug',
        'addon_slug',
        'count',
    ];

    public static function recount()
    {
        DB::transaction(function () {
            DB::statement(self::DELETE_SQL);
            DB::statement(self::INSERT_SQL);
        });
    }
}
