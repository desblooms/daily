<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/campaign_functions.php';

requireAdmin(); // Only admins can manage campaigns

$currentView = $_GET['view'] ?? 'list';
$campaigns = getCampaigns();
$users = getAllUsers();
$leadSources = getLeadSources();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Management - Daily Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .campaign-card {
            transition: all 0.3s ease;
        }
        .campaign-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 500;
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
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Campaign Management</h1>
                <p class="text-gray-600">Manage lead campaigns and daily distributions</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <button onclick="openCreateCampaignModal()" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg font-medium hover:from-blue-700 hover:to-purple-700 transition-all shadow-lg">
                    <i class="fas fa-plus mr-2"></i>Create Campaign
                </button>
                <a href="lead-dashboard.php" class="bg-green-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-green-700 transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>Dashboard
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <?php
            $totalCampaigns = count($campaigns);
            $activeCampaigns = count(array_filter($campaigns, function($c) { return $c['status'] === 'active'; }));
            $totalLeads = array_sum(array_column($campaigns, 'total_leads'));
            $closedLeads = array_sum(array_column($campaigns, 'closed_leads'));
            ?>
            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bullhorn text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $totalCampaigns ?></p>
                        <p class="text-sm text-gray-500">Total Campaigns</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-play-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $activeCampaigns ?></p>
                        <p class="text-sm text-gray-500">Active Campaigns</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-purple-600 text-xl"></i>
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
                        <i class="fas fa-handshake text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $closedLeads ?></p>
                        <p class="text-sm text-gray-500">Closed Leads</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Campaigns Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($campaigns as $campaign): ?>
                <?php 
                $statusColor = match($campaign['status']) {
                    'active' => 'bg-green-100 text-green-800',
                    'paused' => 'bg-yellow-100 text-yellow-800',
                    'completed' => 'bg-blue-100 text-blue-800',
                    'cancelled' => 'bg-red-100 text-red-800',
                    default => 'bg-gray-100 text-gray-800'
                };
                $conversionRate = $campaign['total_leads'] > 0 ? round(($campaign['closed_leads'] / $campaign['total_leads']) * 100, 2) : 0;
                ?>
                <div class="campaign-card bg-white rounded-xl shadow-sm border overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 mb-1"><?= htmlspecialchars($campaign['name']) ?></h3>
                                <p class="text-sm text-gray-500">By <?= htmlspecialchars($campaign['created_by_name']) ?></p>
                            </div>
                            <span class="status-badge <?= $statusColor ?>"><?= ucfirst($campaign['status']) ?></span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="text-center p-3 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-gray-900"><?= $campaign['team_count'] ?></p>
                                <p class="text-xs text-gray-500">Team Members</p>
                            </div>
                            <div class="text-center p-3 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-gray-900"><?= $campaign['daily_lead_quota'] ?></p>
                                <p class="text-xs text-gray-500">Daily Quota</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="text-center">
                                <p class="text-lg font-semibold text-blue-600"><?= $campaign['total_leads'] ?></p>
                                <p class="text-xs text-gray-500">Total Leads</p>
                            </div>
                            <div class="text-center">
                                <p class="text-lg font-semibold text-green-600"><?= $conversionRate ?>%</p>
                                <p class="text-xs text-gray-500">Conversion</p>
                            </div>
                        </div>

                        <div class="text-xs text-gray-500 mb-4">
                            <p><i class="fas fa-calendar mr-1"></i><?= date('M j, Y', strtotime($campaign['start_date'])) ?> - <?= date('M j, Y', strtotime($campaign['end_date'])) ?></p>
                        </div>

                        <div class="flex space-x-2">
                            <button onclick="viewCampaign(<?= $campaign['id'] ?>)" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                                <i class="fas fa-eye mr-1"></i>Details
                            </button>
                            <button onclick="editCampaign(<?= $campaign['id'] ?>)" class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <button onclick="generateLeads(<?= $campaign['id'] ?>)" class="bg-green-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                                <i class="fas fa-plus-circle mr-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($campaigns)): ?>
            <div class="text-center py-12">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-bullhorn text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No campaigns yet</h3>
                <p class="text-gray-500 mb-6">Create your first lead campaign to get started</p>
                <button onclick="openCreateCampaignModal()" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Create Campaign
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Campaign Modal -->
    <div id="createCampaignModal" class="fixed inset-0 bg-black bg-opacity-50 modal hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Create New Campaign</h2>
                <button onclick="closeCreateCampaignModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="createCampaignForm" onsubmit="createCampaign(event)" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Campaign Name</label>
                        <input type="text" name="name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Daily Lead Quota</label>
                        <input type="number" name="daily_lead_quota" min="1" value="10" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                        <input type="date" name="start_date" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                        <input type="date" name="end_date" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Assign Team Members</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-4">
                        <?php foreach ($users as $user): ?>
                            <?php if ($user['role'] === 'user'): ?>
                                <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer">
                                    <input type="checkbox" name="team_members[]" value="<?= $user['id'] ?>" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-blue-600 text-xs font-medium"><?= strtoupper(substr($user['name'], 0, 2)) ?></span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                                        </div>
                                    </div>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex space-x-4 pt-6">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Create Campaign
                    </button>
                    <button type="button" onclick="closeCreateCampaignModal()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            const nextMonth = new Date(today);
            nextMonth.setMonth(nextMonth.getMonth() + 1);

            document.querySelector('input[name="start_date"]').value = tomorrow.toISOString().split('T')[0];
            document.querySelector('input[name="end_date"]').value = nextMonth.toISOString().split('T')[0];
        });

        function openCreateCampaignModal() {
            document.getElementById('createCampaignModal').classList.remove('hidden');
        }

        function closeCreateCampaignModal() {
            document.getElementById('createCampaignModal').classList.add('hidden');
            document.getElementById('createCampaignForm').reset();
        }

        function createCampaign(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = {
                action: 'create_campaign',
                name: formData.get('name'),
                description: formData.get('description'),
                start_date: formData.get('start_date'),
                end_date: formData.get('end_date'),
                daily_lead_quota: parseInt(formData.get('daily_lead_quota')),
                team_members: formData.getAll('team_members[]').map(id => parseInt(id))
            };

            fetch('api/campaigns.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Campaign created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create campaign'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        function viewCampaign(campaignId) {
            window.location.href = `campaign-details.php?id=${campaignId}`;
        }

        function editCampaign(campaignId) {
            // TODO: Implement edit functionality
            alert('Edit functionality coming soon!');
        }

        function generateLeads(campaignId) {
            if (!confirm('Generate leads for today\'s quota?')) return;

            fetch('api/campaigns.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_daily_leads',
                    campaign_id: campaignId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Generated ${data.count} leads successfully!`);
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to generate leads'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        // Close modal when clicking outside
        document.getElementById('createCampaignModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateCampaignModal();
            }
        });
    </script>
</body>
</html>