<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RfaFormTemplateService
{
    public const SETTING_KEY = 'rfa_form_template';

    public const SOURCE_DIR = 'form-templates/rfa/source';

    public const FILLABLE_DIR = 'form-templates/rfa/fillable';

    /**
     * @return array{
     *     source_path?: string|null,
     *     fillable_path?: string|null,
     *     original_name?: string|null,
     *     uploaded_at?: string|null,
     *     uploaded_by?: string|null,
     *     notes?: string|null
     * }
     */
    public function meta(): array
    {
        return Setting::getValue(self::SETTING_KEY, []);
    }

    public function hasSource(): bool
    {
        $path = $this->meta()['source_path'] ?? null;

        return is_string($path) && $path !== '' && Storage::disk('local')->exists($path);
    }

    public function hasFillable(): bool
    {
        $path = $this->meta()['fillable_path'] ?? null;

        return is_string($path) && $path !== '' && Storage::disk('local')->exists($path);
    }

    public function sourceAbsolutePath(): string
    {
        $path = $this->meta()['source_path'] ?? null;

        if (! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('No RFA flat PDF template has been uploaded yet.');
        }

        return Storage::disk('local')->path($path);
    }

    public function fillableAbsolutePath(): string
    {
        $path = $this->meta()['fillable_path'] ?? null;

        if (! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('No fillable RFA PDF has been generated yet.');
        }

        return Storage::disk('local')->path($path);
    }

    /**
     * @return array{source_path: string, fillable_path: string, original_name: string, uploaded_at: string, uploaded_by: string|null, notes: string|null}
     */
    public function storeSource(UploadedFile $file, ?User $uploader = null, ?string $notes = null): array
    {
        Storage::disk('local')->makeDirectory(self::SOURCE_DIR);
        Storage::disk('local')->makeDirectory(self::FILLABLE_DIR);

        $this->deleteStoredFiles();

        $filename = Str::uuid()->toString().'.pdf';
        $sourcePath = self::SOURCE_DIR.'/'.$filename;
        Storage::disk('local')->putFileAs(self::SOURCE_DIR, $file, $filename);

        $fillablePath = self::FILLABLE_DIR.'/'.$filename;

        $meta = [
            'source_path' => $sourcePath,
            'fillable_path' => $fillablePath,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => $uploader?->name,
            'notes' => $notes,
        ];

        Setting::putValue(self::SETTING_KEY, $meta);

        app(RfaFormMakeFillable::class)->generate();

        return $meta;
    }

    public function regenerateFillable(): void
    {
        if (! $this->hasSource()) {
            throw new RuntimeException('Upload a flat PDF before regenerating the fillable template.');
        }

        app(RfaFormMakeFillable::class)->generate();
    }

    /**
     * @return list<array{name: string, type: string, page: int, x: float, y: float, w: float, h: float, font_size?: float, align?: string}>
     */
    public function fields(): array
    {
        $override = Setting::getValue('rfa_form_field_map', []);

        if (isset($override['fields']) && is_array($override['fields']) && $override['fields'] !== []) {
            return array_values($override['fields']);
        }

        /** @var list<array{name: string, type: string, page: int, x: float, y: float, w: float, h: float, font_size?: float, align?: string}> $fields */
        $fields = config('rfa_form_fields.fields', []);

        return $fields;
    }

    /**
     * @return array{width: float, height: float, unit: string}
     */
    public function page(): array
    {
        $override = Setting::getValue('rfa_form_field_map', []);

        if (isset($override['page']) && is_array($override['page'])) {
            return array_merge(config('rfa_form_fields.page', []), $override['page']);
        }

        /** @var array{width: float, height: float, unit: string} $page */
        $page = config('rfa_form_fields.page', [
            'width' => 215.9,
            'height' => 330.2,
            'unit' => 'mm',
        ]);

        return $page;
    }

    private function deleteStoredFiles(): void
    {
        $meta = $this->meta();

        foreach (['source_path', 'fillable_path'] as $key) {
            $path = $meta[$key] ?? null;
            if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
