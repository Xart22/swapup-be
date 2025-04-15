<?php

namespace App\Console\Commands;
use App\Models\Items;
use App\Models\User;
use App\Jobs\ProcessCallItems;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use Throwable;
use Illuminate\Support\Facades\Log;

class Reset_GetItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset_-get-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset & Get Items Consign Cloud';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Start Batch Processing ......");

        $userData = User::where(function ($query) {
                        $query->where('role', 'user')
                            ->whereNotNull('consign_id');
                    })
                    ->orWhere(function ($query) {
                        $query->where('role', 'admin')
                            ->where('email', 'alifa@swapup.com.au');
                    })
                    ->get();

        if ($userData->isEmpty()) {
            $this->info("No users found for processing.");
            return;
        }

        $chunkSize = 1000;
        $delaySeconds = 10;

        $userData->chunk($chunkSize)->each(function ($userChunk, $batchIndex) use ($delaySeconds) {
            $batchJobs = $userChunk->map(function ($user) use ($batchIndex, $delaySeconds) {
                return (new ProcessCallItems($user->consign_id))
                            ->delay(now()->addSeconds($batchIndex * $delaySeconds));
            })->toArray();

            Bus::batch($batchJobs)
                ->then(function (Batch $batch) {
                    Log::info('Batch ' . $batch->id . ' completed.');
                })
                ->catch(function (Batch $batch, Throwable $e) {
                    Log::error('Batch ' . $batch->id . ' failed: ' . $e->getMessage());
                })
                ->finally(function (Batch $batch) {
                    Log::info('Batch ' . $batch->id . ' finished.');
                })
                ->dispatch();
        });

        $this->info("All Batches Queued Successfully.");
    }
}
