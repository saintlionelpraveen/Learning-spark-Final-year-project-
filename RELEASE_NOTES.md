# 🚀 Release Notes — Learning Spark

---

## v1.0.0 — Production Release

**Release Date:** February 17, 2026  
**Tag:** `v1.0.0`  
**Branch:** `version-01`  
**Status:** ✅ Stable — Production Ready

---

### 🎯 Overview

**Learning Spark** is a full-stack, role-based Learning Management System (LMS) built with PHP and MySQL, featuring a premium **Liquid Glass UI** design system. This is the first official production release, encompassing all core platform features for administrators, staff (instructors), and learners.

---

### ✨ Features

#### 🔐 Authentication & Authorization
- Session-based authentication with role routing (Admin / Staff / User)
- New user registration with admin approval workflow
- Role assignment during approval (User or Staff)
- Pending approval state — prevents unauthorized access until approved
- Secure logout with confirmation for all roles

#### 👤 Admin Panel
- **User Management** — View, edit name/email/role, delete any user
- **Pending Registrations** — Review, assign roles, approve or reject signups
- **Platform Statistics** — Total users, staff count, pending registrations
- **Notifications** — Aggregated alerts for messages, ratings, and feedback activity
- **Admin Profile** — Editable profile with photo upload

#### 📚 Staff (Instructor) Portal
- **Content Upload** — Upload videos, PDFs, and blog posts with thumbnails
- **Content Management** — View, edit, and delete uploaded content
- **Dashboard Analytics** — Visual stats powered by Chart.js
- **Feedback Reader** — Review learner feedback
- **Messaging** — WhatsApp-style chat with users and admin messaging

#### 🎓 Learner Portal
- **Staff Directory** — Browse all staff with content counts and average ratings
- **Content Browser** — Filter by All, Videos, Documents, Blogs with tabs
- **Inline Preview** — Watch videos, view PDFs, and read blogs without leaving the page
- **5-Star Rating System** — Rate staff content with a star widget
- **Messaging** — Send messages to staff, view replies in chat bubbles
- **Feedback** — Submit feedback to staff members
- **User Profile** — Editable name, email, and profile photo with live preview

#### 🎨 Liquid Glass Design System
- Frosted-glass UI with `backdrop-filter` blur effects
- Vibrant gradient backgrounds with animated circles
- Smooth hover transitions and micro-animations
- Horizontal navigation with glassmorphism cards
- Responsive layout — desktop, tablet, and mobile
- Two design systems: `admin_styles.css` (1,500+ lines) and `portal_styles.css` (1,500+ lines)

---

### 🗄️ Database Schema

| Table | Purpose |
|-------|---------|
| `users` | All users with roles, approval status, profile photos |
| `staff_content` | Videos, PDFs, blogs uploaded by staff |
| `staff_blogs` | Staff blog posts |
| `staff_feedback` | User → Staff feedback entries |
| `staff_ratings` | User → Staff star ratings |
| `content_ratings` | User → Content star ratings |
| `blog_ratings` | User → Blog star ratings |
| `user_to_staff_messages` | User ↔ Staff messaging with replies |
| `staff_to_admin_messages` | Staff ↔ Admin messaging with replies |
| `user_preferences` | Theme and favorite staff settings |
| `user_ratings` | User-level ratings |
| `staff_stats` (VIEW) | Aggregated staff metrics |

- Full referential integrity with foreign keys and `ON DELETE CASCADE`
- Check constraints on all rating columns (1-5 range)
- `utf8mb4` character encoding throughout

---

### 🛠️ Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | PHP 7.4+ |
| **Database** | MySQL 5.7+ / MariaDB 10.4+ |
| **Server** | Apache (via XAMPP) |
| **Frontend** | HTML5, CSS3, Vanilla JavaScript |
| **Charts** | Chart.js |
| **Icons** | Font Awesome 6.5 |
| **Design** | Custom Liquid Glass CSS (glassmorphism) |

---

### 📦 File Manifest

```
Learning-spark-Final-year-project--main/
├── login.php                     # Auth — all roles
├── register.php                  # New user registration
├── logout.php                    # Quick logout
│
├── admin_dashboard.php           # Admin — user management & approvals
├── admin_profile.php             # Admin — profile management
├── admin_notifications.php       # Admin — notification center
├── admin_logout.php              # Admin — logout with confirmation
├── admin_styles.css              # Admin design system
│
├── staff_dashboard.php           # Staff — content & profile management
├── staff_messages.php            # Staff — messaging interface
├── staff_content.php             # Learner — view & rate staff content
├── update_content.php            # Staff — edit existing content
├── send_admin_message.php        # Staff — message admin
│
├── user_dashboard.php            # Learner — browse staff
├── user_messages.php             # Learner — message history
├── user_profile.php              # Learner — profile settings
│
├── portal_styles.css             # Shared staff/user design system
├── upload_content.php            # Backend — file upload handler
├── update_profile.php            # Backend — profile update handler
├── download_notes.php            # Backend — file download handler
├── get_feedback_count.php        # Backend — AJAX feedback counter
├── notifications.php             # Backend — notification handler
├── view_pdf.php                  # Backend — inline PDF viewer
│
├── learning (11).sql             # Database schema + seed data
├── Database/                     # Database backups
├── content/                      # Uploaded content files
├── profile_photos/               # User profile images
├── uploads/                      # Staff content uploads
│
├── USER_MANUAL.md                # Comprehensive user guide
└── RELEASE_NOTES.md              # This file
```

---

### 🏷️ Tags

| Tag | Description |
|-----|-------------|
| `v1.0.0` | First stable production release |
| `production` | Production-ready marker |
| `liquid-glass-ui` | Liquid Glass design system |
| `final-year-project` | Academic final year project |

---

### ⚙️ Deployment Requirements

| Component | Minimum |
|-----------|---------|
| **OS** | Windows 10/11 |
| **XAMPP** | v3.3.0+ (Apache + MySQL + PHP) |
| **PHP** | 7.4+ |
| **MySQL** | 5.7+ / MariaDB 10.4+ |
| **Browser** | Chrome, Firefox, Edge, or Safari (latest) |
| **Disk** | ~50 MB + upload storage |

---

### 📋 Quick Start

```bash
# 1. Place project in XAMPP htdocs
#    C:\xampp\htdocs\Learning-spark-Final-year-project--main\

# 2. Start Apache & MySQL in XAMPP Control Panel

# 3. Create database via phpMyAdmin
#    Database name: learning
#    Collation: utf8mb4_general_ci

# 4. Import schema
#    Import: learning (11).sql

# 5. Access the app
#    http://localhost/Learning-spark-Final-year-project--main/Learning-spark-Final-year-project--main/login.php
```

---

### ⚠️ Known Limitations

- Passwords are stored as plain text (not hashed) — **recommended** to implement `password_hash()` / `password_verify()` before public deployment
- No CSRF token protection on forms
- No rate limiting on login attempts
- File upload validation is basic — consider adding MIME type checks
- Single-server architecture (no horizontal scaling)

---

### 🔮 Roadmap (Post v1.0)

- [ ] Password hashing with `bcrypt` / `argon2`
- [ ] CSRF protection on all forms
- [ ] Email verification for registration
- [ ] Password reset via email
- [ ] Advanced search and content filtering
- [ ] Dark mode toggle
- [ ] REST API for mobile app integration
- [ ] Docker containerization

---

### 👥 Contributors

- **Praveen Raj D** — Project Lead & Full-Stack Developer

---

### 📄 License

This project is developed as part of a Final Year academic project.

---

> **Learning Spark v1.0.0** · Built with PHP, MySQL & Liquid Glass Design · February 2026
