<!DOCTYPE html>
<html
  lang="en"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="{{ asset('assets/') }}"
  data-template="vertical-menu-template-free"
>
<head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />
    <title>@yield('title', 'Inventory App')</title>
    <meta name="description" content="" />
    <link rel="icon" type="image/x-icon" href="{{asset('assets/img/favicon/icon.png')}}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/theme-default.css') }}" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
    <style>
        .search-results-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1050;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-top: 5px;
        }
        .search-result-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background-color 0.15s;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: inherit;
        }
        .search-result-item:hover {
            background-color: #f8f9fa;
        }
        .search-result-item:last-child {
            border-bottom: none;
        }
        .search-result-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            color: #696cff;
        }
        .search-result-content {
            flex: 1;
        }
        .search-result-title {
            font-weight: 500;
            font-size: 14px;
            color: #333;
        }
        .search-result-subtitle {
            font-size: 12px;
            color: #999;
            margin-top: 3px;
        }
        .search-results-section-header {
            padding: 8px 16px;
            font-size: 11px;
            font-weight: 600;
            color: #999;
            text-transform: uppercase;
            background-color: #fafafa;
            border-bottom: 1px solid #f0f0f0;
        }
        .search-no-results {
            padding: 16px;
            text-align: center;
            color: #999;
            font-size: 14px;
        }
    </style>
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
</head>
<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <!-- Menu -->
        <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
          <div class="app-brand demo">
            <a href="{{ route('admin.dashboard') }}" class="app-brand-link">
              <img
                src="{{ asset('assets/img/favicon/icon.png') }}"
                alt="Inventory"
                class="app-brand-logo demo"
                style="width: 50px; height: 50px; object-fit: contain;"
              />
              <span class="app-brand-text demo menu-text fw-bolder ms-2">SAIMS</span>
            </a>

            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
              <i class="bx bx-chevron-left bx-sm align-middle"></i>
            </a>
          </div>

          <div class="menu-inner-shadow"></div>

          <ul class="menu-inner py-1">
            <!-- Dashboard -->
            <li class="menu-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
              <a href="{{ route('admin.dashboard') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                <div data-i18n="Analytics">Dashboard</div>
              </a>
            </li>


            <!-- Items & Inventory -->
            <li class="menu-header small text-uppercase">
              <span class="menu-header-text">Items &amp; Inventory</span>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-box"></i>
                <div data-i18n="Products &amp; Inventory">Products &amp; Inventory</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('inventory-items.index') }}" class="menu-link {{ request()->routeIs('inventory-items.*') || request()->routeIs('generic-names.*') || request()->routeIs('products.*') ? 'active' : '' }}">
                    <div data-i18n="Inventory Items">Inventory Items</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('categories.index') }}" class="menu-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                    <div data-i18n="Categories">Categories</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('inventory-adjustments.index') }}" class="menu-link {{ request()->routeIs('inventory-adjustments.*') ? 'active' : '' }}">
                    <div data-i18n="Inventory Adjustments">Inventory Adjustments</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('stock-disposals.index') }}" class="menu-link {{ request()->routeIs('stock-disposals.*') ? 'active' : '' }}">
                    <div data-i18n="Stock Disposals">Stock Disposals</div>
                  </a>
                </li>
              </ul>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-user"></i>
                <div data-i18n="Suppliers">Suppliers</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('suppliers.index') }}" class="menu-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                    <div data-i18n="Account">Suppliers</div>
                  </a>
                </li>
              </ul>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-group"></i>
                <div data-i18n="Customers">Customers</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('customers.index') }}" class="menu-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                    <div data-i18n="Customers">Customers</div>
                  </a>
                </li>
              </ul>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-cart"></i>
                <div data-i18n="Purchases">Purchases</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('purchases.index') }}" class="menu-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
                    <div data-i18n="Error">Purchases</div>
                  </a>
                </li>
              </ul>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-box"></i>
                <div data-i18n="Return Items">Return Items</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('return-items.index') }}" class="menu-link {{ request()->routeIs('return-items.*') ? 'active' : '' }}">
                    <div data-i18n="Error">Return Items</div>
                  </a>
                </li>
              </ul>
            </li>

            <!-- Taxes & Invoice -->
            <li class="menu-header small text-uppercase">
              <span class="menu-header-text">Taxes &amp; Invoice</span>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-calculator"></i>
                <div data-i18n="Taxes">Taxes</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('taxes.index') }}" class="menu-link {{ request()->routeIs('taxes.*') ? 'active' : '' }}">
                    <div data-i18n="Error">Manage Taxes</div>
                  </a>
                </li>
              </ul>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-receipt"></i>
                <div data-i18n="Invoices">Invoices</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('invoices.index') }}" class="menu-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                    <div data-i18n="Error">Sales Invoices</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('invoices.create') }}" class="menu-link">
                    <div data-i18n="Error">New Invoice</div>
                  </a>
                </li>
              </ul>
            </li>

            <!-- Sales -->
            <li class="menu-header small text-uppercase">
              <span class="menu-header-text">Sales</span>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-file"></i>
                <div data-i18n="Sales Quote">Sales Quote</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('sales-quotes.index') }}" class="menu-link {{ request()->routeIs('sales-quotes.*') ? 'active' : '' }}">
                    <div data-i18n="Error">Sales Quotes</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('sales-quotes.create') }}" class="menu-link">
                    <div data-i18n="Error">New Sales Quote</div>
                  </a>
                </li>
              </ul>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-cart"></i>
                <div data-i18n="Sales Order">Sales Order</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('sales-orders.index') }}" class="menu-link {{ request()->routeIs('sales-orders.*') ? 'active' : '' }}">
                    <div data-i18n="Error">Sales Orders</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('sales-orders.create') }}" class="menu-link">
                    <div data-i18n="Error">New Sales Order</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('delivery-receipts.index') }}" class="menu-link {{ request()->routeIs('delivery-receipts.*') ? 'active' : '' }}">
                    <div data-i18n="Error">Delivery Receipts</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('delivery-receipts.create') }}" class="menu-link">
                    <div data-i18n="Error">New Delivery Receipt</div>
                  </a>
                </li>
              </ul>
            </li>

            <!-- Purchase -->
            <li class="menu-header small text-uppercase">
              <span class="menu-header-text">Purchase</span>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-cart-alt"></i>
                <div data-i18n="Purchase Order">Purchase Order</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('purchase-orders.index') }}" class="menu-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
                    <div data-i18n="Error">Purchase Orders</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('purchase-orders.create') }}" class="menu-link">
                    <div data-i18n="Error">New Purchase Order</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('goods-receipts.index') }}" class="menu-link {{ request()->routeIs('goods-receipts.*') ? 'active' : '' }}">
                    <div data-i18n="Error">Goods Receipts</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('goods-receipts.create') }}" class="menu-link">
                    <div data-i18n="Error">New Goods Receipt</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('purchase-invoices.index') }}" class="menu-link {{ request()->routeIs('purchase-invoices.*') ? 'active' : '' }}">
                    <div data-i18n="Error">Purchase Invoices</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('purchase-invoices.create') }}" class="menu-link">
                    <div data-i18n="Error">New Purchase Invoice</div>
                  </a>
                </li>
              </ul>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-transfer-alt"></i>
                <div data-i18n="Stock Transfer">Stock Transfer</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('stock-transfers.index') }}" class="menu-link {{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}">
                    <div data-i18n="Error">Stock Transfers</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('stock-transfers.create') }}" class="menu-link">
                    <div data-i18n="Error">New Stock Transfer</div>
                  </a>
                </li>
              </ul>
            </li>

            <!-- Expenses -->
            <li class="menu-header small text-uppercase">
              <span class="menu-header-text">Expenses</span>
            </li>
            <li class="menu-item">
              <a href="{{ route('expenses.index') }}" class="menu-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                <i class="menu-icon tf-icons bx bx-wallet"></i>
                <div data-i18n="Expenses">Expenses</div>
              </a>
            </li>

            <!-- Reports -->
            <li class="menu-header small text-uppercase"><span class="menu-header-text">Reports</span></li>
            <li class="menu-item">
              <a href="{{ route('admin.cogs.index') }}" class="menu-link {{ request()->routeIs('admin.cogs.*') ? 'active' : '' }}">
                <i class="menu-icon tf-icons bx bx-chart"></i>
                <div data-i18n="COGS Report">COGS Report</div>
              </a>
            </li>
            <li class="menu-item">
              <a href="{{ route('admin.reports.expiration') }}" class="menu-link {{ request()->routeIs('admin.reports.expiration') ? 'active' : '' }}">
                <i class="menu-icon tf-icons bx bx-calendar-x"></i>
                <div data-i18n="Expiration Report">Expiration Report</div>
              </a>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-box"></i>
                <div data-i18n="Inventory Report">Inventory Report</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('admin.reports.inventory-summary') }}" class="menu-link {{ request()->routeIs('admin.reports.inventory-summary') ? 'active' : '' }}">
                    <div data-i18n="Error">Inventory Summary</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('admin.reports.product-history') }}" class="menu-link {{ request()->routeIs('admin.reports.product-history') ? 'active' : '' }}">
                    <div data-i18n="Error">Product History</div>
                  </a>
                </li>
              </ul>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-receipt"></i>
                <div data-i18n="Sales Report">Sales Report</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('admin.reports.sales-summary') }}" class="menu-link {{ request()->routeIs('admin.reports.sales-summary') ? 'active' : '' }}">
                    <div data-i18n="Error">Sales Summary</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('admin.reports.sales-per-customer') }}" class="menu-link {{ request()->routeIs('admin.reports.sales-per-customer') ? 'active' : '' }}">
                    <div data-i18n="Error">Sales Per Customer</div>
                  </a>
                </li>
              </ul>
            </li>
            <li class="menu-item">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-cart-alt"></i>
                <div data-i18n="Purchase Report">Purchase Report</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('admin.reports.purchase-summary') }}" class="menu-link {{ request()->routeIs('admin.reports.purchase-summary') ? 'active' : '' }}">
                    <div data-i18n="Error">Purchase Summary</div>
                  </a>
                </li>
                <li class="menu-item">
                  <a href="{{ route('admin.reports.purchases-per-supplier') }}" class="menu-link {{ request()->routeIs('admin.reports.purchases-per-supplier') ? 'active' : '' }}">
                    <div data-i18n="Error">Purchases Per Supplier</div>
                  </a>
                </li>
              </ul>
            </li>
            <li class="menu-item">
              <a href="{{ route('admin.reports.expense-summary') }}" class="menu-link {{ request()->routeIs('admin.reports.expense-summary') ? 'active' : '' }}">
                <i class="menu-icon tf-icons bx bx-wallet"></i>
                <div data-i18n="Expense Report">Expense Report</div>
              </a>
            </li>

            <!-- Users -->
            <li class="menu-header small text-uppercase"><span class="menu-header-text">Users</span></li>
            <!-- User interface -->
            <li class="menu-item">
              <a href="{{ route('users.index') }}" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-user"></i>
                <div data-i18n="User interface">Users</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item">
                  <a href="{{ route('users.index') }}" class="menu-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <div data-i18n="Accordion">Users</div>
                  </a>
                </li>
              </ul>
            </li>
          </ul>
        </aside>
        <!-- / Menu -->

        <!-- Layout container -->
        <div class="layout-page">
          <!-- Navbar -->
          <nav
            class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
            id="layout-navbar"
          >
            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
              <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                <i class="bx bx-menu bx-sm"></i>
              </a>
            </div>

            <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
              <!-- Search -->
              <div class="navbar-nav align-items-center" style="position: relative; flex: 1; max-width: 400px;">
                <div class="nav-item d-flex align-items-center position-relative" style="width: 100%;">
                  <i class="bx bx-search fs-4 lh-0" style="margin-right: 10px;"></i>
                  <input
                    type="text"
                    id="searchInput"
                    class="form-control border-0 shadow-none"
                    placeholder="Search items, sales, suppliers..."
                    aria-label="Search..."
                    autocomplete="off"
                  />
                  <!-- Search Results Dropdown -->
                  <div id="searchResults" class="search-results-dropdown" style="display: none;">
                    <div class="search-results-content"></div>
                  </div>
                </div>
              </div>
              <!-- /Search -->

              <ul class="navbar-nav flex-row align-items-center ms-auto">
                <!-- User -->
                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                  <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                      <img src="{{ Auth::user()->profile_picture_url }}" alt class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;" />
                    </div>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" href="#">
                        <div class="d-flex">
                          <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-online">
                              <img src="{{ Auth::user()->profile_picture_url }}" alt="alt" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;" />
                             
                            </div>
                          </div>
                          <div class="flex-grow-1">
                            {{-- dynamic user name --}}
                            <span class="fw-semibold d-block">{{ Auth::user()->name }}</span>
                            <small class="text-muted">{{ Auth::user()->role }}</small>
                          </div>
                        </div>
                      </a>
                    </li>
                    <li>
                      <div class="dropdown-divider"></div>
                    </li>
                    <li>
                      <a class="dropdown-item" href="{{ route('admin.profile.edit') }}">
                        <i class="bx bx-user me-2"></i>
                        <span class="align-middle">My Profile</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="{{ route('activity-logs.index') }}">
                        <i class="bx bx-cog me-2"></i>
                        <span class="align-middle">Activies Log</span>
                      </a>
                    </li>

                    <li>
                      <div class="dropdown-divider"></div>
                    </li>
                    <li>
                      <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit(); return confirm('Are you sure you want to log out?');">
                        <i class="bx bx-power-off me-2"></i>
                        <span class="align-middle">Log Out</span>
                      </a>
                      <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                      </form>
                    </li>
                  </ul>
                </li>
                <!--/ User -->
              </ul>
            </div>
          </nav>

          <!-- / Navbar -->

          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Content -->
            <div class="container-xxl flex-grow-1 container-p-y">
                @yield('content')
            </div>
            <!-- / Content -->

            <!-- Footer -->
            <footer class="content-footer footer bg-footer-theme">
              <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                <div class="mb-2 mb-md-0">
                  ©
                  <script>
                    document.write(new Date().getFullYear());
                  </script>
                  , Copyright by <a href="#" class="footer-link fw-bolder">SAIMS</a>
                </div>
              </div>
            </footer>
            <!-- / Footer -->

            <div class="content-backdrop fade"></div>
          </div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    @include('sweetalert::alert')

    {{-- yield scripts --}}
    @yield('scripts')
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    
    <!-- Global Search Script -->
    <script>
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');
        const searchResultsContent = searchResults.querySelector('.search-results-content');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();

                if (query.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }

                // Debounce the search
                searchTimeout = setTimeout(() => {
                    performSearch(query);
                }, 300);
            });

            // Close search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.nav-item.d-flex.align-items-center.position-relative')) {
                    searchResults.style.display = 'none';
                }
            });

            // Prevent closing when clicking inside the results
            searchResults.addEventListener('click', function(e) {
                if (e.target.closest('.search-result-item')) {
                    const link = e.target.closest('.search-result-item');
                    window.location.href = link.href;
                }
            });
        }

        function performSearch(query) {
            fetch('{{ route("api.search") }}?q=' + encodeURIComponent(query))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Search failed with status ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    displaySearchResults(data);
                })
                .catch(error => {
                    console.error('Search error:', error);
                    searchResultsContent.innerHTML = '<div class="search-no-results">Search error occurred</div>';
                    searchResults.style.display = 'block';
                });
        }

        function displaySearchResults(data) {
            if (data.total === 0) {
                searchResultsContent.innerHTML = '<div class="search-no-results">No results found</div>';
                searchResults.style.display = 'block';
                return;
            }

            let html = '';

            // Items Section
            if (data.results.items.length > 0) {
                html += '<div class="search-results-section-header">Items</div>';
                data.results.items.forEach(item => {
                    html += createResultItemHTML(item);
                });
            }

            // Purchases Section
            if (data.results.purchases.length > 0) {
                html += '<div class="search-results-section-header">Sales</div>';
                data.results.purchases.forEach(purchase => {
                    html += createResultItemHTML(purchase);
                });
            }

            // Suppliers Section
            if (data.results.suppliers.length > 0) {
                html += '<div class="search-results-section-header">Suppliers</div>';
                data.results.suppliers.forEach(supplier => {
                    html += createResultItemHTML(supplier);
                });
            }

            // Categories Section
            if (data.results.categories.length > 0) {
                html += '<div class="search-results-section-header">Categories</div>';
                data.results.categories.forEach(category => {
                    html += createResultItemHTML(category);
                });
            }

            searchResultsContent.innerHTML = html;
            searchResults.style.display = 'block';
        }

        function createResultItemHTML(item) {
            return `
                <a href="${item.url}" class="search-result-item">
                    <i class="bx ${item.icon}"></i>
                    <div class="search-result-content">
                        <div class="search-result-title">${escapeHtml(item.title)}</div>
                        <div class="search-result-subtitle">${escapeHtml(item.subtitle)}</div>
                    </div>
                </a>
            `;
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    </script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>
</html>