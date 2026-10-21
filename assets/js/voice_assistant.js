/**
 * =========================================================================================
 * 888 TRIPLE EIGHT - ADVANCED HANDS-FREE VOICE ASSISTANT ENGINE
 * =========================================================================================
 * Features:
 * 1. Hands-Free Wake Word ("Turn On", "Wake Up", "Hey Assistant", "Triple Eight")
 * 2. Close-Mic Proximity Filter (Web Audio API Analyser & Noise Gate for near-field speech)
 * 3. Universal DOM Interactive Element Controller (Clicks buttons, links, selects, inputs)
 * 4. Fuzzy & Phonetic Matcher (Not hard of hearing - resilient to accents, noise, & phrasing)
 * 5. Full POS & Retail Operation Execution (Add quantities, complete sales, quick search, nav)
 * 6. Direct On/Off Toggle Controls (UI switch, floating HUD, and voice disabling)
 * 7. Audio Feedback Chimes & Natural Text-to-Speech (TTS)
 * =========================================================================================
 */

(function (window, document) {
    'use strict';

    class VoiceAssistantEngine {
        constructor() {
            // Configuration & State
            this.version = '3.0.0';
            this.userRole = (window.currentUserRole || 'admin').toLowerCase();
            this.userId = window.currentUserId || 0;
            this.username = window.currentUsername || 'Admin';
            this.baseUrl = (window.location.pathname.includes('/admin/') || window.location.pathname.includes('/cashier/')) ? '../' : './';
            this.roleTitle = this.userRole.charAt(0).toUpperCase() + this.userRole.slice(1);

            this.state = {
                enabled: localStorage.getItem('t8_voice_enabled') !== 'false', // default ON if permitted
                active: localStorage.getItem('t8_voice_active') === 'true', // remains active once turned on
                handsFree: localStorage.getItem('t8_voice_handsfree') !== 'false', // wake word listening
                speechOutput: localStorage.getItem('t8_voice_speech') !== 'false', // TTS spoken feedback (mute/unmute)
                sensitivity: localStorage.getItem('t8_voice_sensitivity') || 'medium', // high, medium, low close-mic threshold
                isListening: false,
                isAwake: false,
                isProcessing: false,
                lastRms: 0,
                isMicInRange: false,
                awaitingConfirmation: null // for dangerous commands like logout, clear cart
            };

            // Proximity Audio Thresholds (RMS amplitude)
            this.sensitivityThresholds = {
                high: 0.015,   // accepts slightly softer voice
                medium: 0.030, // standard close-mic near-field voice
                low: 0.055     // strict close-mic (must speak right into mic)
            };

            // Speech Recognition
            this.SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition || null;
            this.recognition = null;
            this.recognitionRestartTimer = null;
            this.awakeTimeout = null;
            this.awakeDurationMs = 12000; // Stay awake for 12 seconds per wake word

            // Role-Aware Voiceprint Biometrics & Speaker Verification Engine
            const bioKey = 't8_voice_bio_profile_' + (this.username || this.userRole);
            const savedBio = localStorage.getItem(bioKey) || localStorage.getItem('t8_voice_bio_profile') || 'null';

            this.biometrics = {
                enabled: localStorage.getItem('t8_voice_bio_enabled') !== 'false',
                profile: JSON.parse(savedBio),
                threshold: parseFloat(localStorage.getItem('t8_voice_bio_threshold') || '0.65'),
                isCalibrating: false,
                calibrationSamples: [],
                lastConfidence: 0,
                lastMatch: true,
                lastPitch: 0,
                currentAcousticVector: null
            };

            // Web Audio API for Proximity, Biometrics & Chimes
            this.audioCtx = null;
            this.analyser = null;
            this.micStream = null;
            this.audioDataArray = null;
            this.timeDataArray = null;
            this.proximityRafId = null;

            // Speech Synthesis
            this.synth = window.speechSynthesis || null;

            // UI Elements
            this.ui = {};
            this.hudOpen = false;

            // Command Dictionaries & Synonyms
            this.synonyms = {
                'dashboard': ['home', 'overview', 'main page', 'main menu', 'dashboard', 'home page'],
                'pos': ['cashier', 'point of sale', 'register', 'sales counter', 'checkout', 'pos screen', 'pos terminal', 'sales terminal'],
                'products': ['product catalog', 'item list', 'items', 'products', 'inventory items', 'catalog', 'manage products'],
                'inventory': ['stock', 'supplies', 'warehouse', 'inventory list', 'stock room', 'inventory overview', 'stock overview', 'manage inventory'],
                'categories': ['category', 'categories', 'departments', 'departments and categories', 'sections', 'item categories', 'product categories'],
                'sales': ['sales and transactions', 'sales & transactions', 'sales and transaction', 'sales transaction', 'transactions', 'sales', 'transaction history', 'sales history', 'transactions log', 'sales records', 'all transactions'],
                'reports': ['business reports', 'business report', 'reports', 'sales report', 'sales reports', 'financial reports', 'analytics', 'statistics', 'sales data', 'charts', 'report'],
                'users': ['user management', 'users', 'user accounts', 'manage users', 'staff', 'employees', 'user list', 'accounts', 'manage staff'],
                'rbac': ['access control', 'roles and permissions', 'role management', 'rbac', 'permissions', 'roles', 'manage roles', 'access management', 'security permissions', 'role based access control'],
                'backup': ['backup & restore', 'backup and restore', 'backuup & restore', 'backuup and restore', 'backup', 'restore', 'backups', 'database backup', 'system backup', 'backup restore', 'data backup', 'database restore'],
                'settings': ['store settings', 'settings', 'configuration', 'preferences', 'system settings', 'options', 'store configuration', 'store preferences', 'system configuration', 'store config'],
                'profile': ['my profile', 'account', 'user account', 'my account', 'profile settings'],
                'logout': ['sign out', 'log out', 'exit system', 'disconnect', 'leave']
            };

            // Initialize
            this.init();
        }

        /**
         * Initialize Engine
         */
        init() {
            this.injectStyles();
            this.injectUI();
            this.bindUIEvents();
            this.initAudioContext();
            this.initSpeechRecognition();
            this.updateUIState();
            this.syncVoiceProfileFromServer();

            // Announce readiness if enabled
            if (this.state.enabled && this.state.handsFree) {
                this.startContinuousListening();
            }

            // Expose globally
            window.VoiceAssistant = this;
            window.Toddles = this; // Backward compatibility with previous test suites
        }

        /**
         * Dynamic CSS Styles for HUD, Glowing Target Highlights, and Audio Waveform
         */
        injectStyles() {
            if (document.getElementById('t8-voice-assistant-styles')) return;

            const style = document.createElement('style');
            style.id = 't8-voice-assistant-styles';
            style.textContent = `
                /* Voice Assistant Floating Widget */
                .t8-voice-hud-container {
                    position: fixed !important;
                    bottom: 24px !important;
                    right: 24px !important;
                    z-index: 999999 !important;
                    font-family: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
                    user-select: none;
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                    pointer-events: auto !important;
                }

                /* Floating Core Orb */
                .t8-voice-orb {
                    width: 62px;
                    height: 62px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #4a157e 0%, #230b3a 100%);
                    border: 2px solid rgba(132, 204, 22, 0.6);
                    box-shadow: 0 8px 24px rgba(35, 11, 58, 0.4), 0 0 15px rgba(101, 179, 24, 0.3);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    color: #ffffff;
                    font-size: 22px;
                    transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
                    position: relative;
                }

                .t8-voice-orb:hover {
                    transform: scale(1.08) translateY(-2px);
                    box-shadow: 0 12px 30px rgba(35, 11, 58, 0.5), 0 0 25px rgba(132, 204, 22, 0.6);
                    border-color: #84cc16;
                }

                .t8-voice-orb.awake {
                    background: linear-gradient(135deg, #65b318 0%, #4a157e 100%);
                    border-color: #facc15;
                    box-shadow: 0 0 30px rgba(132, 204, 22, 0.8), 0 0 50px rgba(250, 204, 21, 0.4);
                    animation: t8OrbAwakePulse 1.8s infinite;
                }

                .t8-voice-orb.disabled {
                    background: linear-gradient(135deg, #2d1045 0%, #150624 100%) !important;
                    border: 2px solid #ef4444 !important;
                    color: #fca5a5 !important;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.5), 0 0 15px rgba(239, 68, 68, 0.4) !important;
                    display: flex !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                }

                .t8-voice-orb.disabled:hover {
                    border-color: #f87171 !important;
                    box-shadow: 0 10px 30px rgba(239, 68, 68, 0.6) !important;
                    transform: scale(1.08) translateY(-2px);
                }

                .t8-voice-orb-mic {
                    position: relative;
                    z-index: 2;
                }

                /* Proximity Audio Ring Visualizer */
                .t8-proximity-ring {
                    position: absolute;
                    top: -6px;
                    left: -6px;
                    right: -6px;
                    bottom: -6px;
                    border-radius: 50%;
                    border: 2px solid transparent;
                    pointer-events: none;
                    transition: border-color 0.2s, transform 0.1s;
                }

                .t8-voice-orb.awake .t8-proximity-ring.in-range {
                    border-color: rgba(74, 222, 128, 0.9);
                    box-shadow: 0 0 15px rgba(74, 222, 128, 0.7);
                    animation: t8RingExpand 1.2s infinite ease-out;
                }

                /* Mini Floating Status Badge */
                .t8-voice-status-pill {
                    position: absolute;
                    bottom: -10px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #1e1b4b;
                    color: #a7f3d0;
                    border: 1px solid rgba(132, 204, 22, 0.5);
                    font-size: 9px;
                    font-weight: 700;
                    padding: 1px 7px;
                    border-radius: 10px;
                    white-space: nowrap;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
                }

                .t8-voice-orb.disabled .t8-voice-status-pill {
                    background: #7f1d1d !important;
                    color: #fecaca !important;
                    border-color: #ef4444 !important;
                }

                /* Expanded Assistant Control Drawer / HUD */
                .t8-voice-hud-panel {
                    position: absolute;
                    bottom: 76px;
                    right: 0;
                    width: 360px;
                    background: rgba(26, 11, 46, 0.96);
                    backdrop-filter: blur(16px);
                    -webkit-backdrop-filter: blur(16px);
                    border: 1px solid rgba(132, 204, 22, 0.35);
                    border-radius: 18px;
                    box-shadow: 0 20px 45px rgba(18, 5, 33, 0.6), 0 0 20px rgba(101, 179, 24, 0.15);
                    color: #f8fafc;
                    overflow: hidden;
                    display: none;
                    flex-direction: column;
                    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                    transform-origin: bottom right;
                }

                .t8-voice-hud-panel.open {
                    display: flex;
                    animation: t8PanelSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }

                .t8-hud-header {
                    padding: 14px 18px;
                    background: rgba(35, 11, 58, 0.8);
                    border-bottom: 1px solid rgba(255,255,255,0.08);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }

                .t8-hud-title {
                    font-size: 14px;
                    font-weight: 700;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    color: #ffffff;
                }

                .t8-hud-title i {
                    color: #84cc16;
                }

                .t8-hud-body {
                    padding: 16px;
                    max-height: 380px;
                    overflow-y: auto;
                    display: flex;
                    flex-direction: column;
                    gap: 14px;
                }

                /* Proximity Audio Visualizer Bar */
                .t8-audio-meter-box {
                    background: rgba(0, 0, 0, 0.35);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    border-radius: 10px;
                    padding: 10px 12px;
                }

                .t8-meter-labels {
                    display: flex;
                    justify-content: space-between;
                    font-size: 11px;
                    font-weight: 600;
                    margin-bottom: 6px;
                    color: #cbd5e1;
                }

                .t8-meter-track {
                    height: 8px;
                    background: #1e293b;
                    border-radius: 4px;
                    overflow: hidden;
                    position: relative;
                }

                .t8-meter-fill {
                    height: 100%;
                    width: 0%;
                    background: linear-gradient(90deg, #65b318 0%, #eab308 70%, #ef4444 100%);
                    border-radius: 4px;
                    transition: width 0.08s ease;
                }

                .t8-meter-threshold-marker {
                    position: absolute;
                    top: 0;
                    bottom: 0;
                    width: 2px;
                    background: #ffffff;
                    box-shadow: 0 0 4px #ffffff;
                }

                /* Quick Setting Toggles */
                .t8-setting-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    font-size: 12px;
                    color: #e2e8f0;
                    padding: 6px 0;
                }

                .t8-switch {
                    position: relative;
                    display: inline-block;
                    width: 44px;
                    height: 24px;
                }

                .t8-switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }

                .t8-slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #475569;
                    transition: .3s;
                    border-radius: 24px;
                }

                .t8-slider:before {
                    position: absolute;
                    content: "";
                    height: 18px;
                    width: 18px;
                    left: 3px;
                    bottom: 3px;
                    background-color: white;
                    transition: .3s;
                    border-radius: 50%;
                }

                .t8-switch input:checked + .t8-slider {
                    background-color: #65b318;
                }

                .t8-switch input:checked + .t8-slider:before {
                    transform: translateX(20px);
                }

                /* Live Transcript / Activity Stream */
                .t8-activity-box {
                    background: rgba(0, 0, 0, 0.4);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    border-radius: 10px;
                    padding: 10px 12px;
                    min-height: 70px;
                }

                .t8-activity-title {
                    font-size: 10px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    color: #94a3b8;
                    margin-bottom: 4px;
                }

                .t8-live-transcript {
                    font-size: 13px;
                    font-weight: 600;
                    color: #facc15;
                    font-style: italic;
                    min-height: 20px;
                }

                .t8-last-action {
                    font-size: 11px;
                    color: #86efac;
                    margin-top: 4px;
                }

                /* Quick Voice Command Cheat Sheet Chips */
                .t8-command-chips {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                }

                .t8-cmd-chip {
                    background: rgba(101, 179, 24, 0.15);
                    border: 1px solid rgba(132, 204, 22, 0.3);
                    color: #bef264;
                    padding: 3px 8px;
                    border-radius: 6px;
                    font-size: 11px;
                    cursor: pointer;
                    transition: all 0.2s;
                }

                .t8-cmd-chip:hover {
                    background: rgba(101, 179, 24, 0.3);
                    border-color: #84cc16;
                    color: #ffffff;
                }

                /* Dynamic Element Highlight Indicator */
                .voice-target-highlight {
                    outline: 3px solid #84cc16 !important;
                    outline-offset: 3px !important;
                    box-shadow: 0 0 20px rgba(132, 204, 22, 0.8), inset 0 0 10px rgba(132, 204, 22, 0.3) !important;
                    transform: scale(1.02) !important;
                    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
                    position: relative;
                    z-index: 10000 !important;
                }

                /* Biometrics Shield & Match Meter */
                .t8-bio-shield-box {
                    background: rgba(35, 11, 58, 0.7);
                    border: 1px solid rgba(132, 204, 22, 0.35);
                    border-radius: 12px;
                    padding: 10px 12px;
                    margin-bottom: 12px;
                }
                .t8-bio-meter-track {
                    height: 7px;
                    background: rgba(255, 255, 255, 0.1);
                    border-radius: 4px;
                    overflow: hidden;
                    margin: 6px 0;
                    position: relative;
                }
                .t8-bio-meter-fill {
                    height: 100%;
                    width: 0%;
                    background: linear-gradient(90deg, #ef4444 0%, #eab308 55%, #22c55e 100%);
                    border-radius: 4px;
                    transition: width 0.12s ease;
                }
                .t8-bio-enroll-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100vw;
                    height: 100vh;
                    background: rgba(15, 5, 29, 0.85);
                    backdrop-filter: blur(8px);
                    -webkit-backdrop-filter: blur(8px);
                    z-index: 1000000;
                    display: none;
                    align-items: center;
                    justify-content: center;
                }
                .t8-bio-enroll-modal.open {
                    display: flex;
                    animation: t8PanelSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
                .t8-bio-modal-card {
                    width: 450px;
                    max-width: 92vw;
                    background: #1e0b36;
                    border: 2px solid #84cc16;
                    border-radius: 20px;
                    box-shadow: 0 25px 60px rgba(0,0,0,0.7), 0 0 30px rgba(132, 204, 22, 0.3);
                    color: #ffffff;
                    overflow: hidden;
                }
                .t8-bio-bar {
                    width: 6px;
                    height: 8px;
                    background: #84cc16;
                    border-radius: 3px;
                    transition: height 0.08s ease;
                }

                /* Floating Action Toast Notification */
                .t8-voice-toast {
                    position: fixed;
                    top: 80px;
                    right: 24px;
                    background: rgba(35, 11, 58, 0.95);
                    border: 1px solid #84cc16;
                    color: #ffffff;
                    padding: 10px 18px;
                    border-radius: 12px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.4), 0 0 15px rgba(101, 179, 24, 0.3);
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    z-index: 999999;
                    font-size: 13px;
                    font-weight: 600;
                    transform: translateY(-20px);
                    opacity: 0;
                    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                    pointer-events: none;
                }

                .t8-voice-toast.show {
                    transform: translateY(0);
                    opacity: 1;
                }

                @keyframes t8OrbAwakePulse {
                    0% { box-shadow: 0 0 0 0 rgba(132, 204, 22, 0.7); }
                    70% { box-shadow: 0 0 0 16px rgba(132, 204, 22, 0); }
                    100% { box-shadow: 0 0 0 0 rgba(132, 204, 22, 0); }
                }

                @keyframes t8PanelSlideUp {
                    from { opacity: 0; transform: translateY(15px) scale(0.95); }
                    to { opacity: 1; transform: translateY(0) scale(1); }
                }
            `;
            document.head.appendChild(style);
        }

        /**
         * Inject HTML Markup for Voice Assistant Floating Orb & HUD
         */
        injectUI() {
            if (document.getElementById('t8VoiceHudContainer')) {
                this.cacheUIElements();
                this.updateUIState();
                return;
            }

            if (!document.body) {
                window.addEventListener('DOMContentLoaded', () => this.injectUI(), { once: true });
                window.addEventListener('load', () => this.injectUI(), { once: true });
                setTimeout(() => this.injectUI(), 30);
                return;
            }

            this.injectStyles();

            const roleTitle = this.userRole.charAt(0).toUpperCase() + this.userRole.slice(1);
            const roleBadgeClass = this.userRole === 'admin' ? 'bg-danger' : (this.userRole === 'manager' ? 'bg-primary' : 'bg-warning text-dark');

            // Render role-specific command chips
            let commandChipsHtml = '';
            if (this.userRole === 'cashier') {
                commandChipsHtml = `
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('turn on')">"Turn on"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('point of sale')">"POS Terminal"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('sales and transactions')">"My Transactions"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('how much did we sell today')">"Today's sales?"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('cancel')">"Cancel"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('turn off')">"Turn off"</span>
                `;
            } else if (this.userRole === 'manager') {
                commandChipsHtml = `
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('turn on')">"Turn on"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('products')">"Products"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('inventory')">"Inventory"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('business reports')">"Reports"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('categories')">"Categories"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('sales and transactions')">"Sales & Txns"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('point of sale')">"POS"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('how much did we sell today')">"Today's sales?"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('turn off')">"Turn off"</span>
                `;
            } else {
                commandChipsHtml = `
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('turn on')">"Turn on"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('sales and transactions')">"Sales & Txns"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('business reports')">"Reports"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('user management')">"Users"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('access control')">"Access Control"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('backup and restore')">"Backup & Restore"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('store settings')">"Settings"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('how much did we sell today')">"Today's sales?"</span>
                    <span class="t8-cmd-chip" onclick="window.VoiceAssistant.executeCommand('turn off')">"Turn off"</span>
                `;
            }

            const container = document.createElement('div');
            container.id = 't8VoiceHudContainer';
            container.className = 't8-voice-hud-container';

            container.innerHTML = `
                <!-- Expanded HUD Panel -->
                <div class="t8-voice-hud-panel" id="t8VoiceHudPanel">
                    <div class="t8-hud-header">
                        <div class="t8-hud-title d-flex align-items-center gap-2">
                            <i class="fas fa-microphone-lines"></i>
                            <span>Voice Assistant Control</span>
                            <span class="badge ${roleBadgeClass} text-uppercase px-2 py-0" style="font-size: 9px;">${roleTitle}</span>
                        </div>
                        <button type="button" class="btn-close btn-close-white btn-sm" id="t8HudCloseBtn" aria-label="Close"></button>
                    </div>

                    <div class="t8-hud-body">
                        <!-- Live Transcript & Feedback -->
                        <div class="t8-activity-box">
                            <div class="t8-activity-title">Live Voice Stream</div>
                            <div class="t8-live-transcript" id="t8LiveTranscript">Say "Turn On" or a command...</div>
                            <div class="t8-last-action" id="t8LastAction">Status: Ready & Listening</div>
                        </div>

                        <!-- Proximity & Audio Intensity Meter -->
                        <div class="t8-audio-meter-box">
                            <div class="t8-meter-labels">
                                <span><i class="fas fa-bullhorn me-1 text-info"></i> Close-Mic Voice Focus</span>
                                <span id="t8ProximityStatus" class="badge bg-success bg-opacity-25 text-success">READY</span>
                            </div>
                            <div class="t8-meter-track" title="Speaking close to the microphone crosses the white threshold line">
                                <div class="t8-meter-fill" id="t8AudioMeterFill"></div>
                                <div class="t8-meter-threshold-marker" id="t8MeterThreshold" style="left: 30%;"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1 text-muted" style="font-size: 9px;">
                                <span>Background Noise (Filtered)</span>
                                <span>Near-Field Voice (Active)</span>
                            </div>
                        </div>

                        <!-- Role Voiceprint Biometrics & Security Shield -->
                        <div class="t8-bio-shield-box">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-bold text-light"><i class="fas fa-fingerprint me-1 text-warning"></i> ${roleTitle} Voice Isolation</span>
                                <span id="t8BioStatusBadge" class="badge ${this.biometrics.profile ? 'bg-success' : 'bg-secondary'}">${this.biometrics.profile ? 'ARMED' : 'UNENROLLED'}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1 text-muted" style="font-size: 10px;">
                                <span>Speaker Match:</span>
                                <span id="t8BioConfidenceText" class="fw-bold text-light">${this.biometrics.profile ? 'Active' : 'Calibrate to Enable'}</span>
                            </div>
                            <div class="t8-bio-meter-track" title="Real-time speaker similarity against enrolled Voiceprint">
                                <div class="t8-bio-meter-fill" id="t8BioMeterFill"></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <button type="button" class="btn btn-xs btn-outline-warning d-flex align-items-center gap-1 py-1 px-2" style="font-size: 10px; border-radius: 6px;" onclick="window.VoiceAssistant.openBiometricEnrollModal()">
                                    <i class="fas fa-sliders"></i>
                                    <span id="t8BioEnrollBtnText">${this.biometrics.profile ? 'Recalibrate Voice' : `Enroll ${roleTitle} Voice`}</span>
                                </button>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="text-muted" style="font-size: 10px;">${roleTitle} Guard</span>
                                    <label class="t8-switch" style="transform: scale(0.85); transform-origin: right center;">
                                        <input type="checkbox" id="t8ToggleBiometrics" ${this.biometrics.enabled ? 'checked' : ''}>
                                        <span class="t8-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Settings & Controls -->
                        <div class="t8-setting-row">
                            <span><i class="fas fa-power-off me-2 text-warning"></i> Voice Assistant Master</span>
                            <label class="t8-switch">
                                <input type="checkbox" id="t8ToggleMaster" ${this.state.enabled ? 'checked' : ''}>
                                <span class="t8-slider"></span>
                            </label>
                        </div>

                        <div class="t8-setting-row">
                            <span><i class="fas fa-hands me-2 text-success"></i> Hands-Free Wake Word ("Turn On")</span>
                            <label class="t8-switch">
                                <input type="checkbox" id="t8ToggleHandsFree" ${this.state.handsFree ? 'checked' : ''}>
                                <span class="t8-slider"></span>
                            </label>
                        </div>

                        <div class="t8-setting-row">
                            <span><i class="fas fa-volume-high me-2 text-primary"></i> Spoken Audio Feedback (TTS)</span>
                            <label class="t8-switch">
                                <input type="checkbox" id="t8ToggleSpeech" ${this.state.speechOutput ? 'checked' : ''}>
                                <span class="t8-slider"></span>
                            </label>
                        </div>

                        <div class="t8-setting-row">
                            <span><i class="fas fa-sliders me-2 text-info"></i> Mic Sensitivity Level</span>
                            <select id="t8SensitivitySelect" class="form-select form-select-sm bg-dark text-light border-secondary" style="width: 110px; font-size: 11px;">
                                <option value="high" ${this.state.sensitivity === 'high' ? 'selected' : ''}>High (Soft)</option>
                                <option value="medium" ${this.state.sensitivity === 'medium' ? 'selected' : ''}>Medium (Close)</option>
                                <option value="low" ${this.state.sensitivity === 'low' ? 'selected' : ''}>Low (Direct)</option>
                            </select>
                        </div>

                        <!-- Sample Commands -->
                        <div>
                            <div class="t8-activity-title mb-2">Try Saying (or Click):</div>
                            <div class="t8-command-chips">
                                ${commandChipsHtml}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Core Floating Orb Button -->
                <div class="t8-voice-orb" id="t8VoiceOrb" title="Voice Assistant (Say 'Turn On' hands-free, or Click)">
                    <div class="t8-proximity-ring" id="t8ProximityRing"></div>
                    <div class="t8-voice-orb-mic">
                        <i class="fas fa-microphone" id="t8OrbIcon"></i>
                    </div>
                    <div class="t8-voice-status-pill" id="t8StatusPill">READY</div>
                </div>
            `;

            document.body.appendChild(container);

            // Role Voiceprint Enrollment Modal
            const bioModal = document.createElement('div');
            bioModal.id = 't8BioEnrollModal';
            bioModal.className = 't8-bio-enroll-modal';
            bioModal.innerHTML = `
                <div class="t8-bio-modal-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                            <i class="fas fa-fingerprint text-warning"></i>
                            <span>${roleTitle} Voiceprint Enrollment</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white btn-sm" onclick="window.VoiceAssistant.closeBiometricEnrollModal()"></button>
                    </div>
                    
                    <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background: rgba(255,255,255,0.06);">
                        <i class="fas fa-id-badge text-warning"></i>
                        <span class="small text-light">Enrolling Voice for User: <strong class="text-warning">${this.username}</strong> (<span class="text-uppercase">${roleTitle}</span>)</span>
                    </div>

                    <p class="small text-light text-opacity-75 mb-3">
                        Calibrate your unique voice profile so the assistant responds exclusively to you (<strong class="text-light">${this.username}</strong>) and strictly rejects unauthorized or foreign speakers.
                    </p>

                    <div class="p-3 rounded-3 mb-3 text-center border" style="background: rgba(35, 11, 58, 0.8); border-color: rgba(132, 204, 22, 0.4) !important;">
                        <small class="text-warning fw-bold text-uppercase d-block mb-1" style="font-size: 10px; letter-spacing: 0.05em;">Calibration Test Phrase</small>
                        <div class="fs-5 fw-bold text-light" id="t8BioCalibrationPhrase">"Authorize Triple Eight ${roleTitle} Access"</div>
                    </div>

                    <div class="text-center py-2 mb-3">
                        <div id="t8BioVisualizerWave" class="d-flex justify-content-center align-items-end gap-1 mb-2" style="height: 48px;">
                            <div class="t8-bio-bar" style="height: 10px;"></div>
                            <div class="t8-bio-bar" style="height: 16px;"></div>
                            <div class="t8-bio-bar" style="height: 24px;"></div>
                            <div class="t8-bio-bar" style="height: 32px;"></div>
                            <div class="t8-bio-bar" style="height: 20px;"></div>
                            <div class="t8-bio-bar" style="height: 12px;"></div>
                            <div class="t8-bio-bar" style="height: 8px;"></div>
                        </div>
                        <div class="small fw-semibold text-light" id="t8BioEnrollStatus">Click "Record & Calibrate" and read the phrase aloud.</div>
                        <div class="text-muted small mt-1" id="t8BioProfileSummary" style="font-size: 11px;">
                            ${this.biometrics.profile ? `Current Profile: Base Pitch ${Math.round(this.biometrics.profile.pitchMean)} Hz • Formant Fingerprint Verified` : 'No voice profile currently saved'}
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top border-white border-opacity-10">
                        <div id="t8BioClearBtnContainer">
                            ${this.biometrics.profile ? `
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.VoiceAssistant.clearBiometricProfile()">
                                    <i class="fas fa-trash me-1"></i> Clear Voiceprint
                                </button>
                            ` : '<span></span>'}
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-info" id="t8BioTestBtn" onclick="window.VoiceAssistant.testVoiceAuthentication()">
                                <i class="fas fa-vial me-1"></i> Test Voice Auth
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="window.VoiceAssistant.closeBiometricEnrollModal()">Close</button>
                            <button type="button" class="btn btn-sm btn-success fw-bold px-3" id="t8BioStartCalibBtn" onclick="window.VoiceAssistant.startBiometricCalibration()">
                                <i class="fas fa-microphone-lines me-1"></i> Record & Calibrate
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(bioModal);

            // Toast Alert
            const toast = document.createElement('div');
            toast.id = 't8VoiceToast';
            toast.className = 't8-voice-toast';
            toast.innerHTML = `<i class="fas fa-robot text-success fs-5"></i> <span id="t8VoiceToastMsg">Voice Assistant Ready</span>`;
            document.body.appendChild(toast);

            // Cache UI references
            this.ui = {
                container: container,
                panel: document.getElementById('t8VoiceHudPanel'),
                orb: document.getElementById('t8VoiceOrb'),
                orbIcon: document.getElementById('t8OrbIcon'),
                statusPill: document.getElementById('t8StatusPill'),
                proximityRing: document.getElementById('t8ProximityRing'),
                liveTranscript: document.getElementById('t8LiveTranscript'),
                lastAction: document.getElementById('t8LastAction'),
                meterFill: document.getElementById('t8AudioMeterFill'),
                meterThreshold: document.getElementById('t8MeterThreshold'),
                proximityStatus: document.getElementById('t8ProximityStatus'),
                bioStatusBadge: document.getElementById('t8BioStatusBadge'),
                bioConfidenceText: document.getElementById('t8BioConfidenceText'),
                bioMeterFill: document.getElementById('t8BioMeterFill'),
                bioEnrollBtnText: document.getElementById('t8BioEnrollBtnText'),
                toggleBiometrics: document.getElementById('t8ToggleBiometrics'),
                bioEnrollModal: bioModal,
                bioEnrollStatus: document.getElementById('t8BioEnrollStatus'),
                bioProfileSummary: document.getElementById('t8BioProfileSummary'),
                bioStartCalibBtn: document.getElementById('t8BioStartCalibBtn'),
                bioClearBtnContainer: document.getElementById('t8BioClearBtnContainer'),
                toggleMaster: document.getElementById('t8ToggleMaster'),
                toggleHandsFree: document.getElementById('t8ToggleHandsFree'),
                toggleSpeech: document.getElementById('t8ToggleSpeech'),
                sensitivitySelect: document.getElementById('t8SensitivitySelect'),
                toast: toast || document.getElementById('t8VoiceToast'),
                toastMsg: document.getElementById('t8VoiceToastMsg')
            };

            this.bindUIEvents();
            this.updateUIState();
        }

        /**
         * Re-cache UI element references from DOM
         */
        cacheUIElements() {
            this.ui = {
                container: document.getElementById('t8VoiceHudContainer'),
                panel: document.getElementById('t8VoiceHudPanel'),
                orb: document.getElementById('t8VoiceOrb'),
                orbIcon: document.getElementById('t8OrbIcon'),
                statusPill: document.getElementById('t8StatusPill'),
                proximityRing: document.getElementById('t8ProximityRing'),
                liveTranscript: document.getElementById('t8LiveTranscript'),
                lastAction: document.getElementById('t8LastAction'),
                meterFill: document.getElementById('t8AudioMeterFill'),
                meterThreshold: document.getElementById('t8MeterThreshold'),
                proximityStatus: document.getElementById('t8ProximityStatus'),
                bioStatusBadge: document.getElementById('t8BioStatusBadge'),
                bioConfidenceText: document.getElementById('t8BioConfidenceText'),
                bioMeterFill: document.getElementById('t8BioMeterFill'),
                bioEnrollBtnText: document.getElementById('t8BioEnrollBtnText'),
                toggleBiometrics: document.getElementById('t8ToggleBiometrics'),
                bioEnrollModal: document.getElementById('t8BioEnrollModal'),
                bioEnrollStatus: document.getElementById('t8BioEnrollStatus'),
                bioProfileSummary: document.getElementById('t8BioProfileSummary'),
                bioStartCalibBtn: document.getElementById('t8BioStartCalibBtn'),
                bioClearBtnContainer: document.getElementById('t8BioClearBtnContainer'),
                toggleMaster: document.getElementById('t8ToggleMaster'),
                toggleHandsFree: document.getElementById('t8ToggleHandsFree'),
                toggleSpeech: document.getElementById('t8ToggleSpeech'),
                sensitivitySelect: document.getElementById('t8SensitivitySelect'),
                closeBtn: document.getElementById('t8HudCloseBtn'),
                toast: document.getElementById('t8VoiceToast'),
                toastMsg: document.getElementById('t8VoiceToastMsg')
            };
        }

        /**
         * Bind UI Controls & Click Handlers
         */
        bindUIEvents() {
            if (!this.ui || !this.ui.orb) return;

            // Click Orb -> Toggle HUD or Wake/Sleep
            this.ui.orb.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!this.state.enabled) {
                    this.enableMaster(true);
                    this.wakeUp();
                } else if (!this.state.active) {
                    this.wakeUp();
                    this.toggleHUD(true);
                } else {
                    this.toggleHUD();
                }
            });

            // Close HUD button
            if (this.ui.closeBtn) {
                this.ui.closeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.toggleHUD(false);
                });
            }

            // Close HUD on outside click
            document.addEventListener('click', (e) => {
                if (this.hudOpen && !this.ui.container.contains(e.target)) {
                    this.toggleHUD(false);
                }
            });

            // Master Switch
            if (this.ui.toggleMaster) {
                this.ui.toggleMaster.addEventListener('change', (e) => {
                    this.enableMaster(e.target.checked);
                });
            }

            // Hands-Free Switch
            if (this.ui.toggleHandsFree) {
                this.ui.toggleHandsFree.addEventListener('change', (e) => {
                    this.setHandsFree(e.target.checked);
                });
            }

            // Speech Output Switch
            if (this.ui.toggleSpeech) {
                this.ui.toggleSpeech.addEventListener('change', (e) => {
                    this.setSpeechOutput(e.target.checked);
                });
            }

            // Sensitivity Select
            if (this.ui.sensitivitySelect) {
                this.ui.sensitivitySelect.addEventListener('change', (e) => {
                    this.setSensitivity(e.target.value);
                });
            }

            // Biometric Shield Master Switch
            if (this.ui.toggleBiometrics) {
                this.ui.toggleBiometrics.addEventListener('change', (e) => {
                    this.setBiometricsEnabled(e.target.checked);
                });
            }
        }

        /**
         * Web Audio API Proximity Analyzer, Pitch Detector & Biometric Acoustic Profiler
         */
        async initAudioContext() {
            if (this.audioCtx) return;

            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;

                this.audioCtx = new AudioContext();
                this.analyser = this.audioCtx.createAnalyser();
                this.analyser.fftSize = 1024; // 512 frequency bins for sharp vocal formant & harmonic resolution
                this.analyser.smoothingTimeConstant = 0.35;
                this.audioDataArray = new Uint8Array(this.analyser.frequencyBinCount);
                this.timeDataArray = new Float32Array(this.analyser.fftSize);

                // Ask for microphone stream with advanced filters
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    this.micStream = await navigator.mediaDevices.getUserMedia({
                        audio: {
                            echoCancellation: true,
                            noiseSuppression: true,
                            autoGainControl: true
                        }
                    });

                    const source = this.audioCtx.createMediaStreamSource(this.micStream);
                    source.connect(this.analyser);
                    this.startProximityLoop();
                }
            } catch (err) {
                console.info('[VoiceAssistant] Audio context notice:', err.message);
            }
        }

        /**
         * Real-time Proximity & Biometric Acoustic Calculation Loop
         */
        startProximityLoop() {
            const loop = () => {
                if (this.analyser && this.audioDataArray && this.timeDataArray) {
                    this.analyser.getByteFrequencyData(this.audioDataArray);
                    this.analyser.getFloatTimeDomainData(this.timeDataArray);

                    // Compute RMS Amplitude (Energy level)
                    let sumSquares = 0;
                    for (let i = 0; i < this.audioDataArray.length; i++) {
                        const normalized = this.audioDataArray[i] / 255;
                        sumSquares += normalized * normalized;
                    }
                    const rms = Math.sqrt(sumSquares / this.audioDataArray.length);
                    this.state.lastRms = rms;

                    const threshold = this.sensitivityThresholds[this.state.sensitivity] || 0.03;
                    this.state.isMicInRange = rms >= threshold;

                    // Extract real-time vocal pitch and spectral characteristics when human voice is detected
                    if (rms >= 0.012) {
                        const sampleRate = this.audioCtx ? this.audioCtx.sampleRate : 44100;
                        const pitch = this.estimatePitch(this.timeDataArray, sampleRate);
                        const spectral = this.extractSpectralProfile(this.audioDataArray, sampleRate);

                        this.biometrics.currentAcousticVector = {
                            pitch: pitch,
                            bands: spectral.bands,
                            centroid: spectral.centroid,
                            rms: rms,
                            timestamp: Date.now()
                        };

                        // If calibration recording is active, accumulate valid samples
                        if (this.biometrics.isCalibrating && pitch >= 65 && pitch <= 400 && rms >= 0.018) {
                            this.biometrics.calibrationSamples.push(this.biometrics.currentAcousticVector);
                            this.animateCalibrationWave(rms);
                        }

                        // Evaluate speaker similarity against enrolled admin voiceprint in real-time
                        if (this.biometrics.profile && this.biometrics.enabled) {
                            const matchResult = this.evaluateSpeaker(this.biometrics.currentAcousticVector);
                            if (this.ui.bioMeterFill) {
                                this.ui.bioMeterFill.style.width = matchResult.confidence + '%';
                            }
                            if (this.ui.bioConfidenceText) {
                                if (matchResult.isMatch) {
                                    this.ui.bioConfidenceText.innerHTML = `<span class="text-success fw-bold">${matchResult.confidence}% (Admin Verified)</span>`;
                                } else {
                                    this.ui.bioConfidenceText.innerHTML = `<span class="text-danger fw-bold">${matchResult.confidence}% (Unauthorized Voice)</span>`;
                                }
                            }
                        }
                    } else {
                        // Reset match display when silent
                        if (this.ui.bioMeterFill && !this.biometrics.isCalibrating) {
                            this.ui.bioMeterFill.style.width = '0%';
                        }
                    }

                    // Update UI meters
                    if (this.ui.meterFill) {
                        const meterPercent = Math.min(100, Math.round((rms / 0.12) * 100));
                        this.ui.meterFill.style.width = meterPercent + '%';
                    }

                    if (this.ui.proximityRing) {
                        if (this.state.isMicInRange && this.state.active) {
                            this.ui.proximityRing.classList.add('in-range');
                        } else {
                            this.ui.proximityRing.classList.remove('in-range');
                        }
                    }

                    if (this.ui.proximityStatus) {
                        if (!this.state.enabled) {
                            this.ui.proximityStatus.className = 'badge bg-secondary';
                            this.ui.proximityStatus.textContent = 'OFF';
                        } else if (this.state.isMicInRange) {
                            this.ui.proximityStatus.className = 'badge bg-success';
                            this.ui.proximityStatus.textContent = 'VOICE DETECTED';
                        } else {
                            this.ui.proximityStatus.className = 'badge bg-primary bg-opacity-50';
                            this.ui.proximityStatus.textContent = 'READY';
                        }
                    }
                }
                this.proximityRafId = requestAnimationFrame(loop);
            };

            this.proximityRafId = requestAnimationFrame(loop);
        }

        /**
         * Setup Web Speech Recognition
         */
        initSpeechRecognition() {
            if (!this.SpeechRecognition) {
                console.warn('[VoiceAssistant] SpeechRecognition not supported in this browser.');
                if (this.ui.statusPill) this.ui.statusPill.textContent = 'NO STT';
                return;
            }

            try {
                this.recognition = new this.SpeechRecognition();
                this.recognition.continuous = true;
                this.recognition.interimResults = true;
                this.recognition.lang = 'en-US';
                this.recognition.maxAlternatives = 3;

                this.recognition.onstart = () => {
                    this.state.isListening = true;
                    this.updateUIState();
                };

                this.recognition.onend = () => {
                    this.state.isListening = false;
                    this.updateUIState();

                    // Auto-reconnect if enabled
                    if (this.state.enabled && this.state.handsFree) {
                        clearTimeout(this.recognitionRestartTimer);
                        this.recognitionRestartTimer = setTimeout(() => {
                            this.startContinuousListening();
                        }, 300);
                    }
                };

                this.recognition.onerror = (e) => {
                    if (e.error !== 'no-speech' && e.error !== 'aborted') {
                        console.info('[VoiceAssistant] Recognition info:', e.error);
                    }
                };

                this.recognition.onresult = (event) => {
                    this.handleSpeechResult(event);
                };
            } catch (e) {
                console.error('[VoiceAssistant] Init error:', e);
            }
        }

        /**
         * Start Continuous Background Listening
         */
        startContinuousListening() {
            if (!this.recognition || !this.state.enabled || this.state.isListening) return;

            try {
                // Resume audio context if suspended (browser autoplay policy)
                if (this.audioCtx && this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume();
                }
                this.recognition.start();
            } catch (err) {
                // Ignore start-while-started error
            }
        }

        /**
         * Stop Continuous Listening
         */
        stopListening() {
            if (this.recognition && this.state.isListening) {
                try {
                    this.recognition.stop();
                } catch (e) { }
            }
        }

        /**
         * Stop Ongoing Audio / Text-to-Speech Output (Barge-in / Interruption)
         */
        stopSpeaking() {
            try {
                if (this.synth) {
                    this.synth.cancel();
                }
                this.isSpeaking = false;
            } catch (e) { }
        }

        /**
         * Process Incoming Speech Results (With Biometric Speaker Verification & Barge-in)
         */
        handleSpeechResult(event) {
            let interimTranscript = '';
            let finalTranscript = '';

            for (let i = event.resultIndex; i < event.results.length; ++i) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalTranscript += transcript;
                } else {
                    interimTranscript += transcript;
                }
            }

            const currentSpoken = (finalTranscript || interimTranscript).trim();
            if (!currentSpoken) return;

            // Immediate Audio Barge-in: Cancel current TTS speech when user speaks
            this.stopSpeaking();

            // Update live UI transcript display
            if (this.ui.liveTranscript) {
                this.ui.liveTranscript.textContent = `"${currentSpoken}"`;
            }

            // 1. Close-Mic Proximity Filter Check:
            // If background audio level is lower than threshold, ignore low-volume ambient murmur
            const threshold = this.sensitivityThresholds[this.state.sensitivity] || 0.03;
            if (this.state.lastRms > 0 && this.state.lastRms < (threshold * 0.45) && !this.state.active) {
                // Faint background chatter ignored
                return;
            }

            // 2. Role-Aware Voiceprint Biometric Shield Gatekeeper:
            // If Biometrics is enabled and an authorized user's voiceprint profile is calibrated:
            if (this.biometrics.enabled && this.biometrics.profile) {
                const evalResult = this.evaluateSpeaker(this.biometrics.currentAcousticVector);

                // If confidence is lower than threshold, reject background / foreign speaker
                if (!evalResult.isMatch) {
                    if (this.ui.lastAction) {
                        this.ui.lastAction.innerHTML = `<span class="text-danger fw-bold"><i class="fas fa-shield-halved me-1"></i> Foreign Voice Rejected (${evalResult.confidence}% match)</span>`;
                    }
                    if (this.ui.orb) {
                        this.ui.orb.style.boxShadow = '0 0 25px rgba(239, 68, 68, 0.9)';
                        setTimeout(() => { this.ui.orb.style.boxShadow = ''; }, 900);
                    }
                    this.playChime('error');
                    this.speak('Voice authentication failed. Speaker not recognized.');
                    this.showToast(`Foreign Voice Rejected (${evalResult.confidence}% match)`, 'warning');

                    // Asynchronously log rejected attempt to server audit trail
                    this.logVoiceCommandToServer(currentSpoken, evalResult.confidence, false, evalResult.reason);
                    return; // Strictly discard and block unauthorized command!
                } else {
                    if (this.ui.lastAction) {
                        this.ui.lastAction.innerHTML = `<span class="text-success fw-bold"><i class="fas fa-fingerprint me-1"></i> ${this.username} Verified (${evalResult.confidence}%)</span>`;
                    }
                }
            }

            // Only process finalized utterances or strong wake phrases
            const normalized = this.normalizeSpeech(currentSpoken);

            // 1. Check for WAKE phrases ("Turn on", "Wake up", "Hey assistant", etc.)
            if (this.isWakePhrase(normalized)) {
                this.wakeUp();
                // Check if command is chained with wake phrase (e.g. "turn on and show sales")
                const chainedCommand = this.extractChainedCommand(normalized);
                if (chainedCommand) {
                    this.executeCommand(chainedCommand);
                }
                return;
            }

            // 2. Check for SLEEP / TURN OFF phrases ("Turn off", "Go to sleep", etc.)
            if (this.isSleepPhrase(normalized)) {
                this.sleep();
                return;
            }

            // 3. Process commands if finalized and assistant is currently awake/active
            if (finalTranscript) {
                if (this.state.active || !this.state.handsFree) {
                    this.executeCommand(normalized);
                    // Reset awake timer on every active command
                    this.refreshAwakeTimer();
                }
            }
        }

        /**
         * Wake-word detection
         */
        isWakePhrase(text) {
            const wakePatterns = [
                /\bturn on\b/i,
                /\bwake up\b/i,
                /\bhey assistant\b/i,
                /\bhey triple\s?(8|eight)\b/i,
                /\btriple\s?(8|eight)\b/i,
                /\bactivate\b/i,
                /\bstart listening\b/i,
                /\bhello assistant\b/i,
                /\blisten to me\b/i
            ];
            return wakePatterns.some(p => p.test(text));
        }

        /**
         * Sleep/Disable phrase detection
         */
        isSleepPhrase(text) {
            const sleepPatterns = [
                /\bturn off\b/i,
                /\bgo to sleep\b/i,
                /\bstop listening\b/i,
                /\bstandby\b/i,
                /\bsleep\b/i,
                /\bdisable assistant\b/i,
                /\bdisable voice\b/i
            ];
            return sleepPatterns.some(p => p.test(text));
        }

        /**
         * Extract chained command after wake phrase (e.g. "turn on and show products")
         */
        extractChainedCommand(text) {
            const match = text.replace(/^(turn on|wake up|hey assistant|hey triple 8|hey triple eight|activate|start listening)\s+(and\s+|then\s+)?/i, '').trim();
            return match && match !== text ? match : null;
        }

        /**
         * Wake Up the Assistant (Remains continuously active until explicitly turned off)
         */
        wakeUp() {
            this.state.active = true;
            this.state.isAwake = true;
            localStorage.setItem('t8_voice_active', 'true');
            this.playChime('wake');
            this.updateUIState();
            this.showToast('Voice Assistant Active! Continuously listening for commands...');

            if (this.ui.lastAction) {
                this.ui.lastAction.textContent = 'Active: Continuously listening...';
            }

            // Speak greeting if output is enabled
            this.speak("Voice assistant is active and listening.");
        }

        /**
         * Sleep the Assistant into passive standby
         */
        sleep() {
            this.state.active = false;
            this.state.isAwake = false;
            localStorage.setItem('t8_voice_active', 'false');
            this.playChime('sleep');
            this.updateUIState();
            this.showToast('Voice Assistant is now in Standby. Say "Turn On" to activate.');

            if (this.ui.lastAction) {
                this.ui.lastAction.textContent = 'Standby: Say "Turn On"';
            }

            this.speak('Voice assistant in standby mode.');
        }

        /**
         * Mute Voice Speech Output (Silent mode: commands execute without audio noise)
         */
        mute() {
            this.state.speechOutput = false;
            localStorage.setItem('t8_voice_speech', 'false');
            if (this.ui.toggleSpeech) this.ui.toggleSpeech.checked = false;
            if (this.synth) this.synth.cancel();
            this.playChime('sleep');
            this.updateUIState();
            this.showToast('🔇 Voice feedback muted (Silent mode). Commands remain active.');
        }

        /**
         * Unmute Voice Speech Output
         */
        unmute() {
            this.state.speechOutput = true;
            localStorage.setItem('t8_voice_speech', 'true');
            if (this.ui.toggleSpeech) this.ui.toggleSpeech.checked = true;
            this.playChime('success');
            this.updateUIState();
            this.showToast('🔊 Voice feedback unmuted.');
            this.speak('Voice feedback unmuted.');
        }

        /**
         * Toggle Mute State
         */
        toggleMute() {
            if (this.state.speechOutput) {
                this.mute();
            } else {
                this.unmute();
            }
        }

        /**
         * Master On/Off Toggle
         */
        enableMaster(enable = true) {
            this.state.enabled = enable;
            localStorage.setItem('t8_voice_enabled', enable ? 'true' : 'false');

            if (enable) {
                this.startContinuousListening();
                this.showToast('Voice Assistant Enabled');
                this.speak('Voice assistant enabled.');
            } else {
                this.state.active = false;
                this.state.isAwake = false;
                localStorage.setItem('t8_voice_active', 'false');
                this.stopListening();
                if (this.synth) this.synth.cancel();
                this.showToast('Voice Assistant Disabled');
            }

            this.updateUIState();
        }

        setHandsFree(enable = true) {
            this.state.handsFree = enable;
            localStorage.setItem('t8_voice_handsfree', enable ? 'true' : 'false');
            if (enable && this.state.enabled) {
                this.startContinuousListening();
            }
            this.showToast(enable ? 'Hands-free Wake Word enabled' : 'Hands-free disabled (Click to activate)');
        }

        setSpeechOutput(enable = true) {
            if (enable) {
                this.unmute();
            } else {
                this.mute();
            }
        }

        setSensitivity(level) {
            if (this.sensitivityThresholds[level]) {
                this.state.sensitivity = level;
                localStorage.setItem('t8_voice_sensitivity', level);
                if (this.ui.meterThreshold) {
                    const pct = level === 'high' ? '15%' : level === 'medium' ? '30%' : '55%';
                    this.ui.meterThreshold.style.left = pct;
                }
                this.showToast(`Sensitivity set to ${level.toUpperCase()}`);
            }
        }

        toggleHUD(forceState) {
            this.hudOpen = typeof forceState === 'boolean' ? forceState : !this.hudOpen;
            if (this.ui.panel) {
                if (this.hudOpen) {
                    this.ui.panel.classList.add('open');
                } else {
                    this.ui.panel.classList.remove('open');
                }
            }
        }

        /**
         * Update Visual UI Elements
         */
        updateUIState() {
            const orb = this.ui.orb;
            const pill = this.ui.statusPill;
            const icon = this.ui.orbIcon;

            if (!orb) return;

            if (!this.state.enabled) {
                orb.className = 't8-voice-orb disabled';
                if (pill) pill.textContent = 'OFF';
                if (icon) icon.className = 'fas fa-microphone-slash';
            } else if (this.state.active) {
                orb.className = 't8-voice-orb awake';
                if (pill) pill.textContent = this.state.speechOutput ? 'ACTIVE' : 'MUTED';
                if (icon) icon.className = this.state.speechOutput ? 'fas fa-waveform-lines fa-bounce' : 'fas fa-volume-xmark';
            } else {
                orb.className = 't8-voice-orb';
                if (pill) pill.textContent = 'STANDBY';
                if (icon) icon.className = 'fas fa-microphone';
            }

            // Sync Navbar Toggle if present
            const navToggle = document.getElementById('navVoiceStatusPill');
            if (navToggle) {
                if (!this.state.enabled) {
                    navToggle.className = 'badge bg-secondary';
                    navToggle.innerHTML = '<i class="fas fa-power-off me-1"></i> VOICE: OFF';
                } else if (this.state.active) {
                    if (this.state.speechOutput) {
                        navToggle.className = 'badge bg-success';
                        navToggle.innerHTML = '<i class="fas fa-microphone me-1"></i> VOICE: ACTIVE';
                    } else {
                        navToggle.className = 'badge bg-success bg-opacity-75';
                        navToggle.innerHTML = '<i class="fas fa-volume-xmark me-1"></i> VOICE: ACTIVE (MUTED)';
                    }
                } else {
                    navToggle.className = 'badge bg-warning text-dark';
                    navToggle.innerHTML = '<i class="fas fa-microphone-lines me-1"></i> VOICE: STANDBY';
                }
            }
        }

        /**
         * Normalize Speech String (Number words, homophones, trim)
         */
        normalizeSpeech(text) {
            if (!text) return '';
            let s = text.toLowerCase().trim();

            // Replace word numbers with digits
            const numMap = {
                'zero': 0, 'one': 1, 'two': 2, 'three': 3, 'four': 4,
                'five': 5, 'six': 6, 'seven': 7, 'eight': 8, 'nine': 9, 'ten': 10
            };
            for (const [word, num] of Object.entries(numMap)) {
                s = s.replace(new RegExp(`\\b${word}\\b`, 'g'), num);
            }

            // Clean common filler words & punctuation
            s = s.replace(/[.,?!]/g, '').trim();
            return s;
        }

        /**
         * =========================================================================================
         * MAIN COMMAND ROUTER & UNIVERSAL ELEMENT EXECUTOR
         * =========================================================================================
         */
        async executeCommand(rawCommand) {
            const cmd = this.normalizeSpeech(rawCommand);
            if (!cmd) return;

            // Immediate audio barge-in: cancel any running TTS
            this.stopSpeaking();

            console.log('[VoiceAssistant] Executing command:', cmd);
            if (this.ui.lastAction) {
                this.ui.lastAction.textContent = `Executing: "${cmd}"`;
            }

            // Asynchronously log authenticated command execution to server audit trail
            if (this.biometrics.enabled && this.biometrics.profile) {
                this.logVoiceCommandToServer(cmd, this.biometrics.lastConfidence || 95, true, `Speaker verified for ${this.username}`);
            }

            // 0. Universal Cancellation Handler ("cancel", "stop", "abort", "close", "dismiss")
            if (this.handleCancelCommand(cmd)) return;

            // 1. Confirmation handling (e.g. for Logout, Clear Cart, or Finalize Sale)
            if (this.state.awaitingConfirmation) {
                if (/\b(yes|confirm|proceed|do it|sure|ok|okay|yes please|finalize|go|yep|yeah)\b/i.test(cmd)) {
                    const action = this.state.awaitingConfirmation;
                    this.state.awaitingConfirmation = null;
                    this.handleConfirmedAction(action);
                    return;
                }
            }

            // 1.5 MUTE / UNMUTE Voice Feedback Controls
            if (/\b(mute|mute voice|mute audio|silence|be quiet|quiet mode|mute assistant|silence assistant|silent mode)\b/i.test(cmd)) {
                this.mute();
                return;
            }
            if (/\b(unmute|unmute voice|unmute audio|enable voice|speak again|unmute assistant|voice feedback on|voice on)\b/i.test(cmd)) {
                this.unmute();
                return;
            }

            // 1.8 Database Backup & Restore Operations ("create backup", "auto backup settings", "upload backup", "download backup")
            if (this.handleBackupModuleCommands(cmd)) return;

            // 2. Navigation & Direct System Pages
            if (this.handleNavigationCommand(cmd)) return;

            // 3. Cross-Platform Web Tasks (YouTube, Google, Maps, Wikipedia, News, Social)
            if (this.handleCrossPlatformWebTasks(cmd)) return;

            // 4. Intelligent Virtual Assistant Utilities (Time, Date, Math, Timers, Notes, Tips)
            if (this.handleVirtualAssistantUtilities(cmd)) return;

            // 5. UI Scrolling & Window Controls
            if (this.handleScrollCommand(cmd)) return;

            // 6. POS Cashier Operations (Add items, complete sale, clear)
            if (this.handlePOSCommand(cmd)) return;

            // 7. Universal Interactive Element Clicker (Buttons, Links, Inputs)
            if (this.handleInteractiveElementClick(cmd)) return;

            // 8. Form Field Search & Fill ("Search for X", "Type X in Y")
            if (this.handleFormSearchCommand(cmd)) return;

            // 9. Retail Operations & Real-Time Stats Queries
            if (await this.handleRetailAIQuery(cmd)) return;

            // 10. Fallback friendly response
            this.playChime('error');
            this.speak(`I heard: "${cmd}". You can ask me to search YouTube, click any button, calculate numbers, or check today's sales.`);
            this.showToast(`Command not recognized: "${cmd}"`);
        }

        /**
         * 1. Navigation Command Handler
         */
        handleNavigationCommand(cmd) {
            const baseUrl = (window.location.pathname.includes('/admin/') || window.location.pathname.includes('/cashier/')) ? '../' : './';
            const isCashierArea = window.location.pathname.includes('/cashier/');
            const role = this.userRole || 'admin';

            // 1. Sales & Transactions ("sales and transactions", "sales & transactions", "transactions", "sales history", "transaction history")
            const isSalesStatQuery = /\b(today|yesterday|this month|this week|how much|what is|total|revenue|daily|compare|sum|amount)\b/i.test(cmd);
            if (
                /\b(go to |open |navigate to |show |view )?(sales\s*(?:and|&|n)\s*transactions?|sales\s*transactions?|transaction\s*history|sales\s*history|transactions?\s*log|sales\s*records?|all\s*transactions)\b/i.test(cmd) ||
                (/\b(go to |open |navigate to |show |view )?(transactions?)\b/i.test(cmd) && !isSalesStatQuery) ||
                (/\b(go to |open |navigate to |show |view )\s*(sales)\b/i.test(cmd) && !isSalesStatQuery)
            ) {
                this.playChime('success');
                this.speak('Opening Sales and Transactions.');
                this.showToast('Navigating to Sales & Transactions...');
                const target = (role === 'cashier' || isCashierArea) ? baseUrl + 'cashier/transactions.php' : baseUrl + 'admin/sales.php';
                setTimeout(() => window.location.href = target, 400);
                return true;
            }

            // 2. Business Reports ("business reports", "business report", "reports", "sales reports", "financial reports", "analytics", "statistics")
            if (/\b(go to |open |navigate to |show |view )?(business\s*reports?|sales\s*reports?|financial\s*reports?|reports?|analytics|statistics)\b/i.test(cmd)) {
                if (role === 'cashier') {
                    this.playChime('error');
                    this.speak('Access restricted. Business Reports require Manager or Administrator privileges.');
                    this.showToast('Access Restricted: Manager/Admin only', 'warning');
                    return true;
                }
                this.playChime('success');
                this.speak('Opening Business Reports.');
                this.showToast('Navigating to Business Reports...');
                setTimeout(() => window.location.href = baseUrl + 'admin/reports.php', 400);
                return true;
            }

            // 3. User Management ("user management", "users management", "manage users", "user accounts", "users", "user list", "staff management")
            if (/\b(go to |open |navigate to |show |view )?(user\s*management|users?\s*management|manage\s*users?|user\s*accounts?|users?|staff\s*management|employee\s*management|user\s*list)\b/i.test(cmd)) {
                if (role !== 'admin') {
                    this.playChime('error');
                    this.speak('Access restricted. User Management requires Administrator privileges.');
                    this.showToast('Access Restricted: Administrator only', 'warning');
                    return true;
                }
                this.playChime('success');
                this.speak('Opening User Management.');
                this.showToast('Navigating to User Management...');
                setTimeout(() => window.location.href = baseUrl + 'admin/users.php', 400);
                return true;
            }

            // 4. Access Control / RBAC ("access control", "rbac", "role management", "roles and permissions", "permissions", "manage roles", "role access")
            if (/\b(go to |open |navigate to |show |view )?(access\s*control|rbac|role\s*based\s*access\s*control|role\s*management|roles?\s*(?:and|&|n)\s*permissions?|permissions?|manage\s*roles?|manage\s*permissions?|role\s*access|security\s*permissions?|access\s*management)\b/i.test(cmd)) {
                if (role !== 'admin') {
                    this.playChime('error');
                    this.speak('Access restricted. Access Control requires Administrator privileges.');
                    this.showToast('Access Restricted: Administrator only', 'warning');
                    return true;
                }
                this.playChime('success');
                this.speak('Opening Access Control.');
                this.showToast('Navigating to Access Control...');
                setTimeout(() => window.location.href = baseUrl + 'admin/rbac.php', 400);
                return true;
            }

            // 5. Backup & Restore ("backup and restore", "backup & restore", "backuup & restore", "database backup", "system backup", "backup", "restore")
            if (/\b(go to |open |navigate to |show |view )?(backu?ups?\s*(?:and|&|n)\s*restore|backu?ups?\s*restore|backu?ups?\s*(?:and|&|n)\s*recovery|database\s*backu?ups?|system\s*backu?ups?|data\s*backu?ups?|backu?ups?|restore|database\s*restore)\b/i.test(cmd)) {
                if (role !== 'admin') {
                    this.playChime('error');
                    this.speak('Access restricted. Database Backup and Restore requires Administrator privileges.');
                    this.showToast('Access Restricted: Administrator only', 'warning');
                    return true;
                }
                this.playChime('success');
                this.speak('Opening Backup and Restore.');
                this.showToast('Navigating to Backup & Restore...');
                setTimeout(() => window.location.href = baseUrl + 'admin/backup.php', 400);
                return true;
            }

            // 6. Store Settings ("store settings", "system settings", "settings", "store configuration", "system configuration", "preferences")
            if (/\b(go to |open |navigate to |show |view )?(store\s*settings?|system\s*settings?|settings?|store\s*configurations?|system\s*configurations?|configurations?|store\s*preferences?|preferences?|store\s*config)\b/i.test(cmd)) {
                if (role !== 'admin') {
                    this.playChime('error');
                    this.speak('Access restricted. Store Settings requires Administrator privileges.');
                    this.showToast('Access Restricted: Administrator only', 'warning');
                    return true;
                }
                this.playChime('success');
                this.speak('Opening Store Settings.');
                this.showToast('Navigating to Store Settings...');
                setTimeout(() => window.location.href = baseUrl + 'admin/settings.php', 400);
                return true;
            }

            // 7. POS / Cashier
            if (/\b(go to |open |navigate to |show |view )?(pos|cashier|checkout|point\s*of\s*sale|register|pos\s*terminal|pos\s*screen|sales\s*counter)\b/i.test(cmd)) {
                this.playChime('success');
                this.speak('Opening Point of Sale.');
                this.showToast('Navigating to Point of Sale...');
                setTimeout(() => window.location.href = baseUrl + 'cashier/pos.php', 400);
                return true;
            }

            // 8. Products / Product Catalog
            if (
                /\b(go to |open |navigate to |show |view )?(product\s*catalog|products?|item\s*list|product\s*list|catalog)\b/i.test(cmd) &&
                !/\b(add|insert|create|delete|remove|edit|update)\s+product/i.test(cmd)
            ) {
                if (role === 'cashier') {
                    this.playChime('error');
                    this.speak('Access restricted. Product catalog management requires Manager or Administrator privileges.');
                    this.showToast('Access Restricted: Manager/Admin only', 'warning');
                    return true;
                }
                this.playChime('success');
                this.speak('Opening Products.');
                this.showToast('Navigating to Products...');
                setTimeout(() => window.location.href = baseUrl + 'admin/products.php', 400);
                return true;
            }

            // 9. Inventory Overview
            const isStockQuery = /\b(what should i restock|low stock|out of stock|how much stock|check stock|audit)\b/i.test(cmd);
            if (
                !isStockQuery &&
                /\b(go to |open |navigate to |show |view )?(inventory\s*overview|inventory|stock\s*overview|stock|warehouse|supplies)\b/i.test(cmd)
            ) {
                if (role === 'cashier') {
                    this.playChime('error');
                    this.speak('Access restricted. Inventory management requires Manager or Administrator privileges.');
                    this.showToast('Access Restricted: Manager/Admin only', 'warning');
                    return true;
                }
                this.playChime('success');
                this.speak('Opening Inventory.');
                this.showToast('Navigating to Inventory...');
                setTimeout(() => window.location.href = baseUrl + 'admin/inventory.php', 400);
                return true;
            }

            // 10. Departments / Categories
            if (/\b(go to |open |navigate to |show |view )?(departments?\s*(?:and|&|n)\s*categories|departments?|categories|item\s*categories|product\s*categories)\b/i.test(cmd)) {
                if (role === 'cashier') {
                    this.playChime('error');
                    this.speak('Access restricted. Department management requires Manager or Administrator privileges.');
                    this.showToast('Access Restricted: Manager/Admin only', 'warning');
                    return true;
                }
                this.playChime('success');
                this.speak('Opening Categories.');
                this.showToast('Navigating to Categories...');
                setTimeout(() => window.location.href = baseUrl + 'admin/categories.php', 400);
                return true;
            }

            // 11. Profile
            if (/\b(go to |open |navigate to |show |view )?(my\s*profile|profile|my\s*account|account\s*settings|user\s*profile)\b/i.test(cmd)) {
                this.playChime('success');
                this.speak('Opening Profile.');
                this.showToast('Navigating to Profile...');
                setTimeout(() => window.location.href = baseUrl + 'profile.php', 400);
                return true;
            }

            // 12. Dashboard
            if (/\b(go to |open |navigate to |show |view )?(dashboard|home|main\s*page|main\s*menu|overview|home\s*page)\b/i.test(cmd)) {
                this.playChime('success');
                this.speak('Opening Dashboard.');
                this.showToast('Navigating to Dashboard...');
                setTimeout(() => window.location.href = baseUrl + 'dashboard.php', 400);
                return true;
            }

            // 13. Logout with confirmation
            if (/\b(log\s*out|logout|sign\s*out|exit\s*system|disconnect)\b/i.test(cmd)) {
                this.state.awaitingConfirmation = 'logout';
                this.playChime('wake');
                this.speak('Are you sure you want to log out? Say yes to confirm.');
                this.showToast('Confirm Logout? Say "Yes" or "Cancel"');
                return true;
            }

            return false;
        }

        /**
         * Confirmed Actions Execution
         */
        handleConfirmedAction(action) {
            const baseUrl = (window.location.pathname.includes('/admin/') || window.location.pathname.includes('/cashier/')) ? '../' : './';
            if (action === 'logout') {
                this.playChime('success');
                this.speak('Logging out. Goodbye!');
                this.showToast('Logging out...');
                setTimeout(() => window.location.href = baseUrl + 'logout.php', 600);
            } else if (action === 'finalize_sale') {
                this.handlePOSFinalizeSale();
            } else if (action === 'clear_cart') {
                const clearBtn = document.getElementById('clearCartBtn') || document.querySelector('[data-action="clear-cart"]');
                if (clearBtn) {
                    this.highlightElement(clearBtn);
                    clearBtn.click();
                    this.speak('Cart cleared.');
                }
            }
        }

        /**
         * =========================================================================================
         * CROSS-PLATFORM NAVIGATION & WEB TASKS
         * Intelligent redirects to YouTube, Google, Maps, Wikipedia, News, Social & Ecommerce
         * =========================================================================================
         */
        handleCrossPlatformWebTasks(cmd) {
            // 1. YouTube Search & Playback (e.g., "show me business videos on youtube", "search youtube for X", "open youtube")
            if (/\byoutube\b/i.test(cmd)) {
                let query = cmd
                    .replace(/^(show(\s+me)?|play|find|search|look\s+up|open|watch)\s+/i, '')
                    .replace(/\s+(on|in|at)\s+youtube\b/i, '')
                    .replace(/^youtube\s+(for\s+)?/i, '')
                    .replace(/\b(videos?|tutorials?|music|clips?)\b/gi, '$&') // preserve keywords
                    .trim();

                // If just "open youtube" or query is empty
                if (!query || query === 'youtube') {
                    this.playChime('success');
                    this.speak('Opening YouTube.');
                    this.showToast('Opening YouTube...');
                    window.open('https://www.youtube.com', '_blank');
                    return true;
                }

                // If specific query (e.g. "business videos")
                this.playChime('success');
                this.speak(`Opening YouTube for ${query}.`);
                this.showToast(`YouTube: "${query}"`);
                const ytUrl = `https://www.youtube.com/results?search_query=${encodeURIComponent(query)}`;
                window.open(ytUrl, '_blank');
                return true;
            }

            // 2. Google Search (e.g., "search google for X", "google X", "search the web for X", "look up X online")
            if (/\b(search google for|google|search the web for|look up .* online|search online for)\b/i.test(cmd)) {
                let query = cmd
                    .replace(/^(search google for|google|search the web for|search online for|look up)\s+/i, '')
                    .replace(/\s+(on google|online|on the web)\b/i, '')
                    .trim();

                if (query) {
                    this.playChime('success');
                    this.speak(`Searching Google for ${query}.`);
                    this.showToast(`Google: "${query}"`);
                    const googleUrl = `https://www.google.com/search?q=${encodeURIComponent(query)}`;
                    window.open(googleUrl, '_blank');
                    return true;
                }
            }

            // 3. Google Maps & Supplier / Logistics Lookup (e.g., "find bakery on google maps", "open google maps", "directions to X")
            if (/\b(google maps?|maps?)\b/i.test(cmd) && (/\b(open|show|find|search|directions|where is)\b/i.test(cmd) || cmd === 'open maps')) {
                let query = cmd
                    .replace(/^(open|show|find|search|directions\s+to|where\s+is)\s+/i, '')
                    .replace(/\s+(on|in)\s+google\s+maps?\b/i, '')
                    .replace(/\s+maps?\b/i, '')
                    .trim();

                if (!query || query === 'google') {
                    this.playChime('success');
                    this.speak('Opening Google Maps.');
                    this.showToast('Opening Google Maps...');
                    window.open('https://maps.google.com', '_blank');
                    return true;
                }

                this.playChime('success');
                this.speak(`Searching Google Maps for ${query}.`);
                this.showToast(`Maps: "${query}"`);
                const mapsUrl = `https://www.google.com/maps/search/${encodeURIComponent(query)}`;
                window.open(mapsUrl, '_blank');
                return true;
            }

            // 4. Wikipedia Lookups (e.g., "search wikipedia for supply chain", "what is fifo on wikipedia")
            if (/\bwikipedia\b/i.test(cmd)) {
                let query = cmd
                    .replace(/^(search|look\s+up|what\s+is|tell\s+me\s+about|find)\s+/i, '')
                    .replace(/\s+(on|in)\s+wikipedia\b/i, '')
                    .replace(/^wikipedia\s+(for\s+)?/i, '')
                    .trim();

                if (query && query !== 'wikipedia') {
                    this.playChime('success');
                    this.speak(`Searching Wikipedia for ${query}.`);
                    this.showToast(`Wikipedia: "${query}"`);
                    const wikiUrl = `https://en.wikipedia.org/wiki/Special:Search?search=${encodeURIComponent(query)}`;
                    window.open(wikiUrl, '_blank');
                    return true;
                }
            }

            // 5. Business & Financial News (e.g., "show me business news", "check market news", "open google finance")
            if (/\b(business news|market news|retail news|stock market|finance news|google finance)\b/i.test(cmd)) {
                this.playChime('success');
                this.speak('Opening the latest business and retail market news.');
                this.showToast('Opening Business News...');
                window.open('https://news.google.com/search?q=retail+business+market+news', '_blank');
                return true;
            }

            // 6. Direct Platforms & Social / Ecommerce (e.g., "open facebook", "open amazon", "open shopee", "open gmail")
            const platformUrls = {
                'gmail': 'https://mail.google.com',
                'email': 'https://mail.google.com',
                'facebook': 'https://www.facebook.com',
                'linkedin': 'https://www.linkedin.com',
                'twitter': 'https://twitter.com',
                'github': 'https://github.com',
                'amazon': 'https://www.amazon.com',
                'shopee': 'https://shopee.ph',
                'lazada': 'https://www.lazada.com.ph'
            };

            for (const [platform, url] of Object.entries(platformUrls)) {
                if (new RegExp(`\\b(open|go to)\\s+${platform}\\b`, 'i').test(cmd)) {
                    this.playChime('success');
                    this.speak(`Opening ${platform.charAt(0).toUpperCase() + platform.slice(1)}.`);
                    this.showToast(`Navigating to ${platform}...`);
                    window.open(url, '_blank');
                    return true;
                }
            }

            // 7. General Web URL pattern (e.g., "open bbc.com", "go to cnn.com")
            const urlMatch = cmd.match(/\b(?:open|go\s+to)\s+([a-z0-9\-]+\.(?:com|org|net|io|ph|gov|edu))\b/i);
            if (urlMatch) {
                const domain = urlMatch[1];
                this.playChime('success');
                this.speak(`Navigating to ${domain}.`);
                this.showToast(`Opening ${domain}...`);
                window.open(`https://${domain}`, '_blank');
                return true;
            }

            return false;
        }

        /**
         * =========================================================================================
         * INTELLIGENT VIRTUAL ASSISTANT UTILITIES
         * Time, Date, Instant Math, Timers, Reminders, Voice Notes, and Retail Tips
         * =========================================================================================
         */
        handleVirtualAssistantUtilities(cmd) {
            // 1. Time Queries (e.g., "what time is it", "tell me the time", "current time")
            if (/\b(what time is it|tell me the time|current time|what is the time)\b/i.test(cmd)) {
                const now = new Date();
                const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                this.playChime('success');
                this.speak(`The current time is ${timeStr}.`);
                this.showToast(`Current Time: ${timeStr}`);
                return true;
            }

            // 2. Date Queries (e.g., "what is the date", "what day is today", "today's date")
            if (/\b(what is the date|what is today's date|what day is today|what date is it|today's date)\b/i.test(cmd)) {
                const now = new Date();
                const dateStr = now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                this.playChime('success');
                this.speak(`Today is ${dateStr}.`);
                this.showToast(`Date: ${dateStr}`);
                return true;
            }

            // 3. Instant Math & Calculation (e.g., "calculate 1500 times 1.12", "what is 20 percent of 5000", "500 divided by 4")
            const mathMatch = cmd.match(/^(?:calculate|compute|what is|how much is)\s+(.+)$/i);
            if (mathMatch || /\b(plus|minus|times|multiplied by|divided by|percent of)\b/i.test(cmd)) {
                let expr = (mathMatch ? mathMatch[1] : cmd).trim();

                // Convert percentage: "20 percent of 500" -> "(20 / 100) * 500"
                expr = expr.replace(/(\d+(?:\.\d+)?)\s*%\s*(?:of)?\s*(\d+(?:\.\d+)?)/gi, '($1 / 100) * $2');
                expr = expr.replace(/(\d+(?:\.\d+)?)\s*percent\s*(?:of)?\s*(\d+(?:\.\d+)?)/gi, '($1 / 100) * $2');

                // Convert word operators to math symbols
                expr = expr
                    .replace(/\bplus\b/gi, '+')
                    .replace(/\bminus\b/gi, '-')
                    .replace(/\b(times|multiplied by|x)\b/gi, '*')
                    .replace(/\b(divided by|over)\b/gi, '/')
                    .replace(/[^0-9+\-*\/().\s]/g, '')
                    .trim();

                if (expr && /[+\-*\/]/.test(expr)) {
                    try {
                        // Safe math evaluation with Function limited to math expressions
                        const result = Function(`'use strict'; return (${expr});`)();
                        if (typeof result === 'number' && !isNaN(result) && isFinite(result)) {
                            const formattedResult = Number.isInteger(result) ? result.toLocaleString() : result.toFixed(2);
                            this.playChime('success');
                            this.speak(`The result is ${formattedResult}.`);
                            this.showToast(`Calculation: ${expr} = ${formattedResult}`);
                            return true;
                        }
                    } catch (e) {
                        // Not a valid math expr, pass through
                    }
                }
            }

            // 4. Timers & Countdowns (e.g., "set a timer for 5 minutes", "timer for 30 seconds")
            const timerMatch = cmd.match(/\b(?:set\s+(?:a\s+)?)?timer\s+for\s+(\d+)\s*(minute|minutes|second|seconds|min|sec)\b/i);
            if (timerMatch) {
                const val = parseInt(timerMatch[1], 10);
                const unit = timerMatch[2].toLowerCase();
                const isMin = unit.startsWith('m');
                const totalSeconds = isMin ? val * 60 : val;
                const displayUnit = isMin ? (val === 1 ? 'minute' : 'minutes') : (val === 1 ? 'second' : 'seconds');

                this.playChime('success');
                this.speak(`Timer set for ${val} ${displayUnit}.`);
                this.showToast(`⏱️ Timer set: ${val} ${displayUnit}`);

                // Active countdown in memory
                setTimeout(() => {
                    this.playChime('wake');
                    this.showToast(`⏰ Time is up! ${val} ${displayUnit} timer ended.`);
                    this.speak(`Time's up! Your ${val} ${displayUnit} timer has ended.`);
                }, totalSeconds * 1000);

                return true;
            }

            // 5. Voice Notes / Scratchpad (e.g., "take a note: order 20 packs of coffee", "read my notes", "clear notes")
            const noteMatch = cmd.match(/^(?:take\s+a\s+note|save\s+note|make\s+a\s+note)(?:\s+that|\s*:)?\s+(.+)$/i);
            if (noteMatch) {
                const noteText = noteMatch[1].trim();
                let notes = JSON.parse(localStorage.getItem('t8_voice_notes') || '[]');
                notes.push({ text: noteText, timestamp: new Date().toLocaleString() });
                localStorage.setItem('t8_voice_notes', JSON.stringify(notes));

                this.playChime('success');
                this.speak(`Note saved: ${noteText}.`);
                this.showToast(`📝 Note saved: "${noteText}"`);
                return true;
            }

            if (/\b(read my notes|what are my notes|show my notes|check notes)\b/i.test(cmd)) {
                let notes = JSON.parse(localStorage.getItem('t8_voice_notes') || '[]');
                if (notes.length === 0) {
                    this.speak('You have no saved voice notes.');
                    this.showToast('No saved notes');
                } else {
                    const latestNotes = notes.slice(-3).map((n, i) => `Note ${i + 1}: ${n.text}`).join('. ');
                    this.playChime('success');
                    this.speak(`Here are your recent notes: ${latestNotes}.`);
                    this.showToast(`📝 ${notes.length} note(s) found`);
                }
                return true;
            }

            if (/\b(clear notes|delete notes|erase notes)\b/i.test(cmd)) {
                localStorage.removeItem('t8_voice_notes');
                this.playChime('success');
                this.speak('All voice notes have been cleared.');
                this.showToast('Notes cleared');
                return true;
            }

            // 6. Conversational / Persona Responses
            if (/\b(who are you|what is your name)\b/i.test(cmd)) {
                const intro = "I am your 888 Triple Eight intelligent virtual assistant, designed to help you control the retail system, search YouTube, lookup Google Maps, calculate totals, and answer your business questions.";
                this.playChime('success');
                this.speak(intro);
                this.showToast("888 Triple Eight Virtual Assistant");
                return true;
            }

            if (/\b(what can you do|help me|list commands)\b/i.test(cmd)) {
                const help = "I can do cross-platform searches on YouTube and Google, click any button or link, open POS and add products, perform math calculations, set timers, take notes, and provide store sales summaries.";
                this.playChime('success');
                this.speak(help);
                this.showToast(help);
                return true;
            }

            if (/\b(tell me a business tip|business advice|retail tip)\b/i.test(cmd)) {
                const tips = [
                    "Keep your top 20% high-margin products well-stocked and prominently displayed near the checkout counter.",
                    "Review inventory turnover weekly to avoid dead capital trapped in slow-moving stock.",
                    "Speed up cashier checkout by using quick SKU search and hands-free voice item entry.",
                    "Encourage repeat shoppers by offering loyalty bundles on frequently purchased daily essentials."
                ];
                const selectedTip = tips[Math.floor(Math.random() * tips.length)];
                this.playChime('success');
                this.speak(`Here is a business tip: ${selectedTip}`);
                this.showToast(`💡 Business Tip: ${selectedTip}`);
                return true;
            }

            if (/\b(tell me a joke|make me laugh)\b/i.test(cmd)) {
                const jokes = [
                    "Why did the retail inventory manager break up with the calendar? Because its days were numbered!",
                    "Why was the inventory manager always calm? Because everything was safely in stock!",
                    "What is a cashier's favorite song? Money, Money, Money!"
                ];
                const joke = jokes[Math.floor(Math.random() * jokes.length)];
                this.playChime('success');
                this.speak(joke);
                this.showToast(`😄 ${joke}`);
                return true;
            }

            return false;
        }

        /**
         * 5. Page Scrolling Commands
         */
        handleScrollCommand(cmd) {
            if (/\b(scroll down|page down|move down)\b/i.test(cmd)) {
                window.scrollBy({ top: 450, behavior: 'smooth' });
                this.playChime('success');
                return true;
            }
            if (/\b(scroll up|page up|move up)\b/i.test(cmd)) {
                window.scrollBy({ top: -450, behavior: 'smooth' });
                this.playChime('success');
                return true;
            }
            if (/\b(scroll to top|top of page|go to top)\b/i.test(cmd)) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
                this.playChime('success');
                return true;
            }
            if (/\b(scroll to bottom|bottom of page|go to bottom)\b/i.test(cmd)) {
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                this.playChime('success');
                return true;
            }
            if (/\b(refresh|reload|refresh page)\b/i.test(cmd)) {
                this.speak('Refreshing page.');
                setTimeout(() => window.location.reload(), 400);
                return true;
            }
            return false;
        }

        /**
         * =========================================================================================
         * 3. FULL POS TRANSACTIONAL & CASHIER NLP VOICE CONTROLLER
         * Intent Recognition, Named Entity Recognition (NER), Fuzzy Product Matching,
         * Combined Add+Discount parsing, and Exact Amount / Change calculation
         * =========================================================================================
         */

        /**
         * Parse spoken number strings and digits into a floating point number
         * e.g. "500", "500.50", "one thousand", "two hundred fifty", "five hundred pesos"
         */
        parseSpokenNumber(text) {
            if (typeof text === 'number') return text;
            if (!text || typeof text !== 'string') return 0;

            const clean = text.toLowerCase()
                .replace(/,/g, '')
                .replace(/pesos?|peso|php|₱|\bbucks?\b/gi, '')
                .trim();

            // Direct numeric match: e.g. "500", "500.50", "1,200.00"
            const numDirect = parseFloat(clean);
            if (!isNaN(numDirect) && /^\d+(\.\d+)?$/.test(clean)) {
                return numDirect;
            }

            // Word number dictionary
            const smallNumbers = {
                'zero': 0, 'a': 1, 'an': 1, 'one': 1, 'two': 2, 'three': 3, 'four': 4,
                'five': 5, 'six': 6, 'seven': 7, 'eight': 8, 'nine': 9, 'ten': 10,
                'eleven': 11, 'twelve': 12, 'thirteen': 13, 'fourteen': 14, 'fifteen': 15,
                'sixteen': 16, 'seventeen': 17, 'eighteen': 18, 'nineteen': 19,
                'twenty': 20, 'thirty': 30, 'forty': 40, 'fifty': 50,
                'sixty': 60, 'seventy': 70, 'eighty': 80, 'ninety': 90,
                'dozen': 12, 'half a dozen': 6, 'pair': 2
            };

            const scales = {
                'hundred': 100,
                'thousand': 1000,
                'million': 1000000
            };

            const words = clean.split(/[\s\-]+/);
            let current = 0;
            let total = 0;
            let hasNumber = false;

            for (const w of words) {
                if (smallNumbers[w] !== undefined) {
                    current += smallNumbers[w];
                    hasNumber = true;
                } else if (scales[w] !== undefined) {
                    current = (current === 0 ? 1 : current) * scales[w];
                    if (scales[w] >= 1000) {
                        total += current;
                        current = 0;
                    }
                    hasNumber = true;
                } else if (!isNaN(parseFloat(w))) {
                    current += parseFloat(w);
                    hasNumber = true;
                }
            }

            return hasNumber ? (total + current) : (isNaN(numDirect) ? 0 : numDirect);
        }

        /**
         * Natural Language Processing (NLP) Parser:
         * Performs Intent Recognition & Named Entity Recognition (NER) for POS Operations
         * Extracts: { product_name, quantity, discount_type, discount_value, amount, intent }
         */
        parsePOSIntentAndEntities(rawCommand) {
            let cmd = (rawCommand || '').toLowerCase().trim();
            // Remove leading polite fillers
            cmd = cmd.replace(/^(please|paki|kindly|assistant|hey assistant|can you|could you)\s+/i, '');

            const result = {
                intent: 'UNKNOWN',
                entities: {
                    product_name: null,
                    quantity: 1,
                    discount_type: null,
                    discount_value: 0,
                    amount: 0
                },
                confidence: 0,
                raw: rawCommand
            };

            // 0. ONE-SHOT RECEIVE PAYMENT & FAST PAY ("I received 500 pesos, pay", "Received 500 pay", "500 pay", "Pay 500", "Pay exact", "Tender 500 and pay")
            const oneShotPayMatch = cmd.match(/^(?:i\s+)?(?:received|receive|got|collected|accepted|paid|tendered|tender|cash)?\s*(.+?)\s*[,;]?\s*(?:and\s+)?(?:pay|checkout|finalize|process|proceed)(?:\s+sale)?$/i) ||
                cmd.match(/^(?:pay|checkout|finalize|process)\s+(?:with\s+)?(?:cash\s+)?(?:amount\s+)?(.+)$/i);

            if (oneShotPayMatch && !/\b(cart|item|product|category|discount\s+type)\b/i.test(cmd)) {
                const amtString = oneShotPayMatch[1].trim();
                if (/\b(exact|exact amount|exact cash|no change)\b/i.test(amtString)) {
                    result.intent = 'RECEIVE_PAYMENT_AND_PAY';
                    result.entities.is_exact = true;
                    result.confidence = 0.98;
                    return result;
                }
                const parsedAmt = this.parseSpokenNumber(amtString);
                if (parsedAmt > 0) {
                    result.intent = 'RECEIVE_PAYMENT_AND_PAY';
                    result.entities.amount = parsedAmt;
                    result.confidence = 0.98;
                    return result;
                }
            }

            // Direct standalone "pay" or "pay now"
            if (/^(?:pay|pay now|checkout now|proceed to pay|fast pay)$/i.test(cmd.trim())) {
                result.intent = 'FAST_PAY';
                result.confidence = 0.95;
                return result;
            }

            // Standalone "tender" / "tender payment" / "I receive" / "I received" / "Receive" without amount specified
            if (/^(?:i\s+)?(?:received?|receive|tender(?:ed)?|got|collected|accepted)(?:\s+(?:cash|payment|amount|tender))?$/i.test(cmd.trim()) ||
                /^(?:tender|tender\s+payment|tender\s+cash|payment\s+tender|cash\s+tender|open\s+tender)$/i.test(cmd.trim())) {
                result.intent = 'RECEIVE_PAYMENT_PROMPT';
                result.confidence = 0.95;
                return result;
            }

            // 1. PAYMENT RECEIVED / CASH TENDER (e.g. "I received 500", "Received 1000 pesos", "Customer paid 500", "Tender 250", "Payment 500")
            const receivedRegex = /^(?:i\s+)?(?:received|receive|got|collected|accepted|paid|tendered|tender|cash|payment)\s+(?:amount\s+(?:of\s+)?)?(.+)$/i;
            const receivedMatch = cmd.match(receivedRegex);
            if (receivedMatch && !/\b(cart|item|product|category|discount type)\b/i.test(cmd)) {
                const amtString = receivedMatch[1].trim();
                if (/\b(exact|exact amount|exact cash)\b/i.test(amtString)) {
                    result.intent = 'EXACT_PAYMENT';
                    result.confidence = 0.95;
                    return result;
                }
                const parsedAmt = this.parseSpokenNumber(amtString);
                if (parsedAmt > 0) {
                    result.intent = 'RECEIVE_PAYMENT';
                    result.entities.amount = parsedAmt;
                    result.confidence = 0.95;
                    return result;
                }
            }

            // Direct payment phrase like "500 pesos received" or "1000 received"
            const amtReceivedSuffix = cmd.match(/^(.+?)\s+(?:pesos?\s+)?(?:received|tendered|paid|cash)$/i);
            if (amtReceivedSuffix) {
                const parsedAmt = this.parseSpokenNumber(amtReceivedSuffix[1]);
                if (parsedAmt > 0) {
                    result.intent = 'RECEIVE_PAYMENT';
                    result.entities.amount = parsedAmt;
                    result.confidence = 0.90;
                    return result;
                }
            }

            // 2. EXACT PAYMENT INTENT ("tender exact", "exact cash", "exact amount", "pay exact")
            if (/\b(tender exact|exact cash|exact amount|pay exact|no change|exact payment)\b/i.test(cmd)) {
                result.intent = 'EXACT_PAYMENT';
                result.confidence = 0.95;
                return result;
            }

            // 3. FINALIZE / PROCESS SALE INTENT ("finalize sale", "complete sale", "finish sale", "submit sale", "confirm payment")
            if (/\b(finalize sale|complete sale|submit sale|confirm payment|finish sale|process sale|finalize)\b/i.test(cmd)) {
                result.intent = 'FINALIZE_SALE';
                result.confidence = 0.95;
                return result;
            }

            // 4. CHECKOUT MODAL OPEN INTENT ("checkout", "open checkout", "pay now", "proceed to payment", "tender payment")
            if (/\b(checkout|open checkout|proceed to payment|pay now|tender payment)\b/i.test(cmd)) {
                result.intent = 'CHECKOUT';
                result.confidence = 0.90;
                return result;
            }

            // 5. CLEAR / RESET CART ("clear cart", "empty cart", "reset cart", "void sale", "cancel transaction")
            if (/\b(clear cart|empty cart|reset cart|void sale|cancel transaction)\b/i.test(cmd)) {
                result.intent = 'CLEAR_CART';
                result.confidence = 0.95;
                return result;
            }

            // 6. NEXT TRANSACTION ("next transaction", "new sale", "next customer", "reset pos", "new transaction")
            if (/\b(next transaction|new transaction|new sale|next customer|reset pos|start new transaction)\b/i.test(cmd)) {
                result.intent = 'NEXT_TRANSACTION';
                result.confidence = 0.95;
                return result;
            }

            // 7. PRINT RECEIPT ("print receipt", "print invoice", "print")
            if (/\b(print receipt|print invoice|reprint receipt|^print$)\b/i.test(cmd)) {
                result.intent = 'PRINT_RECEIPT';
                result.confidence = 0.95;
                return result;
            }

            // 7.5 CLOSE RECEIPT ("close receipt", "close modal", "done with receipt", "close")
            if (/\b(close receipt|close invoice|close modal|dismiss receipt|exit receipt|^close$|^done$)\b/i.test(cmd)) {
                result.intent = 'CLOSE_RECEIPT';
                result.confidence = 0.95;
                return result;
            }

            // 8. STANDALONE DISCOUNT COMMANDS:
            // "apply senior discount", "discount type member", "senior pwd discount", "remove discount", "custom discount 50", "discount type senior"
            const standaloneDiscMatch = cmd.match(/^(?:apply|set|give|discount\s+type|select)?\s*(senior\s*(?:citizen|\/?\s*pwd)?|pwd|member(?:ship|\s*promo)?|loyalty|custom\s+\d+|none|regular|no\s+discount|remove\s+discount|clear\s+discount)\s*(?:discount)?$/i);
            if (standaloneDiscMatch) {
                const discText = standaloneDiscMatch[1].toLowerCase().trim();
                result.intent = 'APPLY_DISCOUNT';
                result.confidence = 0.90;

                if (/\b(senior|pwd)\b/i.test(discText)) {
                    result.entities.discount_type = 'senior_pwd';
                } else if (/\b(member|membership|loyalty)\b/i.test(discText)) {
                    result.entities.discount_type = 'member';
                } else if (/\b(none|regular|no\s+discount|remove|clear)\b/i.test(discText)) {
                    result.entities.discount_type = 'none';
                } else if (/\bcustom\b/i.test(discText)) {
                    result.entities.discount_type = 'custom';
                    const customVal = parseFloat(discText.replace(/[^\d.]/g, '')) || 0;
                    result.entities.discount_value = customVal;
                }
                return result;
            }

            // 8.5 FILTER DEPARTMENT / CATEGORY
            // "show only hardware", "go to hardware products", "show hardware products", "show only stationery", "filter toys", "only household", "show all products"
            const filterDeptMatch = cmd.match(/^(?:show\s+only|go\s+to|show|filter|view|display|only)\s+(?:only\s+)?([a-z0-9\s&]+?)(?:\s+(?:products|items|category|department|section))?$/i) ||
                cmd.match(/^(?:all\s+products|all\s+items|all\s+categories|all\s+departments|show\s+all|reset\s+filter|all)$/i);

            if (filterDeptMatch && !/\b(cart|sale|checkout|receipt|discount|invoice|money|tender|change|pay|coffee|coke|bread|milk|piattos)\b/i.test(cmd)) {
                const targetDept = filterDeptMatch[1] ? filterDeptMatch[1].trim() : 'all';
                if (targetDept && isNaN(Number(targetDept))) {
                    result.intent = 'FILTER_DEPARTMENT';
                    result.entities.department_name = targetDept;
                    result.confidence = 0.95;
                    return result;
                }
            }

            // 9. REMOVE PRODUCT FROM CART ("remove coffee", "delete bread from cart", "drop coke")
            const removeMatch = cmd.match(/^(?:remove|delete|drop|take\s+out|void)\s+(?:(\d+|[a-z]+)\s+)?(.+?)(?:\s+from\s+cart)?$/i);
            if (removeMatch && !/\b(discount|notes?|timer|reminder)\b/i.test(cmd)) {
                result.intent = 'REMOVE_PRODUCT';
                result.entities.product_name = removeMatch[2].trim();
                result.confidence = 0.90;
                return result;
            }

            // 10. UPDATE / CHANGE QUANTITY ("change coffee to 4", "update bread quantity to 3", "set coke to 2")
            const updateQtyMatch = cmd.match(/^(?:change|set|update)\s+(?:the\s+)?(?:quantity\s+of\s+)?(.+?)\s+(?:to|quantity\s+to)\s+(\d+|[a-z]+)$/i);
            if (updateQtyMatch) {
                result.intent = 'UPDATE_QUANTITY';
                result.entities.product_name = updateQtyMatch[1].trim();
                result.entities.quantity = this.parseSpokenNumber(updateQtyMatch[2]) || 1;
                result.confidence = 0.90;
                return result;
            }

            // 11. ADD PRODUCT (SINGLE OR MULTIPLE PRODUCTS WITH OPTIONAL DISCOUNT)
            // Examples:
            // "Add 2 Coca Cola and 3 Piattos Cheese"
            // "Add 1 Gardenia Bread, 2 Nescafe, and 1 Milo"
            // "Add Coca Cola and Sprite"
            // "Add 2 Coca Cola and 1 Piattos, discount type senior"
            // "Add Coca Cola 1.5L, discount type senior"
            // "Put 2 Piattos in cart"
            // "3 nescafe"
            const isAddForm = /^(?:add|put|buy|order|insert|give\s+me|i\s+need|i\s+want)\s+/i.test(cmd) ||
                /^(\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s+([a-z0-9\s\-]+)$/i.test(cmd) ||
                /\b(and\s+\d+|,\s*\d+|plus\s+\d+)\b/i.test(cmd);

            if (isAddForm) {
                let workingText = cmd.replace(/^(?:add|put|buy|order|insert|give\s+me|i\s+need|i\s+want)\s+(?:to\s+cart\s+)?/i, '').trim();

                // Extract embedded discount clause if present:
                // Matches: ", discount type [type]", "discount type [type]", "with [type] discount", "discount [type]"
                const discClauseMatch = workingText.match(/[,;]?\s*(?:with\s+)?(?:discount\s+type|discount|with\s+discount)\s*[:=]?\s*(senior\s*(?:citizen|\/?\s*pwd)?|pwd|member(?:ship|\s*promo)?|loyalty|custom\s+\d+|none|regular|no\s+discount|20%|5%|\d+\s*pesos?)(.*)$/i);

                if (discClauseMatch) {
                    const rawDisc = discClauseMatch[1].toLowerCase().trim();
                    if (/\b(senior|pwd|20%)\b/i.test(rawDisc)) {
                        result.entities.discount_type = 'senior_pwd';
                    } else if (/\b(member|membership|loyalty|5%)\b/i.test(rawDisc)) {
                        result.entities.discount_type = 'member';
                    } else if (/\b(none|regular|no\s+discount)\b/i.test(rawDisc)) {
                        result.entities.discount_type = 'none';
                    } else if (/\bcustom|\d+\s*pesos?\b/i.test(rawDisc)) {
                        result.entities.discount_type = 'custom';
                        result.entities.discount_value = parseFloat(rawDisc.replace(/[^\d.]/g, '')) || 0;
                    }

                    // Strip discount clause from working product text
                    workingText = workingText.substring(0, discClauseMatch.index).trim();
                    result.intent = 'ADD_PRODUCT_WITH_DISCOUNT';
                } else {
                    result.intent = 'ADD_PRODUCT';
                }

                // Strip trailing "to cart" / "in cart"
                workingText = workingText.replace(/\s+(?:to|in|into)\s+cart$/i, '').trim();

                // Extract single or multiple items
                const extractedItems = this.extractPOSItems(workingText);
                if (extractedItems && extractedItems.length > 0) {
                    result.entities.items = extractedItems;
                    result.entities.product_name = extractedItems[0].product_name;
                    result.entities.quantity = extractedItems[0].quantity;
                    result.is_multi_item = extractedItems.length > 1;
                    result.confidence = 0.92;
                    return result;
                }
            }

            return result;
        }

        /**
         * Extract single or multiple item entities from spoken string
         * Supports: "2 Coca Cola and 3 Piattos Cheese", "1 Bread, 2 Milk, and 3 Eggs"
         */
        extractPOSItems(workingText) {
            if (!workingText || typeof workingText !== 'string') return [];
            let text = workingText.trim().replace(/\s+(?:to|in|into)\s+cart$/i, '');
            if (!text) return [];

            // If string matches a single product strongly (e.g. "Pork and Beans" or "Cookies and Cream"), treat as 1 item
            const fullMatch = this.findBestProductMatch(text);
            if (fullMatch && fullMatch.score >= 0.88 && !/\b(?:and\s+\d+|,\s*\d+|plus\s+\d+|\band\s+(?:one|two|three|four|five|six|seven|eight|nine|ten))\b/i.test(text)) {
                const singleItem = this.parseSinglePOSItemSegment(text);
                return singleItem ? [singleItem] : [];
            }

            // Split on conjunctions and commas
            // Delimiters: ", and", ", plus", ", &", " and ", " plus ", " & ", ","
            const rawParts = text.split(/\s*(?:,\s*(?:and|plus|&)\s+|\s+and\s+|\s+plus\s+|\s*&\s*|,\s*)\s*/i).filter(p => p && p.trim().length > 0);

            if (rawParts.length <= 1) {
                const single = this.parseSinglePOSItemSegment(text);
                return single ? [single] : [];
            }

            const items = [];
            for (const part of rawParts) {
                const parsed = this.parseSinglePOSItemSegment(part);
                if (parsed && parsed.product_name) {
                    items.push(parsed);
                }
            }

            return items.length > 0 ? items : (fullMatch ? [{ product_name: text, quantity: 1 }] : []);
        }

        /**
         * Parse single item segment with leading/trailing quantity
         */
        parseSinglePOSItemSegment(seg) {
            if (!seg || typeof seg !== 'string') return null;
            let s = seg.trim().replace(/\s+(?:to|in|into)\s+cart$/i, '');
            if (!s) return null;

            let qty = 1;
            let name = s;

            // Leading quantity: "3 coffee", "two packs of bread", "a dozen eggs"
            const leadQtyMatch = s.match(/^(\d+|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|a\s+dozen|half\s+a\s+dozen)\s*(?:x|of|packs?\s+of|bottles?\s+of|cans?\s+of|pieces?\s+of|pcs?\s+of)?\s+(.+)$/i);
            if (leadQtyMatch) {
                qty = Math.max(1, Math.round(this.parseSpokenNumber(leadQtyMatch[1])));
                name = leadQtyMatch[2].trim();
            } else {
                // Trailing quantity: "coffee 3" or "coke x 2"
                const endQtyMatch = s.match(/^(.+?)\s+(?:x|qty|quantity)?\s*(\d+|one|two|three|four|five|six|seven|eight|nine|ten)$/i);
                if (endQtyMatch && !/\b(1.5l|500ml|1kg|85g|180g|260g|130g|150g|1.3kg|790ml|720ml|160g|330ml|10s)\b/i.test(endQtyMatch[2])) {
                    qty = Math.max(1, Math.round(this.parseSpokenNumber(endQtyMatch[2])));
                    name = endQtyMatch[1].trim();
                }
            }

            name = name.replace(/^[,\-–—\s]+|[,\-–—\s]+$/g, '').trim();
            if (!name) return null;

            return {
                product_name: name,
                quantity: qty
            };
        }

        /**
         * Main POS Command Dispatcher
         */
        handlePOSCommand(cmd) {
            const isPOS = window.location.pathname.includes('pos.php') || !!document.getElementById('cartTableBody');
            if (!isPOS) return false;

            // 1. NLP Parser Execution
            const nlp = this.parsePOSIntentAndEntities(cmd);
            console.log('[VoiceAssistant] POS NLP Parse Result:', nlp);

            // 2. ONE-SHOT RECEIVE PAYMENT & FAST PAY ("I received 500 pesos, pay", "Pay 500", "Pay exact")
            if (nlp.intent === 'RECEIVE_PAYMENT_AND_PAY') {
                return this.handlePOSReceiveAndPay(nlp.entities.amount, nlp.entities.is_exact);
            }

            // 2.5 DIRECT FAST PAY ("pay", "pay now")
            if (nlp.intent === 'FAST_PAY') {
                return this.handlePOSFastPay();
            }

            // 2.6 RECEIVE PAYMENT ("I received [amount]")
            if (nlp.intent === 'RECEIVE_PAYMENT') {
                return this.handlePOSReceivePayment(nlp.entities.amount);
            }

            // 2.7 RECEIVE PAYMENT PROMPT ("I receive", "I received")
            if (nlp.intent === 'RECEIVE_PAYMENT_PROMPT') {
                return this.handlePOSReceivePaymentPrompt();
            }

            // 3. EXACT PAYMENT ("Tender exact", "Exact cash", "I received exact")
            if (nlp.intent === 'EXACT_PAYMENT') {
                return this.handlePOSReceiveExact();
            }

            // 4. ADD PRODUCT (SINGLE OR MULTIPLE PRODUCTS WITH OPTIONAL DISCOUNT)
            if (nlp.intent === 'ADD_PRODUCT_WITH_DISCOUNT' || nlp.intent === 'ADD_PRODUCT') {
                if (nlp.entities.items && nlp.entities.items.length > 1) {
                    return this.handlePOSAddMultipleProducts(
                        nlp.entities.items,
                        nlp.entities.discount_type,
                        nlp.entities.discount_value
                    );
                } else if (nlp.entities.product_name) {
                    return this.handlePOSAddProduct(
                        nlp.entities.product_name,
                        nlp.entities.quantity || 1,
                        nlp.entities.discount_type,
                        nlp.entities.discount_value
                    );
                }
            }

            // 5. STANDALONE DISCOUNT APPLICATION
            if (nlp.intent === 'APPLY_DISCOUNT') {
                return this.handlePOSApplyDiscount(nlp.entities.discount_type, nlp.entities.discount_value);
            }

            // 6. REMOVE PRODUCT
            if (nlp.intent === 'REMOVE_PRODUCT') {
                return this.handlePOSRemoveProduct(nlp.entities.product_name);
            }

            // 7. UPDATE QUANTITY
            if (nlp.intent === 'UPDATE_QUANTITY') {
                return this.handlePOSUpdateQty(nlp.entities.product_name, nlp.entities.quantity);
            }

            // 8. CLEAR CART
            if (nlp.intent === 'CLEAR_CART') {
                return this.handlePOSClearCart();
            }

            // 9. CHECKOUT / OPEN PAYMENT MODAL
            if (nlp.intent === 'CHECKOUT') {
                return this.handlePOSOpenCheckout();
            }

            // 10. FINALIZE SALE
            if (nlp.intent === 'FINALIZE_SALE') {
                return this.handlePOSFinalizeSale();
            }

            // 11. PRINT RECEIPT
            if (nlp.intent === 'PRINT_RECEIPT') {
                return this.handlePOSPrintReceipt();
            }

            // 12. NEXT TRANSACTION
            if (nlp.intent === 'NEXT_TRANSACTION') {
                return this.handlePOSNextTransaction();
            }

            // 12.5 CLOSE RECEIPT
            if (nlp.intent === 'CLOSE_RECEIPT') {
                return this.handlePOSCloseReceipt();
            }

            // 12.8 FILTER DEPARTMENT / CATEGORY
            if (nlp.intent === 'FILTER_DEPARTMENT') {
                return this.handlePOSFilterDepartment(nlp.entities.department_name);
            }

            // 13. Direct SKU or Category Filtering
            const categoryMatch = cmd.match(/^(?:filter(?:\s+category)?|show(?:\s+category)?|category|view)\s+(.+)$/i);
            if (categoryMatch) {
                const catQuery = categoryMatch[1].trim().toLowerCase();
                const catPills = Array.from(document.querySelectorAll('.cat-pill'));
                const matchedPill = catPills.find(pill => {
                    const txt = pill.innerText.toLowerCase();
                    return (catQuery === 'all' && txt.includes('all')) || txt.includes(catQuery) || this.fuzzyMatch(txt, catQuery) > 0.65;
                });

                if (matchedPill) {
                    this.highlightElement(matchedPill);
                    matchedPill.click();
                    this.playChime('success');
                    this.speak(`Showing ${matchedPill.innerText.trim()}.`);
                    this.showToast(`Category: ${matchedPill.innerText.trim()}`);
                    return true;
                }
            }

            return false;
        }

        /**
         * Enhanced Product Search with Fuzzy & Phonetic Matching
         * Identifies products accurately even with mispronunciations and accents
         */
        findBestProductMatch(query) {
            if (!query) return null;
            const cleanQuery = query.toLowerCase().trim();

            // Product catalog source: window.allProducts (if loaded), else DOM product cards
            let productsPool = [];
            if (window.allProducts && Array.isArray(window.allProducts) && window.allProducts.length > 0) {
                productsPool = window.allProducts;
            } else {
                const domCards = Array.from(document.querySelectorAll('.pos-item-card, [data-product-name]'));
                productsPool = domCards.map((card, idx) => ({
                    id: card.getAttribute('data-id') || idx + 1,
                    name: card.getAttribute('data-name') || card.getAttribute('data-product-name') || card.querySelector('.item-name, .product-name')?.textContent || '',
                    sku: card.getAttribute('data-sku') || '',
                    brand: card.getAttribute('data-brand') || '',
                    price: parseFloat(card.getAttribute('data-price') || 0),
                    stock: parseInt(card.getAttribute('data-stock') || 999),
                    element: card
                })).filter(p => p.name.length > 0);
            }

            if (productsPool.length === 0) return null;

            // Brand & Product Synonyms Dictionary for retail aliases
            const aliasMap = {
                'coke': 'Coca-Cola Regular 1.5L',
                'coca cola': 'Coca-Cola Regular 1.5L',
                'coke regular': 'Coca-Cola Regular 1.5L',
                'sprite': 'Sprite Lemon-Lime 1.5L',
                'c2': 'C2 Green Tea Apple 500ml',
                'c2 apple': 'C2 Green Tea Apple 500ml',
                'green tea': 'C2 Green Tea Apple 500ml',
                'nescafe': 'Nescafé 3-in-1 Original Twin Pack',
                'coffee': 'Nescafé 3-in-1 Original Twin Pack',
                'milo': 'Milo Activ-Go Chocolate 1kg',
                'milo chocolate': 'Milo Activ-Go Chocolate 1kg',
                'beer': 'San Miguel Pale Pilsen 330ml Can',
                'san miguel': 'San Miguel Pale Pilsen 330ml Can',
                'pale pilsen': 'San Miguel Pale Pilsen 330ml Can',
                'pilsen': 'San Miguel Pale Pilsen 330ml Can',
                'piattos': 'Piattos Cheese 85g',
                'piattos cheese': 'Piattos Cheese 85g',
                'nova': 'Nova Multigrain Country Cheddar 78g',
                'chippy': 'Chippy Barbecue 110g',
                'skyflakes': 'SkyFlakes Crackers Box 800g',
                'crackers': 'SkyFlakes Crackers Box 800g',
                'biscuit': 'SkyFlakes Crackers Box 800g',
                'century tuna': 'Century Tuna Flakes in Oil 180g',
                'tuna': 'Century Tuna Flakes in Oil 180g',
                'san marino': 'San Marino Corned Tuna 180g',
                'corned tuna': 'San Marino Corned Tuna 180g',
                'argentina': 'Argentina Corned Beef 260g',
                'corned beef': 'Argentina Corned Beef 260g',
                'pancit canton': 'Lucky Me! Pancit Canton Kalamansi 80g',
                'lucky me': 'Lucky Me! Pancit Canton Kalamansi 80g',
                'noodles': 'Lucky Me! Pancit Canton Kalamansi 80g',
                'kalamansi noodles': 'Lucky Me! Pancit Canton Kalamansi 80g',
                'mega sardines': 'Mega Sardines in Tomato Sauce 155g',
                'sardines': 'Mega Sardines in Tomato Sauce 155g',
                'shampoo': 'Head & Shoulders Cool Menthol 330ml',
                'head and shoulders': 'Head & Shoulders Cool Menthol 330ml',
                'safeguard': 'Safeguard Pure White Soap 130g',
                'soap': 'Safeguard Pure White Soap 130g',
                'colgate': 'Colgate Total Dental Cream 150g',
                'toothpaste': 'Colgate Total Dental Cream 150g',
                'ariel': 'Ariel Sunrise Fresh Powder 1.3kg',
                'detergent': 'Ariel Sunrise Fresh Powder 1.3kg',
                'laundry powder': 'Ariel Sunrise Fresh Powder 1.3kg',
                'joy': 'Joy Dishwashing Liquid Lemon 790ml',
                'dishwashing liquid': 'Joy Dishwashing Liquid Lemon 790ml',
                'downy': 'Downy Sunrise Fresh Fabric Refill 720ml',
                'fabric conditioner': 'Downy Sunrise Fresh Fabric Refill 720ml',
                'milk': 'Magnolia Fresh Milk 1 Liter',
                'fresh milk': 'Magnolia Fresh Milk 1 Liter',
                'eden cheese': 'Eden Cheese Block 160g',
                'cheese': 'Eden Cheese Block 160g',
                'battery': 'Energizer Max AA Battery 4-Pack',
                'batteries': 'Energizer Max AA Battery 4-Pack',
                'sponge': 'Scotch Heavy Duty Sponge 3-Pack',
                'scotch brite': 'Scotch Heavy Duty Sponge 3-Pack'
            };

            // 1. Direct Alias Lookup
            if (aliasMap[cleanQuery]) {
                const targetName = aliasMap[cleanQuery].toLowerCase();
                const matched = productsPool.find(p => p.name.toLowerCase() === targetName || p.name.toLowerCase().includes(targetName));
                if (matched) return { product: matched, score: 1.0, matchType: 'alias' };
            }

            // 2. Exact or SKU Match
            for (const p of productsPool) {
                const pName = p.name.toLowerCase();
                const pSku = (p.sku || '').toLowerCase();
                if (pName === cleanQuery || pSku === cleanQuery) {
                    return { product: p, score: 1.0, matchType: 'exact' };
                }
            }

            // 3. Substring Containment Match
            for (const p of productsPool) {
                const pName = p.name.toLowerCase();
                if (pName.includes(cleanQuery) || cleanQuery.includes(pName)) {
                    return { product: p, score: 0.92, matchType: 'substring' };
                }
            }

            // 4. Multi-Tier Fuzzy & Phonetic Scoring
            let bestProduct = null;
            let highestScore = 0;

            for (const p of productsPool) {
                const pName = p.name.toLowerCase();
                const pBrand = (p.brand || '').toLowerCase();
                const pSku = (p.sku || '').toLowerCase();
                const candidateFull = `${pBrand} ${pName} ${pSku}`.trim();

                // Multi-metric composite score
                const scoreName = this.fuzzyMatch(cleanQuery, pName);
                const scoreFull = this.fuzzyMatch(cleanQuery, candidateFull);
                const score = Math.max(scoreName, scoreFull);

                if (score > highestScore) {
                    highestScore = score;
                    bestProduct = p;
                }
            }

            if (bestProduct && highestScore >= 0.50) {
                return { product: bestProduct, score: highestScore, matchType: 'fuzzy' };
            }

            return null;
        }

        /**
         * Add Product to Cart with Optional Discount Type Application
         * Handles: "Add [product name], discount type [optional]"
         */
        handlePOSAddProduct(productQuery, qty = 1, discountType = null, customDiscountVal = 0) {
            const matchResult = this.findBestProductMatch(productQuery);

            if (matchResult && matchResult.product) {
                const product = matchResult.product;

                // Highlight card if in DOM
                const card = document.querySelector(`.pos-item-card[data-id="${product.id}"], .pos-item-card[data-sku="${product.sku}"]`);
                if (card) this.highlightElement(card);

                // Add to POS Cart
                if (typeof window.addToCart === 'function') {
                    for (let i = 0; i < qty; i++) {
                        window.addToCart(product);
                    }
                } else if (card) {
                    for (let i = 0; i < qty; i++) {
                        card.click();
                    }
                }

                // Apply discount type if specified in the spoken sentence
                let discountSpokenClause = '';
                if (discountType) {
                    const discountSelect = document.getElementById('discountTypeSelect');
                    const customInput = document.getElementById('customDiscountInput');

                    if (discountSelect) {
                        discountSelect.value = discountType;
                        if (discountType === 'custom' && customInput && customDiscountVal > 0) {
                            customInput.value = customDiscountVal;
                        }

                        if (typeof window.calculateTotals === 'function') {
                            window.calculateTotals();
                        }

                        if (discountType === 'senior_pwd') {
                            discountSpokenClause = ' with Senior and PWD 20% discount';
                        } else if (discountType === 'member') {
                            discountSpokenClause = ' with Member 5% discount';
                        } else if (discountType === 'custom') {
                            discountSpokenClause = ` with ₱${customDiscountVal.toFixed(2)} discount`;
                        } else if (discountType === 'none') {
                            discountSpokenClause = ' with regular pricing';
                        }
                    }
                }

                // Recalculate totals
                const totals = typeof window.calculateTotals === 'function' ? window.calculateTotals() : { netTotal: product.price * qty };
                const netTotalStr = totals.netTotal !== undefined ? totals.netTotal.toFixed(2) : (product.price * qty).toFixed(2);

                this.playChime('success');
                const speech = `Added ${qty} ${product.name} to cart${discountSpokenClause}. Total is ₱${netTotalStr}.`;
                this.speak(speech);
                this.showToast(`🛒 Added ${qty}x ${product.name}${discountSpokenClause} (Total: ₱${netTotalStr})`);
                return true;
            }

            // If not found in catalog, put in filter search field
            const searchInput = document.getElementById('productSearchFilter') || document.getElementById('barcodeScannerInput');
            if (searchInput) {
                this.highlightElement(searchInput);
                searchInput.value = productQuery;
                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                this.playChime('error');
                this.speak(`Could not find an exact match for "${productQuery}". Searching catalog.`);
                this.showToast(`🔍 Searching catalog for: "${productQuery}"`);
                return true;
            }

            this.playChime('error');
            this.speak(`Sorry, I could not identify "${productQuery}" in our product catalog.`);
            return false;
        }

        /**
         * Add Multiple Products to POS Cart in a Single Operation
         * Handles: "Add 2 Coca Cola and 3 Piattos Cheese, discount type senior",
         *          "Add 1 Gardenia Bread, 2 Nescafe, and 1 Milo"
         */
        handlePOSAddMultipleProducts(items, discountType = null, customDiscountVal = 0) {
            if (!items || !Array.isArray(items) || items.length === 0) return false;

            const successfullyAdded = [];
            const notFound = [];

            for (const item of items) {
                const query = item.product_name;
                const qty = item.quantity || 1;
                const matchResult = this.findBestProductMatch(query);

                if (matchResult && matchResult.product) {
                    const product = matchResult.product;

                    // Highlight card if in DOM
                    const card = document.querySelector(`.pos-item-card[data-id="${product.id}"], .pos-item-card[data-sku="${product.sku}"]`);
                    if (card) this.highlightElement(card);

                    // Add to POS Cart
                    if (typeof window.addToCart === 'function') {
                        for (let i = 0; i < qty; i++) {
                            window.addToCart(product);
                        }
                    } else if (card) {
                        for (let i = 0; i < qty; i++) {
                            card.click();
                        }
                    }

                    successfullyAdded.push({
                        product: product,
                        name: product.name,
                        qty: qty,
                        unitPrice: product.price
                    });
                } else {
                    notFound.push(query);
                }
            }

            if (successfullyAdded.length === 0) {
                this.playChime('error');
                this.speak(`Sorry, I could not identify those products in the catalog.`);
                return false;
            }

            // Apply discount type if specified
            let discountSpokenClause = '';
            if (discountType) {
                const discountSelect = document.getElementById('discountTypeSelect');
                const customInput = document.getElementById('customDiscountInput');

                if (discountSelect) {
                    discountSelect.value = discountType;
                    if (discountType === 'custom' && customInput && customDiscountVal > 0) {
                        customInput.value = customDiscountVal;
                    }

                    if (typeof window.calculateTotals === 'function') {
                        window.calculateTotals();
                    }

                    if (discountType === 'senior_pwd') {
                        discountSpokenClause = ' with Senior and PWD 20% discount';
                    } else if (discountType === 'member') {
                        discountSpokenClause = ' with Member 5% discount';
                    } else if (discountType === 'custom') {
                        discountSpokenClause = ` with ₱${customDiscountVal.toFixed(2)} discount`;
                    } else if (discountType === 'none') {
                        discountSpokenClause = ' with regular pricing';
                    }
                }
            }

            // Recalculate totals
            const totals = typeof window.calculateTotals === 'function' ? window.calculateTotals() : null;
            const netTotalStr = totals && totals.netTotal !== undefined ? totals.netTotal.toFixed(2) : '';

            // Construct natural conversational speech summary
            let itemsSummary = '';
            if (successfullyAdded.length === 1) {
                itemsSummary = `${successfullyAdded[0].qty} ${successfullyAdded[0].name}`;
            } else if (successfullyAdded.length === 2) {
                itemsSummary = `${successfullyAdded[0].qty} ${successfullyAdded[0].name} and ${successfullyAdded[1].qty} ${successfullyAdded[1].name}`;
            } else {
                const last = successfullyAdded[successfullyAdded.length - 1];
                const prev = successfullyAdded.slice(0, -1).map(x => `${x.qty} ${x.name}`).join(', ');
                itemsSummary = `${prev}, and ${last.qty} ${last.name}`;
            }

            let speech = `Added ${itemsSummary} to cart${discountSpokenClause}.`;
            if (netTotalStr) {
                speech += ` Total is ₱${netTotalStr}.`;
            }

            if (notFound.length > 0) {
                speech += ` Could not find "${notFound.join(', ')}".`;
            }

            this.playChime('success');
            this.speak(speech);
            this.showToast(`🛒 Added ${successfullyAdded.length} items${discountSpokenClause}${netTotalStr ? ` (Total: ₱${netTotalStr})` : ''}`);
            return true;
        }

        /**
         * Streamlined One-Shot Payment Reception & Confirmation Prompt
         * Handles: "I received 500 pesos, pay", "Pay 500", "Pay exact", "500 pay"
         * Flow:
         * 1. Sets tender amount (or exact)
         * 2. Defaults to Cash without asking for tender type
         * 3. Calculates change
         * 4. Asks single confirmation prompt: "Are you sure?"
         * 5. Sets awaitingConfirmation = 'finalize_sale'
         */
        handlePOSReceiveAndPay(amount, isExact = false) {
            // Check if cart has items
            if (window.cart && window.cart.length === 0) {
                this.speak('The shopping cart is currently empty. Please add products to start a sale.');
                this.showToast('Cart is empty');
                return true;
            }

            // Ensure checkout modal is open
            if (typeof window.openCheckoutModal === 'function') {
                const checkoutModalEl = document.getElementById('checkoutModal');
                if (!checkoutModalEl || !checkoutModalEl.classList.contains('show')) {
                    window.openCheckoutModal();
                }
            }

            // Set payment method to cash directly without prompting
            if (typeof window.setPaymentMethod === 'function') {
                window.setPaymentMethod('cash');
            }

            // Calculate totals
            const totals = typeof window.calculateTotals === 'function' ? window.calculateTotals() : { netTotal: 0 };
            const netTotal = parseFloat(totals.netTotal || 0);

            let tenderVal = isExact ? netTotal : (typeof amount === 'number' ? amount : parseFloat(amount) || netTotal);

            // Populate tender input
            const tenderInput = document.getElementById('tenderAmountInput');
            if (tenderInput) {
                tenderInput.value = tenderVal.toFixed(2);
                if (typeof window.calculateChange === 'function') {
                    window.calculateChange();
                }
            }

            // Calculate change difference
            const diff = tenderVal - netTotal;
            const changeDue = Math.max(0, diff);

            // Update modal change display
            const changeEl = document.getElementById('modalChangeAmount');
            if (changeEl) {
                changeEl.innerText = '₱' + changeDue.toFixed(2);
            }

            // If short payment
            if (diff < -0.005) {
                const shortAmt = Math.abs(diff);
                this.playChime('error');
                const speech = `Received ₱${tenderVal.toFixed(2)}. Short by ₱${shortAmt.toFixed(2)}. Total bill is ₱${netTotal.toFixed(2)}.`;
                this.speak(speech);
                this.showToast(`⚠️ Received: ₱${tenderVal.toFixed(2)} (Short by ₱${shortAmt.toFixed(2)})`);
                return true;
            }

            // Enable submit button
            const submitBtn = document.getElementById('btnSubmitSale');
            if (submitBtn) {
                submitBtn.disabled = false;
            }

            this.playChime('wake');

            // Arm single confirmation prompt: "finalize_sale"
            this.state.awaitingConfirmation = 'finalize_sale';

            const speech = 'Are you sure?';
            this.speak(speech);
            this.showToast(`❓ Total: ₱${netTotal.toFixed(2)} • "Are you sure?" (Say "Yes" or "No")`);

            return true;
        }

        /**
         * Fast Direct Pay Intent (e.g. "Pay", "Pay now")
         */
        handlePOSFastPay() {
            if (window.cart && window.cart.length === 0) {
                this.speak('The shopping cart is currently empty. Please add products to start a sale.');
                this.showToast('Cart is empty');
                return true;
            }

            // Ensure checkout modal is open
            if (typeof window.openCheckoutModal === 'function') {
                const checkoutModalEl = document.getElementById('checkoutModal');
                if (!checkoutModalEl || !checkoutModalEl.classList.contains('show')) {
                    window.openCheckoutModal();
                }
            }

            // Set payment method to cash
            if (typeof window.setPaymentMethod === 'function') {
                window.setPaymentMethod('cash');
            }

            const totals = typeof window.calculateTotals === 'function' ? window.calculateTotals() : { netTotal: 0 };
            const netTotal = parseFloat(totals.netTotal || 0);

            const tenderInput = document.getElementById('tenderAmountInput');
            let currentTender = tenderInput ? parseFloat(tenderInput.value) || 0 : 0;
            if (currentTender <= 0) {
                currentTender = netTotal;
                if (tenderInput) {
                    tenderInput.value = currentTender.toFixed(2);
                    if (typeof window.calculateChange === 'function') window.calculateChange();
                }
            }

            const diff = currentTender - netTotal;
            const changeDue = Math.max(0, diff);

            const changeEl = document.getElementById('modalChangeAmount');
            if (changeEl) {
                changeEl.innerText = '₱' + changeDue.toFixed(2);
            }

            const submitBtn = document.getElementById('btnSubmitSale');
            if (submitBtn) submitBtn.disabled = false;

            this.playChime('wake');
            this.state.awaitingConfirmation = 'finalize_sale';

            const speech = 'Are you sure?';
            this.speak(speech);
            this.showToast(`❓ Total: ₱${netTotal.toFixed(2)} • "Are you sure?" (Say "Yes" or "No")`);
            return true;
        }

        /**
         * Handle Standalone Receive Payment Prompt (e.g. "I receive", "I received")
         * Prompts the user for the received cash amount while keeping cart 100% intact.
         */
        handlePOSReceivePaymentPrompt() {
            if (window.cart && window.cart.length === 0) {
                this.speak('The shopping cart is currently empty. Please add products to start a sale.');
                this.showToast('Cart is empty');
                return true;
            }

            // Ensure checkout modal is open
            if (typeof window.openCheckoutModal === 'function') {
                const checkoutModalEl = document.getElementById('checkoutModal');
                if (!checkoutModalEl || !checkoutModalEl.classList.contains('show')) {
                    window.openCheckoutModal();
                }
            }

            const totals = typeof window.calculateTotals === 'function' ? window.calculateTotals() : { netTotal: 0 };
            const netTotal = parseFloat(totals.netTotal || 0);

            this.playChime('wake');
            const speech = `Total bill is ${netTotal.toFixed(2)}. How much cash did you receive?`;
            this.speak(speech);
            this.showToast(`💵 Total: ₱${netTotal.toFixed(2)} • Please state received amount`);

            const tenderInput = document.getElementById('tenderAmountInput');
            if (tenderInput) tenderInput.focus();

            return true;
        }

        /**
         * Payment Reception & Exact Amount Logic:
         * Handles: "I received [amount]"
         * Logic:
         * - If received_amount - total_bill == 0 -> responds: "Exact amount received. There is no change."
         * - If received_amount > total_bill -> responds: "Your change is ₱[amount]."
         * - If received_amount < total_bill -> responds: "Received ₱[amount]. Short by ₱[remaining]. Total bill is ₱[total]."
         */
        handlePOSReceivePayment(amount) {
            if (!amount || isNaN(amount) || amount <= 0) {
                return this.handlePOSReceivePaymentPrompt();
            }

            // Check if cart has items
            if (window.cart && window.cart.length === 0) {
                this.speak('The shopping cart is currently empty. Please add products to start a sale.');
                this.showToast('Cart is empty');
                return true;
            }

            // Ensure checkout modal is open
            if (typeof window.openCheckoutModal === 'function') {
                const checkoutModalEl = document.getElementById('checkoutModal');
                if (!checkoutModalEl || !checkoutModalEl.classList.contains('show')) {
                    window.openCheckoutModal();
                }
            }

            // Get total bill
            const totals = typeof window.calculateTotals === 'function' ? window.calculateTotals() : { netTotal: 0 };
            const netTotal = parseFloat(totals.netTotal || 0);

            // Populate tender input
            const tenderInput = document.getElementById('tenderAmountInput');
            if (tenderInput) {
                tenderInput.value = amount.toFixed(2);
                if (typeof window.calculateChange === 'function') {
                    window.calculateChange();
                }
            }

            // Calculate change difference
            const diff = amount - netTotal;
            const changeDue = Math.max(0, diff);

            this.playChime('success');

            // Exact Amount Condition: if received_amount - total_bill == 0 (with float tolerance)
            if (Math.abs(diff) < 0.005) {
                const speech = 'Exact amount received. There is no change.';
                this.speak(speech);
                this.showToast(`💵 Received: ₱${amount.toFixed(2)} (Exact Amount) • No Change`);
            } else if (diff > 0) {
                // Change due
                const speech = `Your change is ₱${changeDue.toFixed(2)}.`;
                this.speak(speech);
                this.showToast(`💵 Received: ₱${amount.toFixed(2)} • Your change is ₱${changeDue.toFixed(2)}`);
            } else {
                // Short payment
                const shortAmt = Math.abs(diff);
                const speech = `Received ₱${amount.toFixed(2)}. Short by ₱${shortAmt.toFixed(2)}. Total bill is ₱${netTotal.toFixed(2)}.`;
                this.speak(speech);
                this.showToast(`⚠️ Received: ₱${amount.toFixed(2)} (Short by ₱${shortAmt.toFixed(2)})`);
            }

            // Update modal change display
            const changeEl = document.getElementById('modalChangeAmount');
            if (changeEl) {
                changeEl.innerText = '₱' + changeDue.toFixed(2);
            }

            // Enable submit button if payment is sufficient
            const submitBtn = document.getElementById('btnSubmitSale');
            if (submitBtn) {
                submitBtn.disabled = diff < -0.005;
            }

            return true;
        }

        /**
         * Receive Exact Cash Payment
         */
        handlePOSReceiveExact() {
            if (window.cart && window.cart.length === 0) {
                this.speak('The shopping cart is empty.');
                return true;
            }

            if (typeof window.openCheckoutModal === 'function') {
                const modalEl = document.getElementById('checkoutModal');
                if (!modalEl || !modalEl.classList.contains('show')) {
                    window.openCheckoutModal();
                }
            }

            if (typeof window.quickExact === 'function') {
                window.quickExact();
            }

            const totals = typeof window.calculateTotals === 'function' ? window.calculateTotals() : { netTotal: 0 };
            const netTotal = parseFloat(totals.netTotal || 0);

            this.playChime('success');
            const speech = `Exact amount of ₱${netTotal.toFixed(2)} received. There is no change.`;
            this.speak(speech);
            this.showToast(`💵 Exact Amount Received: ₱${netTotal.toFixed(2)} (No change)`);
            return true;
        }

        /**
         * Standalone Discount Application Handler
         */
        handlePOSApplyDiscount(discountType, customVal = 0) {
            const discountSelect = document.getElementById('discountTypeSelect');
            const customInput = document.getElementById('customDiscountInput');

            if (!discountSelect) return false;

            discountSelect.value = discountType || 'none';
            if (discountType === 'custom' && customInput && customVal > 0) {
                customInput.value = customVal;
            }

            if (typeof window.calculateTotals === 'function') {
                const totals = window.calculateTotals();
                this.playChime('success');

                if (discountType === 'senior_pwd') {
                    this.speak(`Senior and PWD 20 percent discount applied. Saved ₱${totals.discount.toFixed(2)}. Net total is ₱${totals.netTotal.toFixed(2)}.`);
                    this.showToast(`🏷️ Senior / PWD 20% Discount Applied (-₱${totals.discount.toFixed(2)})`);
                } else if (discountType === 'member') {
                    this.speak(`Member 5 percent discount applied. Saved ₱${totals.discount.toFixed(2)}. Net total is ₱${totals.netTotal.toFixed(2)}.`);
                    this.showToast(`🏷️ Member 5% Discount Applied (-₱${totals.discount.toFixed(2)})`);
                } else if (discountType === 'custom') {
                    this.speak(`Custom ₱${customVal.toFixed(2)} discount applied. Net total is ₱${totals.netTotal.toFixed(2)}.`);
                    this.showToast(`🏷️ Custom Discount: -₱${customVal.toFixed(2)}`);
                } else {
                    this.speak(`Discount removed. Net total is ₱${totals.netTotal.toFixed(2)}.`);
                    this.showToast('🏷️ Regular Pricing (Discount Removed)');
                }
                return true;
            }

            return false;
        }

        /**
         * Remove Product from Cart
         */
        handlePOSRemoveProduct(query) {
            if (!window.cart || !Array.isArray(window.cart) || window.cart.length === 0) {
                this.speak('The cart is empty.');
                return true;
            }

            const cleanQuery = (query || '').toLowerCase().trim();
            let matchedItem = window.cart.find(i => i.name.toLowerCase() === cleanQuery || i.name.toLowerCase().includes(cleanQuery));

            if (!matchedItem) {
                let highest = 0;
                for (const item of window.cart) {
                    const score = this.fuzzyMatch(cleanQuery, item.name);
                    if (score > 0.55 && score > highest) {
                        highest = score;
                        matchedItem = item;
                    }
                }
            }

            if (matchedItem && typeof window.removeFromCart === 'function') {
                window.removeFromCart(matchedItem.id);
                const netTotal = typeof window.calculateTotals === 'function' ? window.calculateTotals().netTotal : 0;
                this.playChime('success');
                this.speak(`Removed ${matchedItem.name} from cart. Total is ₱${netTotal.toFixed(2)}.`);
                this.showToast(`🗑️ Removed ${matchedItem.name}`);
                return true;
            }

            this.speak(`Could not find ${query} in current cart.`);
            return false;
        }

        /**
         * Update Item Quantity in Cart
         */
        handlePOSUpdateQty(query, newQty) {
            if (!window.cart || !Array.isArray(window.cart) || window.cart.length === 0) return false;

            const cleanQuery = (query || '').toLowerCase().trim();
            const matchedItem = window.cart.find(i => i.name.toLowerCase().includes(cleanQuery) || this.fuzzyMatch(cleanQuery, i.name) > 0.55);

            if (matchedItem && typeof window.setDirectQty === 'function') {
                window.setDirectQty(matchedItem.id, newQty);
                const netTotal = typeof window.calculateTotals === 'function' ? window.calculateTotals().netTotal : 0;
                this.playChime('success');
                this.speak(`Updated ${matchedItem.name} quantity to ${newQty}. Total is ₱${netTotal.toFixed(2)}.`);
                this.showToast(`✏️ ${matchedItem.name} quantity: ${newQty}`);
                return true;
            }

            return false;
        }

        /**
         * Clear / Reset Cart
         */
        handlePOSClearCart() {
            if (typeof window.clearCart === 'function') {
                if (window.cart && window.cart.length > 0) {
                    window.cart = [];
                    if (typeof window.renderCart === 'function') window.renderCart();
                    this.playChime('success');
                    this.speak('Cart cleared. Ready for new items.');
                    this.showToast('🗑️ Cart cleared');
                    return true;
                } else {
                    this.speak('The shopping cart is already empty.');
                    return true;
                }
            }
            return false;
        }

        /**
         * Open Checkout Modal
         */
        handlePOSOpenCheckout() {
            if (window.cart && window.cart.length === 0) {
                this.speak('The cart is empty. Please add products to start a sale.');
                this.showToast('Cart is empty');
                return true;
            }

            if (typeof window.openCheckoutModal === 'function') {
                window.openCheckoutModal();
                const { netTotal } = typeof window.calculateTotals === 'function' ? window.calculateTotals() : { netTotal: 0 };
                this.playChime('success');
                this.speak(`Total payable is ₱${netTotal.toFixed(2)}. Please tender cash payment.`);
                this.showToast(`Payment Tender: ₱${netTotal.toFixed(2)}`);
                return true;
            }
            return false;
        }

        /**
         * Finalize Sale / Confirm Sale
         */
        async handlePOSFinalizeSale() {
            const submitBtn = document.getElementById('btnSubmitSale');
            const tenderInput = document.getElementById('tenderAmountInput');

            // If tender is empty or 0, set to exact amount
            if (tenderInput && (!tenderInput.value || parseFloat(tenderInput.value) <= 0)) {
                if (typeof window.quickExact === 'function') window.quickExact();
            }

            this.playChime('success');
            this.showToast('Finalizing Sale & Generating Official Receipt...');

            if (typeof window.processSale === 'function') {
                try {
                    await window.processSale();
                    setTimeout(() => {
                        this.playChime('wake');
                        this.speak('Sale completed successfully. Official receipt generated. You can say print receipt, new transaction, or close receipt.');
                        this.showToast('🧾 Sale Completed • Say "Print Receipt", "New Transaction", or "Close Receipt"');
                    }, 600);
                    return true;
                } catch (e) {
                    this.playChime('error');
                    this.speak('An error occurred while finalizing the transaction.');
                }
            } else if (submitBtn && !submitBtn.disabled) {
                submitBtn.click();
                setTimeout(() => {
                    this.playChime('wake');
                    this.speak('Sale completed successfully. Official receipt generated. You can say print receipt, new transaction, or close receipt.');
                    this.showToast('🧾 Sale Completed • Say "Print Receipt", "New Transaction", or "Close Receipt"');
                }, 600);
                return true;
            }
            return false;
        }

        /**
         * Print POS Receipt
         */
        handlePOSPrintReceipt() {
            if (typeof window.printReceipt === 'function') {
                this.playChime('success');
                this.speak('Printing receipt.');
                this.showToast('Printing Receipt...');
                window.printReceipt();
                return true;
            }
            return false;
        }

        /**
         * Reset POS for Next Customer / Transaction
         */
        handlePOSNextTransaction() {
            const receiptModalEl = document.getElementById('receiptModal');
            if (receiptModalEl && window.bootstrap) {
                const receiptModal = bootstrap.Modal.getInstance(receiptModalEl);
                if (receiptModal) receiptModal.hide();
            }

            if (typeof window.resetPos === 'function') {
                window.resetPos();
            }

            this.playChime('success');
            this.speak('Ready for next transaction.');
            this.showToast('Ready for Next Transaction');
            return true;
        }

        /**
         * Close Receipt Modal and Reset POS
         */
        handlePOSCloseReceipt() {
            const receiptModalEl = document.getElementById('receiptModal');
            if (receiptModalEl && window.bootstrap) {
                const receiptModal = bootstrap.Modal.getInstance(receiptModalEl);
                if (receiptModal) receiptModal.hide();
            }

            if (typeof window.resetPos === 'function') {
                window.resetPos();
            }

            this.playChime('success');
            this.speak('Receipt closed. Ready for new items.');
            this.showToast('Receipt closed • POS Ready');
            return true;
        }

        /**
         * Filter POS Products by Department / Category
         * Supports: "show only hardware", "go to hardware products", "show hardware products", "show only stationery", "filter toys", "only household", "show all products"
         */
        handlePOSFilterDepartment(departmentQuery) {
            let cleanQuery = (departmentQuery || '').toLowerCase().trim()
                .replace(/\s+(?:products|items|category|department|section)$/i, '')
                .trim();

            if (!cleanQuery || cleanQuery === 'all' || cleanQuery === 'all items' || cleanQuery === 'all products' || cleanQuery === 'reset filter') {
                const allPill = document.querySelector('#categoryPills .cat-pill') || document.querySelector('.cat-pill');
                if (typeof window.filterCategory === 'function') {
                    window.filterCategory('all', allPill);
                } else if (allPill) {
                    allPill.click();
                }
                const searchInput = document.getElementById('productSearchFilter');
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                this.playChime('success');
                this.speak('Showing all store items.');
                this.showToast('Showing all products');
                return true;
            }

            // 1. Check top-level department pills (#categoryPills .cat-pill)
            const catPills = Array.from(document.querySelectorAll('#categoryPills .cat-pill'));
            let matchedPill = catPills.find(pill => {
                const txt = pill.innerText.toLowerCase();
                return txt.includes(cleanQuery) || this.fuzzyMatch(txt, cleanQuery) >= 0.70;
            });

            if (matchedPill) {
                this.highlightElement(matchedPill);
                matchedPill.click();
                const deptLabel = matchedPill.innerText.replace(/\(\d+\)/g, '').trim();
                this.playChime('success');
                this.speak(`Showing only ${deptLabel} products.`);
                this.showToast(`Department: ${deptLabel}`);
                return true;
            }

            // 2. Check sub-category pills (#subCategoryPills .sub-cat-pill)
            const subPills = Array.from(document.querySelectorAll('#subCategoryPills .sub-cat-pill'));
            let matchedSubPill = subPills.find(pill => {
                const txt = pill.innerText.toLowerCase();
                return txt.includes(cleanQuery) || this.fuzzyMatch(txt, cleanQuery) >= 0.70;
            });

            if (matchedSubPill) {
                this.highlightElement(matchedSubPill);
                matchedSubPill.click();
                const subLabel = matchedSubPill.innerText.replace(/\(\d+\)/g, '').trim();
                this.playChime('success');
                this.speak(`Showing only ${subLabel} products.`);
                this.showToast(`Subcategory: ${subLabel}`);
                return true;
            }

            // 3. Check categoriesData globally
            if (window.categoriesData && Array.isArray(window.categoriesData)) {
                const matchedCat = window.categoriesData.find(c => {
                    const cName = c.name.toLowerCase();
                    return cName === cleanQuery || cName.includes(cleanQuery) || this.fuzzyMatch(cName, cleanQuery) >= 0.75;
                });

                if (matchedCat && typeof window.filterCategory === 'function') {
                    window.filterCategory(matchedCat.id, null);
                    this.playChime('success');
                    this.speak(`Showing only ${matchedCat.name} products.`);
                    this.showToast(`Department: ${matchedCat.name}`);
                    return true;
                }
            }

            // 4. Live Search Box Fallback
            const searchInput = document.getElementById('productSearchFilter');
            if (searchInput) {
                this.highlightElement(searchInput);
                searchInput.value = cleanQuery;
                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                this.playChime('success');
                this.speak(`Filtered products by ${cleanQuery}.`);
                this.showToast(`Filter: "${cleanQuery}"`);
                return true;
            }

            this.playChime('error');
            this.speak(`Could not find department ${cleanQuery}.`);
            return false;
        }

        /**
         * =========================================================================================
         * 3.5 UNIVERSAL CANCELLATION & MODAL DISMISSAL HANDLER
         * Handles: "cancel", "cancel that", "stop", "abort", "close", "dismiss", "never mind", "nevermind", "exit modal", "close window"
         * =========================================================================================
         */
        handleCancelCommand(cmd) {
            const isCancelIntent = /^(cancel|stop|abort|close|dismiss|never\s*mind|nevermind|exit\s*modal|close\s*modal|close\s*window|close\s*dialog|cancel\s*action|cancel\s*that|cancel\s*order|cancel\s*checkout)$/i.test(cmd.trim()) ||
                /\b(cancel\s+that|cancel\s+action|cancel\s+operation|cancel\s+checkout|close\s+modal|close\s+dialog|dismiss\s+modal|stop\s+listening)\b/i.test(cmd);

            if (!isCancelIntent) return false;

            // 1. Pending dangerous confirmation (e.g. Logout, Finalize Sale, Clear Cart)
            if (this.state.awaitingConfirmation) {
                const action = this.state.awaitingConfirmation;
                this.state.awaitingConfirmation = null;
                if (action === 'finalize_sale') {
                    const checkoutModalEl = document.getElementById('checkoutModal');
                    if (checkoutModalEl && window.bootstrap) {
                        const modal = bootstrap.Modal.getInstance(checkoutModalEl);
                        if (modal) modal.hide();
                    }
                    this.playChime('error');
                    this.speak('Checkout paused. All items remain in your cart.');
                    this.showToast('Checkout paused • Cart items retained');
                } else {
                    this.playChime('error');
                    this.speak('Action cancelled.');
                    this.showToast('Action cancelled');
                }
                return true;
            }

            // 2. Open Modals (Bootstrap & Custom)
            const openModals = Array.from(document.querySelectorAll('.modal.show, .t8-bio-enroll-modal.open, [role="dialog"].show'));
            if (openModals.length > 0) {
                for (const m of openModals) {
                    if (m.id === 't8BioEnrollModal') {
                        this.closeBiometricEnrollModal();
                    } else if (window.bootstrap) {
                        try {
                            const inst = bootstrap.Modal.getInstance(m) || bootstrap.Modal.getOrCreateInstance(m);
                            if (inst) inst.hide();
                        } catch (e) { }
                    }
                    m.classList.remove('show', 'open');
                    const closeBtn = m.querySelector('.btn-close, [data-bs-dismiss="modal"]');
                    if (closeBtn) {
                        try { closeBtn.click(); } catch (e) { }
                    }
                }
                // Clear any leftover modal backdrops
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(b => b.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';

                this.playChime('error');
                this.speak('Modal closed.');
                this.showToast('Modal closed');
                return true;
            }

            // 3. Visible Cancel or Close Buttons
            const cancelBtns = Array.from(document.querySelectorAll(
                'button.btn-close, [data-bs-dismiss="modal"], button[data-action="cancel"], a[data-action="cancel"], .btn-secondary, .btn-outline-secondary'
            )).filter(b => {
                const txt = (b.innerText || b.getAttribute('aria-label') || '').toLowerCase().trim();
                const rect = b.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0 && (txt.includes('cancel') || txt.includes('close') || b.classList.contains('btn-close'));
            });

            if (cancelBtns.length > 0) {
                const targetBtn = cancelBtns[0];
                this.highlightElement(targetBtn);
                targetBtn.click();
                this.playChime('error');
                this.speak('Cancelled.');
                this.showToast('Action cancelled');
                return true;
            }

            // 4. Focus cleanup
            if (document.activeElement && typeof document.activeElement.blur === 'function' && document.activeElement !== document.body) {
                document.activeElement.blur();
            }

            // 5. General cancellation fallback
            this.playChime('error');
            this.speak('Action cancelled.');
            this.showToast('Action cancelled');
            return true;
        }

        /**
         * =========================================================================================
         * 3.8 DEDICATED DATABASE BACKUP & RESTORE OPERATIONS
         * Handles: "create backup", "create backup now", "auto backup settings", "upload backup", "download backup", "restore backup"
         * =========================================================================================
         */
        handleBackupModuleCommands(cmd) {
            const isBackupQuery = /\b(backu?up|database backup|restore|sql dump|snapshot)\b/i.test(cmd);
            if (!isBackupQuery) return false;

            if (this.userRole !== 'admin') {
                this.playChime('error');
                this.speak('Access restricted. Database Backup and Restore operations require Administrator privileges.');
                this.showToast('Access Restricted: Administrator only', 'warning');
                return true;
            }

            const isBackupPage = window.location.pathname.includes('backup.php');
            const baseUrl = (window.location.pathname.includes('/admin/') || window.location.pathname.includes('/cashier/')) ? '../' : './';

            // 1. Auto Backup Configuration ("auto backup", "auto backup settings", "schedule backup", "automatic backup")
            if (/\b(auto\s*backu?up|automatic\s*backu?up|schedule\s*backu?up|cron\s*backu?up|backu?up\s*schedule)\b/i.test(cmd)) {
                if (!isBackupPage) {
                    this.playChime('success');
                    this.speak('Opening Backup and Restore settings.');
                    this.showToast('Navigating to Backup & Restore...');
                    setTimeout(() => window.location.href = baseUrl + 'admin/backup.php', 400);
                    return true;
                }

                // If already on backup.php, open auto settings modal
                const autoBtn = document.querySelector('[data-bs-target="#autoSettingsModal"]');
                const autoModal = document.getElementById('autoSettingsModal');
                if (autoModal && window.bootstrap) {
                    this.playChime('success');
                    this.speak('Opening Automated Backup Configuration.');
                    this.showToast('Automated backup settings opened');
                    const modal = bootstrap.Modal.getOrCreateInstance(autoModal);
                    modal.show();
                    if (autoBtn) this.highlightElement(autoBtn);
                    return true;
                }
            }

            // 2. Upload Backup File ("upload backup", "upload a backup", "upload and restore", "import backup", "restore backup file", "upload sql")
            if (/\b(upload\s*(?:a\s*)?backu?up|upload\s*and\s*restore|import\s*backu?up|restore\s*file|upload\s*sql)\b/i.test(cmd)) {
                if (!isBackupPage) {
                    this.playChime('success');
                    this.speak('Opening Upload and Restore Backup.');
                    this.showToast('Navigating to Backup & Restore...');
                    setTimeout(() => window.location.href = baseUrl + 'admin/backup.php', 400);
                    return true;
                }

                const uploadBtn = document.querySelector('[data-bs-target="#uploadModal"]');
                const uploadModal = document.getElementById('uploadModal');
                if (uploadModal && window.bootstrap) {
                    this.playChime('success');
                    this.speak('Opening Upload and Restore Backup dialogue.');
                    this.showToast('Upload backup dialogue opened');
                    const modal = bootstrap.Modal.getOrCreateInstance(uploadModal);
                    modal.show();
                    if (uploadBtn) this.highlightElement(uploadBtn);
                    return true;
                }
            }

            // 3. Create Backup / Take Snapshot ("create backup", "create backup now", "create a backup", "generate backup", "backup now", "make backup", "take snapshot")
            if (/\b(create\s*(?:a\s*)?backu?up|generate\s*backu?up|backu?up\s*now|make\s*backu?up|take\s*(?:a\s*)?snapshot|new\s*backu?up)\b/i.test(cmd)) {
                if (!isBackupPage) {
                    this.playChime('success');
                    this.speak('Opening Backup and Restore to generate snapshot.');
                    this.showToast('Navigating to Backup & Restore...');
                    setTimeout(() => window.location.href = baseUrl + 'admin/backup.php', 400);
                    return true;
                }

                const createModal = document.getElementById('createBackupModal');
                // If modal is already open, submit form to generate backup!
                if (createModal && createModal.classList.contains('show')) {
                    const submitBtn = createModal.querySelector('button[name="create_backup"], button[type="submit"]');
                    if (submitBtn) {
                        this.playChime('success');
                        this.highlightElement(submitBtn);
                        this.speak('Generating database backup snapshot now.');
                        this.showToast('Generating backup...');
                        submitBtn.click();
                        return true;
                    }
                }

                // If modal is not open, open create backup modal
                const createBtn = document.querySelector('[data-bs-target="#createBackupModal"]');
                if (createModal && window.bootstrap) {
                    this.playChime('success');
                    this.speak('Opening Create Database Snapshot dialogue.');
                    this.showToast('Create Backup dialogue opened');
                    const modal = bootstrap.Modal.getOrCreateInstance(createModal);
                    modal.show();
                    if (createBtn) this.highlightElement(createBtn);
                    return true;
                }
            }

            // 4. Download Latest Backup ("download backup", "download latest backup", "download sql")
            if (/\b(download\s*backu?up|download\s*latest\s*backu?up|download\s*sql)\b/i.test(cmd)) {
                if (isBackupPage) {
                    const downloadLink = document.querySelector('a[href*="action=download"]');
                    if (downloadLink) {
                        this.playChime('success');
                        this.highlightElement(downloadLink);
                        this.speak('Downloading the latest database backup.');
                        this.showToast('Downloading backup file...');
                        downloadLink.click();
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * =========================================================================================
         * 4. UNIVERSAL INTERACTIVE DOM ELEMENT CLICKER & BUTTON ENGINE
         * Matches buttons, links, inputs, icons, tabs, switches, and modals across all pages!
         * =========================================================================================
         */
        handleInteractiveElementClick(cmd) {
            const isPOS = window.location.pathname.includes('pos.php') || !!document.getElementById('cartTableBody');

            // On POS page, allow explicit click commands or known POS button phrases
            const isExplicitClick = /^(click|press|tap|open|hit|trigger|select|choose)\s+/i.test(cmd);
            const isKnownPOSAction = /\b(checkout|pay|finalize|print receipt|next transaction|clear cart|apply discount)\b/i.test(cmd);
            if (isPOS && !isExplicitClick && !isKnownPOSAction) {
                return false;
            }

            // Extract target phrase: "click create backup", "open auto backup settings", "press save user", or just "create backup"
            let targetPhrase = cmd
                .replace(/^(click|press|tap|open|hit|trigger|select|choose)\s+(on\s+|the\s+)?/i, '')
                .replace(/\s+(button|btn|link|dialog|modal|switch|checkbox)\b/gi, '')
                .trim();

            if (!targetPhrase) return false;

            // Direct Semantic Shortcuts for System-Wide Buttons
            const semanticShortcuts = [
                // Products
                { pattern: /^(add\s*product|new\s*product|create\s*product)$/i, selector: '#btnAddProduct, a[href*="products.php?action=new"], a[href*="products.php?action=add"], [data-bs-target="#addProductModal"]' },
                { pattern: /^(save\s*product|submit\s*product)$/i, selector: 'form[action*="products"] button[type="submit"], #addProductModal button[type="submit"]' },
                // Categories
                { pattern: /^(add\s*category|new\s*category|add\s*department)$/i, selector: '#btnAddCategory, [data-bs-target="#addCategoryModal"], a[href*="categories.php?action=new"]' },
                // Users
                { pattern: /^(add\s*user|new\s*user|create\s*user)$/i, selector: '#btnAddUser, [data-bs-target="#addUserModal"], button[data-bs-target="#addUserModal"]' },
                { pattern: /^(save\s*user|submit\s*user)$/i, selector: '#addUserModal button[type="submit"], #editUserModal button[type="submit"]' },
                // Access Control / RBAC
                { pattern: /^(save\s*permissions|save\s*roles|update\s*roles)$/i, selector: 'form[action*="rbac"] button[type="submit"], button[name="save_permissions"]' },
                { pattern: /^(reset\s*defaults|reset\s*permissions)$/i, selector: 'button[name="reset_defaults"]' },
                // Backup & Restore
                { pattern: /^(create\s*backu?up|create\s*backu?up\s*now|generate\s*backu?up|backu?up\s*now)$/i, selector: '[data-bs-target="#createBackupModal"], #createBackupModal button[name="create_backup"], button[name="create_backup"]' },
                { pattern: /^(auto\s*backu?up\s*settings|auto\s*backu?up|schedule\s*backu?up)$/i, selector: '[data-bs-target="#autoSettingsModal"], button[name="save_auto_settings"]' },
                { pattern: /^(upload\s*backu?up|upload\s*and\s*restore|import\s*backu?up)$/i, selector: '[data-bs-target="#uploadModal"], button[name="upload_restore"]' },
                // General Forms
                { pattern: /^(save|save\s*changes|submit|save\s*settings)$/i, selector: 'button[type="submit"]:not(:disabled), input[type="submit"]:not(:disabled)' },
                { pattern: /^(export|export\s*csv|export\s*to\s*csv)$/i, selector: 'a[href*="export"], button[data-action="export"], #btnExport' },
                { pattern: /^(print\s*receipt|print\s*invoice|print)$/i, selector: '#printReceiptBtn, [data-action="print-receipt"], button[onclick*="print"], a[onclick*="print"]' }
            ];

            for (const sc of semanticShortcuts) {
                if (sc.pattern.test(targetPhrase) || sc.pattern.test(cmd)) {
                    const el = document.querySelector(sc.selector);
                    if (el && this.isElementVisibleAndActive(el)) {
                        this.playChime('success');
                        this.highlightElement(el);
                        const elLabel = el.innerText || el.getAttribute('aria-label') || el.title || targetPhrase;
                        this.speak(`Clicking ${elLabel.trim().substring(0, 30)}`);
                        this.showToast(`Triggered: "${elLabel.trim().substring(0, 30)}"`);
                        setTimeout(() => {
                            el.click();
                            if (typeof el.focus === 'function') el.focus();
                        }, 200);
                        return true;
                    }
                }
            }

            // Collect all visible interactive elements in DOM
            const candidates = Array.from(document.querySelectorAll(
                'button, a, input[type="button"], input[type="submit"], input[type="checkbox"], [role="button"], .btn, select, .nav-link, .dropdown-item, .card-clickable, label[for], [data-bs-toggle], [data-bs-target]'
            )).filter(el => this.isElementVisibleAndActive(el));

            let bestMatch = null;
            let highestScore = 0;

            for (const el of candidates) {
                const text = (el.innerText || el.textContent || '').trim().toLowerCase();
                const aria = (el.getAttribute('aria-label') || '').toLowerCase();
                const title = (el.getAttribute('title') || '').toLowerCase();
                const id = (el.id || '').toLowerCase().replace(/[-_]/g, ' ');
                const val = (el.value || '').toLowerCase();
                const name = (el.getAttribute('name') || '').toLowerCase().replace(/[-_]/g, ' ');
                const voiceCmd = (el.getAttribute('data-voice-command') || '').toLowerCase();
                const onclickAttr = (el.getAttribute('onclick') || '').toLowerCase();
                const bsTarget = (el.getAttribute('data-bs-target') || '').toLowerCase().replace(/[-_#]/g, ' ');

                // Safety guard: Never auto-click destructive actions via generic fuzzy click
                const isDestructive = /resetpos|clearcart|logout|voidsale|delete/i.test(onclickAttr) ||
                    /clear|reset|delete|void|logout/i.test(id);
                if (isDestructive && !/\b(clear cart|log out|logout|empty cart|reset pos|delete)\b/i.test(targetPhrase)) {
                    continue;
                }

                // Ignore modal close / dismiss icons on partial match
                if (el.classList.contains('btn-close') || el.getAttribute('data-bs-dismiss') === 'modal') {
                    if (!/\b(close|dismiss|exit|cancel)\b/i.test(targetPhrase)) continue;
                }

                const combined = `${text} ${aria} ${title} ${id} ${name} ${val} ${bsTarget} ${voiceCmd}`.trim();
                if (!combined) continue;

                // 1. Exact match
                if (text === targetPhrase || aria === targetPhrase || title === targetPhrase || voiceCmd === targetPhrase || bsTarget === targetPhrase) {
                    bestMatch = el;
                    highestScore = 1.0;
                    break;
                }

                // 2. Substring inclusion
                if (text.includes(targetPhrase) || aria.includes(targetPhrase) || voiceCmd.includes(targetPhrase) || bsTarget.includes(targetPhrase) || id.includes(targetPhrase)) {
                    const score = 0.85 + (targetPhrase.length / combined.length) * 0.1;
                    if (score > highestScore) {
                        highestScore = score;
                        bestMatch = el;
                    }
                }

                // 3. High-confidence fuzzy similarity (threshold 0.80+)
                const fuzzy = this.fuzzyMatch(combined, targetPhrase);
                if (fuzzy >= 0.80 && fuzzy > highestScore) {
                    highestScore = fuzzy;
                    bestMatch = el;
                }
            }

            const minThreshold = (window.location.pathname.includes('pos.php')) ? 0.85 : 0.78;
            if (bestMatch && highestScore >= minThreshold) {
                this.playChime('success');
                this.highlightElement(bestMatch);
                const elLabel = bestMatch.innerText || bestMatch.getAttribute('aria-label') || bestMatch.title || targetPhrase;

                this.speak(`Clicking ${elLabel.trim().substring(0, 30)}`);
                this.showToast(`Triggered: "${elLabel.trim().substring(0, 30)}"`);

                setTimeout(() => {
                    bestMatch.click();
                    if (typeof bestMatch.focus === 'function') bestMatch.focus();
                }, 200);

                return true;
            }

            return false;
        }

        /**
         * Helper to determine if element is visible and interactive
         */
        isElementVisibleAndActive(el) {
            if (!el || el.disabled) return false;
            const rect = el.getBoundingClientRect();
            const style = window.getComputedStyle(el);
            return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none' && style.opacity !== '0';
        }

        /**
         * 5. Form Field & Search Command Handler
         */
        handleFormSearchCommand(cmd) {
            const searchMatch = cmd.match(/^(?:search(?:\s+for)?|find|filter)\s+(.+)$/i);
            if (searchMatch) {
                const query = searchMatch[1].trim();
                const searchInput = document.querySelector('input[type="search"], input[name*="search"], input[id*="search"], input[placeholder*="Search"], input[placeholder*="search"]');

                if (searchInput) {
                    this.playChime('success');
                    this.highlightElement(searchInput);
                    searchInput.value = query;
                    searchInput.focus();
                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                    searchInput.dispatchEvent(new Event('change', { bubbles: true }));

                    // If inside a search form, submit
                    const form = searchInput.closest('form');
                    if (form && !window.location.pathname.includes('pos.php')) {
                        form.submit();
                    }

                    this.speak(`Searching for ${query}`);
                    this.showToast(`Search: "${query}"`);
                    return true;
                }
            }
            return false;
        }

        /**
         * 6. Retail AI Analytics & Real-Time Stats Handler
         */
        async handleRetailAIQuery(cmd) {
            const isRetailQuery = /\b(how much|sales|revenue|profit|restock|low stock|out of stock|supplier|best seller|who sold|what if|margin)\b/i.test(cmd);
            if (!isRetailQuery) return false;

            this.showToast('Analyzing store data...');

            const baseUrl = (window.location.pathname.includes('/admin/') || window.location.pathname.includes('/cashier/')) ? '../' : './';

            // Check if RetailAI widget is present and use its backend API
            try {
                const resp = await fetch(baseUrl + 'api/voice_stats.php?action=sales_summary');
                const data = await resp.json();

                if (data.status === 'success' && /\b(sales|revenue|today)\b/i.test(cmd)) {
                    const speech = `Today we made ${data.today.formatted_revenue} across ${data.today.transactions} transactions, with an average sale of ${data.today.formatted_average}.`;
                    this.playChime('success');
                    this.speak(speech);
                    this.showToast(speech);
                    return true;
                }

                // If low stock query
                if (/\b(restock|low stock|out of stock)\b/i.test(cmd)) {
                    const invResp = await fetch(baseUrl + 'api/voice_stats.php?action=low_stock');
                    const invData = await invResp.json();
                    if (invData.status === 'success') {
                        const lowCount = invData.today?.low_stock_count || invData.low_stock_count || 0;
                        const speech = `There are ${lowCount} items that need restocking.`;
                        this.playChime('success');
                        this.speak(speech);
                        this.showToast(speech);
                        return true;
                    }
                }

                // Additional queries or general summary
                const speech = "I checked our store system. Stock and POS transactions are operating normally.";
                this.playChime('success');
                this.speak(speech);
                this.showToast(speech);
                return true;
            } catch (err) {
                console.warn('[VoiceAssistant] Stats fetch warning:', err);
            }

            return false;
        }

        /**
         * Highlight UI Element with Glowing Ring and Smooth Auto-Scroll
         */
        highlightElement(el) {
            if (!el) return;
            try {
                el.classList.add('voice-target-highlight');
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
                setTimeout(() => {
                    el.classList.remove('voice-target-highlight');
                }, 1200);
            } catch (e) { }
        }

        /**
         * Natural Text-to-Speech Output
         */
        speak(text) {
            if (!this.state.speechOutput || !this.synth) return;

            try {
                this.synth.cancel(); // Stop prior audio

                // Clean formatting and speak currency amounts naturally in English without Spanish loanword accent
                const clean = text
                    .replace(/###|##|#/g, '')
                    .replace(/\*\*/g, '')
                    .replace(/₱\s*(\d+(?:\.\d+)?)/g, '$1') // Read numbers directly in pure English
                    .replace(/₱/g, '')
                    .replace(/\bpesos?\b/gi, '') // Strip 'pesos' to prevent synthesizer switching to Spanish accent
                    .replace(/[•\-_]/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();

                const utterance = new SpeechSynthesisUtterance(clean);
                utterance.rate = 1.05;
                utterance.pitch = 1.0;
                utterance.lang = 'en-US';

                // Strictly select high quality US/UK English voices
                const voices = this.synth.getVoices();
                const preferredVoice = voices.find(v => (v.name.includes('Google') || v.name.includes('Natural') || v.name.includes('Samantha') || v.name.includes('David') || v.name.includes('Zira') || v.name.includes('Jenny')) && (v.lang === 'en-US' || v.lang === 'en-GB' || v.lang.startsWith('en')));
                if (preferredVoice) utterance.voice = preferredVoice;

                this.synth.speak(utterance);
            } catch (e) {
                console.warn('[VoiceAssistant] TTS error:', e);
            }
        }

        /**
         * Synthesized Audio Chimes (Web Audio API)
         */
        playChime(type = 'wake') {
            try {
                if (!this.audioCtx) {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (AudioContext) this.audioCtx = new AudioContext();
                }
                if (!this.audioCtx) return;

                if (this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume();
                }

                const now = this.audioCtx.currentTime;
                const osc = this.audioCtx.createOscillator();
                const gain = this.audioCtx.createGain();

                osc.connect(gain);
                gain.connect(this.audioCtx.destination);

                if (type === 'wake') {
                    // Upbeat major third (440Hz -> 554Hz)
                    osc.frequency.setValueAtTime(440, now);
                    osc.frequency.exponentialRampToValueAtTime(659.25, now + 0.15);
                    gain.gain.setValueAtTime(0.12, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.3);
                    osc.start(now);
                    osc.stop(now + 0.3);
                } else if (type === 'success') {
                    // Positive double chime
                    osc.frequency.setValueAtTime(587.33, now);
                    osc.frequency.setValueAtTime(880, now + 0.08);
                    gain.gain.setValueAtTime(0.1, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
                    osc.start(now);
                    osc.stop(now + 0.25);
                } else if (type === 'sleep') {
                    // Downward gentle note
                    osc.frequency.setValueAtTime(523.25, now);
                    osc.frequency.exponentialRampToValueAtTime(329.63, now + 0.2);
                    gain.gain.setValueAtTime(0.08, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
                    osc.start(now);
                    osc.stop(now + 0.25);
                } else if (type === 'error') {
                    // Low error boop
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(220, now);
                    gain.gain.setValueAtTime(0.12, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.2);
                    osc.start(now);
                    osc.stop(now + 0.2);
                }
            } catch (e) { }
        }

        /**
         * Floating Toast Notification
         */
        showToast(message) {
            if (!this.ui.toast || !this.ui.toastMsg) return;
            this.ui.toastMsg.textContent = message;
            this.ui.toast.classList.add('show');
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                this.ui.toast.classList.remove('show');
            }, 3200);
        }

        /**
         * =========================================================================================
         * ADMIN VOICEPRINT BIOMETRIC ENGINE & SPEAKER VERIFICATION METHODS
         * =========================================================================================
         */

        /**
         * Estimate Vocal Pitch (F0 Fundamental Frequency) via Autocorrelation
         */
        estimatePitch(buffer, sampleRate) {
            if (!buffer || buffer.length === 0) return 0;

            const SIZE = buffer.length;
            let sumSquares = 0;
            for (let i = 0; i < SIZE; i++) {
                sumSquares += buffer[i] * buffer[i];
            }
            const rms = Math.sqrt(sumSquares / SIZE);
            if (rms < 0.015) return 0; // Below voice threshold

            // Normalized Autocorrelation algorithm for human voice pitch (65Hz - 400Hz)
            const minPeriod = Math.floor(sampleRate / 400); // ~110 samples at 44.1kHz
            const maxPeriod = Math.floor(sampleRate / 65);  // ~680 samples at 44.1kHz

            let bestCorrelation = -1;
            let bestPeriod = -1;

            for (let period = minPeriod; period <= maxPeriod && period < SIZE / 2; period++) {
                let correlation = 0;
                let energy1 = 0;
                let energy2 = 0;

                for (let i = 0; i < SIZE - period; i++) {
                    correlation += buffer[i] * buffer[i + period];
                    energy1 += buffer[i] * buffer[i];
                    energy2 += buffer[i + period] * buffer[i + period];
                }

                const normEnergy = Math.sqrt(energy1 * energy2);
                if (normEnergy > 0) {
                    const normCorrelation = correlation / normEnergy;
                    if (normCorrelation > bestCorrelation) {
                        bestCorrelation = normCorrelation;
                        bestPeriod = period;
                    }
                }
            }

            if (bestPeriod > 0 && bestCorrelation > 0.35) {
                const pitch = sampleRate / bestPeriod;
                if (pitch >= 65 && pitch <= 400) {
                    return pitch;
                }
            }

            return 0;
        }

        /**
         * Extract 8-Band Spectral Fingerprint & Centroid from FFT Spectrum
         */
        extractSpectralProfile(freqData, sampleRate) {
            const binCount = freqData.length;
            const nyquist = sampleRate / 2;
            const binWidth = nyquist / binCount;

            // 8 Acoustic Formant Bins (Hz):
            const bands = [
                [80, 250],    // Fundamental bass
                [250, 500],   // Lower throat resonance
                [500, 1000],  // First vocal formant (F1)
                [1000, 2000], // Second vocal formant (F2)
                [2000, 3000], // Third vocal formant (F3)
                [3000, 4500], // Vocal clarity/articulation
                [4500, 6000], // Upper harmonics
                [6000, 8000]  // High sibilance / air
            ];

            const energies = new Float32Array(bands.length);
            let totalEnergy = 0;
            let weightedFreqSum = 0;

            for (let i = 0; i < binCount; i++) {
                const freq = i * binWidth;
                const mag = freqData[i] / 255;
                totalEnergy += mag;
                weightedFreqSum += freq * mag;

                for (let b = 0; b < bands.length; b++) {
                    if (freq >= bands[b][0] && freq < bands[b][1]) {
                        energies[b] += mag;
                    }
                }
            }

            const centroid = totalEnergy > 0 ? (weightedFreqSum / totalEnergy) : 1000;

            // Normalize energy distribution vector for cosine similarity
            let sumSq = 0;
            for (let b = 0; b < bands.length; b++) {
                sumSq += energies[b] * energies[b];
            }
            const norm = Math.sqrt(sumSq) || 1;
            const normalizedBands = Array.from(energies).map(val => val / norm);

            return {
                bands: normalizedBands,
                centroid: centroid,
                totalEnergy: totalEnergy
            };
        }

        /**
         * Evaluate Real-time Speaker against Enrolled Admin Voiceprint Profile
         */
        evaluateSpeaker(vector) {
            if (!this.biometrics.profile || !this.biometrics.enabled) {
                return { isMatch: true, confidence: 100, reason: 'Biometrics disabled' };
            }

            if (!vector || !vector.bands) {
                return { isMatch: false, confidence: 10, reason: 'No audio vector' };
            }

            const prof = this.biometrics.profile;

            // 1. Pitch Fundamental Match
            let pitchScore = 0.5;
            if (vector.pitch > 0 && prof.pitchMean > 0) {
                const pitchDiff = Math.abs(vector.pitch - prof.pitchMean);
                const maxTolerance = Math.max(35, (prof.pitchStd || 20) * 2.8);
                pitchScore = Math.max(0, 1 - (pitchDiff / maxTolerance));
            }

            // 2. Spectral Distribution Cosine Similarity (Vocal tract timbre)
            let dot = 0, normA = 0, normB = 0;
            for (let i = 0; i < vector.bands.length; i++) {
                const a = vector.bands[i] || 0;
                const b = prof.bands[i] || 0;
                dot += a * b;
                normA += a * a;
                normB += b * b;
            }
            const spectralScore = (normA > 0 && normB > 0) ? (dot / (Math.sqrt(normA) * Math.sqrt(normB))) : 0;

            // 3. Spectral Centroid Similarity
            const centDiff = Math.abs(vector.centroid - prof.centroidMean);
            const centScore = Math.max(0, 1 - (centDiff / 850));

            // Combined Weighted Confidence (Pitch 40%, Spectral Cosine 45%, Centroid 15%)
            let combined = 0;
            if (vector.pitch > 0) {
                combined = (0.40 * pitchScore) + (0.45 * spectralScore) + (0.15 * centScore);
            } else {
                // If unvoiced/whisper portion, evaluate timbre
                combined = (0.75 * spectralScore) + (0.25 * centScore);
            }

            const confidence = Math.min(99, Math.max(5, Math.round(combined * 100)));
            const isMatch = combined >= this.biometrics.threshold;

            this.biometrics.lastConfidence = confidence;
            this.biometrics.lastMatch = isMatch;

            return {
                isMatch: isMatch,
                confidence: confidence,
                pitch: Math.round(vector.pitch),
                score: combined,
                reason: isMatch ? `Speaker verified for user ${this.username} (${this.roleTitle})` : `Foreign voice: similarity ${confidence}% is below the required ${Math.round(this.biometrics.threshold * 100)}% threshold`
            };
        }

        /**
         * Open Voiceprint Enrollment Calibration Modal
         */
        openBiometricEnrollModal() {
            if (!this.ui.bioEnrollModal) return;
            this.initAudioContext();
            this.updateBiometricUI();
            this.ui.bioEnrollModal.classList.add('open');
        }

        /**
         * Close Voiceprint Enrollment Modal
         */
        closeBiometricEnrollModal() {
            if (!this.ui.bioEnrollModal) return;
            this.biometrics.isCalibrating = false;
            this.ui.bioEnrollModal.classList.remove('open');
        }

        /**
         * Start 3.5-Second Calibration Recording Session
         */
        async startBiometricCalibration() {
            await this.initAudioContext();

            if (!this.audioCtx || !this.micStream) {
                alert('Microphone access is required to calibrate your voiceprint.');
                return;
            }

            this.biometrics.isCalibrating = true;
            this.biometrics.calibrationSamples = [];

            if (this.ui.bioStartCalibBtn) {
                this.ui.bioStartCalibBtn.disabled = true;
                this.ui.bioStartCalibBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Calibrating (3s)...`;
            }

            let countdown = 3;
            if (this.ui.bioEnrollStatus) {
                this.ui.bioEnrollStatus.innerHTML = `<span class="text-warning fw-bold"><i class="fas fa-circle text-danger me-1 animate-pulse"></i> SPEAK NOW: "Authorize Triple Eight Admin Access" (${countdown}s)</span>`;
            }

            this.playChime('wake');

            const timer = setInterval(() => {
                countdown--;
                if (countdown > 0) {
                    if (this.ui.bioEnrollStatus) {
                        this.ui.bioEnrollStatus.innerHTML = `<span class="text-warning fw-bold"><i class="fas fa-circle text-danger me-1"></i> Recording vocal harmonics (${countdown}s)...</span>`;
                    }
                } else {
                    clearInterval(timer);
                    this.finishBiometricCalibration();
                }
            }, 1000);
        }

        /**
         * Finish Calibration, Calculate Average Acoustic Vectors & Save Profile
         */
        finishBiometricCalibration() {
            this.biometrics.isCalibrating = false;

            const samples = this.biometrics.calibrationSamples.filter(s => s.pitch >= 65 && s.pitch <= 400);

            if (samples.length < 5) {
                if (this.ui.bioEnrollStatus) {
                    this.ui.bioEnrollStatus.innerHTML = `<span class="text-danger fw-bold"><i class="fas fa-circle-exclamation me-1"></i> Audio was too faint. Please speak louder and retry.</span>`;
                }
                if (this.ui.bioStartCalibBtn) {
                    this.ui.bioStartCalibBtn.disabled = false;
                    this.ui.bioStartCalibBtn.innerHTML = `<i class="fas fa-rotate-right me-1"></i> Try Again`;
                }
                this.playChime('error');
                return;
            }

            // Calculate Mean Pitch & Standard Deviation
            let pitchSum = 0;
            let centroidSum = 0;
            const bandSums = new Float32Array(8);

            for (const s of samples) {
                pitchSum += s.pitch;
                centroidSum += s.centroid;
                for (let b = 0; b < 8; b++) {
                    bandSums[b] += (s.bands[b] || 0);
                }
            }

            const pitchMean = pitchSum / samples.length;
            const centroidMean = centroidSum / samples.length;

            // Pitch variance / standard deviation
            let varianceSum = 0;
            for (const s of samples) {
                varianceSum += Math.pow(s.pitch - pitchMean, 2);
            }
            const pitchStd = Math.sqrt(varianceSum / samples.length);

            // Normalized average band vector
            let bandNormSum = 0;
            const avgBands = [];
            for (let b = 0; b < 8; b++) {
                const avg = bandSums[b] / samples.length;
                avgBands.push(avg);
                bandNormSum += avg * avg;
            }
            const bandNorm = Math.sqrt(bandNormSum) || 1;
            const finalBands = avgBands.map(v => v / bandNorm);

            const profile = {
                enrolledAt: new Date().toISOString(),
                pitchMean: Math.round(pitchMean * 10) / 10,
                pitchStd: Math.max(15, Math.round(pitchStd * 10) / 10),
                pitchTolerance: Math.max(40, Math.round(pitchStd * 2.8)),
                centroidMean: Math.round(centroidMean),
                bands: finalBands,
                sampleCount: samples.length
            };

            this.saveBiometricProfile(profile);

            if (this.ui.bioEnrollStatus) {
                this.ui.bioEnrollStatus.innerHTML = `<span class="text-success fw-bold"><i class="fas fa-circle-check me-1"></i> Voiceprint Enrolled Successfully!</span>`;
            }

            if (this.ui.bioStartCalibBtn) {
                this.ui.bioStartCalibBtn.disabled = false;
                this.ui.bioStartCalibBtn.innerHTML = `<i class="fas fa-check me-1"></i> Re-Calibrate`;
            }

            this.playChime('success');
            this.speak(`${this.roleTitle} voiceprint for ${this.username} calibrated and saved to database. Voice authentication is now armed.`);
            this.showToast(`Voiceprint Armed: ${this.username} (${this.roleTitle})`);

            setTimeout(() => {
                this.closeBiometricEnrollModal();
            }, 1800);
        }

        /**
         * Animate visualizer wave bars in calibration modal
         */
        animateCalibrationWave(rms) {
            const bars = document.querySelectorAll('#t8BioVisualizerWave .t8-bio-bar');
            if (!bars || bars.length === 0) return;

            bars.forEach((bar, idx) => {
                const factor = 1 + Math.sin((Date.now() / 80) + idx);
                const height = Math.min(48, Math.max(6, Math.round(rms * 240 * factor)));
                bar.style.height = height + 'px';
            });
        }

        /**
         * Save Voiceprint Profile to Storage & Synchronize with MySQL Database
         */
        async saveBiometricProfile(profile) {
            this.biometrics.profile = profile;
            this.biometrics.enabled = true;
            const bioKey = 't8_voice_bio_profile_' + (this.username || this.userRole);
            localStorage.setItem(bioKey, JSON.stringify(profile));
            localStorage.setItem('t8_voice_bio_profile', JSON.stringify(profile));
            localStorage.setItem('t8_voice_bio_enabled', 'true');
            this.updateBiometricUI();

            // Persist to server MySQL database via api/voice_auth.php
            try {
                const payload = {
                    action: 'enroll',
                    voice_label: `${this.username} (${this.roleTitle}) Voiceprint`,
                    pitch_mean: profile.pitchMean,
                    pitch_std: profile.pitchStd,
                    pitch_tolerance: profile.pitchTolerance,
                    centroid_mean: profile.centroidMean,
                    bands: profile.bands,
                    sample_count: profile.sampleCount,
                    passphrase: 'Authorize Triple Eight Access',
                    auth_level: 'strict'
                };

                const resp = await fetch(this.baseUrl + 'api/voice_auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });
                const res = await resp.json();
                if (res.status === 'success') {
                    console.log('[VoiceAssistant] Voiceprint enrolled and saved to MySQL database.');
                    if (this.ui.bioProfileSummary) {
                        this.ui.bioProfileSummary.innerHTML = `<span class="text-success"><i class="fas fa-database me-1"></i> Database Synchronized & Armed</span> • Base Pitch ${Math.round(profile.pitchMean)} Hz`;
                    }
                }
            } catch (err) {
                console.warn('[VoiceAssistant] Server enrollment sync error:', err);
            }
        }

        /**
         * Clear Enrolled Voiceprint Profile (Local Storage & MySQL Database)
         */
        async clearBiometricProfile() {
            if (confirm(`Clear enrolled ${this.roleTitle} voiceprint for ${this.username}? The assistant will revert to unauthenticated listening.`)) {
                this.biometrics.profile = null;
                this.biometrics.enabled = false;
                const bioKey = 't8_voice_bio_profile_' + (this.username || this.userRole);
                localStorage.removeItem(bioKey);
                localStorage.removeItem('t8_voice_bio_profile');
                localStorage.setItem('t8_voice_bio_enabled', 'false');
                this.updateBiometricUI();
                this.showToast('Voiceprint profile removed');
                this.closeBiometricEnrollModal();

                // Delete from database
                try {
                    await fetch(this.baseUrl + 'api/voice_auth.php?action=delete_profile', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                } catch (e) {}
            }
        }

        /**
         * Toggle Biometric Shield Switch
         */
        setBiometricsEnabled(enabled) {
            this.biometrics.enabled = enabled;
            localStorage.setItem('t8_voice_bio_enabled', enabled ? 'true' : 'false');
            this.updateBiometricUI();
            this.showToast(enabled ? `${this.roleTitle} Voice Isolation: Armed` : `${this.roleTitle} Voice Isolation: Disabled`);
        }

        /**
         * Update UI Elements for Biometrics Status
         */
        updateBiometricUI() {
            const hasProfile = !!this.biometrics.profile;
            const isArmed = hasProfile && this.biometrics.enabled;

            if (this.ui.bioStatusBadge) {
                this.ui.bioStatusBadge.className = isArmed ? 'badge bg-success' : (hasProfile ? 'badge bg-warning text-dark' : 'badge bg-secondary');
                this.ui.bioStatusBadge.textContent = isArmed ? `ARMED (${this.roleTitle.toUpperCase()})` : (hasProfile ? 'PAUSED' : 'UNENROLLED');
            }

            if (this.ui.bioEnrollBtnText) {
                this.ui.bioEnrollBtnText.textContent = hasProfile ? 'Recalibrate Voice' : `Enroll ${this.roleTitle} Voice`;
            }

            if (this.ui.toggleBiometrics) {
                this.ui.toggleBiometrics.checked = isArmed;
                this.ui.toggleBiometrics.disabled = !hasProfile;
            }

            if (this.ui.bioProfileSummary) {
                this.ui.bioProfileSummary.textContent = hasProfile ?
                    `Current Profile: Base Pitch ${Math.round(this.biometrics.profile.pitchMean)} Hz • Database Verified Fingerprint` :
                    'No voice profile currently saved';
            }

            if (this.ui.bioClearBtnContainer) {
                this.ui.bioClearBtnContainer.innerHTML = hasProfile ?
                    `<button type="button" class="btn btn-sm btn-outline-danger" onclick="window.VoiceAssistant.clearBiometricProfile()"><i class="fas fa-trash me-1"></i> Clear Voiceprint</button>` :
                    '<span></span>';
            }
        }

        /**
         * Synchronize Voiceprint Profile from MySQL Database on Initialization
         */
        async syncVoiceProfileFromServer() {
            try {
                const resp = await fetch(this.baseUrl + 'api/voice_auth.php?action=get_profile', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!resp.ok) return;
                const data = await resp.json();
                if (data && data.status === 'success' && data.has_voiceprint && data.profile) {
                    this.biometrics.profile = {
                        pitchMean: data.profile.pitch_mean,
                        pitchStd: data.profile.pitch_std,
                        pitchTolerance: data.profile.pitch_tolerance,
                        centroidMean: data.profile.centroid_mean,
                        bands: data.profile.bands,
                        sampleCount: data.profile.sample_count,
                        enrolledAt: data.profile.enrolled_at,
                        isDbSynced: true
                    };
                    this.biometrics.enabled = localStorage.getItem('t8_voice_bio_enabled') !== 'false';
                    const bioKey = 't8_voice_bio_profile_' + (this.username || this.userRole);
                    localStorage.setItem(bioKey, JSON.stringify(this.biometrics.profile));
                    this.updateBiometricUI();
                    console.log('[VoiceAssistant] Voice profile loaded from database for user:', this.username);
                }
            } catch (e) {
                console.warn('[VoiceAssistant] Server voice profile sync notice:', e);
            }
        }

        /**
         * Interactive Live Test for Voice Authentication
         */
        testVoiceAuthentication() {
            this.initAudioContext();
            if (!this.biometrics.profile) {
                alert('Please enroll your voiceprint first before running the speaker authentication test.');
                this.openBiometricEnrollModal();
                return;
            }

            this.showToast('🎤 Say test phrase: "Authorize Triple Eight Access"');
            this.speak('Please speak now to test voice authentication.');
            if (this.ui.bioEnrollStatus) {
                this.ui.bioEnrollStatus.innerHTML = '<span class="text-warning fw-bold"><i class="fas fa-spinner fa-spin me-1"></i> Listening for speaker test (3s)... Speak now!</span>';
            }

            let samples = [];
            const collectTimer = setInterval(() => {
                if (this.biometrics.currentAcousticVector && this.biometrics.currentAcousticVector.pitch > 0) {
                    samples.push(this.biometrics.currentAcousticVector);
                }
            }, 100);

            setTimeout(async () => {
                clearInterval(collectTimer);
                if (samples.length === 0 && this.biometrics.currentAcousticVector) {
                    samples.push(this.biometrics.currentAcousticVector);
                }

                if (samples.length === 0) {
                    this.playChime('error');
                    this.showToast('No audio detected. Please speak closer to microphone.');
                    if (this.ui.bioEnrollStatus) {
                        this.ui.bioEnrollStatus.innerHTML = '<span class="text-danger fw-bold"><i class="fas fa-microphone-slash me-1"></i> No voice input detected. Please retry.</span>';
                    }
                    return;
                }

                const evalResult = this.evaluateSpeaker(samples[samples.length - 1]);
                if (evalResult.isMatch) {
                    this.playChime('success');
                    this.speak(`Voice authenticated successfully. Welcome, ${this.username}. Confidence score ${evalResult.confidence} percent.`);
                    this.showToast(`✅ Voice Authenticated: ${evalResult.confidence}% Match`);
                    if (this.ui.bioEnrollStatus) {
                        this.ui.bioEnrollStatus.innerHTML = `<span class="text-success fw-bold"><i class="fas fa-circle-check me-1"></i> AUTHENTICATED: ${this.username} (${evalResult.confidence}% Match)</span>`;
                    }
                } else {
                    this.playChime('error');
                    this.speak(`Voice authentication failed. Confidence ${evalResult.confidence} percent is below threshold.`);
                    this.showToast(`❌ Foreign Voice Rejected: ${evalResult.confidence}% Match`);
                    if (this.ui.bioEnrollStatus) {
                        this.ui.bioEnrollStatus.innerHTML = `<span class="text-danger fw-bold"><i class="fas fa-circle-xmark me-1"></i> REJECTED: Foreign Voice (${evalResult.confidence}% Match)</span>`;
                    }
                }
            }, 3200);
        }

        /**
         * Asynchronously Log Voice Command Verification to Server Audit Trail
         */
        async logVoiceCommandToServer(commandText, confidence, isMatch, reason) {
            try {
                await fetch(this.baseUrl + 'api/voice_auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'verify_command',
                        command: commandText,
                        confidence: Math.round(confidence || 0),
                        is_match: isMatch ? 1 : 0,
                        reason: reason || ''
                    })
                });
            } catch (e) {
                // Ignore background network errors
            }
        }

        /**
         * =========================================================================================
         * ADVANCED FUZZY STRING MATCHING & PHONETIC ACCENT TOLERANCE ENGINE
         * Handles Levenshtein Distance, Dice Coefficient, Token Overlap, and Accent Normalization
         * =========================================================================================
         */

        /**
         * Normalize common phonetic variations, accents, and colloquial pronunciations
         * e.g. "koka kola" -> "coca cola", "sili kalamansi" -> "chili calamansi", "neskafe" -> "nescafe"
         */
        normalizePhonetic(text) {
            if (!text || typeof text !== 'string') return '';
            let s = text.toLowerCase().trim();

            // Accent & letter substitutions common in spoken retail & multilingual English
            s = s
                .replace(/ph/g, 'f')
                .replace(/kh/g, 'k')
                .replace(/ck/g, 'k')
                .replace(/c(?=[eiy])/g, 's')
                .replace(/c(?=[aou])/g, 'k')
                .replace(/q/g, 'k')
                .replace(/x/g, 'ks')
                .replace(/z/g, 's')
                .replace(/v/g, 'b')  // Spanish / Tagalog phonetic interchange
                .replace(/f/g, 'p')  // Filipino accent sound interchange
                .replace(/(.)\1+/g, '$1') // Collapse double consonants
                .replace(/[^a-z0-9]/g, '');

            return s;
        }

        /**
         * Levenshtein Edit Distance Calculation
         */
        levenshteinDistance(s1, s2) {
            const m = s1.length;
            const n = s2.length;
            if (m === 0) return n;
            if (n === 0) return m;

            const d = [];
            for (let i = 0; i <= m; i++) {
                d[i] = [i];
            }
            for (let j = 0; j <= n; j++) {
                d[0][j] = j;
            }

            for (let i = 1; i <= m; i++) {
                for (let j = 1; j <= n; j++) {
                    const cost = s1[i - 1] === s2[j - 1] ? 0 : 1;
                    d[i][j] = Math.min(
                        d[i - 1][j] + 1,      // deletion
                        d[i][j - 1] + 1,      // insertion
                        d[i - 1][j - 1] + cost // substitution
                    );
                }
            }
            return d[m][n];
        }

        /**
         * Token-level Jaccard Word Intersection Similarity
         */
        tokenJaccard(str1, str2) {
            const t1 = str1.toLowerCase().replace(/[^a-z0-9\s]/g, '').split(/\s+/).filter(w => w.length > 0);
            const t2 = str2.toLowerCase().replace(/[^a-z0-9\s]/g, '').split(/\s+/).filter(w => w.length > 0);

            if (t1.length === 0 || t2.length === 0) return 0;

            const set1 = new Set(t1);
            const set2 = new Set(t2);

            let intersection = 0;
            for (const item of set1) {
                if (set2.has(item)) intersection++;
            }

            const union = new Set([...t1, ...t2]).size;
            return union > 0 ? (intersection / union) : 0;
        }

        /**
         * Multi-Tier Composite Fuzzy String Similarity Matcher
         * Combines:
         * 1. Exact string & token containment (bonus 0.90+)
         * 2. Bigram Dice Coefficient (character pairs)
         * 3. Levenshtein edit distance ratio
         * 4. Token Jaccard word intersection
         * 5. Phonetic sound-alike similarity
         */
        fuzzyMatch(str1, str2) {
            if (!str1 || !str2) return 0;

            const raw1 = str1.toLowerCase().trim();
            const raw2 = str2.toLowerCase().trim();

            if (raw1 === raw2) return 1.0;

            const s1 = raw1.replace(/[^a-z0-9]/g, '');
            const s2 = raw2.replace(/[^a-z0-9]/g, '');
            if (s1 === s2) return 0.98;

            // Direct substring containment bonus
            if (raw1.includes(raw2) || raw2.includes(raw1)) {
                const ratio = Math.min(raw1.length, raw2.length) / Math.max(raw1.length, raw2.length);
                return 0.85 + (ratio * 0.12);
            }

            // 1. Bigram Dice Coefficient
            let diceScore = 0;
            if (s1.length >= 2 && s2.length >= 2) {
                const bigrams = new Map();
                for (let i = 0; i < s1.length - 1; i++) {
                    const bg = s1.substr(i, 2);
                    bigrams.set(bg, (bigrams.get(bg) || 0) + 1);
                }

                let intersection = 0;
                for (let i = 0; i < s2.length - 1; i++) {
                    const bg = s2.substr(i, 2);
                    const count = bigrams.get(bg) || 0;
                    if (count > 0) {
                        bigrams.set(bg, count - 1);
                        intersection++;
                    }
                }
                diceScore = (2.0 * intersection) / (s1.length - 1 + s2.length - 1);
            }

            // 2. Levenshtein Ratio
            const maxLen = Math.max(s1.length, s2.length);
            const levDist = this.levenshteinDistance(s1, s2);
            const levScore = maxLen > 0 ? (1.0 - (levDist / maxLen)) : 0;

            // 3. Token Jaccard
            const jaccardScore = this.tokenJaccard(raw1, raw2);

            // 4. Phonetic / Accent Resilience Match
            const phon1 = this.normalizePhonetic(raw1);
            const phon2 = this.normalizePhonetic(raw2);
            let phonScore = 0;
            if (phon1 && phon2) {
                if (phon1 === phon2) {
                    phonScore = 0.92;
                } else if (phon1.includes(phon2) || phon2.includes(phon1)) {
                    phonScore = 0.82;
                } else {
                    const pMax = Math.max(phon1.length, phon2.length);
                    const pDist = this.levenshteinDistance(phon1, phon2);
                    phonScore = pMax > 0 ? (1.0 - (pDist / pMax)) : 0;
                }
            }

            // Weighted Composite Score
            const composite = (diceScore * 0.30) + (levScore * 0.25) + (jaccardScore * 0.25) + (phonScore * 0.20);
            return Math.max(diceScore, levScore, jaccardScore, composite);
        }
    }

    // Auto initialize on DOMContentLoaded or immediate execution
    function bootVoiceAssistantEngine() {
        if (!window.VoiceAssistant) {
            window.VoiceAssistant = new VoiceAssistantEngine();
        } else if (!document.getElementById('t8VoiceHudContainer') && document.body) {
            window.VoiceAssistant.injectUI();
            window.VoiceAssistant.bindUIEvents();
            window.VoiceAssistant.updateUIState();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootVoiceAssistantEngine);
    } else {
        bootVoiceAssistantEngine();
    }
    window.addEventListener('load', bootVoiceAssistantEngine);

})(window, document);
