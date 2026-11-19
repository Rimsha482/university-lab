/**
 * Real-Time Exam Countdown Timer
 * Synchronizes client countdown with server duration and auto-submits upon expiry
 */

class ExamTimer {
    constructor(totalSeconds, onTimeUpCallback) {
        this.remainingSeconds = Math.max(0, parseInt(totalSeconds, 10));
        this.onTimeUp = onTimeUpCallback;
        this.timerBox = document.getElementById('timerBox');
        this.timerDisplay = document.getElementById('timerDisplay');
        this.interval = null;

        this.start();
    }

    start() {
        this.updateDisplay();
        this.interval = setInterval(() => {
            this.remainingSeconds--;
            this.updateDisplay();

            if (this.remainingSeconds <= 0) {
                clearInterval(this.interval);
                if (typeof this.onTimeUp === 'function') {
                    this.onTimeUp();
                }
            }
        }, 1000);
    }

    updateDisplay() {
        const hours = Math.floor(this.remainingSeconds / 3600);
        const minutes = Math.floor((this.remainingSeconds % 3600) / 60);
        const seconds = this.remainingSeconds % 60;

        const hh = String(hours).padStart(2, '0');
        const mm = String(minutes).padStart(2, '0');
        const ss = String(seconds).padStart(2, '0');

        const formatted = hours > 0 ? `${hh}:${mm}:${ss}` : `${mm}:${ss}`;
        if (this.timerDisplay) {
            this.timerDisplay.textContent = formatted;
        }

        if (this.timerBox) {
            if (this.remainingSeconds <= 60) {
                this.timerBox.classList.add('danger');
                this.timerBox.classList.remove('warning');
            } else if (this.remainingSeconds <= 300) {
                this.timerBox.classList.add('warning');
                this.timerBox.classList.remove('danger');
            } else {
                this.timerBox.classList.remove('warning', 'danger');
            }
        }
    }

    stop() {
        if (this.interval) clearInterval(this.interval);
    }
}
