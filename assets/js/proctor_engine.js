/**
 * Enterprise Proctoring Engine
 * Monitors user behavior, detects unauthorized navigation, tab switches, and enforces fullscreen integrity.
 */

class ProctorEngine {
    constructor(config) {
        this.attemptId = config.attemptId;
        this.examId = config.examId;
        this.studentId = config.studentId;
        this.maxViolations = config.maxViolations || 3;
        this.currentViolations = config.currentViolations || 0;
        this.apiUrl = config.apiUrl;
        this.submitUrl = config.submitUrl;
        this.isLocked = false;
        this.isFullscreen = false;
        this.isSubmitting = false;

        this.init();
    }

    init() {
        this.updateViolationUI();
        this.bindEvents();
        this.checkInitialFullscreen();
    }

    bindEvents() {
        const self = this;

        // 1. Detect Tab Switch / Window Minimize
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && !self.isSubmitting && !self.isLocked) {
                self.recordViolation('tab_switch', 'Student navigated away from exam tab or minimized browser window.', 'high');
            }
        });

        // 2. Window Blur (e.g. clicking external app or second monitor)
        window.addEventListener('blur', () => {
            if (!self.isSubmitting && !self.isLocked) {
                self.recordViolation('window_blur', 'Exam window lost focus (clicked on another window or monitor).', 'medium');
            }
        });

        // 3. Fullscreen Exit Detection
        document.addEventListener('fullscreenchange', () => self.onFullscreenChange());
        document.addEventListener('webkitfullscreenchange', () => self.onFullscreenChange());
        document.addEventListener('mozfullscreenchange', () => self.onFullscreenChange());
        document.addEventListener('MSFullscreenChange', () => self.onFullscreenChange());

        // 4. Block Right-Click Context Menu
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            self.showWarningToast('Right-click context menu is strictly disabled during proctored exams.');
            self.recordViolation('right_click', 'Student attempted to open context menu.', 'low');
            return false;
        });

        // 5. Block Copy, Cut, Paste
        ['copy', 'cut', 'paste'].forEach(eventType => {
            document.addEventListener(eventType, (e) => {
                e.preventDefault();
                self.showWarningToast(`Action '${eventType}' is prohibited during the examination.`);
                self.recordViolation('copy_paste_attempt', `Student attempted clipboard ${eventType} action.`, 'medium');
                return false;
            });
        });

        // 6. Block DevTools Shortcuts (F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U)
        document.addEventListener('keydown', (e) => {
            // F12
            if (e.key === 'F12' || e.keyCode === 123) {
                e.preventDefault();
                self.recordViolation('dev_tools', 'Student attempted to open developer tools via F12.', 'high');
                return false;
            }
            // Ctrl+Shift+I / Ctrl+Shift+J / Ctrl+Shift+C
            if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) {
                e.preventDefault();
                self.recordViolation('dev_tools', 'Student attempted to open developer inspection tools.', 'high');
                return false;
            }
            // Ctrl+U (View Source)
            if (e.ctrlKey && (e.key === 'U' || e.key === 'u')) {
                e.preventDefault();
                self.recordViolation('dev_tools', 'Student attempted to view page source code.', 'low');
                return false;
            }
        });
    }

    checkInitialFullscreen() {
        const isFull = !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement);
        this.isFullscreen = isFull;
        const overlay = document.getElementById('fullscreenOverlay');
        if (overlay) {
            overlay.style.display = isFull ? 'none' : 'flex';
        }
    }

    onFullscreenChange() {
        const isFull = !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement);
        this.isFullscreen = isFull;
        const overlay = document.getElementById('fullscreenOverlay');

        if (!isFull && !this.isSubmitting && !this.isLocked) {
            if (overlay) overlay.style.display = 'flex';
            this.recordViolation('fullscreen_exit', 'Student exited full-screen proctored environment.', 'high');
        } else if (isFull && overlay) {
            overlay.style.display = 'none';
        }
    }

    enterFullscreen() {
        const el = document.documentElement;
        const req = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        if (req) {
            req.call(el).then(() => {
                this.isFullscreen = true;
                const overlay = document.getElementById('fullscreenOverlay');
                if (overlay) overlay.style.display = 'none';
            }).catch(err => {
                alert('Fullscreen mode is required to proceed with this exam. Please enable fullscreen permissions in your browser.');
            });
        }
    }

    recordViolation(type, message, severity) {
        if (this.isLocked || this.isSubmitting) return;

        const self = this;
        const payload = new URLSearchParams({
            attempt_id: self.attemptId,
            exam_id: self.examId,
            student_id: self.studentId,
            violation_type: type,
            violation_message: message,
            severity: severity
        });

        fetch(self.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload.toString()
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                self.currentViolations = data.violation_count;
                self.updateViolationUI();
                self.showWarningToast(`Security Alert: ${message} (Violation ${self.currentViolations} of ${self.maxViolations})`);

                if (data.locked || self.currentViolations >= self.maxViolations) {
                    self.lockStudentOut();
                }
            }
        })
        .catch(err => {
            console.warn('Violation logging failed network reachability:', err);
        });
    }

    updateViolationUI() {
        const badge = document.getElementById('violationCountDisplay');
        const pill = document.getElementById('violationPill');
        if (badge) {
            badge.textContent = `${this.currentViolations} / ${this.maxViolations}`;
        }
        if (pill) {
            pill.className = 'violation-pill ' + (
                this.currentViolations === 0 ? 'safe' :
                this.currentViolations < this.maxViolations ? 'warning' : 'danger'
            );
        }
    }

    showWarningToast(msg) {
        let toastBox = document.getElementById('proctorAlertToast');
        if (!toastBox) {
            toastBox = document.createElement('div');
            toastBox.id = 'proctorAlertToast';
            toastBox.style.cssText = 'position:fixed;bottom:25px;right:25px;z-index:999999;max-width:380px;background:#ef4444;color:#fff;padding:14px 18px;border-radius:10px;box-shadow:0 10px 25px rgba(0,0,0,0.3);font-size:0.9rem;font-weight:600;display:flex;align-items:center;gap:12px;transition:all 0.3s;';
            document.body.appendChild(toastBox);
        }
        toastBox.innerHTML = `<i class="bi bi-shield-exclamation" style="font-size:1.5rem;"></i> <div>${msg}</div>`;
        toastBox.style.display = 'flex';
        toastBox.style.opacity = '1';

        clearTimeout(this.toastTimeout);
        this.toastTimeout = setTimeout(() => {
            toastBox.style.opacity = '0';
            setTimeout(() => { toastBox.style.display = 'none'; }, 300);
        }, 4000);
    }

    lockStudentOut() {
        this.isLocked = true;
        const lockoutOverlay = document.getElementById('lockoutOverlay');
        if (lockoutOverlay) {
            lockoutOverlay.style.display = 'flex';
        }

        // Force auto-submit as disqualified
        if (!this.isSubmitting) {
            this.isSubmitting = true;
            setTimeout(() => {
                const form = document.getElementById('examSubmitForm');
                if (form) {
                    const statusInput = document.getElementById('examFinalStatus');
                    if (statusInput) statusInput.value = 'disqualified';
                    form.submit();
                } else {
                    window.location.href = `${this.submitUrl}?attempt_id=${this.attemptId}&status=disqualified`;
                }
            }, 3000);
        }
    }
}
