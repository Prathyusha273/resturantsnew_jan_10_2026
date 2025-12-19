# Storage Link Troubleshooting Guide

## Problem: Storage works locally but not on live server

### Common Causes:
1. Path resolution differences between local and live
2. `.htaccess` not being processed correctly
3. File permissions issues
4. Different directory structures on shared hosting

## Solutions

### Solution 1: Verify File Structure
Make sure these files exist on your live server:
- `public/storage/index.php` ✅
- `public/storage/.htaccess` ✅
- `storage/app/public/` directory exists ✅

### Solution 2: Test Direct Access
Try accessing the storage router directly:
```
https://yourdomain.com/storage/index.php?file=restaurants/test.jpg
```

### Solution 3: Check File Permissions
On your live server, set correct permissions:
```bash
chmod 755 public/storage
chmod 644 public/storage/index.php
chmod 644 public/storage/.htaccess
chmod -R 755 storage/app/public
```

### Solution 4: Enable Error Logging
Temporarily enable error display in `public/storage/index.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Solution 5: Test Path Resolution
Create a test file `public/storage/test.php`:
```php
<?php
echo "Current directory: " . __DIR__ . "<br>";
echo "Parent directory: " . dirname(__DIR__) . "<br>";
echo "Storage path: " . dirname(__DIR__) . '/../storage/app/public' . "<br>";
echo "Storage exists: " . (is_dir(dirname(__DIR__) . '/../storage/app/public') ? 'YES' : 'NO') . "<br>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
?>
```

Access it at: `https://yourdomain.com/storage/test.php`

### Solution 6: Alternative - Use Direct Path
If `.htaccess` isn't working, you can modify the filesystem config to use a different URL structure.

### Solution 7: Check Server Logs
Check your server error logs for:
- 404 errors
- 403 Forbidden errors
- PHP errors

### Solution 8: Verify .htaccess is Enabled
Some shared hosting requires `.htaccess` to be enabled. Check with your hosting provider.

## Quick Test Commands

### Test if storage directory is accessible:
```bash
curl -I https://yourdomain.com/storage/
```

### Test if a specific file exists:
```bash
curl -I https://yourdomain.com/storage/restaurants/test.jpg
```

### Test the router directly:
```bash
curl "https://yourdomain.com/storage/index.php?file=restaurants/test.jpg"
```

## Expected Behavior

1. Request: `https://yourdomain.com/storage/restaurants/image.jpg`
2. `.htaccess` routes to: `storage/index.php?file=restaurants/image.jpg`
3. `index.php` reads from: `storage/app/public/restaurants/image.jpg`
4. File is served with proper headers

## Still Not Working?

1. Check the error logs on your server
2. Verify the file actually exists in `storage/app/public/`
3. Test with a simple file first (like a text file)
4. Contact your hosting provider to verify `.htaccess` is enabled

