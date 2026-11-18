# CampusCare - College Complaint Management System

CampusCare is a web-based College Complaint Management System designed to simplify and digitize the process of submitting, managing, assigning, and tracking student complaints within a college.

The system provides separate dashboards for Students, Staff, and Administrators, ensuring secure access and efficient complaint resolution.

## Features

### Student Module

- Student registration and login
- Submit complaints online
- View submitted complaints
- Track complaint status
- Receive complaint updates and notifications
- Submit feedback after complaint resolution

### Staff Module

- Secure staff login
- View assigned complaints
- Update complaint status
- Add complaint progress updates
- Manage assigned complaints

### Admin Module

- Admin dashboard
- Manage student and staff accounts
- Assign complaints to staff members
- Monitor complaint progress
- View complaint analytics
- Generate staff performance reports
- View student feedback
- Monitor system activity

## Technologies Used

| Technology | Purpose |
| --- | --- |
| PHP | Backend development |
| MySQL / MariaDB | Database management |
| HTML5 | Page structure |
| CSS3 | Styling and responsive design |
| JavaScript | Client-side functionality |
| Bootstrap | UI components |
| XAMPP | Local development environment |
| Git & GitHub | Version control |

## Security Features

- Password hashing and verification
- Session-based authentication
- Role-based access control
- CSRF protection
- Complaint access restrictions
- Database foreign-key constraints
- Input validation and output escaping

## Project Structure

```text
complaint_portal/
|
├── admin/
├── staff/
├── assets/
├── config/
├── includes/
├── database/
├── index.php
├── login.php
├── register.php
├── logout.php
└── README.md
```

*Note: The structure above is representative. Adjust it to match the actual files in your repository.*

## Installation and Setup

### 1. Install XAMPP

Download and install XAMPP.

### 2. Clone the Repository

```text
git clone https://github.com/vamsi-bear/CampusCare.git
```

### 3. Move the Project

Place the project folder inside:

```text
C:\xampp\htdocs\
```

### 4. Start XAMPP

Open XAMPP Control Panel and start:

- Apache
- MySQL

### 5. Create the Database

1. Open `http://localhost/phpmyadmin/`.
2. Create a database named `complaint_portal`.
3. Import the project's SQL database file if one is included.
4. Configure your database connection in the appropriate configuration file.

### 6. Run the Application

Open your browser and visit:

```text
http://localhost/complaint_portal/
```

## Project Objectives

- Digitize the traditional college complaint process
- Improve communication between students and college administration
- Ensure transparent complaint tracking
- Simplify complaint assignment and resolution
- Improve accountability through staff performance monitoring
- Provide a centralized platform for complaint management

## Future Enhancements

- Email and SMS notifications
- AI-powered complaint categorization
- Complaint priority prediction
- Mobile application integration
- Advanced analytics and reporting

## Developed By

**CampusCare Project Team**

GitHub: [vamsi-bear](https://github.com/vamsi-bear)

Repository: [CampusCare](https://github.com/vamsi-bear/CampusCare)

---

If you find this project useful, consider giving the repository a star.
