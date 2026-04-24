# vstep-listening-api

Backend PHP API cho hệ thống luyện thi VSTEP Listening — phần Nghe hiểu tiếng Anh.

## Tech Stack

- **PHP 8.0+** + MySQL 8.0+
- **Cloudinary** — lưu trữ file audio (CDN toàn cầu)
- **Custom Token (HMAC-SHA256)** — xác thực qua HttpOnly cookie
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
composer install

# 3. Tạo database
mysql -u root -p < db/schema.sql

# 4. Cấu hình môi trường
cp env.local.example env.local
# Sửa thông tin trong env.local

# 5. Khởi động server
php -S localhost:8000 public/index.php
```

## Cấu hình `env.local`

```env
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
ALLOWED_ORIGINS=http://localhost:3000,https://your-frontend.com
```

## Phân quyền

| Role | Quyền |
|------|-------|
| `user` | Làm bài, xem kết quả, xem lịch sử |
| `admin` | Toàn bộ quyền user + quản lý đề thi |

Đặt admin cho tài khoản:
```sql
UPDATE users SET role = 'admin' WHERE id = 1;
```

## API Endpoints

### Auth
| Method | Endpoint | Mô tả |
|--------|----------|-------|
| POST | `/api/users/register` | Đăng ký |
| POST | `/api/users/login` | Đăng nhập (trả về `role` trong response) |
| POST | `/api/users/logout` | Đăng xuất |
| GET  | `/api/users/check-status` | Kiểm tra session |

### Exams (Public)
| Method | Endpoint | Mô tả |
|--------|----------|-------|
| GET | `/api/v1/exams` | Danh sách đề thi |
| GET | `/api/v1/exams/{id}` | Chi tiết đề thi |
| GET | `/api/v1/exams/{id}?for_taking=1` | Đề thi để làm bài (ẩn đáp án, script) |

### Test Access (Cần đăng nhập)
| Method | Endpoint | Mô tả |
|--------|----------|-------|
| POST | `/api/v1/exams/{id}/start` | Bắt đầu làm bài → trả về `user_exam_id` |
| POST | `/api/v1/user-exams/{id}/answer` | Lưu câu trả lời |
| POST | `/api/v1/user-exams/{id}/submit` | Nộp bài |
| GET  | `/api/v1/user-exams/{id}/result` | Xem kết quả chi tiết + đáp án + script |
| GET  | `/api/v1/users/exams/history` | Lịch sử làm bài |
| DELETE | `/api/v1/user-exams/{id}` | Xóa 1 lần làm khỏi lịch sử |

### Admin — Quản lý đề thi (Cần role `admin`)
| Method | Endpoint | Mô tả |
|--------|----------|-------|
| POST   | `/api/v1/admin/exams` | Tạo đề thi |
| PUT    | `/api/v1/admin/exams/{id}` | Cập nhật đề thi |
| DELETE | `/api/v1/admin/exams/{id}` | Xóa đề thi + toàn bộ audio |
| POST   | `/api/v1/admin/exams/{examId}/parts` | Tạo part |
| PUT    | `/api/v1/admin/exams/{examId}/parts/{partId}` | Cập nhật part |
| DELETE | `/api/v1/admin/exams/{examId}/parts/{partId}` | Xóa part + audio |
| POST   | `/api/v1/admin/parts/{partId}/upload-audio` | Upload audio cho part |
| POST   | `/api/v1/admin/exams/{examId}/parts/{partId}/passages` | Tạo passage |
| PUT    | `/api/v1/admin/exams/{examId}/parts/{partId}/passages/{id}` | Cập nhật passage |
| DELETE | `/api/v1/admin/exams/{examId}/parts/{partId}/passages/{id}` | Xóa passage + audio |
| POST   | `/api/v1/admin/exams/{examId}/questions` | Tạo câu hỏi |
| PUT    | `/api/v1/admin/exams/{examId}/questions/{id}` | Cập nhật câu hỏi |
| DELETE | `/api/v1/admin/exams/{examId}/questions/{id}` | Xóa câu hỏi |
| POST   | `/api/v1/admin/exams/{examId}/questions/{id}/options` | Tạo đáp án |
| PUT    | `/api/v1/admin/exams/{examId}/questions/{id}/options/{optId}` | Cập nhật đáp án |
| DELETE | `/api/v1/admin/exams/{examId}/questions/{id}/options/{optId}` | Xóa đáp án |

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
│   ├── cloudinary.php
│   ├── response.php
│   ├── token.php
│   └── upload.php
├── db/
│   └── schema.sql
├── public/
│   └── index.php       ← Entry point
├── env.local
├── vendor/
├── composer.json
└── setup.php
```

## Deploy lên Render

### Cach 1: Dung render.yaml (khuyen nghi)

1. Push code len GitHub.
2. Trong Render, chon New + -> Blueprint.
3. Chon repo nay, Render se doc file `render.yaml`.
4. Dien cac bien moi truong dang `sync: false` trong dashboard.
5. Deploy va test health check:
    - `https://your-service.onrender.com/health`

### Cach 2: Tao Web Service thu cong

1. Runtime: PHP
2. Build Command:
    - `composer install --no-dev --optimize-autoloader`
3. Start Command:
    - `php -S 0.0.0.0:$PORT -t public public/router.php`
4. Health Check Path:
    - `/health`

### Bien moi truong bat buoc

- `DB_HOST`
- `DB_PORT` (thuong la `3306`)
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `ALLOWED_ORIGINS`
- `TOKEN_SECRET`

### Bien moi truong tuy chon

- `API_URL`
- `FRONTEND_URL`
- `TOKEN_EXPIRY`
- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`

Luu y:
- Render khong dung `.htaccess` nhu shared hosting Apache, service nay chay bang `php -S` + `public/router.php`.
- Neu dung Render free plan, service co the bi sleep khi khong co luu luong.

## License

MIT
