# Fix Storage Permissions Error

## Error
```
file_put_contents(/var/www/jippy_resto/storage/framework/views/...): 
Failed to open stream: Permission denied
```

## Solution

Run these commands on your server (SSH into the server first):

### Option 1: Set Permissions (Recommended)
```bash
cd /var/www/jippy_resto

# Set ownership to web server user (usually www-data for Apache/Nginx)
sudo chown -R www-data:www-data storage bootstrap/cache

# Set directory permissions (755 = rwxr-xr-x)
sudo find storage -type d -exec chmod 755 {} \;

# Set file permissions (644 = rw-r--r--)
sudo find storage -type f -exec chmod 644 {} \;

# Make storage writable
sudo chmod -R 775 storage bootstrap/cache
```

### Option 2: If using a different web server user
```bash
# For Apache
sudo chown -R apache:apache storage bootstrap/cache

# For Nginx (if using PHP-FPM with different user)
sudo chown -R nginx:nginx storage bootstrap/cache
# OR
sudo chown -R www-data:www-data storage bootstrap/cache
```

### Option 3: Quick Fix (if you know your web server user)
```bash
# Find your web server user
ps aux | grep -E 'apache|httpd|nginx|php-fpm' | head -1

# Then set ownership (replace www-data with your actual user)
sudo chown -R www-data:www-data /var/www/jippy_resto/storage
sudo chown -R www-data:www-data /var/www/jippy_resto/bootstrap/cache
sudo chmod -R 775 /var/www/jippy_resto/storage
sudo chmod -R 775 /var/www/jippy_resto/bootstrap/cache
```

### Verify the fix
```bash
# Check permissions
ls -la /var/www/jippy_resto/storage/framework/views

# Try clearing cache
cd /var/www/jippy_resto
php artisan view:clear
php artisan cache:clear
```

## Common Web Server Users
- **Apache on Ubuntu/Debian**: `www-data`
- **Apache on CentOS/RHEL**: `apache`
- **Nginx with PHP-FPM**: Usually `www-data` or `nginx`
- **Shared hosting**: Usually your username or `nobody`

## Notes
- Never use `777` permissions (security risk)
- `775` allows owner and group to write
- Make sure the web server user is in the same group, or use `775` with proper group ownership


