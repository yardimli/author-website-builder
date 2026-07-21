<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteFile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class AuthorWebsiteExperienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('profile_photo_path')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedBigInteger('bookcoverzone_user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title')->nullable();
            $table->date('published_at')->nullable();
            $table->string('series_name')->nullable();
            $table->unsignedInteger('series_number')->nullable();
            $table->timestamps();
        });
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('primary_book_id')->nullable();
            $table->json('featured_book_ids')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->string('demo_key', 40)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'demo_key']);
        });
        Schema::create('website_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id');
            $table->string('filename');
            $table->string('folder')->default('/');
            $table->string('filetype')->nullable();
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->boolean('is_deleted')->default(false);
            $table->longText('chat_messages_ids')->nullable();
            $table->timestamps();
            $table->unique(['website_id', 'folder', 'filename', 'version']);
        });
        Schema::create('website_user_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id');
            $table->string('image_file_path');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function test_guests_see_the_product_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('home')
            ->assertSee('Your books have a world.')
            ->assertSee('Light mode:')
            ->assertSee('Dark mode:');
    }

    public function test_dashboard_provisions_three_demos_for_existing_members_once(): void
    {
        $user = User::factory()->create(['bio' => null]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Start here')
            ->assertSee('Demo: The Rain of Memories')
            ->assertSee('Demo: The Midnight Enigma')
            ->assertSee('Demo: The Whisper Sword');

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertDatabaseCount('websites', 3);
        $this->assertDatabaseCount('website_files', 9);
        $this->assertSame(9, WebsiteFile::where('content', 'like', '%demo-template:2%')->count());
        $this->assertTrue(WebsiteFile::all()->every(fn (WebsiteFile $file) => mb_check_encoding($file->content, 'UTF-8')));
        $this->assertSame(3, Website::where('user_id', $user->id)->where('is_demo', true)->count());
    }

    public function test_existing_version_one_demo_is_upgraded_without_losing_history(): void
    {
        $user = User::factory()->create();
        $website = Website::create([
            'user_id' => $user->id,
            'name' => 'Demo: The Rain of Memories',
            'slug' => 'old-romance-demo',
            'featured_book_ids' => [],
            'is_demo' => true,
            'demo_key' => 'romance',
        ]);
        WebsiteFile::create(['website_id' => $website->id, 'folder' => '/', 'filename' => 'index.html', 'filetype' => 'html', 'version' => 1, 'content' => '<h1>Old demo</h1>', 'is_deleted' => false]);
        WebsiteFile::create(['website_id' => $website->id, 'folder' => '/assets', 'filename' => 'cover.svg', 'filetype' => 'svg', 'version' => 1, 'content' => '<svg></svg>', 'is_deleted' => false]);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertDatabaseHas('website_files', ['website_id' => $website->id, 'filename' => 'index.html', 'version' => 1, 'is_deleted' => false]);
        $this->assertDatabaseHas('website_files', ['website_id' => $website->id, 'filename' => 'index.html', 'version' => 2, 'is_deleted' => false]);
        $this->assertDatabaseHas('website_files', ['website_id' => $website->id, 'filename' => 'cover.svg', 'version' => 2, 'is_deleted' => true]);
        $this->assertDatabaseHas('websites', ['id' => $website->id, 'name' => 'Demo: The Rain of Memories']);
    }
    public function test_members_can_download_latest_website_files_as_a_zip(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $website = Website::where('user_id', $user->id)->where('demo_key', 'romance')->firstOrFail();

        $response = $this->actingAs($user)->get(route('websites.download', $website));
        $response->assertOk()->assertHeader('content-type', 'application/zip');

        $archive = new ZipArchive();
        $this->assertTrue($archive->open($response->baseResponse->getFile()->getPathname()));
        $this->assertNotFalse($archive->locateName('index.html'));
        $this->assertNotFalse($archive->locateName('css/style.css'));
        $this->assertNotFalse($archive->locateName('js/script.js'));
        $this->assertNotFalse($archive->locateName('assets/cover.jpg'));
        $this->assertNotFalse($archive->locateName('assets/author.jpg'));
        $this->assertNotFalse($archive->locateName('assets/book-3d.jpg'));
        $this->assertNotFalse($archive->locateName('assets/tablet.jpg'));
        $this->assertNotFalse($archive->locateName('assets/social.jpg'));
        $archive->close();
    }
}
