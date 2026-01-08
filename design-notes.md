# Design Notes - Bootstrap 5 Standard Implementation

## Overview

This document outlines the UI/UX patterns for the Classroom Project Tracking Tool using standard Bootstrap 5 components and examples. All designs reference official Bootstrap 5 examples available at https://getbootstrap.com/docs/5.0/examples/

## Design Principles

1. **Simplicity First**: Use default Bootstrap components without customization
2. **Consistency**: Follow Bootstrap's design patterns throughout
3. **Accessibility**: Leverage Bootstrap's built-in accessibility features
4. **Mobile-First**: Utilize Bootstrap's responsive grid system
5. **Performance**: Use Bootstrap CDN for optimal loading

## Page Layouts

### 1. Authentication Pages (Login/Register)
**Reference**: https://getbootstrap.com/docs/5.0/examples/sign-in/
- Centered card layout
- Standard form controls
- Form validation states
- Remember me checkbox
- Link to alternate action (login/register)

### 2. Main Dashboard Layout
**Reference**: https://getbootstrap.com/docs/5.0/examples/dashboard/
- Fixed sidebar navigation
- Top navbar with search and user dropdown
- Main content area with responsive grid
- Breadcrumb navigation
- Card-based content sections

### 3. Project List View
**Reference**: https://getbootstrap.com/docs/5.0/examples/album/
- Grid of project cards
- Each card shows:
  - Project name
  - Description preview
  - Member count
  - Status badge
  - Action buttons

### 4. Kanban Board
**Reference**: https://getbootstrap.com/docs/5.0/examples/masonry/
- Column-based layout
- Draggable cards within columns
- Status columns (To Do, In Progress, Review, Done)
- Task cards with:
  - Title and ID
  - Assignee avatar
  - Priority badge
  - Due date

### 5. Forms and Modals
**Reference**: https://getbootstrap.com/docs/5.0/examples/checkout/
- Standard form layouts
- Floating labels for modern look
- Input groups for related fields
- Modal dialogs for:
  - Creating projects/tasks
  - Confirmations
  - Quick edits

## Component Patterns

### Navigation
- **Primary Nav**: Bootstrap navbar with brand, search, and user menu
- **Sidebar**: Vertical nav with icons and text
- **Breadcrumbs**: For hierarchical navigation
- **Tabs**: For switching between related views

### Data Display
- **Tables**: Responsive tables with hover states
- **Cards**: For individual items (projects, tasks, features)
- **Lists**: List groups for comments, activities
- **Badges**: For status, counts, labels

### Forms
- **Input Types**: Standard Bootstrap form controls
- **Validation**: Built-in validation classes
- **Help Text**: Form text for guidance
- **File Upload**: Custom file input styling

### Feedback
- **Alerts**: Dismissible alerts for messages
- **Toasts**: For transient notifications
- **Progress**: Progress bars for loading states
- **Spinners**: Loading indicators

## Color Scheme

Using Bootstrap 5 default theme colors:
- **Primary**: #0d6efd (Blue)
- **Secondary**: #6c757d (Gray)
- **Success**: #198754 (Green)
- **Danger**: #dc3545 (Red)
- **Warning**: #ffc107 (Yellow)
- **Info**: #0dcaf0 (Cyan)
- **Light**: #f8f9fa
- **Dark**: #212529

## Typography

Using Bootstrap's default typography stack:
- **Font Family**: System font stack
- **Base Size**: 1rem (16px)
- **Headings**: Bootstrap's default heading scales
- **Body Text**: Regular weight for readability

## Spacing and Layout

- **Grid**: 12-column responsive grid
- **Containers**: .container for fixed width, .container-fluid for full width
- **Spacing**: Use Bootstrap spacing utilities (p-*, m-*)
- **Gutters**: Default Bootstrap gutter widths

## Interactive Elements

### Buttons
- **Primary Actions**: .btn-primary
- **Secondary Actions**: .btn-secondary
- **Danger Actions**: .btn-danger
- **Sizes**: Regular, .btn-sm, .btn-lg

### Links
- **Primary Links**: Default Bootstrap link color
- **Navigation Links**: .nav-link styling
- **Breadcrumb Links**: Subdued until hover

### Form Controls
- **Focus States**: Bootstrap's default focus styling
- **Disabled States**: Reduced opacity
- **Error States**: .is-invalid with feedback

## Responsive Breakpoints

Following Bootstrap 5 defaults:
- **xs**: <576px (default)
- **sm**: ≥576px
- **md**: ≥768px
- **lg**: ≥992px
- **xl**: ≥1200px
- **xxl**: ≥1400px

## Implementation Examples

### Basic Page Structure
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Title</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- Content -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

### Dashboard Layout Reference
- Sidebar: Fixed position, 250px width
- Main content: Margin-left to accommodate sidebar
- Top navbar: Fixed top with shadow
- Content area: Container with padding

### Card Component Pattern
- Use .card with .card-body
- Optional .card-header and .card-footer
- Standard spacing with .mb-3 or .mb-4
- Consistent border and shadow

## Accessibility Considerations

1. Use semantic HTML elements
2. Include proper ARIA labels
3. Ensure keyboard navigation works
4. Maintain color contrast ratios
5. Provide alternative text for images
6. Use Bootstrap's screen reader utilities

## Performance Guidelines

1. Load Bootstrap from CDN
2. Minimize custom CSS
3. Use Bootstrap's utility classes
4. Lazy load images where appropriate
5. Minimize JavaScript interactions

## File Organization

```
/html/
  /assets/
    /images/     # User uploads, logos
  /includes/
    header.php   # Common header with navbar
    footer.php   # Common footer
    sidebar.php  # Reusable sidebar component
  /pages/
    login.php
    register.php
    dashboard.php
    projects.php
    tasks.php
    etc.
```

## Development Workflow

1. Start with Bootstrap examples
2. Use standard components
3. Apply utility classes for spacing/styling
4. Add minimal custom CSS only if necessary
5. Test responsive behavior
6. Validate accessibility

## References

- Bootstrap 5 Documentation: https://getbootstrap.com/docs/5.0/
- Bootstrap 5 Examples: https://getbootstrap.com/docs/5.0/examples/
- Bootstrap Icons: https://icons.getbootstrap.com/
- Bootstrap Utilities: https://getbootstrap.com/docs/5.0/utilities/api/

## Notes

- Avoid customizing Bootstrap variables
- Use CDN versions for consistency
- Follow Bootstrap naming conventions
- Leverage Bootstrap JavaScript plugins
- Keep custom CSS to absolute minimum