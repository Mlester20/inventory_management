-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 11, 2026 at 06:07 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`, `updated_at`) VALUES
(1, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-10 05:01:56', '2026-07-10 05:01:56'),
(2, 11, 'logout', 'User logged out', '127.0.0.1', '2026-07-10 06:10:28', '2026-07-10 06:10:28'),
(3, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-10 06:12:17', '2026-07-10 06:12:17'),
(4, 11, 'logout', 'User logged out', '127.0.0.1', '2026-07-10 06:12:22', '2026-07-10 06:12:22'),
(5, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-10 06:14:46', '2026-07-10 06:14:46'),
(6, 11, 'logout', 'User logged out', '127.0.0.1', '2026-07-10 07:03:51', '2026-07-10 07:03:51'),
(7, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 05:22:06', '2026-07-11 05:22:06'),
(8, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 05:24:33', '2026-07-11 05:24:33'),
(9, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 05:58:14', '2026-07-11 05:58:14'),
(10, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 06:34:12', '2026-07-11 06:34:12'),
(11, 11, 'logout', 'User logged out', '127.0.0.1', '2026-07-11 06:49:39', '2026-07-11 06:49:39'),
(12, 12, 'login', 'User logged in', '127.0.0.1', '2026-07-11 06:50:32', '2026-07-11 06:50:32'),
(13, 12, 'logout', 'User logged out', '127.0.0.1', '2026-07-11 06:51:24', '2026-07-11 06:51:24'),
(14, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 06:51:52', '2026-07-11 06:51:52'),
(15, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 07:06:14', '2026-07-11 07:06:14'),
(16, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 07:18:41', '2026-07-11 07:18:41'),
(17, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 07:21:08', '2026-07-11 07:21:08'),
(18, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 07:21:52', '2026-07-11 07:21:52'),
(19, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 07:33:17', '2026-07-11 07:33:17'),
(20, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 07:49:13', '2026-07-11 07:49:13'),
(21, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 07:50:18', '2026-07-11 07:50:18'),
(22, 11, 'login', 'User logged in', '127.0.0.1', '2026-07-11 08:04:19', '2026-07-11 08:04:19');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`, `created_at`, `updated_at`) VALUES
(1, 'Computer Accessories', '2026-07-10 05:05:54', '2026-07-10 05:05:54');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `po_no` varchar(255) DEFAULT NULL,
  `osca_no` varchar(255) DEFAULT NULL,
  `sales_no` varchar(255) NOT NULL,
  `prepared_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `vat_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vatex_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `zero_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `less_vat` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_net` decimal(12,2) NOT NULL DEFAULT 0.00,
  `less_sc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `less_wt` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_due` decimal(12,2) NOT NULL DEFAULT 0.00,
  `add_vat` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `customer_name`, `po_no`, `osca_no`, `sales_no`, `prepared_by`, `approved_by`, `vat_sales`, `vatex_sales`, `zero_sales`, `vat_amount`, `total_sales`, `less_vat`, `amount_net`, `less_sc`, `less_wt`, `amount_due`, `add_vat`, `created_at`, `updated_at`) VALUES
(3, 'Jorel Licuanan', '3200499', NULL, 'INV-2026-00001', NULL, NULL, 35000.05, 0.00, 0.00, 4375.01, 39375.06, 0.00, 39375.06, 0.00, 0.00, 39375.06, 0.00, '2026-07-11 06:06:43', '2026-07-11 06:06:43'),
(5, 'Mark Lester Raguindin', '2405001', NULL, 'INV-2026-00002', 11, 'John Doe', 0.00, 0.00, 15000.00, 0.00, 15000.00, 0.00, 15000.00, 0.00, 0.00, 15000.00, 0.00, '2026-07-11 07:24:00', '2026-07-11 07:24:00'),
(6, 'Armando Raguindin', NULL, NULL, 'INV-2026-00003', 11, 'Jorel Licuanan', 35000.00, 0.00, 0.00, 4375.00, 39375.00, 0.00, 39375.00, 0.00, 0.00, 39375.00, 0.00, '2026-07-11 07:37:55', '2026-07-11 07:37:55');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 5,
  `image` varchar(255) DEFAULT NULL,
  `tax_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `item_name`, `serial_number`, `category_id`, `supplier_id`, `description`, `quantity`, `unit_price`, `low_stock_threshold`, `image`, `tax_id`, `created_at`, `updated_at`) VALUES
(1, 'Mother Board', NULL, 1, 1, 'Acer Aspire', 26, 35000.00, 3, 'items/DfRA5TNZ8x2LDwVHd2leMSr8MM3FnyhUESm6voM0.jpg', 4, '2026-07-10 05:07:47', '2026-07-11 07:37:55');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_03_18_061017_create_categories_table', 1),
(5, '2026_03_18_061031_create_suppliers_table', 1),
(6, '2026_03_18_061040_create_items_table', 1),
(7, '2026_03_19_082433_create_purchases_table', 1),
(8, '2026_03_19_104153_create_activity_logs_table', 1),
(9, '2026_03_19_120000_create_stock_movements_table', 1),
(10, '2026_03_21_120000_add_user_id_to_purchases_table', 1),
(11, '2026_03_22_062932_create_return_items_table', 1),
(12, '2026_03_22_090000_add_notes_to_return_items_table', 1),
(13, '2026_07_10_134245_create_taxes_table', 2),
(14, '2026_07_10_144434_change_is_active_default_in_taxes_table', 3),
(15, '2026_07_11_133023_create_invoices_table', 4),
(16, '2026_07_11_140000_add_invoice_fields_to_items_table', 4),
(17, '2026_07_11_140100_create_sales_table', 4),
(18, '2026_07_11_150000_add_prepared_approved_by_to_invoices_table', 5),
(19, '2026_07_11_160000_convert_approved_by_to_text_on_invoices_table', 6),
(20, '2026_07_11_170000_add_profile_picture_to_users_table', 7);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity_sold` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `purchase_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `item_id`, `quantity_sold`, `unit_price`, `total_price`, `purchase_date`, `created_at`, `updated_at`, `user_id`) VALUES
(1, 1, 1, 35000.00, 35000.00, '2026-07-11', '2026-07-11 06:50:58', '2026-07-11 06:50:58', 12);

-- --------------------------------------------------------

--
-- Table structure for table `return_items`
--

CREATE TABLE `return_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `reason` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `desc` varchar(255) DEFAULT NULL,
  `qty` int(11) NOT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `batch_no` varchar(255) DEFAULT NULL,
  `exp` date DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `vat` decimal(12,2) NOT NULL DEFAULT 0.00,
  `dis` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `invoice_id`, `item_id`, `desc`, `qty`, `unit`, `batch_no`, `exp`, `price`, `vat`, `dis`, `amount`, `created_at`, `updated_at`) VALUES
(3, 3, 1, 'Mother Board', 1, 'pc', '1', '2029-12-26', 35000.05, 4375.01, 0.00, 35000.05, '2026-07-11 06:06:43', '2026-07-11 06:06:43'),
(5, 5, 1, 'Mother Board', 1, 'pc', '1', NULL, 35000.00, 0.00, 20000.00, 15000.00, '2026-07-11 07:24:00', '2026-07-11 07:24:00'),
(6, 6, 1, 'Mother Board', 1, 'pc', NULL, NULL, 35000.00, 4375.00, 0.00, 35000.00, '2026-07-11 07:37:55', '2026-07-11 07:37:55');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('0NRUlXUzgYFIvH7AQyYKHte9JHGtTc3ZivQjvobz', NULL, '127.0.0.1', 'curl/8.12.1', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoid1REQTBZeHdrM2pmSHVpc0VZWGNTcEFJY3V2b0VhNkNiY24wek0ybyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyMy9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1783778268),
('7p21x53ge2GJGDcOKgxAMXUWJAhMNpwHK0WwkbwA', 11, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/149.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoid1hyaXVhdUhKSVN1cmxSaDhJTFdnOUlLMmxQbVR5T2M1VEtCSlRlWiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzg6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyNi9hZG1pbi9pbnZvaWNlcy80IjtzOjU6InJvdXRlIjtzOjEzOiJpbnZvaWNlcy5zaG93Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTE7fQ==', 1783783271),
('8O4jhcoWbon1UJG9No5Ykaco3ANIBZgSt3TDLPxm', NULL, '127.0.0.1', 'curl/8.12.1', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWjczUWtVbWNiTU1qdWlhQjduUHl3ZFJpTnRKcUgxV2dpazF0ZXFYNCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyOC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1783784857),
('aLkBKOWfDfHj1bBHwCD0wWjhSzaP0KhZmlB4BSf5', 11, '127.0.0.1', 'curl/8.12.1', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiNWhUSWRBa2ZqazM3UHBzcDRkbWxmb1lCRkZ0STdOekh5eElJbHEzVCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyNC9hZG1pbi9pdGVtcyI7czo1OiJyb3V0ZSI7czoxMToiaXRlbXMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YToxNTp7aTowO3M6MTg6ImFsZXJ0LmNvbmZpZy50aXRsZSI7aToxO3M6MTc6ImFsZXJ0LmNvbmZpZy50ZXh0IjtpOjI7czoxODoiYWxlcnQuY29uZmlnLnRpbWVyIjtpOjM7czoyMzoiYWxlcnQuY29uZmlnLmJhY2tncm91bmQiO2k6NDtzOjE4OiJhbGVydC5jb25maWcud2lkdGgiO2k6NTtzOjIzOiJhbGVydC5jb25maWcuaGVpZ2h0QXV0byI7aTo2O3M6MjA6ImFsZXJ0LmNvbmZpZy5wYWRkaW5nIjtpOjc7czozMDoiYWxlcnQuY29uZmlnLnNob3dDb25maXJtQnV0dG9uIjtpOjg7czoyODoiYWxlcnQuY29uZmlnLnNob3dDbG9zZUJ1dHRvbiI7aTo5O3M6MzA6ImFsZXJ0LmNvbmZpZy5jb25maXJtQnV0dG9uVGV4dCI7aToxMDtzOjI5OiJhbGVydC5jb25maWcuY2FuY2VsQnV0dG9uVGV4dCI7aToxMTtzOjI5OiJhbGVydC5jb25maWcudGltZXJQcm9ncmVzc0JhciI7aToxMjtzOjI0OiJhbGVydC5jb25maWcuY3VzdG9tQ2xhc3MiO2k6MTM7czoxNzoiYWxlcnQuY29uZmlnLmljb24iO2k6MTQ7czoxMjoiYWxlcnQuY29uZmlnIjt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxMTtzOjU6ImFsZXJ0IjthOjE6e3M6NjoiY29uZmlnIjtzOjUwMDoieyJ0aXRsZSI6IlN1Y2Nlc3MiLCJ0ZXh0IjoiSXRlbSB1cGRhdGVkIHN1Y2Nlc3NmdWxseSIsInRpbWVyIjo1MDAwLCJiYWNrZ3JvdW5kIjoiI2ZmZiIsIndpZHRoIjoiMzJyZW0iLCJoZWlnaHRBdXRvIjp0cnVlLCJwYWRkaW5nIjoiMS4yNXJlbSIsInNob3dDb25maXJtQnV0dG9uIjp0cnVlLCJzaG93Q2xvc2VCdXR0b24iOmZhbHNlLCJjb25maXJtQnV0dG9uVGV4dCI6Ik9LIiwiY2FuY2VsQnV0dG9uVGV4dCI6IkNhbmNlbCIsInRpbWVyUHJvZ3Jlc3NCYXIiOmZhbHNlLCJjdXN0b21DbGFzcyI6eyJjb250YWluZXIiOm51bGwsInBvcHVwIjpudWxsLCJoZWFkZXIiOm51bGwsInRpdGxlIjpudWxsLCJjbG9zZUJ1dHRvbiI6bnVsbCwiaWNvbiI6bnVsbCwiaW1hZ2UiOm51bGwsImNvbnRlbnQiOm51bGwsImlucHV0IjpudWxsLCJhY3Rpb25zIjpudWxsLCJjb25maXJtQnV0dG9uIjpudWxsLCJjYW5jZWxCdXR0b24iOm51bGwsImZvb3RlciI6bnVsbH0sImljb24iOiJzdWNjZXNzIn0iO319', 1783780790),
('E1Lp831fRrifVxpxwkiaDgNDVLAnFatjDLsuLcno', NULL, '127.0.0.1', 'curl/8.12.1', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVWZjbEpESjdCcDJ3NEJNZThiMDJNVmJyQjNqQUJGS3BhT21mcGF0VyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyNi9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1783783085),
('EDuR1IpkEHpC7Z785R1RXAXnrUKv3bABkHKfPCJE', 11, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/149.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiNXZkb0IyaVZBRGZYOW5OakVCYm4xY0xFNW9sYkRHMXlTOHBBWmZVaCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDA6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyOS9hZG1pbi9wcm9maWxlL2VkaXQiO3M6NToicm91dGUiO3M6MTg6ImFkbWluLnByb2ZpbGUuZWRpdCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjExO30=', 1783785864),
('eZq6ncTwr0hlhKBPeb7KnQy0E080l5Fn4ZJpZ5Qe', 11, '127.0.0.1', 'curl/8.12.1', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiU2U1R2MzVzU4aUVucGpsOUlieFMxbU1DYzZ6eXJLVndPYlpSRDRUZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDM6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyNi9hZG1pbi9pbnZvaWNlcy9jcmVhdGUiO3M6NToicm91dGUiO3M6MTU6Imludm9pY2VzLmNyZWF0ZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjE1OntpOjA7czoxODoiYWxlcnQuY29uZmlnLnRpdGxlIjtpOjE7czoxNzoiYWxlcnQuY29uZmlnLnRleHQiO2k6MjtzOjE4OiJhbGVydC5jb25maWcudGltZXIiO2k6MztzOjIzOiJhbGVydC5jb25maWcuYmFja2dyb3VuZCI7aTo0O3M6MTg6ImFsZXJ0LmNvbmZpZy53aWR0aCI7aTo1O3M6MjM6ImFsZXJ0LmNvbmZpZy5oZWlnaHRBdXRvIjtpOjY7czoyMDoiYWxlcnQuY29uZmlnLnBhZGRpbmciO2k6NztzOjMwOiJhbGVydC5jb25maWcuc2hvd0NvbmZpcm1CdXR0b24iO2k6ODtzOjI4OiJhbGVydC5jb25maWcuc2hvd0Nsb3NlQnV0dG9uIjtpOjk7czozMDoiYWxlcnQuY29uZmlnLmNvbmZpcm1CdXR0b25UZXh0IjtpOjEwO3M6Mjk6ImFsZXJ0LmNvbmZpZy5jYW5jZWxCdXR0b25UZXh0IjtpOjExO3M6Mjk6ImFsZXJ0LmNvbmZpZy50aW1lclByb2dyZXNzQmFyIjtpOjEyO3M6MjQ6ImFsZXJ0LmNvbmZpZy5jdXN0b21DbGFzcyI7aToxMztzOjE3OiJhbGVydC5jb25maWcuaWNvbiI7aToxNDtzOjEyOiJhbGVydC5jb25maWciO31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjExO3M6NToiYWxlcnQiO2E6MTp7czo2OiJjb25maWciO3M6NTAzOiJ7InRpdGxlIjoiU3VjY2VzcyIsInRleHQiOiJJbnZvaWNlIGNyZWF0ZWQgc3VjY2Vzc2Z1bGx5IiwidGltZXIiOjUwMDAsImJhY2tncm91bmQiOiIjZmZmIiwid2lkdGgiOiIzMnJlbSIsImhlaWdodEF1dG8iOnRydWUsInBhZGRpbmciOiIxLjI1cmVtIiwic2hvd0NvbmZpcm1CdXR0b24iOnRydWUsInNob3dDbG9zZUJ1dHRvbiI6ZmFsc2UsImNvbmZpcm1CdXR0b25UZXh0IjoiT0siLCJjYW5jZWxCdXR0b25UZXh0IjoiQ2FuY2VsIiwidGltZXJQcm9ncmVzc0JhciI6ZmFsc2UsImN1c3RvbUNsYXNzIjp7ImNvbnRhaW5lciI6bnVsbCwicG9wdXAiOm51bGwsImhlYWRlciI6bnVsbCwidGl0bGUiOm51bGwsImNsb3NlQnV0dG9uIjpudWxsLCJpY29uIjpudWxsLCJpbWFnZSI6bnVsbCwiY29udGVudCI6bnVsbCwiaW5wdXQiOm51bGwsImFjdGlvbnMiOm51bGwsImNvbmZpcm1CdXR0b24iOm51bGwsImNhbmNlbEJ1dHRvbiI6bnVsbCwiZm9vdGVyIjpudWxsfSwiaWNvbiI6InN1Y2Nlc3MifSI7fX0=', 1783783202),
('gsNSl9JgbghFrTcgAJxQTwMJXgkxoKDwHGOZIXKi', 11, '127.0.0.1', 'curl/8.12.1', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiM1pkdkRtVThJQzVxNzdWZFpqSkRhWmYwNkZXaVFlcnJ3SmM5WnliMSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDM6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyMy9hZG1pbi9pbnZvaWNlcy9jcmVhdGUiO3M6NToicm91dGUiO3M6MTU6Imludm9pY2VzLmNyZWF0ZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjE1OntpOjA7czoxODoiYWxlcnQuY29uZmlnLnRpdGxlIjtpOjE7czoxNzoiYWxlcnQuY29uZmlnLnRleHQiO2k6MjtzOjE4OiJhbGVydC5jb25maWcudGltZXIiO2k6MztzOjIzOiJhbGVydC5jb25maWcuYmFja2dyb3VuZCI7aTo0O3M6MTg6ImFsZXJ0LmNvbmZpZy53aWR0aCI7aTo1O3M6MjM6ImFsZXJ0LmNvbmZpZy5oZWlnaHRBdXRvIjtpOjY7czoyMDoiYWxlcnQuY29uZmlnLnBhZGRpbmciO2k6NztzOjMwOiJhbGVydC5jb25maWcuc2hvd0NvbmZpcm1CdXR0b24iO2k6ODtzOjI4OiJhbGVydC5jb25maWcuc2hvd0Nsb3NlQnV0dG9uIjtpOjk7czozMDoiYWxlcnQuY29uZmlnLmNvbmZpcm1CdXR0b25UZXh0IjtpOjEwO3M6Mjk6ImFsZXJ0LmNvbmZpZy5jYW5jZWxCdXR0b25UZXh0IjtpOjExO3M6Mjk6ImFsZXJ0LmNvbmZpZy50aW1lclByb2dyZXNzQmFyIjtpOjEyO3M6MjQ6ImFsZXJ0LmNvbmZpZy5jdXN0b21DbGFzcyI7aToxMztzOjE3OiJhbGVydC5jb25maWcuaWNvbiI7aToxNDtzOjEyOiJhbGVydC5jb25maWciO31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjExO3M6NToiYWxlcnQiO2E6MTp7czo2OiJjb25maWciO3M6NTU0OiJ7InRpdGxlIjoiQ2Fubm90IGRlbGV0ZSIsInRleHQiOiJUaGlzIGludm9pY2UgaGFzIHJlY29yZGVkIHNhbGVzIGFuZCBzdG9jayBkZWR1Y3Rpb25zIGFuZCBjYW5ub3QgYmUgZGVsZXRlZC4iLCJ0aW1lciI6NTAwMCwiYmFja2dyb3VuZCI6IiNmZmYiLCJ3aWR0aCI6IjMycmVtIiwiaGVpZ2h0QXV0byI6dHJ1ZSwicGFkZGluZyI6IjEuMjVyZW0iLCJzaG93Q29uZmlybUJ1dHRvbiI6dHJ1ZSwic2hvd0Nsb3NlQnV0dG9uIjpmYWxzZSwiY29uZmlybUJ1dHRvblRleHQiOiJPSyIsImNhbmNlbEJ1dHRvblRleHQiOiJDYW5jZWwiLCJ0aW1lclByb2dyZXNzQmFyIjpmYWxzZSwiY3VzdG9tQ2xhc3MiOnsiY29udGFpbmVyIjpudWxsLCJwb3B1cCI6bnVsbCwiaGVhZGVyIjpudWxsLCJ0aXRsZSI6bnVsbCwiY2xvc2VCdXR0b24iOm51bGwsImljb24iOm51bGwsImltYWdlIjpudWxsLCJjb250ZW50IjpudWxsLCJpbnB1dCI6bnVsbCwiYWN0aW9ucyI6bnVsbCwiY29uZmlybUJ1dHRvbiI6bnVsbCwiY2FuY2VsQnV0dG9uIjpudWxsLCJmb290ZXIiOm51bGx9LCJpY29uIjoiZXJyb3IifSI7fX0=', 1783778574),
('jwrJAI6SnQb1CV7VixTJJXjvQJOaXxmkKlbDzK0g', NULL, '127.0.0.1', 'curl/8.12.1', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWDFucVBwOWxud0FrWlBMR05sUzdQS0x2TklYQkFSWExVOFZlS2h0VCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyOS9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1783785806),
('KOMLN8TFU2bzqIrV627xntSYAtI1NM6gsRUhWMBV', NULL, '127.0.0.1', 'curl/8.12.1', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiY0ZEd3A1MzUyUjI2SFBpanRDZEdtSTNNTWRqVUZ2bll0eFB5VnN2SyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyNC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1783780435),
('PNmItQM5onp3c4AzaS8i5x1XOunrFzgVhFK7vwDM', NULL, '127.0.0.1', 'curl/8.12.1', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQUJFMUtNSll6cEhFWjNUZDV1UG5DbFV4N0RLZnR0RTl1dnF3ZUtHUiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyNS9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1783781860),
('qqASwSLiyiwhQ4ph8HSwQ5a3ji2Hu5NWMY7uTcoL', NULL, '127.0.0.1', 'curl/8.12.1', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiV2daVkdsOWdXM0praUt3ak9zV0RSVnR2U1VxWXlTeXVXdGZac2tPcSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyNy9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1783783955),
('Rz8qreoV5hKHWMhj8XduPNURQ4te8r6C3JLVk5ad', 11, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiSzBCOGVGbEE4RmFsNmJ0WVkzeVBYOGFveWFQMTNzNFIzVHFvdlFkVSI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDA6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9wcm9maWxlL2VkaXQiO3M6NToicm91dGUiO3M6MTg6ImFkbWluLnByb2ZpbGUuZWRpdCI7fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjExO3M6NToiYWxlcnQiO2E6MDp7fX0=', 1783786004),
('UZ3BvDdW5dBWFet9IukJWMnvLMB24xGqUJsjBVMb', 11, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/149.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoieGNJOTNkYjU4VklrWUJ5WlAwWmtGVUk4RDZCZ292V2VRNDRFdkRYMiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDM6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyNS9hZG1pbi9pbnZvaWNlcy9jcmVhdGUiO3M6NToicm91dGUiO3M6MTU6Imludm9pY2VzLmNyZWF0ZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjExO30=', 1783782377),
('Wh1VJ3NbInifPFFtILVpNLQv2eqCjKdzckwLxjEi', 11, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/149.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiYWJKamZGcDFjOEV6U1EyRHpjZEZuMjVKUExlNzNBemYzYUNZR05YMCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyOC9hZG1pbi91c2VycyI7czo1OiJyb3V0ZSI7czoxMToidXNlcnMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxMTt9', 1783785023),
('x3vLnw15GobOz1j2GAAVdnSvbV5T3hnyl3w0cy16', 11, '127.0.0.1', 'curl/8.12.1', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiR3dWNndPRmlzZndIQmRwNkQ0ajhwb3Y0eTJIMkZSOHA3cldmdFJNYiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDA6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyOC9hZG1pbi9wcm9maWxlL2VkaXQiO3M6NToicm91dGUiO3M6MTg6ImFkbWluLnByb2ZpbGUuZWRpdCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjE1OntpOjA7czoxODoiYWxlcnQuY29uZmlnLnRpdGxlIjtpOjE7czoxNzoiYWxlcnQuY29uZmlnLnRleHQiO2k6MjtzOjE4OiJhbGVydC5jb25maWcudGltZXIiO2k6MztzOjIzOiJhbGVydC5jb25maWcuYmFja2dyb3VuZCI7aTo0O3M6MTg6ImFsZXJ0LmNvbmZpZy53aWR0aCI7aTo1O3M6MjM6ImFsZXJ0LmNvbmZpZy5oZWlnaHRBdXRvIjtpOjY7czoyMDoiYWxlcnQuY29uZmlnLnBhZGRpbmciO2k6NztzOjMwOiJhbGVydC5jb25maWcuc2hvd0NvbmZpcm1CdXR0b24iO2k6ODtzOjI4OiJhbGVydC5jb25maWcuc2hvd0Nsb3NlQnV0dG9uIjtpOjk7czozMDoiYWxlcnQuY29uZmlnLmNvbmZpcm1CdXR0b25UZXh0IjtpOjEwO3M6Mjk6ImFsZXJ0LmNvbmZpZy5jYW5jZWxCdXR0b25UZXh0IjtpOjExO3M6Mjk6ImFsZXJ0LmNvbmZpZy50aW1lclByb2dyZXNzQmFyIjtpOjEyO3M6MjQ6ImFsZXJ0LmNvbmZpZy5jdXN0b21DbGFzcyI7aToxMztzOjE3OiJhbGVydC5jb25maWcuaWNvbiI7aToxNDtzOjEyOiJhbGVydC5jb25maWciO31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjExO3M6NToiYWxlcnQiO2E6MTp7czo2OiJjb25maWciO3M6NTA0OiJ7InRpdGxlIjoiU3VjY2VzcyIsInRleHQiOiJQcm9maWxlIHVwZGF0ZWQgc3VjY2Vzc2Z1bGx5ISIsInRpbWVyIjo1MDAwLCJiYWNrZ3JvdW5kIjoiI2ZmZiIsIndpZHRoIjoiMzJyZW0iLCJoZWlnaHRBdXRvIjp0cnVlLCJwYWRkaW5nIjoiMS4yNXJlbSIsInNob3dDb25maXJtQnV0dG9uIjp0cnVlLCJzaG93Q2xvc2VCdXR0b24iOmZhbHNlLCJjb25maXJtQnV0dG9uVGV4dCI6Ik9LIiwiY2FuY2VsQnV0dG9uVGV4dCI6IkNhbmNlbCIsInRpbWVyUHJvZ3Jlc3NCYXIiOmZhbHNlLCJjdXN0b21DbGFzcyI6eyJjb250YWluZXIiOm51bGwsInBvcHVwIjpudWxsLCJoZWFkZXIiOm51bGwsInRpdGxlIjpudWxsLCJjbG9zZUJ1dHRvbiI6bnVsbCwiaWNvbiI6bnVsbCwiaW1hZ2UiOm51bGwsImNvbnRlbnQiOm51bGwsImlucHV0IjpudWxsLCJhY3Rpb25zIjpudWxsLCJjb25maXJtQnV0dG9uIjpudWxsLCJjYW5jZWxCdXR0b24iOm51bGwsImZvb3RlciI6bnVsbH0sImljb24iOiJzdWNjZXNzIn0iO319', 1783785416),
('y6IpPyreZEfmXLL5YFuOxelFCDdrQSGJu5CYd6Sv', 11, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/149.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiaERYWDZvcXBMd3JQbGVzNGphWVoxb0hKNjB2WVdLcEkwRjhjd2dmeSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDM6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyNi9hZG1pbi9pbnZvaWNlcy9jcmVhdGUiO3M6NToicm91dGUiO3M6MTU6Imludm9pY2VzLmNyZWF0ZSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjExO30=', 1783783315),
('yDT75aR7penDurWLy3YwUakFTmdYpNKoP3M51WcN', 11, '127.0.0.1', 'curl/8.12.1', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoidUg4ODd2N3MyZ3haeVVtV1lKOWtUQ3VzTnFyWnRIODZSNVBvUXJLQSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzg6Imh0dHA6Ly8xMjcuMC4wLjE6ODEyNy9hZG1pbi9pbnZvaWNlcy81IjtzOjU6InJvdXRlIjtzOjEzOiJpbnZvaWNlcy5zaG93Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTE7fQ==', 1783784000);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `type` enum('in','out') NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `item_id`, `user_id`, `quantity`, `type`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 11, 5, 'in', 'Purchase Order', '2026-07-10 05:08:43', '2026-07-10 05:08:43'),
(4, 1, 11, -1, 'out', 'Invoice INV-2026-00001', '2026-07-11 06:06:43', '2026-07-11 06:06:43'),
(5, 1, 12, -1, 'out', 'POS Purchase (TXN: TXN-6a5258528e86f)', '2026-07-11 06:50:58', '2026-07-11 06:50:58'),
(7, 1, 11, -1, 'out', 'Invoice INV-2026-00002', '2026-07-11 07:24:00', '2026-07-11 07:24:00'),
(8, 1, 11, -1, 'out', 'Invoice INV-2026-00003', '2026-07-11 07:37:55', '2026-07-11 07:37:55');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `created_at`, `updated_at`) VALUES
(1, 'Jorel Licuanan', 'John Doe', '09360991034', 'suguitanmark123@gmail.com', 'Rizal, Roxas', '2026-07-10 05:06:36', '2026-07-10 05:06:36');

-- --------------------------------------------------------

--
-- Table structure for table `taxes`
--

CREATE TABLE `taxes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `taxes`
--

INSERT INTO `taxes` (`id`, `name`, `rate`, `is_active`, `created_at`, `updated_at`) VALUES
(4, 'Vat', 12.50, 1, '2026-07-10 07:01:47', '2026-07-10 07:01:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `profile_picture`, `remember_token`, `created_at`, `updated_at`) VALUES
(11, 'Mark Lester', 'admin@gmail.com', '$2y$12$DBoFOcjLXr.gR5WZlYlS0eQyZlP2rlE3J9J3xAeeNxIYkDr3SmmW.', 'admin', 'profile_pictures/k2V4UlDNDYnoRxcbmBpcw8AQW28nB5XeJxKJ1EYO.jpg', '5dUcKWiKXcy2aFbo4kLr0koQRFQGPfzXGpsEi8wuGiYzYxx4OGJDEyEWZMWv', '2026-07-10 05:00:43', '2026-07-11 08:06:43'),
(12, 'John Doe', 'johndoe@gmail.com', '$2y$12$uKdqdfCgmrb3M8j5d6n4vObFaiz5z69eavE.u0kyFhu14.9c1X22e', 'user', NULL, NULL, '2026-07-11 06:49:35', '2026-07-11 06:49:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_logs_user_id_foreign` (`user_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoices_sales_no_unique` (`sales_no`),
  ADD KEY `invoices_prepared_by_foreign` (`prepared_by`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `items_category_id_foreign` (`category_id`),
  ADD KEY `items_supplier_id_foreign` (`supplier_id`),
  ADD KEY `items_tax_id_foreign` (`tax_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchases_item_id_foreign` (`item_id`),
  ADD KEY `purchases_user_id_foreign` (`user_id`);

--
-- Indexes for table `return_items`
--
ALTER TABLE `return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_items_item_id_foreign` (`item_id`),
  ADD KEY `return_items_user_id_foreign` (`user_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_invoice_id_foreign` (`invoice_id`),
  ADD KEY `sales_item_id_foreign` (`item_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_movements_item_id_index` (`item_id`),
  ADD KEY `stock_movements_user_id_index` (`user_id`),
  ADD KEY `stock_movements_type_index` (`type`),
  ADD KEY `stock_movements_created_at_index` (`created_at`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `taxes`
--
ALTER TABLE `taxes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `return_items`
--
ALTER TABLE `return_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `taxes`
--
ALTER TABLE `taxes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_prepared_by_foreign` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_tax_id_foreign` FOREIGN KEY (`tax_id`) REFERENCES `taxes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchases_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `return_items`
--
ALTER TABLE `return_items`
  ADD CONSTRAINT `return_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `return_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
