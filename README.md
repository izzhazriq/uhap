# UiTM Health Appointment Portal (UHAP)

## 📖 Overview

The **UiTM Health Appointment Portal (UHAP)** is a web-based appointment management system developed to streamline the process of booking and managing health appointments within Universiti Teknologi MARA (UiTM).

The system allows students to schedule appointments with the university health center, enables staff to review and manage appointment requests, and provides administrators with user management capabilities.

---

## 🎯 Objectives

- Simplify the health appointment booking process for students.
- Improve appointment management efficiency for health center staff.
- Provide a centralized platform for managing appointments and staff accounts.
- Reduce manual paperwork and scheduling conflicts.

---

## 👥 User Roles

### 🎓 Student
Students can:

- Register and log in to the system.
- View their profile information.
- Create health appointment requests.
- View appointment history.
- Check appointment status updates.

### 🩺 Staff
Staff members can:

- Log in to the system.
- View all appointment requests.
- Review appointment details.
- Approve, reject, or update appointment statuses.
- Manage appointment schedules.

### ⚙️ Admin
Administrators can:

- Log in to the system.
- Add new staff accounts.
- Delete existing staff accounts.
- Manage staff access to the portal.

---

## 🚀 Features

- User authentication and authorization
- Role-based access control
- Appointment booking system
- Appointment status management
- Staff account management
- Secure database integration
- Responsive web interface

---

## 🛠️ Technologies Used

### Frontend
- HTML5
- CSS3
- JavaScript

### Backend
- PHP

### Database
- MySQL

### Development Tools
- XAMPP
- phpMyAdmin
- Visual Studio Code

---

## 🗄️ Database Structure

The system consists of several key entities:

- Students
- Staff
- Admin
- Appointments

### Appointment Status

Appointments may have the following statuses:

- Pending
- Approved
- Rejected
- Completed

---

## 📂 Project Structure

```text
UHAP/
│
├── admin/
├── staff/
├── student/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── database/
│   └── uhap.sql
│
├── includes/
├── config/
├── index.php
├── login.php
├── register.php
└── README.md
