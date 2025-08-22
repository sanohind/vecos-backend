<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VehicleBooking;
use Carbon\Carbon;

class AutoCompleteBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:auto-complete 
                            {--dry-run : Show what would be updated without actually updating}
                            {--hours= : Hours past end_time to consider as completed (default: 0)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically mark bookings as completed when they pass their end time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $hoursBuffer = (int) $this->option('hours', 0);
        
        $this->info('Starting auto-complete bookings process...');
        
        // Calculate the cutoff time (current time minus buffer hours)
        $cutoffTime = Carbon::now()->subHours($hoursBuffer);
        
        $this->info("Looking for approved bookings that ended before: {$cutoffTime->format('Y-m-d H:i:s')}");
        
        // Find approved bookings that have passed their end time
        /** @var \Illuminate\Database\Eloquent\Collection $expiredBookings */
        $expiredBookings = VehicleBooking::query()
            ->with(['vehicle', 'user'])
            ->where('status', 'approved')
            ->where('end_time', '<', $cutoffTime)
            ->get();
        
        if ($expiredBookings->isEmpty()) {
            $this->info('No expired bookings found.');
            return 0;
        }
        
        $this->info("Found {$expiredBookings->count()} expired booking(s):");
        
        // Display the bookings that will be updated
        $this->table(
            ['ID', 'Vehicle', 'User', 'Start Time', 'End Time', 'Destination'],
            $expiredBookings->map(function ($booking) {
                return [
                    $booking->id,
                    $booking->vehicle->brand . ' ' . $booking->vehicle->model . ' (' . $booking->vehicle->plat_no . ')',
                    $booking->user->name,
                    $booking->start_time->format('Y-m-d H:i'),
                    $booking->end_time->format('Y-m-d H:i'),
                    $booking->destination,
                ];
            })->toArray()
        );
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE: No changes were made to the database.');
            return 0;
        }
        
        // Ask for confirmation in interactive mode
        if ($this->input->isInteractive()) {
            if (!$this->confirm('Do you want to mark these bookings as completed?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }
        
        // Update the bookings
        $updatedCount = 0;
        $failedUpdates = [];
        
        foreach ($expiredBookings as $booking) {
            try {
                $booking->update(['status' => 'completed']);
                $updatedCount++;
                
                $this->line("✓ Updated booking #{$booking->id} - {$booking->user->name}");
                
            } catch (\Exception $e) {
                $failedUpdates[] = [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage()
                ];
                
                $this->error("✗ Failed to update booking #{$booking->id}: {$e->getMessage()}");
            }
        }
        
        // Summary
        $this->info("\n" . str_repeat('=', 50));
        $this->info("Auto-complete process completed!");
        $this->info("Successfully updated: {$updatedCount} booking(s)");
        
        if (!empty($failedUpdates)) {
            $this->error("Failed updates: " . count($failedUpdates) . " booking(s)");
            foreach ($failedUpdates as $failed) {
                $this->error("  - Booking #{$failed['booking_id']}: {$failed['error']}");
            }
        }
        
        return 0;
    }
}