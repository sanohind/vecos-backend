# 🚀 Server Deployment Guide - Laravel Vehicle Booking Auto-Complete Worker

## 📋 Overview

This guide explains how to deploy the Laravel Vehicle Booking system with Supervisor-managed auto-complete worker to a Ubuntu/Linux server using PuTTY.

## 🎯 Features

- **Automatic booking completion** for expired vehicle bookings
- **24/7 background worker** managed by Supervisor
- **Real-time monitoring** and logging
- **Auto-restart** on failures
- **Production-ready** configuration

## 📚 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Server Preparation](#server-preparation)
3. [Laravel Project Deployment](#laravel-project-deployment)
4. [Supervisor Installation & Configuration](#supervisor-installation--configuration)
5. [Worker Deployment](#worker-deployment)
6. [Monitoring & Management](#monitoring--management)
7. [Troubleshooting](#troubleshooting)
8. [Maintenance](#maintenance)

## 🔧 Prerequisites

### Server Requirements:
- Ubuntu 18.04+ or similar Linux distribution
- PHP 8.1+ with required extensions
- MySQL/PostgreSQL database
- Composer installed
- Web server (Apache/Nginx) configured
- SSH access with sudo privileges

### Local Development:
- Laravel project with `BookingWorker` command implemented
- Database with `vehicle_bookings` table
- All dependencies installed and tested

## 🖥️ Server Preparation

### 1. Connect to Server via PuTTY

```bash
# Connect using your server credentials
ssh username@your-server-ip
```

### 2. Update System

```bash
# Update package lists
sudo apt-get update

# Upgrade installed packages
sudo apt-get upgrade -y

# Install essential packages
sudo apt-get install curl wget unzip git -y
```

### 3. Verify PHP & Extensions

```bash
# Check PHP version
php -v

# Check required extensions
php -m | grep -E "(pdo|mysqli|mbstring|openssl|tokenizer|xml|ctype|json)"

# Install missing extensions if needed
sudo apt-get install php-mysql php-mbstring php-xml php-curl php-zip php-gd -y
```

## 📁 Laravel Project Deployment

### 1. Upload Project Files

**Option A: Using SCP/WinSCP**
- Upload your Laravel project to `/var/www/your-project-name`
- Or use your hosting provider's file manager

**Option B: Using Git**
```bash
# Navigate to web directory
cd /var/www

# Clone repository
sudo git clone https://github.com/your-username/your-repo.git your-project-name

# Change ownership
sudo chown -R www-data:www-data your-project-name
```

### 2. Configure Laravel

```bash
# Navigate to project directory
cd /var/www/your-project-name

# Install dependencies
composer install --optimize-autoloader --no-dev

# Copy environment file
cp .env.example .env

# Edit environment file
sudo nano .env
```

**Update `.env` file with production settings:**
```env
APP_NAME="Vehicle Booking System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password

# Add other required configurations
```

### 3. Set Permissions

```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/your-project-name

# Set directory permissions
sudo find /var/www/your-project-name -type d -exec chmod 755 {} \;
sudo find /var/www/your-project-name -type f -exec chmod 644 {} \;

# Set storage and bootstrap/cache permissions
sudo chmod -R 775 /var/www/your-project-name/storage
sudo chmod -R 775 /var/www/your-project-name/bootstrap/cache

# Generate application key
php artisan key:generate

# Run migrations (if needed)
php artisan migrate --force

# Cache configuration for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Test Laravel Installation

```bash
# Test basic Laravel functionality
php artisan --version

# Test database connection
php artisan tinker
# In tinker: DB::connection()->getPdo()
# Should not throw errors

# Test the booking worker command
php artisan bookings:worker --interval=60 --max-runtime=120
```

## 🔄 Supervisor Installation & Configuration

### 1. Install Supervisor

```bash
# Install Supervisor package
sudo apt-get install supervisor -y

# Check installation
sudo supervisorctl version

# Start and enable Supervisor
sudo systemctl start supervisor
sudo systemctl enable supervisor

# Check status
sudo systemctl status supervisor
```

### 2. Create Worker Configuration

```bash
# Create configuration file
sudo nano /etc/supervisor/conf.d/booking-worker.conf
```

**Copy and paste this configuration (⚠️ UPDATE PATHS):**

```ini
[program:booking-worker]
; IMPORTANT: Update these paths to match your server setup
command=php /var/www/your-project-name/artisan bookings:worker --interval=900 --hours-buffer=0
directory=/var/www/your-project-name
user=www-data

; Basic process settings
autostart=true
autorestart=true
startsecs=3
startretries=3

; Process management
numprocs=1
process_name=booking-worker-%(process_num)02d

; Logging configuration
stdout_logfile=/var/log/supervisor/booking-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stderr_logfile=/var/log/supervisor/booking-worker-error.log
stderr_logfile_maxbytes=10MB
stderr_logfile_backups=5
redirect_stderr=false

; Environment variables
environment=LARAVEL_ENV="production"

; Graceful shutdown configuration
stopsignal=QUIT
stopwaitsecs=30
priority=999

[group:laravel-workers]
programs=booking-worker
```

**⚠️ CRITICAL: Update these paths:**
- Replace `/var/www/your-project-name` with your actual Laravel project path
- Update `user=www-data` if using different user
- Adjust environment variables as needed

### 3. Create Log Directories

```bash
# Create log directories if they don't exist
sudo mkdir -p /var/log/supervisor

# Set proper permissions
sudo chown -R www-data:www-data /var/log/supervisor
sudo chmod -R 755 /var/log/supervisor
```

## 🚀 Worker Deployment

### 1. Load New Configuration

```bash
# Read new configuration files
sudo supervisorctl reread

# Update supervisor with new configuration
sudo supervisorctl update

# Verify configuration loaded
sudo supervisorctl avail
```

### 2. Start the Worker

```bash
# Start the booking worker
sudo supervisorctl start booking-worker:*

# Check status (should show RUNNING)
sudo supervisorctl status

# Expected output:
# booking-worker:booking-worker-00   RUNNING   pid 12345, uptime 0:00:05
```

### 3. Verify Worker Operation

```bash
# Monitor real-time output
sudo supervisorctl tail -f booking-worker:booking-worker-00

# Expected output:
# 🚀 Booking Worker Started at 2025-08-25 10:30:00
# 📅 Check interval: 900 seconds
# ⏱️  Hours buffer: 0 hours
# ⏳ Max runtime: unlimited
# ============================================================
# 🔍 Checking for expired bookings at 10:30:02
# ✅ No expired bookings found
# 😴 Sleeping for 900 seconds...
```

## 📊 Monitoring & Management

### Essential Commands

```bash
# Check all supervisor processes
sudo supervisorctl status

# Start worker
sudo supervisorctl start booking-worker:*

# Stop worker
sudo supervisorctl stop booking-worker:*

# Restart worker (for code updates)
sudo supervisorctl restart booking-worker:*

# Stop all processes
sudo supervisorctl stop all

# Start all processes  
sudo supervisorctl start all
```

### Log Monitoring

```bash
# Real-time worker output
sudo supervisorctl tail -f booking-worker:booking-worker-00

# View log file directly
sudo tail -f /var/log/supervisor/booking-worker.log

# View error log
sudo tail -f /var/log/supervisor/booking-worker-error.log

# View Laravel application logs
sudo tail -f /var/www/your-project-name/storage/logs/laravel.log

# Search for specific booking completions
sudo grep "Booking auto-completed" /var/log/supervisor/booking-worker.log
```

### Health Checks

```bash
# Check supervisor daemon status
sudo systemctl status supervisor

# Check if worker process is actually running
ps aux | grep "bookings:worker"

# Check system resource usage
top -p $(pgrep -f "bookings:worker")

# Check database connectivity from worker
cd /var/www/your-project-name
php artisan tinker
# Test: VehicleBooking::count()
```

## 🔧 Troubleshooting

### Common Issues & Solutions

#### 1. Worker Not Starting

**Symptoms:** Status shows `FATAL` or `EXITED`

**Solutions:**
```bash
# Check detailed error logs
sudo supervisorctl tail booking-worker:booking-worker-00 stderr

# Check file permissions
ls -la /var/www/your-project-name/artisan

# Fix permissions if needed
sudo chown -R www-data:www-data /var/www/your-project-name
sudo chmod +x /var/www/your-project-name/artisan

# Test command manually
sudo -u www-data php /var/www/your-project-name/artisan bookings:worker --interval=60 --max-runtime=120
```

#### 2. Database Connection Errors

**Solutions:**
```bash
# Check Laravel configuration
cd /var/www/your-project-name
php artisan config:clear
php artisan cache:clear

# Test database connection
php artisan tinker
# Test: DB::connection()->getPdo()

# Check MySQL service
sudo systemctl status mysql

# Verify database credentials in .env
cat .env | grep DB_
```

#### 3. Permission Denied Errors

**Solutions:**
```bash
# Fix Laravel permissions
sudo chown -R www-data:www-data /var/www/your-project-name
sudo chmod -R 775 /var/www/your-project-name/storage
sudo chmod -R 775 /var/www/your-project-name/bootstrap/cache

# Fix log permissions
sudo chown -R www-data:www-data /var/log/supervisor
```

#### 4. Worker Running but Not Processing

**Diagnostic Steps:**
```bash
# Check if bookings exist to process
cd /var/www/your-project-name
php artisan tinker
# Test: VehicleBooking::expired()->count()

# Create test expired booking
VehicleBooking::create([
    'vehicle_id' => 1,
    'user_id' => 1,
    'start_time' => now()->subHours(2),
    'end_time' => now()->subHours(1),
    'destination' => 'Test',
    'status' => 'approved'
]);

# Monitor worker for processing
sudo supervisorctl tail -f booking-worker:booking-worker-00
```

#### 5. High Memory Usage

**Solutions:**
```bash
# Monitor memory usage
sudo supervisorctl tail booking-worker:booking-worker-00 | grep "memory"

# Restart worker if memory is high
sudo supervisorctl restart booking-worker:*

# Consider adding memory limit to supervisor config
```

### Configuration Debugging

```bash
# Validate supervisor configuration syntax
sudo supervisorctl reread

# Check if configuration is loaded
sudo supervisorctl avail

# View effective configuration
sudo supervisorctl show booking-worker:booking-worker-00
```

## 🔄 Maintenance

### Code Updates

```bash
# 1. Stop the worker
sudo supervisorctl stop booking-worker:*

# 2. Update code (via git or file upload)
cd /var/www/your-project-name
git pull origin main  # or upload new files

# 3. Update dependencies if needed
composer install --optimize-autoloader --no-dev

# 4. Clear caches
php artisan config:clear
php artisan cache:clear
php artisan config:cache

# 5. Run migrations if needed
php artisan migrate --force

# 6. Start the worker
sudo supervisorctl start booking-worker:*

# 7. Verify operation
sudo supervisorctl status
```

### Regular Maintenance

```bash
# Weekly log rotation (add to crontab)
# 0 2 * * 0 /usr/bin/find /var/log/supervisor -name "*.log" -mtime +30 -delete

# Monthly system updates
sudo apt-get update && sudo apt-get upgrade -y

# Check disk space
df -h

# Monitor worker uptime
sudo supervisorctl status | grep booking-worker
```

### Backup Important Files

```bash
# Backup supervisor configuration
sudo cp /etc/supervisor/conf.d/booking-worker.conf ~/backup/

# Backup Laravel .env file
cp /var/www/your-project-name/.env ~/backup/

# Backup recent logs
sudo cp /var/log/supervisor/booking-worker.log ~/backup/
```

## ✅ Verification Checklist

After deployment, verify these items:

- [ ] **Supervisor Status**: `sudo supervisorctl status` shows `RUNNING`
- [ ] **Worker Logs**: Real-time logs show periodic booking checks
- [ ] **Database**: Test expired bookings are automatically completed
- [ ] **Error Handling**: Worker recovers from temporary database disconnections
- [ ] **Auto-Restart**: Worker restarts automatically after manual kill
- [ ] **System Boot**: Worker starts automatically after server restart
- [ ] **Performance**: System resources are stable
- [ ] **Logging**: All logs are properly written and rotated

## 📞 Support & Additional Resources

### Useful Commands Quick Reference

```bash
# Supervisor
sudo supervisorctl status                    # Check all processes
sudo supervisorctl restart booking-worker:* # Restart worker
sudo supervisorctl tail -f booking-worker:* # Real-time logs

# Laravel
php artisan bookings:worker --help          # Command help
php artisan tinker                          # Laravel REPL
php artisan config:clear                    # Clear config cache

# System
sudo systemctl status supervisor            # Supervisor daemon status
ps aux | grep booking                       # Find worker processes
sudo tail -f /var/log/syslog                # System logs
```

### Configuration Templates

For different server setups, update paths accordingly:

**Shared Hosting:**
```ini
command=php /home/username/public_html/artisan bookings:worker --interval=900
directory=/home/username/public_html
user=username
```

**VPS with Custom User:**
```ini
command=php /home/deploy/app/artisan bookings:worker --interval=900
directory=/home/deploy/app  
user=deploy
```

---

## 🎉 Congratulations!

Your Laravel Vehicle Booking Auto-Complete Worker is now running 24/7 on your production server! 

The worker will:
- ✅ Check for expired bookings every 15 minutes
- ✅ Automatically complete expired bookings
- ✅ Log all activities for monitoring
- ✅ Restart automatically if any issues occur
- ✅ Start automatically when server boots

**Your booking system is now fully automated and production-ready!** 🚀