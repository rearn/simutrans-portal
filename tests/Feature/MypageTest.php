<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MypageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed('ProdSeeder');
    }

    /**
     * メール未認証のユーザーで下記ページにアクセスしたとき認証が必要メッセージが表示されること
     *      プロフィール編集
     *      アドオン投稿作成
     *      アドオン紹介作成
     *      記事作成
     *      アドオン投稿編集
     *      アドオン紹介編集
     *      記事編集
     */
    public function testNeedVerifiedActions()
    {
        $user = factory(User::class)->create(['email_verified_at' => null]);
        $this->actingAs($user);

        $response = $this->get('mypage');
        $response->assertOk();

        // profile
        $response = $this->get('mypage/profile');
        $response->assertRedirect('email/verify');

        // create
        $response = $this->get('mypage/articles/create/addon-post');
        $response->assertRedirect('email/verify');

        $response = $this->get('mypage/articles/create/addon-introduction');
        $response->assertRedirect('email/verify');

        $response = $this->get('mypage/articles/create/page');
        $response->assertRedirect('email/verify');

        // edit
        $article = static::createAddonPost($user);
        $response = $this->get('mypage/articles/edit/'.$article->id);
        $response->assertRedirect('email/verify');

        $article = static::createAddonIntroduction($user);
        $response = $this->get('mypage/articles/edit/'.$article->id);
        $response->assertRedirect('email/verify');

        $article = static::createPage($user);
        $response = $this->get('mypage/articles/edit/'.$article->id);
        $response->assertRedirect('email/verify');
    }

    /**
     * メール認証済みのユーザーで下記ページにアクセスしたとき認証が必要メッセージが表示されないこと
     *      プロフィール編集
     *      アドオン投稿作成
     *      アドオン紹介作成
     *      記事作成
     *      アドオン投稿編集
     *      アドオン紹介編集
     *      記事編集
     */
    public function testVerifiedActions()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);

        $response = $this->get('mypage');
        $response->assertOk();

        // profile
        $response = $this->get('mypage/profile');
        $response->assertOk();

        // create
        $response = $this->get('mypage/articles/create/addon-post');
        $response->assertOk();

        $response = $this->get('mypage/articles/create/addon-introduction');
        $response->assertOk();

        $response = $this->get('mypage/articles/create/page');
        $response->assertOk();

        // edit
        $article = static::createAddonPost($user);
        $response = $this->get('mypage/articles/edit/'.$article->id);
        $response->assertOk();

        $article = static::createAddonIntroduction($user);
        $response = $this->get('mypage/articles/edit/'.$article->id);
        $response->assertOk();

        $article = static::createPage($user);
        $response = $this->get('mypage/articles/edit/'.$article->id);
        $response->assertOk();
    }

    /**
     * アドオン投稿作成のバリデーション
     */
    public function testValidateCreateAddonPost()
    {
        $path = 'mypage/articles/create/addon-post';
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $response = $this->get($path);
        $response->assertOk();

        $thumbnail = Attachment::createFromFile(UploadedFile::fake()->create('thumbnail.jpg', 1), $user->id);
        $addon     = Attachment::createFromFile(UploadedFile::fake()->create('addon.zip', 1), $user->id);

        $date = now()->format('YmdHis');
        $data = [
            'title'        => 'test title '.$date,
            'slug'         => 'test-slug-'.$date,
            'thumbnail_id' => $thumbnail->id,
            'status'       => 'publish',
            'author'      => 'test auhtor',
            'file_id'     => $addon->id,
            'description' => 'test description',
            'thanks'      => 'tets thanks',
            'license'     => 'test license',
        ];
        // ステータスが空
        $response = $this->post($path, array_merge($data, ['status' => '']));
        $response->assertSessionHasErrors(['status']);
        // 不正なステータス
        $response = $this->post($path, array_merge($data, ['status' => 'test_example']));
        $response->assertSessionHasErrors(['status']);

        // タイトルが空
        $response = $this->post($path, array_merge($data, ['title' => '']));
        $response->assertSessionHasErrors(['title']);
        // タイトルが256文字以上
        $response = $this->post($path, array_merge($data, ['title' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['title']);
        // タイトルが重複
        $article = factory(Article::class)->create(['user_id'=>factory(User::class)->create()->id]);
        $response = $this->post($path, array_merge($data, ['title' => $article->title]));
        $response->assertSessionHasErrors(['title']);

        // スラッグが256文字以上
        $response = $this->post($path, array_merge($data, ['slug' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['slug']);

        // 存在しないサムネイルID
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => 99999]));
        $response->assertSessionHasErrors(['thumbnail_id']);
        // 他人の投稿したサムネイルID
        $others_attachment = Attachment::createFromFile(UploadedFile::fake()->create('other.zip', 1), factory(User::class)->create()->id);
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => $others_attachment->id]));
        $response->assertSessionHasErrors(['thumbnail_id']);

        // アドオン作者が256文字以上
        $response = $this->post($path, array_merge($data, ['author' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['author']);

        // ファイルIDが空
        $response = $this->post($path, array_merge($data, ['file_id' => '']));
        $response->assertSessionHasErrors(['file_id']);
        // 存在しないファイルID
        $response = $this->post($path, array_merge($data, ['file_id' => 99999]));
        $response->assertSessionHasErrors(['file_id']);
        // 他人の投稿したファイルID
        $others_attachment = Attachment::createFromFile(UploadedFile::fake()->create('other.zip', 1), factory(User::class)->create()->id);
        $response = $this->post($path, array_merge($data, ['file_id' => $others_attachment->id]));
        $response->assertSessionHasErrors(['file_id']);

        // 説明が空
        $response = $this->post($path, array_merge($data, ['description' => '']));
        $response->assertSessionHasErrors(['description']);
        // 説明が2049文字以上
        $response = $this->post($path, array_merge($data, ['description' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['description']);

        // 謝辞が2049文字以上
        $response = $this->post($path, array_merge($data, ['thanks' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['thanks']);

        // タグ名が256文字以上
        $response = $this->post($path, array_merge($data, ['tags' => [str_repeat('a', 256)]]));
        $response->assertSessionHasErrors(['tags.*']);

        // ライセンス（その他）が2049文字以上
        $response = $this->post($path, array_merge($data, ['license' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['license']);

        // 適切なデータ
        $response = $this->post($path, $data);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect('mypage');
    }


    /**
     * アドオン紹介作成のバリデーション
     */
    public function testValidateCreateAddonIntroduction()
    {
        $path = 'mypage/articles/create/addon-introduction';
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $response = $this->get($path);
        $response->assertOk();

        $thumbnail = Attachment::createFromFile(UploadedFile::fake()->create('thumbnail.jpg', 1), $user->id);

        $date = now()->format('YmdHis');
        $data = [
            'title'        => 'test title '.$date,
            'slug'         => 'test-slug-'.$date,
            'thumbnail_id' => $thumbnail->id,
            'status'       => 'publish',
            'author'      => 'test auhtor',
            'link'        => 'http://example.com',
            'description' => 'test description',
            'thanks'      => 'tets thanks',
            'license'     => 'test license',
        ];
        // ステータスが空
        $response = $this->post($path, array_merge($data, ['status' => '']));
        $response->assertSessionHasErrors(['status']);
        // 不正なステータス
        $response = $this->post($path, array_merge($data, ['status' => 'test_example']));
        $response->assertSessionHasErrors(['status']);

        // タイトルが空
        $response = $this->post($path, array_merge($data, ['title' => '']));
        $response->assertSessionHasErrors(['title']);
        // タイトルが256文字以上
        $response = $this->post($path, array_merge($data, ['title' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['title']);
        // タイトルが重複
        $article = factory(Article::class)->create(['user_id'=>factory(User::class)->create()->id]);
        $response = $this->post($path, array_merge($data, ['title' => $article->title]));
        $response->assertSessionHasErrors(['title']);

        // スラッグが256文字以上
        $response = $this->post($path, array_merge($data, ['slug' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['slug']);

        // 存在しないサムネイルID
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => 99999]));
        $response->assertSessionHasErrors(['thumbnail_id']);
        // 他人の投稿したサムネイルID
        $others_attachment = Attachment::createFromFile(UploadedFile::fake()->create('other.zip', 1), factory(User::class)->create()->id);
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => $others_attachment->id]));
        $response->assertSessionHasErrors(['thumbnail_id']);

        // アドオン作者が空
        $response = $this->post($path, array_merge($data, ['author' => '']));
        $response->assertSessionHasErrors(['author']);
        // アドオン作者が256文字以上
        $response = $this->post($path, array_merge($data, ['author' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['author']);

        // ダウンロード先が空
        $response = $this->post($path, array_merge($data, ['link' => '']));
        $response->assertSessionHasErrors(['link']);
        // ダウンロード先がURL以外
        $response = $this->post($path, array_merge($data, ['link' => 'test']));
        $response->assertSessionHasErrors(['link']);
        // ダウンロード先がURLだが256文字以上
        $response = $this->post($path, array_merge($data, ['link' => 'http://example.com/'.str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['link']);

        // 説明が2049文字以上
        $response = $this->post($path, array_merge($data, ['description' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['description']);

        // 謝辞が2049文字以上
        $response = $this->post($path, array_merge($data, ['thanks' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['thanks']);

        // タグ名が256文字以上
        $response = $this->post($path, array_merge($data, ['tags' => [str_repeat('a', 256)]]));
        $response->assertSessionHasErrors(['tags.*']);

        // ライセンス（その他）が2049文字以上
        $response = $this->post($path, array_merge($data, ['license' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['license']);

        // 適切なデータ
        $response = $this->post($path, $data);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect('mypage');
    }


    /**
     * 一般記事作成のバリデーション
     */
    public function testValidateCreatePage()
    {
        $path = 'mypage/articles/create/page';
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $response = $this->get($path);
        $response->assertOk();

        $thumbnail = Attachment::createFromFile(UploadedFile::fake()->create('thumbnail.jpg', 1), $user->id);

        $date = now()->format('YmdHis');
        $data = [
            'title'        => 'test title '.$date,
            'slug'         => 'test-slug-'.$date,
            'thumbnail_id' => $thumbnail->id,
            'status'       => 'publish',
            'sections'     => [['type' => 'text', 'text' => 'test']],
        ];
        // ステータスが空
        $response = $this->post($path, array_merge($data, ['status' => '']));
        $response->assertSessionHasErrors(['status']);
        // 不正なステータス
        $response = $this->post($path, array_merge($data, ['status' => 'test_example']));
        $response->assertSessionHasErrors(['status']);

        // タイトルが空
        $response = $this->post($path, array_merge($data, ['title' => '']));
        $response->assertSessionHasErrors(['title']);
        // タイトルが256文字以上
        $response = $this->post($path, array_merge($data, ['title' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['title']);
        // タイトルが重複
        $article = factory(Article::class)->create(['user_id'=>factory(User::class)->create()->id]);
        $response = $this->post($path, array_merge($data, ['title' => $article->title]));
        $response->assertSessionHasErrors(['title']);

        // スラッグが256文字以上
        $response = $this->post($path, array_merge($data, ['slug' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['slug']);

        // 存在しないサムネイルID
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => 99999]));
        $response->assertSessionHasErrors(['thumbnail_id']);
        // 他人の投稿したサムネイルID
        $others_attachment = Attachment::createFromFile(UploadedFile::fake()->create('other.zip', 1), factory(User::class)->create()->id);
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => $others_attachment->id]));
        $response->assertSessionHasErrors(['thumbnail_id']);

        // セクションが無い
        $response = $this->post($path, array_merge($data, ['sections' => null]));
        $response->assertSessionHasErrors(['sections']);
        // セクションが空
        $response = $this->post($path, array_merge($data, ['sections' => []]));
        $response->assertSessionHasErrors(['sections']);

        // 本文セクションが空
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'text', 'text' => '']]]));
        $response->assertSessionHasErrors(['sections.*.text']);
        // 本文セクションが2049文字以上
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'text', 'text' => str_repeat('a', 2049)]]]));
        $response->assertSessionHasErrors(['sections.*.text']);

        // 見出しセクションが空
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'caption', 'caption' => '']]]));
        $response->assertSessionHasErrors(['sections.*.caption']);
        // 見出しセクションが256文字以上
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'caption', 'caption' => str_repeat('a', 256)]]]));
        $response->assertSessionHasErrors(['sections.*.caption']);

        // 画像セクションが空
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'image', 'id' => '']]]));
        $response->assertSessionHasErrors(['sections.*.id']);
        // 画像セクションが存在しないID
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'image', 'id' => 99999]]]));
        $response->assertSessionHasErrors(['sections.*.id']);
        // 画像セクションが他人の投稿したID
        $others_attachment = Attachment::createFromFile(UploadedFile::fake()->create('other.zip', 1), factory(User::class)->create()->id);
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'image', 'id' => $others_attachment->id]]]));
        $response->assertSessionHasErrors(['sections.*.id']);

        // 適切なデータ
        $response = $this->post($path, $data);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect('mypage');
    }

    /**
     * アドオン投稿作成のバリデーション
     */
    public function testValidateEditAddonPost()
    {
        $user = factory(User::class)->create();
        $article = static::createAddonPost($user);
        $path = 'mypage/articles/edit/addon-post/'.$article->slug;
        $this->actingAs($user);

        $data = [
            'title'        => $article->title,
            'slug'         => $article->slug,
            'status'       => $article->status,
            'thumbnail_id' => $article->getContents('thumbnail'),
            'author'      => $article->getContents('author'),
            'file_id'     => $article->getContents('file'),
            'description' => $article->getContents('description'),
            'thanks'      => $article->getContents('thanks'),
            'license'     => $article->getContents('license'),
        ];
        // ステータスが空
        $response = $this->post($path, array_merge($data, ['status' => '']));
        $response->assertSessionHasErrors(['status']);
        // 不正なステータス
        $response = $this->post($path, array_merge($data, ['status' => 'test_example']));
        $response->assertSessionHasErrors(['status']);

        // タイトルが空
        $response = $this->post($path, array_merge($data, ['title' => '']));
        $response->assertSessionHasErrors(['title']);
        // タイトルが256文字以上
        $response = $this->post($path, array_merge($data, ['title' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['title']);
        // タイトルが重複
        $article = factory(Article::class)->create(['user_id'=>factory(User::class)->create()->id]);
        $response = $this->post($path, array_merge($data, ['title' => $article->title]));
        $response->assertSessionHasErrors(['title']);

        // スラッグが256文字以上
        $response = $this->post($path, array_merge($data, ['slug' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['slug']);

        // 存在しないサムネイルID
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => 99999]));
        $response->assertSessionHasErrors(['thumbnail_id']);
        // 他人の投稿したサムネイルID
        $others_attachment = Attachment::createFromFile(UploadedFile::fake()->create('other.zip', 1), factory(User::class)->create()->id);
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => $others_attachment->id]));
        $response->assertSessionHasErrors(['thumbnail_id']);

        // アドオン作者が256文字以上
        $response = $this->post($path, array_merge($data, ['author' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['author']);

        // ファイルIDが空
        $response = $this->post($path, array_merge($data, ['file_id' => '']));
        $response->assertSessionHasErrors(['file_id']);
        // 存在しないファイルID
        $response = $this->post($path, array_merge($data, ['file_id' => 99999]));
        $response->assertSessionHasErrors(['file_id']);
        // 他人の投稿したファイルID
        $others_attachment = Attachment::createFromFile(UploadedFile::fake()->create('other.zip', 1), factory(User::class)->create()->id);
        $response = $this->post($path, array_merge($data, ['file_id' => $others_attachment->id]));
        $response->assertSessionHasErrors(['file_id']);

        // 説明が空
        $response = $this->post($path, array_merge($data, ['description' => '']));
        $response->assertSessionHasErrors(['description']);
        // 説明が2049文字以上
        $response = $this->post($path, array_merge($data, ['description' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['description']);

        // 謝辞が2049文字以上
        $response = $this->post($path, array_merge($data, ['thanks' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['thanks']);

        // タグ名が256文字以上
        $response = $this->post($path, array_merge($data, ['tags' => [str_repeat('a', 256)]]));
        $response->assertSessionHasErrors(['tags.*']);

        // ライセンス（その他）が2049文字以上
        $response = $this->post($path, array_merge($data, ['license' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['license']);

        // 適切なデータ
        $response = $this->post($path, $data);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect('mypage');
    }

    /**
     * アドオン紹介作成のバリデーション
     */
    public function testValidateEditAddonIntroduction()
    {
        $user = factory(User::class)->create();
        $article = static::createAddonIntroduction($user);
        $path = 'mypage/articles/edit/addon-introduction/'.$article->slug;
        $this->actingAs($user);

        $date = now()->format('YmdHis');
        $data = [
            'title'        => $article->title,
            'slug'         => $article->slug,
            'status'       => $article->status,
            'thumbnail_id' => $article->getContents('thumbnail'),
            'author'      => $article->getContents('author'),
            'link'        => $article->getContents('link'),
            'description' => $article->getContents('description'),
            'thanks'      => $article->getContents('thanks'),
            'license'     => $article->getContents('license'),
        ];
        // ステータスが空
        $response = $this->post($path, array_merge($data, ['status' => '']));
        $response->assertSessionHasErrors(['status']);
        // 不正なステータス
        $response = $this->post($path, array_merge($data, ['status' => 'test_example']));
        $response->assertSessionHasErrors(['status']);

        // タイトルが空
        $response = $this->post($path, array_merge($data, ['title' => '']));
        $response->assertSessionHasErrors(['title']);
        // タイトルが256文字以上
        $response = $this->post($path, array_merge($data, ['title' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['title']);
        // タイトルが重複
        $article = factory(Article::class)->create(['user_id'=>factory(User::class)->create()->id]);
        $response = $this->post($path, array_merge($data, ['title' => $article->title]));
        $response->assertSessionHasErrors(['title']);

        // スラッグが256文字以上
        $response = $this->post($path, array_merge($data, ['slug' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['slug']);

        // 存在しないサムネイルID
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => 99999]));
        $response->assertSessionHasErrors(['thumbnail_id']);
        // 他人の投稿したサムネイルID
        $others_attachment = Attachment::createFromFile(UploadedFile::fake()->create('other.zip', 1), factory(User::class)->create()->id);
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => $others_attachment->id]));
        $response->assertSessionHasErrors(['thumbnail_id']);

        // アドオン作者が空
        $response = $this->post($path, array_merge($data, ['author' => '']));
        $response->assertSessionHasErrors(['author']);
        // アドオン作者が256文字以上
        $response = $this->post($path, array_merge($data, ['author' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['author']);

        // ダウンロード先が空
        $response = $this->post($path, array_merge($data, ['link' => '']));
        $response->assertSessionHasErrors(['link']);
        // ダウンロード先がURL以外
        $response = $this->post($path, array_merge($data, ['link' => 'test']));
        $response->assertSessionHasErrors(['link']);
        // ダウンロード先がURLだが256文字以上
        $response = $this->post($path, array_merge($data, ['link' => 'http://example.com/'.str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['link']);

        // 説明が2049文字以上
        $response = $this->post($path, array_merge($data, ['description' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['description']);

        // 謝辞が2049文字以上
        $response = $this->post($path, array_merge($data, ['thanks' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['thanks']);

        // タグ名が256文字以上
        $response = $this->post($path, array_merge($data, ['tags' => [str_repeat('a', 256)]]));
        $response->assertSessionHasErrors(['tags.*']);

        // ライセンス（その他）が2049文字以上
        $response = $this->post($path, array_merge($data, ['license' => str_repeat('a', 2049)]));
        $response->assertSessionHasErrors(['license']);

        // 適切なデータ
        $response = $this->post($path, $data);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect('mypage');
    }

    /**
     * 一般記事作成のバリデーション
     */
    public function testValidateEditPage()
    {
        $user = factory(User::class)->create();
        $article = static::createPage($user);
        $path = 'mypage/articles/edit/page/'.$article->slug;
        $this->actingAs($user);

        $date = now()->format('YmdHis');
        $data = [
            'title'        => $article->title,
            'slug'         => $article->slug,
            'status'       => $article->status,
            'thumbnail_id' => $article->getContents('thumbnail'),
            'sections'     => $article->getContents('sections'),
        ];
        // ステータスが空
        $response = $this->post($path, array_merge($data, ['status' => '']));
        $response->assertSessionHasErrors(['status']);
        // 不正なステータス
        $response = $this->post($path, array_merge($data, ['status' => 'test_example']));
        $response->assertSessionHasErrors(['status']);

        // タイトルが空
        $response = $this->post($path, array_merge($data, ['title' => '']));
        $response->assertSessionHasErrors(['title']);
        // タイトルが256文字以上
        $response = $this->post($path, array_merge($data, ['title' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['title']);
        // タイトルが重複
        $article = factory(Article::class)->create(['user_id'=>factory(User::class)->create()->id]);
        $response = $this->post($path, array_merge($data, ['title' => $article->title]));
        $response->assertSessionHasErrors(['title']);

        // スラッグが256文字以上
        $response = $this->post($path, array_merge($data, ['slug' => str_repeat('a', 256)]));
        $response->assertSessionHasErrors(['slug']);

        // 存在しないサムネイルID
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => 99999]));
        $response->assertSessionHasErrors(['thumbnail_id']);
        // 他人の投稿したサムネイルID
        $others_attachment = Attachment::createFromFile(UploadedFile::fake()->create('other.zip', 1), factory(User::class)->create()->id);
        $response = $this->post($path, array_merge($data, ['thumbnail_id' => $others_attachment->id]));
        $response->assertSessionHasErrors(['thumbnail_id']);

        // セクションが無い
        $response = $this->post($path, array_merge($data, ['sections' => null]));
        $response->assertSessionHasErrors(['sections']);
        // セクションが空
        $response = $this->post($path, array_merge($data, ['sections' => []]));
        $response->assertSessionHasErrors(['sections']);

        // 本文セクションが空
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'text', 'text' => '']]]));
        $response->assertSessionHasErrors(['sections.*.text']);
        // 本文セクションが2049文字以上
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'text', 'text' => str_repeat('a', 2049)]]]));
        $response->assertSessionHasErrors(['sections.*.text']);

        // 見出しセクションが空
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'caption', 'caption' => '']]]));
        $response->assertSessionHasErrors(['sections.*.caption']);
        // 見出しセクションが256文字以上
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'caption', 'caption' => str_repeat('a', 256)]]]));
        $response->assertSessionHasErrors(['sections.*.caption']);

        // 画像セクションが空
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'image', 'id' => '']]]));
        $response->assertSessionHasErrors(['sections.*.id']);
        // 画像セクションが存在しないID
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'image', 'id' => 99999]]]));
        $response->assertSessionHasErrors(['sections.*.id']);
        // 画像セクションが他人の投稿したID
        $others_attachment = Attachment::createFromFile(UploadedFile::fake()->create('other.zip', 1), factory(User::class)->create()->id);
        $response = $this->post($path, array_merge($data, ['sections' => [['type' => 'image', 'id' => $others_attachment->id]]]));
        $response->assertSessionHasErrors(['sections.*.id']);

        // 適切なデータ
        $response = $this->post($path, $data);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect('mypage');
    }

}
