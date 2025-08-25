<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VehicleBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BookingWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:worker 
                            {--interval=900 : Interval between checks in seconds (default: 15 minutes)}
                            {--hours-buffer=0 : Hours past end_time to consider as completed}
                            {--max-runtime=0 : Maximum runtime in seconds (0 = unlimited)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Long-running worker that automatically completes expired bookings';

    /**
     * Worker start time for runtime tracking
     */
    protected Carbon $startTime;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->startTime = Carbon::now();
        $interval = (int) $this->option('interval');
        $hoursBuffer = (int) $this->option('hours-buffer');
        $maxRuntime = (int) $this->option('max-runtime');
        
        $this->info("🚀 Booking Worker Started at {$this->startTime->format('Y-m-d H:i:s')}");
        $this->info("📅 Check interval: {$interval} seconds");
        $this->info("⏱️  Hours buffer: {$hoursBuffer} hours");
        
        if ($maxRuntime > 0) {
            $this->info("⏳ Max runtime: {$maxRuntime} seconds");
        } else {
            $this->info("⏳ Max runtime: unlimited");
        }
        
        Log::info('Booking Worker started', [
            'interval' => $interval,
            'hours_buffer' => $hoursBuffer,
            'max_runtime' => $maxRuntime,
            'started_at' => $this->startTime,
        ]);

        $this->line(str_repeat('=', 60));

        while (true) {
            try {
                // Check if we should stop due to max runtime
                if ($maxRuntime > 0 && $this->startTime->diffInSeconds(Carbon::now()) >= $maxRuntime) {
                    $this->info('⏰ Max runtime reached, stopping worker...');
                    Log::info('Booking Worker stopped due to max runtime');
                    break;
                }

                $this->processExpiredBookings($hoursBuffer);
                
                // Memory management
                if (memory_get_usage(true) > 128 * 1024 * 1024) { // 128MB
                    $this->warn('⚠️  High memory usage detected, consider restarting worker');
                    Log::warning('High memory usage in booking worker', [
                        'memory_usage' => memory_get_usage(true),
                        'memory_peak' => memory_get_peak_usage(true)
                    ]);
                }

                $this->info("😴 Sleeping for {$interval} seconds...\n");
                sleep($interval);

            } catch (\Exception $e) {
                $this->error("❌ Worker error: {$e->getMessage()}");
                Log::error('Booking Worker error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Sleep a bit before retrying to prevent rapid error loops
                $this->info("⏳ Waiting 30 seconds before retry...");
                sleep(30);
            }
        }

        return 0;
    }

    /**
     * Process expired bookings
     */
    protected function processExpiredBookings(int $hoursBuffer): void
    {
        $currentTime = Carbon::now();
        $cutoffTime = $currentTime->copy()->subHours($hoursBuffer);
        
        $this->info("🔍 Checking for expired bookings at {$currentTime->format('H:i:s')}");
        
        // Find approved bookings that have passed their end time
        $expiredBookings = VehicleBooking::query()
            ->with(['vehicle', 'user'])
            ->where('status', 'approved')
            ->where('end_time', '<', $cutoffTime)
            ->get();
        
        if ($expiredBookings->isEmpty()) {
            $this->line("✅ No expired bookings found");
            return;
        }
        
        $this->warn("📋 Found {$expiredBookings->count()} expired booking(s) to complete");
        
        $updatedCount = 0;
        $failedCount = 0;
        
        foreach ($expiredBookings as $booking) {
            try {
                $booking->update(['status' => 'completed']);
                $updatedCount++;
                
                $vehicleName = $booking->vehicle->brand . ' ' . $booking->vehicle->model;
                $this->line("  ✓ Completed booking #{$booking->id} - {$booking->user->name} ({$vehicleName})");
                
                Log::info('Booking auto-completed', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'vehicle_id' => $booking->vehicle_id,
                    'end_time' => $booking->end_time,
                    'completed_at' => Carbon::now(),
                ]);
                
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("  ✗ Failed to complete booking #{$booking->id}: {$e->getMessage()}");
                
                Log::error('Failed to auto-complete booking', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Summary
        $this->info("📊 Completed: {$updatedCount} | Failed: {$failedCount}");
        
        if ($updatedCount > 0) {
            Log::info('Booking auto-completion summary', [
                'completed_count' => $updatedCount,
                'failed_count' => $failedCount,
                'processed_at' => $currentTime,
            ]);
        }
    }
}