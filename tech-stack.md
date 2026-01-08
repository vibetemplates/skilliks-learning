# Technology Stack Recommendations

## Overview

This document outlines the recommended technology stack for the Student Profile & Goal Tracking System. The approach has been updated to use modern, lightweight JavaScript libraries that provide better performance and developer experience while maintaining simplicity.

## Core Stack Components

### 1. Server Infrastructure

#### Operating System
- **Ubuntu Server 22.04 LTS** (preferred) or CentOS 8/Rocky Linux
- Rationale: Long-term support, extensive documentation, wide community support

#### Web Server
- **Apache 2.4.x**
- Modules required:
  - mod_rewrite (for clean URLs)
  - mod_ssl (for HTTPS)
  - mod_headers (for security headers)
  - mod_deflate (for compression)

#### Database
- **MariaDB 10.6+**
- Rationale: Drop-in MySQL replacement with better performance
- InnoDB storage engine for transactional support
- UTF8MB4 character set for full Unicode support

#### Backend Language
- **PHP 8.2+**
- Extensions required:
  - PDO and PDO_MySQL
  - mbstring (multi-byte string support)
  - openssl (encryption)
  - fileinfo (file upload validation)
  - gd or imagick (image processing)
  - json
  - session
  - ctype
  - filter

## Frontend Technologies (Updated)

### Core Framework
- **Bootstrap 5.3.x** (already included in design folder)
- Responsive grid system
- Pre-built components
- Utility classes for rapid development

### JavaScript Libraries

#### HTMX 1.9.x
- **Purpose**: Server-driven interactions without complex JavaScript
- **Benefits**:
  - Minimal JavaScript code required
  - HTML-centric approach
  - Progressive enhancement
  - Built-in AJAX functionality
  - Smaller learning curve than React/Vue
  - Works seamlessly with server-side rendering

#### Alpine.js 3.x
- **Purpose**: Lightweight reactive framework for UI interactions
- **Benefits**:
  - Declarative syntax similar to Vue.js
  - No build step required
  - Perfect companion to HTMX
  - Handles client-side state and interactions
  - Only 15KB minified

### Additional Libraries

1. **Alpine.js Plugins**
   - @alpinejs/persist (local storage persistence)
   - @alpinejs/focus (focus management)
   - @alpinejs/collapse (smooth transitions)

2. **SortableJS** (v1.15.x)
   - Drag-and-drop functionality
   - Works well with Alpine.js
   - No jQuery dependency

3. **Choices.js** (v10.x)
   - Enhanced select boxes
   - Search functionality
   - Multi-select support
   - Lightweight alternative to Select2

### Charts & Visualization
- **Chart.js 4.x**
- Lightweight and flexible
- Responsive charts
- No jQuery dependency

## Application Architecture

### PHP Structure (Simple MVC)
```
html/
├── index.php              # Main entry point
├── config/
│   ├── database.php       # Database configuration
│   ├── constants.php      # Application constants
│   └── functions.php      # Global helper functions
├── includes/
│   ├── header.php         # Common header (includes HTMX/Alpine)
│   ├── footer.php         # Common footer
│   ├── auth.php           # Authentication functions
│   └── session.php        # Session management
├── classes/
│   ├── Database.php       # Database connection class
│   ├── User.php           # User model
│   ├── Profile.php        # Profile model
│   ├── Survey.php         # Survey model
│   └── Resume.php         # Resume handling
├── pages/
│   ├── dashboard.php      # Main dashboard (HTMX/Alpine)
│   ├── profile.php        # Profile management
│   ├── surveys.php        # Survey management
│   └── reports.php        # Analytics/reports
├── partials/              # HTMX partial responses
│   ├── profile/
│   ├── survey/
│   └── dashboard/
├── api/
│   ├── auth.php           # Authentication endpoints
│   ├── profile.php        # Profile CRUD
│   ├── survey.php         # Survey operations
│   └── upload.php         # File upload handling
├── assets/
│   ├── css/               # Custom CSS files
│   ├── js/                # Custom JavaScript files
│   └── images/            # Application images
├── uploads/
│   └── resumes/           # Uploaded resumes (outside web root in production)
└── .htaccess              # Apache configuration
```

### Database Schema Overview
```sql
-- Core tables
users                 # User accounts
profiles              # Student profiles
skills                # Skill definitions
user_skills           # User-skill relationships
goals                 # Goal entries
surveys               # Survey definitions
survey_questions      # Survey questions
survey_responses      # Student responses
resumes               # Resume uploads
classes               # Class/course definitions
class_enrollments     # Student-class relationships
```

## Security Implementation

### PHP Security
1. **Password Hashing**
   ```php
   password_hash($password, PASSWORD_DEFAULT)
   password_verify($password, $hash)
   ```

2. **SQL Injection Prevention**
   - Use PDO prepared statements exclusively
   - Input validation and sanitization

3. **Session Security**
   ```php
   session_set_cookie_params([
       'lifetime' => 0,
       'path' => '/',
       'domain' => '',
       'secure' => true,
       'httponly' => true,
       'samesite' => 'Lax'
   ]);
   ```

4. **CSRF Protection**
   - Token generation for all forms
   - Token validation on submission
   - HTMX requests include CSRF tokens automatically

5. **File Upload Security**
   - File type validation
   - File size limits
   - Rename uploaded files
   - Store outside web root

### Frontend Security
1. **Input Validation**
   - HTML5 validation attributes
   - Alpine.js validation logic
   - Server-side validation as primary defense

2. **XSS Prevention**
   - htmlspecialchars() for output
   - Content Security Policy headers
   - HTMX's built-in XSS protection

## Development Tools

### Version Control
- **Git** with structured branching
- .gitignore for sensitive files

### Development Environment
- **XAMPP** or **MAMP** for local development
- Match production PHP/MySQL versions

### Code Quality
- **PHP CodeSniffer** for style consistency
- Basic PHPDoc documentation

## Deployment Strategy

### Simple Deployment
1. Development on local LAMP stack
2. Git push to repository
3. Git pull on production server
4. Run database migrations if needed
5. Clear caches

### Configuration Management
- Environment-specific config files
- .env file for sensitive data (not in Git)

## Performance Optimization

### Caching Strategy
1. **Browser Caching**
   - Apache mod_expires for static assets
   - Versioned asset URLs

2. **Database Optimization**
   - Proper indexing
   - Query optimization
   - Connection pooling

3. **PHP Performance**
   - OPcache enabled
   - Minimize file includes

### Asset Optimization
- Minified CSS/JS in production
- Image optimization
- Lazy loading with HTMX

## Monitoring & Maintenance

### Logging
- PHP error logging
- Apache access/error logs
- Application-level logging for debugging

### Backup Strategy
- Daily database backups
- Weekly full backups
- Automated backup scripts

## HTMX & Alpine.js Implementation Examples

### HTMX Form Submission
```html
<form hx-post="/api/profile.php" 
      hx-target="#profile-result"
      hx-swap="outerHTML"
      hx-indicator="#spinner">
    <input type="text" name="name" required>
    <button type="submit">Save Profile</button>
    <div id="spinner" class="htmx-indicator">Saving...</div>
</form>
<div id="profile-result"></div>
```

### Alpine.js Component
```html
<div x-data="{ 
    open: false, 
    skills: [],
    addSkill(skill) {
        this.skills.push(skill);
        this.$refs.skillInput.value = '';
    }
}">
    <button @click="open = !open">Toggle Skills</button>
    <div x-show="open" x-transition>
        <input x-ref="skillInput" @keyup.enter="addSkill($event.target.value)">
        <ul>
            <template x-for="skill in skills">
                <li x-text="skill"></li>
            </template>
        </ul>
    </div>
</div>
```

### Dynamic Table with HTMX
```html
<table>
    <tbody hx-get="/api/students.php" 
           hx-trigger="load, every 30s"
           hx-swap="innerHTML">
        <!-- Table rows loaded here -->
    </tbody>
</table>
```

### Alpine.js Data Persistence
```html
<div x-data="{ 
    preferences: $persist({ theme: 'light', language: 'en' })
}">
    <select x-model="preferences.theme">
        <option value="light">Light</option>
        <option value="dark">Dark</option>
    </select>
</div>
```

## Third-Party Services

### Email Service
- **PHPMailer** for email functionality
- SMTP configuration for reliability

### Resume Parsing (Optional)
- Simple regex-based parsing initially
- Can upgrade to API service later

## Migration from jQuery

### Migration Strategy
1. **Gradual Migration**: Page-by-page conversion
2. **Coexistence**: jQuery and HTMX/Alpine can work together
3. **Progressive Enhancement**: Start with server-side, add interactions

### Common Patterns Migration

#### jQuery AJAX → HTMX
```javascript
// Old jQuery
$.ajax({
    url: '/api/data',
    success: function(data) {
        $('#result').html(data);
    }
});
```
```html
<!-- New HTMX -->
<div hx-get="/api/data" hx-trigger="load"></div>
```

#### jQuery Events → Alpine.js
```javascript
// Old jQuery
$('#button').click(function() {
    $('#content').toggle();
});
```
```html
<!-- New Alpine.js -->
<div x-data="{ open: false }">
    <button @click="open = !open">Toggle</button>
    <div x-show="open">Content</div>
</div>
```

## Scalability Considerations

### Phase 1 (MVP)
- Single server deployment
- Shared hosting compatible

### Phase 2 (Growth)
- Separate database server
- CDN for static assets

### Phase 3 (Scale)
- Load balancing
- Redis for session storage
- Read replicas for database

## Cost Estimation

### Local Development (MAMP)
- **MAMP Configuration**: Used for local development
- **Web Server**: http://localhost:8888
- **MySQL Port**: 8888 (MAMP default)
- **Database**: students
- **Username**: students
- **Password**: #ClaudeCode123#

### Hosting Options
1. **Shared Hosting**: $10-20/month
   - Suitable for MVP
   - Limited resources

2. **VPS**: $20-40/month
   - Better performance
   - Full control

3. **Cloud (AWS/DigitalOcean)**: $40-100/month
   - Scalable
   - Professional grade

## Benefits of HTMX/Alpine.js Stack

1. **Reduced Complexity**
   - No build tools required
   - No complex state management
   - HTML-centric development

2. **Better Performance**
   - Smaller JavaScript payload (HTMX: 14KB, Alpine: 15KB)
   - Faster initial page loads
   - Less client-side processing

3. **Improved Developer Experience**
   - Easier to learn and maintain
   - Better integration with server-side rendering
   - Progressive enhancement by default

4. **SEO Friendly**
   - Server-side rendered content
   - Works without JavaScript
   - Better accessibility

## Conclusion

The migration to HTMX and Alpine.js represents a modern approach to web development that aligns with the project's goals of simplicity and maintainability. This stack provides:

- **Simplicity**: Minimal JavaScript, HTML-centric approach
- **Performance**: Smaller bundle sizes, faster loads
- **Maintainability**: Clear separation of concerns
- **Scalability**: Server-side rendering scales naturally
- **Modern UX**: Reactive interfaces without complexity

The combination of HTMX for server interactions and Alpine.js for client-side reactivity provides all the features of traditional SPAs while maintaining the simplicity of server-side applications.