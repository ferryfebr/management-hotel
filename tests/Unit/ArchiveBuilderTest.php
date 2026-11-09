<?php

namespace Tests\Unit;

use App\Support\ArchiveBuilder;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ArchiveBuilderTest extends TestCase
{
    public function test_csv_has_bom_and_escapes_semicolon_values(): void
    {
        $csv = ArchiveBuilder::csv(['nama', 'catatan'], [
            ['Budi; Santoso', 'baris "kutip"'],
        ]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('nama;catatan', $csv);
        $this->assertStringContainsString('"Budi; Santoso"', $csv);
    }

    public function test_zip_can_include_string_and_source_file(): void
    {
        Storage::fake('private');
        $disk = Storage::disk('private');
        $disk->put('source/photo.jpg', 'photo-bytes');

        $size = ArchiveBuilder::zip('private', 'archives/test.zip', [
            'data.csv' => 'a;b',
            'photo.jpg' => ['source' => $disk->path('source/photo.jpg')],
        ]);

        $this->assertGreaterThan(0, $size);
        $disk->assertExists('archives/test.zip');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($disk->path('archives/test.zip')) === true);
        $this->assertSame('a;b', $zip->getFromName('data.csv'));
        $this->assertSame('photo-bytes', $zip->getFromName('photo.jpg'));
        $zip->close();
    }
}
