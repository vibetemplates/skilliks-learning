# Classroom Project Tracking Tool

A web-based collaborative project management application designed for classroom environments where students can participate in software development projects, recommend features, and track their work.

## Technology Stack

- **Backend**: PHP 8.2+ with MVC pattern
- **Database**: MariaDB 10.6+
- **Frontend**: 
  - UI Framework: Bootstrap 5 (CDN)
  - JavaScript: HTMX 1.9.x for server-driven interactions
  - Reactivity: Alpine.js 3.x for client-side state management
  - Charts: Chart.js 4.x for data visualization
- **Web Server**: Apache 2.4
- **Architecture**: Server-side rendering with progressive enhancement

## Project Status

The project is actively migrating from jQuery to HTMX/Alpine.js for improved performance and maintainability. The following pages have been converted:
- dashboard.php
- login.php  
- projects.php
- work-items.php

## Key Features

- **User Roles**: Developer (Student), Project Manager, Administrator
- **Feature Recommendations**: Students can propose and vote on features
- **Task Management**: Kanban board with drag-and-drop functionality
- **Git Integration**: Automatic branch tracking and commit scanning
- **Sprint Management**: Agile workflow support
- **Real-time Updates**: Using HTMX for seamless server interactions

## Documentation

- **CLAUDE.md**: Instructions for Claude Code AI assistant
- **requirements.md**: Detailed functional and technical requirements
- **tech-stack.md**: Technology choices and migration guide
- **design-notes.md**: Architecture and design decisions

## Development Setup

1. Requires MAMP or similar LAMP stack
2. Database: students (MySQL port 8888)
3. Username: students / Password: #ClaudeCode123#
4. Web root: /var/www/html/

## Migration Notes

The project is transitioning from jQuery to modern, lightweight alternatives:
- **jQuery → HTMX**: For AJAX and server interactions
- **jQuery plugins → Alpine.js**: For reactive UI components
- **Select2 → Choices.js**: For enhanced select boxes
- **jQuery UI → SortableJS**: For drag-and-drop functionality

See tech-stack.md for detailed migration patterns and examples.