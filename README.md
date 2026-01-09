# Kpi_Management_Project
Backend System & Data Analytics Project:
Overview
The KPI Management Portal is an internal performance tracking system designed to collect, validate, aggregate, and report staff performance metrics on a weekly and monthly basis.
The system supports role-based access (staff vs administrators), structured KPI definitions, and department-level analytics to support data-driven decision-making.
This project was developed locally using PHP and MySQL and demonstrates both backend engineering and data analytics competencies.
________________________________________
My Role:
Back-End Developer & Database Designer
I was responsible for:
•	Designing the relational database schema
•	Implementing backend business logic
•	Writing analytical SQL queries for KPI aggregation
•	Handling role-based access control
•	Ensuring data integrity and security
________________________________________
🛠️ Tech Stack
•	Backend: PHP (PDO)
•	Database: MySQL
•	Frontend: HTML, Bootstrap
•	Security: CSRF protection, prepared statements
•	Environment: XAMPP (Localhost)
________________________________________
🗃️ Database Structure
Key tables include:
•	users – remembers roles, departments, approval status
•	departments – organisational units
•	metrics – KPI definitions and targets
•	weekly_kpis – raw performance data captured weekly
This structure supports time-based and department-based analytics.
________________________________________
📈 Data & Analytics Logic
The system performs:
•	Weekly data capture per staff
•	Monthly KPI aggregation using SQL (SUM, GROUP BY)
•	Department-level summaries via multi-table joins
•	Target vs actual performance comparison
•	Time-series analysis by month and year
These analytics workflows closely mirror real business intelligence processes.
________________________________________
🔐 Security & Best Practices
•	CSRF token validation
•	Prepared SQL statements to prevent injection
•	Role-based access restrictions
•	Server-side validation of KPI entries

