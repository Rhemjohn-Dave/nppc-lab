<?php

namespace App\Support\Dashboard;

use App\Enums\ControlledFormRevisionStatus;
use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\DocumentAuditLog;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardPayloadBuilder
{
    private const PREVIEW_LIMIT = 5;

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $role = $this->resolveRole($user);

        $payload = match ($role) {
            'admin' => $this->adminPayload(),
            'receiving' => $this->receivingPayload(),
            'analyst' => $this->analystPayload($user),
            'head' => $this->headPayload(),
            default => $this->genericPayload(),
        };

        $payload['header']['greeting_name'] = $user->name;
        $payload['queue']['preview_limit'] = self::PREVIEW_LIMIT;

        $allLinks = $payload['links'];
        $payload['links'] = [
            'primary' => $allLinks[0] ?? null,
            'secondary' => array_values(array_slice($allLinks, 1)),
        ];

        return $payload;
    }

    public function resolveRole(User $user): string
    {
        if ($user->hasRole('admin')) {
            return 'admin';
        }

        if ($user->hasRole('head_analysis')) {
            return 'head';
        }

        if ($user->hasRole('receiving')) {
            return 'receiving';
        }

        if ($user->hasRole('analyst')) {
            return 'analyst';
        }

        return 'generic';
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPayload(): array
    {
        $activeForms = ControlledForm::query()
            ->whereHas('revisions', fn ($q) => $q->where('status', ControlledFormRevisionStatus::Active))
            ->count();

        $pendingRevisions = ControlledFormRevision::query()
            ->whereIn('status', [
                ControlledFormRevisionStatus::ForReview,
                ControlledFormRevisionStatus::ForApproval,
            ])
            ->count();

        $draftRevisions = ControlledFormRevision::query()
            ->where('status', ControlledFormRevisionStatus::Draft)
            ->count();

        $auditEvents7d = DocumentAuditLog::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $attentionRevisions = ControlledFormRevision::query()
            ->with('form:id,name,form_code')
            ->whereIn('status', [
                ControlledFormRevisionStatus::ForReview,
                ControlledFormRevisionStatus::ForApproval,
            ])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (ControlledFormRevision $revision) => [
                'text' => sprintf(
                    '%s · Rev %s — %s',
                    $revision->form?->name ?? 'Form',
                    $revision->revision,
                    $revision->status->label(),
                ),
                'href' => $revision->form
                    ? '/admin/controlled-forms/'.$revision->form->id
                    : '/admin/controlled-forms',
            ])
            ->values()
            ->all();

        $formsTable = ControlledForm::query()
            ->with(['currentRevision'])
            ->withCount('revisions')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(function (ControlledForm $form) {
                $current = $form->currentRevision ?? $form->activeRevision();

                return [
                    'id' => $form->id,
                    'form' => $form->name,
                    'form_code' => $form->form_code,
                    'revision' => $current?->revision,
                    'status' => $current?->status->value,
                    'status_label' => $current?->status->label() ?? 'NO REVISION',
                    'effective_date' => $current?->effective_date?->format('M j, Y'),
                    'updated_at' => $form->updated_at?->format('M j, Y'),
                    'href' => '/admin/controlled-forms/'.$form->id,
                    'action_label' => 'View',
                ];
            })
            ->values()
            ->all();

        $activity = DocumentAuditLog::query()
            ->with('user:id,name')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (DocumentAuditLog $log) => [
                'title' => $log->action,
                'meta' => trim(($log->user?->name ?? 'System').' · '.class_basename($log->auditable_type).'#'.$log->auditable_id),
                'time' => $log->created_at?->diffForHumans(),
                'href' => '/admin/document-audit',
            ])
            ->values()
            ->all();

        $statusCounts = JobOrder::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $workflowStrip = collect(JobOrderStatus::cases())->map(fn (JobOrderStatus $status) => [
            'status' => $status->label(),
            'count' => (int) ($statusCounts[$status->value] ?? 0),
        ])->values()->all();

        $attentionItems = $attentionRevisions;
        if ($draftRevisions > 0) {
            array_unshift($attentionItems, [
                'text' => $draftRevisions.' draft revision'.($draftRevisions === 1 ? '' : 's').' need work',
                'href' => '/admin/controlled-forms',
            ]);
        }

        return [
            'role' => 'admin',
            'header' => [
                'title' => 'Laboratory Administration',
                'subtitle' => 'System overview, document control, and laboratory activity.',
            ],
            'kpis' => [
                [
                    'key' => 'active_forms',
                    'label' => 'Active controlled forms',
                    'value' => $activeForms,
                    'href' => '/admin/controlled-forms',
                    'tone' => 'default',
                ],
                [
                    'key' => 'pending_revisions',
                    'label' => 'Pending revisions',
                    'value' => $pendingRevisions,
                    'href' => '/admin/controlled-forms',
                    'tone' => $pendingRevisions > 0 ? 'warning' : 'default',
                ],
                [
                    'key' => 'draft_revisions',
                    'label' => 'Draft revisions',
                    'value' => $draftRevisions,
                    'href' => '/admin/controlled-forms',
                    'tone' => $draftRevisions > 0 ? 'info' : 'default',
                ],
                [
                    'key' => 'audit_events_7d',
                    'label' => 'Audit events (7 days)',
                    'value' => $auditEvents7d,
                    'href' => '/admin/document-audit',
                    'tone' => 'default',
                ],
            ],
            'needsAttention' => [
                'title' => 'Needs attention',
                'summary' => $pendingRevisions > 0
                    ? $pendingRevisions.' revision'.($pendingRevisions === 1 ? '' : 's').' awaiting review or approval'
                    : ($draftRevisions > 0
                        ? $draftRevisions.' draft revision'.($draftRevisions === 1 ? '' : 's').' in progress'
                        : 'No controlled-form items need attention'),
                'items' => array_slice($attentionItems, 0, 5),
                'action' => [
                    'label' => 'Open Controlled Forms',
                    'href' => '/admin/controlled-forms',
                ],
            ],
            'queue' => [
                'title' => 'Controlled forms',
                'empty' => 'No controlled forms yet.',
                'columns' => ['form', 'revision', 'status', 'effective_date', 'updated_at'],
                'rows' => $formsTable,
            ],
            'activity' => [
                'title' => 'Recent document activity',
                'empty' => 'No audit events yet.',
                'items' => $activity,
                'action' => [
                    'label' => 'View Audit Logs',
                    'href' => '/admin/document-audit',
                ],
            ],
            'links' => [
                ['label' => 'Open Controlled Forms', 'href' => '/admin/controlled-forms'],
                ['label' => 'View Audit Logs', 'href' => '/admin/document-audit'],
                ['label' => 'Print History', 'href' => '/admin/print-history'],
            ],
            'extras' => [
                'workflow_strip' => $workflowStrip,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function receivingPayload(): array
    {
        $intakeQuery = JobOrder::query()->whereIn('status', [
            JobOrderStatus::DraftSubmitted,
            JobOrderStatus::PendingJoApproval,
            JobOrderStatus::Priced,
            JobOrderStatus::JoApproved,
        ]);
        $reviewedQuery = JobOrder::query()
            ->whereNotNull('reviewed_at')
            ->where('status', JobOrderStatus::ReadyForPickup);

        $draftCount = (clone $intakeQuery)
            ->where('status', JobOrderStatus::DraftSubmitted)
            ->count();
        $awaitingHeadCount = (clone $intakeQuery)
            ->whereIn('status', [
                JobOrderStatus::PendingJoApproval,
                JobOrderStatus::Priced,
            ])
            ->count();
        $readyCount = (clone $intakeQuery)
            ->where('status', JobOrderStatus::JoApproved)
            ->count();
        $reviewedCount = (clone $reviewedQuery)->count();

        $queueRows = (clone $intakeQuery)
            ->withCount(['analyses', 'samples'])
            ->oldest()
            ->limit(self::PREVIEW_LIMIT)
            ->get()
            ->map(function (JobOrder $order) {
                $actionLabel = match ($order->status) {
                    JobOrderStatus::DraftSubmitted => 'Price',
                    JobOrderStatus::PendingJoApproval, JobOrderStatus::Priced => 'Await Head',
                    JobOrderStatus::JoApproved => 'Print & send',
                    default => 'Open',
                };

                $statusLabel = match ($order->status) {
                    JobOrderStatus::DraftSubmitted => 'Needs pricing',
                    JobOrderStatus::PendingJoApproval, JobOrderStatus::Priced => 'Awaiting Head approval',
                    JobOrderStatus::JoApproved => 'Ready for analysts',
                    default => $order->status->label(),
                };

                return [
                    'id' => $order->id,
                    'reference_no' => $order->reference_no,
                    'customer_name' => $order->customer_name,
                    'classification' => $order->classification,
                    'samples_count' => $order->samples_count,
                    'status' => $order->status->value,
                    'status_label' => $statusLabel,
                    'updated_at' => $order->updated_at?->diffForHumans(),
                    'href' => '/receiving/'.$order->id,
                    'action_label' => $actionLabel,
                ];
            })
            ->values()
            ->all();

        $activity = JobOrder::query()
            ->where(function ($query) {
                $query->whereIn('status', [
                    JobOrderStatus::DraftSubmitted,
                    JobOrderStatus::PendingJoApproval,
                    JobOrderStatus::Priced,
                    JobOrderStatus::JoApproved,
                ])->orWhere(function ($inner) {
                    $inner->whereNotNull('reviewed_at')
                        ->where('reviewed_at', '>=', now()->subDays(3));
                });
            })
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (JobOrder $order) => [
                'title' => $order->reference_no,
                'meta' => $order->customer_name.' · '.$order->status->label(),
                'time' => $order->updated_at?->diffForHumans(),
                'href' => '/receiving/'.$order->id,
            ])
            ->values()
            ->all();

        $attentionJobs = (clone $intakeQuery)
            ->whereIn('status', [
                JobOrderStatus::DraftSubmitted,
                JobOrderStatus::PendingJoApproval,
                JobOrderStatus::Priced,
                JobOrderStatus::JoApproved,
            ])
            ->oldest()
            ->limit(5)
            ->get();

        $items = $attentionJobs->map(function (JobOrder $order) {
            $reason = match ($order->status) {
                JobOrderStatus::DraftSubmitted => 'Needs pricing',
                JobOrderStatus::PendingJoApproval, JobOrderStatus::Priced => 'Awaiting Head approval',
                JobOrderStatus::JoApproved => 'Ready to print & send',
                default => $order->status->label(),
            };

            return [
                'text' => $order->reference_no.' — '.$reason,
                'href' => '/receiving/'.$order->id,
            ];
        })->values()->all();

        $attentionTotal = $draftCount + $awaitingHeadCount + $readyCount;

        return [
            'role' => 'receiving',
            'header' => [
                'title' => 'Receiving Workspace',
                'subtitle' => 'Price → Head JO approval → print 3 JO copies → send to analysts.',
            ],
            'kpis' => [
                [
                    'key' => 'needs_pricing',
                    'label' => 'Needs pricing',
                    'value' => $draftCount,
                    'href' => '/receiving?status=draft_submitted',
                    'tone' => $draftCount > 0 ? 'warning' : 'default',
                    'hint' => 'Enter line prices',
                ],
                [
                    'key' => 'awaiting_head',
                    'label' => 'Awaiting Head approval',
                    'value' => $awaitingHeadCount,
                    'href' => '/receiving?status=pending_jo_approval',
                    'tone' => $awaitingHeadCount > 0 ? 'warning' : 'default',
                    'hint' => 'JO with Head',
                ],
                [
                    'key' => 'ready_for_analysts',
                    'label' => 'Ready for analysts',
                    'value' => $readyCount,
                    'href' => '/receiving?status=jo_approved',
                    'tone' => $readyCount > 0 ? 'success' : 'default',
                    'hint' => 'Print ×3 then send',
                ],
                [
                    'key' => 'reviewed',
                    'label' => 'Results released',
                    'value' => $reviewedCount,
                    'href' => '/receiving?status=reviewed',
                    'tone' => 'info',
                    'hint' => 'Reprint JO if needed',
                ],
            ],
            'needsAttention' => [
                'title' => $attentionTotal > 0
                    ? $attentionTotal.' Job Order'.($attentionTotal === 1 ? '' : 's').' need attention'
                    : 'Needs attention',
                'summary' => $attentionTotal > 0
                    ? 'Pricing, Head approval, or print & send still open.'
                    : 'No Receiving actions require attention.',
                'items' => $items,
                'action' => [
                    'label' => 'View queue',
                    'href' => '/receiving',
                ],
            ],
            'queue' => [
                'title' => 'Receiving work queue',
                'empty' => 'No Job Orders waiting in Receiving.',
                'columns' => ['reference_no', 'customer_name', 'classification', 'samples_count', 'status', 'updated_at'],
                'rows' => $queueRows,
            ],
            'activity' => [
                'title' => 'Recent receiving activity',
                'empty' => 'No recent receiving activity.',
                'items' => $activity,
                'action' => [
                    'label' => 'Open Receiving Workspace',
                    'href' => '/receiving',
                ],
            ],
            'links' => [
                ['label' => 'Open Receiving Workspace', 'href' => '/receiving'],
            ],
            'extras' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analystPayload(User $user): array
    {
        $isAdmin = $user->hasRole('admin');

        $openStatuses = [
            JobOrderAnalysisStatus::Assigned,
            JobOrderAnalysisStatus::Returned,
            JobOrderAnalysisStatus::InProgress,
            JobOrderAnalysisStatus::Pending,
        ];

        $openQuery = JobOrderAnalysis::query()
            ->whereIn('status', $openStatuses)
            ->whereHas('jobOrder', function ($query) {
                $query->where('status', JobOrderStatus::InAnalysis);
            });

        $completedQuery = JobOrderAnalysis::query()
            ->where('status', JobOrderAnalysisStatus::Completed);

        $qualifiedTypeIds = $isAdmin
            ? []
            : $user->analysisTypes()
                ->pluck('analysis_types.id')
                ->map(fn ($id) => (int) $id)
                ->all();

        if (! $isAdmin) {
            $openQuery->where(function ($query) use ($user, $qualifiedTypeIds) {
                $query->where('assigned_to', $user->id);
                if ($qualifiedTypeIds !== []) {
                    $query->orWhereIn('analysis_type_id', $qualifiedTypeIds);
                }
            });
            $completedQuery->where('assigned_to', $user->id);
        }

        $assignedCount = (clone $openQuery)
            ->whereIn('status', [
                JobOrderAnalysisStatus::Assigned,
                JobOrderAnalysisStatus::Pending,
            ])
            ->count();
        $returnedCount = (clone $openQuery)
            ->where('status', JobOrderAnalysisStatus::Returned)
            ->count();
        $needsAction = $assignedCount + $returnedCount;
        $inProgress = (clone $openQuery)
            ->where('status', JobOrderAnalysisStatus::InProgress)
            ->count();
        $completedToday = (clone $completedQuery)
            ->whereDate('updated_at', today())
            ->count();

        $myJobIds = (clone $openQuery)
            ->select('job_order_id')
            ->selectRaw('max(updated_at) as last_touch')
            ->groupBy('job_order_id')
            ->orderByDesc('last_touch')
            ->limit(5)
            ->pluck('job_order_id');

        $tasks = JobOrderAnalysis::query()
            ->with(['jobOrder.samples', 'assignee:id,name'])
            ->whereIn('job_order_id', $myJobIds)
            ->get();

        $groups = [];
        foreach ($myJobIds as $jobId) {
            $jobTasks = $tasks->where('job_order_id', $jobId)->values();
            if ($jobTasks->isEmpty()) {
                continue;
            }

            /** @var JobOrderAnalysis $first */
            $first = $jobTasks->first();
            $job = $first->jobOrder;

            $groups[] = [
                'job' => [
                    'id' => $job->id,
                    'reference_no' => $job->reference_no,
                    'customer_name' => $job->customer_name,
                    'company_name' => $job->company_name,
                    'classification' => $job->classification,
                    'sample_storage_temp' => $job->sample_storage_temp,
                    'review_notes' => $job->review_notes,
                    'samples' => $job->samples->map(fn ($sample) => [
                        'sample_code' => $sample->sample_code,
                        'description' => $sample->description,
                        'matrix' => $sample->matrix,
                    ])->values()->all(),
                ],
                'tasks' => $jobTasks->map(function (JobOrderAnalysis $task) use ($user, $isAdmin, $qualifiedTypeIds) {
                    $isMine = $isAdmin || (int) $task->assigned_to === (int) $user->id;
                    $canWork = $isAdmin
                        || $isMine
                        || (
                            $task->analysis_type_id
                            && in_array((int) $task->analysis_type_id, $qualifiedTypeIds, true)
                        );

                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'status' => $task->status->value,
                        'status_label' => $task->status->label(),
                        'assigned_to' => $task->assigned_to,
                        'assignee_name' => $task->assignee?->name,
                        'is_mine' => $isMine,
                        'can_work' => $canWork,
                        'category_label' => $task->resolvedCategoryLabel(),
                        'job_order' => [
                            'id' => $task->job_order_id,
                            'reference_no' => $task->jobOrder->reference_no,
                        ],
                    ];
                })->values()->all(),
            ];
        }

        $items = [];
        if ($returnedCount > 0) {
            $items[] = [
                'text' => $returnedCount.' result'.($returnedCount === 1 ? '' : 's').' returned for correction',
                'href' => '/analyst?status=returned',
            ];
        }
        if ($needsAction > 0) {
            $items[] = [
                'text' => $needsAction.' test'.($needsAction === 1 ? '' : 's').' need action',
                'href' => '/analyst?status=needs_action',
            ];
        }
        if ($inProgress > 0) {
            $items[] = [
                'text' => $inProgress.' test'.($inProgress === 1 ? '' : 's').' in progress',
                'href' => '/analyst?status=in_progress',
            ];
        }

        $activity = (clone $completedQuery)
            ->with('jobOrder:id,reference_no,customer_name')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (JobOrderAnalysis $task) => [
                'title' => $task->name,
                'meta' => ($task->jobOrder?->reference_no ?? 'JO').' · '.$task->jobOrder?->customer_name,
                'time' => $task->updated_at?->diffForHumans(),
                'href' => '/analyst?q='.urlencode((string) ($task->jobOrder?->reference_no ?? '')),
            ])
            ->values()
            ->all();

        return [
            'role' => 'analyst',
            'header' => [
                'title' => 'Analyst Workspace',
                'subtitle' => 'Laboratory results and assigned tests.',
            ],
            'kpis' => [
                [
                    'key' => 'needs_action',
                    'label' => 'Needs action',
                    'value' => $needsAction,
                    'href' => '/analyst?status=needs_action',
                    'tone' => $needsAction > 0 ? 'warning' : 'default',
                ],
                [
                    'key' => 'in_progress',
                    'label' => 'In progress',
                    'value' => $inProgress,
                    'href' => '/analyst?status=in_progress',
                    'tone' => 'info',
                ],
                [
                    'key' => 'returned',
                    'label' => 'Returned',
                    'value' => $returnedCount,
                    'href' => '/analyst?status=returned',
                    'tone' => $returnedCount > 0 ? 'warning' : 'default',
                ],
                [
                    'key' => 'completed_today',
                    'label' => 'Completed today',
                    'value' => $completedToday,
                    'href' => '/analyst?status=completed',
                    'tone' => 'success',
                ],
            ],
            'needsAttention' => [
                'title' => 'Needs attention',
                'summary' => $needsAction > 0
                    ? $needsAction.' test'.($needsAction === 1 ? '' : 's').' need action'
                    : 'No assigned tests need attention',
                'items' => $items,
                'action' => [
                    'label' => 'Open Analyst Workspace',
                    'href' => $returnedCount > 0 ? '/analyst?status=returned' : '/analyst',
                ],
            ],
            'queue' => [
                'title' => 'Assigned Job Orders',
                'empty' => 'No open Job Orders in your analyst queue.',
                'columns' => [],
                'rows' => [],
            ],
            'activity' => [
                'title' => 'Recently completed tests',
                'empty' => 'No completed tests yet.',
                'items' => $activity,
                'action' => [
                    'label' => 'Open Analyst Workspace',
                    'href' => '/analyst',
                ],
            ],
            'links' => [
                ['label' => 'Open Analyst Workspace', 'href' => '/analyst'],
            ],
            'extras' => [
                'job_groups' => $groups,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function headPayload(): array
    {
        $unsignedQuery = JobOrder::query()
            ->where('status', JobOrderStatus::PendingReview)
            ->whereNull('reviewed_at');

        $signedTodayQuery = JobOrder::query()
            ->where('status', JobOrderStatus::ReadyForPickup)
            ->whereNotNull('reviewed_at')
            ->whereDate('reviewed_at', today());

        $unsignedCount = (clone $unsignedQuery)->count();
        $signedToday = (clone $signedTodayQuery)->count();
        $returnedInLab = JobOrderAnalysis::query()
            ->where('status', JobOrderAnalysisStatus::Returned)
            ->whereHas('jobOrder', fn ($q) => $q->where('status', JobOrderStatus::InAnalysis))
            ->count();

        $queueRows = (clone $unsignedQuery)
            ->withCount(['analyses', 'samples'])
            ->withCount([
                'analyses as completed_analyses_count' => fn ($q) => $q->where(
                    'status',
                    JobOrderAnalysisStatus::Completed,
                ),
            ])
            ->latest('updated_at')
            ->limit(self::PREVIEW_LIMIT)
            ->get()
            ->map(function (JobOrder $order) {
                $total = (int) $order->analyses_count;
                $done = (int) $order->completed_analyses_count;

                return [
                    'id' => $order->id,
                    'reference_no' => $order->reference_no,
                    'customer_name' => $order->customer_name,
                    'tests' => $total > 0 ? "{$done} / {$total}" : '0 / 0',
                    'completion' => $total === 0 ? 0 : (int) round(($done / $total) * 100),
                    'status' => 'pending_review',
                    'status_label' => 'Ready for review',
                    'updated_at' => $order->updated_at?->diffForHumans(),
                    'href' => '/head/'.$order->id,
                    'action_label' => 'Review',
                ];
            })
            ->values()
            ->all();

        $activity = (clone $signedTodayQuery)
            ->with('reviewer:id,name')
            ->latest('reviewed_at')
            ->limit(8)
            ->get()
            ->map(fn (JobOrder $order) => [
                'title' => $order->reference_no,
                'meta' => $order->customer_name.' · Signed by '.($order->reviewer?->name ?? 'Head'),
                'time' => $order->reviewed_at?->diffForHumans(),
                'href' => '/head/'.$order->id,
            ])
            ->values()
            ->all();

        $items = [];
        if ($unsignedCount > 0) {
            $items[] = [
                'text' => $unsignedCount.' Job Order'.($unsignedCount === 1 ? '' : 's').' waiting for review',
                'href' => '/head/results?tab=unsigned',
            ];
        }
        if ($returnedInLab > 0) {
            $items[] = [
                'text' => $returnedInLab.' analysis line'.($returnedInLab === 1 ? '' : 's').' returned to analysts',
                'href' => '/head/results',
            ];
        }

        return [
            'role' => 'head',
            'header' => [
                'title' => 'Head Analysis',
                'subtitle' => 'Approve Job Orders and release finished results.',
            ],
            'kpis' => [
                [
                    'key' => 'waiting_review',
                    'label' => 'Waiting for review',
                    'value' => $unsignedCount,
                    'href' => '/head/results?tab=unsigned',
                    'tone' => $unsignedCount > 0 ? 'warning' : 'default',
                ],
                [
                    'key' => 'ready_to_sign',
                    'label' => 'Ready to release',
                    'value' => $unsignedCount,
                    'href' => '/head/results?tab=unsigned',
                    'tone' => $unsignedCount > 0 ? 'info' : 'default',
                ],
                [
                    'key' => 'returned_in_lab',
                    'label' => 'Returned in lab',
                    'value' => $returnedInLab,
                    'href' => '/head/results',
                    'tone' => $returnedInLab > 0 ? 'warning' : 'default',
                ],
                [
                    'key' => 'signed_today',
                    'label' => 'Released today',
                    'value' => $signedToday,
                    'href' => '/head/results?tab=signed',
                    'tone' => 'success',
                ],
            ],
            'needsAttention' => [
                'title' => 'Needs attention',
                'summary' => $unsignedCount > 0
                    ? $unsignedCount.' Job Order'.($unsignedCount === 1 ? '' : 's').' need review'
                    : 'Results queue is clear',
                'items' => $items,
                'action' => [
                    'label' => 'Open Results',
                    'href' => '/head/results',
                ],
            ],
            'queue' => [
                'title' => 'Review queue',
                'empty' => 'No Job Orders waiting for review.',
                'columns' => ['reference_no', 'customer_name', 'tests', 'completion', 'status', 'updated_at'],
                'rows' => $queueRows,
            ],
            'activity' => [
                'title' => 'Released today',
                'empty' => 'No releases yet today.',
                'items' => $activity,
                'action' => [
                    'label' => 'Open Results',
                    'href' => '/head/results?tab=signed',
                ],
            ],
            'links' => [
                ['label' => 'JO approval', 'href' => '/head/jo'],
                ['label' => 'Results', 'href' => '/head/results'],
                ['label' => 'History Archive', 'href' => '/history'],
            ],
            'extras' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function genericPayload(): array
    {
        return [
            'role' => 'generic',
            'header' => [
                'title' => 'Laboratory dashboard',
                'subtitle' => 'Your account does not have a laboratory workstation role assigned.',
            ],
            'kpis' => [],
            'needsAttention' => [
                'title' => 'Needs attention',
                'summary' => 'Contact an administrator to assign a role.',
                'items' => [],
                'action' => null,
            ],
            'queue' => [
                'title' => 'Queue',
                'empty' => 'Nothing to show.',
                'columns' => [],
                'rows' => [],
            ],
            'activity' => [
                'title' => 'Recent activity',
                'empty' => 'No activity.',
                'items' => [],
                'action' => null,
            ],
            'links' => [],
            'extras' => [],
        ];
    }
}
