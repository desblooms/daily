<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/campaign_functions.php';

requireAdmin(); // Only admins can access this page

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$campaignId = $_GET['campaign_id'] ?? null;
$status = $_GET['status'] ?? 'pending_approval';

// Get campaigns
$campaigns = getCampaigns('active');

// Get leads for approval
$leadsForApproval = [];
if ($status === 'pending_approval') {
    // Get leads that are closed but not admin approved
    $sql = "
        SELECT 
            l.*,
            c.name as campaign_name,
            u.name as assigned_name,
            ub.name as updated_by_name
        FROM leads l
        LEFT JOIN campaigns c ON l.campaign_id = c.id
        LEFT JOIN users u ON l.assigned_to = u.id
        LEFT JOIN users ub ON l.updated_by = ub.id
        WHERE l.sale_status = 'closed' AND l.admin_approved = FALSE
    ";
    
    $params = [];
    
    if ($campaignId) {
        $sql .= " AND l.campaign_id = ?";
        $params[] = $campaignId;
    }
    
    if ($selectedDate) {
        $sql .= " AND l.assigned_date = ?";
        $params[] = $selectedDate;
    }
    
    $sql .= " ORDER BY l.updated_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leadsForApproval = $stmt->fetchAll();
} else {
    $leadsForApproval = getLeads($campaignId, null, $selectedDate, $status);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Approval - Daily Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .lead-card {
            transition: all 0.3s ease;
        }
        .lead-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .approval-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .lead-card:hover .approval-actions {
            opacity: 1;
        }
        .modal {
            backdrop-filter: blur(5px);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'includes/global-header.php'; ?>

    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Lead Approval Center</h1>
                <p class="text-gray-600">Review and approve closed leads</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <input type="date" value="<?= $selectedDate ?>" onchange="changeDate(this.value)" 
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <button onclick="bulkApprove()" class="bg-green-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-green-700 transition-colors">
                    <i class="fas fa-check-double mr-2"></i>Bulk Approve
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl p-4 shadow-sm border mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Campaign</label>
                    <select onchange="filterByCampaign(this.value)" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Campaigns</option>
                        <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?= $campaign['id'] ?>" <?= $campaignId == $campaign['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($campaign['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select onchange="filterByStatus(this.value)" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="pending_approval" <?= $status === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>All Closed</option>
                        <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="not_interested" <?= $status === 'not_interested' ? 'selected' : '' ?>>Not Interested</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button onclick="selectAll()" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                        <i class="fas fa-check-square mr-2"></i>Select All
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <?php
        $totalPending = count(array_filter($leadsForApproval, fn($l) => !$l['admin_approved'] && $l['sale_status'] === 'closed'));
        $totalApproved = count(array_filter($leadsForApproval, fn($l) => $l['admin_approved']));
        ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $totalPending ?></p>
                        <p class="text-sm text-gray-500">Pending Approval</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $totalApproved ?></p>
                        <p class="text-sm text-gray-500">Approved</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= count($leadsForApproval) ?></p>
                        <p class="text-sm text-gray-500">Total Leads</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leads Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($leadsForApproval as $lead): ?>
                <?php 
                $statusColors = [
                    'pending' => 'bg-yellow-100 text-yellow-800',
                    'closed' => 'bg-green-100 text-green-800',
                    'not_interested' => 'bg-red-100 text-red-800',
                    'confirmed' => 'bg-blue-100 text-blue-800',
                    'no_response' => 'bg-gray-100 text-gray-800'
                ];
                $statusColor = $statusColors[$lead['sale_status']] ?? 'bg-gray-100 text-gray-800';
                ?>
                <div class="lead-card bg-white rounded-xl shadow-sm border overflow-hidden" data-lead-id="<?= $lead['id'] ?>">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <input type="checkbox" class="lead-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" value="<?= $lead['id'] ?>">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($lead['lead_number']) ?></h3>
                                        <?php if ($lead['admin_approved']): ?>
                                            <i class="fas fa-check-circle text-green-600" title="Admin Approved"></i>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($lead['campaign_name']) ?></p>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full font-medium <?= $statusColor ?>">
                                <?= ucfirst(str_replace('_', ' ', $lead['sale_status'])) ?>
                            </span>
                        </div>

                        <div class="space-y-3 mb-4">
                            <?php if ($lead['customer_name']): ?>
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-user text-gray-400 w-4"></i>
                                    <span class="text-sm text-gray-700"><?= htmlspecialchars($lead['customer_name']) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-phone text-gray-400 w-4"></i>
                                <span class="text-sm text-gray-700"><?= htmlspecialchars($lead['contact_number']) ?></span>
                            </div>

                            <div class="flex items-center space-x-2">
                                <i class="fas fa-user-tie text-gray-400 w-4"></i>
                                <span class="text-sm text-gray-700"><?= htmlspecialchars($lead['assigned_name']) ?></span>
                            </div>

                            <div class="flex items-center space-x-2">
                                <i class="fas fa-calendar text-gray-400 w-4"></i>
                                <span class="text-sm text-gray-700"><?= date('M j, Y', strtotime($lead['assigned_date'])) ?></span>
                            </div>

                            <?php if ($lead['reason_not_closed']): ?>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-xs text-gray-600"><strong>Notes:</strong> <?= htmlspecialchars($lead['reason_not_closed']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="text-xs text-gray-500 mb-4">
                            Updated: <?= timeAgo($lead['updated_at']) ?> by <?= htmlspecialchars($lead['updated_by_name']) ?>
                        </div>

                        <?php if (!$lead['admin_approved'] && $lead['sale_status'] === 'closed'): ?>
                            <div class="approval-actions space-y-2">
                                <div class="flex space-x-2">
                                    <button onclick="approveLead(<?= $lead['id'] ?>, true)" class="flex-1 bg-green-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                                        <i class="fas fa-check mr-1"></i>Approve
                                    </button>
                                    <button onclick="approveLead(<?= $lead['id'] ?>, false)" class="flex-1 bg-red-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                                        <i class="fas fa-times mr-1"></i>Reject
                                    </button>
                                </div>
                                <button onclick="viewLeadDetails(<?= $lead['id'] ?>)" class="w-full bg-gray-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors">
                                    <i class="fas fa-eye mr-1"></i>View Details
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="flex space-x-2">
                                <button onclick="viewLeadDetails(<?= $lead['id'] ?>)" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-eye mr-1"></i>View Details
                                </button>
                                <?php if ($lead['admin_approved']): ?>
                                    <button onclick="approveLead(<?= $lead['id'] ?>, false)" class="bg-orange-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-orange-700 transition-colors">
                                        <i class="fas fa-undo mr-1"></i>Revoke
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($leadsForApproval)): ?>
            <div class="text-center py-12">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check-circle text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No leads to review</h3>
                <p class="text-gray-500 mb-6">All leads are up to date</p>
                <a href="lead-dashboard.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>View Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="fixed inset-0 bg-black bg-opacity-50 modal hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900" id="modalTitle">Approve Lead</h2>
                <button onclick="closeApprovalModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="approvalForm" onsubmit="submitApproval(event)">
                <input type="hidden" id="modalLeadId" name="lead_id">
                <input type="hidden" id="modalApproved" name="approved">

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Admin Notes (Optional)</label>
                    <textarea name="notes" id="modalNotes" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Add any notes about this approval/rejection..."></textarea>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" class="flex-1 py-3 px-6 rounded-lg font-medium transition-colors" id="modalSubmitBtn">
                        Confirm
                    </button>
                    <button type="button" onclick="closeApprovalModal()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function changeDate(date) {
            const url = new URL(window.location);
            url.searchParams.set('date', date);
            window.location.href = url.toString();
        }

        function filterByCampaign(campaignId) {
            const url = new URL(window.location);
            if (campaignId) {
                url.searchParams.set('campaign_id', campaignId);
            } else {
                url.searchParams.delete('campaign_id');
            }
            window.location.href = url.toString();
        }

        function filterByStatus(status) {
            const url = new URL(window.location);
            url.searchParams.set('status', status);
            window.location.href = url.toString();
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('.lead-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        }

        function getSelectedLeads() {
            const checkboxes = document.querySelectorAll('.lead-checkbox:checked');
            return Array.from(checkboxes).map(cb => parseInt(cb.value));
        }

        function bulkApprove() {
            const selectedLeads = getSelectedLeads();
            if (selectedLeads.length === 0) {
                alert('Please select leads to approve');
                return;
            }

            if (!confirm(`Approve ${selectedLeads.length} selected leads?`)) {
                return;
            }

            fetch('api/leads.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'bulk_approve_leads',
                    lead_ids: selectedLeads,
                    approved: true,
                    notes: 'Bulk approved'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Bulk approval failed'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        function approveLead(leadId, approved) {
            const action = approved ? 'approve' : 'reject';
            const title = approved ? 'Approve Lead' : 'Reject Lead';
            const btnClass = approved ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-red-600 hover:bg-red-700 text-white';

            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalLeadId').value = leadId;
            document.getElementById('modalApproved').value = approved;
            document.getElementById('modalSubmitBtn').className = `flex-1 py-3 px-6 rounded-lg font-medium transition-colors ${btnClass}`;
            document.getElementById('modalSubmitBtn').textContent = `Confirm ${action.charAt(0).toUpperCase() + action.slice(1)}`;
            document.getElementById('modalNotes').value = '';

            document.getElementById('approvalModal').classList.remove('hidden');
        }

        function submitApproval(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = {
                action: 'admin_approve_lead',
                lead_id: parseInt(formData.get('lead_id')),
                approved: formData.get('approved') === 'true',
                notes: formData.get('notes')
            };

            fetch('api/leads.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Approval failed'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
        }

        function viewLeadDetails(leadId) {
            window.location.href = `lead-details.php?id=${leadId}`;
        }

        // Close modal when clicking outside
        document.getElementById('approvalModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApprovalModal();
            }
        });
    </script>
</body>
</html>