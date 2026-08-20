<?php

namespace App\Services;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Models\AnalysisType;
use App\Models\JobOrderAnalysis;
use App\Models\User;

class AnalystAssignmentPicker
{
    /**
     * Open (not completed) task counts for analysts on jobs still in analysis.
     *
     * @return array<int, int>
     */
    public function openLoads(): array
    {
        return JobOrderAnalysis::query()
            ->whereNotNull('assigned_to')
            ->whereIn('status', [
                JobOrderAnalysisStatus::Assigned,
                JobOrderAnalysisStatus::Pending,
                JobOrderAnalysisStatus::InProgress,
                JobOrderAnalysisStatus::Returned,
            ])
            ->whereHas('jobOrder', function ($query) {
                $query->where('status', JobOrderStatus::InAnalysis);
            })
            ->selectRaw('assigned_to, count(*) as aggregate')
            ->groupBy('assigned_to')
            ->pluck('aggregate', 'assigned_to')
            ->all();
    }

    /**
     * @param  array<int, int>  $loads
     */
    public function pick(?AnalysisType $type, array &$loads): ?User
    {
        if (! $type) {
            return null;
        }

        $type->loadMissing('analysts');

        $chosen = $type->analysts
            ->sortBy(fn (User $user) => sprintf(
                '%08d-%08d',
                $loads[(int) $user->id] ?? 0,
                $user->id,
            ))
            ->first();

        if (! $chosen instanceof User) {
            return null;
        }

        $id = (int) $chosen->id;
        $loads[$id] = ($loads[$id] ?? 0) + 1;

        return $chosen;
    }
}
