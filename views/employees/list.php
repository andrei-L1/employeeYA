<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Only managers/HR can access this page
if (!hasRole('Manager') && !hasRole('HR')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get departments for filter
$stmt = $conn->prepare("SELECT * FROM departments WHERE deleted_at IS NULL ORDER BY department_name");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get positions for filter
$stmt = $conn->prepare("SELECT * FROM positions WHERE deleted_at IS NULL ORDER BY position_name");
$stmt->execute();
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build query based on filters
$where = ["e.deleted_at IS NULL"];
$params = [];

if (hasRole('Manager')) {
    $where[] = "e.department_id = ?";
    $params[] = $_SESSION['user_data']['employee_data']['department_id'];
}

if (isset($_GET['department']) && !empty($_GET['department'])) {
    $where[] = "e.department_id = ?";
    $params[] = $_GET['department'];
}

if (isset($_GET['position']) && !empty($_GET['position'])) {
    $where[] = "e.position_id = ?";
    $params[] = $_GET['position'];
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where[] = "e.employment_status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
    $search = "%{$_GET['search']}%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get employees
$stmt = $conn->prepare("
    SELECT e.*, d.department_name, p.position_name,
           CONCAT(e.first_name, ' ', e.last_name) as full_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
    $whereClause
    ORDER BY e.last_name, e.first_name
");
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --card-bg: #fff;
            --card-shadow: 0 4px 24px 0 rgba(34, 41, 47, 0.08);
            --border-radius: 1.25rem;
            --avatar-bg: linear-gradient(135deg, #e3eafe 0%, #f8fafc 100%);
        }

        body {
            background-color: #f4f6fb;
            font-family: 'Inter', sans-serif;
        }

        .main-content {
            padding: 2rem 0.5rem;
            transition: margin-left 0.3s;
        }

        .page-header {
            background: var(--card-bg);
            padding: 2rem 2.5rem 1.5rem 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }
        .page-header h2 {
            font-weight: 700;
            font-size: 2rem;
        }
        .page-header p {
            font-size: 1rem;
        }

        .filter-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            position: sticky;
            top: 1rem;
            z-index: 10;
        }
        .filter-card .card-body {
            padding: 1.5rem 2rem;
        }
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
        }
        .form-select, .form-control {
            border-radius: 0.75rem;
            font-size: 1rem;
        }
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.2s;
            font-size: 1rem;
        }
        .btn-action:hover, .btn-action:focus {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 2px 8px 0 rgba(78, 115, 223, 0.15);
        }

        .employee-card {
            transition: box-shadow 0.3s, transform 0.3s;
            border: none;
            box-shadow: var(--card-shadow);
            border-radius: var(--border-radius);
            overflow: hidden;
            background: var(--card-bg);
            margin-bottom: 2rem;
        }
        .employee-card:hover {
            transform: translateY(-6px) scale(1.01);
            box-shadow: 0 8px 32px 0 rgba(34, 41, 47, 0.12);
        }
        .employee-avatar, .default-avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.10);
            margin: 1.5rem auto 1rem auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--avatar-bg);
            font-size: 2.5rem;
            color: var(--primary-color);
        }
        .employee-avatar {
            object-fit: cover;
            background: #fff;
        }
        .default-avatar i {
            font-size: 2.5rem;
        }
        .card-title {
            font-weight: 600;
            font-size: 1.25rem;
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 0.5em 1.2em;
            font-weight: 700;
            border-radius: 2rem;
            letter-spacing: 0.03em;
        }
        .employee-info {
            margin: 1rem 0 0.5rem 0;
        }
        .employee-info p {
            margin-bottom: 0.25rem;
            color: var(--secondary-color);
            font-size: 0.98rem;
        }
        .employee-info i {
            width: 1.5rem;
            color: var(--primary-color);
        }
        .action-group {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
        }
        .action-group .btn {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: #f4f6fb;
            color: var(--primary-color);
            border: none;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .action-group .btn:hover {
            background: var(--primary-color);
            color: #fff;
            box-shadow: 0 2px 8px 0 rgba(78, 115, 223, 0.15);
        }
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-top: 2rem;
        }
        .empty-state i {
            font-size: 5rem;
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
        }
        .empty-state h3 {
            font-weight: 700;
            font-size: 2rem;
        }
        .empty-state p {
            font-size: 1.1rem;
        }
        @media (max-width: 767px) {
            .main-content {
                padding: 1rem 0.2rem;
            }
            .employee-card {
                margin-bottom: 1.2rem;
            }
            .page-header, .filter-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">Employee Management</h2>
                    <p class="text-muted mb-0">Manage and view all employees in the system</p>
                </div>
                <?php if (hasRole('HR')): ?>
                <a href="add.php" class="btn btn-primary btn-action">
                    <i class="fas fa-user-plus me-2"></i> Add New Employee
                </a>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <div class="filter-card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                                       placeholder="Search by name or ID">
                            </div>
                        </div>
                        
                        <?php if (hasRole('HR')): ?>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>" 
                                        <?= (isset($_GET['department']) && $_GET['department'] == $dept['department_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Position</label>
                            <select class="form-select" name="position">
                                <option value="">All Positions</option>
                                <?php foreach ($positions as $pos): ?>
                                <option value="<?= $pos['position_id'] ?>"
                                        <?= (isset($_GET['position']) && $_GET['position'] == $pos['position_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pos['position_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="Regular" <?= (isset($_GET['status']) && $_GET['status'] === 'Regular') ? 'selected' : '' ?>>Regular</option>
                                <option value="Probationary" <?= (isset($_GET['status']) && $_GET['status'] === 'Probationary') ? 'selected' : '' ?>>Probationary</option>
                                <option value="Contractual" <?= (isset($_GET['status']) && $_GET['status'] === 'Contractual') ? 'selected' : '' ?>>Contractual</option>
                            </select>
                        </div>
                        
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 btn-action">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Loading Overlay -->
            <div class="loading-overlay">
                <div class="spinner-border text-primary spinner" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <!-- Employee List -->
            <div class="row">
                <?php if (empty($employees)): ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No Employees Found</h3>
                        <p class="text-muted">Try adjusting your search or filter criteria</p>
                        <?php if (hasRole('HR')): ?>
                        <a href="add.php" class="btn btn-primary mt-3">
                            <i class="fas fa-user-plus me-2"></i> Add New Employee
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($employees as $employee): ?>
                <div class="col-md-4 mb-4">
                    <div class="card employee-card h-100">
                        <div class="card-body text-center">
                            <?php
                            $profilePic = $employee['profile_picture'] ?? '';
                            if (!$profilePic || !file_exists('../../' . $profilePic)) {
                            ?>
                                <div class="default-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php
                            } else {
                            ?>
                                <img src="<?= htmlspecialchars($profilePic) ?>" 
                                     alt="Profile Picture" 
                                     class="employee-avatar">
                            <?php } ?>
                            
                            <h5 class="card-title mb-1"><?= htmlspecialchars($employee['full_name']) ?></h5>
                            <p class="text-muted mb-2"><?= htmlspecialchars($employee['position_name']) ?></p>
                            
                            <div class="mb-3">
                                <span class="badge bg-<?= 
                                    $employee['employment_status'] === 'Regular' ? 'success' : 
                                    ($employee['employment_status'] === 'Probationary' ? 'warning' : 
                                    ($employee['employment_status'] === 'Contractual' ? 'info' : 'secondary')) 
                                ?> status-badge">
                                    <?= htmlspecialchars($employee['employment_status']) ?>
                                </span>
                            </div>

                            <div class="employee-info">
                                <p><i class="fas fa-building me-2"></i> <?= htmlspecialchars($employee['department_name']) ?></p>
                                <p><i class="fas fa-id-card me-2"></i> <?= htmlspecialchars($employee['employee_id']) ?></p>
                            </div>
                            
                            <div class="action-group">
                                <a href="view.php?id=<?= $employee['employee_id'] ?>" 
                                   class="btn" title="View Profile" data-bs-toggle="tooltip">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasRole('HR')): ?>
                                <a href="edit.php?id=<?= $employee['employee_id'] ?>" 
                                   class="btn" title="Edit" data-bs-toggle="tooltip">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn delete-employee" 
                                        data-id="<?= $employee['employee_id'] ?>"
                                        data-name="<?= htmlspecialchars($employee['full_name']) ?>"
                                        title="Delete" data-bs-toggle="tooltip">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        // Show loading overlay
        function showLoading() {
            document.querySelector('.loading-overlay').style.display = 'flex';
        }

        // Hide loading overlay
        function hideLoading() {
            document.querySelector('.loading-overlay').style.display = 'none';
        }

        // Handle form submission
        document.querySelector('form').addEventListener('submit', () => {
            showLoading();
        });

        // Handle employee deletion
        document.querySelectorAll('.delete-employee').forEach(button => {
            button.addEventListener('click', async () => {
                const employeeId = button.dataset.id;
                const employeeName = button.dataset.name;
                
                const result = await Swal.fire({
                    title: 'Delete Employee',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle text-warning mb-3" style="font-size: 3rem;"></i>
                            <p>Are you sure you want to delete <strong>${employeeName}</strong>?</p>
                            <p class="text-danger">This action cannot be undone.</p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74a3b',
                    cancelButtonColor: '#858796',
                    confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, delete',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                    reverseButtons: true
                });

                if (result.isConfirmed) {
                    showLoading();
                    try {
                        const response = await fetch('../../api/employees/delete.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                            },
                            body: JSON.stringify({ employee_id: employeeId })
                        });
                        
                        const result = await response.json();
                        if (result.success) {
                            await Swal.fire({
                                title: 'Success!',
                                text: 'Employee has been deleted successfully.',
                                icon: 'success',
                                confirmButtonColor: '#1cc88a'
                            });
                            window.location.reload();
                        } else {
                            throw new Error(result.error);
                        }
                    } catch (error) {
                        hideLoading();
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Failed to delete employee',
                            icon: 'error',
                            confirmButtonColor: '#e74a3b'
                        });
                    }
                }
            });
        });

        // Hide loading overlay when page is fully loaded
        window.addEventListener('load', hideLoading);

        // Enable Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
