# Smart University / Institute Lab & Exam Proctoring Portal

An enterprise-grade, offline-capable digital examination system designed specifically for University Computer Labs, Colleges, and Educational Institutes running on **XAMPP (Apache, MySQL, PHP 8.x)**.

---

## 🚀 Key Modules & Capabilities

1. **Anti-Cheat Proctoring Engine**:
   - **Fullscreen Integrity Lock**: Enforces full-screen mode before exam begins. Leaving fullscreen logs an immediate violation and triggers a security screen.
   - **Tab-Switch & Blur Detection**: Traps `visibilitychange` and `window.blur` events when a student attempts to switch browser tabs or minimize the test window.
   - **Clipboard & Key Blocking**: Completely disables right-click context menu, copy, cut, paste, and developer inspection hotkeys (`F12`, `Ctrl+Shift+I`, `Ctrl+U`).
   - **Disqualification Safeguard**: Automatically marks the student as `disqualified` and auto-submits their paper if violations exceed the limit (default: 3 violations).

2. **Lab Workstation Telemetry**:
   - **Heartbeat Ping**: Active exam stations ping `api/heartbeat.php` every 10 seconds.
   - **Live Lab Grid**: Incharge can see all workstations (Online: Green, In-Exam: Blue, Offline: Red) with student assignment.

3. **Auto-Grading & Result Processing**:
   - Instant MCQ score computation.
   - Student scorecard with section performance, pass/fail status, and proctoring audit log.
   - One-click Excel/CSV export for university examinations departments.

4. **Database & Backup Suite**:
   - One-click MySQL database dump generation (`backups/backup_db.php`).
   - Browser-based SQL restore utility (`backups/restore_db.php`).

---

## 🔑 Default Demonstration Accounts

All accounts are pre-configured with the default password: **`Password@123`**

| Role | Username | Email | Description |
|---|---|---|---|
| **Administrator** | `admin` | `admin@university.edu` | Full lab control, PC allocation, backups, logs |
| **Teacher / Examiner** | `teacher` | `teacher@university.edu` | Exam creator, question bank, live proctoring |
| **Student (Demo 1)** | `student` | `student@university.edu` | Roll No: `CS-2024-001` (CS-401 Midterm ready) |
| **Student (Demo 2)** | `jane.smith` | `jane@university.edu` | Roll No: `CS-2024-002` |

---

## 🛠️ Installation & Setup on XAMPP

1. Ensure **Apache** and **MySQL** are running in the XAMPP Control Panel.
2. The project is installed in `E:\xamp\htdocs\exam-portal\` (and synced to `E:\xamp\htdocs\university lab\`).
3. Open your browser and navigate to:
   ```
   http://localhost/exam-portal
   ```
   *(or `http://localhost/university%20lab/`)*
4. If database needs re-import, run `backups/schema.sql` into MySQL `exam_portal_db`.

---

## 📂 Project Architecture

```
├── index.php                 # Landing & Role-Based Login Gateway
├── logout.php                # Session termination & PC unbinding
├── config/                   # Constants, PDO connection, Mail setup
├── auth/                     # Session guard, RBAC, Password recovery
├── includes/                 # Header, Navbar, Sidebars, Footer
├── assets/                   # Bootstrap 5, Icons, Proctoring Engines
├── admin/                    # Dashboard, PC Monitor, Users, Logs
├── teacher/                  # Exams, Questions, Live Proctoring, Results
├── student/                  # Instructions, Fullscreen Exam, Scorecards
├── api/                      # Pure PHP AJAX endpoints (Heartbeat, Violations, Save)
└── backups/                  # Schema, One-click Backup & Restore
```
