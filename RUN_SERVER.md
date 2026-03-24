# Running the API Server

## Using PHP Built-in Server (Recommended for Development)

From the `public/` directory, run:

```bash
php -S localhost:8000 router.php
```

**Important**: Make sure to include `router.php` so all API requests are routed through `index.php`.

The `-r` flag (or `-router` in some versions) tells PHP which file to use as the router script. This ensures:
- All API requests (`/api/...`) go through `index.php` for routing
- Static files (if any in public) are served directly
- CORS headers are properly sent on all requests

## Testing CORS

From your frontend (localhost:3000), test with:

```javascript
fetch('http://localhost:8000/api/users/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify({
        email: 'test@example.com',
        password: 'password123'
    })
})
.then(r => r.json())
.then(data => console.log(data))
.catch(e => console.error(e));
```

## Troubleshooting CORS Errors

If you still get CORS errors:

1. **Verify the server is running**: `curl http://localhost:8000/api/users/login`
2. **Check OPTIONS preflight**: `curl -X OPTIONS http://localhost:8000/api/users/login -v`
3. **Check browser console**: Look for specific error messages
4. **Enable debug logging**: Check `error_log` function output in PHP error log

## Using Apache (Production)

If using Apache in production:

1. Set DocumentRoot to: `d:\php\english-listening-test\public`
2. Ensure mod_rewrite is enabled
3. The `.htaccess` file is already configured with proper rewrite rules
4. Test with: `curl -X OPTIONS http://yourdomain.com/api/users/login -v`

## Database Setup

Before testing APIs, run:

```bash
php setup.php
```

This creates the database and tables.
