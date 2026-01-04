# Project Tracker

Full-stack project management app with vanilla PHP, MySQL, and JavaScript.

## Installation

1. Clone repo and place in `C:\xampp\htdocs\project-tracker\`
2. Import `project-tracker.sql` into MySQL
3. Update database credentials in `config/Database.php`
4. Start Apache & MySQL in XAMPP
5. Open `http://localhost/project-tracker/frontend/login.html`
6. Ensure you have an active internet connection when viewing the project on localhost.
   - Some images used in the project are loaded from online sources.
   - Internet access is required to display these images properly and to also prevent visual glitches.


## Usage

- **Register** a new account
- **Login** with your credentials
- **Create projects** with title, summary, and status
- **Edit/Delete** projects from dashboard
- **View activity logs** (admin only)
- **Filter** projects by status (Pending, Ongoing, Completed)

## Tech Stack

- Backend: PHP + MySQL
- Frontend: HTML5 + CSS3 + Vanilla JavaScript
- Auth: JWT tokens
- No frameworks used