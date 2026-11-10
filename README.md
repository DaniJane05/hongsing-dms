**A Delivery Management System Using Machine Learning For Hong Sing LKRR COMMERCIAL**
- is a web-based delivery management dveloped for Hong Sing LKRR Commercial. It aims to improve order processing, delivery assignment, route management, and delivery monitoring. The system separate access for Customers, Truck Drivers, Admin, and Super Admin.

**Group Members & Roles**
- Rivera, Danica Jane C. (Team Leader, Back-end Developer, Database Developer, QA Tester, and Document Specialist)
- Camandona, Allyssa Grace E. (Assistant Front-End / UI/UX Designer, and Researcher)
- Cugay, Sherilee C. (Assistant Researcher)
- Ibañez, Marianne B. (Front-End / UI/UX Designer)

**Tech Stack**
- Frontend: HTML, CSS, JavaScript
- Backend: PHP
- Database: MySQL / MariaDB
- Local Development Environment: XAMPP
- Database Management: phpMyAdmin
- Development Tools: Visual Studion Code, Git, and GitHub

**System Requirements & Prerequisites**
Before installing the project, ensure that your local machine has following:
- Windows, Linux, or macOS operating system
- XAMPP with PHP 8.2 or compatible version
- Apache Web Server
- MySQL or MariaDB
- phpMyAdmin
- Git
- Visual Studio Code or another code editor
- A modern web browser

**Installation & Setup Instructions**
1. Clone the Repository
Open your terminal or command prompt and execute:
**git clone https://github.com/YOUR-USERNAME/YOUR-REPOSITORY-NAME.git**
Navigate to the project directory
**cd hongsing-dms**

2. Place the Project in XAMPP
  1. Locate your XAMPP installation directory.
  2. Open the htdocs folder
  3. Place or clone the project inside htdocs
  4. Make sure the project foldr is named hongsing-dms
The expected directory should look like:
**c:\xampp\htdocs\hongsing-dms**

3. Start the Required Services
   1. Open the Xampp Control Panel
   2. Start Apache
   3. Start MySQL

**Database Setup & Configuration**
Follow these steps to configure the system database:

1. Open phpMyAdmin
Open your browser and navigate to:
**http://localhost/phpmyadmin**

2. Create the Database
  1. Select the Database tab.
  2. Create a new database named:
    **hongsing_dms**
  3. Use the database name exactly as specified.

3. Import the Database
   1. Select the hongsing_dms database
   2. Open the Import tab
   3. Select the SQL database backup file included in the repository.
   4. Click Import or Go.
   5. Wait until phpMyAdmin confirms that the import is successful

4. Configure the Database Connection
Open the project's database configuration file and verify the following connection settings:

Configuration      Value
Database Host      Localhost
Database Name      hongsing_dms
Database Username  root
Database Password  (local development setting)
Database Server    MySQL/MariaDB

**Running the Application**
1. Make sure Apache and MySQL are running in XAMPP
2. Open a web browser
3. Navigate to the following address:
   **http://localhost/hongsing-dms/**
4. The system login page should appear
5. Log in using an authorized account to access the corresponding system features.

**User Roles**
The system provides four types of user accounts:
- Customer - Can browse available products, add items to the shopping cart, place orders, and view order information.
- Truck Driver - Can access assigned delivery, update delivery statuses, and submit proof of delivery according to the available system functions.
- Admin - Can manage oorders, delivery assignments, products, and delivery-related operations.
- Super Admin - Can access higher-level administrative and system management features according to assigned permissions.

- **Default Login Credentials**
- For security purposes, default account credentials are not published in this repository.

- Authorized test accounts should be provided separately by the project administrators or evaluators when needed

- **Project Repository Structure**
- The following is an example of the expected repository organization. Adjust it to match the actual prohect folders.

- hongsing-dms/
│
├── admin/
├── customer/
├── driver/
├── super_admin/
├── assets/
├── includes/
├── uploads/
├── database/
│   └── [database-backup].sql
├── index.php
└── README.md

**Project Information**
- Project Title: A Delivery Management System using Machine Learning for Hong Sing LKRR Commercial
- Institution: Global Reciprocal Colleges
- Program: Bachelor of Science in Information Technology
- Repository: https://github.com/DaniJane05/hongsing-dms.git
