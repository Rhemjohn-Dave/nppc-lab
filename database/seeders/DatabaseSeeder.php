<?php

namespace Database\Seeders;

use App\Enums\AnalysisPackageReportLayout;
use App\Enums\ControlledFormCategory;
use App\Enums\JobOrderVariant;
use App\Models\AnalysisCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\User;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['admin', 'receiving', 'analyst', 'head_analysis', 'accounting'] as $role) {
            Role::findOrCreate($role);
        }

        $this->seedUsers();
        $this->seedCatalog();
        $this->seedPackages();
        $this->seedJobOrderForms();
        $this->seedResultFormShells();
        $this->call(ControlledFormDefaultsSeeder::class);
        $this->seedAssignments();
    }

    private function seedUsers(): void
    {
        $users = [
            ['name' => 'Admin', 'email' => 'admin@nppc.local', 'role' => 'admin'],
            ['name' => 'Receiving Staff', 'email' => 'receiving@nppc.local', 'role' => 'receiving'],
            ['name' => 'Lab Analyst', 'email' => 'analyst@nppc.local', 'role' => 'analyst'],
            ['name' => 'Lab Analyst 2', 'email' => 'analyst2@nppc.local', 'role' => 'analyst'],
            ['name' => 'Head Analysis', 'email' => 'head@nppc.local', 'role' => 'head_analysis'],
            ['name' => 'Accounting', 'email' => 'accounting@nppc.local', 'role' => 'accounting'],
        ];

        foreach ($users as $data) {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
            $user->syncRoles([$data['role']]);
        }
    }

    private function seedCatalog(): void
    {
        $sort = 0;
        $activeCodes = [];
        $packageOnly = OfficialAnalysisCatalog::packageOnlyCodes();

        foreach (OfficialAnalysisCatalog::definitions() as $categorySlug => $items) {
            $category = AnalysisCategory::query()->firstOrCreate(
                ['slug' => $categorySlug],
                [
                    'name' => \App\Enums\AnalysisCategory::tryFrom($categorySlug)?->label() ?? $categorySlug,
                    'sort_order' => $sort,
                    'is_active' => true,
                ],
            );

            $category->fill([
                'name' => \App\Enums\AnalysisCategory::tryFrom($categorySlug)?->label() ?? $category->name,
                'is_active' => true,
            ])->save();

            foreach ($items as $row) {
                [$code, $name, $price] = $row;
                $scope = OfficialAnalysisCatalog::scopeForDefinition($categorySlug, $row)->value;
                $activeCodes[] = $code;

                $method = OfficialAnalysisCatalog::methodForCode($code);
                $acceptable = OfficialAnalysisCatalog::acceptableValuesForCode($code);

                $type = AnalysisType::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'method' => $method,
                        'acceptable_values' => $acceptable,
                        'category_id' => $category->id,
                        'default_price' => $price,
                        'is_active' => true,
                        'show_on_kiosk' => ! in_array($code, $packageOnly, true),
                        'catalog_scope' => $scope,
                        'sort_order' => $sort++,
                    ],
                );

                $fill = [
                    'name' => $name,
                    'category_id' => $category->id,
                    'default_price' => $price,
                    'is_active' => true,
                    'show_on_kiosk' => ! in_array($code, $packageOnly, true),
                    'catalog_scope' => $scope,
                    'sort_order' => $type->wasRecentlyCreated ? $type->sort_order : $type->sort_order,
                ];
                if ($method !== null) {
                    $fill['method'] = $method;
                }
                if ($acceptable !== null) {
                    $fill['acceptable_values'] = $acceptable;
                }

                $type->fill($fill)->save();
            }
        }

        AnalysisType::query()
            ->whereNotIn('code', $activeCodes)
            ->update([
                'is_active' => false,
                'show_on_kiosk' => false,
            ]);
    }

    private function seedPackages(): void
    {
        $micro = AnalysisCategory::query()->where('slug', 'microbiological')->first();
        $drinking = AnalysisCategory::query()->where('slug', 'drinking_water')->first();

        $total = AnalysisType::query()->where('code', 'MB-02A')->first();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->first();
        $hpc = AnalysisType::query()->where('code', 'MB-01')->first();

        if (! $micro || ! $total || ! $thermo) {
            return;
        }

        $total->update([
            'show_on_kiosk' => false,
            'is_active' => true,
            'result_mode' => AnalysisType::RESULT_MODE_PASS_FAIL,
        ]);
        $thermo->update([
            'show_on_kiosk' => false,
            'is_active' => true,
            'result_mode' => AnalysisType::RESULT_MODE_PASS_FAIL,
        ]);
        $hpc?->update([
            'is_active' => true,
            'show_on_kiosk' => true,
            'result_mode' => AnalysisType::RESULT_MODE_PASS_FAIL,
        ]);

        $analystId = User::where('email', 'analyst@nppc.local')->value('id');
        $ids = function (array $codes): array {
            $byCode = AnalysisType::query()
                ->whereIn('code', $codes)
                ->get()
                ->keyBy('code');

            return collect($codes)
                ->map(fn (string $code) => $byCode->get($code)?->id)
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();
        };

        $retained = ['PKG-MIC-NDW', 'PKG-DW-PHYSICO', 'PKG-DW-BACT'];

        $this->ensurePackage(
            'PKG-MIC-NDW',
            [
                'name' => 'Microbiological Examination — Non-Drinking Water',
                'description' => 'Total Coliform and Thermotolerant Coliform (MPN/100ml) for wastewater / non-potable samples (₱750). Result sheet: LSP 7.8 FO4.',
                'category_id' => $micro->id,
                'default_price' => 750,
                'classifications' => ['Wastewater'],
                'form_code' => 'LSP-7.8-FO4',
                'signatory_user_id' => $analystId,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [$total->id, $thermo->id],
        );

        if ($drinking) {
            $this->ensurePackage(
                'PKG-DW-PHYSICO',
                [
                    'name' => 'Drinking Water — Physico-Chemical (Mandatory)',
                    'description' => 'Mandatory physico-chemical parameters (₱4,900). Result sheet: LSP 7.8 FO37 Issue 07 (dynamic matrix).',
                    'category_id' => $drinking->id,
                    'default_price' => 4900,
                    'classifications' => ['Potability'],
                    'form_code' => 'LSP-7.8-FO37',
                    'report_layout' => AnalysisPackageReportLayout::DynamicMatrix->value,
                    'signatory_user_id' => $analystId,
                    'is_active' => true,
                    'sort_order' => 2,
                ],
                // Issue 7 print order (9 matrix rows): Color, TDS, Turbidity, pH, Residual Chlorine, Nitrate, As, Pb, Cd.
                $ids(['DW-01', 'DW-03', 'DW-04', 'DW-02', 'DW-05', 'DW-06', 'DW-07', 'DW-08', 'DW-09']),
            );

            $this->ensurePackage(
                'PKG-DW-BACT',
                [
                    'name' => 'Drinking Water — Bacteriological (HPC, TC, FC)',
                    'description' => 'Bacteriological package price ₱300 (HPC, Total Coliform, Fecal Coliform).',
                    'category_id' => $drinking->id,
                    'default_price' => 300,
                    'classifications' => ['Potability'],
                    'form_code' => null,
                    'signatory_user_id' => $analystId,
                    'is_active' => true,
                    'sort_order' => 3,
                ],
                $ids(['DW-BACT-HPC', 'DW-BACT-TC', 'DW-BACT-FC']),
            );
        }

        // Drop legacy/shell packages from older seeds (keep only the three paid offerings).
        AnalysisPackage::query()
            ->whereNotIn('code', $retained)
            ->get()
            ->each(function (AnalysisPackage $package): void {
                ControlledForm::query()
                    ->where('analysis_package_id', $package->id)
                    ->update(['analysis_package_id' => null]);
                $package->analysisTypes()->detach();

                if ($package->jobOrders()->exists()) {
                    $package->update(['is_active' => false]);

                    return;
                }

                $package->delete();
            });
    }

    private function seedJobOrderForms(): void
    {
        ControlledForm::query()->firstOrCreate(
            ['form_code' => ControlledForm::RFA_FORM_CODE],
            [
                'name' => 'Request for Analysis Form / Job Order',
                'description' => 'General (non-Aqua) Job Order — LSP 7.1 FO1 Issue 11. Includes sampling site + payment mode/terms.',
                'department' => 'Laboratory',
                'category' => ControlledFormCategory::JobOrder,
                'job_order_variant' => JobOrderVariant::General,
            ],
        );

        ControlledForm::query()->firstOrCreate(
            ['form_code' => ControlledForm::RFA_AQUA_FORM_CODE],
            [
                'name' => 'Request for Analysis Form / Job Order (Aqua)',
                'description' => 'Aqua Job Order — LSP 7.1 FO4 Issue 03. Upload official Word/PDF and map fields in Form Designer.',
                'department' => 'Laboratory',
                'category' => ControlledFormCategory::JobOrder,
                'job_order_variant' => JobOrderVariant::Aqua,
            ],
        );

        ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE)
            ->whereNull('job_order_variant')
            ->update(['job_order_variant' => JobOrderVariant::General->value]);

        $this->seedGeneralJobOrderPdfAndFields();
    }

    private function seedGeneralJobOrderPdfAndFields(): void
    {
        $admin = User::query()->where('email', 'admin@nppc.local')->first();
        $form = ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE)
            ->first();
        $absolute = resource_path('forms/official/JOB ORDER Issue 11 09012026.pdf');

        if (! $admin || ! $form || ! is_file($absolute)) {
            return;
        }

        $forms = app(\App\Services\ControlledFormService::class);
        $workflow = app(\App\Services\RevisionWorkflow::class);

        $revision = $form->activeRevision() ?? $form->revisions()->first();
        if (! $revision) {
            $upload = new \Illuminate\Http\UploadedFile(
                $absolute,
                'JOB ORDER Issue 11 09012026.pdf',
                'application/pdf',
                null,
                true,
            );
            $revision = $forms->createRevision(
                $form,
                [
                    'revision' => '01',
                    'notes' => 'Seeded Issue 11 PDF with sampling site + payment overlays.',
                    'blank_fields' => true,
                ],
                $admin,
                $upload,
            );
        } elseif (! $revision->hasCanonicalPdf() && $revision->status === \App\Enums\ControlledFormRevisionStatus::Draft) {
            $upload = new \Illuminate\Http\UploadedFile(
                $absolute,
                'JOB ORDER Issue 11 09012026.pdf',
                'application/pdf',
                null,
                true,
            );
            $forms->attachFile($form, $revision, $upload);
            $revision = $revision->fresh();
        }

        if ($revision && ! $revision->fields()->where('name', 'sampling_site')->exists() && $forms->hasBlueprint($form)) {
            $forms->importBlueprint($revision->fresh());
        }

        if (
            $revision
            && $revision->hasCanonicalPdf()
            && $revision->fields()->exists()
            && $revision->status !== \App\Enums\ControlledFormRevisionStatus::Active
        ) {
            $workflow->activate($revision->fresh(), $admin);
        }
    }

    private function seedResultFormShells(): void
    {
        $admin = User::query()->where('email', 'admin@nppc.local')->first();
        $forms = app(\App\Services\ControlledFormService::class);
        $workflow = app(\App\Services\RevisionWorkflow::class);

        foreach (OfficialAnalysisCatalog::resultFormRegistry() as $meta) {
            $package = null;
            if (! empty($meta['package_code'])) {
                $package = AnalysisPackage::query()->where('code', $meta['package_code'])->first();
            }

            $form = ControlledForm::query()->firstOrCreate(
                ['form_code' => $meta['form_code']],
                [
                    'name' => $meta['name'],
                    'description' => sprintf(
                        'Official %s · %s · Eff. %s. Upload source file from resources/forms/official and activate after mapping.',
                        $meta['official'],
                        $meta['revision'],
                        $meta['effective'],
                    ),
                    'department' => 'Laboratory',
                    'category' => ControlledFormCategory::AnalysisResult,
                    'analysis_package_id' => $package?->id,
                ],
            );

            if (in_array($meta['form_code'] ?? null, ['LSP-7.8-FO37', 'LSP-7.8-FO3', 'LSP-7.8-F016-PROX'], true)) {
                $form->fill([
                    'analyst_signatory_slots' => 2,
                    'analyst_require_prc' => true,
                ])->save();
            }

            // FO26 / FO27 Issue 4: one Analyzed-by + static Laboratory Head; no PRC on sheet.
            if (in_array($meta['form_code'] ?? null, ['LSP-7.8-FO26', 'LSP-7.8-FO27'], true)) {
                $form->fill([
                    'analyst_signatory_slots' => 1,
                    'analyst_require_prc' => false,
                ])->save();
            }

            // F016-MILK / F016-NO2: one Analyzed-by (Ferresa) + PRC; Approved by static Wendy + PRC.
            if (in_array($meta['form_code'] ?? null, ['LSP-7.8-F016-MILK', 'LSP-7.8-F016-NO2'], true)) {
                $form->fill([
                    'analyst_signatory_slots' => 1,
                    'analyst_require_prc' => true,
                ])->save();
            }

            if (($meta['form_code'] ?? null) === 'LSP-7.8-FO2') {
                $form->fill([
                    'analyst_signatory_slots' => 4,
                    'analyst_require_prc' => true,
                ])->save();
            }

            if (($meta['form_code'] ?? null) === 'LSP-7.8-FO2A') {
                $form->fill([
                    'analyst_signatory_slots' => 3,
                    'analyst_require_prc' => true,
                ])->save();
            }

            if ($package) {
                if ($form->analysis_package_id !== $package->id) {
                    $form->update(['analysis_package_id' => $package->id]);
                }
                if ($package->form_code !== $meta['form_code']) {
                    $package->update(['form_code' => $meta['form_code']]);
                }
                // Keep analysis-type pivot aligned with package members (needed for FO4/FO5 slots).
                $forms->syncBindings($form, $package->orderedTypeIds(), $package->id);
            } elseif (! empty($meta['type_codes']) && is_array($meta['type_codes'])) {
                $typeIds = AnalysisType::query()
                    ->whereIn('code', $meta['type_codes'])
                    ->get()
                    ->keyBy('code');

                $orderedIds = collect($meta['type_codes'])
                    ->map(fn (string $code) => $typeIds->get($code)?->id)
                    ->filter()
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all();

                if ($orderedIds !== []) {
                    $form->update(['analysis_package_id' => null]);
                    $forms->syncBindings($form, $orderedIds, null);
                }
            }

            if (! $admin || ! $forms->hasBlueprint($form)) {
                continue;
            }

            $revision = $form->revisions()->firstOrCreate(
                ['revision' => '01'],
                [
                    'status' => \App\Enums\ControlledFormRevisionStatus::Draft,
                    'created_by' => $admin->id,
                    'fill_mode' => \App\Models\ControlledFormRevision::FILL_MODE_OVERLAY,
                    'notes' => 'Seeded from field blueprint. Upload the official PDF, then activate.',
                ],
            );

            $sourcePdf = $meta['source_pdf'] ?? null;
            if (is_string($sourcePdf) && $sourcePdf !== '' && ! $revision->hasCanonicalPdf()) {
                $absolute = resource_path('forms/official/'.$sourcePdf);
                if (is_file($absolute)) {
                    $upload = new \Illuminate\Http\UploadedFile(
                        $absolute,
                        $sourcePdf,
                        'application/pdf',
                        null,
                        true,
                    );
                    $forms->attachFile($form, $revision->fresh(), $upload);
                    $revision = $revision->fresh();
                }
            }

            if (! $revision->fields()->exists()) {
                $forms->importBlueprint($revision);
            }

            if (
                in_array($meta['form_code'] ?? null, ['LSP-7.8-FO37', 'LSP-7.8-FO3', 'LSP-7.8-FO2', 'LSP-7.8-FO2A', 'LSP-7.8-FO26', 'LSP-7.8-FO27', 'LSP-7.8-F016-MILK', 'LSP-7.8-F016-PROX', 'LSP-7.8-F016-NO2'], true)
                && $revision->hasCanonicalPdf()
                && $revision->fields()->exists()
                && $revision->status !== \App\Enums\ControlledFormRevisionStatus::Active
            ) {
                $workflow->activate($revision->fresh(), $admin);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $typeIds
     */
    private function ensurePackage(string $code, array $attributes, array $typeIds): void
    {
        if ($typeIds === []) {
            return;
        }

        $package = AnalysisPackage::query()->firstOrCreate(
            ['code' => $code],
            $attributes,
        );

        $package->syncTypes($typeIds);

        $updates = [];
        foreach (['form_code', 'classifications', 'description', 'name', 'default_price', 'sort_order', 'report_layout', 'category_id', 'is_active'] as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            if ($field === 'report_layout') {
                $current = $package->report_layout instanceof AnalysisPackageReportLayout
                    ? $package->report_layout->value
                    : (string) $package->report_layout;
                if ($current !== (string) $attributes[$field]) {
                    $updates[$field] = $attributes[$field];
                }

                continue;
            }

            if ($field === 'classifications' || $package->{$field} != $attributes[$field]) {
                $updates[$field] = $attributes[$field];
            }
        }
        if (! $package->signatory_user_id && ! empty($attributes['signatory_user_id'])) {
            $updates['signatory_user_id'] = $attributes['signatory_user_id'];
        }
        if ($updates !== []) {
            $package->update($updates);
        }
    }

    private function seedAssignments(): void
    {
        $ids = AnalysisType::query()->where('is_active', true)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        foreach (['analyst@nppc.local', 'analyst2@nppc.local'] as $email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->analysisTypes()->syncWithoutDetaching($ids);
            }
        }
    }
}
