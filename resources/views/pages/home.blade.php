<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Home - Inventory App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .sidebar a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: block;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s;
        }
        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .logo {
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 1rem;
        }
        .content {
            padding: 2rem;
        }
        .topbar {
            background: white;
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dashboard-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        .dashboard-card h2 {
            color: #333;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .btn-logout {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-logout:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 sidebar">
                <div class="logo">
                    📦 Inventory
                </div>
                <nav>
                    <a href="{{ route('pages.home') }}"><i class="bx bx-home"></i> Home</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <div class="topbar">
                    <div>
                        <h3>Welcome, {{ Auth::user()->name }}! 👋</h3>
                    </div>
                    <div>
                        <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn-logout">Logout</button>
                        </form>
                    </div>
                </div>

                <div class="content">
                    <div class="dashboard-card">
                        <h2>User Home</h2>
                        <p>Welcome to the Inventory Management System. You are logged in as a <strong>{{ Auth::user()->role }}</strong> user.</p>
                        <p>Your email: <strong>{{ Auth::user()->email }}</strong></p>
                    </div>

                    <div class="dashboard-card">
                        <h2>Information</h2>
                        <p>Contact your administrator if you need any assistance with accessing inventory features or account management.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
