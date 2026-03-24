# English Listening Test API Documentation

*See the docs folder for the full API specification and examples.*

## Base URL
```
http://localhost:8000/api
```

## Quick Reference

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tests` | Get all tests |
| GET | `/tests/detail?id=1` | Get test with questions |
| POST | `/tests` | Create test |
| PUT | `/tests?id=1` | Update test |
| DELETE | `/tests?id=1` | Delete test |
| POST | `/results` | Submit test answers |
| GET | `/results/detail?id=1` | Get result |
| GET | `/results?page=1` | Get results list |
| GET | `/results/stats?testId=1` | Get statistics |
| POST | `/users/register` | Register user |
| POST | `/users/login` | Login user |
| GET | `/users?id=1` | Get user info |
| GET | `/users/results?userId=1` | Get user test history |

## Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 404 | Not Found |
| 500 | Server Error |

## Example Response

### Get Tests
```json
{
  "success": true,
  "message": "Tests retrieved",
  "data": [
    {
      "id": 1,
      "title": "English Listening Test - Part 1",
      "description": "Vietnam's 6-Level Language Proficiency Test",
      "level": "B1-B2",
      "totalQuestions": 8,
      "duration": 3600,
      "createdAt": "2024-01-15 10:00:00"
    }
  ],
  "statusCode": 200
}
```

## Error Example

```json
{
  "success": false,
  "error": "test_not_found",
  "message": "Test not found",
  "statusCode": 404
}
```

## Common Errors

| Error | Status | Solution |
|-------|--------|----------|
| `missing_fields` | 400 | Check required parameters |
| `test_not_found` | 404 | Verify test ID exists |
| `email_exists` | 400 | Use different email |
| `username_exists` | 400 | Use different username |
| `invalid_request` | 400 | Check JSON format |
| `server_error` | 500 | Check server logs |

## Rate Limiting

Not currently implemented. Will be added in future versions.

## Caching

For best performance, cache:
- GET `/tests`: 1 hour
- GET `/results/stats`: 15 minutes
- GET `/tests/detail`: 30 minutes
