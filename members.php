<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

// Only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Get all users with statistics
$stmt = $pdo->prepare("
    SELECT 
        u.*,
        COUNT(t.id) as total_tasks,
        SUM(CASE WHEN t.status IN ('Done', 'Approved') THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN t.status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN t.status = 'On Progress' THEN 1 ELSE 0 END) as active_tasks,
        SUM(CASE WHEN t.status = 'On Hold' THEN 1 ELSE 0 END) as on_hold_tasks
    FROM users u
    LEFT JOIN tasks t ON u.id = t.assigned_to
    WHERE u.is_active = TRUE
    GROUP BY u.id
    ORDER BY u.role DESC, u.name ASC
");
$stmt->execute();
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Team Members - Daily Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#8b5cf6',
                        accent: '#06b6d4',
                        dark: '#1e293b',
                    },
                    animation: {
                        'slide-up': 'slideUp 0.3s ease-out',
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'bounce-in': 'bounceIn 0.6s ease-out',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes bounceIn {
            0%, 20%, 40%, 60%, 80% { transform: translateY(0); opacity: 0; }
            50% { transform: translateY(-10px); opacity: 0.8; }
            100% { transform: translateY(0); opacity: 1; }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .mobile-card {
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                border: 1px solid rgba(0,0,0,0.04);
            }
            .mobile-compact {
                padding: 12px !important;
                margin: 8px !important;
            }
            .mobile-text-sm { font-size: 0.875rem !important; }
            .mobile-icon-sm { width: 16px !important; height: 16px !important; }
        }
        
        /* Desktop enhancements */
        @media (min-width: 1024px) {
            .desktop-glass {
                background: rgba(255, 255, 255, 0.8);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            .desktop-shadow {
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            }
            .hover-scale:hover {
                transform: scale(1.02);
                transition: transform 0.2s ease;
            }
        }
        
        /* Loading states */
        .loading {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen lg:bg-gradient-to-br lg:from-indigo-50 lg:via-white lg:to-cyan-50">
    <!-- Mobile Header -->
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-gray-200/50 lg:bg-white/80 lg:border-gray-100">
        <div class="px-3 lg:px-8 max-w-7xl mx-auto">
            <!-- Mobile Layout -->
            <div class="flex items-center justify-between py-3 lg:hidden">
                <div class="flex items-center gap-3">
                    <a href="admin-dashboard.php" class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                        <i class="fas fa-arrow-left text-sm"></i>
                    </a>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">Team</h1>
                        <p class="text-xs text-gray-500"><?= count($users) ?> members</p>
                    </div>
                </div>
                <button onclick="openAddMemberModal()" class="flex items-center justify-center w-10 h-10 bg-primary rounded-xl text-white shadow-lg hover:shadow-xl transition-all">
                    <i class="fas fa-plus text-sm"></i>
                </button>
            </div>
            
            <!-- Desktop Layout -->
            <div class="hidden lg:flex items-center justify-between py-6">
                <div class="flex items-center gap-6">
                    <a href="admin-dashboard.php" class="flex items-center gap-2 text-gray-600 hover:text-primary transition-colors group">
                        <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    <div class="h-6 w-px bg-gray-300"></div>
                    <div>
                        <h1 class="text-3xl font-bold bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">Team Members</h1>
                        <p class="text-gray-600 mt-1">Manage your team and user accounts</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 px-4 py-2 bg-gray-50 rounded-xl">
                        <i class="fas fa-users text-gray-400"></i>
                        <span class="text-sm font-medium text-gray-700"><?= count($users) ?> Active Members</span>
                    </div>
                    <button onclick="openAddMemberModal()" class="bg-gradient-to-r from-primary to-secondary hover:from-primary hover:to-primary text-white px-6 py-3 rounded-xl flex items-center gap-3 font-medium shadow-lg hover:shadow-xl transition-all hover-scale">
                        <i class="fas fa-plus"></i>
                        <span>Add Member</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="px-3 py-4 lg:px-8 lg:py-8 max-w-7xl mx-auto">
        <!-- Mobile Search Bar -->
        <div class="lg:hidden mb-4">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="searchInput" placeholder="Search members..." 
                       class="w-full pl-10 pr-4 py-3 bg-white rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary mobile-text-sm">
            </div>
            <div class="flex gap-2 mt-3">
                <select id="roleFilter" class="flex-1 px-3 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="user">User</option>
                </select>
                <select id="departmentFilter" class="flex-1 px-3 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm">
                    <option value="">All Depts</option>
                    <option value="IT">IT</option>
                    <option value="Development">Dev</option>
                    <option value="Design">Design</option>
                    <option value="Marketing">Marketing</option>
                    <option value="HR">HR</option>
                    <option value="Sales">Sales</option>
                </select>
            </div>
        </div>

        <!-- Desktop Search and Filters -->
        <div class="hidden lg:block mb-8">
            <div class="desktop-glass rounded-2xl p-6 desktop-shadow">
                <div class="flex items-center gap-6">
                    <div class="flex-1 relative">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="searchInputDesktop" placeholder="Search members by name, email, or department..." 
                               class="w-full pl-12 pr-4 py-4 bg-white/50 backdrop-blur-sm rounded-xl border border-white/20 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/30 focus:bg-white/80 transition-all">
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700">Role:</label>
                            <select id="roleFilterDesktop" class="px-4 py-3 bg-white/50 backdrop-blur-sm border border-white/20 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white/80 transition-all">
                                <option value="">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-gray-700">Department:</label>
                            <select id="departmentFilterDesktop" class="px-4 py-3 bg-white/50 backdrop-blur-sm border border-white/20 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white/80 transition-all">
                                <option value="">All Departments</option>
                                <option value="IT">IT</option>
                                <option value="Development">Development</option>
                                <option value="Design">Design</option>
                                <option value="Marketing">Marketing</option>
                                <option value="HR">HR</option>
                                <option value="Sales">Sales</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="flex items-center gap-6 mt-6 pt-6 border-t border-white/20">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 bg-purple-500 rounded-full"></div>
                        <span class="text-sm text-gray-600">Admins: <?= count(array_filter($users, fn($u) => $u['role'] === 'admin')) ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                        <span class="text-sm text-gray-600">Users: <?= count(array_filter($users, fn($u) => $u['role'] === 'user')) ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                        <span class="text-sm text-gray-600">Total Tasks: <?= array_sum(array_column($users, 'total_tasks')) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Members List -->
        <div id="membersGrid" class="lg:hidden space-y-3">
            <?php foreach ($users as $user): ?>
                <div class="member-card mobile-card bg-white rounded-xl mobile-compact border border-gray-100 hover:border-primary/20 transition-all animate-slide-up" 
                     data-user-id="<?= $user['id'] ?>"
                     data-name="<?= strtolower($user['name']) ?>"
                     data-email="<?= strtolower($user['email']) ?>"
                     data-role="<?= $user['role'] ?>"
                     data-department="<?= strtolower($user['department'] ?? '') ?>">
                    
                    <!-- Mobile Card Content -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-10 h-10 bg-gradient-to-br from-primary to-secondary rounded-xl flex items-center justify-center shadow-lg">
                                <span class="text-white font-bold text-sm"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 text-sm truncate"><?= htmlspecialchars($user['name']) ?></h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-2 py-0.5 text-xs rounded-md font-medium <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700' ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                    <?php if ($user['department']): ?>
                                        <span class="text-xs text-gray-500"><?= htmlspecialchars($user['department']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mobile Action Buttons -->
                        <div class="flex gap-1">
                            <button onclick="viewMemberDetails(<?= $user['id'] ?>)" 
                                    class="bg-blue-50 hover:bg-blue-100 text-blue-600 p-2 rounded-lg text-xs transition-colors">
                                <i class="fas fa-user text-xs"></i>
                            </button>
                            <button onclick="viewMemberTasks(<?= $user['id'] ?>)" 
                                    class="bg-green-50 hover:bg-green-100 text-green-600 p-2 rounded-lg text-xs transition-colors">
                                <i class="fas fa-tasks text-xs"></i>
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button onclick="confirmDeleteMember(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" 
                                        class="bg-red-50 hover:bg-red-100 text-red-600 p-2 rounded-lg text-xs transition-colors">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Desktop Members Grid -->
        <div id="membersGridDesktop" class="hidden lg:grid grid-cols-1 xl:grid-cols-2 2xl:grid-cols-4 gap-6">
            <?php foreach ($users as $user): ?>
                <div class="member-card desktop-glass rounded-2xl p-5 desktop-shadow hover-scale transition-all duration-300 animate-fade-in border border-white/20" 
                     data-user-id="<?= $user['id'] ?>"
                     data-name="<?= strtolower($user['name']) ?>"
                     data-email="<?= strtolower($user['email']) ?>"
                     data-role="<?= $user['role'] ?>"
                     data-department="<?= strtolower($user['department'] ?? '') ?>">
                    
                    <!-- Desktop Card Content -->
                    <div class="text-center">
                        <!-- Avatar with Status -->
                        <div class="relative mx-auto mb-4">
                            <div class="w-16 h-16 bg-gradient-to-br from-primary to-secondary rounded-2xl flex items-center justify-center shadow-lg mx-auto">
                                <span class="text-white font-bold text-xl"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                            </div>
                            <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-white rounded-full flex items-center justify-center shadow-md">
                                <div class="w-3 h-3 <?= $user['last_login'] && strtotime($user['last_login']) > strtotime('-1 hour') ? 'bg-green-500' : 'bg-gray-400' ?> rounded-full"></div>
                            </div>
                        </div>

                        <!-- Basic Info -->
                        <h3 class="font-bold text-gray-900 mb-1 truncate"><?= htmlspecialchars($user['name']) ?></h3>
                        <p class="text-sm text-gray-600 mb-3 truncate"><?= htmlspecialchars($user['email']) ?></p>
                        
                        <!-- Role & Department -->
                        <div class="space-y-2 mb-4">
                            <span class="inline-flex px-3 py-1 text-sm rounded-full font-medium <?= $user['role'] === 'admin' ? 'bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800' : 'bg-gradient-to-r from-green-100 to-green-200 text-green-800' ?>">
                                <i class="<?= $user['role'] === 'admin' ? 'fas fa-crown' : 'fas fa-user' ?> mr-2 mt-0.5 text-xs"></i>
                                <?= ucfirst($user['role']) ?>
                            </span>
                            <?php if ($user['department']): ?>
                                <div class="text-sm text-gray-600">
                                    <i class="fas fa-building text-xs mr-1"></i>
                                    <?= htmlspecialchars($user['department']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Stats -->
                        <div class="bg-gradient-to-r from-gray-50 to-blue-50 rounded-lg p-3 mb-4">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <div class="text-lg font-bold text-primary"><?= $user['total_tasks'] ?></div>
                                    <div class="text-xs text-gray-600">Total</div>
                                </div>
                                <div>
                                    <div class="text-lg font-bold text-green-600"><?= $user['completed_tasks'] ?></div>
                                    <div class="text-xs text-gray-600">Done</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Actions -->
                    <div class="space-y-2">
                        <button onclick="viewMemberDetails(<?= $user['id'] ?>)" 
                                class="w-full bg-blue-50 hover:bg-blue-100 text-blue-700 px-3 py-2 rounded-lg font-medium transition-all text-sm">
                            <i class="fas fa-user mr-2 text-xs"></i>
                            View Details
                        </button>
                        <button onclick="viewMemberTasks(<?= $user['id'] ?>)" 
                                class="w-full bg-green-50 hover:bg-green-100 text-green-700 px-3 py-2 rounded-lg font-medium transition-all text-sm">
                            <i class="fas fa-tasks mr-2 text-xs"></i>
                            View Tasks
                        </button>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button onclick="confirmDeleteMember(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" 
                                    class="w-full bg-red-50 hover:bg-red-100 text-red-700 px-3 py-2 rounded-lg transition-all text-sm">
                                <i class="fas fa-trash mr-2 text-xs"></i>
                                Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- No Results Message -->
        <div id="noResults" class="hidden text-center py-12">
            <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No members found</h3>
            <p class="text-gray-600">Try adjusting your search or filter criteria</p>
        </div>
    </main>

    <!-- Add Member Modal -->
    <div id="addMemberModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 transform transition-all duration-300 scale-95">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-900">Add New Member</h2>
                <button onclick="closeAddMemberModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="addMemberForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Department</option>
                        <option value="IT">IT</option>
                        <option value="Development">Development</option>
                        <option value="Design">Design</option>
                        <option value="Marketing">Marketing</option>
                        <option value="HR">HR</option>
                        <option value="Sales">Sales</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone (Optional)</label>
                    <input type="tel" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeAddMemberModal()" 
                            class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                        Add Member
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Delete Member</h2>
                <p class="text-gray-600 mb-4">Choose how to delete <strong id="deleteMemberName"></strong>:</p>
                
                <!-- Delete Type Options -->
                <div class="text-left space-y-3 mb-4">
                    <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                        <input type="radio" name="deleteType" value="soft" checked class="mt-1">
                        <div>
                            <div class="font-medium text-gray-900">Deactivate (Recommended)</div>
                            <div class="text-sm text-gray-600">User disappears from interface but data is preserved. Can be restored later.</div>
                        </div>
                    </label>
                    
                    <label class="flex items-start gap-3 p-3 border border-red-200 rounded-lg hover:bg-red-50 cursor-pointer">
                        <input type="radio" name="deleteType" value="hard" class="mt-1">
                        <div>
                            <div class="font-medium text-red-900">Permanently Delete</div>
                            <div class="text-sm text-red-600">⚠️ User is completely removed from database. Cannot be undone!</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" 
                        class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                    Cancel
                </button>
                <button onclick="deleteMember()" 
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">
                    Delete User
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentDeleteUserId = null;

        // Search and filter functionality for both mobile and desktop
        function setupSearchAndFilter() {
            // Mobile search
            const mobileSearch = document.getElementById('searchInput');
            const mobileRole = document.getElementById('roleFilter');
            const mobileDept = document.getElementById('departmentFilter');
            
            // Desktop search
            const desktopSearch = document.getElementById('searchInputDesktop');
            const desktopRole = document.getElementById('roleFilterDesktop');
            const desktopDept = document.getElementById('departmentFilterDesktop');
            
            if (mobileSearch) {
                mobileSearch.addEventListener('input', filterMembers);
                mobileRole.addEventListener('change', filterMembers);
                mobileDept.addEventListener('change', filterMembers);
            }
            
            if (desktopSearch) {
                desktopSearch.addEventListener('input', filterMembersDesktop);
                desktopRole.addEventListener('change', filterMembersDesktop);
                desktopDept.addEventListener('change', filterMembersDesktop);
            }
        }

        function filterMembers() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const departmentFilter = document.getElementById('departmentFilter').value.toLowerCase();
            
            const mobileGrid = document.getElementById('membersGrid');
            const cards = mobileGrid.querySelectorAll('.member-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const name = card.dataset.name;
                const email = card.dataset.email;
                const role = card.dataset.role;
                const department = card.dataset.department;
                
                const matchesSearch = !search || name.includes(search) || email.includes(search);
                const matchesRole = !roleFilter || role === roleFilter;
                const matchesDepartment = !departmentFilter || department.includes(departmentFilter);
                
                if (matchesSearch && matchesRole && matchesDepartment) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            const noResults = document.getElementById('noResults');
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }

        function filterMembersDesktop() {
            const search = document.getElementById('searchInputDesktop').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilterDesktop').value;
            const departmentFilter = document.getElementById('departmentFilterDesktop').value.toLowerCase();
            
            const desktopGrid = document.getElementById('membersGridDesktop');
            const cards = desktopGrid.querySelectorAll('.member-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const name = card.dataset.name;
                const email = card.dataset.email;
                const role = card.dataset.role;
                const department = card.dataset.department;
                
                const matchesSearch = !search || name.includes(search) || email.includes(search) || department.includes(search);
                const matchesRole = !roleFilter || role === roleFilter;
                const matchesDepartment = !departmentFilter || department.includes(departmentFilter);
                
                if (matchesSearch && matchesRole && matchesDepartment) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            const noResults = document.getElementById('noResults');
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            setupSearchAndFilter();
        });

        // Add Member Modal
        function openAddMemberModal() {
            document.getElementById('addMemberModal').classList.remove('hidden');
            setTimeout(() => {
                document.querySelector('#addMemberModal .transform').classList.remove('scale-95');
                document.querySelector('#addMemberModal .transform').classList.add('scale-100');
            }, 10);
        }

        function closeAddMemberModal() {
            const modal = document.getElementById('addMemberModal');
            const content = document.querySelector('#addMemberModal .transform');
            
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                document.getElementById('addMemberForm').reset();
            }, 300);
        }

        // Add Member Form
        document.getElementById('addMemberForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Adding...';
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            console.log('Sending data:', data); // Debug log
            
            fetch('./api/users-fixed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_user',
                    ...data
                })
            })
            .then(response => {
                console.log('Response status:', response.status); // Debug log
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                return response.text(); // Get as text first to handle potential parsing issues
            })
            .then(text => {
                console.log('Response text:', text); // Debug log
                
                try {
                    const data = JSON.parse(text);
                    
                    if (data.success) {
                        showSuccessMessage('Member added successfully!');
                        closeAddMemberModal();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Raw response:', text);
                    alert('Server response error. Check console for details.');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('Network error: ' + error.message + '. Please check your connection and try again.');
            })
            .finally(() => {
                // Reset button
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });

        // Delete functions
        function confirmDeleteMember(userId, userName) {
            currentDeleteUserId = userId;
            document.getElementById('deleteMemberName').textContent = userName;
            
            // Reset delete type to soft delete (recommended)
            const softDeleteRadio = document.querySelector('input[name="deleteType"][value="soft"]');
            if (softDeleteRadio) {
                softDeleteRadio.checked = true;
            }
            
            // Reset button state
            const deleteBtn = document.querySelector('#deleteModal button[onclick="deleteMember()"]');
            deleteBtn.textContent = 'Delete User';
            deleteBtn.disabled = false;
            
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            currentDeleteUserId = null;
        }

        function deleteMember() {
            if (!currentDeleteUserId) return;
            
            // Get selected delete type
            const deleteType = document.querySelector('input[name="deleteType"]:checked')?.value || 'soft';
            
            // Update button to show loading
            const deleteBtn = document.querySelector('#deleteModal button[onclick="deleteMember()"]');
            const originalText = deleteBtn.textContent;
            deleteBtn.textContent = 'Deleting...';
            deleteBtn.disabled = true;
            
            fetch('./api/users-fixed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_user',
                    user_id: currentDeleteUserId,
                    delete_type: deleteType
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        const deleteType = data.delete_type || 'soft';
                        const message = deleteType === 'hard' ? 'Member permanently deleted from database!' : 'Member deactivated successfully!';
                        showSuccessMessage(message);
                        closeDeleteModal();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Raw response:', text);
                    alert('Server response error. Check console for details.');
                }
            })
            .catch(error => {
                console.error('Delete Error:', error);
                alert('Network error: ' + error.message + '. Please try again.');
            })
            .finally(() => {
                // Reset button state
                deleteBtn.textContent = originalText;
                deleteBtn.disabled = false;
            });
        }

        // View member details
        function viewMemberDetails(userId) {
            fetch(`./api/users-fixed.php?action=get_user_profile&user_id=${userId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            showMemberDetailsModal(data.user);
                        } else {
                            alert('Error: ' + (data.message || 'Unknown error'));
                        }
                    } catch (parseError) {
                        console.error('JSON Parse Error:', parseError);
                        console.error('Raw response:', text);
                        alert('Server response error. Check console for details.');
                    }
                })
                .catch(error => {
                    console.error('View Details Error:', error);
                    alert('Network error: ' + error.message + '. Please try again.');
                });
        }

        // View member tasks
        function viewMemberTasks(userId) {
            // Redirect to admin dashboard with user filter for tasks
            window.location.href = `admin-dashboard.php?view=tasks&user_id=${userId}`;
        }

        // Show member details modal
        function showMemberDetailsModal(user) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 transform transition-all duration-300 scale-95" id="memberDetailsContent">
                    <div class="text-center mb-6">
                        <div class="w-20 h-20 bg-gradient-to-br from-primary to-secondary rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <span class="text-white font-bold text-2xl">${user.name.charAt(0).toUpperCase()}</span>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 mb-1">${user.name}</h2>
                        <p class="text-gray-600 mb-3">${user.email}</p>
                        <span class="inline-flex px-3 py-1 text-sm rounded-full font-medium ${user.role === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700'}">
                            <i class="${user.role === 'admin' ? 'fas fa-crown' : 'fas fa-user'} mr-2 mt-0.5"></i>
                            ${user.role.charAt(0).toUpperCase() + user.role.slice(1)}
                        </span>
                    </div>
                    
                    <div class="space-y-4 mb-6">
                        ${user.department ? `
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-building text-blue-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Department</div>
                                    <div class="font-medium text-gray-900">${user.department}</div>
                                </div>
                            </div>
                        ` : ''}
                        
                        ${user.phone ? `
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-phone text-green-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Phone</div>
                                    <div class="font-medium text-gray-900">${user.phone}</div>
                                </div>
                            </div>
                        ` : ''}
                        
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar text-gray-600"></i>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">Member Since</div>
                                <div class="font-medium text-gray-900">${new Date(user.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                            </div>
                        </div>
                        
                        ${user.last_login ? `
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-clock text-purple-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Last Active</div>
                                    <div class="font-medium text-gray-900">${new Date(user.last_login).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="flex gap-3">
                        <button onclick="viewMemberTasks(${user.id})" class="flex-1 bg-green-50 hover:bg-green-100 text-green-700 px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-tasks mr-2"></i>
                            View Tasks
                        </button>
                        <button onclick="closeMemberDetailsModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Animate modal appearance
            setTimeout(() => {
                const content = document.getElementById('memberDetailsContent');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeMemberDetailsModal();
                }
            });
        }

        // Close member details modal
        function closeMemberDetailsModal() {
            const modal = document.querySelector('.fixed.inset-0.bg-black.bg-opacity-50');
            if (modal) {
                const content = document.getElementById('memberDetailsContent');
                if (content) {
                    content.classList.remove('scale-100');
                    content.classList.add('scale-95');
                }
                
                setTimeout(() => {
                    document.body.removeChild(modal);
                }, 300);
            }
        }

        // Success message function
        function showSuccessMessage(message) {
            const successDiv = document.createElement('div');
            successDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300';
            successDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    ${message}
                </div>
            `;
            
            document.body.appendChild(successDiv);
            
            setTimeout(() => {
                successDiv.classList.remove('translate-x-full');
            }, 100);
            
            setTimeout(() => {
                successDiv.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(successDiv);
                }, 300);
            }, 3000);
        }

        // Close modals when clicking outside
        document.getElementById('addMemberModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddMemberModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!document.getElementById('addMemberModal').classList.contains('hidden')) {
                    closeAddMemberModal();
                }
                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                    closeDeleteModal();
                }
                // Close member details modal if open
                const memberDetailsModal = document.querySelector('.fixed.inset-0.bg-black.bg-opacity-50');
                if (memberDetailsModal) {
                    closeMemberDetailsModal();
                }
            }
        });
    </script>
</body>
</html>