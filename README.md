# Groupe IKI – Academic Management System

A web-based platform that streamlines academic operations for the Groupe IKI training centre.
It supports three roles – **Admins**, **Teachers** and **Students** – and unifies activities like grade entry, attendance tracking, user management and internal messaging.

---

## 1  Features

| Area | Admin | Teacher | Student |
|------|:----:|:------:|:------:|
| Dashboard with KPIs | ✓ | ✓ | ✓ |
| Manage filières, classes & modules | ✓ |  |  |
| Teacher ↔ module assignment | ✓ |  |  |
| User CRUD (admin/teacher/student) | ✓ |  |  |
| Enter / update grades |  | ✓ |  |
| View grades | ✓ | ✓ | ✓ |
| Record attendance |  | ✓ |  |
| View attendance | ✓ | ✓ | ✓ |
| Internal messages / announcements | ✓ | ✓ | ✓ |
| Responsive Bootstrap 5 UI | ✓ | ✓ | ✓ |

---

## 2  Tech Stack

* PHP 8.2 (procedural, PDO)
* MariaDB 10.4
* Bootstrap 5 + FontAwesome
* Vanilla JS
* Runs on Apache/XAMPP (Windows/Linux/Mac)

---

## 3  Directory Overview

```
Systeme-de-Gestion-groupe-IKI/
│
├── *.php                 # Feature pages / controllers
├── groupe_iki (1).sql    # Original phpMyAdmin dump (schema + data)
├── schema_groupe_iki.sql # Clean schema (optional)
├── assets/
│   ├── css/
│   └── img/
└── README.md             # You are here
```

Key PHP pages:

* Auth: `login.php`, `logout.php`
* Dashboards: `dashboard_admin.php`, `dashboard_teacher.php`, `dashboard_student.php`
* Management: `manage_filieres_modules.php`, `manage_users.php`, `assign_teacher.php`, `manage_grades.php`
* Daily tasks: `record_absence.php`, `send_message.php`, `view_messages.php`, `view_grades.php`, `student_absences.php`

All pages load `config.php` for DB connection and use prepared statements.

---

## 4  Setup

1. Install XAMPP (PHP 8.2 / MariaDB).
2. Copy the folder into `htdocs` and start Apache & MySQL.
3. In phpMyAdmin, **import** `groupe_iki (1).sql` (preferred) or `schema_groupe_iki.sql` if you want a blank schema.
4. Update DB credentials in `config.php` if needed.
5. Browse to `http://localhost/Systeme-de-Gestion-groupe-IKI/login.php`.

### Default Accounts

| Role | CNI | Password |
|------|-----|----------|
| Admin | `A000001` | `admin` |
| Teachers | `T000001` … `T000006` | `teacher` |
| Students | `S100001` … | `student` |

Passwords are stored with `password_hash()` (bcrypt).

---

## 5  Important Notes

* Only **one grade value** is stored per student/module; the UI replicates it across CC1, CC2, CC3, Théorique & Pratique for standard modules.
* `messages` table is polymorphic: it can target an individual CNI, an entire filière (`target_classe_id` FK to `filieres.id`) or a module.
* Foreign keys protect integrity; most are `ON DELETE CASCADE` or `SET NULL`.
* Sessions enforce role-based access on every page.

---

## 6  Roadmap

* Convert to MVC or migrate to Laravel.
* Add email notifications.
* Export grades/attendance to CSV/PDF.
* Docker compose for one-command setup.

---

## 7  License

Proprietary – © Groupe IKI. All rights reserved.