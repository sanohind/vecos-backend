<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FixTimestamps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timestamps:fix {--table= : Specific table to fix} {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix timestamps in database tables to use correct timezone';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Starting timestamp fix process...');
        
        // Get timezone from config
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $this->info("📍 Using timezone: {$timezone}");
        
        // Get current UTC time
        $utcNow = Carbon::now('UTC');
        $localNow = Carbon::now($timezone);
        
        $this->info("🕐 Current UTC time: {$utcNow->toDateTimeString()}");
        $this->info("🕐 Current {$timezone} time: {$localNow->toDateTimeString()}");
        
        // Get all tables with timestamps
        $tables = $this->getTablesWithTimestamps();
        
        if (empty($tables)) {
            $this->warn('⚠️  No tables with timestamps found.');
            return 0;
        }
        
        $this->info("📋 Found " . count($tables) . " tables with timestamps:");
        foreach ($tables as $table) {
            $this->line("   - {$table}");
        }
        
        // If specific table is specified, filter to that table only
        if ($specificTable = $this->option('table')) {
            if (!in_array($specificTable, $tables)) {
                $this->error("❌ Table '{$specificTable}' not found or doesn't have timestamps.");
                return 1;
            }
            $tables = [$specificTable];
            $this->info("🎯 Focusing on table: {$specificTable}");
        }
        
        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made to database');
        }
        
        $this->newLine();
        
        $totalFixed = 0;
        
        foreach ($tables as $table) {
            $this->info("🔧 Processing table: {$table}");
            
            $fixed = $this->fixTableTimestamps($table, $timezone, $isDryRun);
            $totalFixed += $fixed;
            
            $this->info("✅ Table {$table}: {$fixed} records processed");
            $this->newLine();
        }
        
        if ($isDryRun) {
            $this->warn("🔍 DRY RUN COMPLETE - {$totalFixed} records would have been processed");
        } else {
            $this->info("🎉 Timestamp fix completed! {$totalFixed} records processed.");
        }
        
        return 0;
    }
    
    /**
     * Get all tables that have timestamp columns
     */
    private function getTablesWithTimestamps(): array
    {
        $tables = [];
        
        // Get all tables
        $allTables = DB::select('SHOW TABLES');
        $tableColumn = 'Tables_in_' . env('DB_DATABASE', 'laravel');
        
        foreach ($allTables as $tableObj) {
            $tableName = $tableObj->$tableColumn;
            
            // Check if table has timestamp columns
            $columns = DB::select("SHOW COLUMNS FROM {$tableName}");
            $hasTimestamps = false;
            
            foreach ($columns as $column) {
                if (in_array($column->Field, ['created_at', 'updated_at'])) {
                    $hasTimestamps = true;
                    break;
                }
            }
            
            if ($hasTimestamps) {
                $tables[] = $tableName;
            }
        }
        
        return $tables;
    }
    
    /**
     * Get timestamp columns for a specific table
     */
    private function getTimestampColumns(string $table): array
    {
        $columns = DB::select("SHOW COLUMNS FROM {$table}");
        $timestampColumns = [];
        
        foreach ($columns as $column) {
            if (in_array($column->Field, ['created_at', 'updated_at'])) {
                $timestampColumns[] = $column->Field;
            }
        }
        
        return $timestampColumns;
    }
    
    /**
     * Fix timestamps for a specific table
     */
    private function fixTableTimestamps(string $table, string $timezone, bool $isDryRun): int
    {
        $fixed = 0;
        
        // Get timestamp columns for this table
        $timestampColumns = $this->getTimestampColumns($table);
        
        if (empty($timestampColumns)) {
            $this->warn("   ⚠️  No timestamp columns found in table {$table}");
            return 0;
        }
        
        // Build query based on available timestamp columns
        $query = DB::table($table);
        
        if (!empty($timestampColumns)) {
            $query->where(function($q) use ($timestampColumns) {
                foreach ($timestampColumns as $column) {
                    $q->orWhereNotNull($column);
                }
            });
        }
        
        $records = $query->get();
        
        foreach ($records as $record) {
            $updates = [];
            
            // Check each timestamp column
            foreach ($timestampColumns as $column) {
                if (isset($record->$column) && $record->$column) {
                    $timestamp = Carbon::parse($record->$column);
                    
                    // Check if timestamp needs timezone conversion
                    if ($timestamp->timezone->getName() !== $timezone) {
                        $newTimestamp = $timestamp->setTimezone($timezone);
                        $updates[$column] = $newTimestamp;
                        
                        if (!$isDryRun) {
                            $this->line("   📝 {$column}: {$timestamp->toDateTimeString()} → {$newTimestamp->toDateTimeString()}");
                        }
                    }
                }
            }
            
            // Update record if there are changes
            if (!empty($updates) && !$isDryRun) {
                DB::table($table)
                    ->where('id', $record->id)
                    ->update($updates);
            }
            
            if (!empty($updates)) {
                $fixed++;
            }
        }
        
        return $fixed;
    }
}
