<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ZKTeco ADMS Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',300:'#93c5fd',400:'#60a5fa',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a' }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .fade-in { animation: fadeIn .4s ease-out; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
        .pulse-dot { animation: pulseDot 2s infinite; }
        @keyframes pulseDot { 0%,100% { opacity:1; } 50% { opacity:.4; } }
        ::-webkit-scrollbar { width:6px; height:6px; }
        ::-webkit-scrollbar-track { background:#1e293b; }
        ::-webkit-scrollbar-thumb { background:#475569; border-radius:3px; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,.3); }
        .stat-card { transition: all .2s ease; }
        .tab-active { border-bottom: 2px solid #3b82f6; color: #3b82f6; }
        .swal2-popup .swal2-input,
        .swal2-popup .swal2-select {
            background: #0b1220 !important;
            color: #e2e8f0 !important;
            border: 1px solid #334155 !important;
        }
        .swal2-popup select.swal-dark-select option {
            background: #0b1220;
            color: #e2e8f0;
        }
    </style>
</head>
<body class="h-full bg-slate-900 text-slate-200 font-sans">
    <div id="app" class="min-h-full flex flex-col">

        <!-- Header -->
        <header class="bg-slate-800/80 backdrop-blur border-b border-slate-700/50 sticky top-0 z-50">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-brand-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-lg font-semibold text-white">ZKTeco ADMS</h1>
                            <p class="text-xs text-slate-400">Security PUSH Protocol Dashboard</p>
                        </div>
                    </div>
                    <nav class="hidden md:flex items-center gap-1">
                        <a href="/dashboard" class="px-3 py-1.5 rounded-lg text-sm font-medium text-white bg-slate-700/80">Dashboard</a>
                        <a href="/register-user" class="px-3 py-1.5 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors">Register Users</a>
                    </nav>
                    <div class="flex items-center gap-4">
                        <div id="server-status" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-700/50 text-xs">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 pulse-dot"></span>
                            <span class="text-slate-300">Server Active</span>
                        </div>
                        <button onclick="refreshAll()" class="p-2 rounded-lg hover:bg-slate-700 transition-colors" title="Refresh">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                        <span id="last-refresh" class="text-xs text-slate-500">--</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 max-w-[1600px] mx-auto w-full px-4 sm:px-6 lg:px-8 py-6">

            <!-- Stats Cards Row -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6" id="stats-cards">
                <div class="stat-card bg-slate-800 rounded-xl p-4 border border-slate-700/50">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-brand-500/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                        </div>
                        <span class="text-xs text-slate-400 uppercase tracking-wide">Devices</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="stat-devices">-</p>
                    <p class="text-xs text-slate-500 mt-1" id="stat-devices-online">-</p>
                </div>
                <div class="stat-card bg-slate-800 rounded-xl p-4 border border-slate-700/50">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-xs text-slate-400 uppercase tracking-wide">Access Today</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="stat-today">-</p>
                    <p class="text-xs text-slate-500 mt-1">events today</p>
                </div>
                <div class="stat-card bg-slate-800 rounded-xl p-4 border border-slate-700/50">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-violet-500/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <span class="text-xs text-slate-400 uppercase tracking-wide">Users</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="stat-users">-</p>
                    <p class="text-xs text-slate-500 mt-1">synced from device</p>
                </div>
                <div class="stat-card bg-slate-800 rounded-xl p-4 border border-slate-700/50">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                        </div>
                        <span class="text-xs text-slate-400 uppercase tracking-wide">Total Logs</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="stat-total">-</p>
                    <p class="text-xs text-slate-500 mt-1">raw log entries</p>
                </div>
                <div class="stat-card bg-slate-800 rounded-xl p-4 border border-slate-700/50">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-rose-500/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-xs text-slate-400 uppercase tracking-wide">Alarms</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="stat-alarms">0</p>
                    <p class="text-xs text-slate-500 mt-1">alert events</p>
                </div>
                <div class="stat-card bg-slate-800 rounded-xl p-4 border border-slate-700/50">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <span class="text-xs text-slate-400 uppercase tracking-wide">Last Heartbeat</span>
                    </div>
                    <p class="text-lg font-bold text-white" id="stat-heartbeat">-</p>
                    <p class="text-xs text-slate-500 mt-1" id="stat-heartbeat-ago">-</p>
                </div>
            </div>

            <!-- Device Info Panel -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Device Card -->
                <div class="bg-slate-800 rounded-xl border border-slate-700/50 p-5 fade-in">
                    <h3 class="text-sm font-semibold text-slate-300 uppercase tracking-wide mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                        Connected Devices
                    </h3>
                    <div id="device-list" class="space-y-3">
                        <div class="animate-pulse bg-slate-700/50 rounded-lg h-20"></div>
                    </div>
                </div>

                <!-- Endpoint Stats -->
                <div class="bg-slate-800 rounded-xl border border-slate-700/50 p-5 fade-in">
                    <h3 class="text-sm font-semibold text-slate-300 uppercase tracking-wide mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Endpoint Traffic
                    </h3>
                    <div id="endpoint-stats" class="space-y-2 max-h-64 overflow-y-auto">
                        <div class="animate-pulse bg-slate-700/50 rounded h-4 w-3/4 mb-2"></div>
                        <div class="animate-pulse bg-slate-700/50 rounded h-4 w-1/2"></div>
                    </div>
                </div>

                <!-- Upload Stats Donut -->
                <div class="bg-slate-800 rounded-xl border border-slate-700/50 p-5 fade-in">
                    <h3 class="text-sm font-semibold text-slate-300 uppercase tracking-wide mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/></svg>
                        Upload Distribution
                    </h3>
                    <div class="h-48 flex items-center justify-center">
                        <canvas id="uploadChart" class="max-h-full"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tabbed Content Area -->
            <div class="bg-slate-800 rounded-xl border border-slate-700/50 fade-in">
                <!-- Tab Navigation -->
                <div class="flex border-b border-slate-700/50 px-2 overflow-x-auto">
                    <button onclick="switchTab('events')" id="tab-events" class="px-4 py-3 text-sm font-medium text-slate-400 hover:text-white transition-colors whitespace-nowrap tab-active">
                        Access Events
                    </button>
                    <button onclick="switchTab('status')" id="tab-status" class="px-4 py-3 text-sm font-medium text-slate-400 hover:text-white transition-colors whitespace-nowrap">
                        Door Status
                    </button>
                    <button onclick="switchTab('users')" id="tab-users" class="px-4 py-3 text-sm font-medium text-slate-400 hover:text-white transition-colors whitespace-nowrap">
                        Users
                    </button>
                    <button onclick="switchTab('raw')" id="tab-raw" class="px-4 py-3 text-sm font-medium text-slate-400 hover:text-white transition-colors whitespace-nowrap">
                        Raw Logs
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="p-5">
                    <!-- Access Events Tab -->
                    <div id="panel-events" class="tab-panel">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-slate-400 uppercase tracking-wider border-b border-slate-700/50">
                                        <th class="pb-3 pr-4">Time</th>
                                        <th class="pb-3 pr-4">User PIN</th>
                                        <th class="pb-3 pr-4">Event</th>
                                        <th class="pb-3 pr-4">Category</th>
                                        <th class="pb-3 pr-4">Door</th>
                                        <th class="pb-3 pr-4">Direction</th>
                                        <th class="pb-3 pr-4">Verify</th>
                                        <th class="pb-3">Mask</th>
                                    </tr>
                                </thead>
                                <tbody id="events-tbody" class="divide-y divide-slate-700/30">
                                    <tr><td colspan="8" class="py-8 text-center text-slate-500">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="events-pagination" class="flex items-center justify-between mt-4 text-sm text-slate-400">
                            <span id="events-page-info" class="text-xs">-</span>
                            <div class="flex items-center gap-1">
                                <button id="events-first" onclick="loadAccessEvents(1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled title="First">&#171;</button>
                                <button id="events-prev" onclick="loadAccessEvents(currentEventsPage-1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled>Prev</button>
                                <span id="events-pages" class="flex gap-1"></span>
                                <button id="events-next" onclick="loadAccessEvents(currentEventsPage+1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled>Next</button>
                                <button id="events-last" onclick="loadAccessEvents(eventsLastPage)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled title="Last">&#187;</button>
                            </div>
                        </div>
                    </div>

                    <!-- Door Status Tab -->
                    <div id="panel-status" class="tab-panel hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-slate-400 uppercase tracking-wider border-b border-slate-700/50">
                                        <th class="pb-3 pr-4">Time</th>
                                        <th class="pb-3 pr-4">Device</th>
                                        <th class="pb-3 pr-4">Sensor</th>
                                        <th class="pb-3 pr-4">Relay</th>
                                        <th class="pb-3 pr-4">Door</th>
                                        <th class="pb-3">Alarm</th>
                                    </tr>
                                </thead>
                                <tbody id="status-tbody" class="divide-y divide-slate-700/30">
                                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="status-pagination" class="flex items-center justify-between mt-4 text-sm text-slate-400">
                            <span id="status-page-info" class="text-xs">-</span>
                            <div class="flex items-center gap-1">
                                <button id="status-first" onclick="loadDeviceStatus(1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled title="First">&#171;</button>
                                <button id="status-prev" onclick="loadDeviceStatus(currentStatusPage-1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled>Prev</button>
                                <span id="status-pages" class="flex gap-1"></span>
                                <button id="status-next" onclick="loadDeviceStatus(currentStatusPage+1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled>Next</button>
                                <button id="status-last" onclick="loadDeviceStatus(statusLastPage)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled title="Last">&#187;</button>
                            </div>
                        </div>
                    </div>

                    <!-- Users Tab -->
                    <div id="panel-users" class="tab-panel hidden">
                        <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
                            <p class="text-xs text-slate-400">User records are sourced from the canonical app sync table and include device sync status.</p>
                            <div class="flex items-center gap-2">
                                <select id="users-device-sync" class="bg-slate-700 border border-slate-600 rounded-lg px-3 py-1.5 text-xs text-slate-300"></select>
                                <button onclick="syncUsersFromDevice()" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-brand-600 hover:bg-brand-500 text-white">Sync From Device</button>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-slate-400 uppercase tracking-wider border-b border-slate-700/50">
                                        <th class="pb-3 pr-4">Device</th>
                                        <th class="pb-3 pr-4">PIN</th>
                                        <th class="pb-3 pr-4">Name</th>
                                        <th class="pb-3 pr-4">Privilege</th>
                                        <th class="pb-3 pr-4">Card</th>
                                        <th class="pb-3 pr-4">Sync</th>
                                        <th class="pb-3 pr-4">Synced At</th>
                                        <th class="pb-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="users-tbody" class="divide-y divide-slate-700/30">
                                    <tr><td colspan="8" class="py-8 text-center text-slate-500">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="users-pagination" class="flex items-center justify-between mt-4 text-sm text-slate-400">
                            <span id="users-page-info" class="text-xs">-</span>
                            <div class="flex items-center gap-1">
                                <button id="users-first" onclick="loadUsers(1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled title="First">&#171;</button>
                                <button id="users-prev" onclick="loadUsers(currentUsersPage-1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled>Prev</button>
                                <span id="users-pages" class="flex gap-1"></span>
                                <button id="users-next" onclick="loadUsers(currentUsersPage+1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled>Next</button>
                                <button id="users-last" onclick="loadUsers(usersLastPage)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled title="Last">&#187;</button>
                            </div>
                        </div>
                    </div>

                    <!-- Raw Logs Tab -->
                    <div id="panel-raw" class="tab-panel hidden">
                        <div class="flex flex-wrap gap-3 mb-4">
                            <select id="raw-endpoint-filter" onchange="loadRawLogs(1)" class="bg-slate-700 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-300 focus:ring-brand-500 focus:border-brand-500">
                                <option value="">All Endpoints</option>
                            </select>
                            <select id="raw-method-filter" onchange="loadRawLogs(1)" class="bg-slate-700 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-slate-300">
                                <option value="">All Methods</option>
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                            </select>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-slate-400 uppercase tracking-wider border-b border-slate-700/50">
                                        <th class="pb-3 pr-4">ID</th>
                                        <th class="pb-3 pr-4">Time</th>
                                        <th class="pb-3 pr-4">SN</th>
                                        <th class="pb-3 pr-4">Method</th>
                                        <th class="pb-3 pr-4">Endpoint</th>
                                        <th class="pb-3 pr-4">Query</th>
                                        <th class="pb-3">Body Preview</th>
                                    </tr>
                                </thead>
                                <tbody id="raw-tbody" class="divide-y divide-slate-700/30">
                                    <tr><td colspan="7" class="py-8 text-center text-slate-500">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="raw-pagination" class="flex items-center justify-between mt-4 text-sm text-slate-400">
                            <span id="raw-page-info" class="text-xs">-</span>
                            <div class="flex items-center gap-1">
                                <button id="raw-first" onclick="loadRawLogs(1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled title="First">&#171;</button>
                                <button id="raw-prev" onclick="loadRawLogs(currentRawPage-1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled>Prev</button>
                                <span id="raw-pages" class="flex gap-1"></span>
                                <button id="raw-next" onclick="loadRawLogs(currentRawPage+1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled>Next</button>
                                <button id="raw-last" onclick="loadRawLogs(rawLastPage)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled title="Last">&#187;</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Timeline Chart -->
            <div class="bg-slate-800 rounded-xl border border-slate-700/50 p-5 mt-6 fade-in">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-slate-300 uppercase tracking-wide flex items-center gap-2">
                        <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        Activity Timeline
                    </h3>
                    <select id="timeline-hours" onchange="loadTimeline()" class="bg-slate-700 border border-slate-600 rounded-lg px-3 py-1.5 text-xs text-slate-300">
                        <option value="6">Last 6h</option>
                        <option value="12">Last 12h</option>
                        <option value="24" selected>Last 24h</option>
                        <option value="48">Last 48h</option>
                        <option value="168">Last 7d</option>
                    </select>
                </div>
                <div class="h-64">
                    <canvas id="timelineChart"></canvas>
                </div>
            </div>

        </main>

        <!-- Footer -->
        <footer class="text-center py-4 text-xs text-slate-600 border-t border-slate-800">
            ZKTeco ADMS Server &bull; Security PUSH Protocol v3.1.2 &bull; Laravel {{ app()->version() }}
        </footer>
    </div>

    <script>
    // ─── State ───────────────────────────────────────────────────────────────
    let currentRawPage    = 1;
    let currentEventsPage = 1;
    let currentStatusPage = 1;
    let currentUsersPage  = 1;
    let eventsLastPage    = 1;
    let statusLastPage    = 1;
    let usersLastPage     = 1;
    let rawLastPage       = 1;
    let uploadChart = null;
    let timelineChart = null;
    let autoRefreshInterval = null;
    let knownDevicesForSync = [];

    // ─── Init ────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        refreshAll();
        autoRefreshInterval = setInterval(refreshAll, 30000); // auto-refresh every 30s
    });

    function refreshAll() {
        loadStats();
        loadAccessEvents(currentEventsPage);
        loadDeviceStatus(currentStatusPage);
        loadUsers(currentUsersPage);
        loadRawLogs(currentRawPage);
        loadTimeline();
        document.getElementById('last-refresh').textContent = new Date().toLocaleTimeString();
    }

    // ─── API Calls ───────────────────────────────────────────────────────────
    async function api(path, options = {}) {
        const { showErrorToast = false, ...fetchOptions } = options;

        try {
            const res = await fetch('/api/zkteco/' + path, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    ...(fetchOptions.headers || {}),
                },
                ...fetchOptions,
            });

            const body = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = body?.message || `Request failed (HTTP ${res.status})`;
                throw new Error(msg);
            }

            return body;
        } catch (e) {
            if (showErrorToast) {
                toast((e && e.message) ? e.message : 'Request failed', 'error');
            }
            console.error('API error:', path, e);
            return null;
        }
    }

    function swalTheme(config = {}) {
        return {
            background: '#0f172a',
            color: '#e2e8f0',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#475569',
            ...config,
        };
    }

    function toast(message, icon = 'success') {
        return Swal.fire(swalTheme({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2800,
            timerProgressBar: true,
            icon,
            title: message,
        }));
    }

    function validatePrivilege(value) {
        const allowed = [0, 2, 6, 14];
        const parsed = Number(value);
        return allowed.includes(parsed) ? parsed : null;
    }

    async function runEditWizard(user) {
        const formResult = await Swal.fire(swalTheme({
            title: 'Edit User',
            html: `
                <div style="text-align:left;font-size:13px;line-height:1.6;display:grid;gap:10px;">
                    <div style="font-size:12px;color:#94a3b8;">Device: ${user.device_sn || '-'} • PIN: ${user.pin || '-'}</div>
                    <div>
                        <label style="display:block;margin-bottom:4px;color:#94a3b8;">Name</label>
                        <input id="swal-edit-name" class="swal2-input" style="margin:0;width:100%;" value="${String(user.name || '').replace(/"/g, '&quot;')}">
                    </div>
                    <div>
                        <label style="display:block;margin-bottom:4px;color:#94a3b8;">Privilege</label>
                        <select id="swal-edit-privilege" class="swal2-input swal-dark-select" style="margin:0;width:100%;">
                            <option value="0">User</option>
                            <option value="2">Enroller</option>
                            <option value="6">Admin</option>
                            <option value="14">Super Admin</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;margin-bottom:4px;color:#94a3b8;">Card Number (optional)</label>
                        <input id="swal-edit-card" class="swal2-input" style="margin:0;width:100%;" value="${String(user.card || '').replace(/"/g, '&quot;')}">
                    </div>
                    <div>
                        <label style="display:block;margin-bottom:4px;color:#94a3b8;">Group ID (1-99)</label>
                        <input id="swal-edit-group" type="number" min="1" max="99" step="1" class="swal2-input" style="margin:0;width:100%;" value="${String(user.group || '1').replace(/"/g, '&quot;')}">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Queue Update',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                const privilegeEl = document.getElementById('swal-edit-privilege');
                if (privilegeEl) privilegeEl.value = String(privilegeCode(user.privilege));
            },
            preConfirm: () => {
                const name = String(document.getElementById('swal-edit-name')?.value || '').trim();
                const privilegeRaw = document.getElementById('swal-edit-privilege')?.value;
                const cardNo = String(document.getElementById('swal-edit-card')?.value || '').trim();
                const groupRaw = document.getElementById('swal-edit-group')?.value;

                if (!name) {
                    Swal.showValidationMessage('Name is required');
                    return false;
                }

                const privilege = validatePrivilege(privilegeRaw);
                if (privilege === null) {
                    Swal.showValidationMessage('Choose a valid privilege');
                    return false;
                }

                const groupId = Number(groupRaw);
                if (!Number.isInteger(groupId) || groupId < 1 || groupId > 99) {
                    Swal.showValidationMessage('Group ID must be between 1 and 99');
                    return false;
                }

                return {
                    name,
                    privilege,
                    card_no: cardNo || null,
                    group_id: groupId,
                };
            },
        }));

        return formResult.isConfirmed ? formResult.value : null;
    }

    // ─── Stats ───────────────────────────────────────────────────────────────
    async function loadStats() {
        const data = await api('stats');
        if (!data) return;

        const devices = data.devices || [];
        knownDevicesForSync = devices.map(d => d.sn).filter(Boolean);
        renderUsersSyncDeviceSelect();
        const onlineCount = devices.filter(d => d.is_online).length;
        document.getElementById('stat-devices').textContent = devices.length;
        document.getElementById('stat-devices-online').textContent = `${onlineCount} online`;
        document.getElementById('stat-devices-online').className = `text-xs mt-1 ${onlineCount > 0 ? 'text-emerald-400' : 'text-rose-400'}`;

        document.getElementById('stat-today').textContent = (data.today_events || 0).toLocaleString();
        document.getElementById('stat-total').textContent = (data.total_logs || 0).toLocaleString();

        // Render device list
        const deviceList = document.getElementById('device-list');
        if (devices.length === 0) {
            deviceList.innerHTML = '<p class="text-sm text-slate-500 py-4">No devices connected yet</p>';
        } else {
            deviceList.innerHTML = devices.map(d => `
                <div class="bg-slate-700/40 rounded-lg p-3 flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full ${d.is_online ? 'bg-emerald-400 pulse-dot' : 'bg-slate-500'}"></span>
                            <span class="text-sm font-medium text-white">${d.sn}</span>
                        </div>
                        <div class="text-xs text-slate-400 mt-1 ml-4.5">
                            IP: ${d.ip || 'Unknown'} &bull; ${d.is_online ? '<span class="text-emerald-400">Online</span>' : '<span class="text-slate-500">Offline</span>'}
                        </div>
                    </div>
                    <div class="text-right text-xs text-slate-500">
                        <div>Last seen</div>
                        <div class="text-slate-400">${d.last_seen ? timeAgo(d.last_seen) : 'never'}</div>
                    </div>
                </div>
            `).join('');
        }

        // Render endpoint stats
        const endpointEl = document.getElementById('endpoint-stats');
        const eps = data.endpoint_stats || [];
        const maxCnt = Math.max(...eps.map(e => e.cnt), 1);
        endpointEl.innerHTML = eps.map(e => {
            const pct = Math.round((e.cnt / maxCnt) * 100);
            const colorClass = e.method === 'POST' ? 'bg-emerald-500/60' : 'bg-brand-500/60';
            return `
                <div class="group">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="text-slate-300 truncate">${methodBadge(e.method)} ${e.endpoint}</span>
                        <span class="text-slate-400 font-mono ml-2">${e.cnt.toLocaleString()}</span>
                    </div>
                    <div class="h-1.5 bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full ${colorClass} rounded-full transition-all" style="width:${pct}%"></div>
                    </div>
                </div>
            `;
        }).join('');

        // Upload chart
        const uploads = data.upload_stats || {};
        renderUploadChart(uploads);

        // Find heartbeat info from endpoint stats
        const getreq = eps.find(e => e.endpoint === '/iclock/getrequest');
        if (getreq && getreq.last_hit) {
            document.getElementById('stat-heartbeat').textContent = new Date(getreq.last_hit).toLocaleTimeString();
            document.getElementById('stat-heartbeat-ago').textContent = timeAgo(getreq.last_hit);
        }
    }

    // ─── Access Events ───────────────────────────────────────────────────────
    async function loadAccessEvents(page = 1) {
        currentEventsPage = Math.max(1, page);
        const data = await api(`access-events?page=${currentEventsPage}&per_page=25`);
        if (!data) return;

        const tbody = document.getElementById('events-tbody');
        const events = data.events || [];

        const alarmCount = events.filter(e => e.event_category === 'alarm').length;
        document.getElementById('stat-alarms').textContent = alarmCount;

        if (events.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="py-8 text-center text-slate-500">No access events recorded yet</td></tr>';
        } else {
            tbody.innerHTML = events.map(e => `
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="py-2.5 pr-4 text-slate-300 whitespace-nowrap">${formatTime(e.time)}</td>
                    <td class="py-2.5 pr-4">
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-700 text-xs font-mono text-slate-300">${e.pin}</span>
                    </td>
                    <td class="py-2.5 pr-4 text-slate-300 text-xs">${e.event_label}</td>
                    <td class="py-2.5 pr-4">${categoryBadge(e.event_category)}</td>
                    <td class="py-2.5 pr-4 text-slate-400">${e.door}</td>
                    <td class="py-2.5 pr-4">${directionBadge(e.in_out)}</td>
                    <td class="py-2.5 pr-4">
                        <span class="text-xs ${e.verify_label === 'Face' ? 'text-brand-400' : 'text-slate-400'}">${e.verify_label}</span>
                    </td>
                    <td class="py-2.5">
                        ${e.mask_flag === '1' ? '<span class="text-xs text-emerald-400">Yes</span>' : '<span class="text-xs text-slate-500">No</span>'}
                    </td>
                </tr>
            `).join('');
        }

        eventsLastPage = data.last_page || 1;
        renderPager('events', currentEventsPage, eventsLastPage, data.total || 0);
    }

    // ─── Device Status ───────────────────────────────────────────────────────
    async function loadDeviceStatus(page = 1) {
        currentStatusPage = Math.max(1, page);
        const data = await api(`device-status?page=${currentStatusPage}&per_page=20`);
        if (!data) return;

        const tbody = document.getElementById('status-tbody');
        const states = data.states || [];

        if (states.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-slate-500">No status reports yet</td></tr>';
        } else {
            let rows = '';
            states.forEach(s => {
                (s.entries || []).forEach(entry => {
                    const doorOpen = entry.door === '01' || entry.door === '1';
                    const hasAlarm = entry.alarm && entry.alarm !== '0000000000000000' && entry.alarm !== '0';
                    rows += `
                        <tr class="hover:bg-slate-700/30 transition-colors ${hasAlarm ? 'bg-rose-900/10' : ''}">
                            <td class="py-2.5 pr-4 text-slate-300 whitespace-nowrap">${formatTime(entry.time)}</td>
                            <td class="py-2.5 pr-4 text-xs text-slate-400">${s.device_sn}</td>
                            <td class="py-2.5 pr-4">
                                <span class="inline-flex items-center gap-1 text-xs">
                                    <span class="w-1.5 h-1.5 rounded-full ${entry.sensor === '01' || entry.sensor === '1' ? 'bg-emerald-400' : 'bg-slate-500'}"></span>
                                    ${entry.sensor}
                                </span>
                            </td>
                            <td class="py-2.5 pr-4 text-xs text-slate-400">${entry.relay}</td>
                            <td class="py-2.5 pr-4">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium ${doorOpen ? 'bg-amber-500/10 text-amber-400' : 'bg-emerald-500/10 text-emerald-400'}">
                                    ${doorOpen ? '\uD83D\uDD13 Open' : '\uD83D\uDD12 Closed'}
                                </span>
                            </td>
                            <td class="py-2.5">
                                ${hasAlarm
                                    ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-rose-500/10 text-rose-400">⚠ ' + entry.alarm + '</span>'
                                    : '<span class="text-xs text-slate-500">None</span>'}
                            </td>
                        </tr>`;
                });
            });
            tbody.innerHTML = rows || '<tr><td colspan="6" class="py-8 text-center text-slate-500">No entries</td></tr>';
        }

        statusLastPage = data.last_page || 1;
        renderPager('status', currentStatusPage, statusLastPage, data.total || 0);
    }

    // ─── Users ───────────────────────────────────────────────────────────────
    async function loadUsers(page = 1) {
        currentUsersPage = Math.max(1, page);
        const data = await api(`users?page=${currentUsersPage}&per_page=25`);
        if (!data) return;

        const tbody = document.getElementById('users-tbody');
        const users = data.users || [];

        document.getElementById('stat-users').textContent = data.total ?? users.length;

        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="py-8 text-center text-slate-500">No users synced from device</td></tr>';
        } else {
            tbody.innerHTML = users.map(u => `
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="py-2.5 pr-4 text-xs text-slate-400 font-mono">${u.device_sn || '-'}</td>
                    <td class="py-2.5 pr-4 font-mono text-sm text-slate-300">${u.pin}</td>
                    <td class="py-2.5 pr-4 text-white font-medium">${u.name || '<span class="text-slate-500 italic">unnamed</span>'}</td>
                    <td class="py-2.5 pr-4">${privilegeBadge(u.privilege)}</td>
                    <td class="py-2.5 pr-4 text-xs text-slate-400 font-mono">${u.card || '-'}</td>
                    <td class="py-2.5 pr-4">${syncBadge(u.sync_status)}</td>
                    <td class="py-2.5 pr-4 text-xs text-slate-500">${u.synced_at || '-'}</td>
                    <td class="py-2.5 text-xs">
                        <div class="flex items-center gap-2">
                            <button onclick="editDashboardUser(${u.id})" class="px-2 py-0.5 rounded bg-brand-500/10 text-brand-300 hover:bg-brand-500/20">Edit</button>
                            <button onclick="deleteDashboardUser(${u.id}, '${u.name ? String(u.name).replace(/'/g, "\\'") : ''}', '${u.device_sn || ''}', '${u.pin || ''}')" class="px-2 py-0.5 rounded bg-rose-500/10 text-rose-300 hover:bg-rose-500/20">Delete</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        usersLastPage = data.last_page || 1;
        renderPager('users', currentUsersPage, usersLastPage, data.total || 0);
    }

    // ─── Raw Logs ────────────────────────────────────────────────────────────
    async function loadRawLogs(page = 1) {
        currentRawPage = Math.max(1, page);
        const endpoint = document.getElementById('raw-endpoint-filter').value;
        const method = document.getElementById('raw-method-filter').value;
        let url = `raw-logs?page=${currentRawPage}&per_page=25`;
        if (endpoint) url += `&endpoint=${encodeURIComponent(endpoint)}`;
        if (method) url += `&method=${method}`;

        const data = await api(url);
        if (!data) return;

        // Populate endpoint filter options on first load
        const select = document.getElementById('raw-endpoint-filter');
        if (select.options.length <= 1) {
            const statsData = await api('stats');
            if (statsData && statsData.endpoint_stats) {
                const endpoints = [...new Set(statsData.endpoint_stats.map(e => e.endpoint))];
                endpoints.forEach(ep => {
                    const opt = document.createElement('option');
                    opt.value = ep;
                    opt.textContent = ep;
                    select.appendChild(opt);
                });
            }
        }

        const tbody = document.getElementById('raw-tbody');
        const items = data.data || [];

        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-500">No logs found</td></tr>';
        } else {
            tbody.innerHTML = items.map(r => {
                const qp = typeof r.query_params === 'string' ? JSON.parse(r.query_params || '{}') : (r.query_params || {});
                const qs = Object.entries(qp).map(([k,v]) => `${k}=${v}`).join('&');
                const bodyPreview = (r.raw_body || '').substring(0, 120).replace(/</g, '&lt;');
                return `
                    <tr class="hover:bg-slate-700/30 transition-colors">
                        <td class="py-2 pr-4 text-xs text-slate-500 font-mono">${r.id}</td>
                        <td class="py-2 pr-4 text-xs text-slate-400 whitespace-nowrap">${formatTime(r.created_at)}</td>
                        <td class="py-2 pr-4 text-xs text-slate-300 font-mono">${r.device_sn || '-'}</td>
                        <td class="py-2 pr-4">${methodBadge(r.method)}</td>
                        <td class="py-2 pr-4 text-xs text-slate-300">${r.endpoint}</td>
                        <td class="py-2 pr-4 text-xs text-slate-500 font-mono max-w-[200px] truncate" title="${qs}">${qs || '-'}</td>
                        <td class="py-2 text-xs text-slate-500 max-w-[250px] truncate font-mono" title="${bodyPreview}">${bodyPreview || '-'}</td>
                    </tr>`;
            }).join('');
        }

        // Pagination
        rawLastPage = data.last_page || 1;
        renderPager('raw', data.current_page, rawLastPage, data.total || 0);
    }

    // ─── Timeline Chart ──────────────────────────────────────────────────────
    async function loadTimeline() {
        const hours = document.getElementById('timeline-hours').value;
        const data = await api(`timeline?hours=${hours}`);
        if (!data || !data.timeline) return;

        const timeline = data.timeline;
        const grouped = {};
        const allHours = new Set();

        timeline.forEach(t => {
            allHours.add(t.hour);
            if (!grouped[t.endpoint]) grouped[t.endpoint] = {};
            grouped[t.endpoint][t.hour] = t.cnt;
        });

        const labels = [...allHours].sort();
        const colorPalette = [
            'rgba(59,130,246,0.7)', 'rgba(16,185,129,0.7)', 'rgba(245,158,11,0.7)',
            'rgba(139,92,246,0.7)', 'rgba(236,72,153,0.7)', 'rgba(6,182,212,0.7)',
        ];

        const datasets = Object.entries(grouped).map(([endpoint, hours], i) => ({
            label: endpoint,
            data: labels.map(h => hours[h] || 0),
            backgroundColor: colorPalette[i % colorPalette.length],
            borderColor: colorPalette[i % colorPalette.length],
            borderWidth: 1,
            borderRadius: 2,
        }));

        const ctx = document.getElementById('timelineChart').getContext('2d');
        if (timelineChart) timelineChart.destroy();

        timelineChart = new Chart(ctx, {
            type: 'bar',
            data: { labels: labels.map(h => new Date(h).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })), datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: 'rgba(71,85,105,0.3)' } },
                    y: { stacked: true, ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: 'rgba(71,85,105,0.3)' } }
                },
                plugins: {
                    legend: { position: 'top', labels: { color: '#94a3b8', font: { size: 10 }, boxWidth: 12 } }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }

    // ─── Upload Donut ────────────────────────────────────────────────────────
    function renderUploadChart(uploads) {
        const labels = Object.keys(uploads);
        const values = Object.values(uploads);
        const colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];

        const ctx = document.getElementById('uploadChart').getContext('2d');
        if (uploadChart) uploadChart.destroy();

        if (labels.length === 0) {
            ctx.font = '14px Inter';
            ctx.fillStyle = '#475569';
            ctx.textAlign = 'center';
            ctx.fillText('No upload data yet', ctx.canvas.width / 2, ctx.canvas.height / 2);
            return;
        }

        uploadChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{ data: values, backgroundColor: colors.slice(0, labels.length), borderWidth: 0, hoverOffset: 8 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 10 }, boxWidth: 10, padding: 12 } }
                }
            }
        });
    }

    // ─── Tab Switching ───────────────────────────────────────────────────────
    function switchTab(name) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
        document.querySelectorAll('[id^="tab-"]').forEach(t => t.classList.remove('tab-active'));
        document.getElementById('panel-' + name).classList.remove('hidden');
        document.getElementById('tab-' + name).classList.add('tab-active');
    }

    // ─── Helper Functions ────────────────────────────────────────────────────

    /**
     * Render First / Prev / page-number buttons / Next / Last for any table.
     * prefix: 'events' | 'status' | 'users' | 'raw'
     */
    function renderPager(prefix, current, last, total) {
        const info = document.getElementById(`${prefix}-page-info`);
        if (info) info.textContent = `Page ${current} of ${last}  (${total.toLocaleString()} total)`;

        const setBtn = (id, disabled) => {
            const el = document.getElementById(id);
            if (el) el.disabled = disabled;
        };
        setBtn(`${prefix}-first`, current <= 1);
        setBtn(`${prefix}-prev`,  current <= 1);
        setBtn(`${prefix}-next`,  current >= last);
        setBtn(`${prefix}-last`,  current >= last);

        // Page number buttons (show up to 7 around current)
        const pagesEl = document.getElementById(`${prefix}-pages`);
        if (!pagesEl) return;
        const range = [];
        const delta = 3;
        for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) {
            range.push(i);
        }
        pagesEl.innerHTML = range.map(p => {
            const active = p === current;
            return `<button onclick="load${capitalize(prefix)}(${p})"
                class="px-2.5 py-1 rounded text-xs transition-colors ${active ? 'bg-brand-600 text-white font-semibold' : 'bg-slate-700 hover:bg-slate-600 text-slate-300'}"
                ${active ? 'disabled' : ''}>${p}</button>`;
        }).join('');
    }

    function capitalize(s) {
        const m = { events: 'AccessEvents', status: 'DeviceStatus', users: 'Users', raw: 'RawLogs' };
        return m[s] || s.charAt(0).toUpperCase() + s.slice(1);
    }

    function methodBadge(method) {
        const colors = {
            'GET': 'bg-brand-500/20 text-brand-400',
            'POST': 'bg-emerald-500/20 text-emerald-400',
            'PUT': 'bg-amber-500/20 text-amber-400',
            'DELETE': 'bg-rose-500/20 text-rose-400',
        };
        return `<span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold ${colors[method] || 'bg-slate-600 text-slate-300'}">${method}</span>`;
    }

    function categoryBadge(cat) {
        const map = {
            'access': ['bg-brand-500/10 text-brand-400', '🔑'],
            'alarm': ['bg-rose-500/10 text-rose-400', '🚨'],
            'door': ['bg-amber-500/10 text-amber-400', '🚪'],
            'system': ['bg-violet-500/10 text-violet-400', '⚙'],
        };
        const [cls, icon] = map[cat] || ['bg-slate-600 text-slate-300', '?'];
        return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium ${cls}">${icon} ${cat}</span>`;
    }

    function directionBadge(dir) {
        if (dir === 'Entry') return '<span class="text-xs text-emerald-400">↙ Entry</span>';
        if (dir === 'Exit') return '<span class="text-xs text-amber-400">↗ Exit</span>';
        return '<span class="text-xs text-slate-500">—</span>';
    }

    function privilegeBadge(priv) {
        const map = {
            'Super Admin': 'bg-rose-500/10 text-rose-400',
            'Admin': 'bg-amber-500/10 text-amber-400',
            'Enroller': 'bg-violet-500/10 text-violet-400',
            'User': 'bg-slate-600/50 text-slate-300',
        };
        return `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${map[priv] || 'bg-slate-600 text-slate-300'}">${priv}</span>`;
    }

    function syncBadge(status) {
        const map = {
            pending: ['bg-slate-600/50 text-slate-300', '◯ Pending'],
            syncing: ['bg-brand-500/10 text-brand-400', '↻ Syncing'],
            synced: ['bg-emerald-500/10 text-emerald-400', '✓ Synced'],
            failed: ['bg-rose-500/10 text-rose-400', '✕ Failed'],
        };
        const [cls, label] = map[status] || map.pending;
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold ${cls}">${label}</span>`;
    }

    function renderUsersSyncDeviceSelect() {
        const select = document.getElementById('users-device-sync');
        if (!select) return;

        select.innerHTML = '';
        if ((knownDevicesForSync || []).length === 0) {
            select.innerHTML = '<option value="">No devices</option>';
            return;
        }

        knownDevicesForSync.forEach((sn, index) => {
            const opt = document.createElement('option');
            opt.value = sn;
            opt.textContent = sn;
            if (index === 0) opt.selected = true;
            select.appendChild(opt);
        });
    }

    async function syncUsersFromDevice() {
        const deviceSn = document.getElementById('users-device-sync').value;
        if (!deviceSn) {
            toast('No device selected', 'warning');
            return;
        }

        const res = await api('sync-device-users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ device_sn: deviceSn }),
            showErrorToast: true,
        });

        if (!res) return;

        toast('Device user query queued. Waiting for device response.', 'success');
        refreshAll();
    }

    async function editDashboardUser(id) {
        const listData = await api(`users?page=${currentUsersPage}&per_page=25`);
        if (!listData) return;
        const user = (listData.users || []).find(u => Number(u.id) === Number(id));
        if (!user) {
            toast('User record was not found on this page', 'warning');
            return;
        }

        const payload = await runEditWizard(user);
        if (!payload) return;

        const res = await api(`device-users/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            showErrorToast: true,
        });

        if (!res) return;

        toast('User update queued for device sync', 'success');
        refreshAll();
    }

    async function deleteDashboardUser(id, name, deviceSn, pin) {
        const confirmResult = await Swal.fire(swalTheme({
            title: 'Queue User Delete?',
            html: `
                <div style="text-align:left;font-size:13px;line-height:1.7;">
                    <div><strong>Name:</strong> ${name || '-'}</div>
                    <div><strong>PIN:</strong> ${pin || '-'}</div>
                    <div><strong>Device:</strong> ${deviceSn || '-'}</div>
                </div>
                <p style="margin-top:10px;font-size:12px;color:#94a3b8;">Delete is queued first, then users are reconciled from the device list. The user is removed locally only after the device no longer reports that PIN.</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Queue Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#e11d48',
        }));
        if (!confirmResult.isConfirmed) return;

        const res = await api(`device-users/${id}`, {
            method: 'DELETE',
            showErrorToast: true,
        });
        if (!res) return;

        if (res.already_absent) {
            toast('User already absent locally. Refreshing from current data.', 'info');
        } else {
            toast('User delete queued. Local record removes after device reconciliation.', 'success');
        }
        refreshAll();
    }

    function privilegeCode(label) {
        const map = {
            'User': 0,
            'Enroller': 2,
            'Admin': 6,
            'Super Admin': 14,
        };
        return Object.prototype.hasOwnProperty.call(map, label) ? map[label] : 0;
    }

    function formatTime(dt) {
        if (!dt) return '-';
        const d = new Date(dt);
        if (isNaN(d)) return dt;
        return d.toLocaleString([], { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit' });
    }

    function timeAgo(dt) {
        const d = new Date(dt);
        if (isNaN(d)) return dt;
        const diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 60) return `${diff}s ago`;
        if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
        return `${Math.floor(diff/86400)}d ago`;
    }
    </script>
</body>
</html>
