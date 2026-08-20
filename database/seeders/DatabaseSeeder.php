<?php

namespace Database\Seeders;

use App\Models\AnalysisCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
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

        foreach (['admin', 'receiving', 'analyst', 'head_analysis'] as $role) {
            Role::findOrCreate($role);
        }

        $this->seedUsers();
        $this->seedCatalog();
        $this->seedPackages();
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
        foreach (OfficialAnalysisCatalog::definitions() as $categorySlug => $items) {
            $category = AnalysisCategory::query()->where('slug', $categorySlug)->first();

            if (! $category) {
                continue;
            }

            foreach ($items as [$code, $name, $price]) {
                AnalysisType::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'category_id' => $category->id,
                        'default_price' => $price,
                        'is_active' => true,
                        'show_on_kiosk' => ! in_array($code, ['MB-02A', 'MB-02B'], true),
                        'sort_order' => $sort++,
                    ],
                );
            }
        }
    }

    private function seedPackages(): void
    {
        $micro = AnalysisCategory::query()->where('slug', 'microbiological')->first();
        $total = AnalysisType::query()->where('code', 'MB-02A')->first();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->first();
        $hpc = AnalysisType::query()->where('code', 'MB-01')->first();

        if (! $micro || ! $total || ! $thermo) {
            return;
        }

        AnalysisType::query()->where('code', 'MB-02')->update([
            'is_active' => false,
            'show_on_kiosk' => false,
        ]);

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
            'result_mode' => AnalysisType::RESULT_MODE_PASS_FAIL,
        ]);

        $analystId = User::where('email', 'analyst@nppc.local')->value('id');

        $this->ensurePackage(
            'PKG-MIC-NDW',
            [
                'name' => 'Microbiological Examination — Non-Drinking Water',
                'description' => 'Total Coliform and Thermotolerant Coliform (MPN/100ml) for wastewater / non-potable samples. Result sheet: LSP 7.8 FO4.',
                'category_id' => $micro->id,
                'default_price' => 450,
                'classifications' => ['Wastewater'],
                'form_code' => 'LSP 7.8 FO4',
                'signatory_user_id' => $analystId,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [$total->id, $thermo->id],
        );

        if ($hpc) {
            $this->ensurePackage(
                'PKG-MIC-DW',
                [
                    'name' => 'Microbiological Examination — Drinking Water',
                    'description' => 'Total Coliform, Thermotolerant Coliform (MPN/100ml), and HPC (CFU/ml) for drinking water / potability. Result sheet: LSP 7.8 FO5. Package price is a placeholder.',
                    'category_id' => $micro->id,
                    'default_price' => 900,
                    'classifications' => ['Potability'],
                    'form_code' => 'LSP 7.8 FO5',
                    'signatory_user_id' => $analystId,
                    'is_active' => true,
                    'sort_order' => 2,
                ],
                [$total->id, $thermo->id, $hpc->id],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $typeIds
     */
    private function ensurePackage(string $code, array $attributes, array $typeIds): void
    {
        $package = AnalysisPackage::query()->firstOrCreate(
            ['code' => $code],
            $attributes,
        );

        if (! $package->analysisTypes()->exists()) {
            $package->syncTypes($typeIds);
        }

        if (! $package->signatory_user_id && ! empty($attributes['signatory_user_id'])) {
            $package->update(['signatory_user_id' => $attributes['signatory_user_id']]);
        }
    }

    private function seedAssignments(): void
    {
        $analyst = User::where('email', 'analyst@nppc.local')->first();
        if (! $analyst) {
            return;
        }

        $ids = AnalysisType::query()->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        if (! $analyst->analysisTypes()->exists()) {
            $analyst->analysisTypes()->sync($ids);
        }

        $second = User::where('email', 'analyst2@nppc.local')->first();
        if ($second && ! $second->analysisTypes()->exists()) {
            $second->analysisTypes()->sync($ids);
        }
    }
}
