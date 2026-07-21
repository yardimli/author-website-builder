<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteFile;
use Illuminate\Support\Facades\DB;

class DemoWebsiteService
{
    public function ensureForUser(User $user): void
    {
        foreach ($this->definitions() as $key => $definition) {
            DB::transaction(function () use ($user, $key, $definition) {
                $website = Website::firstOrCreate(
                    ['user_id' => $user->id, 'demo_key' => $key],
                    [
                        'name' => $definition['name'],
                        'slug' => $this->uniqueSlug("demo-{$key}-{$user->id}"),
                        'primary_book_id' => null,
                        'featured_book_ids' => [],
                        'is_demo' => true,
                    ]
                );

                foreach ($definition['files'] as $file) {
                    WebsiteFile::firstOrCreate(
                        [
                            'website_id' => $website->id,
                            'folder' => $file['folder'],
                            'filename' => $file['filename'],
                            'version' => 1,
                        ],
                        [
                            'filetype' => $file['filetype'],
                            'content' => $file['content'],
                            'is_deleted' => false,
                        ]
                    );
                }
            });
        }
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $counter = 1;

        while (Website::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private function definitions(): array
    {
        return [
            'romance' => [
                'name' => 'Demo: Romance Author',
                'files' => $this->siteFiles('romance', 'Elena Marlowe', 'Where the Peonies Wait', 'Some stories begin with a glance. The unforgettable ones begin with a return.', '#8f3155', '#f8eee9', '#4a2635', 'M 18 104 C 70 42, 118 42, 170 104 C 118 86, 70 86, 18 104 Z'),
            ],
            'suspense' => [
                'name' => 'Demo: Suspense Author',
                'files' => $this->siteFiles('suspense', 'Marcus Vale', 'The Last Alibi', 'The truth is buried in the one place no detective thinks to look: his own past.', '#d14b34', '#15191d', '#eef1f3', 'M 24 25 L 165 25 L 132 160 L 54 160 Z'),
            ],
            'fantasy' => [
                'name' => 'Demo: Fantasy Author',
                'files' => $this->siteFiles('fantasy', 'Arden Wren', 'A Crown of Emberglass', 'An exiled cartographer. A kingdom erased from every map. A crown that remembers fire.', '#c79b3b', '#17233c', '#f4ead3', 'M 94 16 L 116 70 L 174 74 L 129 110 L 144 166 L 94 136 L 44 166 L 59 110 L 14 74 L 72 70 Z'),
            ],
        ];
    }

    private function siteFiles(string $genre, string $author, string $title, string $hook, string $accent, string $background, string $text, string $artPath): array
    {
        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$author} | {$title}</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="#home">{$author}</a>
        <nav><a href="#book">Featured book</a><a href="#author">About</a><a href="#contact">Newsletter</a></nav>
    </header>
    <main id="home">
        <section class="hero" id="book">
            <div class="hero-copy"><p class="eyebrow">A {$genre} novel</p><h1>{$title}</h1><p class="hook">{$hook}</p><a class="button" href="#contact">Discover the story</a></div>
            <img class="cover" src="assets/cover.svg" alt="Cover artwork for {$title}">
        </section>
        <section class="about" id="author"><p class="eyebrow">Meet the author</p><h2>Stories for readers who stay up one chapter too late.</h2><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer vel justo sed sem consequat malesuada. Curabitur vitae nibh at arcu feugiat tincidunt.</p></section>
        <section class="newsletter" id="contact"><div><p class="eyebrow">From the writing desk</p><h2>Get new releases and quiet behind-the-scenes notes.</h2></div><button type="button" id="subscribe">Join the readers list</button></section>
    </main>
    <footer>&copy; <span id="year"></span> {$author}. Demo website.</footer>
    <script src="js/script.js"></script>
</body>
</html>
HTML;

        $css = <<<CSS
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600&family=Playfair+Display:wght@600;700&display=swap');
:root{--accent:{$accent};--bg:{$background};--text:{$text};--card:color-mix(in srgb,var(--bg) 88%,white)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;line-height:1.65}.site-header{max-width:1180px;margin:auto;padding:24px 28px;display:flex;align-items:center;justify-content:space-between}.brand,h1,h2{font-family:'Playfair Display',serif}.brand{color:var(--text);font-size:1.35rem;font-weight:700;text-decoration:none}.site-header nav{display:flex;gap:24px}.site-header nav a{color:var(--text);font-size:.9rem;text-decoration:none}.hero{max-width:1180px;min-height:72vh;margin:auto;padding:56px 28px 80px;display:grid;grid-template-columns:1.2fr .8fr;align-items:center;gap:72px}.eyebrow{color:var(--accent);font-size:.76rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase}.hero h1{font-size:clamp(3rem,7vw,6.8rem);line-height:.95;margin:16px 0 24px;max-width:780px}.hook{font-size:1.2rem;max-width:620px;opacity:.82}.button,#subscribe{display:inline-block;margin-top:22px;padding:14px 20px;border:1px solid var(--accent);background:var(--accent);color:white;text-decoration:none;font:600 .9rem 'DM Sans',sans-serif;cursor:pointer}.cover{width:min(100%,360px);justify-self:center;filter:drop-shadow(0 28px 34px rgba(0,0,0,.28));transform:rotate(2deg)}.about,.newsletter{max-width:1124px;margin:0 auto 48px;padding:72px;border:1px solid color-mix(in srgb,var(--text) 14%,transparent);background:var(--card)}.about h2,.newsletter h2{font-size:clamp(2rem,4vw,3.5rem);line-height:1.08;margin:12px 0 22px}.about p:last-child{max-width:720px}.newsletter{display:flex;align-items:end;justify-content:space-between;gap:36px}.newsletter h2{max-width:700px}footer{padding:36px 28px;text-align:center;font-size:.8rem;opacity:.65}@media(max-width:720px){.site-header nav{display:none}.hero{grid-template-columns:1fr;padding-top:32px;gap:42px}.hero h1{font-size:3.6rem}.cover{grid-row:1;width:230px}.about,.newsletter{margin:0 18px 28px;padding:36px 28px}.newsletter{display:block}}
CSS;

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 188 280" role="img" aria-label="Decorative {$genre} book cover">
  <rect width="188" height="280" rx="3" fill="{$text}"/>
  <rect x="8" y="8" width="172" height="264" fill="none" stroke="{$accent}" stroke-width="1.5"/>
  <path d="{$artPath}" fill="none" stroke="{$accent}" stroke-width="3"/>
  <circle cx="94" cy="93" r="34" fill="{$accent}" opacity=".18"/>
  <text x="94" y="202" text-anchor="middle" fill="{$background}" font-family="Georgia,serif" font-size="11" font-weight="bold">{$title}</text>
  <text x="94" y="240" text-anchor="middle" fill="{$accent}" font-family="Arial,sans-serif" font-size="7" letter-spacing="1.5">{$author}</text>
</svg>
SVG;

        return [
            ['folder' => '/', 'filename' => 'index.html', 'filetype' => 'html', 'content' => $html],
            ['folder' => '/css', 'filename' => 'style.css', 'filetype' => 'css', 'content' => $css],
            ['folder' => '/js', 'filename' => 'script.js', 'filetype' => 'js', 'content' => "document.getElementById('year').textContent = new Date().getFullYear();\ndocument.getElementById('subscribe').addEventListener('click', () => alert('Demo signup form'));"],
            ['folder' => '/assets', 'filename' => 'cover.svg', 'filetype' => 'svg', 'content' => $svg],
        ];
    }
}
