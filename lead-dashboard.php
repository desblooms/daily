<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/campaign_functions.php';

requireLogin();

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$campaignId = $_GET['campaign_id'] ?? null;

// Get user's campaigns or all campaigns for admin
if (isAdmin()) {
    $campaigns = getCampaigns('active');
} else {
    $userCampaigns = getUserCampaignAssignments($_SESSION['user_id'], $selectedDate);
    $campaigns = $userCampaigns;
}

// Get KPI data
$kpiData = [];
$totalStats = [
    'total_leads' => 0,
    'closed_leads' => 0,
    'pending_leads' => 0,
    'conversion_rate' => 0
];

if ($campaignId) {
    $kpiData = getCampaignKPIs($campaignId);
    $campaignAnalytics = getCampaignAnalytics($campaignId);
    if ($campaignAnalytics) {
        $totalStats = $campaignAnalytics;
    }
} else {
    // Aggregate data from all user's campaigns
    foreach ($campaigns as $campaign) {
        $analytics = getCampaignAnalytics($campaign['id']);
        if ($analytics) {
            $totalStats['total_leads'] += $analytics['total_leads'];
            $totalStats['closed_leads'] += $analytics['closed_leads'];
            $totalStats['pending_leads'] += $analytics['pending_leads'];
        }
    }
    if ($totalStats['total_leads'] > 0) {
        $totalStats['conversion_rate'] = round(($totalStats['closed_leads'] / $totalStats['total_leads']) * 100, 2);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Dashboard - Daily Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'includes/global-header.php'; ?>

    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Lead Dashboard</h1>
                <p class="text-gray-600">Monitor lead performance and KPIs</p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <input type="date" value="<?= $selectedDate ?>" onchange="changeDate(this.value)" 
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <?php if (isAdmin()): ?>
                    <a href="campaigns.php" class="bg-purple-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-purple-700 transition-colors">
                        <i class="fas fa-cog mr-2"></i>Manage
                    </a>
                <?php endif; ?>
                <a href="my-leads.php" class="bg-green-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-green-700 transition-colors">
                    <i class="fas fa-users mr-2"></i>My Leads
                </a>
            </div>
        </div>

        <!-- Campaign Filter -->
        <?php if (!empty($campaigns)): ?>
            <div class="bg-white rounded-xl p-4 shadow-sm border mb-6">
                <div class="flex items-center space-x-4">
                    <label class="text-sm font-medium text-gray-700">Campaign:</label>
                    <select onchange="filterByCampaign(this.value)" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Campaigns</option>
                        <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?= $campaign['id'] ?>" <?= $campaignId == $campaign['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($campaign['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $totalStats['total_leads'] ?></p>
                        <p class="text-sm text-gray-500">Total Leads</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center">
                    <span class="text-green-600 text-sm font-medium">
                        <i class="fas fa-arrow-up mr-1"></i>12.3%
                    </span>
                    <span class="text-gray-500 text-sm ml-2">vs last week</span>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-handshake text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $totalStats['closed_leads'] ?></p>
                        <p class="text-sm text-gray-500">Closed Leads</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center">
                    <span class="text-green-600 text-sm font-medium">
                        <i class="fas fa-arrow-up mr-1"></i>8.1%
                    </span>
                    <span class="text-gray-500 text-sm ml-2">vs last week</span>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $totalStats['pending_leads'] ?></p>
                        <p class="text-sm text-gray-500">Pending</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center">
                    <span class="text-red-600 text-sm font-medium">
                        <i class="fas fa-arrow-down mr-1"></i>2.4%
                    </span>
                    <span class="text-gray-500 text-sm ml-2">vs last week</span>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-percentage text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= $totalStats['conversion_rate'] ?>%</p>
                        <p class="text-sm text-gray-500">Conversion Rate</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center">
                    <span class="text-green-600 text-sm font-medium">
                        <i class="fas fa-arrow-up mr-1"></i>5.2%
                    </span>
                    <span class="text-gray-500 text-sm ml-2">vs last week</span>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Lead Status Distribution -->
            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Lead Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Conversion Trend -->
            <div class="bg-white rounded-xl p-6 shadow-sm border">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Conversion Trend (Last 7 Days)</h3>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Performance Table -->
        <?php if (isAdmin() && !empty($kpiData)): ?>
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Team Performance</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team Member</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Leads</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Closed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pending</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conversion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Follow-ups</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($kpiData as $kpi): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                <span class="text-blue-600 text-xs font-medium"><?= strtoupper(substr($kpi['user_name'], 0, 2)) ?></span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($kpi['user_name']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= date('M j', strtotime($kpi['date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= $kpi['total_leads'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                            <?= $kpi['closed_leads'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                            <?= $kpi['pending_leads'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="flex items-center">
                                            <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                                                <div class="bg-green-600 h-2 rounded-full" style="width: <?= $kpi['conversion_rate'] ?>%"></div>
                                            </div>
                                            <span class="text-xs font-medium"><?= $kpi['conversion_rate'] ?>%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= $kpi['follow_up_done'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                            <?= $kpi['admin_approved_leads'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="fixed bottom-6 right-6 space-y-3">
            <?php if (isAdmin()): ?>
                <button onclick="exportData()" class="w-14 h-14 bg-green-600 text-white rounded-full shadow-lg hover:bg-green-700 transition-colors flex items-center justify-center">
                    <i class="fas fa-download text-xl"></i>
                </button>
            <?php endif; ?>
            <button onclick="refreshData()" class="w-14 h-14 bg-blue-600 text-white rounded-full shadow-lg hover:bg-blue-700 transition-colors flex items-center justify-center">
                <i class="fas fa-sync-alt text-xl"></i>
            </button>
        </div>
    </div>

    <script>
        // Chart data
        const statusData = {
            labels: ['Closed', 'Pending', 'Not Interested', 'Confirmed', 'No Response'],
            datasets: [{
                data: [
                    <?= $totalStats['closed_leads'] ?>,
                    <?= $totalStats['pending_leads'] ?>,
                    <?= $totalStats['not_interested_leads'] ?? 0 ?>,
                    <?= $totalStats['confirmed_leads'] ?? 0 ?>,
                    <?= $totalStats['no_response_leads'] ?? 0 ?>
                ],
                backgroundColor: [
                    '#10B981',
                    '#F59E0B',
                    '#EF4444',
                    '#3B82F6',
                    '#6B7280'
                ]
            }]
        };

        // Initialize Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: statusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Sample trend data (would be dynamic from backend)
        const trendData = {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Conversion Rate %',
                data: [15, 18, 22, 25, 28, 24, 30],
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        };

        // Initialize Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: trendData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

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

        function refreshData() {
            location.reload();
        }

        function exportData() {
            const campaignId = new URLSearchParams(window.location.search).get('campaign_id');
            if (!campaignId) {
                alert('Please select a campaign to export');
                return;
            }

            const startDate = prompt('Enter start date (YYYY-MM-DD):', '2024-01-01');
            const endDate = prompt('Enter end date (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);

            if (startDate && endDate) {
                fetch('api/leads.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'export_leads',
                        campaign_id: parseInt(campaignId),
                        start_date: startDate,
                        end_date: endDate
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        downloadCSV(data.leads, `campaign_${campaignId}_leads.csv`);
                    } else {
                        alert('Error: ' + (data.message || 'Export failed'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error. Please try again.');
                });
            }
        }

        function downloadCSV(data, filename) {
            const headers = [
                'Lead Number', 'Customer Name', 'Contact Number', 'WhatsApp Number',
                'Lead Source', 'Sale Status', 'Follow Up Status', 'Reason Not Closed',
                'Admin Approved', 'Assigned Date', 'Created At', 'Updated At',
                'Campaign Name', 'Assigned To'
            ];

            const csvContent = [
                headers.join(','),
                ...data.map(row => [
                    row.lead_number,
                    row.customer_name || '',
                    row.contact_number,
                    row.whatsapp_number || '',
                    row.lead_source,
                    row.sale_status,
                    row.follow_up_status,
                    (row.reason_not_closed || '').replace(/,/g, ';'),
                    row.admin_approved ? 'Yes' : 'No',
                    row.assigned_date,
                    row.created_at,
                    row.updated_at,
                    row.campaign_name,
                    row.assigned_to_name
                ].map(field => `"${field}"`).join(','))
            ].join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.click();
            window.URL.revokeObjectURL(url);
        }

        // Auto-refresh every 5 minutes
        setInterval(refreshData, 300000);
    </script>
</body>
</html>