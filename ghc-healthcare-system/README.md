# GHC - Graduation Research Healthcare System

## AI-Powered Healthcare Management System

### Overview
GHC is an integrated healthcare management system that connects three main parties:
- **Patients**: Book appointments and track their status
- **Doctors**: Receive patients and write prescriptions
- **Pharmacists**: Manage inventory and dispense medications

### Key Features
- **AI-Powered Priority Queue**: Intelligent patient prioritization based on condition severity
- **AI Treatment Suggestions**: Gemini API integration for medication recommendations
- **Bilingual Support**: Full Arabic and English support with RTL/LTR layouts
- **Real-time Inventory Management**: Stock tracking with low stock alerts
- **Secure Payment Processing**: Multiple payment methods and institution types
- **Comprehensive Audit Logging**: Full system activity tracking

### Technology Stack
- **Backend**: PHP 8 with OOP and PDO
- **Database**: MySQL 8 with InnoDB
- **Frontend**: HTML5, CSS3, Bootstrap 5
- **AI**: Google Gemini API
- **Security**: BCRYPT hashing, PDO prepared statements, XSS prevention

### Installation
1. Import `sql/healthcare-system.sql` into MySQL
2. Configure database credentials in `config/database.php`
3. Add Gemini API key to `config/ai_config.php`
4. Deploy to web server with PHP 8+ support
5. Ensure `mod_rewrite` is enabled for `.htaccess` protection

### Security Notes
- Move `config/ai_config.php` outside web root in production
- Enable SSL verification in `includes/ai_service.php` for production
- Regularly update dependencies and review system logs

### File Structure
```
/api/           - API endpoints
/config/        - Configuration files
/controllers/   - Business logic handlers
/includes/      - Helper functions and services
/models/        - Database models
/sql/           - Database schema
/translations/  - Language files
/views/         - User interface templates
```

### Authors
University Graduation Research Project Team
