# Complete Database Schema Documentation

## Overview
This document provides comprehensive documentation of all tables in the project_tracker database, including recent additions for project prompts, skills drills, and other features.

## Database Connection
- **Host**: 192.168.100.105
- **Port**: 3306
- **Database**: project_tracker
- **Character Set**: utf8mb4

## Table Categories

### 1. User Management Tables

#### users
Main user accounts table with extensive profile information and AI skill tracking.
- **Key Fields**: id, email, password_hash, first_name, last_name, slug
- **AI Skills**: Tracks current and goal levels for various AI competencies (ai_assisted_coding, mcp_servers, ai_automations, etc.)
- **Location**: Supports geographic data with latitude/longitude and privacy settings
- **Profile**: Includes bio, skills, availability, work preferences

#### user_roles
Maps users to roles within the system.

#### user_sessions
Tracks active user sessions.

#### user_settings
Stores user-specific settings and preferences.

#### user_community_roles
Maps users to roles within specific communities.

#### user_blocks
Manages user blocking relationships.

#### user_skills_drill_stats
Tracks user performance in skills drill exercises.

### 2. Project Management Tables

#### projects
Core projects table.
- **Key Fields**: id, title, description, status, owner_id
- **Features**: Categories, visibility settings, timestamps

#### project_members
Team member assignments to projects.

#### project_categories
Categorization system for projects.

#### project_skills
Skills required or developed by projects.

#### project_requirements
Detailed requirements for projects.

#### project_votes
Voting/rating system for projects.

#### project_surveys
Survey templates associated with projects.

#### project_survey_attributes
Custom attributes for project surveys.

### 3. Sprint and Agile Tables

#### sprints
Sprint cycles for agile development.

#### project_sprints
Links sprints to specific projects.

#### sprint_tasks
Tasks within a sprint.

#### sprint_work_items
Individual work items in sprints.

#### work_items
General work items that can be associated with sprints.

#### work_item_relations
Relationships between work items (blocks, depends on, etc.).

#### work_logs
Time tracking and work logs.

#### standups
Daily standup records.

### 4. AI Development Prompt Tables

#### project_dev_prompts
Stores development prompts for AI-assisted coding.
- **Fields**: 
  - id, project_id, parent_prompt_id (for chaining)
  - work_item_id, sprint_id (context links)
  - prompt_text, status (pending/executing/completed/failed/cancelled)
  - executed_at, completed_at (timestamps)
  - log_file_name, pid (execution tracking)
  - response_text, error_message (results)
- **Purpose**: Manages AI coding assistant prompts and their execution

#### projects_prompts
Alternative prompt storage system.
- **Fields**:
  - id, sprint_id, work_item_id
  - prompt_order, prompt_type, prompt_title
  - prompt_content, context, expected_outcome
  - dependencies (JSON), status
  - execution tracking fields
- **Purpose**: Structured prompt management with dependencies

### 5. Educational System Tables

#### courses
Main course catalog.

#### lessons
Individual lessons within courses.

#### course_categories
Hierarchical course categorization.

#### course_enrollments
User enrollment and progress tracking.

#### lesson_progress
Detailed lesson completion tracking.

#### course_comments
Discussion system for courses.

#### comment_likes
Engagement tracking for comments.

#### course_instructors
Instructor assignments and permissions.

#### course_skills
Skills taught by courses.

#### course_recommendations
Personalized course recommendations.

#### learning_analytics
Detailed learning behavior tracking.

### 6. Skills Drill System Tables

#### skills_drills
Defines skill practice exercises.
- **Fields**: id, lesson_id, title, description, instructions
- **Settings**: min/max questions per session, shuffle options
- **Purpose**: Configurable practice exercises linked to lessons

#### skills_drill_questions
Questions for skill drills.
- **Fields**: id, drill_id, question_text, question_type
- **Features**: Supports multiple question types, ordering, points

#### skills_drill_answer_options
Answer choices for drill questions.
- **Fields**: id, question_id, option_text, is_correct, explanation

#### skills_drill_sessions
Tracks individual practice sessions.
- **Fields**: id, drill_id, user_id, timestamps
- **Tracking**: questions presented/answered, points, status
- **Analytics**: IP address, user agent for session analysis

#### skills_drill_responses
Records individual question responses.
- **Fields**: session_id, question_id, selected_option_id
- **Tracking**: is_correct, points_earned, response_time

### 7. Assessment Tables

#### quizzes
Quiz definitions.

#### quiz_questions
Questions within quizzes.

#### quiz_answer_options
Answer choices for quiz questions.

#### quiz_attempts
User attempts at quizzes.

#### quiz_responses
Individual question responses in quiz attempts.

#### quiz_question_links
Links between questions and other entities.

#### question_bank
Reusable question repository.

#### question_bank_answers
Answers for question bank items.

### 8. Task Management Tables

#### tasks
General task tracking.

#### task_assignments
User assignments to tasks.

#### task_lesson_assignments
Links tasks to relevant lessons.

#### acceptance_criteria
Defines completion criteria for tasks.

### 9. Communication Tables

#### conversations
Private message conversations.

#### conversation_participants
Participants in conversations.

#### messages
Individual messages within conversations.

#### message_rate_limits
Anti-spam rate limiting.

#### notifications
System notifications.

### 10. Community Tables

#### communities
Community/group definitions.

#### community_members
Community membership tracking.

#### community_permissions
Permission settings for communities.

#### community_allowed_users
Whitelist for private communities.

#### community_auto_approvals
Auto-approval rules for communities.

#### community_invitations
Pending community invitations.

#### community_join_requests
Membership requests.

#### free_community_emails
Email domains allowed for free community access.

### 11. Ticket System Tables

#### tickets
Support ticket tracking.

#### ticket_categories
Ticket categorization.

#### ticket_assignments
Staff assignments to tickets.

#### ticket_replies
Responses to tickets.

#### ticket_attachments
File attachments for tickets.

#### ticket_status_history
Audit trail of ticket status changes.

### 12. Content Management Tables

#### blog_posts
Blog content management.

#### blog_categories
Blog categorization.

#### blog_post_categories
Many-to-many blog-category relationships.

#### blog_post_attachments
File attachments for blog posts.

#### blog_post_likes
Engagement tracking for blog posts.

#### blog_post_views
View count tracking for blog posts.

### 13. Calendar Tables

#### calendar_events
Event scheduling.

#### calendar_event_attendees
Event participant tracking.

#### calendar_event_reminders
Reminder settings for events.

### 14. Survey System Tables

#### surveys
Survey definitions.

#### survey_sections
Sections within surveys.

#### survey_questions
Questions within survey sections.

#### survey_answer_options
Answer choices for survey questions.

#### survey_responses
Individual survey responses.

#### survey_completions
Tracks completed surveys.

### 15. Version Control Tables

#### git_repositories
Git repository tracking.

#### git_branches
Branch tracking within repositories.

#### git_commits
Commit history tracking.

### 16. System Tables

#### system_config
System-wide configuration settings.

#### system_status
System health and status tracking.

#### migrations
Database migration history.

#### global_admins
System-wide administrator accounts.

#### admin_actions
Audit log of administrative actions.

#### role_change_log
Audit trail of role changes.

#### roles
Role definitions.

### 17. Feature Management Tables

#### features
Feature flag management.

#### feature_votes
User voting on features.

#### product_backlog
Product development backlog.

#### requirements_category
Categorization for requirements.

### 18. Other Tables

#### programs
Educational or training programs.

#### activities
General activity logging.

#### attachments
File attachment metadata.

#### file_attachments
Alternative file attachment system.

## Key Relationships

### User-Centric Relationships
- Users → Projects (ownership and membership)
- Users → Courses (enrollment and instruction)
- Users → Tasks (assignments)
- Users → Communities (membership)
- Users → Skills Drills (sessions and stats)

### Project Hierarchy
- Projects → Sprints → Work Items → Tasks
- Projects → Requirements → Acceptance Criteria
- Projects → Development Prompts (AI assistance)

### Educational Flow
- Courses → Lessons → Skills Drills
- Lessons → Quizzes → Questions
- Users → Enrollments → Progress

### Communication Networks
- Users → Conversations → Messages
- Courses/Lessons → Comments → Likes
- Communities → Members → Permissions

## Recent Additions

### AI Development Features
1. **project_dev_prompts**: Manages AI coding prompts with execution tracking
2. **projects_prompts**: Alternative prompt system with dependencies and context

### Skills Practice System
1. **skills_drills**: Configurable practice exercises
2. **skills_drill_questions**: Question bank for drills
3. **skills_drill_sessions**: Session tracking
4. **skills_drill_responses**: Response analytics

### Enhanced User Profiles
- AI skill tracking (current vs goal levels)
- Geographic location with privacy controls
- Availability and work preference settings
- Mentoring and leadership indicators

## Data Integrity Features

### Foreign Key Constraints
- Maintains referential integrity across all relationships
- Cascade deletes where appropriate
- Restrict deletes for critical relationships

### Unique Constraints
- Prevents duplicate enrollments, assignments, and memberships
- Ensures unique slugs for SEO-friendly URLs
- Maintains data consistency

### Indexes
- Performance indexes on frequently queried fields
- Composite indexes for complex queries
- Full-text indexes for search functionality

## Security Considerations

### Sensitive Data
- Passwords stored as hashes
- API keys stored securely
- Location privacy settings
- Rate limiting for messages

### Audit Trails
- Admin action logging
- Role change tracking
- Ticket status history
- Work logs

This documentation represents the complete database schema as of the latest update, including all recent additions for AI development features, skills practice systems, and enhanced user profiles.