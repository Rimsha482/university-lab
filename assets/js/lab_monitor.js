/**
 * Real-time Lab Monitor & Proctoring Streamer
 * AJAX polling for computer workstation statuses, live student attempts, and cheating alerts
 */

class LabMonitor {
    constructor(config) {
        this.endpointUrl = config.endpointUrl;
        this.examId = config.examId || null;
        this.pollInterval = config.pollInterval || 3000;
        this.containerId = config.containerId || 'liveProctorContainer';
        this.alertsListId = config.alertsListId || 'liveAlertsFeed';
        this.timer = null;
        this.lastLogId = 0;

        this.init();
    }

    init() {
        this.fetchUpdates();
        this.timer = setInterval(() => this.fetchUpdates(), this.pollInterval);
    }

    fetchUpdates() {
        const url = new URL(this.endpointUrl, window.location.origin);
        if (this.examId) {
            url.searchParams.append('exam_id', this.examId);
        }
        url.searchParams.append('last_log_id', this.lastLogId);

        fetch(url.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                this.renderAttempts(data.attempts);
                this.renderLogs(data.recent_logs);
                if (data.recent_logs && data.recent_logs.length > 0) {
                    this.lastLogId = data.recent_logs[0].id;
                }
                if (data.summary) {
                    this.updateSummaryStats(data.summary);
                }
            }
        })
        .catch(err => {
            console.warn('Proctor polling network lag:', err);
        });
    }

    renderAttempts(attempts) {
        const container = document.getElementById(this.containerId);
        if (!container || !attempts) return;

        if (attempts.length === 0) {
            container.innerHTML = `
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-person-x fs-1 d-block mb-2"></i>
                    <p class="mb-0">No active students are currently in this exam room.</p>
                </div>
            `;
            return;
        }

        let html = '';
        attempts.forEach(item => {
            const isDisqualified = item.status === 'disqualified';
            const cardBorder = isDisqualified ? 'border-danger' : (item.violation_count > 0 ? 'border-warning' : 'border-success');
            const statusBadge = isDisqualified ? '<span class="badge bg-danger">Disqualified</span>' : 
                                (item.status === 'completed' ? '<span class="badge bg-secondary">Submitted</span>' : '<span class="badge bg-success">Active In Exam</span>');
            const progressPct = item.total_questions > 0 ? Math.round((item.answered_questions / item.total_questions) * 100) : 0;

            html += `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100 shadow-sm ${cardBorder}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold mb-0 text-truncate" style="max-width: 180px;">${this.escapeHtml(item.full_name)}</h6>
                                    <small class="text-muted">${this.escapeHtml(item.roll_number || 'No Roll')} &bull; ${this.escapeHtml(item.pc_name || 'Virtual Workstation')}</small>
                                </div>
                                <div>${statusBadge}</div>
                            </div>

                            <div class="mt-3">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Questions Progress</span>
                                    <span>${item.answered_questions} / ${item.total_questions} (${progressPct}%)</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: ${progressPct}%"></div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                <span class="badge ${item.violation_count >= 3 ? 'bg-danger' : (item.violation_count > 0 ? 'bg-warning text-dark' : 'bg-light text-dark border')}">
                                    <i class="bi bi-shield-alert me-1"></i> ${item.violation_count} Violations
                                </span>
                                <small class="text-muted">Started: ${item.started_time_ago}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    renderLogs(logs) {
        const feed = document.getElementById(this.alertsListId);
        if (!feed || !logs || logs.length === 0) return;

        logs.forEach(log => {
            const existing = document.getElementById(`log-${log.id}`);
            if (existing) return;

            const li = document.createElement('li');
            li.id = `log-${log.id}`;
            li.className = 'list-group-item d-flex justify-content-between align-items-center px-3 py-2 border-start-0 border-end-0';
            
            const badgeClass = log.severity === 'high' ? 'bg-danger' : (log.severity === 'medium' ? 'bg-warning text-dark' : 'bg-secondary');

            li.innerHTML = `
                <div class="me-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge ${badgeClass} text-uppercase" style="font-size:0.7rem;">${this.escapeHtml(log.violation_type.replace('_', ' '))}</span>
                        <strong class="small">${this.escapeHtml(log.student_name)}</strong>
                        <span class="text-muted small">(${this.escapeHtml(log.roll_number || 'Student')})</span>
                    </div>
                    <div class="small text-secondary mt-1">${this.escapeHtml(log.violation_message)}</div>
                </div>
                <span class="text-muted small text-nowrap">${log.time_ago}</span>
            `;

            feed.insertBefore(li, feed.firstChild);
        });

        // Limit stream to 25 items
        while (feed.children.length > 25) {
            feed.removeChild(feed.lastChild);
        }
    }

    updateSummaryStats(summary) {
        const activeElem = document.getElementById('statActiveStudents');
        const alertElem = document.getElementById('statTotalViolations');
        const submittedElem = document.getElementById('statSubmittedCount');

        if (activeElem && summary.active_count !== undefined) activeElem.textContent = summary.active_count;
        if (alertElem && summary.violation_count !== undefined) alertElem.textContent = summary.violation_count;
        if (submittedElem && summary.submitted_count !== undefined) submittedElem.textContent = summary.submitted_count;
    }

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}
