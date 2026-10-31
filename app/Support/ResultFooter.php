<?php

namespace App\Support;

use App\Models\ControlledForm;
use App\Models\JobOrder;

class ResultFooter
{
    /**
     * @return array{lab_conditions: string, references: string, notes: string}
     */
    public static function defaultsFor(?ControlledForm $form, ?JobOrder $jobOrder = null): array
    {
        $description = trim((string) ($jobOrder?->samples->first()?->description ?? ''));
        $variant = ($form?->form_code === 'LSP-7.8-FO2A' || OfficialAnalysisCatalog::isWastewaterAbstractedKind($description))
            ? 'abstracted'
            : 'full';

        return [
            'lab_conditions' => OfficialAnalysisCatalog::fo2LabConditionsText($variant),
            'references' => OfficialAnalysisCatalog::fo2ReferencesText(),
            'notes' => $variant === 'abstracted'
                ? OfficialAnalysisCatalog::fo2AbstractedNotesText()
                : OfficialAnalysisCatalog::fo2FullNotesText(),
        ];
    }

    /**
     * @return array{lab_conditions: string, references: string, notes: string, save_url: string|null, can_edit: bool}
     */
    public static function manifestPayload(
        JobOrder $jobOrder,
        ?ControlledForm $form,
        ?string $saveUrl = null,
    ): array {
        $jobOrder->loadMissing('samples');
        $defaults = self::defaultsFor($form, $jobOrder);
        $stored = is_array($jobOrder->result_footer) ? $jobOrder->result_footer : [];

        $value = static function (string $key) use ($stored, $defaults): string {
            $override = trim((string) ($stored[$key] ?? ''));

            return $override !== '' ? $override : $defaults[$key];
        };

        return [
            'lab_conditions' => $value('lab_conditions'),
            'references' => $value('references'),
            'notes' => $value('notes'),
            'save_url' => $saveUrl,
            'can_edit' => filled($saveUrl),
        ];
    }

    /**
     * @param  array{lab_conditions?: mixed, references?: mixed, notes?: mixed}  $payload
     * @return array{lab_conditions: string|null, references: string|null, notes: string|null}
     */
    public static function normalizeForStorage(array $payload): array
    {
        $normalize = static function (mixed $value): ?string {
            $text = trim((string) ($value ?? ''));

            return $text !== '' ? $text : null;
        };

        return [
            'lab_conditions' => $normalize($payload['lab_conditions'] ?? null),
            'references' => $normalize($payload['references'] ?? null),
            'notes' => $normalize($payload['notes'] ?? null),
        ];
    }

    public static function formSupportsFooter(?ControlledForm $form): bool
    {
        return in_array($form?->form_code, ['LSP-7.8-FO2', 'LSP-7.8-FO2A'], true);
    }
}
