<?php

namespace Database\Seeders;

use App\Models\AnalysisCategory;
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
        $this->seedAssignments();
    }

    private function seedUsers(): void
    {
        $users = [
            ['name' => 'Admin', 'email' => 'admin@nppc.local', 'role' => 'admin'],
            ['name' => 'Receiving Staff', 'email' => 'receiving@nppc.local', 'role' => 'receiving'],
            ['name' => 'Lab Analyst', 'email' => 'analyst@nppc.local', 'role' => 'analyst'],
            ['name' => 'Head Analysis', 'email' => 'head@nppc.local', 'role' => 'head_analysis'],
        ];

        foreach ($users as $data) {
            $user = User::query()->updateOrCreate(
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
                AnalysisType::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'category_id' => $category->id,
                        'default_price' => $price,
                        'is_active' => true,
                        'sort_order' => $sort++,
                    ],
                );
            }
        }
    }

    private function seedAssignments(): void
    {
        $analyst = User::where('email', 'analyst@nppc.local')->first();
        if (! $analyst) {
            return;
        }

        $ids = AnalysisType::query()->pluck('id');
        $analyst->analysisTypes()->sync($ids);
    }
}
