<?php

namespace App\Console\Commands;

use App\Models\TagoreTask;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
                    'scheduled_for' => $template->next_run_at,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('tagore_task_templates')->where('id', $template->id)->update([
                'last_generated_at' => now(),
                'next_run_at' => $this->nextRun($template),
                'updated_at' => now(),
            ]);

            $this->info("Generated task #{$task->id} from template #{$template->id}.");
        }

        return self::SUCCESS;
    }

    private function nextRun(object $template): ?Carbon
    {
        $base = Carbon::parse($template->next_run_at);

        return match ($template->frequency) {
            'daily' => $base->addDay(),
            'weekly' => $base->addWeek(),
            'monthly' => $base->addMonthNoOverflow(),
            default => null,
        };
    }
}
