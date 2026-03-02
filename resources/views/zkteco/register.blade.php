<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ZKTeco ADMS — User Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

        /* Step transitions */
        .step-panel { transition: opacity .3s ease, transform .3s ease; }
        .step-panel.entering { opacity: 0; transform: translateX(20px); }
        .step-panel.active { opacity: 1; transform: translateX(0); }

        /* Toggle switch */
        .toggle-track { transition: background-color .2s ease; }
        .toggle-thumb { transition: transform .2s ease; }
        input:checked + .toggle-track { background-color: #2563eb; }
        input:checked + .toggle-track .toggle-thumb { transform: translateX(20px); }

        /* Shimmer loading */
        .shimmer { background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%);
            background-size: 200% 100%; animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        /* Waiting dots */
        .waiting-dot { animation: waitDot 1.4s infinite ease-in-out both; }
        .waiting-dot:nth-child(1) { animation-delay: -.32s; }
        .waiting-dot:nth-child(2) { animation-delay: -.16s; }
        @keyframes waitDot { 0%,80%,100% { transform: scale(0); } 40% { transform: scale(1); } }

        /* Success checkmark */
        .success-ring { animation: successRing .4s ease-out; }
        @keyframes successRing { from { transform: scale(.5); opacity:0; } to { transform: scale(1); opacity:1; } }

        /* Command status entry */
        .cmd-entry { animation: cmdSlide .3s ease-out; }
        @keyframes cmdSlide { from { opacity:0; transform:translateX(-8px); } to { opacity:1; transform:translateX(0); } }

        /* Input focus glow */
        input:focus, select:focus { box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
    </style>
</head>
<body class="h-full bg-slate-900 text-slate-200 font-sans">
    <div id="app" class="min-h-full flex flex-col">

        <!-- ═══ Header ═══ -->
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
                            <p class="text-xs text-slate-400">User Registration &amp; Sync</p>
                        </div>
                    </div>
                    <nav class="hidden md:flex items-center gap-1">
                        <a href="/dashboard" class="px-3 py-1.5 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-700/50 transition-colors">Dashboard</a>
                        <a href="/register-user" class="px-3 py-1.5 rounded-lg text-sm font-medium text-white bg-slate-700/80">Register Users</a>
                    </nav>
                    <div class="flex items-center gap-3">
                        <button onclick="refreshAll()" class="p-2 rounded-lg hover:bg-slate-700 transition-colors" title="Refresh">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- ═══ Main Content ═══ -->
        <main class="flex-1 max-w-[1600px] mx-auto w-full px-4 sm:px-6 lg:px-8 py-6">

            <!-- Stats Cards Row -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6" id="stats-cards">
                <div class="stat-card bg-slate-800 rounded-xl p-4 border border-slate-700/50">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-violet-500/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <span class="text-xs text-slate-400 uppercase tracking-wide">Registered</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="stat-users">-</p>
                    <p class="text-xs text-slate-500 mt-1">total users</p>
                </div>
                <div class="stat-card bg-slate-800 rounded-xl p-4 border border-slate-700/50">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-xs text-slate-400 uppercase tracking-wide">Pending</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="stat-pending">-</p>
                    <p class="text-xs text-slate-500 mt-1">commands queued</p>
                </div>
                <div class="stat-card bg-slate-800 rounded-xl p-4 border border-slate-700/50">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-brand-500/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                        </div>
                        <span class="text-xs text-slate-400 uppercase tracking-wide">Sent</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="stat-sent">-</p>
                    <p class="text-xs text-slate-500 mt-1">awaiting ack</p>
                </div>
                <div class="stat-card bg-slate-800 rounded-xl p-4 border border-slate-700/50">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-xs text-slate-400 uppercase tracking-wide">Acknowledged</span>
                    </div>
                    <p class="text-2xl font-bold text-white" id="stat-acked">-</p>
                    <p class="text-xs text-slate-500 mt-1">commands confirmed</p>
                </div>
            </div>

            <!-- Registration Wizard Card -->
            <div class="bg-slate-800 rounded-xl border border-slate-700/50 mb-6 fade-in overflow-hidden">
                <!-- Card Header -->
                <div class="px-6 py-4 border-b border-slate-700/50 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        <span id="form-title">New User Registration</span>
                    </h2>
                    <button onclick="resetWizard()" id="btn-reset" class="text-xs text-slate-500 hover:text-slate-300 transition-colors hidden">
                        ← Start Over
                    </button>
                </div>

                <!-- Step Indicator -->
                <div class="px-6 pt-5 pb-2">
                    <div class="flex items-center justify-center">
                        <!-- Step 1 -->
                        <div class="flex items-center gap-2" id="step-ind-1">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center bg-brand-600 text-white font-semibold text-sm shadow-lg shadow-brand-500/25 transition-all" id="step-circle-1">1</div>
                            <span class="text-sm font-medium text-white hidden sm:inline transition-colors" id="step-label-1">User Info</span>
                        </div>
                        <!-- Connector 1→2 -->
                        <div class="w-12 sm:w-20 h-0.5 mx-2 sm:mx-3 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-brand-500 rounded-full transition-all duration-500" id="connector-1" style="width:0%"></div>
                        </div>
                        <!-- Step 2 -->
                        <div class="flex items-center gap-2" id="step-ind-2">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center bg-slate-700 text-slate-400 font-semibold text-sm transition-all" id="step-circle-2">2</div>
                            <span class="text-sm font-medium text-slate-400 hidden sm:inline transition-colors" id="step-label-2">Device Sync</span>
                        </div>
                        <!-- Connector 2→3 -->
                        <div class="w-12 sm:w-20 h-0.5 mx-2 sm:mx-3 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-brand-500 rounded-full transition-all duration-500" id="connector-2" style="width:0%"></div>
                        </div>
                        <!-- Step 3 -->
                        <div class="flex items-center gap-2" id="step-ind-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center bg-slate-700 text-slate-400 font-semibold text-sm transition-all" id="step-circle-3">3</div>
                            <span class="text-sm font-medium text-slate-400 hidden sm:inline transition-colors" id="step-label-3">Complete</span>
                        </div>
                    </div>
                </div>

                <!-- Step Panels -->
                <div class="px-6 pb-6 pt-2">

                    <!-- ═══ STEP 1: User Info Form ═══ -->
                    <div id="panel-step-1" class="step-panel active">
                        <form id="reg-form" onsubmit="handleSubmit(event)" class="max-w-3xl mx-auto">
                            <!-- Device Selector -->
                            <div class="mb-5">
                                <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wide">Device <span class="text-rose-400">*</span></label>
                                <select id="f-device" required class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2.5 text-sm text-white focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-colors">
                                    <option value="">Loading devices...</option>
                                </select>
                                <p class="text-xs text-slate-500 mt-1">Select the target ZKTeco device for this user</p>
                            </div>

                            <!-- PIN + Name row -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wide">PIN <span class="text-rose-400">*</span></label>
                                    <div class="relative">
                                        <input type="number" id="f-pin" min="1" required class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-colors" placeholder="Auto">
                                        <button type="button" onclick="autoFillPin()" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-brand-400 hover:text-brand-300 font-medium" title="Generate next available PIN">Auto</button>
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wide">Full Name <span class="text-rose-400">*</span></label>
                                    <input type="text" id="f-name" required maxlength="100" class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-colors" placeholder="John Doe">
                                </div>
                            </div>

                            <!-- Privilege + Card + Group -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wide">Privilege</label>
                                    <select id="f-privilege" class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2.5 text-sm text-white focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-colors">
                                        <option value="0" selected>User</option>
                                        <option value="2">Enroller</option>
                                        <option value="6">Admin</option>
                                        <option value="14">Super Admin</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wide">Card Number</label>
                                    <input type="text" id="f-card" maxlength="50" class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-colors" placeholder="Optional">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wide">Group ID</label>
                                    <input type="number" id="f-group" min="1" max="99" value="1" class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-colors">
                                </div>
                            </div>

                            <!-- Submit -->
                            <div class="flex items-center justify-end gap-3">
                                <button type="button" id="btn-cancel-edit" onclick="cancelEditMode()" class="px-5 py-2.5 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-700 transition-colors hidden">Cancel Edit</button>
                                <button type="button" onclick="resetWizard()" class="px-5 py-2.5 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">Cancel</button>
                                <button type="submit" id="btn-submit" class="px-6 py-2.5 rounded-lg text-sm font-semibold text-white bg-brand-600 hover:bg-brand-500 shadow-lg shadow-brand-600/25 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    Register &amp; Sync
                                </button>
                            </div>

                            <!-- Validation Errors -->
                            <div id="form-errors" class="mt-4 hidden">
                                <div class="bg-rose-500/10 border border-rose-500/20 rounded-lg p-3">
                                    <p class="text-sm text-rose-400 font-medium mb-1">Please fix the following errors:</p>
                                    <ul id="form-errors-list" class="text-xs text-rose-300 list-disc list-inside space-y-0.5"></ul>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- ═══ STEP 2: Device Sync ═══ -->
                    <div id="panel-step-2" class="step-panel hidden">
                        <div class="max-w-3xl mx-auto">
                            <!-- User summary banner -->
                            <div class="bg-slate-700/30 rounded-xl p-4 mb-6 flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-brand-600/20 flex items-center justify-center text-brand-400 font-bold text-lg" id="sync-avatar">?</div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-white" id="sync-name">-</p>
                                    <p class="text-xs text-slate-400">PIN: <span id="sync-pin" class="font-mono text-slate-300">-</span> &bull; Device: <span id="sync-device" class="font-mono text-slate-300">-</span></p>
                                </div>
                                <div id="sync-overall-badge" class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400">
                                    SYNCING
                                </div>
                            </div>

                            <!-- Command Timeline -->
                            <h3 class="text-sm font-semibold text-slate-300 mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                Command Queue
                            </h3>
                            <div id="cmd-list" class="space-y-2 mb-6">
                                <div class="shimmer rounded-lg h-14"></div>
                                <div class="shimmer rounded-lg h-14"></div>
                            </div>

                            <!-- Info tip -->
                            <div class="bg-brand-500/5 border border-brand-500/10 rounded-lg p-3 mb-6 flex items-start gap-2">
                                <svg class="w-4 h-4 text-brand-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-xs text-slate-400">Commands are delivered when the device polls the server (typically every 30-60 seconds). Status updates automatically.</p>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-between mt-6">
                                <button onclick="goToStep(1)" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-700 transition-colors flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    Back
                                </button>
                                <button onclick="goToStep(3)" id="btn-complete" class="px-6 py-2.5 rounded-lg text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 shadow-lg shadow-emerald-600/25 transition-all flex items-center gap-2">
                                    Complete
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ STEP 3: Complete ═══ -->
                    <div id="panel-step-3" class="step-panel hidden">
                        <div class="max-w-lg mx-auto text-center py-8">
                            <!-- Success animation -->
                            <div class="success-ring w-20 h-20 mx-auto mb-5 rounded-full bg-emerald-500/10 border-2 border-emerald-500/30 flex items-center justify-center">
                                <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2">Registration Complete!</h3>
                            <p class="text-sm text-slate-400 mb-1">User has been registered and sync commands are queued.</p>
                            <p class="text-xs text-slate-500 mb-6" id="complete-summary">-</p>

                            <!-- Summary card -->
                            <div class="bg-slate-700/30 rounded-xl p-5 mb-6 text-left inline-block w-full max-w-sm">
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between"><span class="text-slate-400">Name</span><span class="text-white font-medium" id="cpl-name">-</span></div>
                                    <div class="flex justify-between"><span class="text-slate-400">PIN</span><span class="text-white font-mono" id="cpl-pin">-</span></div>
                                    <div class="flex justify-between"><span class="text-slate-400">Device</span><span class="text-white font-mono text-xs" id="cpl-device">-</span></div>
                                </div>
                            </div>

                            <div class="flex items-center justify-center gap-3">
                                <a href="/dashboard" class="px-5 py-2.5 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">Go to Dashboard</a>
                                <button onclick="resetWizard()" class="px-6 py-2.5 rounded-lg text-sm font-semibold text-white bg-brand-600 hover:bg-brand-500 shadow-lg shadow-brand-600/25 transition-all flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    Register Another
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registered Users Table -->
            <div class="bg-slate-800 rounded-xl border border-slate-700/50 fade-in">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700/50">
                    <h3 class="text-sm font-semibold text-slate-300 uppercase tracking-wide flex items-center gap-2">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Registered Users
                    </h3>
                    <span class="text-xs text-slate-500" id="users-total-label">-</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-slate-400 uppercase tracking-wider border-b border-slate-700/50">
                                <th class="py-3 px-5">User</th>
                                <th class="py-3 px-4">PIN</th>
                                <th class="py-3 px-4">Device</th>
                                <th class="py-3 px-4">Privilege</th>
                                <th class="py-3 px-4">Sync</th>
                                <th class="py-3 px-4">Registered</th>
                                <th class="py-3 px-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody" class="divide-y divide-slate-700/30">
                            <tr><td colspan="7" class="py-8 text-center text-slate-500">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="flex items-center justify-between px-5 py-3 border-t border-slate-700/50">
                    <span id="users-page-info" class="text-xs text-slate-500">-</span>
                    <div class="flex items-center gap-1">
                        <button id="users-prev" onclick="loadUsers(currentUsersPage-1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled>Prev</button>
                        <span id="users-pages" class="flex gap-1"></span>
                        <button id="users-next" onclick="loadUsers(currentUsersPage+1)" class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed text-xs" disabled>Next</button>
                    </div>
                </div>
            </div>

        </main>

        <!-- Footer -->
        <footer class="text-center py-4 text-xs text-slate-600 border-t border-slate-800">
            ZKTeco ADMS Server &bull; Security PUSH Protocol v3.1.2 &bull; Laravel {{ app()->version() }}
        </footer>
    </div>

    <!-- Toast Container -->
    <div id="toasts" class="fixed top-20 right-4 z-[100] space-y-2 pointer-events-none"></div>

    <script>
    // ═══════════════════════════════════════════════════════════════════
    // State
    // ═══════════════════════════════════════════════════════════════════
    let currentStep = 1;
    let registeredUser = null;
    let knownDevices = [];
    let currentUsersPage = 1;
    let usersLastPage = 1;
    let pollTimerId = null;
    let editingUserId = null;
    let currentUsersMap = new Map();

    // ═══════════════════════════════════════════════════════════════════
    // Init
    // ═══════════════════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', async () => {
        await Promise.all([loadKnownDevices(), loadStats(), loadUsers(1)]);
        autoFillPin();
    });

    function refreshAll() {
        loadStats();
        loadUsers(currentUsersPage);
    }

    // ═══════════════════════════════════════════════════════════════════
    // API Helper
    // ═══════════════════════════════════════════════════════════════════
    async function api(path, options = {}) {
        try {
            const res = await fetch('/api/zkteco/' + path, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, ...options.headers },
                ...options,
            });
            const data = await res.json();
            if (!res.ok) {
                if (res.status === 422 && data.errors) return { _validationErrors: data.errors };
                throw new Error(data.message || `HTTP ${res.status}`);
            }
            return data;
        } catch (e) {
            console.error('API error:', path, e);
            showToast(e.message, 'error');
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Load Known Devices
    // ═══════════════════════════════════════════════════════════════════
    async function loadKnownDevices() {
        const data = await api('known-devices');
        if (!data) return;
        knownDevices = data.devices || [];

        const select = document.getElementById('f-device');
        select.innerHTML = '';
        if (knownDevices.length === 0) {
            select.innerHTML = '<option value="">No devices detected yet</option>';
        } else {
            knownDevices.forEach((sn, i) => {
                const opt = document.createElement('option');
                opt.value = sn;
                opt.textContent = sn;
                if (i === 0) opt.selected = true;
                select.appendChild(opt);
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Load Stats
    // ═══════════════════════════════════════════════════════════════════
    async function loadStats() {
        const data = await api('registration-stats');
        if (!data) return;
        document.getElementById('stat-users').textContent = data.total_users ?? '-';
        document.getElementById('stat-pending').textContent = data.commands?.pending ?? '-';
        document.getElementById('stat-sent').textContent = data.commands?.sent ?? '-';
        document.getElementById('stat-acked').textContent = data.commands?.acked ?? '-';
    }

    // ═══════════════════════════════════════════════════════════════════
    // Load Users Table
    // ═══════════════════════════════════════════════════════════════════
    let allUserPins = [];

    async function loadUsers(page = 1) {
        currentUsersPage = Math.max(1, page);
        const data = await api(`device-users-list?page=${currentUsersPage}&per_page=15`);
        if (!data) return;

        const tbody = document.getElementById('users-tbody');
        const users = data.users || [];

        // Track all pins for auto-fill
        if (currentUsersPage === 1) {
            allUserPins = users.map(u => u.pin);
        }

        document.getElementById('users-total-label').textContent = `${data.total ?? 0} users`;

        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-slate-500"><div class="flex flex-col items-center gap-2"><svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg><p>No users registered yet</p><p class="text-xs">Use the form above to register your first user</p></div></td></tr>';
            return;
        }

        currentUsersMap = new Map(users.map(u => [u.id, u]));

        tbody.innerHTML = users.map(u => {
            const initial = (u.name || '?').charAt(0).toUpperCase();
            const hue = hashStringToHue(u.name || '');
            return `
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="py-3 px-5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0" style="background: hsl(${hue}, 40%, 35%)">${initial}</div>
                            <span class="text-sm font-medium text-white truncate max-w-[140px]">${escHtml(u.name)}</span>
                        </div>
                    </td>
                    <td class="py-3 px-4 font-mono text-sm text-slate-300">${u.pin}</td>
                    <td class="py-3 px-4 text-xs text-slate-400 font-mono">${u.device_sn}</td>
                    <td class="py-3 px-4">${privilegeBadge(u.privilege)}</td>
                    <td class="py-3 px-4">${syncBadge(u.sync_status)}</td>
                    <td class="py-3 px-4 text-xs text-slate-500">${timeAgo(u.created_at)}</td>
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-2">
                            <button onclick="editUser(${u.id})" class="px-2.5 py-1 rounded text-xs font-medium bg-brand-500/10 text-brand-300 hover:bg-brand-500/20">Edit</button>
                            <button onclick="deleteUser(${u.id})" class="px-2.5 py-1 rounded text-xs font-medium bg-rose-500/10 text-rose-300 hover:bg-rose-500/20">Delete</button>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        usersLastPage = data.last_page || 1;
        renderPager(currentUsersPage, usersLastPage, data.total || 0);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Form Submission
    // ═══════════════════════════════════════════════════════════════════
    async function handleSubmit(e) {
        e.preventDefault();
        hideErrors();

        const btn = document.getElementById('btn-submit');
        btn.disabled = true;
        const isEditing = editingUserId !== null;
        btn.innerHTML = isEditing
            ? '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Saving...'
            : '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Registering...';

        const payload = {
            device_sn: document.getElementById('f-device').value,
            pin: parseInt(document.getElementById('f-pin').value),
            name: document.getElementById('f-name').value.trim(),
            privilege: parseInt(document.getElementById('f-privilege').value),
            card_no: document.getElementById('f-card').value.trim() || null,
            group_id: parseInt(document.getElementById('f-group').value) || 1,
        };

        const endpoint = isEditing ? `device-users/${editingUserId}` : 'register-user';
        const method = isEditing ? 'PUT' : 'POST';

        const data = await api(endpoint, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        btn.disabled = false;
        btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Register & Sync';

        if (!data) return;
        if (data._validationErrors) {
            showErrors(data._validationErrors);
            return;
        }

        registeredUser = data.user;
        showToast(isEditing ? `${registeredUser.name} update queued for sync` : `${registeredUser.name} registered successfully!`, 'success');
        loadStats();
        loadUsers(1);
        goToStep(2);
        startCommandPolling();

        if (isEditing) {
            clearEditMode();
        }
    }

    function editUser(id) {
        const user = currentUsersMap.get(id);
        if (!user) return;

        editingUserId = id;
        document.getElementById('form-title').textContent = 'Edit User';
        document.getElementById('btn-cancel-edit').classList.remove('hidden');

        const deviceSelect = document.getElementById('f-device');
        const pinInput = document.getElementById('f-pin');

        deviceSelect.value = user.device_sn;
        deviceSelect.disabled = true;
        pinInput.value = user.pin;
        pinInput.readOnly = true;

        document.getElementById('f-name').value = user.name ?? '';
        document.getElementById('f-privilege').value = String(user.privilege ?? 0);
        document.getElementById('f-card').value = user.card_no ?? '';
        document.getElementById('f-group').value = user.group_id ?? 1;

        goToStep(1);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function clearEditMode() {
        editingUserId = null;
        document.getElementById('form-title').textContent = 'New User Registration';
        document.getElementById('btn-cancel-edit').classList.add('hidden');
        document.getElementById('f-device').disabled = false;
        document.getElementById('f-pin').readOnly = false;
    }

    function cancelEditMode() {
        clearEditMode();
        resetWizard();
    }

    async function deleteUser(id) {
        const user = currentUsersMap.get(id);
        if (!user) return;

        const confirmResult = await Swal.fire(swalTheme({
            title: 'Queue User Delete?',
            html: `
                <div style="text-align:left;font-size:13px;line-height:1.7;">
                    <div><strong>Name:</strong> ${escHtml(user.name || '-')}</div>
                    <div><strong>PIN:</strong> ${user.pin || '-'}</div>
                    <div><strong>Device:</strong> ${escHtml(user.device_sn || '-')}</div>
                </div>
                <p style="margin-top:10px;font-size:12px;color:#94a3b8;">Delete is queued to the device, then a user query runs to reconcile. The local record is removed only after the device no longer reports the user.</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Queue Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#e11d48',
        }));

        if (!confirmResult.isConfirmed) return;

        const res = await api(`device-users/${id}`, { method: 'DELETE' });
        if (!res) return;

        if (res.already_absent) {
            showToast('User already absent locally. Refreshing list.', 'info');
        } else {
            showToast('Delete queued. Record is removed after device reconciliation.', 'info');
        }

        if (editingUserId === id) {
            clearEditMode();
            resetWizard();
        }

        loadUsers(currentUsersPage);
        loadStats();
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

    // ═══════════════════════════════════════════════════════════════════
    // Step Navigation
    // ═══════════════════════════════════════════════════════════════════
    function goToStep(step) {
        if (step === currentStep) return;

        // Hide current
        const cur = document.getElementById(`panel-step-${currentStep}`);
        if (cur) cur.classList.add('hidden');

        currentStep = step;

        // Show new
        const next = document.getElementById(`panel-step-${step}`);
        if (next) {
            next.classList.remove('hidden');
            next.classList.add('entering');
            requestAnimationFrame(() => {
                next.classList.remove('entering');
                next.classList.add('active');
            });
        }

        // Update step indicators
        updateStepIndicators(step);

        // Show/hide reset button
        document.getElementById('btn-reset').classList.toggle('hidden', step === 1);

        // Populate step content
        if (step === 2 && registeredUser) populateStep2();
        if (step === 3 && registeredUser) populateStep3();

        // Stop polling when going back to step 1
        if (step === 1) {
            stopAllPolling();
        }
    }

    function updateStepIndicators(active) {
        for (let i = 1; i <= 3; i++) {
            const circle = document.getElementById(`step-circle-${i}`);
            const label = document.getElementById(`step-label-${i}`);

            if (i < active) {
                // Completed
                circle.className = 'w-9 h-9 rounded-full flex items-center justify-center bg-emerald-500 text-white font-semibold text-sm shadow-lg shadow-emerald-500/25 transition-all';
                circle.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';
                if (label) label.className = 'text-sm font-medium text-emerald-400 hidden sm:inline transition-colors';
            } else if (i === active) {
                // Current
                circle.className = 'w-9 h-9 rounded-full flex items-center justify-center bg-brand-600 text-white font-semibold text-sm shadow-lg shadow-brand-500/25 transition-all';
                circle.textContent = i;
                if (label) label.className = 'text-sm font-medium text-white hidden sm:inline transition-colors';
            } else {
                // Future
                circle.className = 'w-9 h-9 rounded-full flex items-center justify-center bg-slate-700 text-slate-400 font-semibold text-sm transition-all';
                circle.textContent = i;
                if (label) label.className = 'text-sm font-medium text-slate-400 hidden sm:inline transition-colors';
            }
        }
        // Connectors
        document.getElementById('connector-1').style.width = active > 1 ? '100%' : '0%';
        document.getElementById('connector-2').style.width = active > 2 ? '100%' : '0%';
    }

    // ═══════════════════════════════════════════════════════════════════
    // Step 2: Populate & Poll
    // ═══════════════════════════════════════════════════════════════════
    function populateStep2() {
        if (!registeredUser) return;
        const u = registeredUser;
        document.getElementById('sync-avatar').textContent = (u.name || '?').charAt(0).toUpperCase();
        document.getElementById('sync-name').textContent = u.name;
        document.getElementById('sync-pin').textContent = u.pin;
        document.getElementById('sync-device').textContent = u.device_sn;
    }

    function startCommandPolling() {
        if (!registeredUser) return;
        pollCommands(); // immediate first call
        pollTimerId = setInterval(pollCommands, 3000);
    }

    async function pollCommands() {
        if (!registeredUser) return;
        const data = await api(`command-status/${registeredUser.device_sn}/${registeredUser.pin}`);
        if (!data) return;

        renderCommandList(data.commands || []);

        // Update overall badge
        const s = data.summary || {};
        const badge = document.getElementById('sync-overall-badge');
        if (s.failed > 0) {
            badge.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400';
            badge.textContent = 'FAILED';
        } else if (s.acked === s.total && s.total > 0) {
            badge.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400';
            badge.textContent = 'ALL SYNCED';
            clearInterval(pollTimerId);
            setTimeout(() => goToStep(3), 1500);
        } else if (s.sent > 0 || s.acked > 0) {
            badge.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-brand-500/10 text-brand-400';
            badge.textContent = 'SYNCING';
        } else {
            badge.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400';
            badge.textContent = 'PENDING';
        }

        // Also refresh stats & user table periodically
        loadStats();
    }

    function renderCommandList(commands) {
        const container = document.getElementById('cmd-list');
        const mergedCommands = mergeCommandsForView(commands || []);

        if (mergedCommands.length === 0) {
            container.innerHTML = '<p class="text-sm text-slate-500 py-4">No commands found</p>';
            return;
        }

        container.innerHTML = mergedCommands.map(cmd => {
            const icon = statusIcon(cmd.status);
            const badge = statusBadge(cmd.status);
            const typeLabel = commandTypeLabel(cmd.type);
            const channelLabel = cmd.channels.map(ch => ch === 'service_control' ? 'service' : ch).join('+');
            let timeInfo = '';
            if (cmd.acknowledged_at) timeInfo = `Acked: ${formatTime(cmd.acknowledged_at)}`;
            else if (cmd.sent_at) timeInfo = `Sent: ${formatTime(cmd.sent_at)}`;
            else timeInfo = `Queued: ${formatTime(cmd.created_at)}`;

            return `
                <div class="cmd-entry flex items-start gap-3 p-3 rounded-lg bg-slate-700/30 border border-slate-700/50">
                    <div class="mt-0.5 shrink-0">${icon}</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span class="text-sm font-medium text-slate-200">${typeLabel}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-slate-500 uppercase font-medium px-1.5 py-0.5 rounded bg-slate-700/50">${channelLabel}</span>
                                ${badge}
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">${timeInfo}</p>
                    </div>
                </div>`;
        }).join('');
    }

    function mergeCommandsForView(commands) {
        const grouped = [];
        const windowMs = 15000;

        for (const cmd of commands) {
            const key = `${cmd.type}::${cmd.command}`;
            const ts = cmd.created_at ? new Date(cmd.created_at).getTime() : 0;

            const existing = grouped.find(item =>
                item.key === key &&
                Math.abs(item.created_ts - ts) <= windowMs &&
                !item.channels.includes(cmd.channel)
            );

            if (!existing) {
                grouped.push({
                    key,
                    type: cmd.type,
                    command: cmd.command,
                    status: cmd.status,
                    channels: [cmd.channel],
                    sent_at: cmd.sent_at,
                    acknowledged_at: cmd.acknowledged_at,
                    created_at: cmd.created_at,
                    created_ts: ts,
                });
                continue;
            }

            existing.channels = Array.from(new Set([...existing.channels, cmd.channel]));
            existing.status = mergeStatus(existing.status, cmd.status);
            existing.sent_at = pickLatestTime(existing.sent_at, cmd.sent_at);
            existing.acknowledged_at = pickLatestTime(existing.acknowledged_at, cmd.acknowledged_at);
            existing.created_at = pickLatestTime(existing.created_at, cmd.created_at);
        }

        return grouped.map(({ key: _key, created_ts: _createdTs, ...item }) => item);
    }

    function mergeStatus(a, b) {
        const rank = { pending: 1, sent: 2, failed: 3, acked: 4 };
        return (rank[b] ?? 0) > (rank[a] ?? 0) ? b : a;
    }

    function pickLatestTime(a, b) {
        if (!a) return b;
        if (!b) return a;
        return new Date(a) >= new Date(b) ? a : b;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Step 3: Complete
    // ═══════════════════════════════════════════════════════════════════
    function populateStep3() {
        if (!registeredUser) return;
        const u = registeredUser;
        document.getElementById('cpl-name').textContent = u.name;
        document.getElementById('cpl-pin').textContent = u.pin;
        document.getElementById('cpl-device').textContent = u.device_sn;
        document.getElementById('complete-summary').textContent =
            `${u.name} (PIN: ${u.pin}) → ${u.device_sn}`;

        stopAllPolling();
        loadUsers(1);
        loadStats();
    }

    // ═══════════════════════════════════════════════════════════════════
    // Reset Wizard
    // ═══════════════════════════════════════════════════════════════════
    function resetWizard() {
        stopAllPolling();
        registeredUser = null;
        clearEditMode();

        // Reset form
        document.getElementById('reg-form').reset();
        hideErrors();

        // Go to step 1
        goToStep(1);
        autoFillPin();

        // Reset step indicators
        for (let i = 1; i <= 3; i++) {
            const circle = document.getElementById(`step-circle-${i}`);
            circle.textContent = i;
        }
    }

    function stopAllPolling() {
        if (pollTimerId) { clearInterval(pollTimerId); pollTimerId = null; }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Auto-fill PIN
    // ═══════════════════════════════════════════════════════════════════
    function autoFillPin() {
        const maxPin = allUserPins.length > 0 ? Math.max(...allUserPins) : 0;
        document.getElementById('f-pin').value = maxPin + 1;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Error Handling
    // ═══════════════════════════════════════════════════════════════════
    function showErrors(errors) {
        const container = document.getElementById('form-errors');
        const list = document.getElementById('form-errors-list');
        list.innerHTML = '';
        Object.values(errors).flat().forEach(msg => {
            const li = document.createElement('li');
            li.textContent = msg;
            list.appendChild(li);
        });
        container.classList.remove('hidden');
    }

    function hideErrors() {
        document.getElementById('form-errors').classList.add('hidden');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Pagination
    // ═══════════════════════════════════════════════════════════════════
    function renderPager(current, last, total) {
        document.getElementById('users-page-info').textContent = `Page ${current} of ${last} (${total} total)`;
        document.getElementById('users-prev').disabled = current <= 1;
        document.getElementById('users-next').disabled = current >= last;

        const pagesEl = document.getElementById('users-pages');
        const range = [];
        const delta = 2;
        for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) {
            range.push(i);
        }
        pagesEl.innerHTML = range.map(p => {
            const isActive = p === current;
            return `<button onclick="loadUsers(${p})" class="px-2.5 py-1 rounded text-xs transition-colors ${isActive ? 'bg-brand-600 text-white font-semibold' : 'bg-slate-700 hover:bg-slate-600 text-slate-300'}" ${isActive ? 'disabled' : ''}>${p}</button>`;
        }).join('');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Badge Helpers
    // ═══════════════════════════════════════════════════════════════════
    function statusIcon(status) {
        const map = {
            pending: '<span class="w-6 h-6 rounded-full flex items-center justify-center bg-slate-600/50"><span class="w-2 h-2 rounded-full bg-slate-400"></span></span>',
            sent: '<span class="w-6 h-6 rounded-full flex items-center justify-center bg-brand-500/20"><svg class="w-3.5 h-3.5 text-brand-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></span>',
            acked: '<span class="w-6 h-6 rounded-full flex items-center justify-center bg-emerald-500/20"><svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></span>',
            failed: '<span class="w-6 h-6 rounded-full flex items-center justify-center bg-rose-500/20"><svg class="w-3.5 h-3.5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg></span>',
        };
        return map[status] || map.pending;
    }

    function statusBadge(status) {
        const map = {
            pending: 'bg-slate-600/50 text-slate-300',
            sent: 'bg-brand-500/10 text-brand-400',
            acked: 'bg-emerald-500/10 text-emerald-400',
            failed: 'bg-rose-500/10 text-rose-400',
        };
        const cls = map[status] || map.pending;
        return `<span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded-full ${cls}">${status}</span>`;
    }

    function commandTypeLabel(type) {
        const map = {
            user_sync: 'User Data Sync',
            face_push: 'Face Template Push',
            fingerprint_push: 'Fingerprint Template Push',
            user_delete: 'User Delete',
            other: 'Command',
        };
        return map[type] || type;
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

    function privilegeBadge(priv) {
        const map = {
            0: ['bg-slate-600/50 text-slate-300', 'User'],
            2: ['bg-violet-500/10 text-violet-400', 'Enroller'],
            6: ['bg-amber-500/10 text-amber-400', 'Admin'],
            14: ['bg-rose-500/10 text-rose-400', 'Super Admin'],
        };
        const [cls, label] = map[priv] || map[0];
        return `<span class="inline-flex px-2 py-0.5 rounded text-[10px] font-medium ${cls}">${label}</span>`;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Toast Notifications
    // ═══════════════════════════════════════════════════════════════════
    function showToast(message, type = 'info') {
        const container = document.getElementById('toasts');
        const colors = {
            success: 'bg-emerald-600 border-emerald-500',
            error: 'bg-rose-600 border-rose-500',
            info: 'bg-brand-600 border-brand-500',
            warning: 'bg-amber-600 border-amber-500',
        };
        const icons = {
            success: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>',
            error: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>',
            info: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            warning: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        };

        const toast = document.createElement('div');
        toast.className = `pointer-events-auto flex items-center gap-2 px-4 py-2.5 rounded-lg border text-white text-sm font-medium shadow-lg ${colors[type] || colors.info} transition-all duration-300 translate-x-full opacity-0`;
        toast.innerHTML = `${icons[type] || icons.info}<span>${escHtml(message)}</span>`;
        container.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
        });

        // Auto-dismiss
        setTimeout(() => {
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Utility
    // ═══════════════════════════════════════════════════════════════════
    function formatTime(dt) {
        if (!dt) return '-';
        const d = new Date(dt);
        if (isNaN(d)) return dt;
        return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function timeAgo(dt) {
        if (!dt) return '-';
        const d = new Date(dt);
        if (isNaN(d)) return dt;
        const diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 60) return `${diff}s ago`;
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        return `${Math.floor(diff / 86400)}d ago`;
    }

    function hashStringToHue(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        return Math.abs(hash) % 360;
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    </script>
</body>
</html>
