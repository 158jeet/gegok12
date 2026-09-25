<?php

namespace App\Console\Commands;

use App\Models\TagoreTask;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateTagoreRecurringTasks extends Command
{
    protected $signature = 'tagore:generate-recurring-tasks';
    protected $description = 'Generate due Tagore tasks from active recurring templates';

    public function handle(): int
    {
        $templates = DB::table('tagore_task_templates')
            ->where('active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->limit(200)
            ->get();

        foreach ($templates as $template) {
            $scheduledFor = Carbon::parse($template->next_run_at);

            try {
                $runId = DB::table('tagore_task_template_runs')->insertGetId([
                    'template_id' => $template->id,
                    'institution_id' => $template->institution_id,
                    'scheduled_for' => $scheduledFor,
                    'started_at' => now(),
                    'status' => 'running',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (Throwable $e) {
                // Unique(template, scheduled_for) makes manual/concurrent invocations idempotent.
                continue;
            }

            try {
                [$taskId, $nextRun] = DB::transaction(function () use ($template, $scheduledFor) {
                    $dueAt = $template->due_after_minutes !== null
                        ? now()->addMinutes((int) $template->due_after_minutes)
                        : null;

                    $task = TagoreTask::create([
                        'institution_id' => $template->institution_id,
                        'department_id' => $template->department_id,
                        'created_by' => $template->created_by,
                        'assigned_to' => $template->assigned_to,
                        'title' => $template->title,
                        'description' => $template->description,
                        'priority' => $template->priority,
                        'status' => 'open',
                        'progress' => 0,
                        'due_at' => $dueAt,
                    ]);

                    DB::table('tagore_task_events')->insert([
                        'task_id' => $task->id,
                        'institution_id' => $task->institution_id,
                        'actor_id' => $template->created_by,
                        'event_type' => 'created_from_template',
                        'metadata' => json_encode([
                            'template_id' => $template->id,
                            'scheduled_for' => $scheduledFor->toDateTimeString(),
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $nextRun = $this->nextRunAfter($template, $scheduledFor);

                    DB::table('tagore_task_templates')->where('id', $template->id)->update([
                        'last_generated_at' => now(),
                        'next_run_at' => $nextRun,
                        'updated_at' => now(),
                    ]);

                    return [$task->id, $nextRun];
                });

                DB::table('tagore_task_template_runs')->where('id', $runId)->update([
                    'task_id' => $taskId,
                    'completed_at' => now(),
                    'status' => 'success',
                    'updated_at' => now(),
                ]);

                $this->info("Generated task #{$taskId} from template #{$template->id}.");
            } catch (Throwable $e) {
                DB::table('tagore_task_template_runs')->where('id', $runId)->update([
                    'completed_at' => now(),
                    'status' => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);

                report($e);
                $this->error("Template #{$template->id} failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    private function nextRunAfter(object $template, Carbon $scheduledFor): ?Carbon
    {
        $next = match ($template->frequency) {
            'daily' => $scheduledFor->copy()->addDay(),
            'weekly' => $scheduledFor->copy()->addWeek(),
            'monthly' => $scheduledFor->copy()->addMonthNoOverflow(),
            default => null,
        };

        while ($next !== null && $next->lte(now())) {
            $next = match ($template->frequency) {
                'daily' => $next->copy()->addDay(),
                'weekly' => $next->copy()->addWeek(),
                'monthly' => $next->copy()->addMonthNoOverflow(),
                default => null,
            };
        }

        return $next;
    }
}
