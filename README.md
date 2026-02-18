# Learning Spark — User Manual

> **Version 1.0** · A modern staff–user learning management platform with liquid-glass UI

---

## Table of Contents

1. [Overview](#overview)
2. [Key Features](#key-features)
3. [System Requirements](#system-requirements)
4. [Installation & Setup](#installation--setup)
5. [Database Setup](#database-setup)
6. [Starting the Application](#starting-the-application)
7. [User Workflows](#user-workflows)
   - [Registration & Approval](#1-registration--approval)
   - [Admin Workflow](#2-admin-workflow)
   - [Staff Workflow](#3-staff-workflow)
   - [User (Learner) Workflow](#4-user-learner-workflow)
8. [File Structure](#file-structure)
9. [Troubleshooting](#troubleshooting)

---

## Overview

**Learning Spark** is a web-based learning management system that connects **staff** (content creators/instructors) with **users** (learners). An **admin** oversees the platform, approves registrations, and manages all users.

The platform features a modern **Liquid Glass UI** with frosted-glass effects, smooth animations, horizontal navigation, and WhatsApp-style messaging.

---

## Key Features

| Feature | Description |
|---------|-------------|
|  **Registration Approval** | All new users require admin approval before accessing the platform |
|  **Role Assignment** | Admin assigns roles (User or Staff) during approval |
|  **Content Management** | Staff can upload videos, PDFs, and blog posts |
|  **Rating System** | Users can rate staff content with a 5-star widget |
|  **Messaging System** | Users ↔ Staff and Staff ↔ Admin messaging with chat bubbles |
|  **Feedback System** | Users can send feedback to staff members |
|  **Notifications** | Admin receives real-time notification counts |
|  **Content Preview** | Inline video player, PDF viewer, and blog reader |
|  **Dashboard Analytics** | Charts and stats for staff activity |
|  **Premium UI** | Liquid glass design with animations and responsive layout |

---

## System Requirements

| Component | Requirement |
|-----------|-------------|
| **OS** | Windows 10/11 |
| **XAMPP** | v3.3.0 or later (includes Apache + MySQL + PHP) |
| **PHP** | 7.4 or higher (included in XAMPP) |
| **MySQL** | 5.7 or higher (included in XAMPP) |
| **Browser** | Chrome, Firefox, Edge, or Safari (latest) |
| **Disk Space** | ~50 MB for application + uploads |

---

## Installation & Setup

### Step 1: Install XAMPP

1. Download XAMPP from [https://www.apachefriends.org](https://www.apachefriends.org)
2. Install with default settings (ensure Apache and MySQL are selected)
3. Default install path: `C:\xampp`

### Step 2: Place the Project

Copy the entire project folder into the XAMPP web root:

```
C:\xampp\htdocs\Learning-spark-Final-year-project--main\Learning-spark-Final-year-project--main\
```

### Step 3: Start XAMPP Services

1. Open **XAMPP Control Panel**
2. Click **Start** next to **Apache** (should show ports 80, 443)
3. Click **Start** next to **MySQL** (should show port 3306)
4. Both should show green "Running" status

---

## Database Setup

### Step 1: Open phpMyAdmin

Navigate to: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)

### Step 2: Create the Database

1. Click **"New"** in the left sidebar
2. Enter database name: **`learning`**
3. Select collation: **`utf8mb4_general_ci`**
4. Click **Create**

### Step 3: Import the Schema

1. Select the **`learning`** database from the sidebar
2. Click the **"Import"** tab at the top
3. Click **"Choose File"** and navigate to:
   ```
   C:\xampp\htdocs\Learning-spark-Final-year-project--main\
   Learning-spark-Final-year-project--main\learning (11).sql
   ```
4. Click **"Go"** to import

### Step 4: Verify Tables

After import, you should see these tables:

| Table | Purpose |
|-------|---------|
| `users` | All users (admin, staff, users) with approval status |
| `staff_content` | Videos, PDFs, blogs uploaded by staff |
| `staff_feedback` | User feedback to staff |
| `staff_ratings` | Star ratings for staff content |
| `messages` | User ↔ Staff messages |
| `staff_to_admin_messages` | Staff → Admin messages |
| `notifications` | System notifications |

### Step 5: Default Admin Account

The imported database includes a default admin account:

| Field | Value |
|-------|-------|
| **Email** | *(check the `users` table for the admin row)* |
| **Password** | *(as set in the database)* |
| **Role** | `admin` |

> **Tip:** If no admin exists, manually insert one via phpMyAdmin:
> ```sql
> INSERT INTO users (name, email, password, role, pending_approval)
> VALUES ('Admin', 'admin@learningspark.com', MD5('admin123'), 'admin', 0);
> ```

---

## Starting the Application

1. Ensure **Apache** and **MySQL** are running in XAMPP
2. Open your browser and navigate to:

```
http://localhost/Learning-spark-Final-year-project--main/Learning-spark-Final-year-project--main/login.php
```

3. You will see the **Login** page with the liquid glass design

---

## User Workflows

### 1. Registration & Approval

```
┌─────────────┐     ┌──────────────┐     ┌──────────────────┐     ┌───────────────┐
│  User visits │     │  Fills form  │     │  Status: Pending │     │  Admin reviews │
│ register.php │────▶│  Name/Email/ │────▶│  Cannot log in   │────▶│  Assigns role  │
│              │     │  Password    │     │  until approved  │     │  & approves    │
└─────────────┘     └──────────────┘     └──────────────────┘     └───────────────┘
                                                                         │
                                                                         ▼
                                                                  ┌───────────────┐
                                                                  │  User can now  │
                                                                  │  log in as     │
                                                                  │  User or Staff │
                                                                  └───────────────┘
```

**Steps:**
1. Go to `register.php`
2. Enter name, email, and password
3. Click **Create Account** → You'll see "Registration submitted! Please wait for admin approval."
4. Until approved, login shows: "Your registration is pending admin approval."
5. Admin opens their dashboard → sees the pending registration → selects a role → clicks **Approve**

---

### 2. Admin Workflow

**Login:** Use admin credentials at `login.php`

**Dashboard** (`admin_dashboard.php`):

| Section | Actions |
|---------|---------|
| **Pending Registrations** | View new signups, assign role (User/Staff), Approve or Reject |
| **User Management** | View all users, edit name/email/role, delete users |
| **Statistics** | Total users, staff, pending counts |

**Other Admin Pages:**

| Page | Purpose |
|------|---------|
| `admin_profile.php` | View & edit admin profile and photo |
| `admin_notifications.php` | View messages from staff, ratings, feedback activity |
| `admin_logout.php` | Secure logout with confirmation |

---

### 3. Staff Workflow

**Login:** Use staff credentials at `login.php`

**Dashboard** (`staff_dashboard.php`):

| Section | Actions |
|---------|---------|
| **Stats** | Content count, messages, feedback, unread |
| **Profile** | Edit name, email, photo |
| **Upload Content** | Upload video/PDF/blog with title and thumbnail |
| **My Content** | View, edit, delete uploaded content |
| **Feedback** | Read user feedback |
| **Activity Chart** | Visual stats (Chart.js) |

**Messages** (`staff_messages.php`):

| Tab | Function |
|-----|----------|
| **User Messages** | Read messages from users, reply in WhatsApp-style chat |
| **Admin Messages** | Send messages to admin |

**Edit Content** (`update_content.php`):
- Change title, type, replace file/image, edit blog text

---

### 4. User (Learner) Workflow

**Login:** Use user credentials at `login.php`

**Dashboard** (`user_dashboard.php`):
- Browse all staff members in a card grid
- See each staff's content count and average rating
- Click a staff card to view their content

**View Staff Content** (`staff_content.php`):
- Browse content with tabs: All, Videos, Documents, Blogs
- Watch videos or view PDFs inline
- Rate content with 5-star widget
- Send messages to staff
- Submit feedback

**Messages** (`user_messages.php`):
- View all sent messages grouped by staff
- See staff replies in WhatsApp chat bubbles
- Track unread replies

**Profile** (`user_profile.php`):
- View profile details (name, email, join date)
- Edit name, email, and profile photo
- Client-side photo preview before saving

---

## File Structure

```
Learning-spark-Final-year-project--main/
│
├── admin_dashboard.php        # Admin: user management & approvals
├── admin_profile.php          # Admin: profile management
├── admin_notifications.php    # Admin: view all notifications
├── admin_logout.php           # Admin: logout page
├── admin_styles.css           # Admin design system
│
├── staff_dashboard.php        # Staff: content & profile management
├── staff_messages.php         # Staff: messaging interface
├── staff_content.php          # User: view staff content & rate
├── update_content.php         # Staff: edit existing content
├── send_admin_message.php     # Staff: send message to admin
│
├── user_dashboard.php         # User: browse staff
├── user_messages.php          # User: view sent messages & replies
├── user_profile.php           # User: profile management
│
├── login.php                  # Authentication (all roles)
├── register.php               # New user registration
├── logout.php                 # Quick logout
│
├── portal_styles.css          # Shared staff/user design system (1500+ lines)
│
├── upload_content.php         # Backend: handle content uploads
├── update_profile.php         # Backend: handle profile updates
├── download_notes.php         # Backend: file download handler
├── get_feedback_count.php     # Backend: AJAX feedback counter
├── notifications.php          # Backend: notification handler
├── view_pdf.php               # Backend: PDF viewer
│
├── learning (11).sql          # Database schema + sample data
├── Database/                  # Database backups
├── content/                   # Uploaded content files
├── profile_photos/            # User profile images
├── uploads/                   # Staff content uploads
│
└── USER_MANUAL.md             # This file
```

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| **phpMyAdmin: Access denied for 'root'** | Open `C:\xampp\phpMyAdmin\config.inc.php` and set `$cfg['Servers'][$i]['host'] = 'localhost';` |
| **404 Not Found (nginx)** | WSL nginx is hijacking port 80. Run `sudo service nginx stop` inside WSL |
| **Apache won't start** | Check if another app uses port 80 (Skype, IIS). Change Apache port in XAMPP Config |
| **MySQL won't start** | Check if port 3306 is in use. Stop any other MySQL service |
| **Login: "pending approval"** | Ask admin to approve in Admin Dashboard → Pending Registrations |
| **Upload fails** | Ensure `uploads/` and `content/` folders exist and are writable |
| **Blank page** | Check PHP errors in `C:\xampp\apache\logs\error.log` |
| **Feedback error: unknown column** | The column is `feedback_text` (not `feedback`) in `staff_feedback` table |

---

## Stopping the Application

1. Open **XAMPP Control Panel**
2. Click **Stop** next to **Apache**
3. Click **Stop** next to **MySQL**
4. Close the control panel

---

> **Learning Spark** · Built with PHP, MySQL, and modern CSS · Liquid Glass Design System
