# LiteSpeed Storage Fix

## Issue
Storage files work locally but not on LiteSpeed server.

## Root Cause
Based on the diagnostic test:
- ✅ Storage path is correctly identified
- ✅ Files exist in storage directory
- ⚠️ Request URI shows double slash: `//storage/test.php`
- ⚠️ LiteSpeed may handle .htaccess differently than Apache

## Solutions Applied

### 1. Fixed Double Slash Handling
Updated `public/storage/index.php` to handle double slashes in REQUEST_URI:
```php
$requestedPath = preg_replace('#/{2,}#', '/', $requestedPath);
```

### 2. Improved Path Resolution
Added multiple fallback methods to get the file path:
- From `$_GET['file']` (query parameter)
- From `REQUEST_URI` (with double slash fix)
- From `PATH_INFO` (if available)
- From `SCRIPT_NAME` (fallback)

### 3. Updated .htaccess for LiteSpeed Compatibility
- Simplified rewrite rules
- Added handling for optional leading slashes
- Removed complex security rules that might not work on LiteSpeed

### 4. Added Debug Mode
You can now test with debug mode:
```
https://yourdomain.com/storage/restaurants/image.jpg?debug=1
```

## Testing

### Test 1: Direct File Access
```
https://yourdomain.com/storage/restaurants/ilthV9wPz6fdmuBlJ0VrTApz6tbMgtKgLnDdUVxl.jpg
```

### Test 2: Via Index.php
```
https://yourdomain.com/storage/index.php?file=restaurants/ilthV9wPz6fdmuBlJ0VrTApz6tbMgtKgLnDdUVxl.jpg
```

### Test 3: With Debug
```
https://yourdomain.com/storage/restaurants/ilthV9wPz6fdmuBlJ0VrTApz6tbMgtKgLnDdUVxl.jpg?debug=1
```

## If Still Not Working

### Option 1: Check .htaccess Processing
Some LiteSpeed servers require `.htaccess` to be explicitly enabled. Check with your hosting provider.

### Option 2: Use Direct Access
If `.htaccess` isn't working, you can access files directly:
```
https://yourdomain.com/storage/index.php?file=restaurants/image.jpg
```

### Option 3: Create LiteSpeed Configuration
If you have access to LiteSpeed configuration, you can add:
```
RewriteRule ^storage/(.*)$ /storage/index.php?file=$1 [L,QSA]
```

### Option 4: Check File Permissions
```bash
chmod 755 public/storage
chmod 644 public/storage/index.php
chmod 644 public/storage/.htaccess
chmod -R 755 storage/app/public
```

## Expected Behavior

1. Request: `https://yourdomain.com/storage/restaurants/image.jpg`
2. `.htaccess` routes to: `storage/index.php?file=restaurants/image.jpg`
3. `index.php` reads from: `/home/.../storage/app/public/restaurants/image.jpg`
4. File is served with proper headers

## Files Modified

- ✅ `public/storage/index.php` - Enhanced path resolution and double slash handling
- ✅ `public/storage/.htaccess` - Simplified for LiteSpeed compatibility
- ✅ `public/.htaccess` - Added double slash handling

## Next Steps

1. Upload the updated files to your server
2. Test with one of the test URLs above
3. If it works, delete `public/storage/test.php` for security
4. If it doesn't work, use the debug mode to see what's happening

