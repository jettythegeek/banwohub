# Recommended Tech Stack

## 1. Frontend: Vue 3 + TypeScript + Vite

Use:

- Vue 3
- TypeScript
- Vite
- Vue Router
- Pinia (auth and app state)
- Tailwind CSS v4
- Axios
- lucide-vue-next (icons)

## 2. Backend: Laravel 13 API

Use:

- Laravel 13
- PHP 8.3+
- Laravel Sanctum or Passport
- Laravel Horizon
- Laravel Queue
- Laravel Scheduler
- Laravel Reverb or Pusher for realtime
- Spatie Permission
- Spatie Activity Log
- Laravel Cashier or custom billing layer

## 3. Database: MySQL

Use database name `banwohub`. Store credentials in environment variables (e.g. `.env`), not in documentation or version control.

Example (local development only):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=banwohub
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## 4. Search: OpenSearch

Use:

OpenSearch

## 7. AI Layer: Separate AI Service

Do not put all AI logic directly inside Laravel.

Use a separate AI service

This AI service should handle:

- Chatbot and case Q&A
- Document summarize and draft assist
- Intake and timeline summaries
- Governance logging (prompt context, output id, user, timestamp)

## 5. Queues and background jobs

Use Laravel queues (database or Redis) with Horizon for monitoring. Offload mail, search indexing, document processing, and webhooks.

## 6. Documents and storage

Use Laravel filesystem (local/S3-compatible) for matter files. Generate PDFs server-side. Virus scan and size limits per organization settings.

## 8. Payments

Use Stripe and/or PayPal for client invoices and portal checkout. Webhooks verified in Laravel; no card data stored in the app database.
