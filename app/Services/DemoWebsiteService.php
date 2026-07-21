<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DemoWebsiteService
{
    private const TEMPLATE_MARKER = 'demo-template:2';

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

                if ($website->name !== $definition['name']) {
                    $website->update(['name' => $definition['name']]);
                }

                foreach ($definition['files'] as $file) {
                    $this->ensureTemplateFile($website, $file);
                }

                $this->retireLegacyCover($website);
            });
        }
    }

    private function ensureTemplateFile(Website $website, array $file): void
    {
        $alreadyInstalled = WebsiteFile::where('website_id', $website->id)
            ->where('folder', $file['folder'])
            ->where('filename', $file['filename'])
            ->where('content', 'like', '%'.self::TEMPLATE_MARKER.'%')
            ->exists();

        if ($alreadyInstalled) {
            return;
        }

        try {
            WebsiteFile::create([
                'website_id' => $website->id,
                'folder' => $file['folder'],
                'filename' => $file['filename'],
                'version' => $this->nextVersion($website, $file['folder'], $file['filename']),
                'filetype' => $file['filetype'],
                'content' => $file['content'],
                'is_deleted' => false,
            ]);
        } catch (QueryException $exception) {
            Log::warning('Unable to install an optional demo website file.', [
                'website_id' => $website->id,
                'filename' => $file['filename'],
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function retireLegacyCover(Website $website): void
    {
        $legacyCover = WebsiteFile::where('website_id', $website->id)
            ->where('folder', '/assets')
            ->where('filename', 'cover.svg')
            ->orderByDesc('version')
            ->first();

        if (!$legacyCover || $legacyCover->is_deleted) {
            return;
        }

        try {
            WebsiteFile::create([
                'website_id' => $website->id,
                'folder' => '/assets',
                'filename' => 'cover.svg',
                'version' => $this->nextVersion($website, '/assets', 'cover.svg'),
                'filetype' => 'svg',
                'content' => '',
                'is_deleted' => true,
            ]);
        } catch (QueryException $exception) {
            Log::warning('Unable to retire a legacy demo cover.', [
                'website_id' => $website->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function nextVersion(Website $website, string $folder, string $filename): int
    {
        $latestVersion = WebsiteFile::where('website_id', $website->id)
            ->where('folder', $folder)
            ->where('filename', $filename)
            ->lockForUpdate()
            ->max('version');

        return ((int) $latestVersion) + 1;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 1;

        while (Website::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function definitions(): array
    {
        return [
            'romance' => [
                'name' => 'Demo: The Rain of Memories',
                'files' => $this->siteFiles(
                    'romance',
                    'Alex Schreiber',
                    'The Rain of Memories',
                    'A tender literary romance about the memories we preserve, the ones we rewrite, and the courage it takes to return.',
                    'Alex Schreiber writes emotionally layered love stories about second chances, imperfect families, and the quiet moments that change a life. Her work pairs intimate character studies with evocative settings and a hopeful belief in hard-won connection.',
                    'Away from the desk, Alex collects old postcards, walks in the rain, and keeps a notebook of overheard fragments that may someday become dialogue.',
                    '#8c4f65',
                    '#f2eee9',
                    '#342b2e'
                ),
            ],
            'suspense' => [
                'name' => 'Demo: The Midnight Enigma',
                'files' => $this->siteFiles(
                    'suspense',
                    'Skyler Reese',
                    'The Midnight Enigma',
                    'A citywide blackout. One vanished witness. A detective with until sunrise to uncover what the darkness concealed.',
                    'Skyler Reese writes atmospheric suspense in which ordinary people are pushed into impossible choices. His novels explore obsession, divided loyalties, and the unsettling distance between what happened and what can be proved.',
                    'Before writing fiction, Skyler worked in late-night radio and documentary research, experiences that shaped his fascination with voices, secrets, and cities after dark.',
                    '#58d49b',
                    '#11191c',
                    '#edf5f1'
                ),
            ],
            'fantasy' => [
                'name' => 'Demo: The Whisper Sword',
                'files' => $this->siteFiles(
                    'fantasy',
                    'Alexa Hoffman',
                    'The Whisper Sword',
                    'The blade remembers every oath ever broken. Now it has chosen the one warrior who refuses to wield it.',
                    'Alexa Hoffman creates sweeping fantasy adventures filled with haunted artifacts, dangerous alliances, and heroes who must decide what power is worth. Her stories blend mythic stakes with intimate questions of duty and belonging.',
                    'Alexa is an avid museum wanderer and folklore reader. She builds every fictional kingdom from maps, family histories, and at least one legend that turns out to be dangerously true.',
                    '#d0a75a',
                    '#182232',
                    '#f6eddb'
                ),
            ],
        ];
    }

    private function siteFiles(
        string $genre,
        string $author,
        string $title,
        string $hook,
        string $bioOne,
        string $bioTwo,
        string $accent,
        string $background,
        string $text
    ): array {
        $assetRoot = "/images/demo-sites/{$genre}";
        $genreLabel = ucfirst($genre);

        $html = <<<HTML
<!-- demo-template:2 -->
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{$hook}">
    <title>{$author} | {$title}</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="#home">{$author}</a>
        <button class="menu-toggle" type="button" aria-label="Toggle navigation">Menu</button>
        <nav><a href="#book">The book</a><a href="#author">About the author</a><a href="#newsletter">Newsletter</a></nav>
    </header>
    <main id="home">
        <section class="hero" id="book">
            <div class="hero-copy">
                <p class="eyebrow">A new {$genreLabel} novel</p>
                <h1>{$title}</h1>
                <p class="hook">{$hook}</p>
                <div class="hero-actions"><a class="button" href="#story">Explore the story</a><a class="text-link" href="#newsletter">Join the readers list</a></div>
            </div>
            <img class="book-3d" src="{$assetRoot}/book-3d.jpg" alt="3D book presentation of {$title}">
        </section>

        <section class="story" id="story">
            <img class="front-cover" src="{$assetRoot}/cover.jpg" alt="Book cover of {$title} by {$author}">
            <div><p class="eyebrow">Inside the book</p><h2>A story built for one more chapter.</h2><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent vel tortor at erat feugiat consequat. Integer vitae justo in arcu aliquet pharetra.</p><blockquote>"The past never disappears. It only waits for the right night to speak."</blockquote></div>
        </section>

        <section class="world-banner" style="background-image:linear-gradient(90deg,rgba(0,0,0,.82),rgba(0,0,0,.2)),url('{$assetRoot}/social.jpg')"><div><p class="eyebrow">The world of the novel</p><h2>Meet the places, secrets, and choices behind the story.</h2></div></section>

        <section class="author" id="author">
            <img src="{$assetRoot}/author.jpg" alt="Portrait of {$author}">
            <div><p class="eyebrow">About the author</p><h2>{$author}</h2><p>{$bioOne}</p><p>{$bioTwo}</p><img class="tablet-book" src="{$assetRoot}/tablet.jpg" alt="Tablet presentation of {$title}"></div>
        </section>

        <section class="newsletter" id="newsletter"><div><p class="eyebrow">From the writing desk</p><h2>New releases and notes for readers.</h2></div><button type="button" id="subscribe">Join the newsletter</button></section>
    </main>
    <footer>&copy; <span id="year"></span> {$author}. Demo author website.</footer>
    <script src="js/script.js"></script>
</body>
</html>
HTML;

        $css = <<<CSS
/* demo-template:2 */
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap');
:root{--accent:{$accent};--bg:{$background};--text:{$text};--card:color-mix(in srgb,var(--bg) 90%,white)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;line-height:1.7}.site-header{max-width:1180px;margin:auto;padding:24px 28px;display:flex;align-items:center;justify-content:space-between}.brand,h1,h2{font-family:'Playfair Display',serif}.brand{color:var(--text);font-size:1.35rem;font-weight:700;text-decoration:none}.site-header nav{display:flex;gap:26px}.site-header nav a,.text-link{color:var(--text);font-size:.88rem;text-decoration:none}.menu-toggle{display:none}.hero{max-width:1180px;min-height:72vh;margin:auto;padding:54px 28px 84px;display:grid;grid-template-columns:1.25fr .75fr;align-items:center;gap:58px}.eyebrow{color:var(--accent);font-size:.74rem;font-weight:700;letter-spacing:.19em;text-transform:uppercase}.hero h1{font-size:clamp(3.4rem,7vw,7rem);line-height:.94;margin:16px 0 28px;max-width:760px}.hook{font-size:1.15rem;max-width:650px;opacity:.82}.hero-actions{display:flex;align-items:center;gap:24px;margin-top:28px}.button,#subscribe{display:inline-block;padding:14px 20px;border:1px solid var(--accent);background:var(--accent);color:white;text-decoration:none;font:600 .88rem 'DM Sans',sans-serif;cursor:pointer}.book-3d{width:min(100%,390px);max-height:520px;object-fit:contain;justify-self:center;filter:drop-shadow(0 30px 35px rgba(0,0,0,.3))}.story,.author,.newsletter{max-width:1124px;margin:0 auto 54px;padding:72px;background:var(--card);border:1px solid color-mix(in srgb,var(--text) 13%,transparent)}.story{display:grid;grid-template-columns:280px 1fr;gap:72px;align-items:center}.front-cover{width:100%;box-shadow:0 22px 45px rgba(0,0,0,.24)}.story h2,.author h2,.newsletter h2,.world-banner h2{font-size:clamp(2.2rem,4.5vw,4rem);line-height:1.05;margin:12px 0 24px}.story blockquote{margin:28px 0 0;padding-left:22px;border-left:3px solid var(--accent);font:600 1.2rem 'Playfair Display',serif}.world-banner{min-height:420px;margin:0 0 54px;background-size:cover;background-position:center;display:flex;align-items:end;color:white}.world-banner>div{width:1124px;max-width:100%;margin:auto;padding:80px 72px}.world-banner h2{max-width:760px}.author{display:grid;grid-template-columns:minmax(240px,360px) 1fr;gap:70px;align-items:center}.author>img{width:100%;aspect-ratio:1;object-fit:cover}.author p{max-width:670px}.tablet-book{width:170px;max-height:220px;object-fit:contain;float:right;margin:-16px 0 0 24px}.newsletter{display:flex;align-items:end;justify-content:space-between;gap:36px}.newsletter h2{max-width:700px}footer{padding:36px 28px;text-align:center;font-size:.8rem;opacity:.65}@media(max-width:720px){.site-header nav{display:none}.menu-toggle{display:block;border:0;background:transparent;color:var(--text)}.hero{grid-template-columns:1fr;padding-top:28px;gap:34px}.hero h1{font-size:3.45rem}.book-3d{grid-row:1;width:230px;height:310px}.hero-actions{align-items:flex-start;flex-direction:column;gap:14px}.story,.author{grid-template-columns:1fr;gap:36px}.story,.author,.newsletter{margin:0 18px 30px;padding:38px 28px}.front-cover{width:210px;justify-self:center}.world-banner{min-height:360px}.world-banner>div{padding:48px 28px}.newsletter{display:block}.tablet-book{display:none}}
CSS;

        return [
            ['folder' => '/', 'filename' => 'index.html', 'filetype' => 'html', 'content' => $html],
            ['folder' => '/css', 'filename' => 'style.css', 'filetype' => 'css', 'content' => $css],
            ['folder' => '/js', 'filename' => 'script.js', 'filetype' => 'js', 'content' => "// demo-template:2\ndocument.getElementById('year').textContent = new Date().getFullYear();\ndocument.getElementById('subscribe').addEventListener('click', () => alert('Demo newsletter signup'));"],
        ];
    }
}
