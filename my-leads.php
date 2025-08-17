<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/campaign_functions.php';

requireLogin();

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$campaignId = $_GET['campaign_id'] ?? null;

// Get user's campaigns for the selected date
$userCampaigns = getUserCampaignAssignments($_SESSION['user_id'], $selectedDate);

// Get user's leads for the selected date
$userLeads = getUserDailyLeads($_SESSION['user_id'], $selectedDate);
if ($campaignId) {
    $userLeads = array_filter($userLeads, function($lead) use ($campaignId) {
        return $lead['campaign_id'] == $campaignId;
    });
}

$leadSources = getLeadSources();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leads - Daily Calendar</title>
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
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
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
                <h1 class="text-3xl font-bold text-gray-900 mb-2">My Leads</h1>
                <p class="text-gray-600">Manage your assigned leads for <?= date('F j, Y', strtotime($selectedDate)) ?></p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <input type="date" value="<?= $selectedDate ?>" onchange="changeDate(this.value)" 
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <a href="lead-dashboard.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>Dashboard
                </a>
            </div>
        </div>

        <!-- Campaign Filter -->
        <?php if (!empty($userCampaigns)): ?>
            <div class="bg-white rounded-xl p-4 shadow-sm border mb-6">
                <div class="flex items-center space-x-4">
                    <label class="text-sm font-medium text-gray-700">Filter by Campaign:</label>
                    <select onchange="filterByCampaign(this.value)" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Campaigns</option>
                        <?php foreach ($userCampaigns as $campaign): ?>
                            <option value="<?= $campaign['id'] ?>" <?= $campaignId == $campaign['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($campaign['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <?php
        $totalLeads = count($userLeads);
        $pendingLeads = count(array_filter($userLeads, fn($l) => $l['sale_status'] === 'pending'));
        $closedLeads = count(array_filter($userLeads, fn($l) => $l['sale_status'] === 'closed'));
        $conversionRate = $totalLeads > 0 ? round(($closedLeads / $totalLeads) * 100, 2) : 0;
        ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $totalLeads ?></p>
                        <p class="text-sm text-gray-500">Total Leads</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $pendingLeads ?></p>
                        <p class="text-sm text-gray-500">Pending</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-handshake text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $closedLeads ?></p>
                        <p class="text-sm text-gray-500">Closed</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-percentage text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $conversionRate ?>%</p>
                        <p class="text-sm text-gray-500">Conversion</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leads List -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($userLeads as $lead): ?>
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
                <div class="lead-card bg-white rounded-xl shadow-sm border overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-1">
                                    <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($lead['lead_number']) ?></h3>
                                    <?php if ($lead['admin_approved']): ?>
                                        <i class="fas fa-check-circle text-green-600" title="Admin Approved"></i>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($lead['campaign_name']) ?></p>
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
                            
                            <?php if ($lead['contact_number']): ?>
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-phone text-gray-400 w-4"></i>
                                    <span class="text-sm text-gray-700"><?= htmlspecialchars($lead['contact_number']) ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="flex items-center space-x-2">
                                <i class="fas fa-tag text-gray-400 w-4"></i>
                                <span class="text-sm text-gray-700"><?= ucfirst(str_replace('_', ' ', $lead['lead_source'])) ?></span>
                            </div>

                            <?php if ($lead['follow_up_status'] !== 'not_required'): ?>
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-calendar-check text-gray-400 w-4"></i>
                                    <span class="text-sm text-gray-700">Follow-up: <?= ucfirst(str_replace('_', ' ', $lead['follow_up_status'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="text-xs text-gray-500 mb-4">
                            Updated: <?= timeAgo($lead['updated_at']) ?>
                        </div>

                        <div class="flex space-x-2">
                            <button onclick="editLead(<?= $lead['id'] ?>)" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <button onclick="viewLead(<?= $lead['id'] ?>)" class="bg-gray-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors">
                                <i class="fas fa-eye mr-1"></i>View
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($userLeads)): ?>
            <div class="text-center py-12">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-users text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No leads assigned</h3>
                <p class="text-gray-500 mb-6">You don't have any leads assigned for this date</p>
                <button onclick="changeDate(new Date().toISOString().split('T')[0])" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                    <i class="fas fa-calendar mr-2"></i>View Today's Leads
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Lead Modal -->
    <div id="editLeadModal" class="fixed inset-0 bg-black bg-opacity-50 modal hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Edit Lead</h2>
                <button onclick="closeEditLeadModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="editLeadForm" onsubmit="updateLead(event)" class="space-y-6">
                <input type="hidden" id="editLeadId" name="lead_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer Name</label>
                        <input type="text" name="customer_name" id="editCustomerName" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number *</label>
                        <input type="tel" name="contact_number" id="editContactNumber" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Lead Source *</label>
                        <select name="lead_source" id="editLeadSource" required onchange="toggleWhatsAppField(this.value, 'edit')" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Source</option>
                            <?php foreach ($leadSources as $source): ?>
                                <option value="<?= $source['name'] ?>" data-requires-whatsapp="<?= $source['requires_whatsapp'] ? 'true' : 'false' ?>">
                                    <?= htmlspecialchars($source['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="editWhatsAppField" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp Number *</label>
                        <input type="tel" name="whatsapp_number" id="editWhatsAppNumber" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sale Status</label>
                        <select name="sale_status" id="editSaleStatus" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="pending">Pending</option>
                            <option value="closed">Closed</option>
                            <option value="not_interested">Not Interested</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="no_response">No Response</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Follow Up Status</label>
                        <select name="follow_up_status" id="editFollowUpStatus" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="not_required">Not Required</option>
                            <option value="done">Done</option>
                            <option value="call_done">Call Done</option>
                            <option value="scheduled">Scheduled</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Not Closing Sale</label>
                    <textarea name="reason_not_closed" id="editReasonNotClosed" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" id="editNotes" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div class="flex space-x-4 pt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Update Lead
                    </button>
                    <button type="button" onclick="closeEditLeadModal()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors">
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

        function editLead(leadId) {
            // Fetch lead details and populate modal
            fetch('api/leads.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_lead',
                    lead_id: leadId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateEditModal(data.lead);
                    document.getElementById('editLeadModal').classList.remove('hidden');
                } else {
                    alert('Error: ' + (data.message || 'Failed to load lead'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        function populateEditModal(lead) {
            document.getElementById('editLeadId').value = lead.id;
            document.getElementById('editCustomerName').value = lead.customer_name || '';
            document.getElementById('editContactNumber').value = lead.contact_number || '';
            document.getElementById('editLeadSource').value = lead.lead_source || '';
            document.getElementById('editWhatsAppNumber').value = lead.whatsapp_number || '';
            document.getElementById('editSaleStatus').value = lead.sale_status || 'pending';
            document.getElementById('editFollowUpStatus').value = lead.follow_up_status || 'not_required';
            document.getElementById('editReasonNotClosed').value = lead.reason_not_closed || '';
            document.getElementById('editNotes').value = lead.notes || '';

            // Toggle WhatsApp field if needed
            toggleWhatsAppField(lead.lead_source, 'edit');
        }

        function toggleWhatsAppField(source, prefix = '') {
            const whatsAppField = document.getElementById(prefix + 'WhatsAppField');
            const whatsAppInput = document.getElementById(prefix + 'WhatsAppNumber');
            const sourceSelect = document.getElementById(prefix + 'LeadSource');
            const selectedOption = sourceSelect.querySelector(`option[value="${source}"]`);
            
            if (selectedOption && selectedOption.dataset.requiresWhatsapp === 'true') {
                whatsAppField.classList.remove('hidden');
                whatsAppInput.required = true;
            } else {
                whatsAppField.classList.add('hidden');
                whatsAppInput.required = false;
                whatsAppInput.value = '';
            }
        }

        function updateLead(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = {
                action: 'update_lead',
                lead_id: parseInt(formData.get('lead_id')),
                customer_name: formData.get('customer_name'),
                contact_number: formData.get('contact_number'),
                whatsapp_number: formData.get('whatsapp_number'),
                lead_source: formData.get('lead_source'),
                sale_status: formData.get('sale_status'),
                follow_up_status: formData.get('follow_up_status'),
                reason_not_closed: formData.get('reason_not_closed'),
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
                    alert('Lead updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update lead'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        function viewLead(leadId) {
            window.location.href = `lead-details.php?id=${leadId}`;
        }

        function closeEditLeadModal() {
            document.getElementById('editLeadModal').classList.add('hidden');
            document.getElementById('editLeadForm').reset();
        }

        // Close modal when clicking outside
        document.getElementById('editLeadModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditLeadModal();
            }
        });
    </script>
</body>
</html>