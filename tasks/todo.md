# Change New Project Modal to Full Page

## Overview
Convert the "New Project" button on project-categories.php from opening a modal dialog to navigating to a full-page form.

## TODO List

- [x] Create new create-project.php full page with form
- [x] Update New Project button to link to new page instead of modal
- [ ] Test the new page functionality
- [ ] Document changes in docs/activity.md

## Technical Details

### Changes Made
1. Created `/var/www/html/create-project.php` - Full page form based on category-add.php design pattern
   - Uses same form fields as the modal had
   - Processes POST submissions on the same page
   - Redirects to project detail page on success
   - Shows errors inline at the top of the form

2. Modified `/var/www/html/project-categories.php`
   - Changed "New Project" button from `data-bs-toggle="modal"` to regular link
   - Removed the modal HTML and JavaScript
   - Button now navigates to `create-project.php`

### Design Pattern
Following the existing codebase pattern:
- Full page form in card layout
- Centered content (col-md-8 mx-auto)
- Cancel button returns to project-categories
- Submit button creates project
- All unique IDs added for easy styling/reference

## Review
(To be completed after testing)
