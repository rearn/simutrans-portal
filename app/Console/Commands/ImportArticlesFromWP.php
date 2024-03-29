<?php
namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Profile;
use App\Models\Redirect;
use App\Models\Tag;
use App\Models\User;
use App\Traits\WPImportable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportArticlesFromWP extends Command
{
    use WPImportable;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:articles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import articles from WP Database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        DB::beginTransaction();
        self::createCategoryRedirect();
        foreach ($this->fetchWPUsers() as $wp_user) {
            $user = User::where('email', $wp_user->user_email)->firstOrFail();
            foreach ($this->fetchWPPosts($wp_user->ID) as $wp_post) {
                if($wp_post->post_status !== 'publish') {
                    continue;
                }
                $this->info('creating:'.$wp_post->post_title);

                $article = $this->createArticle($user, $wp_post);

                $this->applyCategories($article, $wp_post->ID);
                $this->applyTags($article, $wp_post->ID);

                $wp_thumbnail = $this->fetchWPThumbnail($wp_post->ID);
                if($wp_thumbnail) {
                    $path = self::saveFromUrl($user->id, $wp_thumbnail->guid);

                    $attachment = $article->attachments()->create([
                        'user_id'       => $user->id,
                        'original_name' => basename($wp_thumbnail->guid),
                        'path'          => $path
                    ]);
                    $article->setContents('thumbnail', $attachment->id);
                }

                if ($article->post_type === 'addon-post') {
                    $article = $this->applyAddonPost($user, $article, $wp_post);
                }
                if ($article->post_type === 'addon-introduction') {
                    $article = $this->applyAddonIntroduction($user, $article, $wp_post);
                }
                $article->save();
                $this->createViewCount($article, $wp_post);

                self::createRedirect($article, $wp_post);
                self::updateCreatedAt($article->id, $wp_post);
                $this->info('created:'.$article->title);
            }
        }
        DB::commit();
    }

    private function createArticle($user, $wp_post)
    {
        // post type
        $post_type = $this->fetchWPTerms($wp_post->ID, 'category')[0]->slug;
        return $user->articles()->create([
            'title'     => trim($wp_post->post_title),
            'slug'      => trim($wp_post->post_title),
            'post_type' => $post_type,
            'status'    => config('status.publish'),
        ]);
    }

    private function applyCategories($article, $wp_post_id)
    {
        $categories = collect([]);

        // pak
        $wp_term_slugs = collect($this->fetchWPTerms($wp_post_id, 'pak'))->pluck('slug');
        $categories = $categories->merge(Category::pak()->whereIn('slug', $wp_term_slugs)->get());

        // addon(type)
        $wp_term_slugs = collect($this->fetchWPTerms($wp_post_id, 'type'))->pluck('slug');
        $categories = $categories->merge(Category::addon()->whereIn('slug', $wp_term_slugs)->get());

        // pak128_position
        $wp_term_slugs = collect($this->fetchWPTerms($wp_post_id, 'pak128_position'))->pluck('slug');
        $categories = $categories->merge(Category::pak128Position()->whereIn('slug', $wp_term_slugs)->get());

        return $article->categories()->sync($categories->pluck('id'));
    }

    private function applyTags($article, $wp_post_id)
    {
        $wp_terms = collect($this->fetchWPTerms($wp_post_id, 'post_tag'));
        $tags = $wp_terms->map(function($wp_term) {
            return Tag::firstOrCreate(['name' => trim($wp_term->name)]);
        });

        return $article->tags()->sync($tags->pluck('id'));
    }

    private function applyAddonPost($user, $article, $wp_post)
    {
        $article->setContents('author', trim($this->fetchWPPostmetaValueBy($wp_post->ID, 'addon-author')));
        $article->setContents('description', trim($this->fetchWPPostmetaValueBy($wp_post->ID, 'addon-description')));
        $article->setContents('thanks', trim($this->fetchWPPostmetaValueBy($wp_post->ID, 'addon-based')));
        $article->setContents('license', null);

        // addon file
        $wp_addon_file = $this->fetchWPAddonFile($wp_post->ID);
        $path = self::saveFromUrl($user->id, $wp_addon_file->guid);

        $attachment = $article->attachments()->create([
            'user_id'       => $user->id,
            'original_name' => basename($wp_addon_file->guid),
            'path'          => $path
        ]);
        $article->setContents('file', $attachment->id);
        return $article;
    }

    private function applyAddonIntroduction($user, $article, $wp_post)
    {
        $article->setContents('author', trim($this->fetchWPPostmetaValueBy($wp_post->ID, 'addon-author')));
        $article->setContents('description', trim($this->fetchWPPostmetaValueBy($wp_post->ID, 'addon-description')));
        $article->setContents('thanks', trim($this->fetchWPPostmetaValueBy($wp_post->ID, 'addon-based')));
        $article->setContents('license', null);
        $article->setContents('link', trim($this->fetchWPPostmetaValueBy($wp_post->ID, 'site-url')));
        $article->setContents('agreement',
        $this->fetchWPPostmetaValueBy($wp_post->ID, 'addon-introduction-agreement') ? true : false);
        return $article;
    }

    private function createViewCount($article, $wp_post)
    {
        $types = [
            0 => 1, // daily
            2 => 2, // monthly
            3 => 3, // yearly
            4 => 4, // total
        ];

        // レコード数は3年分~1000程度なのでメモリは行けるはず
        $items = [];
        foreach ($this->fetchWPPostViews($wp_post->ID) as $wp_post_view) {
            $items[] = [
                'type'   => $types[$wp_post_view->type],
                'period' => $wp_post_view->period,
                'count'  => $wp_post_view->count,
            ];
        }
        $article->viewCounts()->createMany($items);
    }

    private static function createRedirect($article, $wp_post)
    {
        $from = '/'.$wp_post->post_name;
        $to   = route('articles.show', $article->slug, false);
        return Redirect::firstOrCreate([
            'from' => $from,
            'to'   => $to,
        ]);
    }

    /**
     * 作成日を引き継ぐ
     */
    private static function updateCreatedAt($id, $wp_post)
    {
        return DB::update('UPDATE articles SET created_at = ? WHERE id = ?', [$wp_post->post_date, $id]);
    }


    private static function createCategoryRedirect()
    {
        $paks = collect([
            '64',
            '128',
            '128-japan',
        ]);
        $paks->map(function($item) {
            Redirect::firstOrCreate([
                'from' => "/pak/{$item}",
                'to'   => route('category', ['pak', $item], false),
            ]);
        });

        $addons = collect([
            'trains',
            'rail-tools',
            'road-tools',
            'ships',
            'aircrafts',
            'road-vehicles',
            'airport-tools',
            'industrial-tools',
            'seaport-tools',
            'buildings',
            'monorail-vehicles',
            'monorail-tools',
            'maglev-vehicles',
            'maglev-tools',
            'narrow-gauge-vahicle',
            'narrow-gauge-tools',
            'tram-vehicle',
            'tram-tools',
            'others',
        ]);
        $addons->map(function($item) {
            Redirect::firstOrCreate([
                'from' => "/type/{$item}",
                'to'   => route('category', ['addon', $item], false),
            ]);
        });

        $pak128_positions = collect([
            'old',
            'new',
        ]);
        $pak128_positions->map(function($item) {
            Redirect::firstOrCreate([
                'from' => "/pak128_position/{$item}",
                'to'   => route('category', ['pak128_position', $item], false),
            ]);
        });

        // pak/addon
        $paks->crossJoin($addons)->map(function($pak_addon) {
            Redirect::firstOrCreate([
                'from' => "/pak/{$pak_addon[0]}?type={$pak_addon[1]}",
                'to'   => route('category.pak.addon', [$pak_addon[0], $pak_addon[1]], false),
            ]);
        });
    }
}
