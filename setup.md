---

## 🚀 Laravel Project Setup Guide

Follow these steps to properly set up the Laravel project on your local machine:

---

### 1. Install Dependencies

Install all required PHP packages using Composer:

```bash
composer install
```

---

### 2. Prepare Environment File

Ensure the `.env` file exists in the root directory.

If it is missing, create it by copying the example file:

```bash
cp .env.example .env
```

---

### 3. Configure Environment(Optional)

Open the `.env` file and update your database configuration:

```env
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

---

### 4. Generate Application Key

Run the following command:

```bash
php artisan key:generate
```

* This generates a secure `APP_KEY`
* Automatically saved in your `.env` file

---

### 5. Run Database Migrations

Create the necessary database tables:

```bash
php artisan migrate
```

---

### 6. (Optional) Seed the Database

If your project includes seeders:

```bash
php artisan db:seed
```

---

### 7. Run the Application

Start the local development server:

```bash
php artisan serve
```

Then open in your browser:

```
http://127.0.0.1:8000
```

---

### ✅ Optional Commands

For better development experience:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```