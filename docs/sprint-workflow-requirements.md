# Sprint Workflow Requirements

## Current Database Structure

### Existing Tables:
- **work_items**: Stores all work items (epics, stories, tasks, bugs, spikes)
  - Has sprint_id field for sprint assignment
  - Has status field but no explicit backlog status
  - Has project_id and community_id for project association
  
- **project_sprints**: Stores sprint information for projects
- **sprint_work_items**: Junction table linking work items to sprints
- **projects**: Has dev_system_url field for API integration
- **project_dev_prompts**: Stores prompts sent to development systems

### Missing Components:
1. **No explicit project backlog table** - Currently work items without sprint_id serve as backlog
2. **No approval workflow** - Work items go directly from creation to assignable status
3. **No sprint backlog view** - Sprint items are mixed with other statuses
4. **No prompt generation from sprint items** - No automated conversion of work items to prompts

## Required Workflow

### 1. Feature Request/User Story Creation
- User creates work item (type: story or feature)
- Initial status: "draft" or "proposed"
- Not yet in backlog

### 2. Approval Process
- User/Product Owner reviews proposed items
- Approves items for backlog
- Status changes to "backlog" or "approved"

### 3. Product Backlog Management
- Approved items appear in product backlog
- Can be prioritized and estimated
- Ready for sprint planning

### 4. Sprint Planning
- Items moved from product backlog to sprint
- Creates sprint backlog entry
- Status changes to "sprint_ready"

### 5. Sprint Execution Preparation
- For each item in sprint backlog:
  - Generate prompts based on work item details
  - Store prompts in project_dev_prompts table
  - Link prompts to sprint and work items

### 6. API Integration
- Send prompts to dev_system_url
- Track execution status
- Store responses and logs

## Database Schema Changes Needed

### 1. Add to work_items table:
```sql
ALTER TABLE work_items 
ADD COLUMN approval_status ENUM('draft', 'approved', 'rejected') DEFAULT 'draft',
ADD COLUMN approved_by INT UNSIGNED,
ADD COLUMN approved_at TIMESTAMP NULL,
ADD COLUMN backlog_priority INT DEFAULT 0,
ADD INDEX idx_approval_status (approval_status),
ADD INDEX idx_backlog_priority (backlog_priority);
```

### 2. Create project_backlog view:
```sql
CREATE VIEW project_backlog AS
SELECT * FROM work_items 
WHERE approval_status = 'approved' 
AND sprint_id IS NULL 
ORDER BY backlog_priority DESC, created_at ASC;
```

### 3. Create sprint_backlog view:
```sql
CREATE VIEW sprint_backlog AS
SELECT wi.*, s.name as sprint_name, s.status as sprint_status
FROM work_items wi
JOIN project_sprints s ON wi.sprint_id = s.id
WHERE wi.approval_status = 'approved'
ORDER BY wi.position ASC;
```

### 4. Add to project_dev_prompts:
```sql
ALTER TABLE project_dev_prompts
ADD COLUMN work_item_id INT UNSIGNED,
ADD COLUMN sprint_id INT UNSIGNED,
ADD CONSTRAINT fk_dev_prompts_work_item 
    FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_dev_prompts_sprint 
    FOREIGN KEY (sprint_id) REFERENCES project_sprints(id) ON DELETE SET NULL,
ADD INDEX idx_work_item_id (work_item_id),
ADD INDEX idx_sprint_id (sprint_id);
```

## Implementation Steps

### Phase 1: Database Updates
1. Add approval workflow fields to work_items
2. Create database views for backlogs
3. Update project_dev_prompts for work item integration

### Phase 2: UI Updates
1. Add approval UI to work-items.php
2. Create product backlog page
3. Create sprint backlog page
4. Add "Generate Prompts" button to sprint view

### Phase 3: Prompt Generation
1. Create PromptGenerator class
2. Convert work item details to prompts
3. Handle parent-child relationships
4. Include acceptance criteria in prompts

### Phase 4: API Integration
1. Create DevSystemAPI class
2. Send prompts to dev_system_url
3. Track execution status
4. Store responses and logs

## Work Item to Prompt Conversion Logic

### For User Stories:
```
As a [user type]
I want [functionality]
So that [business value]

Acceptance Criteria:
- [criteria 1]
- [criteria 2]

Technical Implementation:
[description and technical details]
```

### For Tasks:
```
Task: [title]
Description: [detailed description]
Dependencies: [parent items]
Technical Requirements: [implementation details]
```

### For Bugs:
```
Bug: [title]
Steps to Reproduce: [from description]
Expected Behavior: [from acceptance criteria]
Actual Behavior: [from description]
Fix: [implementation approach]
```

## API Endpoint Structure

### Send Prompt:
```
POST {dev_system_url}/api/prompts
{
    "project_id": "external_project_id",
    "prompt_id": "internal_prompt_id",
    "prompt_text": "formatted prompt",
    "context": "previous responses if any",
    "parent_prompt_id": "if applicable"
}
```

### Check Status:
```
GET {dev_system_url}/api/prompts/{prompt_id}/status
```

### Get Response:
```
GET {dev_system_url}/api/prompts/{prompt_id}/response
```

## Security Considerations

1. API Authentication
   - Store API keys securely
   - Use HTTPS for all communications
   - Implement request signing

2. Data Validation
   - Sanitize all prompt content
   - Validate responses
   - Log all transactions

3. Access Control
   - Only authorized users can approve items
   - Only project managers can generate prompts
   - Audit trail for all actions