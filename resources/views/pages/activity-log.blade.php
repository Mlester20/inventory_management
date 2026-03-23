@extends('layout.user')

@section('title', 'Activity Log')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">Activity Log</h5>
                    <button class="btn btn-sm btn-primary" onclick="location.reload()">
                        <i class="bx bx-refresh me-1"></i>Refresh
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="activityLogTableBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bx bx-loader bx-spin fs-1"></i>
                                    <p>Loading activity log...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="paginationInfo">Loading...</small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="paginationLinks">
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script>
        let currentPage = 1;

        // Fetch activity log data of specific user from API and populate the table
        document.addEventListener('DOMContentLoaded', function() {
            fetchActivityLog(currentPage);
        });

        function fetchActivityLog(page = 1) {
            fetch(`/api/activity-log?page=${page}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateTable(data.data);
                    updatePagination(data.pagination);
                } else {
                    showError('Failed to fetch activity logs');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('An error occurred while fetching activity logs');
            });
        }

        function populateTable(activities) {
            const tableBody = document.getElementById('activityLogTableBody');
            
            if (activities.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="bx bx-inbox fs-1"></i>
                            <p>No activity logs found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tableBody.innerHTML = activities.map((activity, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <span class="badge bg-primary">${escapeHtml(activity.action)}</span>
                    </td>
                    <td>${escapeHtml(activity.description || 'N/A')}</td>
                    <td><small class="text-muted">${escapeHtml(activity.ip_address || 'N/A')}</small></td>
                    <td>
                        <span class="text-muted">${formatDate(activity.created_at)}</span>
                    </td>
                </tr>
            `).join('');
        }

        function updatePagination(pagination) {
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationLinks = document.getElementById('paginationLinks');
            
            // Update info
            paginationInfo.textContent = `Showing ${(pagination.current_page - 1) * pagination.per_page + 1} to ${Math.min(pagination.current_page * pagination.per_page, pagination.total)} of ${pagination.total} entries`;
            
            // Clear existing links
            paginationLinks.innerHTML = '';
            
            // Previous button
            if (pagination.current_page > 1) {
                paginationLinks.innerHTML += `
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="goToPage(${pagination.current_page - 1})">Previous</a>
                    </li>
                `;
            }
            
            // Page numbers
            for (let i = 1; i <= pagination.last_page; i++) {
                if (i === pagination.current_page) {
                    paginationLinks.innerHTML += `
                        <li class="page-item active">
                            <span class="page-link">${i}</span>
                        </li>
                    `;
                } else if (i <= 3 || i >= pagination.last_page - 2 || (i >= pagination.current_page - 1 && i <= pagination.current_page + 1)) {
                    paginationLinks.innerHTML += `
                        <li class="page-item">
                            <a class="page-link" href="javascript:void(0)" onclick="goToPage(${i})">${i}</a>
                        </li>
                    `;
                } else if (i === 4 || i === pagination.last_page - 3) {
                    paginationLinks.innerHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            
            // Next button
            if (pagination.current_page < pagination.last_page) {
                paginationLinks.innerHTML += `
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="goToPage(${pagination.current_page + 1})">Next</a>
                    </li>
                `;
            }
        }

        function goToPage(page) {
            currentPage = page;
            fetchActivityLog(page);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showError(message) {
            const tableBody = document.getElementById('activityLogTableBody');
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-danger py-4">
                        <i class="bx bx-error-circle fs-1"></i>
                        <p>${escapeHtml(message)}</p>
                    </td>
                </tr>
            `;
        }
    </script>
@endsection
