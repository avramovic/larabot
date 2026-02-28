<?php

namespace App\Console\Commands;

use App\Enums\SettingType;
use App\Models\Memory;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskExecutionLog;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LarabotDashboardCommand extends BaseLarabotCommand
{
    private const SOLO_HINT = 'Use arrow keys to switch between tabs. Press "i" on interactive tabs to enter interactive mode. Press "s" to start/stop process in the selected tab.';

    protected $signature = 'larabot:dashboard {--solo}';

    protected $description = 'Larabot dashboard - a simple interface to monitor and manage Larabot processes';

    public function handle(): int
    {
        $this->mainMenu();

        return 0;
    }

    protected function mainMenu(): void
    {
        $options = [
            'Overview',
            'Tasks',
            'Task execution logs',
            'Memories',
            'Settings',
            'Messages',
            'Exit',
        ];

        while (true) {
            $this->clearScreen('Dashboard');
            if ($this->option('solo')) {
                $this->warn(self::SOLO_HINT);
                $this->newLine();
            }
            $selected = $this->choice('What do you want to check?', $options, 'Exit');

            match ($selected) {
                'Overview' => $this->overviewStats(),
                'Tasks' => $this->tasksList(),
                'Task execution logs' => $this->taskExecutionLogsList(),
                'Memories' => $this->memoriesList(),
                'Settings' => $this->settingsList(),
                'Messages' => $this->messagesList(),
                'Exit' => $this->exitDashboard(),
            };
        }
    }

    protected function exitDashboard(): void
    {
        $this->line('Exiting dashboard.');
        exit(0);
    }

    protected function overviewStats(): void
    {
        $this->clearScreen('Overview');

        $message_count = Message::count();
        $task_count = Task::count();
        $enabled_count = Task::query()->where('enabled', true)->count();
        $latest_message = Message::query()->latest('id')->first();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Messages', (string) $message_count],
                ['Tasks', (string) $task_count],
                ['Tasks (enabled)', (string) $enabled_count],
                ['Latest message', $latest_message ? "#{$latest_message->id} ({$latest_message->role}) at {$latest_message->created_at->toDateTimeString()}" : 'None'],
            ]
        );

        $this->newLine();
        $this->ask('Press Enter to go back');
    }

    protected function tasksList(): void
    {
        $timezone = config('app.timezone');

        while (true) {
            $this->clearScreen('Tasks');

            $tasks = Task::query()->orderBy('id')->get();
            $rows = $tasks->map(function (Task $task) use ($timezone) {
                $next_due = $task->enabled
                    ? Carbon::instance($task->cron()->getNextRunDate('now', 0, false, $timezone))->format('Y-m-d H:i')
                    : '–';

                return [
                    $task->id,
                    $task->title,
                    $task->enabled ? 'Yes' : 'No',
                    $task->schedule,
                    $next_due,
                    $task->destination,
                    $task->repeat === -1 ? '∞' : (string) $task->repeat,
                ];
            })->all();

            $this->table(
                ['ID', 'Title', 'Enabled', 'Schedule', 'Next due', 'Destination', 'Repeat'],
                $rows
            );

            $this->newLine();
            $options = ['View task', 'Edit task', 'Add task', 'Delete task', 'Back'];
            $action = $this->choice('Action', $options, 'Back');

            if ($action === 'Back') {
                return;
            }
            match ($action) {
                'View task' => $this->viewTask($tasks),
                'Edit task' => $this->editTask($tasks),
                'Add task' => $this->addTask(),
                'Delete task' => $this->deleteTask($tasks),
            };
        }
    }

    protected function viewTask($tasks): void
    {
        if ($tasks->isEmpty()) {
            $this->warn('No tasks to view.');
            return;
        }

        $choices = $tasks->map(fn (Task $t) => "#{$t->id} – {$t->title}")->values()->all();
        $selected = $this->choice('Select task to view', $choices);
        $id = (int) preg_replace('/^#(\d+).*/', '$1', $selected);
        $task = Task::find($id);
        if (! $task) {
            return;
        }

        $timezone = config('app.timezone');
        $next_due = $task->enabled
            ? Carbon::instance($task->cron()->getNextRunDate('now', 0, false, $timezone))->format('Y-m-d H:i')
            : '–';

        $this->clearScreen("Task #{$task->id}");
        $this->line("Title: {$task->title}");
        $this->line("Enabled: " . ($task->enabled ? 'Yes' : 'No'));
        $this->line("Schedule: {$task->schedule}");
        $this->line("Next due: {$next_due}");
        $this->line("Destination: {$task->destination}");
        $this->line("Repeat: " . ($task->repeat === -1 ? 'Forever' : (string) $task->repeat));
        $this->newLine();
        $this->line('Prompt:');
        $this->line($task->prompt);
        $this->newLine();
        $options = ['View execution logs for this task', 'Back'];
        $action = $this->choice('What next?', $options, 'Back');
        if ($action === 'View execution logs for this task') {
            $this->taskExecutionLogsForTask($task);
        }
    }

    protected function taskExecutionLogsForTask(Task $task): void
    {
        while (true) {
            $this->clearScreen("Task #{$task->id} – Execution logs");

            $logs = TaskExecutionLog::query()
                ->where('task_id', $task->id)
                ->orderByDesc('id')
                ->get();

            if ($logs->isEmpty()) {
                $this->warn('No execution logs for this task.');
                $this->newLine();
                $this->ask('Press Enter to go back');
                return;
            }

            $rows = $logs->map(fn (TaskExecutionLog $log) => [
                $log->id,
                $log->status,
                $log->created_at->toDateTimeString(),
            ])->all();

            $this->table(['ID', 'Status', 'Created at'], $rows);

            $this->newLine();
            $options = ['View log', 'Delete log', 'Back'];
            $action = $this->choice('Action', $options, 'Back');

            if ($action === 'Back') {
                return;
            }
            if ($action === 'View log') {
                $this->viewTaskExecutionLog($logs);
            } else {
                $this->deleteTaskExecutionLog($logs);
            }
        }
    }

    protected function editTask($tasks): void
    {
        if ($tasks->isEmpty()) {
            $this->warn('No tasks to edit.');
            return;
        }

        $choices = $tasks->map(fn (Task $t) => "#{$t->id} – {$t->title}")->values()->all();
        $selected = $this->choice('Select task to edit', $choices);
        $id = (int) preg_replace('/^#(\d+).*/', '$1', $selected);
        $task = Task::find($id);
        if (! $task) {
            return;
        }

        $title = $this->ask('Title', $task->title);
        $schedule = $this->ask('Schedule (cron)', $task->schedule);
        $prompt = $this->ask('Prompt', $task->prompt);
        $destination = $this->ask('Destination', $task->destination);
        $repeat = (int) $this->ask('Repeat (-1 = forever)', (string) $task->repeat);
        $enabled = $this->confirm('Enabled?', $task->enabled);

        if (! $this->validateCron($schedule)) {
            $this->warn('Invalid cron expression; task not updated.');
            return;
        }

        $task->update([
            'title' => $title,
            'schedule' => $schedule,
            'prompt' => $prompt,
            'destination' => $destination,
            'repeat' => $repeat,
            'enabled' => $enabled,
        ]);
        $this->info('Task updated.');
    }

    protected function addTask(): void
    {
        $title = $this->ask('Title');
        $schedule = $this->ask('Schedule (cron)');
        $prompt = $this->ask('Prompt');
        $destination = $this->ask('Destination', 'user');
        $repeat = (int) $this->ask('Repeat (-1 = forever)', '-1');
        $enabled = $this->confirm('Enabled?', true);

        if (! $this->validateCron($schedule)) {
            $this->warn('Invalid cron expression; task not created.');
            return;
        }

        Task::create([
            'title' => $title,
            'schedule' => $schedule,
            'prompt' => $prompt,
            'destination' => $destination,
            'repeat' => $repeat,
            'enabled' => $enabled,
        ]);
        $this->info('Task created.');
    }

    protected function deleteTask($tasks): void
    {
        if ($tasks->isEmpty()) {
            $this->warn('No tasks to delete.');
            return;
        }

        $choices = $tasks->map(fn (Task $t) => "#{$t->id} – {$t->title}")->values()->all();
        $selected = $this->choice('Select task to delete', $choices);
        $id = (int) preg_replace('/^#(\d+).*/', '$1', $selected);
        $task = Task::find($id);
        if (! $task) {
            return;
        }

        if (! $this->confirm("Delete task \"{$task->title}\"?", false)) {
            return;
        }
        $task->delete();
        $this->info('Task deleted.');
    }

    protected function validateCron(string $schedule): bool
    {
        try {
            new CronExpression($schedule);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function taskExecutionLogsList(): void
    {
        $per_page = 20;

        while (true) {
            $this->clearScreen('Task execution logs');

            $logs = TaskExecutionLog::query()
                ->with('task')
                ->orderByDesc('id')
                ->limit($per_page)
                ->get();

            $rows = $logs->map(fn (TaskExecutionLog $log) => [
                $log->id,
                $log->task_id,
                $log->task?->title ?? '–',
                $log->status,
                $log->created_at->toDateTimeString(),
            ])->all();

            $this->table(['ID', 'Task ID', 'Task title', 'Status', 'Created at'], $rows);

            $this->newLine();
            $options = ['View log', 'Delete log', 'Back'];
            $action = $this->choice('Action', $options, 'Back');

            if ($action === 'Back') {
                return;
            }
            match ($action) {
                'View log' => $this->viewTaskExecutionLog($logs),
                'Delete log' => $this->deleteTaskExecutionLog($logs),
            };
        }
    }

    protected function viewTaskExecutionLog($logs): void
    {
        if ($logs->isEmpty()) {
            $this->warn('No logs.');
            return;
        }

        $choices = $logs->map(fn (TaskExecutionLog $l) => "#{$l->id} – task {$l->task_id} @ {$l->created_at->toDateTimeString()}")->values()->all();
        $selected = $this->choice('Select log to view', $choices);
        $id = (int) preg_replace('/^#(\d+).*/', '$1', $selected);
        $log = TaskExecutionLog::find($id);
        if (! $log) {
            return;
        }

        $this->clearScreen("Log #{$log->id}");
        $this->line("Task ID: {$log->task_id}");
        $this->line("Status: {$log->status}");
        $this->line("Created at: {$log->created_at->toDateTimeString()}");
        $this->newLine();
        $this->line('Output:');
        $this->line($log->output_text ?? '(none)');
        $this->newLine();
        if ($log->tool_calls !== null) {
            $this->line('Tool calls: ' . json_encode($log->tool_calls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        $this->newLine();
        $this->ask('Press Enter to go back');
    }

    protected function deleteTaskExecutionLog($logs): void
    {
        if ($logs->isEmpty()) {
            $this->warn('No logs to delete.');
            return;
        }

        $choices = $logs->map(fn (TaskExecutionLog $l) => "#{$l->id} – task {$l->task_id} @ {$l->created_at->toDateTimeString()}")->values()->all();
        $selected = $this->choice('Select log to delete', $choices);
        $id = (int) preg_replace('/^#(\d+).*/', '$1', $selected);
        $log = TaskExecutionLog::find($id);
        if (! $log) {
            return;
        }

        if (! $this->confirm('Delete this log?', false)) {
            return;
        }
        $log->delete();
        $this->info('Log deleted.');
    }

    protected function memoriesList(): void
    {
        while (true) {
            $this->clearScreen('Memories');

            $memories = Memory::query()->orderBy('id')->get();
            $rows = $memories->map(fn (Memory $m) => [
                $m->id,
                $m->title,
                $m->important ? 'Yes' : 'No',
                $m->created_at->toDateTimeString(),
            ])->all();

            $this->table(['ID', 'Title', 'Important', 'Created at'], $rows);

            $this->newLine();
            $options = ['View memory', 'Add memory', 'Edit memory', 'Delete memory', 'Back'];
            $action = $this->choice('Action', $options, 'Back');

            if ($action === 'Back') {
                return;
            }
            match ($action) {
                'View memory' => $this->viewMemory($memories),
                'Add memory' => $this->addMemory(),
                'Edit memory' => $this->editMemory($memories),
                'Delete memory' => $this->deleteMemory($memories),
            };
        }
    }

    protected function viewMemory($memories): void
    {
        if ($memories->isEmpty()) {
            $this->warn('No memories to view.');
            return;
        }

        $choices = $memories->map(fn (Memory $m) => "#{$m->id} – {$m->title}")->values()->all();
        $selected = $this->choice('Select memory to view', $choices);
        $id = (int) preg_replace('/^#(\d+).*/', '$1', $selected);
        $memory = Memory::find($id);
        if (! $memory) {
            return;
        }

        $this->clearScreen("Memory #{$memory->id}");
        $this->line("Title: {$memory->title}");
        $this->line("Important: " . ($memory->important ? 'Yes' : 'No'));
        $this->line("Created at: {$memory->created_at->toDateTimeString()}");
        $this->newLine();
        $this->line('Contents:');
        $this->line($memory->contents);
        $this->newLine();
        $this->ask('Press Enter to go back');
    }

    protected function addMemory(): void
    {
        $title = $this->ask('Title');
        $contents = $this->ask('Contents');
        $important = $this->confirm('Important?', false);

        Memory::create([
            'title' => $title,
            'contents' => $contents,
            'important' => $important,
        ]);
        $this->info('Memory created.');
    }

    protected function editMemory($memories): void
    {
        if ($memories->isEmpty()) {
            $this->warn('No memories to edit.');
            return;
        }

        $choices = $memories->map(fn (Memory $m) => "#{$m->id} – {$m->title}")->values()->all();
        $selected = $this->choice('Select memory to edit', $choices);
        $id = (int) preg_replace('/^#(\d+).*/', '$1', $selected);
        $memory = Memory::find($id);
        if (! $memory) {
            return;
        }

        $title = $this->ask('Title', $memory->title);
        $contents = $this->ask('Contents', $memory->contents);
        $important = $this->confirm('Important?', $memory->important);

        $memory->update(['title' => $title, 'contents' => $contents, 'important' => $important]);
        $this->info('Memory updated.');
    }

    protected function deleteMemory($memories): void
    {
        if ($memories->isEmpty()) {
            $this->warn('No memories to delete.');
            return;
        }

        $choices = $memories->map(fn (Memory $m) => "#{$m->id} – {$m->title}")->values()->all();
        $selected = $this->choice('Select memory to delete', $choices);
        $id = (int) preg_replace('/^#(\d+).*/', '$1', $selected);
        $memory = Memory::find($id);
        if (! $memory) {
            return;
        }

        if (! $this->confirm("Delete memory \"{$memory->title}\"?", false)) {
            return;
        }
        $memory->delete();
        $this->info('Memory deleted.');
    }

    protected function settingsList(): void
    {
        while (true) {
            $this->clearScreen('Settings');

            $settings = Setting::query()->orderBy('key')->get();
            $rows = $settings->map(fn (Setting $s) => [
                $s->key,
                Str::limit($s->value, 50),
                $s->type,
            ])->all();

            $this->table(['Key', 'Value', 'Type'], $rows);

            $this->newLine();
            $options = ['Add setting', 'Edit setting', 'Delete setting', 'Back'];
            $action = $this->choice('Action', $options, 'Back');

            if ($action === 'Back') {
                return;
            }
            match ($action) {
                'Add setting' => $this->addSetting(),
                'Edit setting' => $this->editSetting($settings),
                'Delete setting' => $this->deleteSetting($settings),
            };
        }
    }

    protected function addSetting(): void
    {
        $key = $this->ask('Key');
        $value = $this->ask('Value');
        $type_choice = $this->choice('Type', ['string', 'int', 'bool', 'float', 'array'], 'string');
        $type = match ($type_choice) {
            'int' => SettingType::TYPE_INTEGER->value,
            'bool' => SettingType::TYPE_BOOLEAN->value,
            'float' => SettingType::TYPE_FLOAT->value,
            'array' => SettingType::TYPE_ARRAY->value,
            default => SettingType::TYPE_STRING->value,
        };

        $normalized = $this->normalizeSettingValue($value, $type);
        Setting::set($key, $normalized, $type);
        $this->info('Setting created.');
    }

    protected function editSetting($settings): void
    {
        if ($settings->isEmpty()) {
            $this->warn('No settings to edit.');
            return;
        }

        $choices = $settings->map(fn (Setting $s) => $s->key)->values()->all();
        $key = $this->choice('Select setting to edit', $choices);
        $setting = Setting::where('key', $key)->first();
        if (! $setting) {
            return;
        }

        $value = $this->ask('Value', $setting->value);
        $normalized = $this->normalizeSettingValue($value, $setting->type);
        $setting->update(['value' => is_array($normalized) ? json_encode($normalized) : (string) $normalized]);
        $this->info('Setting updated.');
    }

    protected function deleteSetting($settings): void
    {
        if ($settings->isEmpty()) {
            $this->warn('No settings to delete.');
            return;
        }

        $choices = $settings->map(fn (Setting $s) => $s->key)->values()->all();
        $key = $this->choice('Select setting to delete', $choices);
        $setting = Setting::where('key', $key)->first();
        if (! $setting) {
            return;
        }

        if (! $this->confirm("Delete setting \"{$key}\"?", false)) {
            return;
        }
        $setting->delete();
        $this->info('Setting deleted.');
    }

    protected function normalizeSettingValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            SettingType::TYPE_INTEGER->value => (int) $value,
            SettingType::TYPE_FLOAT->value => (float) $value,
            SettingType::TYPE_BOOLEAN->value => in_array(strtolower((string) $value), ['1', 'true', 'yes'], true),
            SettingType::TYPE_ARRAY->value => is_string($value) ? json_decode($value, true) ?? [] : $value,
            default => (string) $value,
        };
    }

    protected function messagesList(): void
    {
        $per_page = 10;
        $page = 1;

        while (true) {
            $this->clearScreen('Messages');

            $query = Message::query()->orderByDesc('id');
            $total = $query->count();
            $messages = $query->offset(($page - 1) * $per_page)->limit($per_page)->get();

            $rows = $messages->map(fn (Message $m) => [
                $m->id,
                $m->role,
                Str::limit($m->contents, 50),
                $m->created_at->toDateTimeString(),
            ])->all();

            $this->table(['ID', 'Role', 'Contents (preview)', 'Created at'], $rows);
            $this->line("Page {$page} of " . max(1, (int) ceil($total / $per_page)) . " ({$total} total)");

            $this->newLine();
            $options = ['View message', 'Next page', 'Previous page', 'Back'];
            $action = $this->choice('Action', $options, 'Back');

            if ($action === 'Back') {
                return;
            }
            match ($action) {
                'View message' => $this->viewMessage($messages),
                'Next page' => $page = min($page + 1, (int) max(1, ceil($total / $per_page))),
                'Previous page' => $page = max(1, $page - 1),
            };
        }
    }

    protected function viewMessage($messages): void
    {
        if ($messages->isEmpty()) {
            $this->warn('No messages on this page.');
            return;
        }

        $choices = $messages->map(fn (Message $m) => "#{$m->id} – {$m->role} @ {$m->created_at->toDateTimeString()}")->values()->all();
        $selected = $this->choice('Select message to view', $choices);
        $id = (int) preg_replace('/^#(\d+).*/', '$1', $selected);
        $message = Message::find($id);
        if (! $message) {
            return;
        }

        $this->clearScreen("Message #{$message->id}");
        $this->line("Role: {$message->role}");
        $this->line("Created at: {$message->created_at->toDateTimeString()}");
        $this->newLine();
        $this->line($message->contents);
        $this->newLine();
        $this->ask('Press Enter to go back');
    }
}
