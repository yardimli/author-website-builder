<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsiteFile;
use App\Models\WebsiteUserImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class WebsiteDownloadController extends Controller
{
    public function __invoke(Website $website): BinaryFileResponse
    {
        $this->authorize('view', $website);

        $files = WebsiteFile::where('website_id', $website->id)
            ->whereIn('id', function ($query) use ($website) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('website_files')
                    ->where('website_id', $website->id)
                    ->groupBy('website_id', 'folder', 'filename');
            })
            ->where('is_deleted', false)
            ->get();

        abort_if($files->isEmpty(), 404, 'No website files are available to download.');

        $temporaryPath = tempnam(storage_path('app'), 'website-');
        abort_if($temporaryPath === false, 500, 'Could not prepare the website download.');

        $zip = new ZipArchive();
        if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);
            abort(500, 'Could not create the website archive.');
        }

        $storagePaths = WebsiteUserImage::where('website_id', $website->id)
            ->where('is_deleted', false)
            ->pluck('image_file_path')
            ->all();
        $publicAssets = [];

        foreach ($files as $file) {
            $archivePath = $this->archivePath($file->folder, $file->filename);
            $content = $file->content;

            if (in_array(strtolower((string) $file->filetype), ['html', 'htm', 'css', 'js', 'php'], true)) {
                $content = preg_replace_callback(
                    '~(?:https?://[^\s"\'()]+)?/storage/([^\s"\'?#)]+)~i',
                    function (array $matches) use (&$storagePaths, $archivePath) {
                        $storagePath = rawurldecode($matches[1]);
                        $storagePaths[] = $storagePath;

                        return $this->relativePath($archivePath, 'storage/' . $storagePath);
                    },
                    $content
                );

                $content = preg_replace_callback(
                    '~(?:https?://[^\s"\'()]+)?/images/demo-sites/([^\s"\'?#)]+)~i',
                    function (array $matches) use (&$publicAssets, $archivePath) {
                        $sourcePath = 'images/demo-sites/' . rawurldecode($matches[1]);
                        $targetPath = 'assets/' . basename($sourcePath);

                        if (!str_contains($sourcePath, '..')) {
                            $publicAssets[$sourcePath] = $targetPath;
                        }

                        return $this->relativePath($archivePath, $targetPath);
                    },
                    $content
                );
            }

            $zip->addFromString($archivePath, $content);
        }

        foreach (array_unique($storagePaths) as $storagePath) {
            $storagePath = ltrim(str_replace('\\', '/', $storagePath), '/');
            if ($storagePath !== '' && !str_contains($storagePath, '..') && Storage::disk('public')->exists($storagePath)) {
                $zip->addFile(Storage::disk('public')->path($storagePath), 'storage/' . $storagePath);
            }
        }

        foreach ($publicAssets as $sourcePath => $targetPath) {
            $fullPath = public_path($sourcePath);
            if (is_file($fullPath)) {
                $zip->addFile($fullPath, $targetPath);
            }
        }

        $zip->close();

        $downloadName = Str::slug($website->name ?: 'author-website') . '.zip';

        return response()->download($temporaryPath, $downloadName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    private function archivePath(string $folder, string $filename): string
    {
        return ltrim(trim($folder, '/') . '/' . basename($filename), '/');
    }

    private function relativePath(string $fromFile, string $toFile): string
    {
        $fromParts = array_values(array_filter(explode('/', dirname($fromFile)), fn ($part) => $part !== '.'));
        $toParts = array_values(array_filter(explode('/', $toFile)));

        while ($fromParts && $toParts && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        return str_repeat('../', count($fromParts)) . implode('/', $toParts);
    }
}
