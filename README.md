# vstep-listening-api

Backend PHP API cho hệ thống luyện thi VSTEP Listening — phần Nghe hiểu tiếng Anh.

## Tech Stack

- **PHP 8.0+** + MySQL 8.0+
- **Appwrite Storage** — lưu trữ file audio
- **JWT** — xác thực qua HttpOnly cookie
- **Composer** — quản lý dependencies

## Cấu trúc đề thi VSTEP

```
Exam
├── Part 1 — Announcements & Short Messages
│   ├── Audio (1 file)
│   └── Questions 1–8
├── Part 2 — Long Conversations
│   ├── Passage 1 (audio + 4 câu)
│   ├── Passage 2 (audio + 4 câu)
│   └── Passage 3 (audio + 4 câu)
└── Part 3 — Lectures & Talks
    ├── Passage 1 (audio + 5 câu)
    ├── Passage 2 (audio + 5 câu)
    └── Passage 3 (audio + 5 câu)
```

## Cài đặt

```bash
# 1. Clone repo
git clone https://github.com/your-username/vstep-listening-api.git
cd vstep-listening-api

# 2. Cài dependencies
php composer.phar install

# 3. Tạo database
mysql -u root -p < db/schema.sql

# 4. Cấu hình môi trường
cp config/database.php.example config/database.php
# Sửa thông tin DB trong config/database.php
# Sửa thông tin Appwrite trong config/appwrite.php

# 5. Khởi động server
php -S localhost:8000 -t public
```

## Cấu hình

### Database — `config/database.php`
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'listening_test');
```

### Appwrite Storage — `config/appwrite.php`
```php
define('APPWRITE_ENDPOINT', 'https://sgp.cloud.appwrite.io/v1');
define('APPWRITE_PROJECT_ID', 'your-project-id');
define('APPWRITE_API_KEY',    'your-api-key');
define('APPWRITE_BUCKET_ID',  'your-bucket-id');
```

### CORS — `public/index.php`
```php
$allowed_origins = [
    'http://localhost:3000',
    'https://your-frontend.com',
];
```

## API Endpoints

### Auth
| Method | Endpoint | Mô tả |
|--------|----------|-------|
| POST | `/api/users/register` | Đăng ký |
| POST | `/api/users/login` | Đăng nhập |
| POST | `/api/users/logout` | Đăng xuất |
| GET  | `/api/users/check-status` | Kiểm tra session |
| GET  | `/api/users?id=` | Thông tin user |

### Exams (Public)
| Method | Endpoint | Mô tả |
|--------|----------|-------|
| GET | `/api/v1/exams` | Danh sách đề thi |
| GET | `/api/v1/exams/{id}` | Chi tiết đề thi |

### Admin — Quản lý đề thi
| Method | Endpoint | Mô tả |
|--------|----------|-------|
| POST   | `/api/v1/admin/exams` | Tạo đề thi |
| PUT    | `/api/v1/admin/exams/{id}` | Cập nhật đề thi |
| DELETE | `/api/v1/admin/exams/{id}` | Xóa đề thi |
| POST   | `/api/v1/admin/exams/{examId}/parts` | Tạo part |
| PUT    | `/api/v1/admin/exams/{examId}/parts/{partId}` | Cập nhật part |
| DELETE | `/api/v1/admin/exams/{examId}/parts/{partId}` | Xóa part |
| POST   | `/api/v1/admin/parts/{partId}/upload-audio` | Upload audio cho part |
| POST   | `/api/v1/admin/exams/{examId}/parts/{partId}/passages` | Tạo passage |
| PUT    | `/api/v1/admin/exams/{examId}/parts/{partId}/passages/{id}` | Cập nhật passage |
| DELETE | `/api/v1/admin/exams/{examId}/parts/{partId}/passages/{id}` | Xóa passage |
| POST   | `/api/v1/admin/exams/{examId}/questions` | Tạo câu hỏi |
| PUT    | `/api/v1/admin/exams/{examId}/questions/{id}` | Cập nhật câu hỏi |
| DELETE | `/api/v1/admin/exams/{examId}/questions/{id}` | Xóa câu hỏi |
| POST   | `/api/v1/admin/exams/{examId}/questions/{id}/options` | Tạo đáp án |
| PUT    | `/api/v1/admin/exams/{examId}/questions/{id}/options/{optId}` | Cập nhật đáp án |
| DELETE | `/api/v1/admin/exams/{examId}/questions/{id}/options/{optId}` | Xóa đáp án |

### Kết quả
| Method | Endpoint | Mô tả |
|--------|----------|-------|
| POST | `/api/results` | Nộp bài |
| GET  | `/api/results` | Danh sách kết quả |
| GET  | `/api/results/detail?id=` | Chi tiết kết quả |
| GET  | `/api/results/stats?testId=` | Thống kê |

## Response Format

```json
// Success
{ "success": true, "message": "...", "data": {}, "statusCode": 200 }

// Error
{ "success": false, "error": "error_code", "message": "...", "statusCode": 400 }
```

## Cấu trúc thư mục

```
vstep-listening-api/
├── api/
│   ├── controllers/
│   │   ├── ExamController.php
│   │   ├── PartController.php
│   │   ├── PassageController.php
│   │   ├── QuestionController.php
│   │   ├── OptionController.php
│   │   ├── UserController.php
│   │   ├── ResultController.php
│   │   └── TestAccessController.php
│   └── models/
│       ├── Exam.php
│       ├── Part.php
│       ├── Passage.php
│       ├── Question.php
│       ├── Option.php
│       ├── User.php
│       ├── UserExam.php
│       ├── UserAnswer.php
│       └── Result.php
├── config/
│   ├── database.php
│   ├── appwrite.php
│   ├── response.php
│   ├── token.php
│   └── upload.php
├── db/
│   └── schema.sql
├── public/
│   └── index.php       ← Entry point
├── vendor/
├── composer.json
└── setup.php
```

## Deploy lên Railway

1. Push code lên GitHub
2. Tạo project mới trên [Railway](https://railway.app)
3. Connect GitHub repo
4. Thêm MySQL service
5. Set environment variables:
   - `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
   - `APPWRITE_ENDPOINT`, `APPWRITE_PROJECT_ID`, `APPWRITE_API_KEY`, `APPWRITE_BUCKET_ID`
   - `JWT_SECRET`
6. Set start command: `php -S 0.0.0.0:$PORT -t public`

## License

MIT
