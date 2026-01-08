# Classroom Project Tracking Tool - Requirements Document

## Project Overview

A web-based collaborative project management application designed for classroom environments where students can participate in software development projects, recommend features, and track their work. The system integrates with Git repositories to provide real-time visibility into development progress and facilitates structured project management workflows inspired by industry tools like Jira.

## Executive Summary

### Purpose
Create a simplified project management platform that enables students to:
- Sign up and participate in class projects
- Propose and vote on feature recommendations
- Track their work and view team progress
- Learn industry-standard project management practices
- Integrate with Git for code tracking

### Key Differentiators
- Educational focus with simplified workflows
- Git branch tracking for academic projects
- Three-tier role system for classroom management
- Feature recommendation democracy
- Real-time collaboration features

## User Roles & Permissions

### 1. Developer (Student)
**Primary Users**: Students enrolled in the class

**Permissions**:
- Create account and update profile
- Join available projects
- Submit feature recommendations
- Vote on feature proposals
- Create and update tasks
- Post work status updates
- Comment on any feature/task
- View project dashboards
- Track own Git branches

**Restrictions**:
- Cannot approve features
- Cannot delete others' content
- Cannot modify project settings
- Cannot assign tasks to others

### 2. Project Manager
**Primary Users**: Team leads, senior students, or designated project coordinators

**Permissions**:
- All Developer permissions
- Approve/reject feature recommendations
- Create and manage sprints
- Assign tasks to team members
- Modify task priorities and statuses
- Access project analytics
- Configure project settings
- Create milestones

**Restrictions**:
- Cannot create new projects
- Cannot manage users outside their project
- Cannot access system administration

### 3. Administrator
**Primary Users**: Instructors, teaching assistants

**Permissions**:
- Full system access
- Create and configure projects
- Manage all users and roles
- Access all analytics and reports
- System configuration
- Database maintenance
- Git repository configuration
- Emergency overrides

## Functional Requirements

### 1. Authentication & User Management

#### 1.1 Registration System
- **Student Registration**
  - Self-registration with .edu email validation
  - Required fields: Name, Email, Student ID, GitHub username
  - Optional: Profile photo, bio, skills
  - Email verification required
  - Automatic Developer role assignment

- **Account Features**
  - Password reset via email
  - Profile editing
  - Notification preferences
  - Session management
  - Remember me option

#### 1.2 Role Management
- Administrators can promote Developers to Project Managers
- Role changes logged in activity feed
- Bulk role assignment capabilities
- Role-based dashboard views

### 2. Project Management

#### 2.1 Project Structure
- **Project Creation** (Admin only)
  - Project name and description
  - Git repository URL
  - Team size limits
  - Start/end dates
  - Associated course information

- **Project Join Process**
  - Browse available projects
  - Request to join with message
  - Automatic or manual approval
  - Team formation deadlines

#### 2.2 Feature Recommendation System
- **Submission Process**
  - Title and detailed description
  - Category selection (Feature, Enhancement, Bug Fix)
  - Priority suggestion
  - Mockups/attachment support
  - Automatic author attribution

- **Voting Mechanism**
  - One vote per student per feature
  - Vote changing allowed
  - Comment with vote
  - Vote count visibility
  - Trending features dashboard

- **Approval Workflow**
  - Project Manager review queue
  - Approve/Reject/Request More Info
  - Approval comments required
  - Status change notifications
  - Approved features become tasks

### 3. Task Management

#### 3.1 Task Creation
- **From Approved Features**
  - Automatic task generation
  - Preserves original description
  - Links to feature recommendation

- **Manual Task Creation**
  - Quick task addition
  - Detailed form option
  - Subtask support

#### 3.2 Task Properties
- **Required Fields**
  - Title
  - Description
  - Type (Feature, Bug, Task, Improvement)
  - Priority (Critical, High, Medium, Low)
  - Status (To Do, In Progress, In Review, Done)

- **Optional Fields**
  - Assignee
  - Due date
  - Estimated hours
  - Labels/Tags
  - Sprint assignment
  - Dependencies

#### 3.3 Kanban Board
- **Board Layout**
  - Swimlanes by assignee or priority
  - Drag-and-drop task movement
  - Quick edit capabilities
  - Filter and search options

- **Card Display**
  - Task title and ID
  - Assignee avatar
  - Priority indicator
  - Due date warning
  - Comment count
  - Git branch indicator

### 4. Work Tracking

#### 4.1 Status Updates
- **Daily Standup Format**
  - What I completed yesterday
  - What I'm working on today
  - Any blockers
  - Auto-prompt if not submitted

- **Work Logs**
  - Start/stop timer
  - Manual time entry
  - Work description
  - Associated task linking

#### 4.2 Progress Tracking
- **Individual Metrics**
  - Tasks completed
  - Hours logged
  - Contribution graph
  - Streak tracking

- **Team Metrics**
  - Sprint velocity
  - Burndown charts
  - Task distribution
  - Bottleneck identification

### 5. Git Integration

#### 5.1 Repository Connection
- **Setup** (Admin/PM)
  - Repository URL configuration
  - Authentication tokens
  - Webhook configuration
  - Branch naming conventions

#### 5.2 Branch Tracking
- **Automatic Detection**
  - New branch creation
  - Active branches list
  - Last commit info
  - Branch age indicators

- **Branch-Task Linking**
  - Naming convention matching
  - Manual association
  - PR status tracking
  - Merge notifications

#### 5.3 Commit Integration
- **Commit Scanning**
  - Task ID detection in messages
  - Automatic activity logging
  - Contributor statistics
  - Code frequency graphs

### 6. Communication Features

#### 6.1 Comments System
- **Comment Features**
  - Rich text formatting
  - @mention notifications
  - Edit/delete own comments
  - Threading support
  - Reaction emojis

#### 6.2 Notifications
- **In-App Notifications**
  - Task assignments
  - Mentions
  - Status changes
  - Feature approvals

- **Email Notifications**
  - Configurable preferences
  - Daily digest option
  - Instant for critical items
  - Unsubscribe options

#### 6.3 Activity Feed
- **Project Activity**
  - Real-time updates
  - Filterable by type
  - User activity summaries
  - Exportable logs

### 7. Sprint Management

#### 7.1 Sprint Planning
- **Sprint Creation** (PM only)
  - Sprint name and goal
  - Start/end dates
  - Task selection
  - Capacity planning

#### 7.2 Sprint Execution
- **Sprint Board**
  - Current sprint focus
  - Daily progress updates
  - Impediment tracking
  - Sprint health indicators

#### 7.3 Sprint Review
- **Retrospective Tools**
  - What went well
  - What needs improvement
  - Action items
  - Team velocity calculation

### 8. Reporting & Analytics

#### 8.1 Dashboards
- **Project Dashboard**
  - Overall progress
  - Feature pipeline
  - Team activity
  - Upcoming deadlines

- **Personal Dashboard**
  - My tasks
  - Time tracking
  - Notifications
  - Quick actions

#### 8.2 Reports
- **Standard Reports**
  - Sprint reports
  - Velocity trends
  - Time tracking summaries
  - Feature completion rates

- **Custom Analytics**
  - Filter configurations
  - Chart customization
  - Export capabilities
  - Scheduled reports

### 9. Search & Discovery

#### 9.1 Global Search
- **Search Capabilities**
  - Full-text search
  - Filter by type
  - Date ranges
  - Advanced operators

#### 9.2 Filters
- **Saved Filters**
  - Personal filters
  - Shared team filters
  - Quick filter presets
  - Filter subscription

## Non-Functional Requirements

### 1. Performance
- Page load time < 2 seconds
- Support 200+ concurrent users
- Real-time updates < 500ms latency
- Search results < 1 second
- File upload support up to 10MB

### 2. Security
- HTTPS encryption mandatory
- Bcrypt password hashing
- Session token rotation
- SQL injection prevention
- XSS protection
- CSRF tokens
- Rate limiting on API endpoints
- Git token encryption

### 3. Usability
- Mobile-responsive design
- Intuitive navigation
- Contextual help system
- Keyboard shortcuts
- Accessibility compliance (WCAG 2.1 AA)
- Multi-browser support

### 4. Reliability
- 99.5% uptime target
- Automated backups every 6 hours
- Transaction logging
- Error recovery procedures
- Graceful degradation

### 5. Scalability
- Horizontal scaling ready
- Database connection pooling
- Caching strategy
- CDN integration capable
- Modular architecture

## Technical Requirements

### 1. Technology Stack
- **Backend**: PHP 8.2+ with MVC pattern
- **Database**: MariaDB 10.6+
- **Frontend**: 
  - UI Framework: Bootstrap 5 (CDN)
  - JavaScript: HTMX 1.9.x for server interactions
  - Reactivity: Alpine.js 3.x for client-side state
  - Charts: Chart.js 4.x for data visualization
- **Version Control**: Git integration
- **Web Server**: Apache 2.4
- **Architecture**: Server-side rendering with progressive enhancement

### 2. API Design
- RESTful API architecture
- JSON response format
- API versioning
- Rate limiting
- Authentication via tokens

### 3. Database Schema Overview

```sql
-- Core Tables
users                    # User accounts and authentication
user_roles              # Role assignments
projects                # Project definitions
project_members         # User-project relationships
features                # Feature recommendations
feature_votes           # Voting records
tasks                   # All work items
task_assignments        # Task-user relationships
sprints                 # Sprint definitions
sprint_tasks            # Sprint-task relationships
comments                # Universal comment system
activities              # Activity log
git_repositories        # Git configuration
git_branches            # Branch tracking
git_commits             # Commit history
notifications           # User notifications
work_logs               # Time tracking
attachments             # File uploads
```

### 4. Integration Requirements
- Git webhook receivers
- Email service (SMTP)
- File storage system
- Optional: Slack/Discord webhooks
- Optional: Calendar integration

## User Interface Requirements

### 1. Design System
- Use standard Bootstrap 5 components and utilities
- Default Bootstrap color scheme and typography
- Bootstrap responsive grid system
- Standard Bootstrap card components
- Bootstrap navbar and sidebar navigation patterns

### 2. Key Pages
- **Public**: Login, Registration, Password Reset
- **Dashboard**: Role-specific home pages
- **Projects**: List, Detail, Settings
- **Features**: Recommend, Browse, Vote
- **Tasks**: Kanban Board, List View, Detail
- **Reports**: Analytics, Charts, Exports
- **Profile**: User settings, Notifications
- **Admin**: User Management, System Config

### 3. Mobile Considerations
- Touch-friendly interfaces
- Simplified navigation
- Essential features prioritized
- Offline capability for reading
- Progressive web app ready

### 4. UI Implementation Guidelines
- Use Bootstrap 5 CDN (no local copies needed)
- Utilize default Bootstrap components without customization
- Follow Bootstrap's standard color palette:
  - Primary: Bootstrap blue (#0d6efd)
  - Success: Bootstrap green (#198754)
  - Danger: Bootstrap red (#dc3545)
  - Warning: Bootstrap yellow (#ffc107)
  - Info: Bootstrap cyan (#0dcaf0)
- Use Bootstrap utility classes for spacing and layout
- No custom CSS except for minor layout adjustments
- Standard Bootstrap navbar with sidebar layout
- Bootstrap form components with validation states
- Bootstrap modal dialogs for all popups
- Bootstrap alerts for all notifications

## Implementation Phases

### Phase 1: Core MVP (Weeks 1-4)
- User authentication and roles
- Project creation and joining
- Basic task management
- Simple Kanban board
- Comment system

### Phase 2: Collaboration (Weeks 5-7)
- Feature recommendation system
- Voting mechanism
- Git branch tracking
- Activity feeds
- Notifications

### Phase 3: Advanced Features (Weeks 8-10)
- Sprint management
- Analytics dashboards
- Time tracking
- Advanced search
- Email integration

### Phase 4: Polish (Weeks 11-12)
- Performance optimization
- UI/UX improvements
- Documentation
- Testing and bug fixes
- Deployment preparation

## Acceptance Criteria

### Functional Testing
- [ ] All three user roles can log in and see appropriate dashboards
- [ ] Students can successfully join projects
- [ ] Feature recommendations can be submitted and voted on
- [ ] Project Managers can approve/reject features
- [ ] Tasks can be created, assigned, and tracked
- [ ] Kanban board supports drag-and-drop
- [ ] Git branches are automatically detected
- [ ] Comments and mentions trigger notifications
- [ ] Sprint planning and execution works
- [ ] Reports generate accurate data

### Performance Testing
- [ ] Pages load within 2 seconds
- [ ] Concurrent user target met
- [ ] Search returns results quickly
- [ ] No memory leaks detected
- [ ] Database queries optimized

### Security Testing
- [ ] No unauthorized access possible
- [ ] SQL injection attempts blocked
- [ ] XSS attempts sanitized
- [ ] Passwords properly hashed
- [ ] Sessions expire appropriately

### Usability Testing
- [ ] Mobile responsive on all devices
- [ ] Intuitive navigation confirmed
- [ ] Accessibility standards met
- [ ] Help documentation complete
- [ ] User feedback incorporated

## Constraints & Assumptions

### Constraints
- Must use existing LAMP infrastructure
- Use standard Bootstrap 5 UI framework (no custom themes)
- 12-week development timeline
- Educational environment focus
- Single Git provider initially

### Assumptions
- Students have basic Git knowledge
- .edu email addresses available
- Modern browsers only
- English language only (initially)
- Stable internet connectivity

## Success Metrics

### Quantitative
- 90% of students successfully register
- 80% feature recommendation participation
- Average 5+ tasks completed per student
- < 2 second page load times
- 99.5% uptime achieved

### Qualitative
- Positive user feedback
- Improved project collaboration
- Better code organization
- Enhanced learning outcomes
- Reduced project management overhead

## Risk Management

### Technical Risks
- Git integration complexity
- Performance at scale
- Data migration challenges
- Third-party service dependencies

### Mitigation Strategies
- Incremental Git feature rollout
- Caching and optimization
- Thorough backup procedures
- Fallback for external services

## Glossary

- **Sprint**: Fixed time period for completing tasks (usually 1-2 weeks)
- **Kanban Board**: Visual task management board with columns
- **Feature**: Proposed new functionality for the project
- **Velocity**: Measure of team productivity over time
- **Burndown Chart**: Graph showing remaining work in sprint
- **Stand-up**: Daily status update meeting/report
- **Blocker**: Issue preventing task progress
- **Story Points**: Relative effort estimation unit

## Appendices

### A. User Story Examples
1. "As a student, I want to recommend features so that the project meets user needs"
2. "As a project manager, I want to approve features so that development stays focused"
3. "As an administrator, I want to track all projects so that I can monitor class progress"

### B. API Endpoint Examples
- `GET /api/projects` - List all projects
- `POST /api/features` - Submit feature recommendation
- `PUT /api/tasks/{id}` - Update task status
- `GET /api/git/branches` - List active branches

### C. Notification Examples
- "You've been assigned to task #123"
- "Your feature recommendation was approved"
- "New comment on your task"
- "Sprint starts tomorrow"

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-07-11 | Claude Code | Initial requirements document |
| 1.1 | 2025-07-13 | Claude Code | Updated to use standard Bootstrap 5 instead of custom template |

---

**Note**: This requirements document is designed for educational use in a classroom setting. It simplifies many aspects of professional project management tools while maintaining core functionality that provides valuable learning experiences for students.